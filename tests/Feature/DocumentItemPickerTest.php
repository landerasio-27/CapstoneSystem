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
 * A document line is always a real inventory record — a supply or a piece of
 * equipment — and is never typed in by hand. What the picker offers depends on
 * the form: a PAR records property, an ICS/RIS records supplies. The controller
 * links (and prices) whichever record was chosen.
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
            // Above the Property Classification Threshold, so this property is a PAR.
            'unit_cost' => 55000,
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

    /**
     * The picker lists the whole catalogue, grouped by the paper each item
     * belongs on: the type is no longer chosen by hand, so the form has to offer
     * every item — and say which paper each one will produce.
     */
    public function test_the_picker_offers_both_catalogues_grouped_by_classification(): void
    {
        $supply = $this->supply();
        $equipment = $this->equipment();
        $calculator = $this->supply([
            'item_code' => 'SUP-00002', 'name' => 'Calculator', 'unit_cost' => 45,
            'is_consumable' => false, 'reorder_level' => 0,
        ]);

        $this->actingAs($this->admin)->get('/admin/documents/create')
            ->assertOk()
            ->assertSee('Select an item')
            ->assertSee('<optgroup label="PAR — Non-Consumable Property">', false)
            ->assertSee('<optgroup label="ICS — Non-Consumable Property">', false)
            ->assertSee('<optgroup label="RIS — Consumable Supply">', false)
            ->assertSee('value="supply:' . $supply->id . '" data-classification="RIS"', false)
            ->assertSee('value="equipment:' . $equipment->id . '" data-classification="PAR"', false)
            ->assertSee('value="supply:' . $calculator->id . '" data-classification="ICS"', false)
            // The unit cost prints beside the item, so the office can see why.
            ->assertSee('· ₱55,000.00');

        // A paper asked for by name — a custodian's folder opening a PAR, or an
        // edit form — offers only the items that belong on that paper.
        foreach ([
            'PAR' => [$equipment, 'equipment:'],
            'ICS' => [$calculator, 'supply:'],
            'RIS' => [$supply, 'supply:'],
        ] as $type => [$item, $prefix]) {
            $this->actingAs($this->admin)
                ->get("/admin/documents/create?type={$type}")
                ->assertOk()
                ->assertSee('value="' . $prefix . $item->id . '" data-classification="' . $type . '"', false);
        }

        $this->actingAs($this->admin)->get('/admin/documents/create?type=PAR')
            ->assertOk()
            ->assertDontSee('data-classification="RIS"', false)
            ->assertDontSee('data-classification="ICS"', false);
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
        $equipment = $this->equipment(['name' => 'Epson LCD Projector', 'unit' => 'unit', 'unit_cost' => 55000]);

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
        $this->assertSame('55000.00', (string) $item->unit_cost);
        $this->assertSame('110000.00', (string) $item->amount);
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
     * A transaction holding more than one classification is written as one paper
     * per classification — never filed under a single wrong type. The type the
     * client posts is ignored; the items decide.
     */
    public function test_a_transaction_holding_two_classifications_is_written_as_two_papers(): void
    {
        $supply = $this->supply();
        $equipment = $this->equipment();

        // Posted as a PAR, but the supply line is a consumable: it belongs on a RIS.
        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([
                ['item' => 'supply:' . $supply->id, 'quantity' => 4],
                ['item' => 'equipment:' . $equipment->id, 'quantity' => 1],
            ], 'PAR'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Document::count());

        $par = Document::where('type', 'PAR')->firstOrFail();
        $ris = Document::where('type', 'RIS')->firstOrFail();

        $parLine = $par->items()->firstOrFail();
        $risLine = $ris->items()->firstOrFail();

        $this->assertSame($equipment->id, $parLine->equipment_id);
        $this->assertSame('PAR', $parLine->document_type);
        $this->assertFalse((bool) $parLine->is_consumable);

        $this->assertSame($supply->id, $risLine->supply_id);
        $this->assertSame('RIS', $risLine->document_type);
        $this->assertTrue((bool) $risLine->is_consumable);
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
            ->assertSee('value="equipment:' . $equipment->id . '" data-classification="PAR" data-consumable="0" selected', false);
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
        $this->actingAs($this->admin)->get('/admin/documents/create')
            ->assertOk()
            ->assertSee('name="items[1][item]"', false)
            ->assertSee('value="equipment:' . $equipment->id . '" data-classification="PAR" data-consumable="0" selected', false)
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

    /**
     * Every line names a real inventory record: a typed-in name, however it is
     * dressed up, is refused. The line's item is the picker and nothing else.
     */
    public function test_a_line_that_is_not_picked_from_the_inventory_is_rejected(): void
    {
        foreach ([
            ['item' => '', 'quantity' => 1],
            [
                'item' => '',
                'manual' => '1',
                'description' => 'Custom wooden table',
                'unit' => 'pc',
                'unit_cost' => '1500.50',
                'quantity' => 2,
            ],
        ] as $row) {
            $this->actingAs($this->admin)
                ->post('/admin/documents', $this->payload([$row]))
                ->assertSessionHasErrors('items.0.item');
        }

        $this->assertSame(0, Document::count(), 'A line that names no inventory record may not be saved.');
    }

    /**
     * The form offers the catalogue and nothing else: there is no way to type a
     * line in, and the picker is the required field.
     */
    public function test_the_form_offers_only_the_catalogue_for_a_line(): void
    {
        $this->supply();
        $this->equipment();

        $this->actingAs($this->admin)->get('/admin/documents/create?type=PAR')
            ->assertOk()
            ->assertDontSee('Type manually')
            ->assertDontSee('manual-item-fields', false)
            ->assertDontSee('name="items[0][description]"', false)
            ->assertDontSee('name="items[0][unit_cost]"', false)
            ->assertSee('name="items[0][item]" required', false);
    }

    /** A refused row keeps the item that was picked, ready to be corrected. */
    public function test_a_failed_submit_keeps_the_picked_item_on_the_row(): void
    {
        $equipment = $this->equipment();

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload([
                ['item' => 'equipment:' . $equipment->id, 'quantity' => 0],
            ]))
            ->assertSessionHasErrors('items.0.quantity');

        $content = $this->actingAs($this->admin)->get('/admin/documents/create')
            ->assertOk()
            ->getContent();

        // The row comes back on the item that was picked, not at "Select an item".
        $this->assertMatchesRegularExpression(
            '/<option value="equipment:' . $equipment->id . '"[^>]*\sselected/',
            $content,
        );
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
