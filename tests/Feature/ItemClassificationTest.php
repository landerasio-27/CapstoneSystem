<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\Supply;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ItemClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Item classification — the one rule the whole system reads.
 *
 * The office asks the consumable question first and the price question second:
 * a consumable is always a RIS however much it costs, and non-consumable
 * property is a PAR above the Property Classification Threshold or an ICS at or
 * below it. Everything else — the badges, the panels on the forms, the paper a
 * transaction is written on — follows from App\Services\ItemClassifier, so these
 * tests pin the rule itself, the columns that store it, and the screens that
 * show it.
 */
class ItemClassificationTest extends TestCase
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

    private function teacher(array $attributes = []): Teacher
    {
        return Teacher::create($attributes + [
            'employee_id' => 'FAC-' . str_pad((string) (Teacher::count() + 1), 4, '0', STR_PAD_LEFT),
            'first_name' => 'Cristina',
            'last_name' => 'Bautista',
            'department' => 'College of Business',
            'position' => 'Associate Professor',
            'contact_number' => '09171234567',
            'is_active' => true,
        ]);
    }

    /** A stored paper of a given type, so the register has something to list. */
    private function paper(string $type, array $attributes = []): Document
    {
        return Document::create($attributes + [
            'document_no' => $type . '-2026-' . str_pad((string) (Document::count() + 1), 5, '0', STR_PAD_LEFT),
            'type' => $type,
            'status' => 'draft',
            'document_date' => '2026-09-22',
            'entity_name' => 'Isabela State University - Cauayan Campus',
        ]);
    }

    private function line(Document $document, array $attributes = []): DocumentItem
    {
        return $document->items()->create($attributes + [
            'description' => 'Bond Paper A4 (Ream)',
            'unit' => 'ream',
            'quantity' => 1,
            'unit_cost' => 220,
            'amount' => 220,
            'is_consumable' => $document->type === 'RIS',
            'document_type' => $document->type,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* The rule                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * The consumable answer is read first: a consumable is a RIS at ₱20 and at
     * ₱5,000 alike, because the price never turns a supply into property.
     */
    public function test_the_consumable_answer_decides_the_paper_before_the_price(): void
    {
        foreach ([0, 20, 50, 500, 5000, 35000] as $unitCost) {
            $this->assertSame('RIS', ItemClassifier::for(true, $unitCost), "A consumable at ₱{$unitCost} is still a RIS.");
        }
    }

    /**
     * Non-consumable property is separated by the Property Classification
     * Threshold, and by nothing else: ₱50,000 is an ICS, a centavo more is a PAR.
     */
    public function test_non_consumable_property_changes_paper_at_the_property_classification_threshold(): void
    {
        $this->assertSame(50000.00, ItemClassifier::PROPERTY_THRESHOLD);

        $this->assertSame('ICS', ItemClassifier::for(false, 40));
        $this->assertSame('ICS', ItemClassifier::for(false, 35000));
        $this->assertSame('ICS', ItemClassifier::for(false, 50000));
        $this->assertSame('ICS', ItemClassifier::for(false, '50000.00'));
        $this->assertSame('ICS', ItemClassifier::for(false, 0));
        $this->assertSame('PAR', ItemClassifier::for(false, 50000.01));
        $this->assertSame('PAR', ItemClassifier::for(false, 50001));
        $this->assertSame('PAR', ItemClassifier::for(false, 75000));
    }

    /** Every classification is named, described and toned from the same place. */
    public function test_every_classification_reads_the_same_way_wherever_it_is_shown(): void
    {
        $labels = ItemClassifier::labels();

        $this->assertSame(['PAR', 'ICS', 'RIS'], array_keys($labels));

        $this->assertSame('Property Acknowledgement Receipt', $labels['PAR']['label']);
        $this->assertSame('Inventory Custodian Slip', $labels['ICS']['label']);
        $this->assertSame('Requisition and Issue Slip', $labels['RIS']['label']);

        $this->assertSame('Non-Consumable Property', $labels['PAR']['summary']);
        $this->assertSame('Non-Consumable Property', $labels['ICS']['summary']);
        $this->assertSame('Consumable Supply', $labels['RIS']['summary']);

        // Each paper has its own tone, and the reason names the rule that put the
        // item there — the threshold, never the word "expensive".
        $this->assertSame('bg-brand-50 text-brand-700 ring-brand-200', ItemClassifier::tone('PAR'));
        $this->assertNotSame(ItemClassifier::tone('PAR'), ItemClassifier::tone('ICS'));
        $this->assertNotSame(ItemClassifier::tone('ICS'), ItemClassifier::tone('RIS'));

        // The reason names the rule that put the item there — the threshold for
        // the two property papers, the consumable answer for a RIS — and the word
        // the interface never uses is absent from every one of them.
        $this->assertStringContainsString('₱50,000.00', ItemClassifier::reason('PAR'));
        $this->assertStringContainsString('₱50,000.00', ItemClassifier::reason('ICS'));
        $this->assertStringContainsString('Consumable', ItemClassifier::reason('RIS'));

        $this->assertStringNotContainsString('expensive', strtolower(json_encode($labels)));
    }

    /* ------------------------------------------------------------------ */
    /* What the records store                                              */
    /* ------------------------------------------------------------------ */

    public function test_a_supply_is_consumable_and_equipment_is_property_until_the_office_says_otherwise(): void
    {
        $supply = $this->supply();
        $equipment = $this->equipment();

        $this->assertTrue($supply->is_consumable);
        $this->assertSame('RIS', $supply->document_type);
        $this->assertSame('RIS', $supply->classification());

        $this->assertFalse($equipment->is_consumable);
        $this->assertSame('PAR', $equipment->document_type);
        $this->assertSame('PAR', $equipment->classification());
    }

    /**
     * A semi-expendable item kept in the supply room is property, and is then
     * classified by its own cost — the same two decisions, in the same order.
     */
    public function test_a_non_consumable_supply_is_property_and_is_classified_by_its_cost(): void
    {
        $calculator = $this->supply(['name' => 'Calculator', 'unit_cost' => 45, 'is_consumable' => false]);
        $charger = $this->supply(['name' => 'Laptop Charger', 'unit_cost' => 75000, 'is_consumable' => false]);

        $this->assertFalse($calculator->is_consumable);
        $this->assertSame('ICS', $calculator->document_type);
        $this->assertSame('ICS', $calculator->classification());

        $this->assertSame('PAR', $charger->document_type);
        $this->assertSame('PAR', $charger->classification());
    }

    /**
     * The stored classification follows the recorded price: the receipt that
     * brings the stock in is where the real unit cost arrives, and the item's
     * paper is recomputed from it.
     */
    public function test_the_stored_classification_follows_the_recorded_price(): void
    {
        $supply = $this->supply(['name' => 'Calculator', 'unit_cost' => 45, 'is_consumable' => false]);
        $this->assertSame('ICS', $supply->document_type);

        $this->actingAs($this->admin)
            ->post("/admin/supplies/{$supply->id}/stock", [
                'quantity' => 5,
                'unit_cost' => 75000,
            ])
            ->assertRedirect();

        $supply->refresh();

        $this->assertSame(75000.0, (float) $supply->unit_cost);
        $this->assertSame('PAR', $supply->document_type);
    }

    /**
     * Papers written before the classification existed still know the paper each
     * line belongs to: the backfill stamps the document's own type onto its
     * lines (and the consumable answer that paper stands for), so the register
     * can be filtered the same way for old and new papers alike. History is not
     * re-derived from today's price — the line keeps the paper it was written on.
     */
    public function test_the_backfill_stamps_every_existing_line_with_the_paper_it_was_written_on(): void
    {
        foreach (['PAR', 'ICS', 'RIS'] as $type) {
            $this->line($this->paper($type));
        }

        // The state the two columns are in on a register that predates them.
        DB::table('document_items')->update(['document_type' => null, 'is_consumable' => null]);

        (require database_path('migrations/2026_10_08_000002_backfill_line_classification.php'))->up();

        $this->assertSame(
            ['ICS' => 1, 'PAR' => 1, 'RIS' => 1],
            DocumentItem::query()->selectRaw('document_type, count(*) as total')->groupBy('document_type')->orderBy('document_type')->pluck('total', 'document_type')->all(),
        );

        $this->assertSame(1, DocumentItem::where('document_type', 'RIS')->where('is_consumable', true)->count());
        $this->assertSame(2, DocumentItem::whereIn('document_type', ['PAR', 'ICS'])->where('is_consumable', false)->count());
        $this->assertSame(0, DocumentItem::whereNull('document_type')->count());
    }

    /* ------------------------------------------------------------------ */
    /* The screens                                                         */
    /* ------------------------------------------------------------------ */

    public function test_the_supply_form_asks_whether_the_item_is_consumable_and_names_the_paper(): void
    {
        // A new supply is consumable, so it is headed for a RIS.
        $this->actingAs($this->admin)->get('/admin/supplies/create')
            ->assertOk()
            ->assertSee('Consumable?')
            ->assertSee('Document Classification')
            ->assertSee('name="is_consumable"', false)
            ->assertSee('Consumable Supply');

        // The stored price is the one the panel reads: ₱45 is an ICS, ₱3,500 is
        // a PAR, and the reason names the Property Classification Threshold.
        $calculator = $this->supply(['name' => 'Calculator', 'unit_cost' => 45, 'is_consumable' => false]);

        $this->actingAs($this->admin)->get("/admin/supplies/{$calculator->id}/edit")
            ->assertOk()
            ->assertSee('Inventory Custodian Slip')
            ->assertSee('Based on a unit cost of ₱45.00');

        $charger = $this->supply(['name' => 'Laptop Charger', 'unit_cost' => 75000, 'is_consumable' => false]);

        $this->actingAs($this->admin)->get("/admin/supplies/{$charger->id}/edit")
            ->assertOk()
            ->assertSee('Property Acknowledgement Receipt')
            ->assertSee('unit cost above ₱50,000.00');
    }

    /**
     * Equipment is property and nothing else, so its form never asks the
     * consumable question — and a request that sends the answer anyway is
     * ignored rather than believed. The panel stays, because the recorded price
     * is what decides between a PAR and an ICS.
     */
    public function test_the_equipment_form_never_asks_whether_property_is_consumable(): void
    {
        $equipment = $this->equipment(['name' => 'Dell OptiPlex Desktop', 'unit_cost' => 55000]);

        foreach (['/admin/equipment/create', "/admin/equipment/{$equipment->id}/edit"] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertDontSee('Consumable?')
                ->assertDontSee('name="is_consumable"', false)
                // The paper is still named, and it is never a RIS.
                ->assertSee('Document Classification')
                ->assertSee('Non-Consumable Property');
        }

        // Creating one, with the answer smuggled in: it stays property.
        $this->actingAs($this->admin)->post('/admin/equipment', [
            'item_code' => 'EQP-00099',
            'name' => 'Wireless Microphone Set',
            'unit' => 'unit',
            'status' => 'available',
            'is_consumable' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $created = Equipment::where('item_code', 'EQP-00099')->sole();
        $this->assertFalse($created->is_consumable);
        $this->assertSame('ICS', $created->classification());

        // Editing one the same way cannot turn property into a consumable.
        $this->actingAs($this->admin)->put("/admin/equipment/{$equipment->id}", [
            'item_code' => $equipment->item_code,
            'name' => $equipment->name,
            'unit' => 'unit',
            'status' => 'available',
            'is_consumable' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $equipment->refresh();
        $this->assertFalse($equipment->is_consumable);
        $this->assertSame('PAR', $equipment->classification());
    }

    public function test_the_item_registers_carry_the_classification_beside_every_item(): void
    {
        $supply = $this->supply(['name' => 'Bond Paper A4 (Ream)']);
        $calculator = $this->supply(['name' => 'Calculator', 'unit_cost' => 45, 'is_consumable' => false]);
        $equipment = $this->equipment(['name' => 'Dell OptiPlex Desktop', 'unit_cost' => 55000]);

        // Each row carries the badge for its own classification, with the paper's
        // full name in its tooltip.
        $this->actingAs($this->admin)->get('/admin/supplies')
            ->assertOk()
            ->assertSee('Bond Paper A4 (Ream)')
            ->assertSee('Calculator')
            ->assertSee('title="Requisition and Issue Slip — Consumable Supply"', false)
            ->assertSee('title="Inventory Custodian Slip — Non-Consumable Property"', false);

        $this->actingAs($this->admin)->get('/admin/equipment')
            ->assertOk()
            ->assertSee('Dell OptiPlex Desktop')
            ->assertSee('title="Property Acknowledgement Receipt — Non-Consumable Property"', false);

        // The record itself prints why, and the whole name of the paper.
        $this->actingAs($this->admin)->get("/admin/supplies/{$supply->id}")
            ->assertOk()
            ->assertSee('Requisition and Issue Slip')
            ->assertSee('Consumable — used up, depleted or consumed in operations.');

        $this->actingAs($this->admin)->get("/admin/equipment/{$equipment->id}")
            ->assertOk()
            ->assertSee('Property Acknowledgement Receipt')
            ->assertSee('Non-consumable with a unit cost above ₱50,000.00.');
    }

    /**
     * A line is classified from the inventory record it names, so a pricey
     * consumable is written on a RIS — not a PAR because of what it costs.
     */
    public function test_a_consumable_line_is_written_on_a_ris_however_much_it_costs(): void
    {
        $toner = $this->supply(['name' => 'Toner Cartridge', 'unit' => 'cartridge', 'unit_cost' => 500]);

        $this->actingAs($this->admin)
            ->post('/admin/documents', [
                'document_date' => '2026-09-22',
                'entity_name' => 'Isabela State University - Cauayan Campus',
                'items' => [[
                    'item' => 'supply:' . $toner->id,
                    'quantity' => 2,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document = Document::latest('id')->firstOrFail();
        $line = $document->items()->sole();

        $this->assertSame('RIS', $document->type);
        $this->assertTrue($line->is_consumable);
        $this->assertSame('RIS', $line->document_type);
        $this->assertSame('RIS', $line->classification());
        $this->assertSame(1000.0, (float) $line->amount);
    }

    /* ------------------------------------------------------------------ */
    /* Preventing an incorrect classification                              */
    /* ------------------------------------------------------------------ */

    /**
     * A draft cannot be made to hold a line that belongs on another paper: the
     * line is refused with the paper it should be filed on, so nothing is ever
     * written on the wrong form.
     */
    public function test_a_draft_refuses_a_line_that_belongs_on_another_paper(): void
    {
        $equipment = $this->equipment(['name' => 'Dell OptiPlex Desktop', 'unit_cost' => 55000]);
        $supply = $this->supply(['name' => 'Bond Paper A4 (Ream)']);

        $this->actingAs($this->admin)
            ->post('/admin/documents', [
                'document_date' => '2026-09-22',
                'entity_name' => 'Isabela State University - Cauayan Campus',
                'items' => [['item' => 'equipment:' . $equipment->id, 'quantity' => 1]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $par = Document::latest('id')->firstOrFail();
        $this->assertSame('PAR', $par->type);

        // Editing the PAR to add a ream of bond paper — a consumable — is refused.
        $this->actingAs($this->admin)
            ->put("/admin/documents/{$par->id}", [
                'document_date' => '2026-09-22',
                'entity_name' => 'Isabela State University - Cauayan Campus',
                'items' => [
                    ['item' => 'equipment:' . $equipment->id, 'quantity' => 1],
                    ['item' => 'supply:' . $supply->id, 'quantity' => 1],
                ],
            ])
            ->assertSessionHasErrors('items.1.item');

        $this->assertStringContainsString(
            'cannot be filed on this PAR',
            session('errors')->first('items.1.item'),
        );

        // Nothing was written: the draft still holds only its own line.
        $this->assertSame('PAR', $par->fresh()->type);
        $this->assertSame(1, $par->items()->count());
    }

    /* ------------------------------------------------------------------ */
    /* The dashboard and the register                                      */
    /* ------------------------------------------------------------------ */

    public function test_the_classification_figures_are_counted_once_for_every_page(): void
    {
        // One paper of each kind, and the items behind them.
        $this->line($this->paper('PAR'));
        $this->line($this->paper('ICS'), ['description' => 'Calculator', 'unit_cost' => 45, 'amount' => 45]);
        $this->line($this->paper('RIS'));

        $this->supply(['name' => 'Bond Paper A4 (Ream)', 'unit_cost' => 220, 'quantity_on_hand' => 10]);
        $this->supply(['name' => 'Laptop Charger', 'unit_cost' => 75000, 'quantity_on_hand' => 2, 'is_consumable' => false]);
        $this->equipment(['name' => 'Dell OptiPlex Desktop', 'unit_cost' => 55000, 'quantity_on_hand' => 1]);

        $figures = app(\App\Services\InventoryAnalyticsService::class)->classificationSummary();

        $this->assertSame(['PAR' => 1, 'ICS' => 1, 'RIS' => 1], $figures['papers']);
        $this->assertSame(3, $figures['papers_total']);

        // Items: the consumable supply is a RIS, the non-consumable one and the
        // desktop are property (₱75,000 → PAR, ₱55,000 → PAR), and the ICS paper's
        // line is not a catalogue item, so it is not counted as one.
        $this->assertSame(['PAR' => 2, 'ICS' => 0, 'RIS' => 1], $figures['items']);
        $this->assertSame(1, $figures['consumable']);
        $this->assertSame(2, $figures['non_consumable']);

        // Property value is what the office holds: 2 × ₱75,000 + 1 × ₱55,000.
        $this->assertSame(205000.0, $figures['property_value']);

        // The dashboard prints them...
        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Property Acknowledgement Receipt')
            ->assertSee('Inventory Custodian Slip')
            ->assertSee('Requisition and Issue Slip')
            ->assertSee('Total consumable supplies')
            ->assertSee('Total non-consumable property')
            ->assertSee('Total property value')
            ->assertSee('₱205,000.00');

        // ... and so does the register, which offers the same figures as links.
        $this->actingAs($this->admin)->get('/admin/documents')
            ->assertOk()
            ->assertSee('Total property value')
            ->assertSee('₱205,000.00');
    }

    public function test_the_register_filters_by_paper_search_and_kind_of_item(): void
    {
        $custodian = $this->teacher(['first_name' => 'Cristina', 'last_name' => 'Bautista']);
        $engineer = $this->teacher(['first_name' => 'Miguel', 'last_name' => 'Santos', 'department' => 'College of Engineering']);

        $paper = $this->supply(['name' => 'Bond Paper A4 (Ream)', 'category' => 'Office Supplies']);
        $desktop = $this->equipment(['name' => 'Dell OptiPlex Desktop', 'category' => 'ICT Equipment', 'unit_cost' => 55000]);

        $par = $this->paper('PAR', ['document_no' => 'PAR-2026-00001', 'teacher_id' => $custodian->id]);
        $this->line($par, ['equipment_id' => $desktop->id, 'description' => 'Dell OptiPlex Desktop', 'unit_cost' => 55000, 'amount' => 55000]);

        $ics = $this->paper('ICS', ['document_no' => 'ICS-2026-00001']);
        $this->line($ics, ['description' => 'Calculator', 'unit_cost' => 45, 'amount' => 45]);

        $ris = $this->paper('RIS', ['document_no' => 'RIS-2026-00001', 'teacher_id' => $engineer->id]);
        $this->line($ris, ['supply_id' => $paper->id]);

        // The pills narrow to one paper and keep the rest of the address.
        $this->actingAs($this->admin)->get('/admin/documents?type=ICS')
            ->assertOk()
            ->assertSee('ICS-2026-00001')
            ->assertDontSee('PAR-2026-00001')
            ->assertDontSee('RIS-2026-00001');

        // A pill is offered per paper the register itself holds. An IAR is not
        // one of them — it is page two of a PAR / ICS / RIS, never a row of its
        // own — so the register offers no IAR to narrow to.
        $this->actingAs($this->admin)->get('/admin/documents')
            ->assertOk()
            ->assertSee('type=PAR', false)
            ->assertSee('type=RIS', false)
            ->assertDontSee('type=IAR', false);

        // Search reads the paper's number, the items printed on it and the
        // custodian it is filed under.
        foreach (['PAR-2026-00001', 'Dell OptiPlex', 'Bautista'] as $term) {
            $this->actingAs($this->admin)->get('/admin/documents?search=' . urlencode($term))
                ->assertOk()
                ->assertSee('PAR-2026-00001');
        }

        $this->actingAs($this->admin)->get('/admin/documents?search=Bond+Paper')
            ->assertOk()
            ->assertSee('RIS-2026-00001')
            ->assertDontSee('PAR-2026-00001')
            ->assertDontSee('ICS-2026-00001');

        // The custodian, the department and the kind of item all narrow too.
        $this->actingAs($this->admin)->get('/admin/documents?custodian=' . $custodian->id)
            ->assertOk()
            ->assertSee('PAR-2026-00001')
            ->assertDontSee('ICS-2026-00001');

        $this->actingAs($this->admin)->get('/admin/documents?custodian=' . $engineer->id)
            ->assertOk()
            ->assertSee('RIS-2026-00001')
            ->assertDontSee('PAR-2026-00001')
            ->assertDontSee('ICS-2026-00001');

        // The department is read off the custodian the paper is filed under.
        $this->actingAs($this->admin)->get('/admin/documents?department=College+of+Business')
            ->assertOk()
            ->assertSee('PAR-2026-00001')
            ->assertDontSee('RIS-2026-00001')
            ->assertDontSee('ICS-2026-00001');

        $this->actingAs($this->admin)->get('/admin/documents?consumable=1')
            ->assertOk()
            ->assertSee('RIS-2026-00001')
            ->assertDontSee('PAR-2026-00001')
            ->assertDontSee('ICS-2026-00001');

        $this->actingAs($this->admin)->get('/admin/documents?consumable=0')
            ->assertOk()
            ->assertSee('PAR-2026-00001')
            ->assertSee('ICS-2026-00001')
            ->assertDontSee('RIS-2026-00001');

        // A category of the catalogue the papers' own lines point at.
        $this->actingAs($this->admin)->get('/admin/documents?category=Office+Supplies')
            ->assertOk()
            ->assertSee('RIS-2026-00001')
            ->assertDontSee('PAR-2026-00001')
            ->assertDontSee('ICS-2026-00001');

        $this->actingAs($this->admin)->get('/admin/documents?category=ICT+Equipment')
            ->assertOk()
            ->assertSee('PAR-2026-00001')
            ->assertDontSee('RIS-2026-00001');
    }
}
