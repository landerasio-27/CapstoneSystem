<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\Supply;
use App\Models\User;
use App\Services\CardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutomaticCardsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function payload(string $register): array
    {
        return [
            'item_code' => 'AUTO-' . $register,
            'name' => 'Automatic ' . $register,
            'unit' => 'piece',
            'status' => 'available',
            'reorder_level' => 10,
        ];
    }

    public function test_web_creation_opens_an_empty_matching_card_and_links_to_it(): void
    {
        foreach (['supplies' => 'SC', 'equipment' => 'PC'] as $register => $type) {
            $this->actingAs($this->admin)->post('/admin/' . $register, $this->payload($register))
                ->assertRedirect()->assertSessionHasNoErrors();
            $card = Card::where('type', $type)->sole();
            $item = $type === 'SC' ? Supply::sole() : Equipment::sole();

            $this->assertSame($item->id, $type === 'SC' ? $card->supply_id : $card->equipment_id);
            $this->assertNull($type === 'SC' ? $card->equipment_id : $card->supply_id);
            $this->assertSame($this->admin->id, $card->created_by);
            $this->assertSame(Card::DEFAULT_ENTITY, $card->entity_name);
            $this->assertSame(0, $item->quantity_on_hand);
            $this->assertSame(0, $card->transactions()->count());
            $this->assertSame(0, $card->balance());
            $this->get('/admin/' . $register . '/' . $item->id)->assertOk()
                ->assertSee($card->card_no)->assertSee(route('admin.cards.show', $card), false);
            $this->get(route('admin.cards.show', $card))->assertOk()->assertSee($item->name);
            $this->put('/admin/' . $register . '/' . $item->id, $this->payload($register) + ['description' => 'Updated'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(1, Card::where('type', $type)->count());
        }
    }

    public function test_receipts_are_posted_once_to_the_automatic_cards(): void
    {
        foreach (['supplies' => 'SC', 'equipment' => 'PC'] as $register => $type) {
            $this->actingAs($this->admin)->post('/admin/' . $register, $this->payload($register))->assertRedirect();
            $item = $type === 'SC' ? Supply::sole() : Equipment::sole();
            $endpoint = $type === 'SC' ? 'stock' : 'quantity';
            $this->post('/admin/' . $register . '/' . $item->id . '/' . $endpoint, [
                'quantity' => 3,
                'unit_cost' => 250,
                'transaction_date' => today()->toDateString(),
                'reference' => 'AUTO-RECEIPT',
            ])->assertRedirect()->assertSessionHasNoErrors();

            $card = Card::where('type', $type)->sole();
            app(CardService::class)->sync($card);
            $this->assertSame(1, $card->transactions()->count());
            $this->assertSame(3, $card->balance());
            $this->assertSame('750.00', $card->transactions()->sole()->amount);
            $this->post('/admin/cards', [
                'type' => $type,
                'item' => ($type === 'SC' ? 'supply:' : 'equipment:') . $item->id,
                'entity_name' => Card::DEFAULT_ENTITY,
            ])
                ->assertSessionHasErrors('type');
            $this->assertSame(1, Card::where('type', $type)->count());
        }
    }

    public function test_api_creation_projects_opening_stock_into_matching_cards(): void
    {
        foreach (['supplies' => 'SC', 'equipment' => 'PC'] as $register => $type) {
            $this->actingAs($this->admin)->postJson('/api/admin/' . $register, $this->payload($register) + [
                'quantity_on_hand' => 4,
                'unit_cost' => 200,
            ])->assertCreated();
            $card = Card::where('type', $type)->sole();
            $this->assertSame($this->admin->id, $card->created_by);
            $this->assertSame(4, $card->balance());
            $line = $card->transactions()->sole();
            $this->assertSame(4, $line->receipt_qty);
            $this->assertSame('800.00', $line->amount);
            app(CardService::class)->sync($card);
            $this->assertSame(1, $card->transactions()->count());
        }
    }

    public function test_supply_staff_also_get_automatic_cards(): void
    {
        $role = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Supply Staff']);
        $staff = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        foreach (['supplies' => 'SC', 'equipment' => 'PC'] as $register => $type) {
            $this->actingAs($staff)->post('/admin/' . $register, $this->payload($register))
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($staff->id, Card::where('type', $type)->sole()->created_by);
        }
    }

    public function test_card_failure_rolls_back_new_items_for_web_and_api(): void
    {
        $this->mock(CardService::class, function ($mock) {
            $mock->shouldReceive('create')->times(4)->andThrow(
                ValidationException::withMessages(['type' => 'Card creation failed.'])
            );
        });
        foreach (['supplies', 'equipment'] as $register) {
            $this->actingAs($this->admin)->post('/admin/' . $register, $this->payload($register))
                ->assertSessionHasErrors('type');
            $this->postJson('/api/admin/' . $register, $this->payload($register) + [
                'quantity_on_hand' => 4, 'unit_cost' => 200,
            ])->assertUnprocessable()->assertJsonValidationErrors('type');
        }
        $this->assertDatabaseCount('supplies', 0);
        $this->assertDatabaseCount('equipment', 0);
        $this->assertDatabaseCount('cards', 0);
        $this->assertDatabaseCount('inventory', 0);
        $this->assertDatabaseCount('equipment_transactions', 0);
    }
}
