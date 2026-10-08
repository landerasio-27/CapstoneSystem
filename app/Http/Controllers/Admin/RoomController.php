<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\Supply;
use App\Services\ItemClassifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rooms — the inventory folders.
 *
 * A room is not a record holding a copy of the inventory: equipment and supplies
 * each carry the `room_id` they sit in, so opening a room reads those rows back
 * and shows what is physically there. The module's own two writes are the
 * location changes — placing an item in a room and transferring it to another —
 * and each of those leaves one row in `room_movements`, the room's history.
 */
class RoomController extends Controller
{
    /** How many room folders one page of the grid shows. */
    private const PER_PAGE = 12;

    /** The tabs the inventory on a room page is read through. */
    private const TABS = ['all', 'equipment', 'supplies'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search'));
        $status = (string) $request->get('status', 'all');
        $group = (string) $request->get('group', 'all');
        $type = trim((string) $request->get('type'));
        $sort = (string) $request->get('sort', 'code_asc');

        $rooms = Room::query()
            // The figures on each folder come straight off the relations, so an
            // item filed anywhere in the system lands in the counts.
            ->withCount([
                'teachers',
                'equipment as equipment_property_count' => fn ($q) => $q->where('is_consumable', false),
                'supplies as supply_property_count' => fn ($q) => $q->where('is_consumable', false),
                'equipment as equipment_consumable_count' => fn ($q) => $q->where('is_consumable', true),
                'supplies as supply_consumable_count' => fn ($q) => $q->where('is_consumable', true),
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($sub) use ($search) {
                // Every word has to land somewhere on the record, so "CCS
                // laboratory" and "A101" both work.
                foreach (preg_split('/\s+/', $search) as $word) {
                    $sub->where(function ($inner) use ($word) {
                        $inner->where('code', 'like', "%{$word}%")
                            ->orWhere('name', 'like', "%{$word}%")
                            ->orWhere('building', 'like', "%{$word}%")
                            ->orWhere('floor', 'like', "%{$word}%")
                            ->orWhere('department', 'like', "%{$word}%")
                            ->orWhere('description', 'like', "%{$word}%")
                            ->orWhere('type', 'like', "%{$word}%");

                        // A word naming a room kind — "laboratory", "storage
                        // room" — finds the rooms of that kind, whatever the
                        // value stored for it.
                        foreach (Room::TYPES as $value => $label) {
                            if (str_contains(mb_strtolower($label), mb_strtolower($word))) {
                                $inner->orWhere('type', $value);
                            }
                        }
                    });
                }
            }))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            // The pill wins when it is set; the type dropdown is the full list.
            ->when($group !== 'all' && isset(Room::TYPE_GROUPS[$group]),
                fn ($query) => $query->whereIn('type', Room::TYPE_GROUPS[$group]))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($sort === 'code_desc', fn ($query) => $query->orderByDesc('code'))
            ->when($sort === 'equipment', fn ($query) => $query->orderByRaw('(equipment_property_count + supply_property_count) DESC')->orderBy('code'))
            ->when($sort === 'supplies', fn ($query) => $query->orderByRaw('(equipment_consumable_count + supply_consumable_count) DESC')->orderBy('code'))
            ->when($sort === 'recent', fn ($query) => $query->orderByDesc('updated_at'))
            ->when(! in_array($sort, ['code_desc', 'equipment', 'supplies', 'recent'], true),
                fn ($query) => $query->orderBy('code'))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $rooms->getCollection()->each(function ($room) {
            $room->equipment_count = $room->equipment_property_count + $room->supply_property_count;
            $room->supplies_count = $room->equipment_consumable_count + $room->supply_consumable_count;
        });

        return view('admin.rooms.index', [
            'rooms' => $rooms,
            'search' => $search,
            'status' => $status,
            'group' => $group,
            'type' => $type,
            'sort' => $sort,
        ]);
    }

