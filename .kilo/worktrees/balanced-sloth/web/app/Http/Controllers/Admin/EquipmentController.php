<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\EquipmentTransaction;
use App\Models\Room;
use App\Models\Teacher;
use App\Services\CardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Equipment master information plus the equipment ledger.
 *
 * Basic information (codes, name, description, category, unit, room, custodian)
 * is edited on the record. Quantity and cost are never typed into that form:
 * every quantity change goes through addQuantity(), which records the receipt
 * with its own price and writes the matching history row.
 */
class EquipmentController extends Controller
{
    public function __construct(
        private readonly CardService $cards,
    ) {}

    public function index(Request $request): View
    {
        $equipment = Equipment::with(['room', 'teacher'])
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', "%{$request->search}%")
                    ->orWhere('item_code', 'like', "%{$request->search}%")
                    ->orWhere('property_number', 'like', "%{$request->search}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            // Threshold retained on the record; the reorder point is no longer
            // editable, so this compares against the stored level.
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('quantity_on_hand', '<=', 'reorder_level'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = Equipment::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        return view('admin.equipment.index', compact('equipment', 'categories'));
    }

    public function create(): View
    {
        return view('admin.equipment.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // A new record starts empty: the quantity it arrives with is recorded as
        // a receipt, so the movement history is complete from the first unit.
        $equipment = Equipment::create($data + ['quantity_on_hand' => 0, 'unit_cost' => 0]);

        ActivityLog::record('created', 'equipment', "Created equipment \"{$equipment->name}\" ({$equipment->item_code}).");

        return redirect()->route('admin.equipment.show', $equipment)
            ->with('success', "Equipment \"{$equipment->name}\" created. Record the units on hand with the Add Quantity button.");
    }

    public function show(Equipment $equipment): View
    {
        $equipment->load(['room', 'teacher']);

        $movements = $equipment->transactions()
            ->with('user')
            // Ordered by the transaction date, not by insertion, so a receipt
            // recorded late still reads in ledger order. Ties break on id.
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.equipment.show', compact('equipment', 'movements'));
    }

    public function edit(Equipment $equipment): View
    {
        return view('admin.equipment.edit', ['equipment' => $equipment] + $this->formOptions());
    }

    /**
     * Basic information only. Quantity on hand and unit cost are deliberately not
     * editable here (and not part of validated()): they change through
     * addQuantity() so every change leaves a history row behind. Historical
     * transactions are never touched by an edit.
     */
    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $this->validated($request, $equipment);

        $equipment->update($data);

        ActivityLog::record('updated', 'equipment', "Updated equipment \"{$equipment->name}\" ({$equipment->item_code}).");

        return redirect()->route('admin.equipment.show', $equipment)->with('success', "Equipment \"{$equipment->name}\" updated successfully.");
    }

    // Deliberately no destroy(): equipment records are never removed from the UI,
    // because the receipts recorded against them are the office's audit trail.

    /**
     * Record a quantity received: validates the input, adds the quantity to the
     * quantity on hand, writes the movement history row and keeps that receipt's
     * own unit cost. Total cost is always calculated server side, and the user
     * who received it is taken from the session, never from the form.
     */
    public function addQuantity(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'source' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], [], [
            'transaction_date' => 'date received',
            'quantity' => 'quantity received',
            'unit_cost' => 'unit cost',
            'source' => 'source / supplier',
        ]);

        [$quantity, $unitCost, $balanceAfter] = DB::transaction(function () use ($data, $equipment) {
            // Lock the row so two concurrent receipts cannot overwrite each other.
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->id);

            $quantity = (int) $data['quantity'];
            $unitCost = round((float) $data['unit_cost'], 2);

            EquipmentTransaction::create([
                'equipment_id' => $equipment->id,
                'transaction_date' => Carbon::parse($data['transaction_date'])->toDateString(),
                'type' => 'receipt',
                'reference' => $data['reference'] ?? null,
                'quantity' => $quantity,
                // The price of this receipt, calculated here rather than trusted
                // from the browser, and never overwritten by a later receipt.
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 2),
                'balance_after' => $equipment->quantity_on_hand + $quantity,
                'source' => $data['source'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'user_id' => auth()->id(),
            ]);

            // New quantity = previous quantity + quantity received.
            $balanceAfter = $equipment->quantity_on_hand + $quantity;

            $equipment->update([
                'quantity_on_hand' => $balanceAfter,
                // Kept in step with the newest receipt by date, so reports and the
                // dashboard value the item at what it was last acquired for.
                'unit_cost' => $this->latestReceiptCost($equipment->id) ?? $unitCost,
            ]);

            return [$quantity, $unitCost, $balanceAfter];
        });

        // Keep any Property Card for this equipment in step with the receipt.
        $this->cards->syncCardsForEquipment($equipment);

        ActivityLog::record(
            'stock_in',
            'equipment',
            "Received {$quantity} {$equipment->unit} of \"{$equipment->name}\" at ₱" . number_format($unitCost, 2) . ' each.',
        );

        return redirect()->route('admin.equipment.show', $equipment)->with('success', sprintf(
            'Quantity added: +%d %s of "%s". Current quantity is now %d.',
            $quantity,
            $equipment->unit,
            $equipment->name,
            $balanceAfter,
        ));
    }

    /** Unit cost of the newest recorded receipt, or null when none is recorded. */
    private function latestReceiptCost(int $equipmentId): ?float
    {
        $cost = EquipmentTransaction::query()
            ->where('equipment_id', $equipmentId)
            ->where('type', 'receipt')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->value('unit_cost');

        return $cost === null ? null : round((float) $cost, 2);
    }

    private function formOptions(): array
    {
        return [
            'rooms' => Room::orderBy('name')->get(),
            'custodians' => Teacher::orderBy('last_name')->orderBy('first_name')->get(),
            'statuses' => Equipment::STATUSES,
        ];
    }

    /** Basic information only — see update(). */
    private function validated(Request $request, ?Equipment $equipment = null): array
    {
        return $request->validate([
            'item_code' => ['required', 'string', 'max:255', Rule::unique('equipment', 'item_code')->ignore($equipment?->id)],
            'property_number' => ['nullable', 'string', 'max:255', Rule::unique('equipment', 'property_number')->ignore($equipment?->id)],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'status' => ['required', Rule::in(Equipment::STATUSES)],
            'date_acquired' => ['nullable', 'date'],
        ]);
    }
}
