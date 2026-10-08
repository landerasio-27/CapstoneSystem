<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $rooms = Room::withCount(['teachers', 'equipment', 'supplies'])
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', "%{$request->search}%")
                    ->orWhere('code', 'like', "%{$request->search}%")
                    ->orWhere('building', 'like', "%{$request->search}%");
            }))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.rooms.index', compact('rooms'));
    }

    /**
     * Everything the room currently holds, read from the records themselves.
     *
     * The room is the single reference point: a teacher points at it for their
     * assigned room, equipment for where it sits, and supplies for where they are
     * stored. Nothing here is counted by hand, so moving a record between rooms
     * updates both rooms automatically.
     */
    public function show(Room $room): View
    {
        $room->loadCount(['teachers', 'equipment', 'supplies']);

        $teachers = $room->teachers()->orderBy('last_name')->orderBy('first_name')->get();
        $equipment = $room->equipment()->with('teacher')->orderBy('name')->get();
        $supplies = $room->supplies()->orderBy('name')->get();

        return view('admin.rooms.show', compact('room', 'teachers', 'equipment', 'supplies'));
    }

    public function create(): View
    {
        return view('admin.rooms.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $room = Room::create($data);

        ActivityLog::record('created', 'rooms', "Created room \"{$room->name}\" ({$room->code}).");

        return redirect()->route('admin.rooms.index')->with('success', "Room \"{$room->name}\" created successfully.");
    }

    public function edit(Room $room): View
    {
        return view('admin.rooms.edit', compact('room'));
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $data = $this->validated($request, $room);

        $room->update($data);

        ActivityLog::record('updated', 'rooms', "Updated room \"{$room->name}\" ({$room->code}).");

        return redirect()->route('admin.rooms.index')->with('success', "Room \"{$room->name}\" updated successfully.");
    }

    public function destroy(Room $room): RedirectResponse
    {
        if ($room->equipment()->exists() || $room->teachers()->exists() || $room->supplies()->exists()) {
            return back()->with('error', 'Cannot delete a room that still has teachers, equipment, or supplies assigned to it.');
        }

        $name = $room->name;
        $room->delete();

        ActivityLog::record('deleted', 'rooms', "Deleted room \"{$name}\".");

        return redirect()->route('admin.rooms.index')->with('success', "Room \"{$name}\" deleted successfully.");
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