    /**
     * One room read as a folder: what it is, what is inside it, what has moved
     * through it, and the papers raised for it.
     *
     * The inventory is read through the records themselves — equipment and
     * supplies each name the room they sit in — so the figures cannot drift from
     * the items listed below them: both are the same rows.
     */
    public function show(Request $request, Room $room): View
    {
        $room->loadCount([
            'teachers',
            'equipment',
            'supplies',
            // Only the papers still on the register: filed documents live in the
            // archive, and the room's count should agree with the room's list.
            'documents as documents_count' => fn ($query) => $query->whereNull('archive_id'),
        ]);

        $tab = in_array($request->get('tab'), self::TABS, true) ? (string) $request->get('tab') : 'all';
        $term = trim((string) $request->get('q'));

        // What the room holds, read once: the figures on the cards and the
        // sheets' own lists are built from these rows, so they cannot disagree
        // with what is listed.
        $inventoryEquipment = $room->equipment()->with('teacher')->orderBy('name')->get();
        $inventorySupplies = $room->supplies()->orderBy('name')->get();
        $inventory = $inventoryEquipment->concat($inventorySupplies);
        $room->equipment_count = $inventory->filter(fn ($item) => ! ItemClassifier::isConsumable($item))->count();
        $room->supplies_count = $inventory->filter(fn ($item) => ItemClassifier::isConsumable($item))->count();

        // The search only narrows what is shown; it never changes the counts.
        $equipment = $term === '' ? $inventoryEquipment
            : $room->equipment()->with('teacher')->matching($term)->orderBy('name')->get();
        $supplies = $term === '' ? $inventorySupplies
            : $room->supplies()->matching($term)->orderBy('name')->get();

        // Consumability, not the source register or price, separates the two sections.
        $matchingInventory = $equipment->concat($supplies);
        $equipment = $matchingInventory->filter(fn ($item) => ! ItemClassifier::isConsumable($item))->sortBy('name')->values();
        $supplies = $matchingInventory->filter(fn ($item) => ItemClassifier::isConsumable($item))->sortBy('name')->values();

        // Where each item was last placed in this room, read off the history it
        // wrote on the way in rather than guessed from the record's timestamp.
        $placedAt = RoomMovement::query()
            ->where('to_room_id', $room->id)
            ->selectRaw('item_type, item_id, MAX(created_at) as placed_at')
            ->groupBy('item_type', 'item_id')
            ->get()
            ->mapWithKeys(fn ($movement) => [$movement->item_type.':'.$movement->item_id => \Illuminate\Support\Carbon::parse($movement->placed_at)]);

        return view('admin.rooms.show', [
            'room' => $room,
            'tab' => $tab,
            'search' => $term,
            'equipment' => $equipment,
            'supplies' => $supplies,
            'classificationCounts' => $this->classificationCounts($inventoryEquipment, $inventorySupplies),
            'placedAt' => $placedAt,
            'history' => $room->history(),
            'custodians' => $room->teachers()
                ->withCount('equipment')
                ->orderBy('last_name')->orderBy('first_name')
                ->get(),
            'documents' => $room->documents()
                ->with('teacher')
                ->whereNull('archive_id')
                ->latest('document_date')
                ->limit(8)
                ->get(),
            // What the "Add Item" sheet offers: every catalogue item that is not
            // already in this room. Nothing is copied — picking one only points
            // its own record at this room.
            'assignableEquipment' => Equipment::with('room')->where(
                fn ($query) => $query->whereNull('room_id')->orWhere('room_id', '!=', $room->id)
            )->orderBy('name')->get(),
            'assignableSupplies' => Supply::with('room')->where(
                fn ($query) => $query->whereNull('room_id')->orWhere('room_id', '!=', $room->id)
            )->orderBy('name')->get(),
            // What the "Transfer Item" sheet offers: everything physically in
            // this room, and the rooms it can be sent to.
            'roomItems' => $inventoryEquipment->concat($inventorySupplies)->sortBy('name')->values(),
            'destinations' => Room::whereKeyNot($room->id)->orderBy('code')->get(),
        ]);
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

    /**
     * Place an item in this room from the room's own page.
     *
     * Nothing is copied: the equipment or supply row simply names this room, so
     * the item leaves wherever it was and appears here — one record, read from
     * both places. The move is written to the room's history.
     */
    public function addItem(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'item' => ['required', 'string', 'regex:/^(equipment|supply):\d+$/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'item.required' => 'Choose the item to place in this room.',
        ]);

        return DB::transaction(function () use ($data, $room) {
            $item = $this->resolveItem((string) $data['item']);
            $previous = $item->room;

            if ($previous && $previous->id === $room->id) {
                return back()->with('error', "{$item->name} is already in {$room->code}.");
            }

            $item->roomMovementNotes = $data['notes'] ?? null;
            $item->update(['room_id' => $room->id]);

            ActivityLog::record('updated', 'rooms', "Placed {$item->name} in {$room->code}".($previous ? ", moved from {$previous->code}." : '.'));

            return back()->with('success', $previous
                ? "{$item->name} moved to {$room->code} from {$previous->code}."
                : "{$item->name} placed in {$room->code}.");
        });
    }

