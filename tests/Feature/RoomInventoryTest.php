<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\Supply;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Rooms module's own two writes.
 *
 * An item's location is the single `room_id` on its own row, so placing it in a
 * room or moving it to another is one update — no second record is created, and
 * a supply or a piece of equipment appears in the room it names. Every move
 * writes one row of history (from, to, why, who), which is what the room page
 * reads back.
 */
class RoomInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'name' => 'Cristina Bautista',
            'is_active' => true,
        ]);
    }

    private function room(array $attributes = []): Room
    {
        return Room::create($attributes + [
            'code' => 'A' . (Room::count() + 101),
            'name' => 'Computer Laboratory',
            'building' => 'Main Building A',
            'floor' => '1st Floor',
            'department' => 'College of Computing Studies',
            'type' => 'laboratory',
            'is_active' => true,
        ]);
    }

    private function teacher(array $attributes = []): Teacher
    {
        return Teacher::create($attributes + [
            'employee_id' => 'EMP-' . str_pad((string) (Teacher::count() + 1), 4, '0', STR_PAD_LEFT),
            'first_name' => 'Ricardo',
            'last_name' => 'Ramos',
            'department' => 'CCS',
            'is_active' => true,
        ]);
    }

    private function equipment(array $attributes = []): Equipment
    {
        return Equipment::create($attributes + [
            'item_code' => 'EQP-' . str_pad((string) (Equipment::count() + 1), 5, '0', STR_PAD_LEFT),
            'property_number' => 'PN-' . str_pad((string) (Equipment::count() + 1), 5, '0', STR_PAD_LEFT),
            'name' => 'Dell OptiPlex Desktop',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 55000,
            'status' => 'available',
        ]);
    }

    private function supply(array $attributes = []): Supply
    {
        return Supply::create($attributes + [
            'item_code' => 'SUP-' . str_pad((string) (Supply::count() + 1), 5, '0', STR_PAD_LEFT),
            'name' => 'Bond Paper A4',
            'category' => 'Office Supplies',
            'unit' => 'ream',
            'quantity_on_hand' => 20,
            'reorder_level' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Just the inventory the room page lists — without the sheets and pickers
     * further down, which offer the whole catalogue rather than what is in this
     * room.
     */
    private function inventoryHtml(string $html): string
    {
        $start = strpos($html, 'Equipment &amp; Property');
        if ($start === false) {
            $start = strpos($html, 'Supplies (');
        }
        $this->assertNotFalse($start, 'The room page listed no inventory at all.');

        return substr($html, $start, strpos($html, 'Documents Filed to This Room') - $start);
    }

    /** Just the history table, so a name in the page header is not read as the recorder. */
    private function historyHtml(string $html): string
    {
        $start = strpos($html, 'Inventory History');
        $this->assertNotFalse($start, 'The room page printed no inventory history.');

        return substr($html, $start, strpos($html, 'Custodians Assigned') - $start);
    }

    public function test_an_item_is_placed_in_a_room_without_a_second_record_being_created(): void
    {
        $room = $this->room(['code' => 'A101']);
        $item = $this->equipment(['name' => 'Dell OptiPlex Desktop', 'unit_cost' => 55000]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.items.store', $room), [
                'item' => 'equipment:' . $item->id,
                'notes' => 'Delivered with the new laboratory units.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Dell OptiPlex Desktop placed in Computer Laboratory.');

        // The one record now names the room — nothing was copied.
        $this->assertSame($room->id, $item->refresh()->room_id);
        $this->assertSame(1, Equipment::count());

        // And the move is on the room's history, with who did it.
        $this->assertDatabaseHas('room_movements', [
            'item_type' => 'equipment',
            'item_id' => $item->id,
            'item_label' => 'Dell OptiPlex Desktop',
            'action' => 'added',
            'from_room_id' => null,
            'to_room_id' => $room->id,
            'user_id' => $this->admin->id,
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.rooms.show', $room))->assertOk()->getContent();
        $this->assertStringContainsString('Equipment &amp; Property (1)', $html);
        $this->assertStringContainsString('Dell OptiPlex Desktop', $html);

        // The move is on the room's history, with who did it.
        $history = $this->historyHtml($html);
        $this->assertStringContainsString('Added', $history);
        $this->assertStringContainsString('Cristina Bautista', $history);
        $this->assertStringContainsString('Delivered with the new laboratory units.', $history);
    }

    public function test_a_supply_is_placed_in_a_room_and_reads_as_a_ris_there(): void
    {
        $room = $this->room(['code' => 'A101']);
        $supply = $this->supply(['name' => 'Bond Paper A4', 'quantity_on_hand' => 20]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.items.store', $room), ['item' => 'supply:' . $supply->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($room->id, $supply->refresh()->room_id);
        $this->assertSame(1, Supply::count());

        $this->actingAs($this->admin)->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('Supplies (1)')
            ->assertSee('Bond Paper A4')
            ->assertSee('title="Requisition and Issue Slip — Consumable Supply"', false);
    }

    public function test_an_item_already_in_the_room_is_not_placed_again(): void
    {
        $room = $this->room(['code' => 'A101']);
        $item = $this->equipment(['room_id' => $room->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.items.store', $room), ['item' => 'equipment:' . $item->id])
            ->assertRedirect()
            ->assertSessionHas('error', 'Dell OptiPlex Desktop is already in Computer Laboratory.');

        // Creating an item in a room records its initial placement; this
        // refused request must not add a second movement.
        $this->assertSame(1, RoomMovement::count());
        $this->assertSame(1, Equipment::count());
    }

    public function test_placing_an_item_requires_choosing_one_from_the_catalogue(): void
    {
        $room = $this->room(['code' => 'A101']);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.items.store', $room), ['item' => ''])
            ->assertSessionHasErrors('item');

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.items.store', $room), ['item' => 'equipment:99999'])
            ->assertNotFound();
    }

    public function test_an_item_is_transferred_between_rooms_and_both_rooms_read_themselves(): void
    {
        $from = $this->room(['code' => 'A101', 'name' => 'Computer Laboratory 1']);
        $to = $this->room(['code' => 'B102', 'name' => 'Computer Laboratory 2']);
        $item = $this->equipment(['room_id' => $from->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.transfer', $from), [
                'item' => 'equipment:' . $item->id,
                'to_room_id' => $to->id,
                'reason' => 'relocation',
                'notes' => 'Moved with the new laboratory layout.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Dell OptiPlex Desktop transferred from Computer Laboratory 1 to Computer Laboratory 2.');

        // One update, so it leaves one room and arrives in the other at once.
        $this->assertSame($to->id, $item->refresh()->room_id);

        $this->assertDatabaseHas('room_movements', [
            'item_type' => 'equipment',
            'item_id' => $item->id,
            'action' => 'transferred',
            'from_room_id' => $from->id,
            'to_room_id' => $to->id,
            'reason' => 'relocation',
            'notes' => 'Moved with the new laboratory layout.',
            'user_id' => $this->admin->id,
        ]);

        // The room it left no longer lists it, and shows the move out.
        $left = $this->actingAs($this->admin)->get(route('admin.rooms.show', $from))->assertOk()->getContent();
        $this->assertStringContainsString('This room is currently empty', $left);
        $this->assertStringContainsString('Transferred', $this->historyHtml($left));
        $this->assertStringContainsString('Relocation of item', $this->historyHtml($left));
        $this->assertStringContainsString('Cristina Bautista', $this->historyHtml($left));

        // The room it arrived in lists it, and shows the move in with its notes.
        $arrived = $this->actingAs($this->admin)->get(route('admin.rooms.show', $to))->assertOk()->getContent();
        $this->assertStringContainsString('Equipment &amp; Property (1)', $arrived);
        $this->assertStringContainsString('Dell OptiPlex Desktop', $this->inventoryHtml($arrived));
        $this->assertStringContainsString('Transferred', $this->historyHtml($arrived));
        $this->assertStringContainsString('Relocation of item', $this->historyHtml($arrived));
        $this->assertStringContainsString('Computer Laboratory 1', $this->historyHtml($arrived));
        $this->assertStringContainsString('Moved with the new laboratory layout.', $this->historyHtml($arrived));
    }

    public function test_a_transfer_must_come_from_this_room_and_go_somewhere_else(): void
    {
        $room = $this->room(['code' => 'A101', 'name' => 'Computer Laboratory 1']);
        $other = $this->room(['code' => 'A102', 'name' => 'Computer Laboratory 2']);
        $elsewhere = $this->equipment(['room_id' => $other->id]);

        // An item sitting in another room cannot be transferred out of this one:
        // refused, and nothing moves.
        $this->actingAs($this->admin)
            ->post(route('admin.rooms.transfer', $room), [
                'item' => 'equipment:' . $elsewhere->id,
                'to_room_id' => $other->id,
                'reason' => 'relocation',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Dell OptiPlex Desktop is not in Computer Laboratory 1.');

        $this->assertSame($other->id, $elsewhere->refresh()->room_id);
        $this->assertSame(1, RoomMovement::count());

        // In this room, but asked to move to the room it is already in.
        $item = $this->equipment(['room_id' => $room->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.transfer', $room), [
                'item' => 'equipment:' . $item->id,
                'to_room_id' => $room->id,
                'reason' => 'relocation',
            ])
            ->assertSessionHasErrors('to_room_id');

        $this->assertSame($room->id, $item->refresh()->room_id);
        $this->assertSame(2, RoomMovement::count());

        // No reason recorded: refused, because a move without one is a move
        // nobody can read later.
        $this->actingAs($this->admin)
            ->post(route('admin.rooms.transfer', $room), [
                'item' => 'equipment:' . $item->id,
                'to_room_id' => $other->id,
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame($room->id, $item->refresh()->room_id);
    }

    public function test_the_papers_already_raised_are_not_rewritten_when_an_item_moves(): void
    {
        $from = $this->room(['code' => 'A101']);
        $to = $this->room(['code' => 'B102']);
        $custodian = $this->teacher(['room_id' => $from->id]);
        $item = $this->equipment(['room_id' => $from->id, 'teacher_id' => $custodian->id, 'unit_cost' => 55000]);

        // A PAR filed for this room and this custodian, with the item printed on it.
        $document = Document::create([
            'document_no' => 'PAR-2026-00001',
            'type' => 'PAR',
            'status' => 'issued',
            'document_date' => '2026-09-30',
            'room_id' => $from->id,
            'teacher_id' => $custodian->id,
        ]);

        $item->documentItems()->create([
            'document_id' => $document->id,
            'description' => $item->name,
            'unit' => 'unit',
            'quantity' => 1,
            'unit_cost' => 55000,
            'is_consumable' => false,
            'document_type' => 'PAR',
            'amount' => 55000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.transfer', $from), [
                'item' => 'equipment:' . $item->id,
                'to_room_id' => $to->id,
                'reason' => 'reassignment',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // The item has moved; the paper still says the room it was written for.
        $this->assertSame($to->id, $item->refresh()->room_id);
        $this->assertSame($from->id, $document->refresh()->room_id);
        $this->assertSame($custodian->id, $document->teacher_id);
    }

    public function test_the_room_page_lists_the_papers_filed_to_it_and_the_register_can_narrow_to_the_room(): void
    {
        $room = $this->room(['code' => 'A101']);
        $otherRoom = $this->room(['code' => 'A102']);
        $custodian = $this->teacher(['room_id' => $room->id]);

        Document::create([
            'document_no' => 'ICS-2026-00007',
            'type' => 'ICS',
            'status' => 'issued',
            'document_date' => '2026-10-02',
            'room_id' => $room->id,
            'teacher_id' => $custodian->id,
        ]);

        Document::create([
            'document_no' => 'RIS-2026-00009',
            'type' => 'RIS',
            'status' => 'draft',
            'document_date' => '2026-10-03',
            'room_id' => $otherRoom->id,
        ]);

        $this->actingAs($this->admin)->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('Documents Filed to This Room (1)')
            ->assertSee('ICS-2026-00007')
            ->assertDontSee('RIS-2026-00009');

        // The register's own room filter is what the room page links to.
        $this->actingAs($this->admin)->get('/admin/documents?room=' . $room->id)
            ->assertOk()
            ->assertSee('ICS-2026-00007')
            ->assertDontSee('RIS-2026-00009');
    }

    public function test_the_date_an_item_was_placed_in_the_room_comes_from_the_move(): void
    {
        $room = $this->room(['code' => 'A101']);
        $item = $this->equipment(['name' => 'Dell OptiPlex Desktop', 'date_acquired' => '2026-01-05']);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.items.store', $room), ['item' => 'equipment:' . $item->id])
            ->assertRedirect();

        $this->assertDatabaseHas('room_movements', [
            'item_id' => $item->id,
            'to_room_id' => $room->id,
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.rooms.show', $room))->assertOk()->getContent();
        $inventory = $this->inventoryHtml($html);

        // The day it arrived in the room is what the row prints — not the day it
        // was bought, which is what the record's own date would have said.
        $this->assertStringContainsString(now()->format('M d, Y'), $inventory);
        $this->assertStringNotContainsString('Jan 05, 2026', $inventory);
    }

    public function test_the_room_page_can_be_read_from_the_custodian_folder_and_back_again(): void
    {
        $room = $this->room(['code' => 'B102', 'name' => 'Lecture Room 2']);
        $custodian = $this->teacher(['room_id' => $room->id, 'first_name' => 'Cristina', 'last_name' => 'Bautista']);
        $item = $this->equipment(['room_id' => $room->id, 'teacher_id' => $custodian->id]);

        // The room lists it with the person holding it...
        $this->actingAs($this->admin)->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('Cristina Bautista')
            ->assertSee('Equipment & Property (1)');

        // ...and the custodian's folder lists the same record with the room it
        // sits in. One record, read from both sides.
        $this->actingAs($this->admin)
            ->get(route('admin.teachers.show', $custodian) . '?panel=equipment')
            ->assertOk()
            ->assertSee('Lecture Room 2')
            ->assertSee($item->item_code);
    }

    public function test_custodian_assignment_defaults_only_an_unlocated_item_to_the_custodians_room(): void
    {
        $room = $this->room();
        $other = $this->room(['code' => 'B102']);
        $teacher = $this->teacher(['room_id' => $room->id]);
        $item = $this->equipment();

        $this->actingAs($this->admin)->post(route('admin.teachers.equipment.store', $teacher), [
            'equipment_id' => $item->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($room->id, $item->refresh()->room_id);
        $this->assertSame($teacher->id, $item->teacher_id);
        $this->assertDatabaseHas('room_movements', ['item_id' => $item->id, 'to_room_id' => $room->id]);

        $item->update(['room_id' => $other->id]);
        $this->assertSame($other->id, $item->refresh()->room_id);
        $this->assertSame($teacher->id, $item->teacher_id);
    }

    public function test_location_changes_from_the_inventory_models_are_recorded_without_duplicate_history(): void
    {
        $from = $this->room();
        $to = $this->room(['code' => 'B102']);
        $item = $this->supply(['room_id' => $from->id]);
        $this->assertSame(1, RoomMovement::count());
        $item->update(['room_id' => $to->id]);
        $this->assertSame(2, RoomMovement::count());
        $this->assertDatabaseHas('room_movements', [
            'item_type' => 'supply', 'item_id' => $item->id,
            'from_room_id' => $from->id, 'to_room_id' => $to->id, 'action' => 'transferred',
        ]);
        $item->update(['name' => 'Renamed paper']);
        $this->assertSame(2, RoomMovement::count());
    }

    public function test_room_sections_follow_consumability_rather_than_the_source_register(): void
    {
        $room = $this->room();
        $this->supply(['room_id' => $room->id, 'name' => 'Property Calculator', 'is_consumable' => false]);
        $this->equipment(['room_id' => $room->id, 'name' => 'Consumable Cartridge', 'is_consumable' => true, 'unit_cost' => 100000]);
        $page = $this->actingAs($this->admin)->get(route('admin.rooms.show', $room))->assertOk();
        $this->assertSame('Property Calculator', $page->viewData('equipment')->sole()->name);
        $this->assertSame('Consumable Cartridge', $page->viewData('supplies')->sole()->name);
        $this->assertSame(['PAR' => 0, 'ICS' => 1, 'RIS' => 1], $page->viewData('classificationCounts'));
    }

    public function test_supply_staff_can_place_and_transfer_existing_inventory(): void
    {
        $role = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Supply Staff']);
        $staff = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $from = $this->room();
        $to = $this->room(['code' => 'B102']);
        $item = $this->supply();
        $this->actingAs($staff)->post(route('admin.rooms.items.store', $from), ['item' => 'supply:'.$item->id])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.rooms.transfer', $from), [
            'item' => 'supply:'.$item->id, 'to_room_id' => $to->id, 'reason' => 'storage',
        ])->assertSessionHasNoErrors();
        $this->assertSame($to->id, $item->refresh()->room_id);
        $this->assertSame(20, $item->quantity_on_hand);
        $this->assertSame(1, Supply::count());
    }

    public function test_guests_cannot_change_a_room(): void
    {
        $room = $this->room(['code' => 'A101']);
        $item = $this->equipment();

        $this->post(route('admin.rooms.items.store', $room), ['item' => 'equipment:' . $item->id])
            ->assertRedirect('/login');

        $this->post(route('admin.rooms.transfer', $room), [
            'item' => 'equipment:' . $item->id,
            'to_room_id' => $room->id,
            'reason' => 'relocation',
        ])->assertRedirect('/login');

        $this->assertNull($item->refresh()->room_id);
        $this->assertSame(0, RoomMovement::count());
    }
}
