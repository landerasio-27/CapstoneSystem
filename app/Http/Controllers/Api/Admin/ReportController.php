<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\Supply;
use App\Models\SupplyRequestItem;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only report data for the desktop client. Mirrors the web reports, but
 * returns {title, headings, rows} JSON instead of rendering HTML/PDF/Excel.
 */
class ReportController extends AdminApiController
{
    /** Filter options for the report screen (rooms, teachers, categories, …). */
    public function options(): JsonResponse
    {
        return $this->ok([
            'rooms' => Room::orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn ($r) => ['id' => $r->id, 'label' => "{$r->code} — {$r->name}"]),
            'teachers' => Teacher::orderBy('last_name')->get()
                ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_name]),
            'categories' => Supply::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'equipment_statuses' => Equipment::STATUSES,
            'document_types' => Document::TYPES,
        ]);
    }

    public function inventorySummary(Request $request): JsonResponse
    {
        $supplies = Supply::with('room')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->room_id))
            ->orderBy('name')->get();

        $equipment = Equipment::with('room')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->room_id))
            ->orderBy('name')->get();

        $rows = collect()
            // Only supplies carry a re-order point the office can set.
            ->concat($supplies->map(fn ($s) => ['Supply', $s->item_code, $s->name, $s->category, $s->quantity_on_hand, $s->reorder_level, number_format($s->unit_cost, 2), $s->room?->code, $s->isLowStock() ? 'Low Stock' : 'OK']))
            ->concat($equipment->map(fn ($e) => ['Equipment', $e->item_code, $e->name, $e->category, $e->quantity_on_hand, '—', number_format($e->unit_cost, 2), $e->room?->code, ucfirst($e->status)]))
            ->values()->all();

        return $this->report('Inventory Summary',
            ['Type', 'Item Code', 'Name', 'Category', 'Qty on Hand', 'Reorder Level', 'Unit Cost', 'Room', 'Status'], $rows);
    }

    public function supplyIssuance(Request $request): JsonResponse
    {
        $items = SupplyRequestItem::with(['supply', 'supplyRequest.requestedBy'])
            ->whereHas('supplyRequest', function ($q) use ($request) {
                $q->where('status', 'issued')
                    ->when($request->filled('date_from'), fn ($sub) => $sub->whereDate('updated_at', '>=', $request->date_from))
                    ->when($request->filled('date_to'), fn ($sub) => $sub->whereDate('updated_at', '<=', $request->date_to));
            })
            ->where('quantity_issued', '>', 0)->get();

        $rows = $items->map(fn ($i) => [
            $i->supplyRequest->request_no, $i->supply->name, $i->quantity_issued,
            $i->supplyRequest->requestedBy?->name, $i->supplyRequest->updated_at->format('Y-m-d'),
        ])->all();

        return $this->report('Supply Issuance',
            ['Request No.', 'Supply', 'Quantity Issued', 'Requested By', 'Date'], $rows);
    }

    public function documentRegister(Request $request): JsonResponse
    {
        $documents = Document::with(['teacher', 'room'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('document_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('document_date', '<=', $request->date_to))
            ->latest('document_date')->get();

        $rows = $documents->map(fn ($d) => [
            $d->document_no, $d->type, ucfirst($d->status), $d->custodian_name,
            $d->room?->code, $d->document_date->format('Y-m-d'),
        ])->all();

        return $this->report('Document Register',
            ['Document No.', 'Type', 'Status', 'Teacher', 'Room', 'Date'], $rows);
    }

    private function report(string $title, array $headings, array $rows): JsonResponse
    {
        return $this->ok(['title' => $title, 'headings' => $headings, 'rows' => $rows]);
    }
}
