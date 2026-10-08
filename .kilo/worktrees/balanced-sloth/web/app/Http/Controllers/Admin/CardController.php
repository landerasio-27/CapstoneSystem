<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Supply;
use App\Services\CardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cards — the digital Stock Card (SC) and Property Card (PC) register.
 *
 * The card itself is only a header; its lines are projected from the stock
 * ledger and from PAR/ICS/IAR document lines by CardService, so nothing here
 * ever types a balance.
 */
class CardController extends Controller
{
    public function __construct(private readonly CardService $cards) {}

    public function index(Request $request): View
    {
        return $this->listing($request, null);
    }

    /** Sidebar shortcut: the Stock Cards subset of the register. */
    public function stock(Request $request): View
    {
        return $this->listing($request, 'SC');
    }

    /** Sidebar shortcut: the Property Cards subset of the register. */
    public function property(Request $request): View
    {
        return $this->listing($request, 'PC');
    }

    private function listing(Request $request, ?string $type): View
    {
        $type ??= in_array($request->query('type'), Card::TYPES, true) ? $request->query('type') : null;

        $cards = Card::with(['supply', 'equipment', 'creator'])
            // The register is the working list: filed cards live in the archive.
            ->notArchived()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('card_no', 'like', "%{$search}%")
                        ->orWhereHas('supply', fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%"))
                        ->orWhereHas('equipment', fn ($e) => $e->where('name', 'like', "%{$search}%")->orWhere('property_number', 'like', "%{$search}%"));
                });
            })
            // Date range over the *last activity* — the same date the Last
            // activity column shows — computed as a subquery so cards are
            // filtered in SQL rather than one query per row. A card with no
            // transactions has no last activity, so it falls outside any range.
            ->when($request->filled('date_from') || $request->filled('date_to'), function ($q) use ($request) {
                $lastActivity = CardTransaction::query()
                    ->selectRaw('MAX(transaction_date)')
                    ->whereColumn('card_id', 'cards.id');

                $q->when($request->filled('date_from'), fn ($sub) => $sub->where($lastActivity, '>=', $request->date_from))
                    ->when($request->filled('date_to'), fn ($sub) => $sub->where($lastActivity, '<=', $request->date_to));
            })
            ->orderBy('card_no')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'all' => Card::notArchived()->count(),
            'SC' => Card::notArchived()->where('type', 'SC')->count(),
            'PC' => Card::notArchived()->where('type', 'PC')->count(),
        ];

        // The page's Add Card modal offers both forms, so it needs the same item
        // lists the standalone create page has.
        return view('admin.cards.index', compact('cards', 'counts', 'type') + $this->formData());
    }

    /**
     * Step one of Add Card is the type chooser; passing ?type=SC|PC moves to the
     * form, where the item is picked from the existing supplies/equipment.
     */
    public function create(Request $request): View
    {
        $type = $request->query('type');

        if (! in_array($type, Card::TYPES, true)) {
            return view('admin.cards.create', ['type' => null]);
        }

        return view('admin.cards.create', ['type' => $type] + $this->formData());
    }

    /**
     * Item lists and header defaults shared by the create page and the Cards
     * page modal. One card per item: anything already carded is not offered
     * again, and neither is an item with nothing on hand — a card written before
     * the first receipt would have no lines. An item appears here as soon as its
     * first Add Stock / Add Quantity is recorded.
     *
     * @return array{supplies:\Illuminate\Support\Collection,equipment:\Illuminate\Support\Collection,defaults:array}
     */
    private function formData(): array
    {
        $cardedSupplies = Card::whereNotNull('supply_id')->pluck('supply_id')->all();
        $cardedEquipment = Card::whereNotNull('equipment_id')->pluck('equipment_id')->all();

        return [
            'supplies' => Supply::with('room')->where('quantity_on_hand', '>', 0)->orderBy('name')->get()
                ->reject(fn (Supply $s) => in_array($s->id, $cardedSupplies, true)),
            'equipment' => Equipment::where('quantity_on_hand', '>', 0)->orderBy('name')->get()
                ->reject(fn (Equipment $e) => in_array($e->id, $cardedEquipment, true)),
            'defaults' => $this->headerDefaults(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:SC,PC'],
            'item' => ['required', 'string'],
            'entity_name' => ['required', 'string', 'max:255'],
            'fund_cluster' => ['nullable', 'string', 'max:255'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
        ]);

        [$kind, $id] = array_pad(explode(':', $data['item'], 2), 2, null);

        $item = match ($kind) {
            'supply' => Supply::find($id),
            'equipment' => Equipment::find($id),
            default => null,
        };

        if (! $item) {
            return back()->withInput()->withErrors(['item' => 'Select a valid supply or equipment item.']);
        }

        if ($data['type'] === 'SC' && ! $item instanceof Supply) {
            return back()->withInput()->withErrors(['item' => 'A Stock Card can only be created for a supply.']);
        }

        if ($data['type'] === 'PC' && ! $item instanceof Equipment) {
            return back()->withInput()->withErrors(['item' => 'A Property Card can only be created for a piece of equipment.']);
        }

        $card = $this->cards->create($data['type'], $item, [
            'entity_name' => $data['entity_name'],
            'fund_cluster' => $data['fund_cluster'] ?? null,
            'reorder_point' => $data['reorder_point'] ?? null,
        ]);

        $posted = $card->transactions()->count();

        return redirect()->route('admin.cards.show', $card)->with('success', sprintf(
            '%s %s created for "%s".%s',
            $card->typeLabel(),
            $card->card_no,
            $card->itemName(),
            $posted > 0
                ? " {$posted} existing transaction(s) were posted to the card."
                : ' No stock movements on record yet.',
        ));
    }

    public function show(Card $card): View
    {
        $card->load(['supply.room', 'equipment.room', 'creator']);

        $transactions = $card->transactions()->with('creator')->paginate(20);

        return view('admin.cards.show', [
            'card' => $card,
            'transactions' => $transactions,
            'daysToConsume' => $this->cards->daysToConsume($card),
            'gap' => $this->cards->reconciliationGap($card),
            // The days column is meaningless with no issues on record.
            'issueCount' => $card->transactions()->where('issue_qty', '>', 0)->count(),
        ]);
    }

    public function edit(Card $card): View
    {
        $card->load(['supply', 'equipment']);

        return view('admin.cards.edit', compact('card'));
    }

    /** Header fields only: the posted lines are history and are never edited. */
    public function update(Request $request, Card $card): RedirectResponse
    {
        $data = $request->validate([
            'entity_name' => ['required', 'string', 'max:255'],
            'fund_cluster' => ['nullable', 'string', 'max:255'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
        ]);

        $card->update([
            'entity_name' => $data['entity_name'],
            'fund_cluster' => $data['fund_cluster'] ?? null,
            'reorder_point' => $card->isStockCard() ? ($data['reorder_point'] ?? null) : null,
        ]);

        ActivityLog::record('updated', 'cards', "Updated {$card->typeLabel()} {$card->card_no}.");

        return redirect()->route('admin.cards.show', $card)->with('success', "{$card->typeLabel()} {$card->card_no} updated.");
    }

    /** Post a line by hand: an issue without a RIS, a transfer, a disposal or an adjustment. */
    public function storeTransaction(Request $request, Card $card): RedirectResponse
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'type' => ['required', 'in:receipt,issue,transfer,disposal,adjustment'],
            'reference' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'office' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], [], [
            'quantity' => 'quantity',
            'transaction_date' => 'date',
        ]);

        if (! $card->isStockCard() && $data['type'] === 'adjustment') {
            return back()->withInput()->withErrors(['type' => 'A Property Card records receipts, issues, transfers and disposals.']);
        }

        if ($card->isStockCard() && in_array($data['type'], ['transfer', 'disposal'], true)) {
            return back()->withInput()->withErrors(['type' => 'A Stock Card records receipts and issues. Use an adjustment to correct a balance.']);
        }

        $line = $this->cards->post($card, $data);

        return redirect()->route('admin.cards.show', $card)->with('success', sprintf(
            '%s posted: %s %s. Balance is now %d %s.',
            $line->typeLabel(),
            $line->receipt_qty > 0 ? '+' : '-',
            $line->receipt_qty > 0 ? $line->receipt_qty : $line->issue_qty,
            $line->balance_qty,
            $card->unit(),
        ));
    }

    /** Re-post anything the source ledgers gained since the card was last touched. */
    public function sync(Card $card): RedirectResponse
    {
        $added = $this->cards->sync($card);

        return redirect()->route('admin.cards.show', $card)->with('success', $added > 0
            ? "{$added} new transaction(s) posted from the source records."
            : 'The card is already up to date.');
    }

    /** The official form, printable straight from the browser. */
    public function print(Card $card): View
    {
        $card->load(['supply.room', 'equipment.room']);

        return view('admin.cards.print', [
            'card' => $card,
            'daysToConsume' => $this->cards->daysToConsume($card),
        ]);
    }

    public function pdf(Card $card): Response
    {
        $card->load(['supply.room', 'equipment.room']);

        $pdf = Pdf::loadView('admin.cards.pdf', [
            'card' => $card,
            'daysToConsume' => $this->cards->daysToConsume($card),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("{$card->card_no}.pdf");
    }

    /**
     * Entity name + fund cluster as the office last recorded them, so the card
     * header is never invented while still being editable.
     *
     * @return array{entity_name:string,fund_cluster:string|null}
     */
    private function headerDefaults(): array
    {
        $recent = Document::query()
            ->whereNotNull('fund_cluster')
            ->latest('document_date')
            ->first();

        return [
            'entity_name' => $recent?->entity_name ?: Card::DEFAULT_ENTITY,
            'fund_cluster' => $recent?->fund_cluster,
        ];
    }
}
