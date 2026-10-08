<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issuing an ICS or RIS hands supplies over, so every line takes its quantity
 * off the shelf and lands in the item's Stock Movement History. A PAR records
 * property, which is not consumed, so it deducts nothing.
 */
class DocumentStockReleaseTest extends TestCase
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
            'item_code' => 'SUP-00001',
            'name' => 'Bond Paper A4',
            'category' => 'Office Supplies',
            'unit' => 'ream',
            'quantity_on_hand' => 100,
            'unit_cost' => 220,
            'reorder_level' => 0,
            'is_active' => true,
        ]);
    }

    /** Create a document of the given type and leave it approved. */
    private function approvedDocument(string $type, array $items): Document
    {
        $this->actingAs($this->admin)->post('/admin/documents', [
            'type' => $type,
            'document_date' => '2026-09-22',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'items' => $items,
        ])->assertRedirect();

        $document = Document::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/approve")->assertRedirect();

        return $document->refresh();
    }

    public function test_issuing_a_ris_takes_each_lines_quantity_off_the_shelf(): void
    {
        $supply = $this->supply();
        $document = $this->approvedDocument('RIS', [
            ['item' => 'supply:' . $supply->id, 'quantity' => 20],
        ]);

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue")->assertRedirect();

        $this->assertSame(80, $supply->refresh()->quantity_on_hand);

        $movement = Inventory::where('supply_id', $supply->id)->latest('id')->firstOrFail();

        $this->assertSame('out', $movement->type);
        $this->assertSame(-20, (int) $movement->quantity);
        $this->assertSame(80, (int) $movement->balance_after);
        // The document it came in on is what the history and the card show.
        $this->assertSame($document->document_no, $movement->reference);
        $this->assertSame($document->id, (int) $movement->reference_id);
        $this->assertSame(auth()->id() ?? $this->admin->id, (int) $movement->user_id);

        // The supply's own page reports it as a Stock Out.
        $this->actingAs($this->admin)->get("/admin/supplies/{$supply->id}")
            ->assertOk()
            ->assertSee('Stock Out')
            ->assertSee('-20')
            ->assertSee($document->document_no);
    }

    public function test_issuing_an_ics_takes_the_quantity_off_the_shelf_too(): void
    {
        $supply = $this->supply(['quantity_on_hand' => 50]);
        $document = $this->approvedDocument('ICS', [
            ['item' => 'supply:' . $supply->id, 'quantity' => 5],
        ]);

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");

        $this->assertSame(45, $supply->refresh()->quantity_on_hand);
        $this->assertSame(-5, (int) Inventory::where('supply_id', $supply->id)->latest('id')->value('quantity'));
    }

    public function test_a_ris_releases_its_issue_quantity_when_one_is_recorded(): void
    {
        $supply = $this->supply();
        $document = $this->approvedDocument('RIS', [
            // Ten requested, five actually handed over.
            ['item' => 'supply:' . $supply->id, 'quantity' => 10, 'issued_quantity' => 5],
        ]);

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");

        $this->assertSame(95, $supply->refresh()->quantity_on_hand);
    }

    public function test_issuing_a_par_consumes_nothing(): void
    {
        $equipment = Equipment::create([
            'item_code' => 'EQP-00001',
            'property_number' => 'PPE-2026-0001',
            'name' => 'Laptop Computer',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 2,
            'unit_cost' => 45000,
            'reorder_level' => 0,
            'status' => 'available',
        ]);

        $document = $this->approvedDocument('PAR', [
            ['item' => 'equipment:' . $equipment->id, 'quantity' => 1],
        ]);

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");

        // Property is issued, not consumed: the count on hand is untouched, and
        // no stock movement is invented for it.
        $this->assertSame(2, $equipment->refresh()->quantity_on_hand);
        $this->assertSame(0, Inventory::count());
    }

    public function test_an_issue_larger_than_the_balance_is_refused_whole(): void
    {
        $supply = $this->supply(['quantity_on_hand' => 8]);
        $document = $this->approvedDocument('RIS', [
            ['item' => 'supply:' . $supply->id, 'quantity' => 20],
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/documents/{$document->id}/issue")
            ->assertSessionHas('error');

        // Nothing was released and nothing was half-done.
        $this->assertSame(8, $supply->refresh()->quantity_on_hand);
        $this->assertSame('approved', $document->refresh()->status);
        $this->assertNull($document->issued_at);
        $this->assertSame(0, Inventory::count());

        $this->assertStringContainsString(
            'Insufficient stock for Bond Paper A4. Available balance: 8 ream. Requested issue: 20.',
            session('error'),
        );

        // The shelf can still be released from after correcting the document.
        $document->items()->update(['quantity' => 5]);
        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");

        $this->assertSame(3, $supply->refresh()->quantity_on_hand);
        $this->assertSame('issued', $document->refresh()->status);
    }

    public function test_the_release_is_posted_to_the_items_stock_card(): void
    {
        $supply = $this->supply();
        Card::create([
            'type' => 'SC',
            'card_no' => 'SC-00001',
            'supply_id' => $supply->id,
            'entity_name' => Card::DEFAULT_ENTITY,
            'created_by' => $this->admin->id,
        ]);

        $document = $this->approvedDocument('RIS', [
            ['item' => 'supply:' . $supply->id, 'quantity' => 30],
        ]);

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");

        $card = Card::firstOrFail();
        $line = $card->transactions()->reorder()->orderByDesc('id')->firstOrFail();

        $this->assertSame('issue', $line->type);
        $this->assertSame(30, (int) $line->issue_qty);
        $this->assertSame(70, (int) $line->balance_qty);
        $this->assertSame($document->document_no, $line->reference);
        $this->assertSame(70, $card->balance());
    }

    public function test_issuing_twice_releases_only_once(): void
    {
        $supply = $this->supply();
        $document = $this->approvedDocument('RIS', [
            ['item' => 'supply:' . $supply->id, 'quantity' => 25],
        ]);

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");
        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");

        $this->assertSame(75, $supply->refresh()->quantity_on_hand);
        $this->assertSame(1, Inventory::count());
    }
}
