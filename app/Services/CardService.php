<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Equipment;
use App\Models\EquipmentTransaction;
use App\Models\Inventory;
use App\Models\Supply;
use App\Models\SupplyRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Digital Stock Cards (SC) and Property Cards (PC).
 *
 * A card is a projection, not a second ledger. Every line on a Stock Card comes
 * from a real `inventory` movement, and every line on a Property Card comes from
 * a real document line or from a posting an officer made on the card itself.
 * Because each projected line remembers the source row it came from (with a
 * unique index), re-syncing a card can never double-post a receipt.
 *
 * Balances are always recalculated from the posted lines in chronological order
 * and are never accepted from the browser.
 */
class CardService
{
    /**
     * Create a card for an item and post whatever history already exists for it.
     *
     * $source names the paper (or other record) the card was opened from, and
     * only shows up in the activity log — "Created Property Card PC-2026-00003
     * for \"Laptop Computer\" from PAR-2026-00007."
     *
     * @param  array{entity_name?:string|null,fund_cluster?:string|null,reorder_point?:int|null}  $header
     */
    public function create(string $type, Supply|Equipment $item, array $header = [], ?string $source = null): Card
    {
        $wantsStockCard = $type === 'SC';
        $isSupply = $item instanceof Supply;

        if ($wantsStockCard !== $isSupply) {
            throw ValidationException::withMessages([
                'type' => $wantsStockCard
                    ? 'A Stock Card can only be created for a supply item.'
                    : 'A Property Card can only be created for a piece of equipment.',
            ]);
        }

        $existing = Card::query()
            ->when($wantsStockCard, fn ($q) => $q->where('supply_id', $item->id))
            ->when(! $wantsStockCard, fn ($q) => $q->where('equipment_id', $item->id))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'type' => "A {$existing->typeLabel()} already exists for this item ({$existing->card_no}).",
            ]);
        }

        $card = DB::transaction(function () use ($type, $item, $header, $wantsStockCard) {
            $card = Card::create([
                'type' => $type,
                'card_no' => Card::nextCardNumber($type),
                'supply_id' => $wantsStockCard ? $item->id : null,
                'equipment_id' => $wantsStockCard ? null : $item->id,
                'entity_name' => $header['entity_name'] ?? Card::DEFAULT_ENTITY,
                'fund_cluster' => $header['fund_cluster'] ?? null,
                // The SC prints a re-order point; it defaults to the threshold the
                // supply already carried, and is kept on the card from then on.
                'reorder_point' => $wantsStockCard
                    ? ($header['reorder_point'] ?? ($item->reorder_level ?: null))
                    : null,
                'created_by' => auth()->id(),
            ]);

            $this->sync($card);

            return $card;
        });

        ActivityLog::record('created', 'cards', sprintf(
            'Created %s %s for "%s"%s.',
            $card->typeLabel(),
            $card->card_no,
            $card->itemName(),
            $source ? " from {$source}" : '',
        ));

        return $card;
    }

    /**
     * Post any source rows that are not on the card yet. Idempotent.
     *
     * @return int number of lines added
     */
    public function sync(Card $card): int
    {
        // A stock card mirrors the stock ledger, including the quantity that was
        // already on hand before any movement was recorded (an item seeded or
        // created with stock but no receipt). Its lines keep the ledger's own
        // balance, which is why they are never re-derived here: summing them from
        // zero would make the first line of such an item negative.
        if ($this->cardIsStockCard($card)) {
            $added = $this->projectInventory($card);
            // Balances on an already projected line are restored from the ledger
            // it mirrors, so re-syncing repairs a card instead of only adding to it.
            $this->restoreStockCardBalances($card);

            return $added;
        }

        // A property card draws on both equipment ledgers: the PAR/ICS/IAR lines
        // of finalized documents, and the receipts recorded on the equipment
        // record itself through Add Quantity. It owns its own ledger, so its
        // balances are re-derived from the lines in date order.
        $added = $this->projectDocumentItems($card) + $this->projectEquipmentTransactions($card);

        // Re-balanced even when nothing new was posted, so pressing Sync also
        // repairs the balances of a card that was written under an older rule.
        $this->recalculateBalances($card);

        return $added;
    }

    /**
     * Post a transaction by hand (an issue the system has no RIS for, a
     * transfer, a disposal, or a correcting adjustment).
     *
     * @param  array{transaction_date:string,type:string,reference?:string|null,quantity:int,office?:string|null,amount?:numeric-string|float|null,remarks?:string|null}  $data
     */
    public function post(Card $card, array $data): CardTransaction
    {
        $isStockCard = $this->cardIsStockCard($card);
        $type = $data['type'];
        $quantity = (int) $data['quantity'];
        $incoming = $type === 'receipt';
        $allowIncoming = $isStockCard
            ? ['receipt', 'issue', 'adjustment']
            : ['receipt', 'issue', 'transfer', 'disposal'];

        if (! in_array($type, $allowIncoming, true)) {
            throw ValidationException::withMessages(['type' => 'That transaction type is not valid for this card.']);
        }

        return DB::transaction(function () use ($card, $data, $isStockCard, $type, $quantity, $incoming) {
            // Lock the card so two postings cannot read the same balance.
            $card = Card::query()->lockForUpdate()->findOrFail($card->id);

            $card->loadMissing('supply', 'equipment');

            // Normalised to a plain date: the date cast stores a datetime, so raw
            // string comparisons would treat "2026-09-23" as earlier than
            // "2026-09-23 00:00:00".
            $date = Carbon::parse($data['transaction_date'])->toDateString();

            // A stock card mirrors the stock ledger, whose balances are already
            // recorded as of each movement: its lines must stay in the order the
            // ledger wrote them, so a stock card posting can only move forward.
            if ($isStockCard) {
                $lastDate = $card->transactions()->max('transaction_date');
                $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : null;

                if ($lastDate && $date < $lastDate) {
                    throw ValidationException::withMessages([
                        'transaction_date' => sprintf(
                            'A stock card posting cannot be dated before the last posted transaction (%s). Post it today or later.',
                            Carbon::parse($lastDate)->format('m/d/y'),
                        ),
                    ]);
                }
            }

            // The balance a posting can draw on: everything posted up to its date.
            $available = $isStockCard
                ? $card->balance()
                : $this->propertyBalanceAsOf($card, $date);

            if (! $incoming && $quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Insufficient stock. Available balance: %d %s. Requested %s: %d.',
                        $available,
                        $card->unit(),
                        $type,
                        $quantity,
                    ),
                ]);
            }

            $inventoryId = null;
            // A receipt always adds. Only a *stock* card's issue consumes: on a
            // property card an issue, transfer or disposal moves custody of
            // property the entity still holds, so the balance does not drop.
            $newBalance = $incoming
                ? $available + $quantity
                : ($isStockCard ? $available - $quantity : $available);

            // A stock card posting is a real stock movement: write the inventory
            // row and move the supply's balance in the same transaction, so the
            // card and the stock on hand can never drift apart.
            if ($isStockCard) {
                $inventoryId = $this->recordInventoryMovement($card, $data, $incoming, $quantity, $newBalance);
            }

            $line = CardTransaction::create([
                'card_id' => $card->id,
                'transaction_date' => $date,
                'type' => $type,
                'reference' => $data['reference'] ?? null,
                'receipt_qty' => $incoming ? $quantity : 0,
                'issue_qty' => $incoming ? 0 : $quantity,
                'office' => $incoming ? null : ($data['office'] ?? null),
                'balance_qty' => $newBalance,
                'amount' => $incoming ? ($data['amount'] ?? null) : null,
                'remarks' => $data['remarks'] ?? null,
                'inventory_id' => $inventoryId,
                'created_by' => auth()->id(),
            ]);

            // A property card owns its own ledger, so a line dated into the
            // middle of the history re-balances the lines that follow it.
            if (! $isStockCard) {
                $this->recalculateBalances($card);
                $line = $line->fresh();
            }

            ActivityLog::record(
                'posted',
                'cards',
                sprintf(
                    '%s %s %s on %s %s%s.',
                    $incoming ? 'Received' : 'Issued',
                    $quantity,
                    $card->unit(),
                    $card->typeLabel(),
                    $card->card_no,
                    $card->itemName() ? " for \"{$card->itemName()}\"" : '',
                ),
            );

            return $line;
        });
    }

    /**
     * Estimated days the current balance will last, from the card's own issue
     * history. Returns null when there is not enough history to be honest about.
     *
     * @return array<int,int|null> keyed by card transaction id
     */
    public function daysToConsume(Card $card): array
    {
        $lines = $card->transactions()->reorder()->orderBy('transaction_date')->orderBy('id')->get();

        if ($lines->count() < 2) {
            return [];
        }

        $estimate = [];
        $firstIssueDate = null;
        $issuedSoFar = 0;
        $issueMovements = 0;

        foreach ($lines as $line) {
            if ($line->issue_qty > 0) {
                $firstIssueDate ??= $line->transaction_date;
                $issuedSoFar += $line->issue_qty;
                $issueMovements++;
            }

            // Consumption is only knowable once the office has actually issued
            // the item more than once over a measurable span. The rate comes from
            // issues only — idle months with no movement must not dilute it.
            $span = $firstIssueDate ? $firstIssueDate->diffInDays($line->transaction_date) : 0;
            $estimate[$line->id] = ($issueMovements >= 2 && $span >= 1 && $issuedSoFar > 0)
                ? (int) floor($line->balance_qty / ($issuedSoFar / $span))
                : null;
        }

        return $estimate;
    }

    /**
     * Property still held as of a date — what a posting on that date can draw on.
     *
     * Property is not consumed by being issued: a laptop handed to a custodian,
     * transferred to another office or written off is still property the entity
     * received, so only the receipts count and the balance never shrinks. The
     * issue/transfer/disposal lines record who holds it, not what is left.
     */
    private function propertyBalanceAsOf(Card $card, string $date): int
    {
        // Deliberately first() and not value(): value() re-selects the alias on its
        // own, and SQLite then reads "balance" as a string literal instead of an alias.
        $row = $card->transactions()
            ->reorder()
            // whereDate, not where: the column holds a datetime, and a plain
            // comparison would exclude lines dated on the same day.
            ->whereDate('transaction_date', '<=', $date)
            ->selectRaw('coalesce(sum(receipt_qty), 0) as balance')
            ->first();

        return (int) ($row->balance ?? 0);
    }

    /**
     * Difference between the card's closing balance and the stock on hand.
     * Reported, never silently corrected.
     */
    public function reconciliationGap(Card $card): ?int
    {
        if (! $card->supply) {
            return null;
        }

        $onHand = (int) $card->supply->quantity_on_hand;
        $onCard = $card->balance();

        return $onHand === $onCard ? null : $onHand - $onCard;
    }

    /* ------------------------------------------------------------------ */
    /* Projection from the real ledgers                                    */
    /* ------------------------------------------------------------------ */

    /** Post every inventory movement for the card's supply that is not posted yet. */
    private function projectInventory(Card $card): int
    {
        $already = CardTransaction::where('card_id', $card->id)
            ->whereNotNull('inventory_id')
            ->pluck('inventory_id')
            ->all();

        $movements = Inventory::query()
            ->where('supply_id', $card->supply_id)
            ->when($already, fn ($q) => $q->whereNotIn('id', $already))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            return 0;
        }

        // Resolve the reference document for each movement once, not per row.
        $references = $this->resolveReferences($movements);
        $added = 0;

        foreach ($movements as $movement) {
            $incoming = $movement->quantity > 0;
            $reference = $references[$movement->id] ?? [];

            // forceFill, not create(): a projected line keeps the source row's
            // timestamps, which are not mass-assignable.
            (new CardTransaction)->forceFill([
                'card_id' => $card->id,
                'transaction_date' => $movement->created_at?->toDateString() ?? now()->toDateString(),
                'type' => $incoming ? 'receipt' : 'issue',
                // The document the movement belongs to: the linked requisition or
                // document when there is one, else the reference typed on the
                // stock-in (the IAR / PO the delivery came in on).
                'reference' => $reference['reference'] ?? $movement->reference,
                'receipt_qty' => $incoming ? abs($movement->quantity) : 0,
                'issue_qty' => $incoming ? 0 : abs($movement->quantity),
                'office' => $incoming ? null : ($reference['office'] ?? null),
                'balance_qty' => $movement->balance_after,
                'amount' => $incoming ? $movement->total_cost : null,
                'remarks' => $movement->remarks,
                'inventory_id' => $movement->id,
                'created_by' => $movement->user_id,
                'created_at' => $movement->created_at,
                'updated_at' => $movement->updated_at,
            ])->save();

            $added++;
        }

        return $added;
    }

    /** Post every finalized document line for the card's equipment that is not posted yet. */
    private function projectDocumentItems(Card $card): int
    {
        $already = CardTransaction::where('card_id', $card->id)
            ->whereNotNull('document_item_id')
            ->pluck('document_item_id')
            ->all();

        $items = DocumentItem::query()
            ->with('document')
            ->where('equipment_id', $card->equipment_id)
            // A Property Card records property: PAR/ICS/IAR are the PPE source
            // documents. A RIS is a supply release and must not be posted here,
            // even when a line of it points at an equipment record.
            ->whereHas('document', fn ($q) => $q->whereIn('type', ['PAR', 'ICS', 'IAR']))
            // Only a finalized document posts to the card; a draft does not.
            ->whereHas('document', fn ($q) => $q->whereIn('status', ['approved', 'issued']))
            ->when($already, fn ($q) => $q->whereNotIn('id', $already))
            ->orderBy('id')
            ->get();

        $added = 0;

        foreach ($items as $item) {
            $document = $item->document;

            if (! $document) {
                continue;
            }

            (new CardTransaction)->forceFill([
                'card_id' => $card->id,
                'transaction_date' => $document->document_date?->toDateString() ?? now()->toDateString(),
                'type' => 'receipt',
                'reference' => $document->document_no,
                'receipt_qty' => $item->quantity,
                'issue_qty' => 0,
                'office' => null,
                'balance_qty' => 0, // recalculated below, in date order
                'amount' => $item->amount,
                'remarks' => $item->item_remarks,
                'document_item_id' => $item->id,
                'created_by' => $document->issued_by ?? $document->approved_by ?? $document->prepared_by,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
            ])->save();

            $added++;
        }

        return $added;
    }

    /**
     * Post every receipt recorded on the equipment record itself that is not on
     * the card yet. The unique index on `equipment_transaction_id` makes this
     * idempotent, so re-syncing can never post the same receipt twice.
     */
    private function projectEquipmentTransactions(Card $card): int
    {
        if (! $card->equipment_id) {
            return 0;
        }

        $already = CardTransaction::where('card_id', $card->id)
            ->whereNotNull('equipment_transaction_id')
            ->pluck('equipment_transaction_id')
            ->all();

        $receipts = EquipmentTransaction::query()
            ->where('equipment_id', $card->equipment_id)
            ->when($already, fn ($q) => $q->whereNotIn('id', $already))
            ->orderBy('id')
            ->get();

        $added = 0;

        foreach ($receipts as $receipt) {
            $incoming = $receipt->quantity >= 0;

            (new CardTransaction)->forceFill([
                'card_id' => $card->id,
                'transaction_date' => $receipt->transaction_date?->toDateString() ?? now()->toDateString(),
                'type' => $incoming ? 'receipt' : 'issue',
                'reference' => $receipt->reference,
                'receipt_qty' => $incoming ? abs((int) $receipt->quantity) : 0,
                'issue_qty' => $incoming ? 0 : abs((int) $receipt->quantity),
                'office' => null,
                'balance_qty' => 0, // recalculated below, in date order
                'amount' => $incoming ? $receipt->total_cost : null,
                'remarks' => $receipt->remarks,
                'equipment_transaction_id' => $receipt->id,
                'created_by' => $receipt->user_id,
                'created_at' => $receipt->created_at,
                'updated_at' => $receipt->updated_at,
            ])->save();

            $added++;
        }

        return $added;
    }

    /**
     * Put a stock card's balances back in step with the stock ledger it mirrors.
     *
     * The ledger stores the quantity that was on hand after each movement, which
     * includes stock that predates the ledger (an item created or seeded with a
     * quantity and no receipt). Those values are the card's own record, so they are
     * copied back rather than re-derived from the lines.
     */
    private function restoreStockCardBalances(Card $card): void
    {
        $lines = $card->transactions()->reorder()->whereNotNull('inventory_id')->get();

        if ($lines->isEmpty()) {
            return;
        }

        $balances = Inventory::query()
            ->whereIn('id', $lines->pluck('inventory_id'))
            ->pluck('balance_after', 'id');

        foreach ($lines as $line) {
            $recorded = $balances[$line->inventory_id] ?? null;

            if ($recorded !== null && (int) $line->balance_qty !== (int) $recorded) {
                $line->forceFill(['balance_qty' => (int) $recorded])->saveQuietly();
            }
        }
    }

    /**
     * Recalculate every balance on the card from the posted lines, in date order.
     *
     * Only a property card uses this: its lines are its own ledger. A stock card
     * mirrors the stock ledger and is restored from it instead. Property is not
     * consumed by being issued, transferred or disposed of, so a receipt is the
     * only line that moves the balance; the rest record where the property went.
     */
    private function recalculateBalances(Card $card): void
    {
        $lines = $card->transactions()->reorder()->orderBy('transaction_date')->orderBy('id')->get();
        $balance = 0;

        foreach ($lines as $line) {
            $balance += (int) $line->receipt_qty;

            // A safety net rather than an expected state: receipts can no longer
            // be outrun, but `balance_qty` is unsigned, so a negative running
            // total is refused here instead of surfacing as a database error.
            if ($balance < 0) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'This would leave the card with a negative balance (%d %s). Record the receipt it came from first.',
                        $balance,
                        $card->unit(),
                    ),
                ]);
            }

            if ((int) $line->balance_qty !== $balance) {
                $line->forceFill(['balance_qty' => $balance])->saveQuietly();
            }
        }
    }

    /**
     * Reference label + issuing office for a batch of inventory movements.
     *
     * @param  \Illuminate\Support\Collection<int,Inventory>  $movements
     * @return array<int,array{reference:string|null,office:string|null}>
     */
    private function resolveReferences($movements): array
    {
        $requestIds = $movements->where('reference_type', SupplyRequest::class)->pluck('reference_id')->filter()->unique();
        $documentIds = $movements->where('reference_type', Document::class)->pluck('reference_id')->filter()->unique();

        $ris = $requestIds->isEmpty()
            ? collect()
            : Document::query()
                ->where('type', 'RIS')
                ->where('source_type', SupplyRequest::class)
                ->whereIn('source_id', $requestIds)
                ->get()
                ->keyBy('source_id');

        $requesters = $requestIds->isEmpty()
            ? collect()
            : SupplyRequest::with('requestedBy')->whereIn('id', $requestIds)->get()->keyBy('id');

        $documents = $documentIds->isEmpty()
            ? collect()
            : Document::whereIn('id', $documentIds)->get()->keyBy('id');

        $out = [];

        foreach ($movements as $movement) {
            $reference = null;
            $office = null;

            if ($movement->reference_type === SupplyRequest::class && $movement->reference_id) {
                $reference = $ris->get($movement->reference_id)?->document_no;
                $request = $requesters->get($movement->reference_id);
                // Issued to: the RIS office when it names one, else the requester.
                $office = $ris->get($movement->reference_id)?->office
                    ?: $request?->requestedBy?->name;
            } elseif ($movement->reference_type === Document::class && $movement->reference_id) {
                $reference = $documents->get($movement->reference_id)?->document_no;
            }

            $out[$movement->id] = ['reference' => $reference, 'office' => $office];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Writes back into the stock ledger                                   */
    /* ------------------------------------------------------------------ */

    /** Record a card posting in the `inventory` ledger and move the stock on hand. */
    private function recordInventoryMovement(Card $card, array $data, bool $incoming, int $quantity, int $newBalance): int
    {
        $supply = Supply::query()->lockForUpdate()->findOrFail($card->supply_id);
        $amount = isset($data['amount']) && $data['amount'] !== null ? round((float) $data['amount'], 2) : null;
        $unitCost = $incoming && $amount !== null ? round($amount / max($quantity, 1), 2) : null;

        $supply->update([
            'quantity_on_hand' => $newBalance,
            // Keep the recorded purchase price in step with the newest receipt.
            ...($unitCost !== null ? ['unit_cost' => $unitCost] : []),
        ]);

        $movement = Inventory::create([
            'supply_id' => $supply->id,
            'type' => $incoming ? 'in' : 'out',
            'quantity' => $incoming ? $quantity : -$quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $incoming ? $amount : null,
            'balance_after' => $newBalance,
            // These movements originate on a card, not on a requisition.
            'reference_type' => Card::class,
            'reference_id' => $card->id,
            'remarks' => $data['remarks'] ?? null,
            'user_id' => auth()->id(),
        ]);

        return $movement->id;
    }

    private function cardIsStockCard(Card $card): bool
    {
        return $card->type === 'SC';
    }

    /* ------------------------------------------------------------------ */
    /* Best-effort hooks used by the existing stock flows                  */
    /* ------------------------------------------------------------------ */

    /**
     * Re-post a supply's cards after its stock moved. Failures are logged, never
     * thrown: a card projection must not be able to break a stock transaction,
     * and any missed line is recoverable by re-syncing the card.
     */
    public function syncCardsForSupply(int|Supply $supply): void
    {
        $this->syncQuietly(fn () => $this->eachCard(
            Card::where('supply_id', $supply instanceof Supply ? $supply->id : $supply)->get(),
        ), 'supply', $supply instanceof Supply ? $supply->id : $supply);
    }

    public function syncCardsForEquipment(int|Equipment $equipment): void
    {
        $this->syncQuietly(fn () => $this->eachCard(
            Card::where('equipment_id', $equipment instanceof Equipment ? $equipment->id : $equipment)->get(),
        ), 'equipment', $equipment instanceof Equipment ? $equipment->id : $equipment);
    }

    /**
     * Open the card every item on a document needs, so writing a PAR / ICS / RIS
     * files the paper and its Stock Card / Property Card together instead of
     * leaving the card to be created by hand.
     *
     * An item that already has its card (opened by hand or by an earlier
     * document) keeps that one card — there is exactly one per item — and is
     * re-synced instead. A hand-typed line carries no linked record, so there is
     * nothing to card; a RIS line that points at a piece of equipment is skipped
     * like it is on the card itself, because a RIS is a supply release.
     *
     * Failures are logged, never thrown: the paper is already saved by the time
     * this runs, and the cards can always be re-synced afterwards.
     */
    public function ensureCardsForDocument(Document $document): void
    {
        $this->syncQuietly(fn () => $this->ensureCards($document), 'document', $document->id);
    }

    /**
     * Re-post the cards of the items that appear on a document, opening any that
     * is still missing. Called when a document is saved, approved or issued.
     */
    public function syncCardsForDocument(Document $document): void
    {
        $this->syncQuietly(fn () => $this->ensureCards($document), 'document', $document->id);
    }

    /**
     * The cards of the items on a document, in line order and without repeats.
     *
     * @return array<int,Card>
     */
    private function ensureCards(Document $document): array
    {
        $document->loadMissing('items.supply', 'items.equipment');

        $cards = [];

        foreach ($document->items as $item) {
            $card = $this->ensureCardForItem($document, $item);

            if ($card) {
                $cards[$card->id] = $card;
            }
        }

        return array_values($cards);
    }

    /** The card this document line needs, opening it when the item has none. */
    private function ensureCardForItem(Document $document, DocumentItem $item): ?Card
    {
        [$type, $linked] = match (true) {
            $item->supply_id !== null => ['SC', $item->supply],
            $item->equipment_id !== null && $document->type !== 'RIS' => ['PC', $item->equipment],
            default => [null, null],
        };

        if (! $type || ! $linked) {
            return null; // A hand-typed line, or a link whose item has been deleted.
        }

        $card = Card::query()
            ->when($type === 'SC', fn ($q) => $q->where('supply_id', $linked->id))
            ->when($type === 'PC', fn ($q) => $q->where('equipment_id', $linked->id))
            ->first();

        if ($card) {
            $this->sync($card);

            return $card;
        }

        return $this->create($type, $linked, [
            'entity_name' => $document->entity_name,
            'fund_cluster' => $document->fund_cluster,
        ], $document->document_no);
    }

    private function eachCard(iterable $cards): void
    {
        foreach ($cards as $card) {
            $this->sync($card);
        }
    }

    private function syncQuietly(callable $callback, string $source, int $id): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning('Card sync failed', [
                'source' => $source,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Cards are an add-on table: behave as a no-op before the migration runs. */
    private function tablesReady(): bool
    {
        static $ready = null;

        return $ready ??= \Illuminate\Support\Facades\Schema::hasTable('cards')
            && \Illuminate\Support\Facades\Schema::hasTable('card_transactions');
    }
}