    /**
     * Transfer an item out of this room and into another.
     *
     * The item's own `room_id` is the location, so the transfer is one update and
     * both rooms re-read themselves: it leaves this room's lists and appears in
     * the destination's. The papers already raised for it are untouched — a PAR
     * filed in the old room stays the paper it was, and the history records the
     * move.
     */
    public function transfer(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'item' => ['required', 'string', 'regex:/^(equipment|supply):\d+$/'],
            'to_room_id' => ['required', 'integer', 'exists:rooms,id', Rule::notIn([$room->id])],
            'reason' => ['required', Rule::in(array_keys(RoomMovement::REASONS))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'item.required' => 'Choose the item to transfer.',
            'to_room_id.not_in' => 'Choose the room the item is moving to.',
            'reason.required' => 'Choose the reason for the transfer.',
        ]);

        return DB::transaction(function () use ($data, $room) {
            $item = $this->resolveItem((string) $data['item']);

            if ((int) $item->room_id !== $room->id) {
                return back()->with('error', "{$item->name} is not in {$room->code}.");
            }

            $destination = Room::findOrFail($data['to_room_id']);
            $item->roomMovementReason = $data['reason'];
            $item->roomMovementNotes = $data['notes'] ?? null;
            $item->update(['room_id' => $destination->id]);

            ActivityLog::record('updated', 'rooms', "Transferred {$item->name} from {$room->code} to {$destination->code}.");

            return back()->with('success', "{$item->name} transferred from {$room->code} to {$destination->code}.");
        });
    }

    /**
     * The inventory figures over the room: how many pieces of equipment and how
     * many supplies are in it, and how many of those fall on each paper.
     *
     * Read through the model's own classification (`classification()`), so the
     * numbers on the cards are the same rule the badges are drawn from — a
     * supply is a RIS at any price, property is a PAR above the threshold and an
     * ICS at or below it.
     *
     * @param  Collection<int, Equipment>  $equipment
     * @param  Collection<int, Supply>  $supplies
     * @return array<string, int>
     */
    private function classificationCounts(Collection $equipment, Collection $supplies): array
    {
        $counts = array_fill_keys(ItemClassifier::TYPES, 0);

        foreach ($equipment->concat($supplies) as $item) {
            $type = $item->classification();
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /** The equipment or supply row named by an "equipment:7" / "supply:3" value. */
    private function resolveItem(string $value): Equipment|Supply
    {
        [$kind, $id] = explode(':', $value, 2);

        return $kind === 'equipment'
            ? Equipment::query()->lockForUpdate()->findOrFail($id)
            : Supply::query()->lockForUpdate()->findOrFail($id);
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('rooms', 'code')->ignore($room?->id)],
            'name' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(Room::TYPES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
