<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\Supply;
use App\Models\User;
use App\Services\CardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Cards — the digital Stock Card (SC) and Property Card (PC).
 *
 * A card never invents numbers: its lines are projected from the stock ledger
 * (inventory) and from finalized PAR/ICS/IAR document lines, balances are
 * calculated and never accepted from the browser, and history is append-only.
 */
class CardsTest extends TestCase
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
            'description' => 'Black ballpoint pen',
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
            'item_code' => 'EQP-' . (Equipment::count() + 1),
            'property_number' => 'PPE-2026-0001',
            'name' => 'Laptop Computer',
            'description' => 'Dell Latitude 5420 · 16GB RAM · Serial 12345',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 45000,
            'status' => 'available',
        ]);
    }

    /** Record a stock movement the way the Add Stock / RIS flows do. */
    private function movement(Supply $supply, int $quantity, int $balanceAfter, array $attributes = []): Inventory
    {
        // created_at is not mass assignable (it is the ledger's own timestamp).
        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $movement = Inventory::create($attributes + [
            'supply_id' => $supply->id,
            'type' => $quantity > 0 ? 'in' : 'out',
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'user_id' => $this->admin->id,
        ]);

        if ($createdAt) {
            $movement->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        }

        return $movement;
    }

    private function card(string $type, Supply|Equipment $item): Card
    {
        return app(CardService::class)->create($type, $item, ['fund_cluster' => '01']);
    }

    /* ------------------------------------------------------------------ */
    /* Register                                                            */
    /* ------------------------------------------------------------------ */

    public function test_the_cards_register_is_reachable_from_the_sidebar(): void
    {
        $this->assertTrue(Route::has('admin.cards.index'));
        $this->assertTrue(Route::has('admin.cards.stock'));
        $this->assertTrue(Route::has('admin.cards.property'));

        $this->actingAs($this->admin)->get('/admin/cards')->assertOk();
        $this->actingAs($this->admin)->get('/admin/cards/stock')->assertOk()->assertSee('Stock Cards');
        $this->actingAs($this->admin)->get('/admin/cards/property')->assertOk()->assertSee('Property Cards');

        // The sidebar lists the cards register (under the Documents group).
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()
            ->assertSee('href="' . route('admin.cards.index') . '"', false);
    }

    public function test_the_supply_staff_work_the_cards_register_too(): void
    {
        // The stock and property ledger is the supply office's own register,
        // so a supply staff account reaches it and can post to it the way the
        // administrator does. Only the archive the cards are filed into stays
        // with the administrator.
        $staffRole = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Inspection Staff']);
        $staff = User::factory()->create(['role_id' => $staffRole->id, 'is_active' => true]);

        $supply = $this->supply();

        $this->actingAs($staff)->get('/admin/cards')->assertOk();
        $this->actingAs($staff)->get('/admin/cards/stock')->assertOk()->assertSee('Stock Cards');
        $this->actingAs($staff)->get('/admin/cards/property')->assertOk()->assertSee('Property Cards');
        $this->actingAs($staff)->get('/admin/cards/create?type=SC')->assertOk()
            ->assertSee('Ballpoint Pen (Black) (SUP-00002)');

        $this->actingAs($staff)->post('/admin/cards', [
            'type' => 'SC',
            'item' => "supply:{$supply->id}",
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'fund_cluster' => '01',
        ])->assertRedirect(route('admin.cards.show', Card::firstOrFail()));

        $card = Card::firstOrFail();

        // A line posted by hand lands on the card for them, credited to them.
        $this->actingAs($staff)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => '2026-09-15',
            'type' => 'receipt',
            'quantity' => 5,
            'remarks' => 'Delivered today',
        ])->assertRedirect(route('admin.cards.show', $card));

        $line = $card->transactions()->sole();
        $this->assertSame(5, $line->receipt_qty);
        $this->assertSame($staff->id, $line->created_by);

        $this->actingAs($staff)->get("/admin/cards/{$card->id}")->assertOk();
        $this->actingAs($staff)->get("/admin/cards/{$card->id}/print")->assertOk();
    }

    public function test_the_add_card_flow_offers_both_card_types(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/cards/create')
            ->assertOk()
            ->assertSee('Create New Card')
            ->assertSee('Stock Card')
            ->assertSee('Property Card')
            ->assertSee('For supplies')
            ->assertSee('For PPE / assets');
    }

    public function test_add_card_opens_the_chooser_in_a_modal_on_the_cards_page(): void
    {
        $this->supply();
        $this->equipment();

        $response = $this->actingAs($this->admin)->get('/admin/cards')->assertOk();

        // The chooser is a modal on the page, not a jump to another screen.
        $response->assertSee('id="new-card-modal"', false)
            ->assertSee('ISUNewCard.open()')
            ->assertSee('Create New Card')
            ->assertSee('What type of card do you want to create?')
            ->assertSee('For supplies')
            ->assertSee('For PPE / assets')
            // Both steps are rendered up front and swapped in the modal.
            ->assertSee('data-new-card-step="choose"', false)
            ->assertSee('data-new-card-step="SC"', false)
            ->assertSee('data-new-card-step="PC"', false)
            ->assertSee('New Stock Card (SC)')
            ->assertSee('New Property Card (PC)')
            ->assertDontSee('href="' . route('admin.cards.create') . '"', false);
    }

    public function test_the_modal_carries_both_forms_with_their_items(): void
    {
        $this->supply();
        $this->equipment();

        $response = $this->actingAs($this->admin)->get('/admin/cards')->assertOk();

        // One form per card type, each offering the items that have no card yet.
        $this->assertSame(2, substr_count($response->getContent(), 'data-card-form data-card-type'));

        $response->assertSee('id="item-SC"', false)
            ->assertSee('id="item-PC"', false)
            ->assertSee('Ballpoint Pen (Black) (SUP-00002)')
            ->assertSee('Dell Latitude 5420')
            // The stock card form keeps its re-order point field; the property
            // card form does not have one.
            ->assertSee('id="reorder_point-SC"', false)
            ->assertDontSee('id="reorder_point-PC"', false);
    }

    public function test_a_stock_card_can_be_created_for_an_item_whose_stock_predates_the_ledger(): void
    {
        // The seeded reality: 8 on hand that no receipt records, then an issue of
        // 4, then two deliveries. Summing the movements from zero would start this
        // card at -4, which is what broke card creation for such an item.
        $supply = $this->supply(['quantity_on_hand' => 44]);

        $this->movement($supply, -4, 4);
        $this->movement($supply, 20, 24);
        $this->movement($supply, 20, 44);

        $this->actingAs($this->admin)->post('/admin/cards', [
            'type' => 'SC',
            'item' => "supply:{$supply->id}",
            'entity_name' => 'Isabela State University - Cauayan Campus',
        ])->assertRedirect();

        $card = Card::firstOrFail();
        $lines = $card->transactions()->reorder()->get();

        // Every line keeps the ledger's own balance: -4 never appears.
        $this->assertSame([4, 24, 44], $lines->pluck('balance_qty')->map(fn ($b) => (int) $b)->all());
        $this->assertSame([-4, 20, 20], $lines->map(fn ($l) => (int) $l->receipt_qty - (int) $l->issue_qty)->all());
        $this->assertSame(['issue', 'receipt', 'receipt'], $lines->pluck('type')->all());

        // Closing balance is the stock on hand, so nothing looks out of step.
        $this->assertSame(44, $card->balance());
        $this->assertNull(app(CardService::class)->reconciliationGap($card));

        $this->actingAs($this->admin)->get("/admin/cards/{$card->id}")->assertOk()->assertSee('SC-00001');
        // The printed form carries the same balances, and no negative. It ends at
        // the last ruled row: no closing-balance line under the table.
        $this->actingAs($this->admin)->get("/admin/cards/{$card->id}/print")
            ->assertOk()
            ->assertSee('44')
            ->assertDontSee('-4')
            ->assertDontSee('Closing balance')
            ->assertDontSee('Receipts add to the balance');
    }

    public function test_a_posting_that_would_go_negative_is_refused_with_a_message(): void
    {
        $equipment = $this->equipment();
        $card = $this->card('PC', $equipment);

        // No receipt has been posted, so nothing can be disposed of yet.
        $this->actingAs($this->admin)
            ->from("/admin/cards/{$card->id}")
            ->post("/admin/cards/{$card->id}/transactions", [
                'transaction_date' => now()->toDateString(),
                'type' => 'disposal',
                'quantity' => 3,
                'remarks' => 'Disposed due to damage',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, $card->transactions()->count());
    }

    public function test_a_rejected_submission_reopens_the_modal_on_the_submitted_step(): void
    {
        $this->supply();
        $this->equipment();

        // Following the redirect lands back on the Cards page with the modal
        // already open on the stock card step, message and values intact.
        $this->actingAs($this->admin)
            ->from(route('admin.cards.index'))
            ->followingRedirects()
            ->post('/admin/cards', [
                'type' => 'SC',
                'item' => 'supply:999999',
                'entity_name' => 'Isabela State University - Cauayan Campus',
            ])
            ->assertOk()
            ->assertSee('data-open-step="SC"', false)
            ->assertSee('Select a valid supply or equipment item.')
            ->assertSee('ISUNewCard.choose(step)');
    }

    public function test_creating_a_card_without_leaving_the_cards_page(): void
    {
        $supply = $this->supply();

        // Exactly what the modal submits: it posts to the same endpoint and lands
        // on the new card.
        $this->actingAs($this->admin)->post('/admin/cards', [
            'type' => 'SC',
            'item' => "supply:{$supply->id}",
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'fund_cluster' => '01',
            'reorder_point' => 60,
        ])->assertRedirect(route('admin.cards.show', Card::firstOrFail()));

        $card = Card::firstOrFail();
        $this->assertSame('SC-00001', $card->card_no);
        $this->assertSame(60, $card->reorder_point);

        // And the item is gone from the modal's picker afterwards.
        $this->actingAs($this->admin)->get('/admin/cards')
            ->assertOk()
            ->assertSee('No supply can start a new card');
    }

    public function test_a_stock_card_is_populated_from_its_supply(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)
            ->get('/admin/cards/create?type=SC')
            ->assertOk()
            // Offered items come from the Supplies table, not typed by hand.
            ->assertSee('Ballpoint Pen (Black) (SUP-00002)')
            ->assertSee('data-code="SUP-00002"', false);

        $this->actingAs($this->admin)->post('/admin/cards', [
            'type' => 'SC',
            'item' => "supply:{$supply->id}",
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'fund_cluster' => '01',
        ])->assertRedirect();

        $card = Card::firstOrFail();
        $this->assertSame('SC', $card->type);
        $this->assertSame('SC-00001', $card->card_no);
        $this->assertSame($supply->id, $card->supply_id);
        // The card prints the supply's own figures; nothing was re-typed.
        $this->assertSame('Ballpoint Pen (Black)', $card->itemName());
        $this->assertSame('SUP-00002', $card->itemCode());
        $this->assertSame('piece', $card->unit());
        $this->assertSame('Black ballpoint pen', $card->description());
        $this->assertSame(50, $card->reorder_point);

        $this->actingAs($this->admin)->get("/admin/cards/{$card->id}")->assertOk()->assertSee('Stock Card');
    }

    public function test_card_numbers_are_sequential_per_type(): void
    {
        $first = $this->card('SC', $this->supply());
        $second = $this->card('SC', $this->supply(['item_code' => 'SUP-00009', 'name' => 'Folder (Long, Brown)']));
        $property = $this->card('PC', $this->equipment());

        $this->assertSame(['SC-00001', 'SC-00002'], [$first->card_no, $second->card_no]);
        $this->assertSame('PC-00001', $property->card_no);
    }

    public function test_one_card_per_item_is_enforced(): void
    {
        $supply = $this->supply();
        $this->card('SC', $supply);

        $this->actingAs($this->admin)->post('/admin/cards', [
            'type' => 'SC',
            'item' => "supply:{$supply->id}",
            'entity_name' => 'Isabela State University - Cauayan Campus',
        ])->assertSessionHasErrors('type');

        $this->assertSame(1, Card::count());
        // The item is not offered twice either.
        $this->actingAs($this->admin)->get('/admin/cards/create?type=SC')
            ->assertOk()
            ->assertSee('No supply can start a new card');
    }

    public function test_a_stock_card_cannot_be_created_for_equipment(): void
    {
        $equipment = $this->equipment();

        $this->actingAs($this->admin)->post('/admin/cards', [
            'type' => 'SC',
            'item' => "equipment:{$equipment->id}",
            'entity_name' => 'Isabela State University - Cauayan Campus',
        ])->assertSessionHasErrors('item');

        $this->assertSame(0, Card::count());
    }

    public function test_an_invalid_item_reference_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/cards', [
            'type' => 'SC',
            'item' => 'supply:999999',
            'entity_name' => 'Isabela State University - Cauayan Campus',
        ])->assertSessionHasErrors('item');

        $this->assertSame(0, Card::count());
    }

    /* ------------------------------------------------------------------ */
    /* Projection from the stock ledger                                    */
    /* ------------------------------------------------------------------ */

    public function test_creating_a_stock_card_posts_the_existing_stock_history(): void
    {
        $supply = $this->supply();
        $this->movement($supply, 300, 300, ['remarks' => 'Initial stock', 'unit_cost' => 7.50, 'total_cost' => 2250, 'created_at' => now()->subDays(3)]);
        $this->movement($supply, 100, 400, ['remarks' => 'New delivery', 'unit_cost' => 8.00, 'total_cost' => 800, 'created_at' => now()->subDay()]);
        $this->movement($supply, -20, 380, ['remarks' => 'Issued via RIS RIS-2026-00001.', 'created_at' => now()]);

        $card = $this->card('SC', $supply);

        $lines = $card->transactions()->get();
        $this->assertCount(3, $lines);

        $this->assertSame(300, $lines[0]->receipt_qty);
        $this->assertSame('Initial stock', $lines[0]->remarks);
        $this->assertSame(300, $lines[0]->balance_qty);

        $this->assertSame(100, $lines[1]->receipt_qty);
        $this->assertSame('₱800.00', '₱' . number_format((float) $lines[1]->amount, 2));
        $this->assertSame(400, $lines[1]->balance_qty);

        // A stock-out is an issue, with the balance the ledger recorded.
        $this->assertSame(20, $lines[2]->issue_qty);
        $this->assertSame(0, $lines[2]->receipt_qty);
        $this->assertSame(380, $lines[2]->balance_qty);

        $this->assertSame(380, $card->balance());

        // No item was duplicated in the process.
        $this->assertSame(1, Supply::count());
    }

    public function test_issuing_stock_through_a_ris_posts_an_issue_with_its_office(): void
    {
        $supply = $this->supply();
        $this->movement($supply, 300, 300);

        $card = $this->card('SC', $supply);
        $this->assertCount(1, $card->transactions);

        // A RIS release, as SupplyRequestController writes it.
        $this->movement($supply, -20, 280, [
            'remarks' => 'Issued via RIS RIS-2026-00001.',
            'reference_type' => \App\Models\SupplyRequest::class,
            'reference_id' => null,
            'created_at' => now(),
        ]);

        app(CardService::class)->sync($card);

        $issue = $card->transactions()->reorder()->orderByDesc('id')->first();
        $this->assertSame(20, $issue->issue_qty);
        $this->assertSame(280, $issue->balance_qty);
        $this->assertStringContainsString('RIS', $issue->remarks);
    }

    public function test_syncing_twice_never_double_posts(): void
    {
        $supply = $this->supply();
        $this->movement($supply, 300, 300);

        $card = $this->card('SC', $supply);
        $service = app(CardService::class);

        $this->assertSame(0, $service->sync($card));
        $this->assertSame(0, $service->sync($card));

        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/sync")->assertRedirect();
        $this->assertSame(1, CardTransaction::where('card_id', $card->id)->count());
    }

    public function test_add_stock_posts_a_receipt_to_the_card_automatically(): void
    {
        $supply = $this->supply();
        $this->movement($supply, 300, 300);

        $card = $this->card('SC', $supply);

        $this->actingAs($this->admin)->post("/admin/supplies/{$supply->id}/stock", [
            'quantity' => 100,
            'unit_cost' => 8.00,
            'remarks' => 'New delivery',
        ])->assertRedirect();

        $this->assertSame(400, $supply->fresh()->quantity_on_hand);

        $receipt = $card->transactions()->reorder()->orderByDesc('id')->first();
        $this->assertSame(100, $receipt->receipt_qty);
        $this->assertSame(400, $receipt->balance_qty);
        $this->assertSame('New delivery', $receipt->remarks);
        $this->assertSame(400, $card->balance());
    }

    /* ------------------------------------------------------------------ */
    /* Posting by hand                                                     */
    /* ------------------------------------------------------------------ */

    public function test_an_issue_posted_on_a_stock_card_moves_the_stock_and_the_ledger(): void
    {
        $supply = $this->supply(['quantity_on_hand' => 20]);
        $this->movement($supply, 20, 20);
        $card = $this->card('SC', $supply);

        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => now()->toDateString(),
            'type' => 'issue',
            'reference' => 'RIS-2026-00007',
            'quantity' => 5,
            'office' => 'College of Information Technology',
            'remarks' => 'Issued to CICT',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $line = $card->transactions()->reorder()->orderByDesc('id')->first();
        $this->assertSame(5, $line->issue_qty);
        $this->assertSame(0, $line->receipt_qty);
        $this->assertSame(15, $line->balance_qty);
        $this->assertSame('College of Information Technology', $line->office);
        $this->assertSame($this->admin->id, $line->created_by);

        // The stock on hand moved with it, and the movement is in the ledger.
        $this->assertSame(15, $supply->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory', [
            'supply_id' => $supply->id,
            'type' => 'out',
            'quantity' => -5,
            'balance_after' => 15,
        ]);
    }

    public function test_an_issue_larger_than_the_balance_is_refused(): void
    {
        $supply = $this->supply(['quantity_on_hand' => 10]);
        $this->movement($supply, 10, 10);
        $card = $this->card('SC', $supply);

        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => now()->toDateString(),
            'type' => 'issue',
            'quantity' => 15,
        ])->assertSessionHasErrors('quantity');

        $this->assertStringContainsString(
            'Insufficient stock. Available balance: 10 piece. Requested issue: 15.',
            session('errors')->first('quantity'),
        );

        // Nothing was written anywhere.
        $this->assertSame(1, CardTransaction::where('card_id', $card->id)->count());
        $this->assertSame(10, $supply->fresh()->quantity_on_hand);
        $this->assertSame(1, Inventory::where('supply_id', $supply->id)->count());
    }

    public function test_the_client_cannot_dictate_the_balance(): void
    {
        $supply = $this->supply(['quantity_on_hand' => 30]);
        $this->movement($supply, 30, 30);
        $card = $this->card('SC', $supply);

        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => now()->toDateString(),
            'type' => 'receipt',
            'quantity' => 10,
            'amount' => 80,
            // Attempted tampering: the posted balance is calculated, not read.
            'balance_qty' => 9999,
        ])->assertSessionHasNoErrors();

        $line = $card->transactions()->reorder()->orderByDesc('id')->first();
        $this->assertSame(40, $line->balance_qty);
        $this->assertSame(40, $supply->fresh()->quantity_on_hand);
    }

    public function test_a_stock_card_refuses_transfer_and_disposal_types(): void
    {
        $supply = $this->supply();
        $this->movement($supply, 300, 300);
        $card = $this->card('SC', $supply);

        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => now()->toDateString(),
            'type' => 'disposal',
            'quantity' => 1,
        ])->assertSessionHasErrors('type');
    }

    public function test_quantity_must_be_a_positive_whole_number(): void
    {
        $supply = $this->supply();
        $this->movement($supply, 300, 300);
        $card = $this->card('SC', $supply);

        foreach (['0', '-5', 'abc'] as $quantity) {
            $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
                'transaction_date' => now()->toDateString(),
                'type' => 'receipt',
                'quantity' => $quantity,
            ])->assertSessionHasErrors('quantity');
        }

        $this->assertSame(1, CardTransaction::where('card_id', $card->id)->count());
    }

    /* ------------------------------------------------------------------ */
    /* Property cards                                                      */
    /* ------------------------------------------------------------------ */

    public function test_a_property_card_posts_receipts_from_finalized_documents(): void
    {
        $equipment = $this->equipment();

        $document = Document::create([
            'document_no' => 'PAR-2026-00001',
            'type' => 'PAR',
            'status' => 'approved',
            'document_date' => now()->subDays(2)->toDateString(),
            'fund_cluster' => '01',
            'prepared_by' => $this->admin->id,
        ]);

        $document->items()->create([
            'equipment_id' => $equipment->id,
            'description' => $equipment->name,
            'unit' => 'unit',
            'quantity' => 1,
            'unit_cost' => 45000,
            'amount' => 45000,
        ]);

        $card = $this->card('PC', $equipment);

        $line = $card->transactions()->firstOrFail();
        $this->assertSame('receipt', $line->type);
        $this->assertSame('PAR-2026-00001', $line->reference);
        $this->assertSame(1, $line->receipt_qty);
        $this->assertSame(1, $line->balance_qty);
        $this->assertSame('₱45,000.00', '₱' . number_format((float) $line->amount, 2));
        $this->assertSame('PPE-2026-0001', $card->itemCode());
    }

    public function test_a_draft_document_does_not_post_until_it_is_approved(): void
    {
        $equipment = $this->equipment();

        $document = Document::create([
            'document_no' => 'ICS-2026-00001',
            'type' => 'ICS',
            'status' => 'draft',
            'document_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
        ]);

        $document->items()->create([
            'equipment_id' => $equipment->id,
            'description' => $equipment->name,
            'unit' => 'unit',
            'quantity' => 1,
            'unit_cost' => 45000,
            'amount' => 45000,
        ]);

        $card = $this->card('PC', $equipment);
        $this->assertSame(0, $card->transactions()->count());
        $this->assertSame(0, $card->balance());

        // Approving the document is what posts it.
        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/approve")->assertRedirect();

        $this->assertSame(1, $card->transactions()->count());
        $this->assertSame(1, $card->balance());
        $this->assertSame('ICS-2026-00001', $card->transactions()->first()->reference);
    }

    public function test_a_property_card_records_a_transfer(): void
    {
        $equipment = $this->equipment();
        $card = $this->card('PC', $equipment);

        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => now()->toDateString(),
            'type' => 'receipt',
            'reference' => 'PAR-2026-00001',
            'quantity' => 1,
            'amount' => 45000,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => now()->toDateString(),
            'type' => 'transfer',
            'quantity' => 1,
            'office' => 'College of Information Technology',
            'remarks' => 'Transferred to CICT',
        ])->assertSessionHasNoErrors();

        // Property is not consumed by being transferred, so the balance holds.
        $this->assertSame(1, $card->balance());

        $transfer = $card->transactions()->reorder()->orderByDesc('id')->first();
        $this->assertSame('transfer', $transfer->type);
        $this->assertSame(1, $transfer->balance_qty);
        $this->assertSame('Transferred to CICT', $transfer->remarks);
        $this->assertSame('College of Information Technology', $transfer->office);
    }

    public function test_issue_transfer_and_disposal_do_not_deduct_a_property_card_balance(): void
    {
        $equipment = $this->equipment();
        $card = $this->card('PC', $equipment);

        $post = fn (array $payload) => $this->actingAs($this->admin)
            ->post("/admin/cards/{$card->id}/transactions", $payload + [
                'transaction_date' => now()->toDateString(),
                'quantity' => 1,
            ]);

        $post(['type' => 'receipt', 'reference' => 'PAR-2026-00001', 'amount' => 45000])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $card->balance());

        // Each of these moves custody of the same one unit; none of them
        // consumes it, so the balance is unchanged on every line.
        foreach (['issue', 'transfer', 'disposal'] as $type) {
            $post(['type' => $type, 'office' => 'College of Information Technology', 'remarks' => ucfirst($type) . ' of the unit'])
                ->assertSessionHasNoErrors();

            $line = $card->transactions()->reorder()->orderByDesc('id')->first();
            $this->assertSame($type, $line->type);
            $this->assertSame(1, $line->issue_qty);
            $this->assertSame(1, $line->balance_qty, "A {$type} must not deduct the balance.");
            $this->assertSame(1, $card->balance());
        }

        // The printed sheet shows the same non-deducting balances.
        $this->actingAs($this->admin)
            ->get("/admin/cards/{$card->id}/print")
            ->assertOk()
            ->assertSee('Disposal of the unit');
    }

    public function test_a_ris_line_against_equipment_never_posts_to_a_property_card(): void
    {
        $equipment = $this->equipment();

        // Seeded data can carry a supply release that points at an equipment
        // record; a RIS is not a PPE document and must not be posted.
        $ris = Document::create([
            'document_no' => 'RIS-2026-00001',
            'type' => 'RIS',
            'status' => 'approved',
            'document_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
        ]);

        $ris->items()->create([
            'equipment_id' => $equipment->id,
            'description' => $equipment->name,
            'unit' => 'unit',
            'quantity' => 1,
            'unit_cost' => 45000,
            'amount' => 45000,
        ]);

        $card = $this->card('PC', $equipment);

        $this->assertSame(0, $card->transactions()->count());
        $this->assertSame(0, $card->balance());
    }

    public function test_a_property_card_cannot_go_negative(): void
    {
        $equipment = $this->equipment();
        $card = $this->card('PC', $equipment);

        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => now()->toDateString(),
            'type' => 'disposal',
            'quantity' => 1,
            'remarks' => 'Disposed due to damage',
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, $card->transactions()->count());
    }

    /* ------------------------------------------------------------------ */
    /* Header editing, days to consume, print                              */
    /* ------------------------------------------------------------------ */

    public function test_only_the_card_header_can_be_edited(): void
    {
        $supply = $this->supply();
        $this->movement($supply, 300, 300);
        $card = $this->card('SC', $supply);

        $this->actingAs($this->admin)->put("/admin/cards/{$card->id}", [
            'entity_name' => 'ISU Cauayan — Supply Office',
            'fund_cluster' => '07',
            'reorder_point' => 60,
            // No ledger fields exist in this form; these are ignored anyway.
            'balance_qty' => 9999,
        ])->assertRedirect();

        $card->refresh();
        $this->assertSame('ISU Cauayan — Supply Office', $card->entity_name);
        $this->assertSame('07', $card->fund_cluster);
        $this->assertSame(60, $card->reorder_point);
        $this->assertSame(300, $card->balance());
        $this->assertSame(300, $supply->fresh()->quantity_on_hand);
    }

    public function test_days_to_consume_is_estimated_from_issue_history_or_reported_as_not_available(): void
    {
        $supply = $this->supply(['quantity_on_hand' => 100]);
        $this->movement($supply, 100, 100, ['created_at' => now()->subDays(20)]);
        $card = $this->card('SC', $supply);

        $service = app(CardService::class);

        // One movement, no consumption pattern yet: nothing to estimate from.
        $this->assertSame([], $service->daysToConsume($card));

        $post = fn (int $quantity, string $date) => $this->actingAs($this->admin)
            ->post("/admin/cards/{$card->id}/transactions", [
                'transaction_date' => $date,
                'type' => 'issue',
                'quantity' => $quantity,
            ])->assertSessionHasNoErrors();

        $post(20, now()->subDays(10)->toDateString());
        $post(20, now()->subDays(4)->toDateString());

        $estimates = $service->daysToConsume($card->fresh());
        $lines = $card->transactions()->reorder()->orderBy('id')->get();

        // The first issue cannot be rated yet; the second one can.
        $this->assertNull($estimates[$lines[1]->id]);
        // 40 issued across the 6 days between the two issues = 6.67/day,
        // against a remaining balance of 60.
        $this->assertSame(9, $estimates[$lines[2]->id]);
    }

    public function test_a_stock_card_posting_cannot_be_back_dated_into_the_ledger(): void
    {
        $supply = $this->supply(['quantity_on_hand' => 100]);
        $this->movement($supply, 100, 100, ['created_at' => now()->subDays(5)]);
        $card = $this->card('SC', $supply);

        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => now()->subDays(30)->toDateString(),
            'type' => 'receipt',
            'quantity' => 50,
        ])->assertSessionHasErrors('transaction_date');

        $this->assertSame(1, CardTransaction::where('card_id', $card->id)->count());
        $this->assertSame(100, $supply->fresh()->quantity_on_hand);
    }

    public function test_a_property_card_re_balances_lines_when_one_is_dated_into_the_middle(): void
    {
        $equipment = $this->equipment();
        $card = $this->card('PC', $equipment);

        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => now()->subDays(10)->toDateString(),
            'type' => 'receipt',
            'quantity' => 1,
            'reference' => 'PAR-2026-00001',
        ])->assertSessionHasNoErrors();

        // Dated before the later receipt, so the balance after it is 1, not 2.
        $this->actingAs($this->admin)->post("/admin/cards/{$card->id}/transactions", [
            'transaction_date' => now()->subDays(20)->toDateString(),
            'type' => 'receipt',
            'quantity' => 1,
            'reference' => 'PAR-2026-00000',
        ])->assertSessionHasNoErrors();

        $balances = $card->transactions()->reorder()->orderBy('transaction_date')->get()->pluck('balance_qty')->all();
        $this->assertSame([1, 2], $balances);
        $this->assertSame(2, $card->balance());
    }

    public function test_the_printed_card_reproduces_the_official_form(): void
    {
        $supply = $this->supply();
        $this->movement($supply, 300, 300);
        $card = $this->card('SC', $supply);

        $this->actingAs($this->admin)
            ->get("/admin/cards/{$card->id}/print")
            ->assertOk()
            ->assertSee('Revised March 2016')
            ->assertSee('Appendix 54')
            // The form prints upper case through CSS; the markup keeps title case.
            ->assertSee('Stock Card')
            ->assertSee('Entity Name:')
            ->assertSee('Fund Cluster:')
            ->assertSee('Unit of Measurement:')
            ->assertSee('Re-order Point:')
            ->assertSee('No. of Days to Consume')
            ->assertSee('N/A')
            ->assertSee('SUP-00002')
            ->assertSee('Print Card');

        $card->delete();

        $equipment = $this->equipment();
        $property = $this->card('PC', $equipment);

        $this->actingAs($this->admin)
            ->get("/admin/cards/{$property->id}/print")
            ->assertOk()
            ->assertSee('Appendix 69')
            ->assertSee('Property Card')
            ->assertSee('Property, Plant and Equipment:')
            ->assertSee('Property Number:')
            ->assertSee('Issue/Transfer/ Disposal')
            ->assertSee('Office/Officer')
            ->assertSee('Remarks');
    }

    public function test_a_card_can_be_downloaded_as_a_pdf(): void
    {
        $supply = $this->supply();
        $this->movement($supply, 300, 300);
        $card = $this->card('SC', $supply);

        $response = $this->actingAs($this->admin)->get("/admin/cards/{$card->id}/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('SC-00001.pdf', $response->headers->get('content-disposition'));
    }

    public function test_cards_are_not_reachable_by_guests(): void
    {
        $supply = $this->supply();
        $card = $this->card('SC', $supply);

        foreach ([
            '/admin/cards',
            '/admin/cards/create',
            "/admin/cards/{$card->id}",
            "/admin/cards/{$card->id}/print",
            "/admin/cards/{$card->id}/pdf",
        ] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }
}
