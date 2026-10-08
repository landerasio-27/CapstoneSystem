<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Role;
use App\Models\Room;
use App\Models\Supply;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A room is the reference point for everything assigned to it: a teacher points
 * at it for their assigned room, equipment for where it sits, and supplies for
 * where they are stored. The details page reads those records, so moving one
 * between rooms updates both rooms with nothing to re-count by hand.
 */
class RoomDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function room(array $attributes = []): Room
    {
        return Room::create($attributes + [
            'code' => 'A' . (Room::count() + 101),
            'name' => 'Computer Laboratory',
            'building' => 'Main Building A',
            'floor' => '1st Floor',
            'type' => 'laboratory',
            'is_active' => true,
        ]);
    }

    private function teacher(array $attributes = []): Teacher
    {
        return Teacher::create($attributes + [
            'employee_id' => 'EMP-' . str_pad((string) (Teacher::count() + 1), 4, '0', STR_PAD_LEFT),
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
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
            'unit_cost' => 35000,
            'status' => 'available',
        ]);
    }

    private function supply(array $attributes = []): Supply
    {
        return Supply::create($attributes + [
            'item_code' => 'SUP-' . str_pad((string) (Supply::count() + 1), 5, '0', STR_PAD_LEFT),
            'name' => 'Ballpoint Pen (Black)',
            'category' => 'Office Supplies',
            'unit' => 'piece',
            'quantity_on_hand' => 300,
            'reorder_level' => 0,
            'is_active' => true,
        ]);
    }

    public function test_the_room_row_opens_the_room_details_page(): void
    {
        $room = $this->room();
        $href = route('admin.rooms.show', $room);

        $this->actingAs($this->admin)
            ->get('/admin/rooms')
            ->assertOk()
            // The row carries the room's own destination, so the page is
            // reachable without an Edit link standing beside it.
            ->assertSee('data-href="' . $href . '"', false);

        // And no cell has to look like the way in: the code and the name are
        // written as plain text, leaving the row the one way into the room.
        $html = $this->actingAs($this->admin)->get('/admin/rooms')->getContent();
        // (The row's own data-href is not a link: nothing in the list opens the
        // room but the row itself.)
        $this->assertSame(0, substr_count($html, '<a href="' . $href . '"'));
        $this->assertStringContainsString('<span class="text-slate-700">' . $room->code . '</span>', $html);
        $this->assertStringContainsString('<span class="text-slate-600">' . $room->name . '</span>', $html);

        // Neither the list nor the room's own page offers to remove a room: the
        // register keeps the rooms its assignments are read against.
        $this->assertStringNotContainsString(route('admin.rooms.edit', $room), $html);
        $this->assertStringNotContainsString('>Edit<', $html);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('Edit Room')
            ->assertDontSee('Delete Room')
            ->assertDontSee('This will permanently delete this room.');
    }

    public function test_the_room_details_page_lists_only_its_own_teachers(): void
    {
        $room = $this->room(['code' => 'A101', 'name' => 'Computer Laboratory 1']);
        $other = $this->room(['code' => 'A102', 'name' => 'Computer Laboratory 2']);

        $this->teacher(['room_id' => $room->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'department' => 'CCS']);
        $this->teacher(['room_id' => $other->id, 'first_name' => 'Maria', 'last_name' => 'Santos', 'department' => 'COE']);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('Custodians Assigned (1)')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('EMP-0001')
            ->assertSee('CCS')
            ->assertSee(route('admin.teachers.show', Teacher::where('last_name', 'Dela Cruz')->firstOrFail()), false)
            ->assertDontSee('Maria Santos');
    }

    public function test_equipment_assigned_to_the_room_is_listed_with_its_custodian(): void
    {
        $room = $this->room(['code' => 'A101']);
        $other = $this->room(['code' => 'A102']);

        $custodian = $this->teacher(['room_id' => $room->id, 'first_name' => 'Ricardo', 'last_name' => 'Ramos']);
        $mine = $this->equipment(['room_id' => $room->id, 'teacher_id' => $custodian->id, 'name' => 'Dell OptiPlex Desktop Computer', 'status' => 'missing']);
        $theirs = $this->equipment(['room_id' => $other->id, 'name' => 'Epson LCD Projector']);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('Equipment Assigned (1)')
            ->assertSee('Dell OptiPlex Desktop Computer')
            ->assertSee($mine->item_code)
            ->assertSee($mine->property_number)
            ->assertSee('Ricardo Ramos')
            ->assertSee('Missing')
            ->assertSee(route('admin.equipment.show', $mine), false)
            ->assertDontSee('Epson LCD Projector')
            ->assertDontSee($theirs->item_code);
    }

    public function test_supplies_stored_in_the_room_are_listed_and_link_to_the_supply(): void
    {
        $room = $this->room(['code' => 'A101']);
        $other = $this->room(['code' => 'A102']);

        $mine = $this->supply(['room_id' => $room->id, 'name' => 'Ballpoint Pen (Black)', 'quantity_on_hand' => 300, 'unit' => 'piece']);
        $this->supply(['room_id' => $other->id, 'name' => 'Bond Paper A4', 'item_code' => 'SUP-00999']);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('Supplies / Items in Room (1)')
            ->assertSee('Ballpoint Pen (Black)')
            ->assertSee($mine->item_code)
            ->assertSee('Office Supplies')
            ->assertSee('piece')
            ->assertSee(route('admin.supplies.show', $mine), false)
            ->assertDontSee('Bond Paper A4');
    }

    public function test_the_counts_follow_the_assignments_when_an_item_moves_rooms(): void
    {
        $a = $this->room(['code' => 'A101', 'name' => 'Computer Laboratory 1']);
        $b = $this->room(['code' => 'A102', 'name' => 'Computer Laboratory 2']);

        $this->teacher(['room_id' => $a->id]);
        $first = $this->equipment(['room_id' => $a->id]);
        $this->equipment(['room_id' => $a->id, 'name' => 'Epson LCD Projector']);
        $this->supply(['room_id' => $a->id]);
        $this->equipment(['room_id' => $b->id, 'name' => 'HP LaserJet Printer']);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.show', $a))
            ->assertOk()
            ->assertSee('Equipment Assigned (2)')
            ->assertSee('Supplies / Items in Room (1)');

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.show', $b))
            ->assertOk()
            ->assertSee('Equipment Assigned (1)')
            ->assertSee('No custodians are currently assigned to this room.');

        // The list columns are counted the same way, not stored on the room.
        $html = $this->actingAs($this->admin)->get('/admin/rooms')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/A101<\/span>.*?<td[^>]*>1<\/td>\s*<td[^>]*>2<\/td>/s', $html);

        // Move one item from A101 to A102: both rooms re-count themselves.
        $first->update(['room_id' => $b->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.show', $a))
            ->assertOk()
            ->assertSee('Equipment Assigned (1)');

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.show', $b))
            ->assertOk()
            ->assertSee('Equipment Assigned (2)');

        $html = $this->actingAs($this->admin)->get('/admin/rooms')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/A101<\/span>.*?<td[^>]*>1<\/td>\s*<td[^>]*>1<\/td>/s', $html);
    }

    public function test_an_empty_room_shows_a_clean_empty_state_not_an_empty_table(): void
    {
        $room = $this->room(['code' => 'B201', 'name' => 'Spare Classroom']);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('No custodians are currently assigned to this room.')
            ->assertSee('No equipment is currently assigned to this room.')
            ->assertSee('No supplies are currently stored in this room.')
            ->assertSee('Custodians Assigned (0)')
            ->assertSee('Equipment Assigned (0)')
            ->assertSee('Supplies / Items in Room (0)')
            ->assertDontSee('<table', false);
    }

    public function test_rooms_can_be_searched_by_building(): void
    {
        $this->room(['code' => 'A101', 'name' => 'Computer Laboratory 1', 'building' => 'Main Building A']);
        $this->room(['code' => 'C301', 'name' => 'Science Laboratory', 'building' => 'Science Hall']);

        $this->actingAs($this->admin)
            ->get('/admin/rooms?search=' . urlencode('Science Hall'))
            ->assertOk()
            ->assertSee('Science Laboratory')
            ->assertDontSee('Computer Laboratory 1');
    }

    public function test_guests_cannot_open_the_room_details_page(): void
    {
        $room = $this->room();

        $this->get(route('admin.rooms.show', $room))->assertRedirect('/login');
    }
}
