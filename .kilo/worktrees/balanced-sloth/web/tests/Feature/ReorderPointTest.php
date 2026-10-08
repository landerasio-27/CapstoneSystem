<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Role;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The re-order point is part of the supply record again: it is set on the supply
 * form, returned by the desktop API, printed in the inventory summary report and
 * shown on the dashboard. Equipment keeps no threshold field (the dashboard's
 * Low Stock tile, list filter and status maths are supply-only), and the
 * retained equipment column is preserved untouched.
 */
class ReorderPointTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function supplyPayload(array $overrides = []): array
    {
        return $overrides + [
            'item_code' => 'SUP-00002',
            'name' => 'Ballpoint Pen (Black)',
            'category' => 'Office Supplies',
            'unit' => 'piece',
            'reorder_level' => 50,
            'is_active' => 1,
        ];
    }

    private function supply(array $attributes = []): Supply
    {
        return Supply::create($attributes + [
            'item_code' => 'SUP-00002',
            'name' => 'Ballpoint Pen (Black)',
            'category' => 'Office Supplies',
            'unit' => 'piece',
            'quantity_on_hand' => 300,
            'unit_cost' => 8,
            'reorder_level' => 50,
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
            'status' => 'available',
        ]);
    }

    public function test_the_supply_form_offers_a_reorder_level_again(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)
            ->get('/admin/supplies/create')
            ->assertOk()
            ->assertSee('Reorder Level')
            ->assertSee('reorder_level');

        $this->actingAs($this->admin)
            ->get("/admin/supplies/{$supply->id}/edit")
            ->assertOk()
            ->assertSee('Reorder Level')
            // The stored value is what the form shows.
            ->assertSee('value="50"', false);
    }

    public function test_the_supply_details_page_shows_the_reorder_level(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)
            ->get("/admin/supplies/{$supply->id}")
            ->assertOk()
            ->assertSee('Reorder Level')
            ->assertSee('50 piece');
    }

    public function test_creating_a_supply_stores_its_reorder_level(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/supplies', $this->supplyPayload(['reorder_level' => 75]))
            ->assertRedirect();

        $this->assertSame(75, Supply::firstOrFail()->reorder_level);
        // Stock still starts at zero; only the threshold was set.
        $this->assertSame(0, Supply::firstOrFail()->quantity_on_hand);
    }

    public function test_editing_a_supply_can_change_its_reorder_level(): void
    {
        $supply = $this->supply(['reorder_level' => 50]);

        $this->actingAs($this->admin)
            ->put("/admin/supplies/{$supply->id}", $this->supplyPayload(['reorder_level' => 120]))
            ->assertRedirect();

        $this->assertSame(120, $supply->fresh()->reorder_level);
        // The stock itself is still off-limits on this form.
        $this->assertSame(300, $supply->fresh()->quantity_on_hand);
    }

    public function test_a_missing_reorder_level_falls_back_to_zero_rather_than_failing(): void
    {
        $payload = $this->supplyPayload();
        unset($payload['reorder_level']);

        $this->actingAs($this->admin)->post('/admin/supplies', $payload)->assertRedirect();

        $this->assertSame(0, Supply::firstOrFail()->reorder_level);
    }

    public function test_a_negative_reorder_level_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/supplies', $this->supplyPayload(['reorder_level' => -5]))
            ->assertSessionHasErrors('reorder_level');

        $this->assertSame(0, Supply::count());
    }

    public function test_the_desktop_api_accepts_and_returns_the_reorder_level(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/supplies', [
            'item_code' => 'SUP-00009',
            'name' => 'Stapler',
            'unit' => 'piece',
            'quantity_on_hand' => 5,
            'unit_cost' => 120,
            'reorder_level' => 3,
        ])->assertCreated()->assertJsonPath('data.reorder_level', 3);

        $this->assertSame(3, Supply::firstOrFail()->reorder_level);

        $supply = Supply::firstOrFail();
        $this->putJson("/api/admin/supplies/{$supply->id}", [
            'item_code' => $supply->item_code,
            'name' => $supply->name,
            'unit' => $supply->unit,
            'quantity_on_hand' => 5,
            'unit_cost' => 120,
            'reorder_level' => 12,
        ])->assertOk()->assertJsonPath('data.reorder_level', 12);

        $this->assertSame(12, $supply->fresh()->reorder_level);
    }

    public function test_the_inventory_summary_report_lists_the_reorder_level_for_supplies(): void
    {
        Sanctum::actingAs($this->admin);

        $this->supply(['reorder_level' => 50]);
        $this->equipment(['reorder_level' => 25]);

        $response = $this->getJson('/api/admin/reports/inventory-summary')->assertOk();

        $headings = $response->json('data.headings');
        $this->assertContains('Reorder Level', $headings);

        $column = array_search('Reorder Level', $headings, true);

        foreach ($response->json('data.rows') as $row) {
            $this->assertCount(count($headings), $row);
        }

        $rows = collect($response->json('data.rows'));
        // A supply prints its own threshold; equipment has none to print.
        $this->assertSame(50, $rows->firstWhere(0, 'Supply')[$column]);
        $this->assertSame('—', $rows->firstWhere(0, 'Equipment')[$column]);
    }

    public function test_equipment_still_has_no_reorder_level_field(): void
    {
        $equipment = $this->equipment(['reorder_level' => 25]);

        foreach (["/admin/equipment/create", "/admin/equipment/{$equipment->id}/edit"] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertDontSee('Reorder Level');
        }

        // Posting one changes nothing, and the stored value survives.
        $this->actingAs($this->admin)->put("/admin/equipment/{$equipment->id}", [
            'item_code' => $equipment->item_code,
            'name' => $equipment->name,
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 28000,
            'status' => 'available',
            'reorder_level' => 999,
        ])->assertRedirect();

        $this->assertSame(25, $equipment->fresh()->reorder_level);
    }

    public function test_the_reorder_level_drives_the_low_stock_indicators(): void
    {
        $below = $this->supply(['quantity_on_hand' => 8, 'reorder_level' => 20, 'name' => 'Whiteboard Marker']);
        $above = $this->supply(['item_code' => 'SUP-00001', 'quantity_on_hand' => 300, 'reorder_level' => 50, 'name' => 'Ballpoint Pen (Black)']);

        $this->assertTrue($below->isLowStock());
        $this->assertFalse($above->isLowStock());

        // The list badge, and the filter the dashboard's Low Stock tile links to.
        $this->actingAs($this->admin)
            ->get('/admin/supplies?low_stock=1')
            ->assertOk()
            ->assertSee('Whiteboard Marker')
            ->assertDontSee('Ballpoint Pen (Black)');
    }
}
