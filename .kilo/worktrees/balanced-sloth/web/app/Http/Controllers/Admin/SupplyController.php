<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Room;
use App\Models\Supply;
use App\Services\CardService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplyController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CardService $cards,
    ) {}

    public function index(Request $request): View
    {
        $supplies = Supply::with('room')
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', "%{$request->search}%")
                    ->orWhere('item_code', 'like', "%{$request->search}%");
            }))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            // Threshold retained on the record; the reorder point is no longer
            // editable, but the dashboard's Low Stock tile links here.
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('quantity_on_hand', '<=', 'reorder_level'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = Supply::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        return view('admin.supplies.index', compact('supplies', 'categories'));
    }

    public function create(): View
    {
        $rooms = Room::orderBy('name')->get();

        return view('admin.supplies.create', compact('rooms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // A new product starts at zero stock. Quantity only ever grows through
        // the "Add Stock" transaction, which writes the matching history row.
        $supply = Supply::create($data + ['quantity_on_hand' => 0, 'unit_cost' => 0]);

        ActivityLog::record('created', 'supplies', "Created supply \"{$supply->name}\" ({$supply->item_code}).");

        return redirect()->route('admin.supplies.show', $supply)
            ->with('success', "Supply \"{$supply->name}\" created. Add opening stock with the Add Stock button.");
    }

    public function show(Supply $supply): View
    {
        $supply->load('room');
        $movements = $supply->inventoryMovements()->with('user')->latest()->paginate(15);
        $latestUnitCost = $supply->latestUnitCost();

        return view('admin.supplies.show', compact('supply', 'movements', 'latestUnitCost'));
    }

    public function edit(Supply $supply): View
    {
        $rooms = Room::orderBy('name')->get();

        return view('admin.supplies.edit', compact('supply', 'rooms'));
    }

    /**
     * Product master data only. Quantity on hand and unit cost are deliberately
     * not editable here: stock changes go through addStock() so every movement
     * is recorded in the history.
     */
    public function update(Request $request, Supply $supply): RedirectResponse
    {
        $data = $this->validated($request, $supply);

        $supply->update($data);

        ActivityLog::record('updated', 'supplies', "Updated supply \"{$supply->name}\" ({$supply->item_code}).");

        return redirect()->route('admin.supplies.show', $supply)->with('success', "Supply \"{$supply->name}\" updated successfully.");
    }

    /**
     * Record a stock-in transaction: validates the input, adds the quantity to
     * the on-hand balance, writes the movement history row and stores the cost
     * of that purchase. Total cost is always calculated server side.
     */
    public function addStock(Request $request, Supply $supply): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            // The document the delivery came in on (IAR / PO / DR no.).
            'reference' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], [], [
            'quantity' => 'quantity added',
            'unit_cost' => 'unit cost',
            'reference' => 'reference',
        ]);

        [$quantity, $unitCost, $balanceAfter] = DB::transaction(function () use ($data, $supply) {
            // Lock the row so two concurrent stock-ins cannot overwrite each other.
            $supply = Supply::query()->lockForUpdate()->findOrFail($supply->id);

            $quantity = (int) $data['quantity'];
            $unitCost = round((float) $data['unit_cost'], 2);
            $balanceAfter = $supply->quantity_on_hand + $quantity;

            $supply->update([
                'quantity_on_hand' => $balanceAfter,
                // Kept in sync with the newest purchase for documents/reports,
                // but no longer editable directly on the product.
                'unit_cost' => $unitCost,
            ]);

            Inventory::create([
                'supply_id' => $supply->id,
                'type' => 'in',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 2),
                'balance_after' => $balanceAfter,
                // Typed by the officer, and printed on the Stock Card.
                'reference' => $data['reference'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'user_id' => auth()->id(),
            ]);

            return [$quantity, $unitCost, $balanceAfter];
        });

        // Keep any Stock Card for this supply in step with the new movement.
        $this->cards->syncCardsForSupply($supply);

        ActivityLog::record(
            'stock_in',
            'supplies',
            "Added {$quantity} {$supply->unit} of \"{$supply->name}\" at ₱" . number_format($unitCost, 2) . ' each'
                . (filled($data['reference'] ?? null) ? " — {$data['reference']}" : '') . '.',
        );

        return redirect()->route('admin.supplies.show', $supply)->with('success', sprintf(
            'Stock added: +%d %s of "%s". Quantity on hand is now %d.',
            $quantity,
            $supply->unit,
            $supply->name,
            $balanceAfter,
        ));
    }

    private function validated(Request $request, ?Supply $supply = null): array
    {
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:255', Rule::unique('supplies', 'item_code')->ignore($supply?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            // The re-order point the office sets for this item; it drives the
            // low-stock badge, the dashboard status and the printed stock card.
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'room_id' => ['nullable', 'exists:rooms,id'],
        ]);

        $data['reorder_level'] = (int) ($data['reorder_level'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
