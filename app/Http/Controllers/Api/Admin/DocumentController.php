<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Supply;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocumentController extends AdminApiController
{
    public function index(Request $request): JsonResponse
    {
        $documents = Document::with(['teacher', 'room', 'preparedBy'])
            ->withCount('items')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where('document_no', 'like', "%{$request->search}%"))
            ->latest('document_date')
            ->limit(300)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'document_no' => $d->document_no,
                'type' => $d->type,
                'status' => $d->status,
                'teacher' => $d->custodian_name,
                'room' => $d->room?->code,
                'document_date' => $d->document_date->format('Y-m-d'),
                'items_count' => $d->items_count,
                'prepared_by' => $d->preparedBy?->name,
            ]);

        return $this->ok($documents);
    }

    public function show(Document $document): JsonResponse
    {
        $document->load(['teacher', 'room', 'preparedBy', 'approvedBy', 'issuedBy', 'items.supply', 'items.equipment']);

        return $this->ok([
            'id' => $document->id,
            'document_no' => $document->document_no,
            'type' => $document->type,
            'status' => $document->status,
            'entity_name' => $document->entity_name,
            'fund_cluster' => $document->fund_cluster,
            'document_date' => $document->document_date->format('Y-m-d'),
            'teacher' => $document->custodian_name,
            'room' => $document->room ? "{$document->room->code} — {$document->room->name}" : null,
            'requested_by' => $document->requested_by,
            'received_by' => $document->received_by,
            'remarks' => $document->remarks,
            'supplier' => $document->supplier,
            'po_no' => $document->po_no,
            'prepared_by' => $document->preparedBy?->name,
            'approved_by' => $document->approvedBy?->name,
            'issued_by' => $document->issuedBy?->name,
            'approved_at' => $document->approved_at?->format('Y-m-d H:i'),
            'issued_at' => $document->issued_at?->format('Y-m-d H:i'),
            'items' => $document->items->map(fn ($it) => [
                'description' => $it->description,
                'unit' => $it->unit,
                'quantity' => $it->quantity,
                'unit_cost' => (float) $it->unit_cost,
                'amount' => (float) $it->amount,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(Document::TYPES)],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'custodian' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'document_date' => ['required', 'date'],
            'entity_name' => ['nullable', 'string', 'max:255'],
            'fund_cluster' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'po_no' => ['nullable', 'string', 'max:255'],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supply_id' => ['required', 'exists:supplies,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $document = DB::transaction(function () use ($data) {
            $header = collect($data)->only([
                'entity_name', 'fund_cluster', 'remarks', 'supplier', 'po_no', 'requested_by', 'received_by',
            ])->filter(fn ($v) => $v !== null && $v !== '')->all();

            $document = Document::create(array_merge($header, [
                'document_no' => Document::nextDocumentNumber($data['type']),
                'type' => $data['type'],
                'status' => 'draft',
                'teacher_id' => $data['teacher_id'] ?? null,
                'custodian' => $data['custodian'] ?? null,
                'room_id' => $data['room_id'] ?? null,
                'prepared_by' => auth()->id(),
                'document_date' => $data['document_date'],
            ]));

            foreach ($data['items'] as $item) {
                $supply = Supply::findOrFail($item['supply_id']);
                $document->items()->create([
                    'supply_id' => $supply->id,
                    'description' => $supply->name,
                    'unit' => $supply->unit,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $supply->unit_cost,
                    'amount' => $item['quantity'] * $supply->unit_cost,
                ]);
            }

            return $document;
        });

        ActivityLog::record('created', 'documents', "Created {$document->type} document {$document->document_no}.");

        return $this->ok(['id' => $document->id, 'document_no' => $document->document_no],
            "{$document->type} {$document->document_no} created.", 201);
    }

    public function approve(Document $document): JsonResponse
    {
        if ($document->status !== 'draft') {
            return $this->fail('Only draft documents can be approved.', 422);
        }

        $document->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        ActivityLog::record('approved', 'documents', "Approved {$document->type} document {$document->document_no}.");

        return $this->ok(null, 'Document approved.');
    }

    public function issue(Document $document): JsonResponse
    {
        if ($document->status !== 'approved') {
            return $this->fail('Only approved documents can be issued.', 422);
        }

        $document->update(['status' => 'issued', 'issued_by' => auth()->id(), 'issued_at' => now()]);
        ActivityLog::record('issued', 'documents', "Issued {$document->type} document {$document->document_no}.");

        return $this->ok(null, 'Document issued.');
    }

    /** Streams the document PDF (bearer-token auth) for the desktop client. */
    public function pdf(Document $document): Response
    {
        $document->load(['teacher', 'room', 'preparedBy', 'approvedBy', 'issuedBy', 'items.equipment', 'items.supply']);

        $pdf = Pdf::loadView('admin.documents.pdf', compact('document'))->setPaper('a4', 'portrait');

        return $pdf->stream("{$document->type}-{$document->document_no}.pdf");
    }
}
