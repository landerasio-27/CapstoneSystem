<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Role;
use App\Models\Room;
use App\Models\Supply;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * What a supply staff account may do once it is signed in.
 *
 * The panel is the supply office's, and both of its accounts work the same
 * records: the inventory (supplies, equipment), the rooms and custodians those
 * point at, the document register the PAR / ICS / RIS papers are raised in,
 * the cards ledger and the reports. Only the back office — user management,
 * the activity log and the archive — is the administrator's alone.
 */
class SupplyStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => Role::ADMIN, 'display_name' => 'Admin']);
        $staff = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Supply Staff']);

        $this->admin = User::factory()->create(['role_id' => $admin->id, 'is_active' => true]);
        $this->staff = User::factory()->create(['role_id' => $staff->id, 'is_active' => true]);
    }

    private function room(array $attributes = []): Room
    {
        return Room::create($attributes + [
            'code' => 'A101',
            'name' => 'Computer Laboratory 1',
            'building' => 'Main Building A',
            'floor' => '1st Floor',
            'is_active' => true,
        ]);
    }

    private function supply(array $attributes = []): Supply
    {
        return Supply::create($attributes + [
            'item_code' => 'SUP-00001',
            'name' => 'Bond Paper A4 (Ream)',
            'category' => 'Office Supplies',
            'unit' => 'ream',
            'quantity_on_hand' => 50,
            'unit_cost' => 220,
            'reorder_level' => 0,
            'is_active' => true,
        ]);
    }

    private function equipment(array $attributes = []): Equipment
    {
        return Equipment::create($attributes + [
            'item_code' => 'EQP-00001',
            'name' => 'Epson LCD Projector',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 28000,
            'reorder_level' => 0,
            'status' => 'available',
        ]);
    }

    /**
     * The forms behind the Add and Edit buttons on every working page open for
     * a supply staff account. These are the buttons the index pages already
     * draw for every role, so a refusal here is a button that leads nowhere.
     */
    public function test_a_supply_staff_account_can_open_every_write_form(): void
    {
        $supply = $this->supply();
        $equipment = $this->equipment();
        $room = $this->room();
        $teacher = Teacher::create([
            'employee_id' => 'EMP-00001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'room_id' => $room->id,
            'is_active' => true,
        ]);

        $urls = [
            '/admin/supplies/create',
            "/admin/supplies/{$supply->id}/edit",
            '/admin/equipment/create',
            "/admin/equipment/{$equipment->id}/edit",
            '/admin/rooms/create',
            "/admin/rooms/{$room->id}/edit",
            '/admin/teachers/create',
            "/admin/teachers/{$teacher->id}/edit",
            '/admin/documents/create?type=PAR',
            '/admin/cards/create?type=SC',
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }
    }

    public function test_a_supply_staff_account_can_add_a_supply(): void
    {
        $this->actingAs($this->staff)
            ->post('/admin/supplies', [
                'item_code' => 'SUP-00009',
                'name' => 'Ballpoint Pen (Black)',
                'category' => 'Office Supplies',
                'unit' => 'piece',
                'reorder_level' => 10,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('supplies', [
            'item_code' => 'SUP-00009',
            'name' => 'Ballpoint Pen (Black)',
        ]);
    }

    public function test_a_supply_staff_account_can_edit_a_supply(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->staff)
            ->put("/admin/supplies/{$supply->id}", [
                'item_code' => 'SUP-00001',
                'name' => 'Bond Paper A4 (Ream, 80gsm)',
                'category' => 'Office Supplies',
                'unit' => 'ream',
                'reorder_level' => 5,
            ])
            ->assertRedirect(route('admin.supplies.show', $supply));

        $this->assertSame('Bond Paper A4 (Ream, 80gsm)', $supply->refresh()->name);
    }

    public function test_a_supply_staff_account_can_record_stock_in(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->staff)
            ->post("/admin/supplies/{$supply->id}/stock", [
                'quantity' => 25,
                'unit_cost' => '220.00',
            ])
            ->assertRedirect(route('admin.supplies.show', $supply));

        $this->assertSame(75, $supply->refresh()->quantity_on_hand);
    }

    public function test_a_supply_staff_account_can_add_and_edit_equipment(): void
    {
        $this->actingAs($this->staff)
            ->post('/admin/equipment', [
                'item_code' => 'EQP-00002',
                'name' => 'Acer Veriton Desktop',
                'category' => 'ICT Equipment',
                'unit' => 'unit',
                'status' => 'available',
            ])
            ->assertRedirect();

        $equipment = Equipment::query()->where('item_code', 'EQP-00002')->firstOrFail();

        $this->actingAs($this->staff)
            ->put("/admin/equipment/{$equipment->id}", [
                'item_code' => 'EQP-00002',
                'name' => 'Acer Veriton Desktop (i5)',
                'unit' => 'unit',
                'status' => 'issued',
            ])
            ->assertRedirect(route('admin.equipment.show', $equipment));

        $this->assertSame('Acer Veriton Desktop (i5)', $equipment->refresh()->name);
    }

    public function test_a_supply_staff_account_can_record_a_quantity_received(): void
    {
        $equipment = $this->equipment();

        $this->actingAs($this->staff)
            ->post("/admin/equipment/{$equipment->id}/quantity", [
                'transaction_date' => '2026-10-01',
                'quantity' => 4,
                'unit_cost' => '28000.00',
            ])
            ->assertRedirect(route('admin.equipment.show', $equipment));

        $this->assertSame(5, $equipment->refresh()->quantity_on_hand);
    }

    public function test_a_supply_staff_account_can_add_edit_and_delete_a_room(): void
    {
        $this->actingAs($this->staff)
            ->post('/admin/rooms', [
                'code' => 'B204',
                'name' => 'Supply Storage Room',
                'building' => 'Main Building B',
                'floor' => '2nd Floor',
            ])
            ->assertRedirect(route('admin.rooms.index'));

        $room = Room::query()->where('code', 'B204')->firstOrFail();

        $this->actingAs($this->staff)
            ->put("/admin/rooms/{$room->id}", [
                'code' => 'B204',
                'name' => 'Supply Storage Room 2',
                'building' => 'Main Building B',
                'floor' => '2nd Floor',
            ])
            ->assertRedirect(route('admin.rooms.index'));

        $this->assertSame('Supply Storage Room 2', $room->refresh()->name);

        $this->actingAs($this->staff)
            ->delete("/admin/rooms/{$room->id}")
            ->assertRedirect(route('admin.rooms.index'));

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_a_supply_staff_account_can_add_edit_and_delete_a_custodian(): void
    {
        // A new custodian opens their own folder: the folder is the record, so
        // the flow ends where the documents and equipment will gather. The
        // employee number is the system's, so nobody types one.
        $this->actingAs($this->staff)
            ->post('/admin/teachers', [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
            ])
            ->assertRedirect(route('admin.teachers.show', Teacher::query()->where('last_name', 'Santos')->firstOrFail()));

        $teacher = Teacher::query()->where('last_name', 'Santos')->firstOrFail();

        $this->assertSame('FAC-0001', $teacher->employee_id);

        $this->actingAs($this->staff)
            ->put("/admin/teachers/{$teacher->id}", [
                'first_name' => 'Maria',
                'last_name' => 'Santos-Reyes',
            ])
            ->assertRedirect(route('admin.teachers.show', $teacher));

        $this->assertSame('Maria Santos-Reyes', $teacher->refresh()->full_name);

        $this->actingAs($this->staff)
            ->delete("/admin/teachers/{$teacher->id}")
            ->assertRedirect(route('admin.teachers.index'));

        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
    }

    /**
     * Supplies and equipment are kept for audit: their stock and cost history,
     * and the document lines that point at them, hang off the record. No role
     * deletes one, and the guard is the missing route itself — not a hidden
     * button anyone could post around.
     */
    public function test_supplies_and_equipment_stay_undeletable_for_both_roles(): void
    {
        $supply = $this->supply();
        $equipment = $this->equipment();

        $this->assertFalse(Route::has('admin.supplies.destroy'));
        $this->assertFalse(Route::has('admin.equipment.destroy'));

        $this->actingAs($this->staff)->delete("/admin/supplies/{$supply->id}")->assertStatus(405);
        $this->actingAs($this->staff)->delete("/admin/equipment/{$equipment->id}")->assertStatus(405);

        $this->assertDatabaseHas('supplies', ['id' => $supply->id]);
        $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);
    }

    /** The register and its papers, which the supply office raises itself. */
    public function test_a_supply_staff_account_can_work_the_document_register_and_cards(): void
    {
        // The card form lists the items a card can be written for.
        $this->supply();

        $urls = [
            '/admin/documents',
            '/admin/documents/create?type=RIS',
            '/admin/cards',
            '/admin/cards/stock',
            '/admin/cards/property',
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }
    }

    public function test_the_table_reports_are_open_to_a_supply_staff_account(): void
    {
        $this->supply();

        $this->actingAs($this->staff)->get('/admin/reports')->assertOk()->assertSee('Inventory Summary');

        foreach (['inventory-summary', 'supply-issuance', 'document-register'] as $report) {
            $this->actingAs($this->staff)->get("/admin/reports/{$report}")->assertOk();
        }
    }

    /** The back office is the administrator's, and stays refused to staff. */
    public function test_the_back_office_stays_administrator_only(): void
    {
        foreach (['/admin/users', '/admin/activity-logs', '/admin/archive'] as $url) {
            $this->actingAs($this->staff)->get($url)->assertForbidden();
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        // Filing and adding archive years post from the settings panel.
        $this->actingAs($this->staff)
            ->post('/admin/account-settings/archive', ['year' => 2026])
            ->assertForbidden();
    }

    /** The administrator keeps every door the supply staff account has. */
    public function test_the_administrator_keeps_the_same_access(): void
    {
        $room = $this->room();

        $this->actingAs($this->admin)->get('/admin/supplies/create')->assertOk();
        $this->actingAs($this->admin)->get('/admin/equipment/create')->assertOk();
        $this->actingAs($this->admin)->get("/admin/rooms/{$room->id}/edit")->assertOk();
        $this->actingAs($this->admin)->get('/admin/teachers/create')->assertOk();
        $this->actingAs($this->admin)->get('/admin/reports/inventory-summary')->assertOk();
    }

    /** An account that is not of the supply office reaches none of it. */
    public function test_an_inactive_supply_staff_account_is_turned_away(): void
    {
        $this->staff->update(['is_active' => false]);

        $this->actingAs($this->staff->refresh())->get('/admin/supplies/create')->assertForbidden();
        $this->actingAs($this->staff->refresh())->get('/admin/supplies')->assertForbidden();
    }
}
