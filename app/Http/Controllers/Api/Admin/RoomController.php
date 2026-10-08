<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ActivityLog;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends AdminApiController
{
    public function index(Request $request): JsonResponse
    {
        $rooms = Room::withCount(['teachers', 'equipment', 'supplies'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('code', 'like', "%{$request->search}%")))
            ->orderBy('code')
            ->get()
            ->map(fn ($r) => $this->transform($r));

        return $this->ok($rooms);
    }

    public function store(Request $request): JsonResponse
    {
        $room = Room::create($this->validated($request));
        ActivityLog::record('created', 'rooms', "Created room \"{$room->name}\" ({$room->code}).");

        return $this->ok($this->transform($room->loadCount(['teachers', 'equipment', 'supplies'])), 'Room created.', 201);
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $room->update($this->validated($request, $room));
        ActivityLog::record('updated', 'rooms', "Updated room \"{$room->name}\" ({$room->code}).");

        return $this->ok($this->transform($room->loadCount(['teachers', 'equipment', 'supplies'])), 'Room updated.');
    }

    public function destroy(Room $room): JsonResponse
    {
        if ($room->equipment()->exists() || $room->teachers()->exists() || $room->supplies()->exists()) {
            return $this->fail('Cannot delete a room that still has teachers, equipment, or supplies assigned to it.');
        }

        $name = $room->name;
        $room->delete();
        ActivityLog::record('deleted', 'rooms', "Deleted room \"{$name}\".");

        return $this->ok(null, 'Room deleted.');
    }

    private function transform(Room $r): array
    {
        return [
            'id' => $r->id,
            'code' => $r->code,
            'name' => $r->name,
            'building' => $r->building,
            'floor' => $r->floor,
            'type' => $r->type,
            'is_active' => (bool) $r->is_active,
            'teachers_count' => $r->teachers_count ?? 0,
            'equipment_count' => $r->equipment_count ?? 0,
            'supplies_count' => $r->supplies_count ?? 0,
        ];
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('rooms', 'code')->ignore($room?->id)],
            'name' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
