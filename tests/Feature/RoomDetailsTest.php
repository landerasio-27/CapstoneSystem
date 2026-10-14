<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\Room;
use App\Models\Supply;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A room is a folder over the records that name it: teachers point at it for the
 * room they work in, equipment for where it sits, supplies for where they are
 * stored. The room page reads those rows back, so moving an item between rooms
 * updates both rooms with nothing re-counted by hand.
 *
 * This covers the register (the room folders) and the room page (what is inside
 * it). The module's own writes — placing an item in a room, and transferring it
 * to another — are covered by RoomInventoryTest.
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
            'department' => 'College of Computing Studies',
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

    /** The folder card the register drew for one room, so its figures can be read. */
    private function cardFor(string $html, string $code): string
    {
        $room = Room::where('code', $code)->firstOrFail();
        $start = strpos($html, 'data-href="' . route('admin.rooms.show', $room) . '"');
        $this->assertNotFalse($start, "No folder card was drawn for room {$code}.");

        // Up to the next card — the drawn icons are long enough that a fixed
        // window would cut the last figure off the card.
        $next = strpos($html, 'data-href="', $start + 1);

        return substr($html, $start, ($next === false ? strlen($html) : $next) - $start);
    }

    /**
     * Just the inventory the room page lists — the equipment and supplies
     * tables, without the sheets and pickers further down the page, which offer
     * the whole catalogue rather than what is in this room.
     */
    private function inventoryHtml(string $html): string
    {
        $start = strpos($html, 'Equipment &amp; Property');
        if ($start === false) {
            $start = strpos($html, 'Supplies (');
        }
        $this->assertNotFalse($start, 'The room page listed no inventory at all.');

        $end = strpos($html, 'Documents Filed to This Room');

        return substr($html, $start, $end - $start);
    }

    // -----------------------------------------------------------------------
    // The register
    // -----------------------------------------------------------------------

    public function test_rooms_are_listed_as_folders_with_what_is_inside_them(): void
    {
        $room = $this->room(['code' => 'A101', 'name' => 'Computer Laboratory 1']);
        $empty = $this->room(['code' => 'B201', 'name' => 'Spare Classroom', 'type' => 'classroom']);

        $this->equipment(['room_id' => $room->id, 'name' => 'Dell OptiPlex Desktop Computer']);
        $this->equipment(['room_id' => $room->id, 'name' => 'Epson LCD Projector']);
        $this->supply(['room_id' => $room->id, 'name' => 'Bond Paper A4']);
        $this->teacher(['room_id' => $room->id]);

        $html = $this->actingAs($this->admin)->get('/admin/rooms')->assertOk()->getContent();

        // A folder per room, not a table of rows.
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringContainsString('Open Room', $html);

        // The card carries the room, its department and its kind. The number a
        // room used to be filed under is retired, so it is on no card at all.
        $card = $this->cardFor($html, 'A101');
        $this->assertStringContainsString('Computer Laboratory 1', $card);
        $this->assertStringNotContainsString('A101', $html);
        $this->assertStringContainsString('College of Computing Studies', $card);
        $this->assertStringContainsString('Laboratory', $card);
        $this->assertStringContainsString('Active', $card);

        // ...and what is physically in it: 2 equipment + 1 supply = 3 items.
        $this->assertMatchesRegularExpression(
            '/Equipment<\/dt>\s*<dd[^>]*>2<\/dd>.*?Supplies<\/dt>\s*<dd[^>]*>1<\/dd>.*?Total<\/dt>\s*<dd[^>]*>3<\/dd>/s',
            $card,
        );

        // A room with nothing in it still reads as a folder.
        $emptyCard = $this->cardFor($html, 'B201');
        $this->assertMatchesRegularExpression(
            '/Equipment<\/dt>\s*<dd[^>]*>0<\/dd>.*?Supplies<\/dt>\s*<dd[^>]*>0<\/dd>.*?Total<\/dt>\s*<dd[^>]*>0<\/dd>/s',
            $emptyCard,
        );
        $this->assertStringContainsString(route('admin.rooms.show', $empty), $emptyCard);
    }

    public function test_rooms_can_be_searched_by_name_building_and_department(): void
    {
        $lab = $this->room(['code' => 'A101', 'name' => 'Computer Laboratory 1', 'building' => 'CCS Building', 'department' => 'College of Computing Studies']);
        $other = $this->room(['code' => 'C301', 'name' => 'Science Hall Annex', 'building' => 'Science Hall', 'department' => 'College of Education', 'type' => 'classroom']);

        foreach (['Computer Laboratory', 'CCS Building', 'College of Computing Studies'] as $term) {
            $this->actingAs($this->admin)
                ->get('/admin/rooms?search=' . urlencode($term))
                ->assertOk()
                ->assertSee('data-href="' . route('admin.rooms.show', $lab) . '"', false)
                ->assertDontSee('data-href="' . route('admin.rooms.show', $other) . '"', false);
        }

        // The room number is not a way in any more: the office never sees one to
        // search by, so one typed into the box finds nothing.
        $this->actingAs($this->admin)
            ->get('/admin/rooms?search=A101')
            ->assertOk()
            ->assertDontSee('data-href="' . route('admin.rooms.show', $lab) . '"', false);

        // The kind is matched by the word the office uses, not the value stored
        // for it: "storage" finds a store room whose name never says so.
        $store = $this->room(['code' => 'SUP-01', 'name' => 'Room 101', 'type' => 'storage']);

        $this->actingAs($this->admin)->get('/admin/rooms?search=storage')
            ->assertOk()
            ->assertSee('data-href="' . route('admin.rooms.show', $store) . '"', false)
            ->assertDontSee('data-href="' . route('admin.rooms.show', $lab) . '"', false);
    }

    public function test_rooms_can_be_filtered_by_status_and_by_kind(): void
    {
        $active = $this->room(['code' => 'A101', 'type' => 'laboratory']);
        $inactive = $this->room(['code' => 'A102', 'type' => 'laboratory', 'is_active' => false]);
        $storage = $this->room(['code' => 'SUP-01', 'type' => 'storage']);

        // Active only, inactive only.
        $this->actingAs($this->admin)->get('/admin/rooms?status=active')
            ->assertOk()
            ->assertSee('data-href="' . route('admin.rooms.show', $active) . '"', false)
            ->assertDontSee('data-href="' . route('admin.rooms.show', $inactive) . '"', false);

        $this->actingAs($this->admin)->get('/admin/rooms?status=inactive')
            ->assertOk()
            ->assertSee('data-href="' . route('admin.rooms.show', $inactive) . '"', false)
            ->assertDontSee('data-href="' . route('admin.rooms.show', $active) . '"', false);

        // The kind pill: Laboratories holds both laboratories, whatever their
        // status, and leaves the store room out.
        $this->actingAs($this->admin)->get('/admin/rooms?group=laboratories')
            ->assertOk()
            ->assertSee('data-href="' . route('admin.rooms.show', $active) . '"', false)
            ->assertSee('data-href="' . route('admin.rooms.show', $inactive) . '"', false)
            ->assertDontSee('data-href="' . route('admin.rooms.show', $storage) . '"', false);

        $this->actingAs($this->admin)->get('/admin/rooms?group=storage')
            ->assertOk()
            ->assertSee('data-href="' . route('admin.rooms.show', $storage) . '"', false)
            ->assertDontSee('data-href="' . route('admin.rooms.show', $active) . '"', false);

        // And the full list by value, for the kinds the pills do not cover.
        $faculty = $this->room(['code' => 'A201', 'type' => 'faculty_room']);

        $this->actingAs($this->admin)->get('/admin/rooms?type=faculty_room')
            ->assertOk()
            ->assertSee('data-href="' . route('admin.rooms.show', $faculty) . '"', false)
            ->assertDontSee('data-href="' . route('admin.rooms.show', $active) . '"', false);
    }

    public function test_rooms_can_be_sorted_by_what_they_hold(): void
    {
        $quiet = $this->room(['code' => 'A101', 'name' => 'Quiet Room']);
        $busy = $this->room(['code' => 'B101', 'name' => 'Busy Room']);

        $this->equipment(['room_id' => $busy->id]);
        $this->equipment(['room_id' => $busy->id, 'name' => 'Epson LCD Projector']);
        $this->supply(['room_id' => $quiet->id]);

        // Most Equipment puts the room holding two first.
        $html = $this->actingAs($this->admin)->get('/admin/rooms?sort=equipment')->assertOk()->getContent();
        $this->assertLessThan(
            strpos($html, 'Quiet Room'),
            strpos($html, 'Busy Room'),
            'Sorting by most equipment did not put the fuller room first.',
        );

        // Most Supplies puts the room holding the stock first.
        $html = $this->actingAs($this->admin)->get('/admin/rooms?sort=supplies')->assertOk()->getContent();
        $this->assertLessThan(
            strpos($html, 'Busy Room'),
            strpos($html, 'Quiet Room'),
            'Sorting by most supplies did not put the room holding stock first.',
        );

        // Rooms are listed by name, so Room Name A–Z and Z–A are the two ways
        // through the register now that the number is retired.
        $html = $this->actingAs($this->admin)->get('/admin/rooms?sort=name_asc')->assertOk()->getContent();
        $this->assertLessThan(
            strpos($html, 'Quiet Room'),
            strpos($html, 'Busy Room'),
            'Sorting by room name A-Z did not put Busy Room first.',
        );

        $html = $this->actingAs($this->admin)->get('/admin/rooms?sort=name_desc')->assertOk()->getContent();
        $this->assertLessThan(
            strpos($html, 'Busy Room'),
            strpos($html, 'Quiet Room'),
            'Sorting by room name Z-A did not reverse the order.',
        );
    }

    public function test_a_register_with_nothing_in_it_offers_to_open_a_room(): void
    {
        $this->actingAs($this->admin)->get('/admin/rooms')
            ->assertOk()
            ->assertSee('No rooms yet')
            ->assertSee('New Room');
    }

    /**
     * A room is added and corrected by name alone: the number it used to be
     * filed under is no longer asked for, and an edit never disturbs it.
     */
    public function test_a_room_is_created_and_edited_without_a_room_number(): void
    {
        foreach (['/admin/rooms/create', '/admin/rooms'] as $url) {
            // The register's own two ways in: the form asks for the room, not a
            // number for it.
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertDontSee('Room Number')
                ->assertDontSee('name="code"', false);
        }

        $this->actingAs($this->admin)
            ->post('/admin/rooms', [
                'name' => 'Audio-Visual Room',
                'building' => 'Main Building A',
                'floor' => '2nd Floor',
                'type' => 'other',
            ])
            ->assertRedirect(route('admin.rooms.index'))
            ->assertSessionHasNoErrors();

        $room = Room::latest('id')->firstOrFail();

        $this->assertSame('Audio-Visual Room', $room->name);
        // The row still carries one, minted for it, because older records are
        // keyed by it — it is simply never printed.
        $this->assertNotSame('', (string) $room->code);

        $code = $room->code;

        $this->actingAs($this->admin)->get("/admin/rooms/{$room->id}/edit")
            ->assertOk()
            ->assertSee('Audio-Visual Room')
            ->assertDontSee('Room Number');

        $this->actingAs($this->admin)
            ->put("/admin/rooms/{$room->id}", ['name' => 'Audio-Visual Room 2', 'type' => 'other'])
            ->assertRedirect(route('admin.rooms.index'))
            ->assertSessionHasNoErrors();

        $room->refresh();
        $this->assertSame('Audio-Visual Room 2', $room->name);
        $this->assertSame($code, $room->code, 'An edit must not disturb the code the room is filed under.');
    }

    // -----------------------------------------------------------------------
    // The room page
    // -----------------------------------------------------------------------

    public function test_the_room_page_shows_the_room_information_and_the_inventory_figures(): void
    {
        $room = $this->room([
            'code' => 'A101',
            'name' => 'Computer Laboratory 1',
            'building' => 'CCS Building',
            'floor' => '2nd Floor',
            'department' => 'College of Computing Studies',
            'type' => 'laboratory',
            'description' => 'The main CCS laboratory.',
        ]);

        $custodian = $this->teacher(['room_id' => $room->id]);
        $this->equipment(['room_id' => $room->id, 'teacher_id' => $custodian->id, 'name' => 'Dell OptiPlex Desktop Computer']);
        $this->equipment(['room_id' => $room->id, 'name' => 'Epson LCD Projector', 'unit_cost' => 45000]);
        $this->supply(['room_id' => $room->id, 'name' => 'Bond Paper A4']);

        $this->actingAs($this->admin)->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('Room Information')
            // The room number is retired: the page reads by name and prints none.
            ->assertDontSee('Room Number')
            ->assertDontSee('A101')
            ->assertSee('Computer Laboratory 1')
            ->assertSee('CCS Building')
            ->assertSee('2nd Floor')
            ->assertSee('College of Computing Studies')
            ->assertSee('Laboratory')
            ->assertSee('The main CCS laboratory.')
            ->assertSee('Active')
            // What is inside it, and the way in to each part of it.
            ->assertSee('Equipment & Property (2)')
            ->assertSee('Supplies (1)')
            ->assertSee('Documents Filed to This Room')
            ->assertSee('Inventory History')
            ->assertSee('Add Item')
            ->assertSee('Transfer Item')
            ->assertSee('Edit Room');
    }

    public function test_equipment_is_listed_with_its_paper_its_custodian_and_where_it_sits(): void
    {
        $room = $this->room(['code' => 'A101']);

        $custodian = $this->teacher(['room_id' => $room->id, 'first_name' => 'Ricardo', 'last_name' => 'Ramos']);
        // Above the Property Classification Threshold: a PAR.
        $par = $this->equipment(['room_id' => $room->id, 'teacher_id' => $custodian->id, 'name' => 'Dell OptiPlex Desktop Computer', 'unit_cost' => 55000]);
        // At or below it: an ICS.
        $ics = $this->equipment(['room_id' => $room->id, 'name' => 'Epson LCD Projector', 'unit_cost' => 30000]);
        // Somewhere else entirely: never listed in this room's inventory.
        $elsewhere = $this->equipment(['name' => 'HP LaserJet Printer']);

        $html = $this->actingAs($this->admin)->get(route('admin.rooms.show', $room))->assertOk()->getContent();
        $inventory = $this->inventoryHtml($html);

        $this->assertStringContainsString('Dell OptiPlex Desktop Computer', $inventory);
        $this->assertStringContainsString($par->item_code, $inventory);
        $this->assertStringContainsString($par->property_number, $inventory);
        $this->assertStringContainsString('₱55,000.00', $inventory);
        $this->assertStringContainsString('Ricardo Ramos', $inventory);
        // Each item carries the paper it is recorded on.
        $this->assertStringContainsString('title="Property Acknowledgement Receipt — Non-Consumable Property"', $inventory);
        $this->assertStringContainsString('title="Inventory Custodian Slip — Non-Consumable Property"', $inventory);
        $this->assertStringContainsString($ics->item_code, $inventory);
        $this->assertStringContainsString('₱30,000.00', $inventory);

        // Another room's property is not printed as if it were in this one.
        $this->assertStringNotContainsString('HP LaserJet Printer', $inventory);
        $this->assertStringNotContainsString($elsewhere->item_code, $inventory);
    }

    public function test_supplies_are_listed_as_ris_whatever_they_cost(): void
    {
        $room = $this->room(['code' => 'A101']);

        // An expensive consumable is still a consumable: it never becomes a PAR.
        $this->supply(['room_id' => $room->id, 'name' => 'Printer Ink', 'unit_cost' => 5000, 'quantity_on_hand' => 8, 'unit' => 'bottle']);
        $this->supply(['room_id' => $room->id, 'name' => 'Bond Paper A4', 'quantity_on_hand' => 20, 'unit' => 'ream']);

        $response = $this->actingAs($this->admin)->get(route('admin.rooms.show', $room))->assertOk();

        $inventory = $this->inventoryHtml($response->getContent());
        $this->assertStringContainsString('Printer Ink', $inventory);
        $this->assertStringContainsString('₱5,000.00', $inventory);
        $this->assertStringContainsString('Bond Paper A4', $inventory);
        $this->assertStringContainsString('title="Requisition and Issue Slip — Consumable Supply"', $inventory);

        // The cards over the room count the paper each item is recorded on, and
        // both supplies land on the RIS — never on a property paper.
        $this->assertSame(['PAR' => 0, 'ICS' => 0, 'RIS' => 2], $response->viewData('classificationCounts'));
    }

    public function test_the_room_figures_are_the_items_themselves_not_stored_counts(): void
    {
        $a = $this->room(['code' => 'A101']);
        $b = $this->room(['code' => 'A102']);

        $this->equipment(['room_id' => $a->id, 'unit_cost' => 60000]);
        $this->equipment(['room_id' => $a->id, 'name' => 'Epson LCD Projector', 'unit_cost' => 30000]);
        $this->supply(['room_id' => $a->id]);
        $this->equipment(['room_id' => $b->id, 'name' => 'HP LaserJet Printer']);

        $page = $this->actingAs($this->admin)->get(route('admin.rooms.show', $a))->assertOk();
        $this->assertSame(['PAR' => 1, 'ICS' => 1, 'RIS' => 1], $page->viewData('classificationCounts'));

        $this->actingAs($this->admin)->get(route('admin.rooms.show', $b))
            ->assertOk()
            ->assertSee('Equipment & Property (1)');

        // Move the property on: both rooms read themselves again, with nothing
        // stored on either of them to re-count.
        Equipment::where('room_id', $a->id)->orderBy('id')->firstOrFail()->update(['room_id' => $b->id]);

        $this->actingAs($this->admin)->get(route('admin.rooms.show', $a))
            ->assertOk()
            ->assertSee('Equipment & Property (1)');

        $this->actingAs($this->admin)->get(route('admin.rooms.show', $b))
            ->assertOk()
            ->assertSee('Equipment & Property (2)');
    }

    public function test_the_inventory_search_narrows_to_the_item_the_custodian_or_the_paper(): void
    {
        $room = $this->room(['code' => 'A101']);

        $custodian = $this->teacher(['room_id' => $room->id, 'first_name' => 'Ricardo', 'last_name' => 'Ramos']);
        $this->equipment(['room_id' => $room->id, 'teacher_id' => $custodian->id, 'name' => 'Dell OptiPlex Desktop Computer']);
        $projector = $this->equipment(['room_id' => $room->id, 'name' => 'Epson LCD Projector']);

        // A paper the projector is printed on, raised for this room.
        $document = Document::create([
            'document_no' => 'ICS-2026-00001',
            'type' => 'ICS',
            'status' => 'draft',
            'document_date' => '2026-10-01',
            'room_id' => $room->id,
        ]);

        $projector->documentItems()->create([
            'document_id' => $document->id,
            'description' => 'Epson LCD Projector',
            'unit' => 'unit',
            'quantity' => 1,
            'unit_cost' => 30000,
            'is_consumable' => false,
            'document_type' => 'ICS',
            'amount' => 30000,
        ]);

        $search = fn (string $term) => $this->inventoryHtml(
            $this->actingAs($this->admin)
                ->get(route('admin.rooms.show', ['room' => $room, 'q' => $term]))
                ->assertOk()
                ->getContent(),
        );

        // By item code...
        $found = $search($projector->item_code);
        $this->assertStringContainsString('Epson LCD Projector', $found);
        $this->assertStringNotContainsString('Dell OptiPlex Desktop Computer', $found);

        // ...by the custodian holding it...
        $found = $search('Ramos');
        $this->assertStringContainsString('Dell OptiPlex Desktop Computer', $found);
        $this->assertStringNotContainsString('Epson LCD Projector', $found);

        // ...and by the number of the paper it is printed on.
        $found = $search('ICS-2026-00001');
        $this->assertStringContainsString('Epson LCD Projector', $found);
        $this->assertStringNotContainsString('Dell OptiPlex Desktop Computer', $found);
    }

    public function test_the_tabs_show_one_part_of_the_inventory_at_a_time(): void
    {
        $room = $this->room(['code' => 'A101']);
        $this->equipment(['room_id' => $room->id]);
        $this->supply(['room_id' => $room->id]);

        $this->actingAs($this->admin)->get(route('admin.rooms.show', ['room' => $room, 'tab' => 'equipment']))
            ->assertOk()
            ->assertSee('Equipment & Property (1)')
            ->assertDontSee('Supplies (1)');

        $this->actingAs($this->admin)->get(route('admin.rooms.show', ['room' => $room, 'tab' => 'supplies']))
            ->assertOk()
            ->assertSee('Supplies (1)')
            ->assertDontSee('Equipment & Property (1)');

        $this->actingAs($this->admin)->get(route('admin.rooms.show', ['room' => $room, 'tab' => 'all']))
            ->assertOk()
            ->assertSee('Equipment & Property (1)')
            ->assertSee('Supplies (1)');
    }

    public function test_an_empty_room_shows_a_clean_empty_state(): void
    {
        $room = $this->room(['code' => 'B201', 'name' => 'Spare Classroom']);

        $this->actingAs($this->admin)->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('This room is currently empty')
            ->assertSee('No custodians are currently assigned to this room.')
            ->assertSee('No documents are filed to this room yet.')
            ->assertSee('Nothing has moved in or out of this room yet.')
            // Nothing to transfer out of an empty room, and no empty tables
            // drawn where a sentence says the same thing.
            ->assertSee('This room is empty')
            ->assertDontSee('<table', false);
    }

    public function test_guests_cannot_open_the_room_details_page(): void
    {
        $room = $this->room();

        $this->get(route('admin.rooms.show', $room))->assertRedirect('/login');
        $this->get(route('admin.rooms.index'))->assertRedirect('/login');
    }
}
