<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Equipment;
use App\Models\EquipmentTransaction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Equipment information is edited separately from its quantity/cost ledger:
 * basic information lives on the record, every quantity change is a receipt in
 * `equipment_transactions` with its own price and the user who recorded it.
 */
class EquipmentLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'name' => 'Lander Asio']);
        $this->otherAdmin = User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'name' => 'Maria Santos']);
    }

    private function equipment(array $attributes = []): Equipment
    {
        return Equipment::create($attributes + [
            'item_code' => 'EQP-00001',
            'property_number' => 'PN-00001',
            'name' => 'Dell OptiPlex Desktop Computer',
            'description' => 'Desktop computer for classroom/laboratory use.',
            'category' => 'ICT Equipment',
            'unit' => 'piece',
            'quantity_on_hand' => 1,
            'unit_cost' => 35000,
            'status' => 'available',
        ]);
    }

    private function addQuantity(Equipment $equipment, array $payload)
    {
        return $this->actingAs($this->admin)
            ->post("/admin/equipment/{$equipment->id}/quantity", $payload + [
                'transaction_date' => now()->toDateString(),
            ]);
    }

    public function test_equipment_cannot_be_deleted_from_the_list(): void
    {
        $equipment = $this->equipment();

        $this->assertFalse(Route::has('admin.equipment.destroy'));

        $this->actingAs($this->admin)
            ->delete("/admin/equipment/{$equipment->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);

        // The list offers Edit, and no delete action.
        $this->actingAs($this->admin)->get('/admin/equipment')
            ->assertOk()
            ->assertSee('Edit')
            ->assertDontSee('This will permanently delete this equipment record.')
            ->assertDontSee('Delete equipment?');
    }

    public function test_the_edit_form_only_edits_basic_information(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 5, 'unit_cost' => 35000]);

        $response = $this->actingAs($this->admin)->get("/admin/equipment/{$equipment->id}/edit");

        $response->assertOk()
            ->assertSee('Item Code')
            ->assertSee('Property Number')
            ->assertSee('Name')
            ->assertSee('Description')
            ->assertSee('Category')
            ->assertSee('Unit')
            ->assertSee('Room')
            ->assertSee('Custodian')
            // Quantity and cost belong to the transactions, not this form.
            ->assertDontSee('Quantity on Hand')
            ->assertDontSee('Unit Cost');
    }

    public function test_editing_basic_information_leaves_quantity_cost_and_history_alone(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 2, 'unit_cost' => 35000]);

        $this->addQuantity($equipment, ['quantity' => 3, 'unit_cost' => '35000.00', 'reference' => 'PAR-2026-001']);

        $this->actingAs($this->admin)->put("/admin/equipment/{$equipment->id}", [
            'item_code' => $equipment->item_code,
            'property_number' => 'PN-00001',
            'name' => 'Dell OptiPlex Desktop Computer',
            'description' => 'Desktop computer for classroom/laboratory use.',
            'category' => 'ICT Equipment',
            'unit' => 'piece',
            'status' => 'available',
            // Quantity and cost are not part of this form; posting them changes nothing.
            'quantity_on_hand' => 999,
            'unit_cost' => 1,
        ])->assertRedirect();

        $equipment = $equipment->fresh();

        $this->assertSame(5, $equipment->quantity_on_hand);
        $this->assertSame('35000.00', $equipment->unit_cost);
        $this->assertSame('Dell OptiPlex Desktop Computer', $equipment->name);
        $this->assertSame(1, $equipment->transactions()->count());
        $this->assertSame('PAR-2026-001', $equipment->transactions()->first()->reference);
    }

    public function test_add_quantity_increases_the_quantity_and_records_the_history(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 2, 'unit_cost' => 0]);

        $this->addQuantity($equipment, [
            'transaction_date' => '2026-09-22',
            'reference' => 'PAR-2026-001',
            'quantity' => 3,
            'unit_cost' => '35000.00',
            'source' => 'ISU Procurement Office',
            'remarks' => 'Initial receipt',
        ])->assertRedirect("/admin/equipment/{$equipment->id}");

        $this->assertSame(5, $equipment->fresh()->quantity_on_hand);

        $line = EquipmentTransaction::firstOrFail();

        $this->assertSame($equipment->id, $line->equipment_id);
        $this->assertSame('receipt', $line->type);
        $this->assertSame('Receipt', $line->typeLabel());
        $this->assertSame(3, $line->quantity);
        $this->assertSame('+3', $line->signedQuantity());
        $this->assertSame('35000.00', $line->unit_cost);
        // Total cost is calculated, never taken from the request.
        $this->assertSame('105000.00', $line->total_cost);
        $this->assertSame(5, $line->balance_after);
        $this->assertSame('PAR-2026-001', $line->reference);
        $this->assertSame('ISU Procurement Office', $line->source);
        $this->assertSame('Initial receipt', $line->remarks);
        $this->assertSame($this->admin->id, $line->user_id);
        $this->assertSame('2026-09-22', $line->transaction_date->toDateString());
        $this->assertNotNull($line->created_at);

        // The current unit cost follows the newest receipt.
        $this->assertSame('35000.00', $equipment->fresh()->unit_cost);
    }

    public function test_the_total_cost_is_always_calculated_on_the_server(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 0, 'unit_cost' => 0]);

        $this->addQuantity($equipment, [
            'quantity' => 100,
            'unit_cost' => '8.00',
            'total_cost' => '1.00',
            'balance_after' => 9999,
            'user_id' => $this->otherAdmin->id,
        ]);

        $line = EquipmentTransaction::firstOrFail();

        $this->assertSame('800.00', $line->total_cost);
        $this->assertSame(100, $line->balance_after);
        // The user is the authenticated account, never the posted one.
        $this->assertSame($this->admin->id, $line->user_id);
        $this->assertNotSame($this->otherAdmin->id, $line->user_id);
    }

    public function test_add_quantity_validates_the_input(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 0]);

        foreach ([['quantity' => 0], ['quantity' => -50], ['quantity' => 'abc']] as $bad) {
            $this->actingAs($this->admin)
                ->post("/admin/equipment/{$equipment->id}/quantity", $bad + ['unit_cost' => '8.00'])
                ->assertSessionHasErrors('quantity');
        }

        $this->actingAs($this->admin)
            ->post("/admin/equipment/{$equipment->id}/quantity", ['quantity' => 1, 'unit_cost' => -5])
            ->assertSessionHasErrors('unit_cost');

        $this->actingAs($this->admin)
            ->post("/admin/equipment/{$equipment->id}/quantity", ['quantity' => 1])
            ->assertSessionHasErrors('unit_cost');

        $this->actingAs($this->admin)
            ->post("/admin/equipment/{$equipment->id}/quantity", ['quantity' => 1, 'unit_cost' => '8.00'])
            ->assertSessionHasErrors('transaction_date');

        $this->assertSame(0, EquipmentTransaction::count());
        $this->assertSame(0, $equipment->fresh()->quantity_on_hand);
    }

    public function test_each_receipt_keeps_its_own_price(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 0, 'unit_cost' => 0]);

        $this->addQuantity($equipment, ['quantity' => 100, 'unit_cost' => '8.00']);
        $this->addQuantity($equipment, ['quantity' => 200, 'unit_cost' => '8.50']);

        $lines = EquipmentTransaction::orderBy('id')->get();

        $this->assertSame('800.00', $lines[0]->total_cost);
        $this->assertSame('1700.00', $lines[1]->total_cost);
        $this->assertSame(300, $equipment->fresh()->quantity_on_hand);
        // The record's cost is the latest one; the earlier receipt is untouched.
        $this->assertSame('8.50', $equipment->fresh()->unit_cost);
        $this->assertSame('8.00', $lines[0]->fresh()->unit_cost);
    }

    public function test_the_details_page_shows_the_values_and_the_history(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 0, 'unit_cost' => 0]);

        $this->addQuantity($equipment, [
            'transaction_date' => '2026-09-22',
            'reference' => 'PAR-2026-001',
            'quantity' => 5,
            'unit_cost' => '35000.00',
            'source' => 'ISU Procurement Office',
            'remarks' => 'Initial receipt',
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/equipment/{$equipment->id}");

        $response->assertOk()
            ->assertSee('Equipment Information')
            ->assertSee('Current Quantity')
            ->assertSee('5 piece')
            ->assertSee('Current Unit Cost')
            ->assertSee('35,000.00')
            ->assertSee('Current Total Value')
            ->assertSee('175,000.00')
            ->assertSee('Add Quantity')
            ->assertSee('Equipment Movement History')
            ->assertSee('Receipt')
            ->assertSee('PAR-2026-001')
            ->assertSee('ISU Procurement Office')
            ->assertSee('Initial receipt')
            ->assertSee($this->admin->name);
    }

    public function test_the_history_is_empty_for_an_item_with_no_receipts(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 4]);

        $this->actingAs($this->admin)->get("/admin/equipment/{$equipment->id}")
            ->assertOk()
            ->assertSee('No equipment movements recorded.')
            // The carried-over quantity is explained rather than shown as history.
            ->assertSee('was on record before transactions were tracked here');
    }

    public function test_a_new_record_starts_empty_and_is_filled_by_a_receipt(): void
    {
        $this->actingAs($this->admin)->post('/admin/equipment', [
            'item_code' => 'EQP-00099',
            'property_number' => 'PN-00099',
            'name' => 'Epson LCD Projector',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'status' => 'available',
            // Posting a quantity or cost on this form must not create stock.
            'quantity_on_hand' => 7,
            'unit_cost' => 28000,
        ])->assertRedirect();

        $equipment = Equipment::where('item_code', 'EQP-00099')->firstOrFail();

        $this->assertSame(0, $equipment->quantity_on_hand);
        $this->assertSame('0.00', $equipment->unit_cost);

        $this->addQuantity($equipment, ['quantity' => 2, 'unit_cost' => '28000.00']);

        $this->assertSame(2, $equipment->fresh()->quantity_on_hand);
        $this->assertSame('56000.00', EquipmentTransaction::firstOrFail()->total_cost);
    }

    public function test_the_receipt_posts_a_line_on_the_items_property_card(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 0, 'unit_cost' => 0]);

        $this->actingAs($this->admin)->post('/admin/cards', [
            'type' => 'PC',
            'item' => "equipment:{$equipment->id}",
            'entity_name' => 'Isabela State University',
            'fund_cluster' => '01',
        ])->assertRedirect();

        $this->addQuantity($equipment, [
            'quantity' => 3,
            'unit_cost' => '35000.00',
            'reference' => 'PAR-2026-001',
        ]);

        $card = Card::where('equipment_id', $equipment->id)->firstOrFail();
        $lines = $card->transactions()->get();

        $this->assertCount(1, $lines);
        $this->assertSame(3, $lines[0]->receipt_qty);
        $this->assertSame(3, $lines[0]->balance_qty);
        $this->assertSame('PAR-2026-001', $lines[0]->reference);
        $this->assertSame('105000.00', $lines[0]->amount);

        // Re-syncing (again and again) must not double-post the receipt.
        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/sync");
        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/sync");

        $this->assertSame(1, $card->transactions()->count());
    }
}
