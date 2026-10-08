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
 * A document line may be a supply or a piece of equipment. What the picker
 * offers depends on the form: a PAR records property, an ICS/RIS records
 * supplies. The controller links (and prices) whichever one was chosen.
 */
class DocumentItemPickerTest extends TestCase
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
            'item_code' => 'SUP-' . str_pad((string) (Supply::count() + 1), 5, '0', STR_PAD_LEFT),
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
            'item_code' => 'EQP-' . str_pad((string) (Equipment::count() + 1), 5, '0', STR_PAD_LEFT),
            'name' => 'Epson LCD Projector',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 28000,
            'reorder_level' => 0,
            'status' => 'available',
        ]);
    }

    private function payload(array $items, string $type = 'PAR'): array
    {
        return [
            'type' => $type,
            'document_date' => '2026-09-22',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'items' => $items,
        ];
    }

    public function test_the_item_picker_offers_only_the_catalogue_each_document_records(): void
    {
        $supply = $this->supply();
        $equipment = $this->equipment();

        // A PAR is a property receipt, so it lists equipment and nothing else.
        $this->actingAs($this->admin)
            ->get('/admin/documents/create?type=PAR')
            ->assertOk()
            ->assertSee('Select equipment')
            ->assertSee('Epson LCD Projector (EQP-00001)')
            ->assertSee('value="equipment:' . $equipment->id . '"', false)
            ->assertSee('<optgroup label="Equipment">', false)
            ->assertDontSee('<optgroup label="Supplies">', false)
            ->assertDontSee('value="supply:' . $supply->id . '"', false)
            ->assertDontSee('Bond Paper A4 (Ream) (SUP-00001)');

        // An ICS/RIS records supplies being issued, so it lists supplies only.
        foreach (['ICS', 'RIS'] as $type) {
            $this->actingAs($this->admin)
                ->get("/admin/documents/create?type={$type}")
                ->assertOk()
                ->assertSee('Select supply')
                ->assertSee('Bond Paper A4 (Ream) (SUP-00001)')
                ->assertSee('value="supply:' . $supply->id . '"', false)
                ->assertSee('<optgroup label="Supplies">', false)
                ->assertDontSee('<optgroup label="Equipment">', false)
                ->assertDontSee('value="equipment:' . $equipment->id . '"', false)
                ->assertDontSee('Epson LCD Projector (EQP-00001)');
        }

        // The IAR is generated as page two of another document rather than
        // filled in on its own, so it keeps both catalogues.
        $this->actingAs($this->admin)
            ->get('/admin/documents/create?type=IAR')
            ->assertOk()
            ->assertSee('Select supply or equipment')
            ->assertSee('<optgroup label="Supplies">', false)
            ->assertSee('<optgroup label="Equipment">', false);
    }

    public function test_the_picker_is_marked_required_and_keeps_its_placeholder(): void
    {
        $this->supply();
        $this->equipment();

        $this->actingAs($this->admin)->get('/admin/documents/create?type=PAR')
            ->assertOk()
            ->assertSee('name="items[0][item]" required', false);
    }

    public function test_a_document_line_can_be_a_piece_of_equipment(): void
    {
        $equipment = $this->equipment(['name' => 'Epson LCD Projector', 'unit' => 'unit', 'unit_cost' => 28000]);

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([
                ['item' => 'equipment:' . $equipment->id, 'quantity' => 2, 'date_acquired' => '2026-09-20'],
            ]))
            ->assertRedirect();

        $document = Document::latest('id')->firstOrFail();
        $item = $document->items()->firstOrFail();

        $this->assertSame('PAR', $document->type);
        $this->assertSame($equipment->id, $item->equipment_id);
        $this->assertNull($item->supply_id);
        $this->assertSame('Epson LCD Projector', $item->description);
        $this->assertSame('unit', $item->unit);
        $this->assertSame('28000.00', (string) $item->unit_cost);
        $this->assertSame('56000.00', (string) $item->amount);
        $this->assertSame('2026-09-20', $item->date_acquired->format('Y-m-d'));
    }

    public function test_a_supply_line_still_behaves_as_before(): void
    {
        $supply = $this->supply(['name' => 'Bond Paper A4 (Ream)', 'unit' => 'ream', 'unit_cost' => 220]);

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([
                ['item' => 'supply:' . $supply->id, 'quantity' => 3, 'stock_available' => 'yes'],
            ], 'RIS'))
            ->assertRedirect();

        $item = Document::latest('id')->firstOrFail()->items()->firstOrFail();

        $this->assertSame($supply->id, $item->supply_id);
        $this->assertNull($item->equipment_id);
        $this->assertSame('Bond Paper A4 (Ream)', $item->description);
        $this->assertSame('ream', $item->unit);
        $this->assertSame('220.00', (string) $item->unit_cost);
        $this->assertSame('660.00', (string) $item->amount);
        $this->assertSame('yes', $item->stock_available);
    }

    /**
     * The picker offers one catalogue per document type, but the store endpoint
     * only checks that each line points at a real item. A document recorded
     * before that rule (or through another client) therefore stays saveable
     * instead of becoming impossible to edit.
     */
    public function test_a_document_holding_both_kinds_still_saves(): void
    {
        $supply = $this->supply();
        $equipment = $this->equipment();

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([
                ['item' => 'supply:' . $supply->id, 'quantity' => 4],
                ['item' => 'equipment:' . $equipment->id, 'quantity' => 1],
            ]))
            ->assertRedirect();

        $items = Document::latest('id')->firstOrFail()->items()->orderBy('id')->get();

        $this->assertCount(2, $items);
        $this->assertSame($supply->id, $items[0]->supply_id);
        $this->assertNull($items[0]->equipment_id);
        $this->assertNull($items[1]->supply_id);
        $this->assertSame($equipment->id, $items[1]->equipment_id);
    }

    public function test_the_picker_rejects_an_item_that_does_not_exist(): void
    {
        $this->supply();
        $this->equipment();

        foreach (['equipment:999999', 'supply:999999', 'nonsense', ''] as $value) {
            $this->actingAs($this->admin)
                ->post('/admin/documents', $this->payload([
                    ['item' => $value, 'quantity' => 1],
                ]))
                ->assertSessionHasErrors('items.0.item');
        }

        $this->assertSame(0, Document::count(), 'Nothing may be saved when a line points at no item.');
    }

    public function test_the_quantity_is_still_validated(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([
                ['item' => 'supply:' . $supply->id, 'quantity' => 0],
            ]))
            ->assertSessionHasErrors('items.0.quantity');
    }

    public function test_editing_a_document_can_swap_the_item_on_a_line(): void
    {
        $first = $this->equipment(['name' => 'Epson LCD Projector']);
        $second = $this->equipment(['name' => 'HP LaserJet Printer', 'item_code' => 'EQP-00002']);

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([['item' => 'equipment:' . $first->id, 'quantity' => 2]]))
            ->assertRedirect();

        $document = Document::latest('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->put("/admin/documents/{$document->id}", $this->payload([
                ['item' => 'equipment:' . $second->id, 'quantity' => 1],
            ]))
            ->assertRedirect();

        $items = $document->refresh()->items()->get();

        $this->assertCount(1, $items);
        $this->assertSame($second->id, $items[0]->equipment_id);
        $this->assertNull($items[0]->supply_id);
        $this->assertSame('HP LaserJet Printer', $items[0]->description);
    }

    public function test_the_edit_form_preselects_an_equipment_line(): void
    {
        $equipment = $this->equipment();

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([['item' => 'equipment:' . $equipment->id, 'quantity' => 1]]))
            ->assertRedirect();

        $document = Document::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->get("/admin/documents/{$document->id}/edit")
            ->assertOk()
            ->assertSee('value="equipment:' . $equipment->id . '" selected>', false);
    }

    public function test_a_failed_submit_keeps_the_rows_the_user_entered(): void
    {
        $equipment = $this->equipment();

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([
                ['item' => 'equipment:' . $equipment->id, 'quantity' => 7],
                ['item' => 'not-a-real-item', 'quantity' => 1],
            ]))
            ->assertSessionHasErrors('items.1.item');

        // The redirect back to the form re-renders both rows with what was typed.
        $this->actingAs($this->admin)->get('/admin/documents/create?type=PAR')
            ->assertOk()
            ->assertSee('name="items[1][item]"', false)
            ->assertSee('value="equipment:' . $equipment->id . '" selected>', false)
            ->assertSee('name="items[0][quantity]" value="7"', false);
    }

    public function test_editing_with_a_blank_entity_name_keeps_the_stored_one(): void
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([['item' => 'supply:' . $supply->id, 'quantity' => 1]]))
            ->assertRedirect();

        $document = Document::latest('id')->firstOrFail();

        $payload = $this->payload([['item' => 'supply:' . $supply->id, 'quantity' => 2]]);
        $payload['entity_name'] = '';

        $this->actingAs($this->admin)
            ->put("/admin/documents/{$document->id}", $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Isabela State University - Cauayan Campus', $document->refresh()->entity_name);
    }

    public function test_the_issued_document_still_updates_the_equipment_custodian(): void
    {
        $equipment = $this->equipment(['status' => 'available']);

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([['item' => 'equipment:' . $equipment->id, 'quantity' => 1]]))
            ->assertRedirect();

        $document = Document::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/approve");
        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");

        $this->assertSame('issued', $document->refresh()->status);
        $this->assertSame('issued', $equipment->refresh()->status);
    }
}
