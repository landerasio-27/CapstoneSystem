<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Items are created with nothing on hand — stock only arrives through Add Stock
 * (supplies) or Add Quantity (equipment). Until then the item is not offered
 * where stock is being drawn on, so the pickers never list what the shelf does
 * not have.
 */
class ItemStockGatingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function supplyPayload(array $extra = []): array
    {
        return $extra + [
            'item_code' => 'SUP-00001',
            'name' => 'Bond Paper A4',
            'category' => 'Office Supplies',
            'unit' => 'ream',
            'reorder_level' => 10,
            'is_active' => 1,
        ];
    }

    private function equipmentPayload(array $extra = []): array
    {
        return $extra + [
            'item_code' => 'EQP-00001',
            'name' => 'Epson LCD Projector',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'status' => 'available',
        ];
    }

    public function test_a_new_supply_starts_with_nothing_on_hand(): void
    {
        $this->actingAs($this->admin)->post('/admin/supplies', $this->supplyPayload())->assertRedirect();

        $supply = Supply::latest('id')->firstOrFail();

        $this->assertSame(0, $supply->quantity_on_hand);
        $this->assertSame('0.00', (string) $supply->unit_cost);

        // Nothing to type: the form carries no quantity or cost input.
        $this->actingAs($this->admin)->get('/admin/supplies/create')
            ->assertOk()
            ->assertDontSee('name="quantity_on_hand"', false)
            ->assertDontSee('name="unit_cost"', false);
    }

    public function test_a_new_equipment_starts_with_nothing_on_hand(): void
    {
        $this->actingAs($this->admin)->post('/admin/equipment', $this->equipmentPayload())->assertRedirect();

        $equipment = Equipment::latest('id')->firstOrFail();

        $this->assertSame(0, $equipment->quantity_on_hand);
        $this->assertSame('0.00', (string) $equipment->unit_cost);

        $this->actingAs($this->admin)->get('/admin/equipment/create')
            ->assertOk()
            ->assertDontSee('name="quantity_on_hand"', false)
            ->assertDontSee('name="unit_cost"', false);
    }

    public function test_an_item_with_no_stock_is_not_offered_on_a_document(): void
    {
        // Created but never received: it must not be selectable yet.
        $this->actingAs($this->admin)->post('/admin/supplies', $this->supplyPayload())->assertRedirect();
        $this->actingAs($this->admin)->post('/admin/equipment', $this->equipmentPayload())->assertRedirect();

        $this->actingAs($this->admin)->get('/admin/documents/create?type=RIS')
            ->assertOk()
            ->assertDontSee('Bond Paper A4 (SUP-00001)')
            ->assertSee('No items with stock on hand yet');

        $this->actingAs($this->admin)->get('/admin/documents/create?type=PAR')
            ->assertOk()
            ->assertDontSee('Epson LCD Projector (EQP-00001)')
            ->assertSee('No items with stock on hand yet');
    }

    public function test_a_supply_appears_once_stock_is_added(): void
    {
        $this->actingAs($this->admin)->post('/admin/supplies', $this->supplyPayload())->assertRedirect();
        $supply = Supply::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", [
            'quantity' => 50,
            'unit_cost' => '220.00',
            'remarks' => 'Initial delivery',
        ])->assertSessionHasNoErrors();

        $this->assertSame(50, $supply->refresh()->quantity_on_hand);

        $this->actingAs($this->admin)
            ->get('/admin/documents/create?type=RIS')
            ->assertOk()
            ->assertSee('Bond Paper A4 (SUP-00001)')
            ->assertDontSee('No items with stock on hand yet');
    }

    public function test_equipment_appears_once_a_quantity_is_received(): void
    {
        $this->actingAs($this->admin)->post('/admin/equipment', $this->equipmentPayload())->assertRedirect();
        $equipment = Equipment::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->post("/admin/equipment/{$equipment->id}/quantity", [
            'transaction_date' => '2026-09-22',
            'quantity' => 2,
            // Above the Property Classification Threshold: the receipt makes the
            // item property that belongs on a PAR.
            'unit_cost' => '55000.00',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $equipment->refresh()->quantity_on_hand);

        $this->actingAs($this->admin)
            ->get('/admin/documents/create?type=PAR')
            ->assertOk()
            ->assertSee('Epson LCD Projector (EQP-00001)')
            ->assertDontSee('No items with stock on hand yet');
    }

    public function test_a_zero_stock_item_is_not_offered_for_a_new_card(): void
    {
        $this->actingAs($this->admin)->post('/admin/supplies', $this->supplyPayload())->assertRedirect();

        $this->actingAs($this->admin)->get('/admin/cards/create?type=SC')
            ->assertOk()
            ->assertDontSee('Bond Paper A4 (SUP-00001)')
            ->assertSee('No supply can start a new card');
    }

    public function test_editing_a_draft_keeps_an_item_whose_stock_has_run_out(): void
    {
        $supply = Supply::create($this->supplyPayload([
            'unit' => 'piece',
            'quantity_on_hand' => 5,
        ]));

        $this->actingAs($this->admin)->post('/admin/documents', [
            'type' => 'RIS',
            'document_date' => '2026-09-22',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'items' => [['item' => 'supply:' . $supply->id, 'quantity' => 5]],
        ])->assertRedirect();

        $document = Document::latest('id')->firstOrFail();

        // The stock is drawn down to nothing afterwards.
        $supply->update(['quantity_on_hand' => 0]);

        $this->actingAs($this->admin)
            ->get("/admin/documents/{$document->id}/edit")
            ->assertOk()
            // The line is still there, and still selected, so saving keeps it.
            // (The option also carries the classification it will produce, which
            // is what the row badge and the panel read.)
            ->assertSee('value="supply:' . $supply->id . '" data-classification="RIS" data-consumable="1" selected>', false);
    }
}
