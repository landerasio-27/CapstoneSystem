<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\Supply;
use App\Models\User;
use App\Services\CardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cards register filters by last activity — the date the Last activity
 * column shows — so a range answers "which cards moved in this period?".
 * Cards with no transactions have no last activity and drop out of any range.
 */
class CardDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function supply(string $code, string $name): Supply
    {
        return Supply::create([
            'item_code' => $code,
            'name' => $name,
            'category' => 'Office Supplies',
            'unit' => 'piece',
            'quantity_on_hand' => 100,
            'unit_cost' => 8,
            'reorder_level' => 0,
            'is_active' => true,
        ]);
    }

    private function equipment(string $code, string $name): Equipment
    {
        return Equipment::create([
            'item_code' => $code,
            'property_number' => 'PPE-' . $code,
            'name' => $name,
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 45000,
            'status' => 'available',
        ]);
    }

    private function card(string $type, Supply|Equipment $item): Card
    {
        return app(CardService::class)->create($type, $item, ['fund_cluster' => '01']);
    }

    /** Post a card line straight onto the ledger, dated as given. */
    private function line(Card $card, string $date, int $quantity = 10): CardTransaction
    {
        return CardTransaction::create([
            'card_id' => $card->id,
            'transaction_date' => $date,
            'type' => 'receipt',
            'reference' => 'IAR-2026-001',
            'receipt_qty' => $quantity,
            'issue_qty' => 0,
            'balance_qty' => $quantity,
            'created_by' => $this->admin->id,
        ]);
    }

    /** The filter bar itself — the form marked for auto-submit. */
    private function filterBar(string $html): string
    {
        preg_match('/<form\b[^>]*data-auto-filter[^>]*>.*?<\/form>/s', $html, $matches);

        return $matches[0] ?? '';
    }

    public function test_the_filter_bar_applies_without_a_search_button(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/cards')->assertOk()->getContent();
        $bar = $this->filterBar($html);

        $this->assertNotSame('', $bar, 'The cards filter bar should be marked data-auto-filter.');
        $this->assertStringNotContainsString('<button', $bar);
        $this->assertStringContainsString('name="search"', $bar);
        $this->assertStringContainsString('name="date_from"', $bar);

        // The active card type tab rides along with the filters, so applying a
        // date on the Stock Cards page keeps the subset.
        $stock = $this->filterBar(
            $this->actingAs($this->admin)->get('/admin/cards/stock')->assertOk()->getContent()
        );
        $this->assertStringContainsString('name="type" value="SC"', $stock);
    }

    public function test_the_register_offers_from_and_to_date_inputs(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/cards')
            ->assertOk()
            ->assertSee('name="date_from"', false)
            ->assertSee('name="date_to"', false)
            ->assertSee('From')
            ->assertSee('To');
    }

    public function test_the_chosen_dates_are_kept_in_the_inputs(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/cards?date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/name="date_from"\s+value="2026-09-01"/', $html);
        $this->assertMatchesRegularExpression('/name="date_to"\s+value="2026-09-30"/', $html);
        $this->assertStringContainsString('Clear', $html);
    }

    public function test_a_card_whose_last_activity_is_inside_the_range_is_listed(): void
    {
        $card = $this->card('SC', $this->supply('SUP-00002', 'Ballpoint Pen (Black)'));
        $this->line($card, '2026-09-10');

        $this->actingAs($this->admin)
            ->get('/admin/cards?date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk()
            ->assertSee($card->card_no)
            ->assertSee('Ballpoint Pen (Black)');
    }

    public function test_a_card_whose_last_activity_is_outside_the_range_is_hidden(): void
    {
        $card = $this->card('SC', $this->supply('SUP-00002', 'Ballpoint Pen (Black)'));
        $this->line($card, '2026-08-01');

        $this->actingAs($this->admin)
            ->get('/admin/cards?date_from=2026-09-01')
            ->assertOk()
            ->assertDontSee($card->card_no);

        $this->actingAs($this->admin)
            ->get('/admin/cards?date_to=2026-08-15')
            ->assertOk()
            ->assertSee($card->card_no);
    }

    public function test_the_range_uses_the_last_activity_not_any_activity(): void
    {
        $card = $this->card('SC', $this->supply('SUP-00002', 'Ballpoint Pen (Black)'));
        $this->line($card, '2026-08-01');
        $this->line($card, '2026-09-20', 25);

        // An earlier line inside the window does not qualify the card: its last
        // activity (September 20) is what the range is measured against.
        $this->actingAs($this->admin)
            ->get('/admin/cards?date_from=2026-08-01&date_to=2026-08-31')
            ->assertOk()
            ->assertDontSee($card->card_no);
    }

    public function test_a_card_with_no_transactions_drops_out_of_a_date_filter(): void
    {
        $card = $this->card('SC', $this->supply('SUP-00002', 'Ballpoint Pen (Black)'));

        // No filter: the card is listed.
        $this->actingAs($this->admin)
            ->get('/admin/cards')
            ->assertOk()
            ->assertSee($card->card_no);

        // A range cannot include a card that never moved.
        $this->actingAs($this->admin)
            ->get('/admin/cards?date_from=2026-01-01&date_to=2026-12-31')
            ->assertOk()
            ->assertDontSee($card->card_no);
    }

    public function test_the_date_range_works_with_the_card_type_tabs(): void
    {
        $stockCard = $this->card('SC', $this->supply('SUP-00002', 'Ballpoint Pen (Black)'));
        $propertyCard = $this->card('PC', $this->equipment('EQP-00001', 'Dell OptiPlex Desktop'));
        $this->line($stockCard, '2026-09-10');
        $this->line($propertyCard, '2026-09-12');

        $this->actingAs($this->admin)
            ->get('/admin/cards/stock?date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk()
            ->assertSee($stockCard->card_no)
            ->assertDontSee($propertyCard->card_no);

        $this->actingAs($this->admin)
            ->get('/admin/cards/property?date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk()
            ->assertSee($propertyCard->card_no)
            ->assertDontSee($stockCard->card_no);
    }

    public function test_blank_dates_do_not_filter_anything(): void
    {
        $card = $this->card('SC', $this->supply('SUP-00002', 'Ballpoint Pen (Black)'));
        $this->line($card, '2026-08-01');

        $this->actingAs($this->admin)
            ->get('/admin/cards?date_from=&date_to=')
            ->assertOk()
            ->assertSee($card->card_no);
    }
}
