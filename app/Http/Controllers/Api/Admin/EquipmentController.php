<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\EquipmentTransaction;
use App\Services\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EquipmentController extends AdminApiController
{
    public function __construct(private readonly CardService $cards) {}
    public function index(Request $request): JsonResponse
    {
        $equipment = Equipment::with(['room', 'teacher'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('item_code', 'like', "%{$request->search}%")
                ->orWhere('property_number', 'like', "%{$request->search}%")))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->get()
            ->map(fn ($e) => $this->transform($e));

        return $this->ok($equipment);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $equipment = DB::transaction(function () use ($data) {
            $equipment = Equipment::create($data);

            if ($equipment->quantity_on_hand > 0) {
                EquipmentTransaction::create([
                    'equipment_id' => $equipment->id,
                    'transaction_date' => $equipment->date_acquired?->toDateString() ?? today()->toDateString(),
                    'type' => 'receipt',
                    'quantity' => $equipment->quantity_on_hand,
                    'balance_after' => $equipment->quantity_on_hand,
                    'unit_cost' => $equipment->unit_cost,
                    'total_cost' => round($equipment->quantity_on_hand * $equipment->unit_cost, 2),
                    'remarks' => 'Initial quantity on record creation.',
                    'user_id' => auth()->id(),
                ]);
            }

            $this->cards->create('PC', $equipment);

            return $equipment;
        });
        ActivityLog::record('created', 'equipment', "Created equipment \"{$equipment->name}\" ({$equipment->item_code}).");

        return $this->ok($this->transform($equipment->load(['room', 'teacher'])), 'Equipment created.', 201);
    }

    public function update(Request $request, Equipment $equipment): JsonResponse
    {
        $equipment->update($this->validated($request, $equipment));
        ActivityLog::record('updated', 'equipment', "Updated equipment \"{$equipment->name}\" ({$equipment->item_code}).");

        return $this->ok($this->transform($equipment->load(['room', 'teacher'])), 'Equipment updated.');
    }

    public function destroy(Equipment $equipment): JsonResponse
    {
        $name = $equipment->name;
        $equipment->delete();
        ActivityLog::record('deleted', 'equipment', "Deleted equipment \"{$name}\".");

        return $this->ok(null, 'Equipment deleted.');
    }

    private function transform(Equipment $e): array
    {
        return [
            'id' => $e->id,
            'item_code' => $e->item_code,
            'property_number' => $e->property_number,
            'name' => $e->name,
            'description' => $e->description,
            'category' => $e->category,
            'unit' => $e->unit,
            'quantity_on_hand' => $e->quantity_on_hand,
            'unit_cost' => (float) $e->unit_cost,
            'room_id' => $e->room_id,
            'room_name' => $e->room?->name,
            'teacher_id' => $e->teacher_id,
            'teacher_name' => $e->custodian_name,
            'status' => $e->status,
            'date_acquired' => $e->date_acquired?->format('Y-m-d'),
            'is_low_stock' => $e->isLowStock(),
        ];
    }

    private function validated(Request $request, ?Equipment $equipment = null): array
    {
        return $request->validate([
            'item_code' => ['required', 'string', 'max:255', Rule::unique('equipment', 'item_code')->ignore($equipment?->id)],
            'property_number' => ['nullable', 'string', 'max:255', Rule::unique('equipment', 'property_number')->ignore($equipment?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'custodian' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Equipment::STATUSES)],
            'date_acquired' => ['nullable', 'date'],
        ]);
    }
}
