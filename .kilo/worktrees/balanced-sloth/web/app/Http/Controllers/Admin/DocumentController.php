<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Equipment;
use App\Models\Inventory;
use App\Models\Room;
use App\Models\Supply;
use App\Models\Teacher;
use App\Services\CardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(private readonly CardService $cards) {}

    public function index(Request $request): View
    {
        $documents = Document::with(['teacher', 'room', 'preparedBy'])
            // The register is the working list: filed documents live in the archive.
            ->notArchived()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where('document_no', 'like', "%{$request->search}%"))
            // Date range over the document date, inclusive on both ends.
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('document_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('document_date', '<=', $request->date_to))
            ->latest('document_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.documents.index', compact('documents'));
    }

    /**
     * The catalogue the item picker offers, per document type: a PAR is a
     * property receipt so it lists equipment only, while an ICS/RIS records
     * supplies being issued to a custodian or office. IAR is generated as page
     * two of another document rather than filled in on its own, so it keeps
     * both. The unused query is never run.
     *
     * Only items with something on hand are offered: an item is created with a
     * quantity of zero and reaches the shelf through Add Stock / Add Quantity,
     * so listing it before then would offer stock that does not exist. Items
     * already on the document being edited are kept regardless (see $keep).
     *
     * @param  \Illuminate\Support\Collection<int,int>|null  $keep  linked item ids to keep listed
     * @return array{supplies: \Illuminate\Support\Collection, equipment: \Illuminate\Support\Collection}
     */
    private function itemCatalogue(string $type, array $keep = []): array
    {
        $inStock = function ($query) use ($keep) {
            return $query->where(function ($q) use ($keep) {
                $q->where('quantity_on_hand', '>', 0);

                if ($keep) {
                    $q->orWhereIn('id', $keep);
                }
            });
        };

        $supplies = fn () => $inStock(Supply::query())->orderBy('name')->get();
        $equipment = fn () => $inStock(Equipment::query())->orderBy('name')->get();

        return match ($type) {
            'PAR' => ['supplies' => collect(), 'equipment' => $equipment()],
            'ICS', 'RIS' => ['supplies' => $supplies(), 'equipment' => collect()],
            default => ['supplies' => $supplies(), 'equipment' => $equipment()],
        };
    }

    public function create(Request $request): View
    {
        $type = $request->get('type', 'PAR');

        return view('admin.documents.create', $this->itemCatalogue($type) + [
            'type' => $type,
            // Shown as the placeholder on the Document No. field: the number the
            // office would get by leaving it blank.
            'suggestedDocumentNo' => Document::suggestedDocumentNumber($type),
            'rooms' => Room::orderBy('name')->get(),
            'custodians' => Teacher::orderBy('last_name')->orderBy('first_name')->get(),
            // "Add Document" inside a custodian's folder opens the form with that
            // custodian already chosen, so the paper is filed into the folder the
            // moment it is saved.
            'preselectedCustodian' => Teacher::whereKey($request->integer('teacher_id'))->value('id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(Document::TYPES)],
            // Optional: the office may number the paper themselves. Left blank,
            // the next number for the type is assigned on save; typed in, it is
            // kept as given and must not already be on file.
            'document_no' => ['nullable', 'string', 'max:255', Rule::unique('documents', 'document_no')],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'document_date' => ['required', 'date'],
            'entity_name' => ['nullable', 'string', 'max:255'],
            'fund_cluster' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],

            // IAR header
            'supplier' => ['nullable', 'string', 'max:255'],
            'po_no' => ['nullable', 'string', 'max:255'],
            'po_date' => ['nullable', 'date'],
            'invoice_no' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'requisition_office' => ['nullable', 'string', 'max:255'],
            'requisition_center_code' => ['nullable', 'string', 'max:255'],
            'date_inspected' => ['nullable', 'date'],
            'date_received' => ['nullable', 'date'],
            'acceptance_status' => ['nullable', Rule::in(['complete', 'partial'])],
            // Name printed on the IAR's "Inspection Officer" signature line.
            'inspection_officer' => ['nullable', 'string', 'max:255'],

            // RIS header
            'division' => ['nullable', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'responsibility_center_code' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],

            // Signatory overrides (PAR/ICS/RIS)
            'requested_by' => ['nullable', 'string', 'max:255'],
            'received_by' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.item' => ['required', 'string', $this->existingItemRule()],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.date_acquired' => ['nullable', 'date'],
            'items.*.estimated_useful_life' => ['nullable', 'string', 'max:255'],
            'items.*.stock_available' => ['nullable', Rule::in(['yes', 'no'])],
            'items.*.issued_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.item_remarks' => ['nullable', 'string', 'max:255'],
        ], [], ['document_no' => 'document number']);

        $document = DB::transaction(function () use ($data) {
            $header = collect($data)->only([
                'entity_name', 'fund_cluster', 'remarks',
                'supplier', 'po_no', 'po_date', 'invoice_no', 'invoice_date',
                'requisition_office', 'requisition_center_code', 'date_inspected', 'date_received', 'acceptance_status',
                'inspection_officer',
                'division', 'office', 'responsibility_center_code', 'purpose',
                'requested_by', 'received_by',
            ])->filter(fn ($v) => $v !== null && $v !== '')->all();

            $document = Document::create(array_merge($header, [
                'document_no' => trim((string) ($data['document_no'] ?? '')) !== ''
                    ? trim($data['document_no'])
                    : Document::nextDocumentNumber($data['type']),
                'type' => $data['type'],
                'status' => 'draft',
                'teacher_id' => $data['teacher_id'] ?? null,
                'room_id' => $data['room_id'] ?? null,
                'prepared_by' => auth()->id(),
                'document_date' => $data['document_date'],
            ]));

            $this->saveItems($document, $data['items']);

            return $document;
        });

        ActivityLog::record('created', 'documents', "Created {$document->type} document {$document->document_no}.");

        return redirect()->route('admin.documents.show', $document)->with('success', "{$document->type} {$document->document_no} created successfully.");
    }

    public function show(Document $document): View
    {
        $document->load(['teacher', 'room', 'preparedBy', 'approvedBy', 'issuedBy', 'items.equipment', 'items.supply', 'parent', 'children']);

        return view('admin.documents.show', compact('document'));
    }

    public function edit(Document $document): View
    {
        abort_unless($document->status === 'draft', 403, 'Only draft documents can be edited.');

        $document->load('items');

        // The lines already on this draft stay in the picker even if their stock
        // has since run out, so editing cannot silently drop an item.
        $keep = $document->items->pluck('supply_id')->filter()->merge(
            $document->items->pluck('equipment_id')->filter(),
        )->unique()->all();

        return view('admin.documents.edit', $this->itemCatalogue($document->type, $keep) + [
            'document' => $document,
            'rooms' => Room::orderBy('name')->get(),
            'custodians' => Teacher::orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        abort_unless($document->status === 'draft', 403, 'Only draft documents can be edited.');

        $data = $request->validate([
            // Editable so a number typed wrong at creation can be corrected; it
            // still has to be unique across the register (the row's own number
            // is ignored, otherwise saving an unchanged form would collide).
            // Blank keeps the number the document already carries, so a caller
            // that does not send the field cannot erase it.
            'document_no' => ['nullable', 'string', 'max:255', Rule::unique('documents', 'document_no')->ignore($document->id)],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'document_date' => ['required', 'date'],
            'entity_name' => ['nullable', 'string', 'max:255'],
            'fund_cluster' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'po_no' => ['nullable', 'string', 'max:255'],
            'po_date' => ['nullable', 'date'],
            'invoice_no' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'requisition_office' => ['nullable', 'string', 'max:255'],
            'requisition_center_code' => ['nullable', 'string', 'max:255'],
            'date_inspected' => ['nullable', 'date'],
            'date_received' => ['nullable', 'date'],
            'acceptance_status' => ['nullable', Rule::in(['complete', 'partial'])],
            'inspection_officer' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'responsibility_center_code' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item' => ['required', 'string', $this->existingItemRule()],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.date_acquired' => ['nullable', 'date'],
            'items.*.estimated_useful_life' => ['nullable', 'string', 'max:255'],
            'items.*.stock_available' => ['nullable', Rule::in(['yes', 'no'])],
            'items.*.issued_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.item_remarks' => ['nullable', 'string', 'max:255'],
        ], [], ['document_no' => 'document number']);

        DB::transaction(function () use ($document, $data) {
            $headerKeys = [
                'entity_name', 'fund_cluster', 'remarks',
                'supplier', 'po_no', 'po_date', 'invoice_no', 'invoice_date',
                'requisition_office', 'requisition_center_code', 'date_inspected', 'date_received', 'acceptance_status',
                'inspection_officer',
                'division', 'office', 'responsibility_center_code', 'purpose',
                'requested_by', 'received_by',
            ];
            $header = collect($headerKeys)->mapWithKeys(fn ($k) => [$k => $data[$k] ?? null])->all();

            // entity_name is NOT NULL in the schema, so leaving the field blank
            // keeps the stored value rather than failing the update.
            if (blank($header['entity_name'])) {
                $header['entity_name'] = $document->entity_name;
            }

            $document->update(array_merge($header, [
                'document_no' => trim((string) ($data['document_no'] ?? '')) !== ''
                    ? trim($data['document_no'])
                    : $document->document_no,
                'teacher_id' => $data['teacher_id'] ?? null,
                'room_id' => $data['room_id'] ?? null,
                'document_date' => $data['document_date'],
            ]));

            $document->items()->delete();
            $this->saveItems($document, $data['items']);
        });

        ActivityLog::record('updated', 'documents', "Updated {$document->type} document {$document->document_no}.");

        return redirect()->route('admin.documents.show', $document)->with('success', "{$document->type} {$document->document_no} updated.");
    }

    /**
     * Persist a document's line items. A line may be a supply or a piece of
     * equipment, so the picker value (“supply:12” / “equipment:5”) decides
     * which record is linked; the printed fields are copied from that record.
     * The per-type extras (date acquired, useful life, stock/issue details) are
     * optional and saved when present.
     */
    private function saveItems(Document $document, array $items): void
    {
        foreach ($items as $item) {
            [$kind, $record] = $this->resolveItem((string) $item['item']);

            $document->items()->create([
                'supply_id' => $kind === 'supply' ? $record->id : null,
                'equipment_id' => $kind === 'equipment' ? $record->id : null,
                'description' => $record->name,
                'unit' => $record->unit,
                'quantity' => $item['quantity'],
                'unit_cost' => $record->unit_cost,
                'amount' => $item['quantity'] * $record->unit_cost,
                'date_acquired' => $item['date_acquired'] ?? null,
                'estimated_useful_life' => $item['estimated_useful_life'] ?? null,
                'stock_available' => $item['stock_available'] ?? null,
                'issued_quantity' => $item['issued_quantity'] ?? null,
                'item_remarks' => $item['item_remarks'] ?? null,
            ]);
        }
    }

    /**
     * The item picker submits "supply:{id}" or "equipment:{id}". Returns the kind
     * and the matching record, or fails validation when it points nowhere.
     *
     * @return array{0: string, 1: \Illuminate\Database\Eloquent\Model}
     */
    private function resolveItem(string $value): array
    {
        [$kind, $id] = array_pad(explode(':', $value, 2), 2, null);

        return $kind === 'equipment'
            ? ['equipment', Equipment::findOrFail($id)]
            : ['supply', Supply::findOrFail($id)];
    }

    /** Validation rule for the combined supply/equipment picker. */
    private function existingItemRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            [$kind, $id] = array_pad(explode(':', (string) $value, 2), 2, null);

            $exists = match ($kind) {
                'supply' => Supply::whereKey($id)->exists(),
                'equipment' => Equipment::whereKey($id)->exists(),
                default => false,
            };

            if (! $exists) {
                $fail('Select a valid supply or equipment item.');
            }
        };
    }

    public function approve(Document $document): RedirectResponse
    {
        if ($document->status !== 'draft') {
            return back()->with('error', 'Only draft documents can be approved.');
        }

        $document->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // A finalized document is what posts to a Property Card; a draft does not.
        $this->cards->syncCardsForDocument($document);

        ActivityLog::record('approved', 'documents', "Approved {$document->type} document {$document->document_no}.");

        return back()->with('success', 'Document approved.');
    }

    /**
     * Issue the document.
     *
     * For PAR/ICS this updates the linked equipment's custodian, room and status
     * so the property records stay in sync. For ICS/RIS it releases supplies:
     * each line takes its quantity off the shelf and writes a Stock Out movement.
     *
     * An issue that would take more than is on hand is refused as a whole — the
     * document stays approved and nothing is half-released.
     */
    public function issue(Document $document): RedirectResponse
    {
        if ($document->status !== 'approved') {
            return back()->with('error', 'Only approved documents can be issued.');
        }

        try {
            DB::transaction(function () use ($document) {
                $issuedAt = now();

                $document->update([
                    'status' => 'issued',
                    'issued_by' => auth()->id(),
                    'issued_at' => $issuedAt,
                    // Property and supplies are received by the custodian the moment
                    // the document is issued, so the received-by date is stamped here
                    // instead of printing blank. A date the office already recorded
                    // (the IAR's Date Received) is kept as typed.
                    'date_received' => $document->date_received ?? $issuedAt,
                ]);

                if (in_array($document->type, ['PAR', 'ICS'], true)) {
                    foreach ($document->items as $item) {
                        if ($item->equipment_id) {
                            $item->equipment()->update([
                                'teacher_id' => $document->teacher_id,
                                // A legacy typed name is kept only when the document
                                // names no custodian record.
                                'custodian' => $document->teacher_id ? null : $document->custodian,
                                'room_id' => $document->room_id,
                                'status' => 'issued',
                            ]);
                        }
                    }
                }

                // A requisition/issue slip hands supplies over; a property receipt
                // does not consume anything, so only ICS and RIS release stock.
                if (in_array($document->type, ['ICS', 'RIS'], true)) {
                    foreach ($document->items as $item) {
                        $this->releaseStock($document, $item);
                    }
                }

                // Post the receipt onto any Property Card that documents these items.
                $this->cards->syncCardsForDocument($document);
            });
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'The document could not be issued.';

            return back()->with('error', $message);
        }

        ActivityLog::record('issued', 'documents', "Issued {$document->type} document {$document->document_no}.");

        return back()->with('success', 'Document issued.');
    }

    /**
     * Take one document line's quantity off the shelf.
     *
     * The amount released is the line's Issue Qty when the form recorded one (a
     * RIS may request more than it hands over), otherwise the line's quantity.
     * The stock on hand and the Stock Out movement are written together in the
     * caller's transaction, so the balance and the item's Stock Movement History
     * can never disagree; a movement that would go past zero is refused instead
     * of being quietly rounded down.
     */
    private function releaseStock(Document $document, DocumentItem $item): void
    {
        if (! $item->supply_id) {
            return;
        }

        $quantity = (int) ($item->issued_quantity ?: $item->quantity);

        if ($quantity <= 0) {
            return;
        }

        $supply = Supply::query()->lockForUpdate()->find($item->supply_id);

        if (! $supply) {
            return;
        }

        $onHand = (int) $supply->quantity_on_hand;

        if ($quantity > $onHand) {
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'Insufficient stock for %s. Available balance: %d %s. Requested issue: %d.',
                    $supply->name,
                    $onHand,
                    $supply->unit,
                    $quantity,
                ),
            ]);
        }

        $balanceAfter = $onHand - $quantity;

        $supply->update(['quantity_on_hand' => $balanceAfter]);

        Inventory::create([
            'supply_id' => $supply->id,
            'type' => 'out',
            'quantity' => -$quantity,
            // Cost belongs to the receipt that brought the stock in; a release
            // carries its own reference instead.
            'unit_cost' => null,
            'total_cost' => null,
            'balance_after' => $balanceAfter,
            'reference_type' => Document::class,
            'reference_id' => $document->id,
            'reference' => $document->document_no,
            'remarks' => "Issued via {$document->type} {$document->document_no}.",
            'user_id' => auth()->id(),
        ]);

        // A Stock Card mirrors the stock ledger, so it picks this line up now.
        $this->cards->syncCardsForSupply($supply->id);
    }

    public function pdf(Request $request, Document $document): Response
    {
        $document->load(['teacher', 'room', 'preparedBy', 'approvedBy', 'issuedBy', 'items.equipment', 'items.supply']);

        $pdf = Pdf::loadView('admin.documents.pdf', compact('document'))->setPaper('a4', 'portrait');
        $filename = "{$document->type}-{$document->document_no}.pdf";

        // ?download=1 forces a file download; otherwise stream inline (used by the
        // preview iframe on the document page).
        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * Render a two-page bundle for a PAR/ICS/RIS: page 1 is the source document,
     * page 2 is an Inspection & Acceptance Report auto-generated from the same
     * header and items. The IAR is built in memory only — it is never persisted,
     * so it consumes no IAR number and does not appear in the documents list.
     */
    public function bundlePdf(Request $request, Document $document): Response
    {
        abort_if($document->type === 'IAR', 404, 'An IAR has no source document to bundle.');

        $document->load(['teacher', 'room', 'preparedBy', 'approvedBy', 'issuedBy', 'items.equipment', 'items.supply']);

        // replicate() copies every attribute plus the eager-loaded relations
        // (items, room, signatories) onto a fresh, unsaved model.
        $iar = $document->replicate();
        $iar->type = 'IAR';
        // Show a matching IAR-style number, e.g. PAR-2026-00001 -> IAR-2026-00001.
        $iar->document_no = preg_replace('/^[A-Za-z]+/', 'IAR', $document->document_no);

        $documents = collect([$document, $iar]);

        $pdf = Pdf::loadView('admin.documents.pdf_bundle', [
            'documents' => $documents,
            'title' => "{$document->type} + IAR - {$document->document_no}",
        ])->setPaper('a4', 'portrait');

        $filename = "{$document->type}-{$document->document_no}-with-IAR.pdf";

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
