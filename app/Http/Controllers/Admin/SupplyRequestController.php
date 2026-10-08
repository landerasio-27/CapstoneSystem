<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Inventory;
use App\Models\Supply;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\CardService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupplyRequestController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CardService $cards,
    ) {}

    public function index(Request $request): View
    {
        $requests = SupplyRequest::with(['requestedBy', 'items.supply'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.supply-requests.index', compact('requests'));
    }

    public function create(): View
    {
        $supplies = Supply::where('is_active', true)->orderBy('name')->get();
        $users = User::orderBy('name')->get();

        return view('admin.supply-requests.create', compact('supplies', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requested_by' => ['required', 'exists:users,id'],
            'purpose' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supply_id' => ['required', 'distinct', 'exists:supplies,id'],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1'],
        ]);

        $supplyRequest = DB::transaction(function () use ($data) {
            $supplyRequest = SupplyRequest::create([
                'request_no' => 'REQ-'.now()->year.'-'.strtoupper(Str::random(6)),
                'requested_by' => $data['requested_by'],
                'purpose' => $data['purpose'] ?? null,
                'status' => 'pending',
            ]);

            foreach ($data['items'] as $item) {
                $supplyRequest->items()->create([
                    'supply_id' => $item['supply_id'],
                    'quantity_requested' => $item['quantity_requested'],
                ]);
            }

            return $supplyRequest;
        });

        ActivityLog::record('created', 'supply_requests', "Created supply request {$supplyRequest->request_no}.");

        $this->notifications->notifyAdmins(
            'supply_request',
            'New supply request submitted',
            "{$supplyRequest->requestedBy->name} submitted supply request {$supplyRequest->request_no}.",
            ['supply_request_id' => $supplyRequest->id],
        );

        return redirect()->route('admin.supply-requests.show', $supplyRequest)->with('success', 'Supply request submitted successfully.');
    }

    public function show(SupplyRequest $supplyRequest): View
    {
        $supplyRequest->load(['requestedBy', 'approvedBy', 'items.supply', 'documents']);

        return view('admin.supply-requests.show', compact('supplyRequest'));
    }

    public function approve(Request $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        if ($supplyRequest->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be approved.');
        }

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.quantity_approved' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $supplyRequest) {
            foreach ($supplyRequest->items as $item) {
                $approvedQty = $data['items'][$item->id]['quantity_approved'] ?? $item->quantity_requested;
                $item->update(['quantity_approved' => min($approvedQty, $item->quantity_requested)]);
            }

            $supplyRequest->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        ActivityLog::record('approved', 'supply_requests', "Approved supply request {$supplyRequest->request_no}.");

        return back()->with('success', 'Supply request approved.');
    }

    public function reject(Request $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        if ($supplyRequest->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be rejected.');
        }

        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);

        $supplyRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        ActivityLog::record('rejected', 'supply_requests', "Rejected supply request {$supplyRequest->request_no}.");

        return back()->with('success', 'Supply request rejected.');
    }

    /**
     * Issue the approved supplies: decrements stock, logs inventory movements,
     * and auto-generates the RIS document from this request so nothing is re-typed.
     */
    public function issue(SupplyRequest $supplyRequest): RedirectResponse
    {
        if ($supplyRequest->status !== 'approved') {
            return back()->with('error', 'Only approved requests can be issued.');
        }

        $lowStockSupplies = collect();
        $issuedSupplies = collect();

        DB::transaction(function () use ($supplyRequest, &$lowStockSupplies, &$issuedSupplies) {
            $document = Document::create([
                'document_no' => Document::nextDocumentNumber('RIS'),
                'type' => 'RIS',
                'status' => 'issued',
                'source_type' => SupplyRequest::class,
                'source_id' => $supplyRequest->id,
                'prepared_by' => auth()->id(),
                'issued_by' => auth()->id(),
                'document_date' => now()->toDateString(),
                'remarks' => $supplyRequest->purpose,
                'issued_at' => now(),
            ]);

            foreach ($supplyRequest->items as $item) {
                $issueQty = $item->quantity_approved;

                if ($issueQty <= 0) {
                    continue;
                }

                $supply = $item->supply()->lockForUpdate()->first();
                $newBalance = max(0, $supply->quantity_on_hand - $issueQty);

                $supply->update(['quantity_on_hand' => $newBalance]);

                if ($newBalance <= $supply->reorder_level) {
                    $lowStockSupplies->push($supply);
                }

                Inventory::create([
                    'supply_id' => $supply->id,
                    'type' => 'out',
                    'quantity' => -$issueQty,
                    'balance_after' => $newBalance,
                    'reference_type' => SupplyRequest::class,
                    'reference_id' => $supplyRequest->id,
                    'remarks' => "Issued via RIS {$document->document_no}.",
                    'user_id' => auth()->id(),
                ]);

                $item->update(['quantity_issued' => $issueQty]);
                $issuedSupplies->push($supply->id);

                $document->items()->create([
                    'supply_id' => $supply->id,
                    'description' => $supply->name,
                    'unit' => $supply->unit,
                    'quantity' => $issueQty,
                    'unit_cost' => $supply->unit_cost,
                    'amount' => $issueQty * $supply->unit_cost,
                ]);
            }

            $supplyRequest->update(['status' => 'issued']);
        });

        // The issue is a stock-out: post it to any Stock Card for these supplies.
        foreach ($issuedSupplies->unique() as $supplyId) {
            $this->cards->syncCardsForSupply($supplyId);
        }

        ActivityLog::record('issued', 'supply_requests', "Issued supply request {$supplyRequest->request_no} and generated RIS.");

        foreach ($lowStockSupplies->unique('id') as $supply) {
            $this->notifications->notifyAdmins(
                'low_stock',
                'Low stock alert',
                "{$supply->name} is now at {$supply->quantity_on_hand} {$supply->unit} — at or below its reorder level of {$supply->reorder_level}.",
                ['supply_id' => $supply->id],
            );
        }

        return back()->with('success', 'Supplies issued and RIS document generated.');
    }
}
