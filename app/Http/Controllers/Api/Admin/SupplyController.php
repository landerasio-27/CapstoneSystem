<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Supply;
use App\Services\CardService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplyController extends AdminApiController
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CardService $cards,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $supplies = Supply::with('room')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('item_code', 'like', "%{$request->search}%")))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('quantity_on_hand', '<=', 'reorder_level'))
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => $this->transform($s));

        return $this->ok($supplies);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $supply = DB::transaction(function () use ($data) {
            $supply = Supply::create($data);

            if ($supply->quantity_on_hand > 0) {
                Inventory::create([
                    'supply_id' => $supply->id,
                    'type' => 'in',
                    'quantity' => $supply->quantity_on_hand,
                    'unit_cost' => $supply->unit_cost,
                    'total_cost' => round($supply->quantity_on_hand * $supply->unit_cost, 2),
                    'balance_after' => $supply->quantity_on_hand,
                    'remarks' => 'Initial stock on record creation.',
                    'user_id' => auth()->id(),
                ]);
            }

            $this->cards->create('SC', $supply);

            return $supply;
        });

        ActivityLog::record('created', 'supplies', "Created supply \"{$supply->name}\" ({$supply->item_code}).");

        return $this->ok($this->transform($supply->load('room')), 'Supply created.', 201);
    }

    public function update(Request $request, Supply $supply): JsonResponse
    {
        $data = $this->validated($request, $supply);

        DB::transaction(function () use ($data, $supply) {
            $previousQuantity = $supply->quantity_on_hand;
            $supply->update($data);

            $delta = $data['quantity_on_hand'] - $previousQuantity;
            if ($delta !== 0) {
                Inventory::create([
                    'supply_id' => $supply->id,
                    'type' => 'adjustment',
                    'quantity' => $delta,
                    'balance_after' => $data['quantity_on_hand'],
                    'remarks' => 'Manual stock adjustment via desktop edit.',
                    'user_id' => auth()->id(),
                ]);
            }
        });

        $this->cards->syncCardsForSupply($supply);

        ActivityLog::record('updated', 'supplies', "Updated supply \"{$supply->name}\" ({$supply->item_code}).");

        if ($supply->isLowStock()) {
            $this->notifications->notifyAdmins(
                'low_stock',
                'Low stock alert',
                "{$supply->name} is now at {$supply->quantity_on_hand} {$supply->unit} — at or below its reorder level of {$supply->reorder_level}.",
                ['supply_id' => $supply->id],
            );
        }

        return $this->ok($this->transform($supply->load('room')), 'Supply updated.');
    }

    public function destroy(Supply $supply): JsonResponse
    {
        $name = $supply->name;
        $supply->delete();
        ActivityLog::record('deleted', 'supplies', "Deleted supply \"{$name}\".");

        return $this->ok(null, 'Supply deleted.');
    }

    private function transform(Supply $s): array
    {
        return [
            'id' => $s->id,
            'item_code' => $s->item_code,
            'name' => $s->name,
            'description' => $s->description,
            'category' => $s->category,
            'unit' => $s->unit,
            'quantity_on_hand' => $s->quantity_on_hand,
            'unit_cost' => (float) $s->unit_cost,
            'reorder_level' => (int) $s->reorder_level,
            'room_id' => $s->room_id,
            'room_name' => $s->room?->name,
            'is_active' => (bool) $s->is_active,
            'is_low_stock' => $s->isLowStock(),
        ];
    }

    private function validated(Request $request, ?Supply $supply = null): array
    {
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:255', Rule::unique('supplies', 'item_code')->ignore($supply?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'room_id' => ['nullable', 'exists:rooms,id'],
        ]);
        $data['reorder_level'] = (int) ($data['reorder_level'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
