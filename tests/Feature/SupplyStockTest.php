<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Role;
use App\Models\Supply;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SupplyStockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
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

    public function test_supplies_cannot_be_deleted(): void
    {
        $supply = $this->supply();

        $this->assertFalse(Route::has('admin.supplies.destroy'));

        $this->actingAs($this->admin)
            ->delete("/admin/supplies/{$supply->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('supplies', ['id' => $supply->id]);
    }

    public function test_editing_a_supply_cannot_change_stock_or_cost(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)
            ->put("/admin/supplies/{$supply->id}", [
                'item_code' => 'SUP-00002',
                'name' => 'Ballpoint Pen (Blue)',
                'category' => 'Office Supplies',
                'unit' => 'piece',
                'quantity_on_hand' => 999,
                'unit_cost' => 99.99,
                'reorder_level' => 999,
            ])
            ->assertRedirect(route('admin.supplies.show', $supply));

        $supply->refresh();

        $this->assertSame('Ballpoint Pen (Blue)', $supply->name);
        // Stock and cost are locked to their transactions...
        $this->assertSame(300, $supply->quantity_on_hand);
        $this->assertSame('8.00', $supply->unit_cost);
        // ...but the re-order point is a supply-level policy, so it is editable here.
        $this->assertSame(999, $supply->reorder_level);
    }

    public function test_adding_stock_increases_quantity_and_writes_history(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)
            ->post("/admin/supplies/{$supply->id}/stock", [
                'quantity' => 100,
                'unit_cost' => '8.00',
                'remarks' => 'New delivery from supplier',
                // A tampered total must be ignored: the server recomputes it.
                'total_cost' => 1,
            ])
            ->assertRedirect(route('admin.supplies.show', $supply));

        $this->assertSame(400, $supply->refresh()->quantity_on_hand);

        $movement = Inventory::where('supply_id', $supply->id)->latest('id')->first();

        $this->assertSame('in', $movement->type);
        $this->assertSame(100, $movement->quantity);
        $this->assertSame('8.00', $movement->unit_cost);
        $this->assertSame('800.00', $movement->total_cost);
        $this->assertSame(400, $movement->balance_after);
        $this->assertSame('New delivery from supplier', $movement->remarks);
        $this->assertSame($this->admin->id, $movement->user_id);
    }

    public function test_adding_stock_keeps_the_second_transaction_separate(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", ['quantity' => 100, 'unit_cost' => '8.00']);
        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", ['quantity' => 200, 'unit_cost' => '8.50']);

        $this->assertSame(600, $supply->refresh()->quantity_on_hand);
        $this->assertSame(2, Inventory::where('supply_id', $supply->id)->count());
        $this->assertSame('1700.00', (string) Inventory::where('supply_id', $supply->id)->latest('id')->value('total_cost'));
        $this->assertSame('8.50', (string) $supply->refresh()->unit_cost);
    }

    public function test_stock_quantity_and_cost_are_validated(): void
    {
        $supply = $this->supply();

        foreach ([['quantity' => 0], ['quantity' => -50], ['quantity' => 'abc'], ['quantity' => 10, 'unit_cost' => -5]] as $payload) {
            $this->actingAs($this->admin)
                ->post("/admin/supplies/{$supply->id}/stock", $payload)
                ->assertSessionHasErrors();
        }

        $this->actingAs($this->admin)
            ->post("/admin/supplies/{$supply->id}/stock", ['quantity' => 10, 'unit_cost' => 15.5])
            ->assertSessionHasNoErrors();

        $this->assertSame(310, $supply->refresh()->quantity_on_hand);
    }

    public function test_supply_list_offers_edit_only(): void
    {
        $this->supply();

        $response = $this->actingAs($this->admin)->get('/admin/supplies');

        $response->assertOk()
            ->assertSee('Ballpoint Pen (Black)')
            ->assertSee('Edit')
            ->assertSee('Qty on Hand')
            // The re-order point lives on the form and the details page, not here.
            ->assertDontSee('Reorder Level')
            ->assertDontSee('This will permanently delete this supply.');
    }

    public function test_edit_form_only_edits_product_information(): void
    {
        $supply = $this->supply();

        $response = $this->actingAs($this->admin)->get("/admin/supplies/{$supply->id}/edit");

        $response->assertOk()
            ->assertSee('Item Code')
            ->assertSee('Description')
            ->assertSee('Category')
            ->assertSee('Unit')
            ->assertSee('Storage Room')
            ->assertSee('Reorder Level')
            // Stock and cost stay out of this form: they belong to the transactions.
            ->assertDontSee('Quantity on Hand')
            ->assertDontSee('Unit Cost');
    }

    public function test_details_page_shows_add_stock_and_costed_history(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", [
            'quantity' => 100,
            'unit_cost' => '8.00',
            'remarks' => 'New delivery',
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/supplies/{$supply->id}");

        $response->assertOk()
            ->assertSee('Supply Information')
            ->assertSee('Add Stock')
            ->assertSee('400 piece')
            ->assertSee('Stock In')
            ->assertSee('+100')
            ->assertSee('₱8.00')
            ->assertSee('₱800.00')
            ->assertSee('New delivery')
            ->assertSee($this->admin->name)
            ->assertSee('Reorder Level');
    }

    public function test_a_stock_in_records_the_reference_it_came_in_on(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", [
            'quantity' => 100,
            'unit_cost' => '8.00',
            'reference' => 'IAR-2026-001',
            'remarks' => 'New delivery',
        ]);

        $movement = Inventory::where('supply_id', $supply->id)->latest('id')->firstOrFail();

        $this->assertSame('IAR-2026-001', $movement->reference);
        // The typed reference does not disturb the document link columns.
        $this->assertNull($movement->reference_type);
        $this->assertNull($movement->reference_id);

        // The history table shows it, next to the movement it belongs to.
        $this->actingAs($this->admin)->get("/admin/supplies/{$supply->id}")
            ->assertOk()
            ->assertSee('Reference')
            ->assertSee('IAR-2026-001');
    }

    public function test_the_reference_is_optional_and_validated(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", [
            'quantity' => 10,
            'unit_cost' => '8.00',
        ]);

        $this->assertNull(Inventory::where('supply_id', $supply->id)->latest('id')->firstOrFail()->reference);

        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", [
            'quantity' => 10,
            'unit_cost' => '8.00',
            'reference' => str_repeat('x', 300),
        ])->assertSessionHasErrors('reference');

        $this->assertSame(310, $supply->fresh()->quantity_on_hand);
    }

    public function test_the_reference_prints_on_the_items_stock_card(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)->post('/admin/cards', [
            'type' => 'SC',
            'item' => "supply:{$supply->id}",
            'entity_name' => 'Isabela State University',
            'fund_cluster' => '01',
        ])->assertRedirect();

        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", [
            'quantity' => 100,
            'unit_cost' => '8.00',
            'reference' => 'IAR-2026-001',
        ]);

        $card = \App\Models\Card::where('supply_id', $supply->id)->firstOrFail();
        $line = $card->transactions()->latest('id')->firstOrFail();

        $this->assertSame('IAR-2026-001', $line->reference);
        $this->assertSame(100, $line->receipt_qty);

        // It reaches the printed card, which is what the office files.
        $this->actingAs($this->admin)->get("/admin/cards/{$card->id}/print")
            ->assertOk()
            ->assertSee('IAR-2026-001');
    }

    public function test_details_page_lists_an_empty_history_state(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)
            ->get("/admin/supplies/{$supply->id}")
            ->assertOk()
            ->assertSee('No stock movements recorded.');
    }

    public function test_stock_transactions_are_timestamped_in_the_local_clock(): void
    {
        $this->assertNotSame('UTC', config('app.timezone'), 'The application must run on the local timezone, not UTC.');

        $supply = $this->supply();

        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", [
            'quantity' => 100,
            'unit_cost' => '8.00',
        ]);

        $recorded = Inventory::where('supply_id', $supply->id)->latest('id')->first()->created_at;

        // The previous minute is allowed so this cannot flake on a minute boundary.
        $this->assertContains(
            $recorded->format('Y-m-d H:i'),
            [Carbon::now('Asia/Manila')->format('Y-m-d H:i'), Carbon::now('Asia/Manila')->subMinute()->format('Y-m-d H:i')],
            'A stock transaction must record the current local time, not the UTC wall clock.'
        );
    }

    public function test_stock_history_displays_local_time(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", [
            'quantity' => 100,
            'unit_cost' => '8.00',
        ]);

        $html = $this->actingAs($this->admin)
            ->get("/admin/supplies/{$supply->id}")
            ->assertOk()
            ->getContent();

        $local = [
            Carbon::now('Asia/Manila')->format('M d, Y g:i A'),
            Carbon::now('Asia/Manila')->subMinute()->format('M d, Y g:i A'),
        ];

        $utc = [
            Carbon::now('UTC')->format('M d, Y g:i A'),
            Carbon::now('UTC')->subMinute()->format('M d, Y g:i A'),
        ];

        $this->assertTrue(
            collect($local)->contains(fn ($time) => str_contains($html, $time)),
            'The history table must show the local transaction time.'
        );

        foreach ($utc as $time) {
            $this->assertFalse(
                str_contains($html, $time),
                "The history table must not display the UTC time [{$time}]."
            );
        }
    }

    public function test_guests_cannot_add_stock(): void
    {
        $supply = $this->supply();

        $this->post("/admin/supplies/{$supply->id}/stock", ['quantity' => 5, 'unit_cost' => 1])
            ->assertRedirect('/login');

        $this->assertSame(300, $supply->refresh()->quantity_on_hand);
        $this->assertSame(0, Inventory::count());
    }
}
