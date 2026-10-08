<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\Supply;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SheetPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Reports → PAR / ICS / RIS.
 *
 * Three separate official forms. Everything they print is read from records on
 * file — equipment for PAR and ICS, RIS documents for RIS — and every amount is
 * quantity × unit cost, so a report can never invent a transaction or a figure.
 */
class DocumentReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The starred line that closes a list. It is as wide as the columns it
     * spans, so it is written to each table's own widths: the RIS and the
     * monthly sheets run it from the description column to the end of the unit
     * cost column, and the narrower ICS slip carries a shorter run.
     */
    private const NOTHING_FOLLOWS = '**************************Nothing Follows**************************';

    private const NOTHING_FOLLOWS_ICS = '************************Nothing Follows************************';

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
            'description' => 'Substance 20 · 500 sheets per ream',
            'category' => 'Office Supplies',
            'unit' => 'ream',
            'quantity_on_hand' => 100,
            'unit_cost' => 220,
            'reorder_level' => 0,
            'is_active' => true,
        ]);
    }

    private function equipment(array $attributes = []): Equipment
    {
        return Equipment::create($attributes + [
            'item_code' => 'EQP-00001',
            'property_number' => 'PPE-2026-0001',
            'name' => 'Desktop Computer',
            'description' => "Processor: RYZEN 5 5600GT\nMemory (RAM): 8GB DDR4\nStorage: 512GB M.2 NVME SSD",
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 35000,
            'status' => 'available',
        ]);
    }

    private function teacher(string $first, string $last, array $attributes = []): Teacher
    {
        return Teacher::create($attributes + [
            'employee_id' => 'EMP-'.str_pad((string) (Teacher::count() + 1), 4, '0', STR_PAD_LEFT),
            'first_name' => $first,
            'last_name' => $last,
            'position' => 'Instructor I',
            'is_active' => true,
        ]);
    }

    /**
     * An RIS document with its lines, written straight to the models so a test
     * can pin the date and status the report filters on.
     *
     * @param  array<int, array>  $lines  each: ['supply'|'equipment' => model, 'quantity' => …, 'issued_quantity' => …]
     */
    private function ris(string $number, string $date, array $lines, array $attributes = []): Document
    {
        $document = Document::create($attributes + [
            'document_no' => $number,
            'type' => 'RIS',
            'status' => 'issued',
            'entity_name' => Card::DEFAULT_ENTITY,
            'document_date' => $date,
        ]);

        foreach ($lines as $line) {
            $record = $line['supply'] ?? $line['equipment'];
            $quantity = (int) ($line['quantity'] ?? 1);
            $unitCost = (float) ($line['unit_cost'] ?? $record->unit_cost);

            $document->items()->create([
                'supply_id' => isset($line['supply']) ? $record->id : null,
                'equipment_id' => isset($line['equipment']) ? $record->id : null,
                'description' => $record->name,
                'unit' => $record->unit,
                'quantity' => $quantity,
                'issued_quantity' => $line['issued_quantity'] ?? null,
                'unit_cost' => $unitCost,
                'amount' => $quantity * $unitCost,
                'property_number' => $line['property_number'] ?? null,
            ]);
        }

        return $document;
    }

    /** An ICS document, so equipment can be marked as assigned through one. */
    private function ics(string $number, Equipment $equipment, array $attributes = []): Document
    {
        $document = Document::create($attributes + [
            'document_no' => $number,
            'type' => 'ICS',
            'status' => 'issued',
            'document_date' => '2026-08-01',
        ]);

        $document->items()->create([
            'equipment_id' => $equipment->id,
            'description' => $equipment->name,
            'unit' => $equipment->unit,
            'quantity' => 1,
            'unit_cost' => $equipment->unit_cost,
            'amount' => $equipment->unit_cost,
        ]);

        return $document;
    }

    /* ------------------------------------------------------------------ */
    /* The picker and the three templates                                  */
    /* ------------------------------------------------------------------ */

    public function test_the_report_picker_groups_the_three_official_documents(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reports/par-ics-ris')
            ->assertOk()
            ->assertSee('Property Documents')
            ->assertSee('Supply Documents')
            ->assertSee('Property Acknowledgement Receipt')
            ->assertSee('Inventory Custodian Slip')
            ->assertSee('Report of Supplies and Materials Issued')
            ->assertSee('href="'.route('admin.reports.document.index', ['type' => 'par']).'"', false)
            ->assertSee('href="'.route('admin.reports.document.index', ['type' => 'ics']).'"', false)
            ->assertSee('href="'.route('admin.reports.document.index', ['type' => 'ris']).'"', false);
    }

    public function test_the_reports_index_leads_to_the_official_documents(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee(route('admin.reports.documents.index'), false)
            ->assertSee('PAR / ICS / RIS documents');
    }

    public function test_the_reports_menu_no_longer_lists_the_document_picker(): void
    {
        // The Reports menu keeps only the monthly report and the inventory
        // summary; the picker is no longer a menu entry, though it still
        // answers at its own URL.
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('href="'.route('admin.reports.documents.index').'"', false);

        $this->actingAs($this->admin)
            ->get('/admin/reports/par-ics-ris')
            ->assertOk();
    }

    public function test_each_generator_renders_only_its_own_document(): void
    {
        // Same data, three papers: the templates never bleed into each other.
        $this->actingAs($this->admin)
            ->get('/admin/reports/par')
            ->assertOk()
            ->assertSee('Property Acknowledgement Receipt')
            ->assertDontSee('Inventory Custodian Slip')
            ->assertDontSee('Report of Supplies and Materials Issued');

        $this->actingAs($this->admin)
            ->get('/admin/reports/ics')
            ->assertOk()
            ->assertSee('Inventory Custodian Slip')
            ->assertDontSee('Property Acknowledgement Receipt')
            ->assertDontSee('Report of Supplies and Materials Issued');

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris')
            ->assertOk()
            ->assertSee('Report of Supplies and Materials Issued')
            ->assertDontSee('Property Acknowledgement Receipt')
            ->assertDontSee('Inventory Custodian Slip');
    }

    public function test_each_generator_offers_its_own_filters(): void
    {
        $this->equipment();

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris')
            ->assertOk()
            ->assertSee('name="month"', false)
            ->assertSee('name="year"', false)
            ->assertSee('name="end_user"', false)
            ->assertSee('name="stock_no"', false)
            ->assertSee('name="category"', false)
            ->assertSee('Generate Report')
            ->assertSee('Preview')
            ->assertSee('Export PDF')
            ->assertDontSee('name="equipment[]"', false);

        // PAR and ICS are built from a selection of equipment, not a period.
        foreach (['par', 'ics'] as $type) {
            $this->actingAs($this->admin)
                ->get("/admin/reports/{$type}")
                ->assertOk()
                ->assertSee('name="equipment[]"', false)
                ->assertSee('Received from:')
                ->assertSee('Received by:');
        }
    }

    public function test_the_official_documents_are_open_to_the_supply_staff(): void
    {
        $role = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Inspection Staff']);
        $staff = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $urls = ['/admin/reports/par-ics-ris', '/admin/reports/par', '/admin/reports/ics', '/admin/reports/ris'];

        // Still behind the door: nobody outside the office reads a form.
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        // The supply staff raise these papers, so the sheets they print are
        // theirs to generate, preview and export.
        foreach ($urls as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }

        $equipment = $this->equipment();
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        foreach (['par', 'ics'] as $type) {
            $this->actingAs($staff)->get("/admin/reports/{$type}/print?equipment[]={$equipment->id}")->assertOk();
        }

        $this->actingAs($staff)->get('/admin/reports/ris/print?month=8&year=2026')->assertOk();

        $pdf = $this->actingAs($staff)->get('/admin/reports/ris/pdf?month=8&year=2026');
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        // The routes exist under their own names, too.
        $this->assertTrue(Route::has('admin.reports.documents.index'));
        $this->assertTrue(Route::has('admin.reports.document.print'));
        $this->assertTrue(Route::has('admin.reports.document.pdf'));
    }

    /**
     * The register is worked, not just viewed: a supply staff account raises a
     * paper, approves it, issues it and prints it, and the workflow stamps the
     * document with their own account rather than the administrator's.
     */
    public function test_a_supply_staff_account_works_the_document_register(): void
    {
        $role = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Inspection Staff']);
        $staff = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $equipment = $this->equipment();
        $custodian = $this->teacher('Maria', 'Santos');

        $this->actingAs($staff)->get('/admin/documents')->assertOk();
        $this->actingAs($staff)->get('/admin/documents/create?type=PAR')->assertOk();

        $this->actingAs($staff)
            ->post('/admin/documents', [
                'type' => 'PAR',
                'document_date' => '2026-09-22',
                'entity_name' => 'Isabela State University - Cauayan Campus',
                'teacher_id' => $custodian->id,
                'items' => [['item' => 'equipment:'.$equipment->id, 'quantity' => 1]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document = Document::latest('id')->firstOrFail();

        $this->actingAs($staff)->post("/admin/documents/{$document->id}/approve")->assertRedirect();
        $this->assertSame('approved', $document->fresh()->status);

        $this->actingAs($staff)->post("/admin/documents/{$document->id}/issue")->assertRedirect();
        $this->assertSame('issued', $document->fresh()->status);
        $this->assertSame($staff->id, $document->fresh()->issued_by);

        // The paper comes off the register as a PDF for them as well.
        $pdf = $this->actingAs($staff)->get("/admin/documents/{$document->id}/pdf");
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    /* ------------------------------------------------------------------ */
    /* PAR                                                                 */
    /* ------------------------------------------------------------------ */

    public function test_a_par_is_built_from_the_equipment_that_was_selected(): void
    {
        $equipment = $this->equipment();
        $custodian = $this->teacher('Maria', 'Santos');
        $equipment->update(['teacher_id' => $custodian->id, 'room_id' => null]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/par?equipment[]='.$equipment->id)
            ->assertOk()
            ->assertSee('PPE-2026-0001')
            ->assertSee('EQP-00001')
            ->assertSee('Desktop Computer')
            // The long description is printed in full, line by line.
            ->assertSee('RYZEN 5 5600GT')
            ->assertSee('512GB M.2 NVME SSD')
            ->assertSee('Maria Santos')
            ->assertSee('Instructor I')
            // Amount is quantity × unit cost, worked out from the record.
            ->assertSee('35,000.00');
    }

    public function test_a_par_can_cover_several_equipment_records(): void
    {
        $first = $this->equipment();
        $second = $this->equipment([
            'item_code' => 'EQP-00002',
            'property_number' => 'PPE-2026-0002',
            'name' => 'Laptop Computer',
            'description' => 'Core i7 · 16GB RAM',
            'unit_cost' => 55000,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/reports/par?equipment[]={$first->id}&equipment[]={$second->id}")
            ->assertOk()
            ->assertSee('Desktop Computer')
            ->assertSee('Laptop Computer')
            ->assertSee('PPE-2026-0002')
            // Both are on the receipt, so both amounts are.
            ->assertSee('35,000.00')
            ->assertSee('55,000.00')
            // The total is the sum of the two computed amounts.
            ->assertSee('90,000.00');
    }

    public function test_a_par_amount_is_quantity_times_unit_cost(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 4, 'unit_cost' => 1250]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/par?equipment[]='.$equipment->id)
            ->assertOk()
            ->assertSee('5,000.00');
    }

    public function test_an_empty_par_says_there_are_no_records(): void
    {
        $this->equipment();

        $this->actingAs($this->admin)
            ->get('/admin/reports/par')
            ->assertOk()
            ->assertSee('No records found for the selected period.')
            // Nothing is invented to fill the sheet: no amount is printed for
            // equipment that was never selected.
            ->assertDontSee('35,000.00');
    }

    public function test_generating_a_report_writes_nothing_to_the_database(): void
    {
        $equipment = $this->equipment();

        foreach (['par', 'ics', 'ris'] as $type) {
            $this->actingAs($this->admin)->get("/admin/reports/{$type}?equipment[]={$equipment->id}")->assertOk();
        }

        $this->assertSame(0, Document::count());
        $this->assertSame(1, Equipment::count());
    }

    /* ------------------------------------------------------------------ */
    /* ICS                                                                 */
    /* ------------------------------------------------------------------ */

    public function test_an_ics_prints_one_slip_per_custodian(): void
    {
        $maria = $this->teacher('Maria', 'Santos');
        $jose = $this->teacher('Jose', 'Cruz');

        $first = $this->equipment(['teacher_id' => $maria->id]);
        $second = $this->equipment(['item_code' => 'EQP-00002', 'property_number' => 'PPE-2026-0002', 'name' => 'Laptop Computer', 'teacher_id' => $jose->id]);

        $html = $this->actingAs($this->admin)
            ->get("/admin/reports/ics?equipment[]={$first->id}&equipment[]={$second->id}")
            ->assertOk()
            ->assertSee('Maria Santos')
            ->assertSee('Jose Cruz')
            ->assertSee('Desktop Computer')
            ->assertSee('Laptop Computer')
            ->assertSee('Received from:')
            ->assertSee('Received by:')
            ->getContent();

        // Two sheets, each starting a page of its own.
        $this->assertSame(2, substr_count($html, 'class="doc doc-sheet"'));
        // And each slip carries the next free ICS number, not the same one twice.
        $this->assertStringContainsString('ICS-2026-00001', $html);
        $this->assertStringContainsString('ICS-2026-00002', $html);
    }

    public function test_the_ics_list_opens_on_the_property_assigned_through_an_ics(): void
    {
        $viaIcs = $this->equipment();
        $other = $this->equipment(['item_code' => 'EQP-00002', 'property_number' => 'PPE-2026-0002', 'name' => 'Printer']);

        $this->ics('ICS-2026-00001', $viaIcs);

        $html = $this->actingAs($this->admin)->get('/admin/reports/ics')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/value="'.$viaIcs->id.'"\s+data-picker-checkbox checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$other->id.'"\s+data-picker-checkbox checked/', $html);
        $this->assertStringContainsString('via ICS', $html);
    }

    public function test_a_submitted_ics_selection_replaces_the_default_ticking(): void
    {
        $viaIcs = $this->equipment();
        $other = $this->equipment(['item_code' => 'EQP-00002', 'property_number' => 'PPE-2026-0002', 'name' => 'Printer']);

        $this->ics('ICS-2026-00001', $viaIcs);

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ics?equipment[]='.$other->id)
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/value="'.$other->id.'"\s+data-picker-checkbox checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$viaIcs->id.'"\s+data-picker-checkbox checked/', $html);
    }

    public function test_an_ics_amount_is_quantity_times_unit_cost(): void
    {
        $equipment = $this->equipment(['quantity_on_hand' => 3, 'unit_cost' => 1200]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ics?equipment[]='.$equipment->id)
            ->assertOk()
            ->assertSee('3,600.00')
            ->assertSee('Inventory Custodian Slip');
    }

    public function test_an_empty_ics_says_there_are_no_records(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reports/ics')
            ->assertOk()
            ->assertSee('No records found for the selected period.');
    }

    public function test_the_ics_closes_its_list_with_nothing_follows(): void
    {
        $equipment = $this->equipment();

        $this->actingAs($this->admin)
            ->get('/admin/reports/ics?equipment[]='.$equipment->id)
            ->assertOk()
            ->assertSee(self::NOTHING_FOLLOWS_ICS);

        // An empty slip has no list to close: it says so instead.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ics')
            ->assertOk()
            ->assertSee('No records found for the selected period.')
            ->assertDontSee(self::NOTHING_FOLLOWS_ICS);
    }

    /**
     * The header block and the item list are two tables, but the slip is ruled
     * as one sheet: the line between the entity's name and its value stands on
     * the line between the property number and the item, and runs on down the
     * list with no step where the two tables meet.
     */
    public function test_the_ics_header_column_stands_on_the_item_columns(): void
    {
        $equipment = $this->equipment();

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ics?equipment[]='.$equipment->id)
            ->assertOk()
            ->getContent();

        $head = substr($html, (int) strpos($html, '<table class="doc-grid">'));
        $head = substr($head, 0, (int) strpos($head, '</table>'));
        $items = substr($html, (int) strpos($html, 'doc-grid doc-fixed no-top'));
        $items = substr($items, 0, (int) strpos($items, '</table>'));

        // The label column and the property number column are the same column.
        $this->assertMatchesRegularExpression('/style="width:([\d.]+)%;">Entity Name:</', $head);
        $this->assertMatchesRegularExpression('/style="width:([\d.]+)%;">Inventory \/ Property No\.</', $items);
        preg_match('/style="width:([\d.]+)%;">Entity Name:</', $head, $label);
        preg_match('/style="width:([\d.]+)%;">Inventory \/ Property No\.</', $items, $number);
        $this->assertSame(
            $label[1],
            $number[1],
            'the header block rules its first column at the width the list below it rules its own'
        );

        // And the list's columns are the sheet's columns, held whatever is
        // written in them: a lengthy description must not widen its column and
        // walk the verticals off the lines the headings above them draw.
        $this->assertStringContainsString('.doc-fixed { table-layout: fixed; }', $html);
    }

    /**
     * The recapitulation closes the same slip, so it stands on the same columns:
     * the stock number under the list's item column, the quantity at the end of
     * the description, and the money group under the columns that name it.
     * Every line the block draws meets a line of the list above it.
     */
    public function test_the_ics_recapitulation_stands_on_the_ics_lists_columns(): void
    {
        $equipment = $this->equipment();

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ics?equipment[]='.$equipment->id)
            ->assertOk()
            ->getContent();

        $recap = substr($html, (int) strpos($html, 'doc-grid doc-recap'));
        $recap = substr($recap, 0, (int) strpos($recap, '</table>'));

        // The list's columns are 14 / 18 / 32 / 6 / 7 / 11 / 12, so its rules
        // stand at 14, 32, 64, 70, 77 and 88 per cent. The block's shares add
        // up to the same lines: 14, then 18 (32), 10 and 22 (64), 13 (77) and
        // 11 (88). The tenth of a width that only separates the quantity from
        // the open middle is the one seam the block does not rule.
        $this->assertStringContainsString('doc-recap-ics', $recap);
        $this->assertStringContainsString('<th class="doc-c doc-strip" style="width:14%;"></th>', $recap);

        // The slip closes its list the way the RIS does: the closing line stops
        // at the strip the block opens beside, and the item column reads on down
        // it, so the two papers leave the same corner of paper open.
        $this->assertMatchesRegularExpression('/<tr class="doc-foot">\s*<td class="doc-strip"><\/td>/', $html);
        $this->assertStringContainsString('style="width:18%;">STOCK NO:', $recap);
        $this->assertStringContainsString('style="width:10%;">QNTY', $recap);
        $this->assertStringContainsString('style="width:22%;"></th>', $recap);
        $this->assertStringContainsString('style="width:13%;">UNIT COST', $recap);
        $this->assertStringContainsString('style="width:11%;">TOTAL COST', $recap);
        $this->assertStringContainsString('style="width:12%;">AMOUNT', $recap);

        // The band's runs are given the spans those shares leave: the first
        // over the stock and the quantity to the end of the description, the
        // second over the whole money group. The band opens at the stock
        // number: the strip the list's number column leaves beside the block
        // carries no rule of its own, and the run beside it drops the line
        // along that seam, as the two runs drop the line between them.
        $this->assertStringContainsString('<td class="doc-strip"></td>', $recap);
        $this->assertStringContainsString('<td colspan="3" class="band doc-seam-l doc-seam-r">', $recap);
        $this->assertStringContainsString('<td colspan="2" class="band doc-seam-l">', $recap);

        // The RIS keeps its own shares: the same partial, the same block as
        // the sheet prints it, so its list and its recapitulation still meet.
        $ris = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=2&year=2026')
            ->assertOk()
            ->getContent();
        $risRecap = substr($ris, (int) strpos($ris, 'doc-grid doc-recap'));
        $this->assertStringNotContainsString('doc-recap-ics', $risRecap);
        $this->assertStringContainsString('style="width:14.5%;">UNIT COST', $risRecap);
    }

    public function test_the_ics_recapitulation_totals_each_property_number(): void
    {
        $teacher = $this->teacher('Maria', 'Santos');
        $desktop = $this->equipment([
            'teacher_id' => $teacher->id,
            'property_number' => 'PPE-2026-0001',
            'quantity_on_hand' => 3,
            'unit_cost' => 1200,
        ]);
        $printer = $this->equipment([
            'teacher_id' => $teacher->id,
            'item_code' => 'EQP-00002',
            'property_number' => 'PPE-2026-0002',
            'name' => 'Printer',
            'quantity_on_hand' => 1,
            'unit_cost' => 500,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/reports/ics?equipment[]={$desktop->id}&equipment[]={$printer->id}")
            ->assertOk()
            // The slip closes with the recapitulation, keyed by property number.
            ->assertSee('Recapitulation')
            ->assertSee('PPE-2026-0001')
            ->assertSee('PPE-2026-0002')
            // 3 × 1,200 = 3,600.00 and 1 × 500 = 500.00, totalling 4,100.00.
            ->assertSee('3,600.00')
            ->assertSee('500.00')
            ->assertSee('4,100.00');
    }

    public function test_the_ics_certification_and_signature_lines_are_printed(): void
    {
        $equipment = $this->equipment();

        $this->actingAs($this->admin)
            ->get('/admin/reports/ics?equipment[]='.$equipment->id)
            ->assertOk()
            ->assertSee('I HEREBY CERTIFY TO THE CORRECTNESS OF THE ABOVE INFORMATION')
            ->assertSee('Posted By/ Date:')
            ->assertSee('Administrative Officer IV')
            ->assertSee('Accounting Clerk')
            // The supply officer who issues the slip is the one who posts it.
            ->assertSee($this->admin->name);
    }

    /* ------------------------------------------------------------------ */
    /* RIS                                                                 */
    /* ------------------------------------------------------------------ */

    public function test_the_ris_report_covers_only_the_chosen_month(): void
    {
        $supply = $this->supply();

        $before = $this->ris('RIS-2026-00001', '2026-07-31', [['supply' => $supply, 'quantity' => 1]]);
        $firstDay = $this->ris('RIS-2026-00080', '2026-08-01', [['supply' => $supply, 'quantity' => 2]]);
        $lastDay = $this->ris('RIS-2026-00081', '2026-08-31', [['supply' => $supply, 'quantity' => 3]]);
        $after = $this->ris('RIS-2026-00099', '2026-09-01', [['supply' => $supply, 'quantity' => 4]]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            // Both ends of the month are inside the period…
            ->assertSee($firstDay->document_no)
            ->assertSee($lastDay->document_no)
            // …and nothing outside it is.
            ->assertDontSee($before->document_no)
            ->assertDontSee($after->document_no);
    }

    public function test_the_ris_header_names_the_month_it_covers(): void
    {
        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->assertSee('AUGUST 01-31, 2026')
            // The number is the report's own to give, so a page that has only
            // been opened holds none: the line in the sheet stands blank until
            // the report is generated, or until a number is written into it.
            ->assertSee('value=""', false)
            ->assertDontSee('2026-08-080')
            ->assertSee(Card::DEFAULT_ENTITY);
    }

    public function test_the_report_number_writes_the_sequence_in_dashes_and_three_digits(): void
    {
        $supply = $this->supply();

        // The form carries the number to three digits, so the first paper of a
        // month reads 2026-08-001 rather than 2026-08-1…
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $supply, 'quantity' => 1]]);
        // …and a sequence already past two digits keeps all three it has:
        // 080, as the form writes it.
        $this->ris('RIS-2026-00080', '2026-09-05', [['supply' => $supply, 'quantity' => 1]]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->assertSee('<span class="doc-sign-line">2026-08-001</span>', false);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=9&year=2026')
            ->assertOk()
            ->assertSee('<span class="doc-sign-line">2026-09-080</span>', false);
    }

    public function test_the_ris_number_and_end_user_are_written_once_per_paper(): void
    {
        $supply = $this->supply();
        $maria = $this->teacher('Maria', 'Santos');

        // Two lines on one paper, one on the next, both drawn by the same person.
        $this->ris('RIS-2026-00080', '2026-08-03', [
            ['supply' => $supply, 'quantity' => 2],
            ['supply' => $supply, 'quantity' => 1],
        ], ['teacher_id' => $maria->id]);
        $this->ris('RIS-2026-00090', '2026-08-04', [
            ['supply' => $supply, 'quantity' => 5],
        ], ['teacher_id' => $maria->id]);

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&show_costs=1')
            ->assertOk()
            ->getContent();

        // The item lines: the first table after the header block.
        $table = substr($html, (int) strpos($html, '<table class="doc-grid doc-items doc-fixed no-top">'));
        $body = substr($table, (int) strpos($table, '<tbody>'));
        $body = substr($body, 0, (int) strpos($body, '</tbody>'));

        // Once for each paper, not once for each line…
        $this->assertSame(1, substr_count($body, 'RIS-2026-00080'));
        $this->assertSame(1, substr_count($body, 'RIS-2026-00090'));
        // …and the person is named once per paper as well.
        $this->assertSame(2, substr_count($body, 'Maria Santos'));
        // Every line under a paper keeps its own figures: 2×220, 1×220 and 5×220.
        $this->assertStringContainsString('440.00', $body);
        $this->assertStringContainsString('1,100.00', $body);
    }

    public function test_the_item_area_is_ruled_by_its_columns_alone(): void
    {
        $this->ris('RIS-2026-00080', '2026-08-05', [
            ['supply' => $this->supply(['unit_cost' => 220]), 'quantity' => 2],
        ]);

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->getContent();

        $table = substr($html, (int) strpos($html, 'doc-grid doc-items'));
        $table = substr($table, 0, (int) strpos($table, '</table>'));

        // Each column keeps the share of the width the official sheet gives
        // it, and the description column takes the balance between them. The
        // number column is the one the form is opened out for: a number the
        // system prints is always fourteen characters — "RIS-2026-00002" —
        // which is wider than the 12 per cent the sheet rules for one written
        // in by hand, so the end-user beside it gives up the width instead.
        $this->assertStringContainsString('style="width:14.5%;">RIS NO.', $table);
        $this->assertStringContainsString('style="width:11.5%;">End-User', $table);
        $this->assertStringContainsString('style="width:36%;">Item / Description', $table);
        $this->assertStringContainsString('style="width:7%;">UNIT', $table);
        $this->assertStringContainsString('style="width:7.5%;">QNTY', $table);
        $this->assertStringContainsString('style="width:9.5%;">UNIT COST', $table);
        $this->assertStringContainsString('style="width:14%;">AMOUNT', $table);

        // The entries are written on the columns alone — none of them carries a
        // rule across — and the list closes on the "Nothing Follows" line
        // rather than on a run of empty ruled lines below it; that closing line
        // is the block's foot, the rule the recapitulation opens with, so the
        // two tables share a single line.
        $this->assertStringContainsString('<tr class="doc-noline">', $table);
        $this->assertStringContainsString('.doc-items tbody tr.doc-noline td { border-top: none; border-bottom: none; }', $html);
        $this->assertStringContainsString('.doc-items tbody tr.doc-foot td { border-bottom: 1px solid #000; }', $html);
        $this->assertStringContainsString('<tr class="doc-noline doc-foot">', $table);
        // The strip the block opens beside carries on up into the list, and the
        // line that closes the entries runs out across it to the sheet's left
        // edge: the number column is closed at the foot like the columns beside
        // it, and the verticals down either side of it meet a rule rather than
        // ending in the air. It is the only line drawn across the strip — the
        // entries above carry none, and the block below gives up its own row
        // rules there.
        $this->assertMatchesRegularExpression('/<tr class="doc-noline doc-foot">\s*<td class="doc-strip"><\/td>/', $table);
        $this->assertStringContainsString('.doc-grid tbody tr.doc-foot td.doc-strip { border-bottom: 1px solid #000; }', $html);
        $this->assertStringNotContainsString('<tr class="doc-blank doc-noline">', $table);
        $this->assertStringNotContainsString('<tr class="doc-blank', $table);
        $this->assertSame(1, substr_count($table, 'doc-foot'));
        $this->assertStringNotContainsString('.doc-items tbody tr:last-child td', $html);

        // An empty report stands its message on its own such line, and the
        // block's foot line closes it there too.
        $empty = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=2&year=2026')
            ->assertOk()
            ->getContent();
        $emptyTable = substr($empty, (int) strpos($empty, 'doc-grid doc-items'));
        $emptyTable = substr($emptyTable, 0, (int) strpos($emptyTable, '</table>'));
        $this->assertMatchesRegularExpression(
            '/<tr class="doc-noline doc-foot">\s*<td colspan="7" class="doc-c doc-i"[^>]*>No records found for the selected period\./',
            $emptyTable
        );
    }

    /**
     * The recapitulation sits on the official sheet's own columns: the stock
     * number, the quantity, the open middle, the unit cost read across two of
     * them, and the two money columns — each heading held inside its column
     * rather than spread across the block.
     */
    public function test_the_recapitulation_keeps_the_official_sheets_columns(): void
    {
        $this->ris('RIS-2026-00080', '2026-08-05', [
            ['supply' => $this->supply(['unit_cost' => 220]), 'quantity' => 2],
        ]);

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->getContent();

        $table = substr($html, (int) strpos($html, 'doc-grid doc-recap'));
        $table = substr($table, 0, (int) strpos($table, '</table>'));

        // The band the form prints across the head of the block: the word once
        // over the stock and its quantity, once over the cost columns, and the
        // rest of the band left open. It stands beside the stock number's
        // column rather than under it — the strip the list's number column
        // leaves there carries no rule, so the block opens at the stock number
        // — and no line runs between the band's two runs, the cells that meet
        // there both dropping their border.
        $this->assertMatchesRegularExpression(
            '/<td class="doc-strip"><\/td>\s*<td colspan="3" class="band doc-seam-l doc-seam-r"><div class="band-issue">Recapitulation<\/div><\/td>/',
            $table
        );
        $this->assertStringContainsString('<td class="band doc-seam-l"><div class="band-cost">Recapitulation</div></td>', $table);
        $this->assertStringContainsString('<td colspan="2" class="band"></td>', $table);
        $this->assertSame(2, substr_count($table, 'Recapitulation'));

        // Every heading keeps the share of the width the sheet gives it: the
        // stock number under the end-user column — opened out with the list's
        // own number column, so the two blocks still rule the same lines — and
        // the quantity at the head of the description column, the two money
        // columns under the headings that name them.
        $this->assertStringContainsString('style="width:14.5%;"></th>', $table);
        $this->assertStringContainsString('style="width:11.5%;">STOCK NO:', $table);

        // The first of those shares is the strip the list's own number column
        // leaves beside the block. It keeps the width — so every heading stays
        // over the column the entries above were written on, and the block's
        // first rule meets the list's as one line — and carries the rule that
        // closes the block's left side: the vertical the list parts that column
        // with runs straight on down it. What it gives up are the row rules
        // that would cut it into a column of empty little boxes.
        $this->assertStringContainsString('<th class="doc-c doc-strip" style="width:14.5%;"></th>', $table);
        $this->assertStringContainsString('<th class="doc-c doc-nowrap doc-seam-l" style="width:11.5%;">STOCK NO:</th>', $table);
        $this->assertStringContainsString('.doc-recap .doc-strip { border-bottom: none; }', $html);

        // One line of the strip is the sheet's, not the strip's: the rule that
        // ends the block runs out to the sheet's left edge where the form draws
        // it, so the corner the strip leaves is closed by it. Only the line
        // that ends the block carries it.
        $this->assertStringContainsString('<td class="doc-strip doc-strip-end"></td>', $table);
        $this->assertStringContainsString('.doc-recap .doc-strip-end { border-bottom: 1px solid #000; }', $html);
        $this->assertSame(1, substr_count($table, 'doc-strip-end'));
        $this->assertStringContainsString('style="width:10%;">QNTY', $table);
        $this->assertStringContainsString('style="width:26%;"></th>', $table);
        $this->assertStringContainsString('style="width:14.5%;">UNIT COST', $table);
        $this->assertStringContainsString('style="width:9.5%;">TOTAL COST', $table);
        $this->assertStringContainsString('style="width:14%;">AMOUNT', $table);

        // Two of the block's lines are not ruled on the form: no line stands
        // between the quantity heading and the open middle, and TOTAL COST and
        // AMOUNT share one cell below the headings that name them.
        $this->assertStringContainsString('<th class="doc-c doc-nowrap doc-seam-r" style="width:10%;">QNTY</th>', $table);
        $this->assertStringContainsString('<th class="doc-c doc-seam-l" style="width:26%;"></th>', $table);
        $this->assertStringContainsString('.doc-recap .doc-seam-r { border-right: none; }', $html);
        $this->assertStringContainsString('.doc-recap .doc-seam-l { border-left: none; }', $html);

        // The money columns are narrow, so their headings are held on one line
        // and run edge to edge in them: "TOTAL COST" never breaks into two,
        // and side padding there would widen the column past the sheet's line.
        $this->assertStringContainsString('<th class="doc-c doc-nowrap" style="width:9.5%;">TOTAL COST</th>', $table);
        $this->assertStringContainsString('<th class="doc-c doc-nowrap" style="width:14%;">AMOUNT</th>', $table);
        $this->assertStringContainsString('<th class="doc-c doc-nowrap" style="width:14.5%;">UNIT COST</th>', $table);
        $this->assertStringContainsString('.doc-recap th { padding: 2px 0; }', $html);
        $this->assertStringContainsString('.doc-recap th { font-weight: normal; }', $html);

        // The line between the cost column and the total cost holds the longest
        // word on the sheet: at 9.5 per cent "TOTAL COST" sits inside its
        // column with the ruling clear of the letters, at the sheet's own size.
        // The column is widened for it rather than the word set smaller — the
        // cost column gives up the width, and the list above gives up the same
        // tenth from its quantity so that the line the two blocks draw at 76.5
        // per cent is one line.
        $this->assertStringNotContainsString('doc-tight', $html);

        $ics = $this->actingAs($this->admin)
            ->get('/admin/reports/ics?equipment[]='.$this->equipment()->id)
            ->assertOk()
            ->getContent();
        $icsRecap = substr($ics, (int) strpos($ics, 'doc-grid doc-recap'));
        $icsRecap = substr($icsRecap, 0, (int) strpos($icsRecap, '</table>'));
        $this->assertStringContainsString('<th class="doc-c doc-nowrap" style="width:11%;">TOTAL COST</th>', $icsRecap);

        // The band's words stand a shade larger than the headings — the first
        // centring in the cells it names by the cell it sits in alone, with no
        // box of its own, and the second hanging in a box drawn back from the
        // cost column's centre the way the form prints it. The band is the
        // form's label over the block rather than a heading, so it carries no
        // bold face: the headings below it are the bold printing.
        $this->assertStringContainsString('.doc-grid .band { text-align: center; }', $html);
        $this->assertStringNotContainsString('.doc-grid .band { font-weight: bold', $html);
        $this->assertStringContainsString('.doc-recap .band {', $html);
        $this->assertStringContainsString('white-space: nowrap;', $html);
        $this->assertStringContainsString('font-size: 16px;', $html);
        $this->assertStringNotContainsString('.band-issue {', $html);
        $this->assertStringContainsString('.doc-recap .band-cost {', $html);
        $this->assertStringContainsString('margin-left: -20.4pt;', $html);

        // A month with nothing issued still prints the block's own ruling —
        // and keeps it, too: unlike the open space in the item area above, the
        // empty row here is drawn on the columns, so the accounting unit fills
        // the very cells the figures would have sat in.
        $blank = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=7&year=2026')
            ->assertOk()
            ->getContent();
        $blankTable = substr($blank, (int) strpos($blank, 'doc-grid doc-recap'));
        $blankTable = substr($blankTable, 0, (int) strpos($blankTable, '</table>'));

        $this->assertStringContainsString('<tr class="doc-blank">', $blankTable);
        $this->assertStringNotContainsString('doc-noline', $blankTable);
    }

    /**
     * The item list and the recapitulation are two tables, but one sheet is
     * ruled through both of them: the verticals of the list run on down the
     * block that closes it, with no step where the two meet.
     */
    public function test_the_ris_list_and_the_recapitulation_stand_on_one_grid(): void
    {
        $this->ris('RIS-2026-00080', '2026-08-05', [
            ['supply' => $this->supply(['unit_cost' => 220]), 'quantity' => 2],
        ]);

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->getContent();

        $list = substr($html, (int) strpos($html, 'doc-grid doc-items'));
        $list = substr($list, 0, (int) strpos($list, '</table>'));
        $recap = substr($html, (int) strpos($html, 'doc-grid doc-recap'));
        $recap = substr($recap, 0, (int) strpos($recap, '</table>'));

        // Both blocks are held to their columns: a description that runs long
        // — or a heading a shade wider than its column — may not widen it and
        // walk the ruling off the line the other block draws.
        $this->assertStringContainsString('doc-grid doc-items doc-fixed no-top', $list);
        $this->assertStringContainsString('doc-grid doc-recap doc-fixed no-top', $recap);

        // The shares are named on a line of no height at the head of each
        // block, where they sit on cells with no border and no padding, so
        // what they measure is the column rather than the box it is printed
        // in. Written on the headings instead, the band the form rules over
        // them would be measured against them with the little they carry.
        $shares = function (string $table): array {
            preg_match('/<tr class="doc-measure">(.*?)<\/tr>/s', $table, $row);
            preg_match_all('/width:([\d.]+)%/', $row[1] ?? '', $widths);

            return $widths[1];
        };

        $this->assertSame(['14.5', '11.5', '36', '7', '7.5', '9.5', '14'], $shares($list));

        // Every share is measured from the one before it, so the lines the two
        // blocks rule are where their shares add up. The two meet on all of
        // them but two: the form's block runs the quantity heading and the open
        // middle under it as one cell, so the block draws no line at 36 where
        // the list draws one — and the list rules a UNIT column at 69 that the
        // block does not reach, standing open for the accounting unit to write
        // in. Every other line is the same line.
        $lines = function (string $table) use ($shares): array {
            $running = 0;

            return array_map(function ($share) use (&$running) {
                return $running = round($running + (float) $share, 1);
            }, $shares($table));
        };

        $this->assertSame(
            [14.5, 26.0, 62.0, 76.5, 86.0, 100.0],
            array_values(array_intersect($lines($list), $lines($recap)))
        );
        $this->assertSame([36.0], array_values(array_diff($lines($recap), $lines($list))));
        $this->assertSame([69.0], array_values(array_diff($lines($list), $lines($recap))));
        $this->assertStringContainsString('.doc-grid .doc-measure td {', $html);
        $this->assertStringContainsString('height: 0;', $html);

        // The banner over the two halves of the sheet is no longer the first
        // row of the list: it stands in a table of its own above it, so the
        // list opens with the row that names every column. Its own columns are
        // named the same way, which is how its line between the two halves —
        // the end of the supply unit's run — lands on the list's own line and
        // runs on down it.
        $this->assertStringContainsString('doc-grid doc-banner doc-fixed no-top', $html);
        $this->assertMatchesRegularExpression(
            '/doc-grid doc-banner doc-fixed no-top">\s*<thead>\s*<tr class="doc-measure">.*?<tr>\s*<th colspan="5" class="doc-i">To be filled up in the Supply and Property Unit<\/th>\s*<th colspan="2" class="doc-i">To be Filled up by Accounting Unit<\/th>/s',
            $html
        );
        $this->assertLessThan(
            (int) strpos($html, 'doc-grid doc-items'),
            (int) strpos($html, 'doc-grid doc-banner'),
            'the banner stands above the list'
        );
    }

    /**
     * The sheet's own printing stands bold — its headings, its labels, the
     * wording it carries and the item's own name — while everything read off
     * the record is written into it at normal weight.
     */
    public function test_the_printing_is_bold_and_what_is_written_into_the_form_is_not(): void
    {
        $this->ris('RIS-2026-00080', '2026-08-05', [
            ['supply' => $this->supply(['item_code' => 'SUP-00080', 'unit_cost' => 220]), 'quantity' => 2],
        ]);

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&show_costs=1&show_stock=1&show_total=1')
            ->assertOk()
            ->getContent();

        $items = substr($html, (int) strpos($html, 'doc-grid doc-items'));
        $items = substr($items, 0, (int) strpos($items, '</table>'));
        $recap = substr($html, (int) strpos($html, 'doc-grid doc-recap'));
        $recap = substr($recap, 0, (int) strpos($recap, '</table>'));

        // Printed on the form: the headings, the labels, the wording, and the
        // name of the item itself.
        $this->assertStringContainsString('.doc-grid thead th {', $html);
        $this->assertStringContainsString('.doc-grid .lbl { font-weight: bold;', $html);
        // The recapitulation's headings are not: the block belongs to the
        // accounting unit, and its words stand at the sheet's own weight with
        // the band and the figures they head.
        $this->assertStringContainsString('.doc-recap th { font-weight: normal; }', $html);
        $this->assertStringContainsString('.doc-name { font-weight: bold; }', $html);
        $this->assertStringContainsString('<div class="doc-name">Bond Paper A4', $items);
        $this->assertStringContainsString('class="doc-cert-post"', $html);
        $this->assertStringContainsString('.doc-grid.doc-cert .doc-cert-statement {', $html);

        // The certification is written into and signed, and only the officer's
        // name stands bold on its rule, the way the printed name does on the
        // signing lines below it. The statement the form carries, the caption
        // the offices post under and the two titles below stay at the sheet's
        // own weight, like the entries and figures above them.
        $cert = substr($html, (int) strpos($html, '/* ---- Certification'));
        $cert = substr($cert, 0, (int) strpos($cert, '/* ---- Signatures'));
        $nameRule = substr($cert, (int) strpos($cert, '.doc-grid.doc-cert .doc-cert-name {'));
        $nameRule = substr($nameRule, 0, (int) strpos($nameRule, '}') + 1);
        $this->assertStringContainsString('font-weight: bold', $nameRule);
        $this->assertStringNotContainsString('font-weight: bold', str_replace($nameRule, '', $cert));
        $this->assertStringNotContainsString('doc-cert-name doc-c doc-b', $html);
        $this->assertStringContainsString('<td class="doc-cert-name doc-c"', $html);

        // Written in: the number, the end-user, the unit, the quantity and the
        // figures, none of which claims a bold face of its own.
        $this->assertStringNotContainsString(' doc-b"', $items);
        $this->assertStringContainsString('<td class="doc-nowrap">RIS-2026-00080</td>', $items);
        $this->assertStringContainsString('<td class="doc-c">ream</td>', $items);
        $this->assertStringContainsString('<td class="doc-c">2</td>', $items);
        $this->assertStringContainsString('<td class="doc-r">220.00</td>', $items);
        $this->assertStringContainsString('<td class="doc-r">440.00</td>', $items);

        // The recapitulation reads the same way, its totals line included: the
        // word TOTAL and the figures it adds up are written into the form, so
        // they stand at the sheet's own weight, and the row is marked by its
        // class alone.
        $this->assertStringContainsString('<td class="doc-c doc-nowrap doc-seam-l">SUP-00080</td>', $recap);
        $this->assertStringContainsString('<td class="doc-c doc-seam-r">2</td>', $recap);
        $this->assertStringContainsString('<td class="doc-r doc-total doc-seam-l">TOTAL</td>', $recap);
        $this->assertStringNotContainsString('.doc-total', $html);
    }

    public function test_the_ris_report_lists_each_issued_line_with_a_computed_amount(): void
    {
        $supply = $this->supply(['unit_cost' => 220]);

        // Ten requested, five handed over: the report prints what was issued.
        $this->ris('RIS-2026-00001', '2026-08-05', [
            ['supply' => $supply, 'quantity' => 10, 'issued_quantity' => 5],
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&show_costs=1')
            ->assertOk()
            ->assertSee('RIS-2026-00001')
            ->assertSee('Bond Paper A4')
            ->assertSee('ream')
            // 5 × 220.00, computed rather than read from anywhere.
            ->assertSee('1,100.00');
    }    /**
     * The printed form keeps the money columns for the accounting unit to fill
     * in by hand: the report's own figures go into them only when the box is
     * ticked. The columns themselves never leave the sheet.
     */
    public function test_the_money_columns_stand_blank_until_the_figures_are_asked_for(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(['unit_cost' => 220]), 'quantity' => 5]]);

        // The box sits in the form.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->assertSee('name="show_costs"', false);

        // Untouched, the sheet stands with 5 × 220.00 and its totals left to
        // the pen — but every column of the money group is still on it, ruled
        // and headed for the figures to be written into.
        $untouched = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->assertSee('Bond Paper A4')
            ->assertDontSee('1,100.00')
            ->assertDontSee('220.00')
            ->getContent();

        $list = substr($untouched, (int) strpos($untouched, 'doc-grid doc-items'));
        $list = substr($list, 0, (int) strpos($list, '</table>'));
        $recap = substr($untouched, (int) strpos($untouched, 'doc-grid doc-recap'));
        $recap = substr($recap, 0, (int) strpos($recap, '</table>'));

        // The list keeps UNIT COST and AMOUNT over their shares of the width,
        // on the same grid as the recapitulation below it.
        $this->assertStringContainsString('style="width:9.5%;">UNIT COST</th>', $list);
        $this->assertStringContainsString('style="width:14%;">AMOUNT</th>', $list);

        // The block keeps its headings — the money columns are named on the
        // form even when nothing is printed in them — and both band words, the
        // second one heading them.
        $this->assertStringContainsString('<th class="doc-c doc-nowrap" style="width:14.5%;">UNIT COST</th>', $recap);
        $this->assertStringContainsString('<th class="doc-c doc-nowrap" style="width:9.5%;">TOTAL COST</th>', $recap);
        $this->assertStringContainsString('<th class="doc-c doc-nowrap" style="width:14%;">AMOUNT</th>', $recap);
        $this->assertSame(2, substr_count($recap, 'Recapitulation'));

        // Nor does the accounting unit's half of the banner go with them: the
        // form says which half of it that office fills in.
        $this->assertStringContainsString('To be filled up in the Supply and Property Unit', $untouched);
        $this->assertStringContainsString('To be Filled up by Accounting Unit', $untouched);

        // Ticked, the very same sheet carries the figures.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&show_costs=1')
            ->assertOk()
            ->assertSee('220.00')
            ->assertSee('1,100.00');

        // The sheet follows whatever the form last submitted.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&show_costs=1')
            ->assertOk()
            ->assertSee('1,100.00');
    }

    /**
     * The stock number and the quantity are the report's own record, but they
     * are what the report counted rather than the form's own printing, so the
     * two columns stand with the form and blank until the officer asks for the
     * figures, as the money columns do. Either way the columns stay — heading,
     * ruling and width are the form's, and it is only the figures that are
     * printed or withheld.
     */
    public function test_the_stock_number_and_quantity_are_printed_only_when_they_are_asked_for(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 5]]);

        // The box sits in the form, beside the money one, with the hidden
        // field that carries the "no" an unticked box cannot send itself.
        $panel = $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->assertSee('name="show_stock"', false)
            ->assertSee('name="show_stock" value="0"', false)
            ->assertSee('Show stock no. &amp; quantity', false)
            ->getContent();

        // It arrives unticked: the officer asks for the figures rather than
        // having to clear a box that was filled in for them.
        $this->assertStringNotContainsString('name="show_stock" value="1" checked', $panel);

        // Ticked, the box stays ticked on the page that comes back.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&show_stock=1')
            ->assertOk()
            ->assertSee('name="show_stock" value="1" checked', false);

        // Untouched with the figures beside them printed, the block keeps both
        // headings and its ruling, the line stays standing so nothing on the
        // sheet moves, and the total row stands where it is asked for — only
        // the stock number and quantity stay away.
        $held = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&show_costs=1&show_total=1')
            ->assertOk()
            ->assertSee('STOCK NO:')
            ->assertDontSee('SUP-00001')
            ->getContent();

        $heldRecap = $this->recapOf($held);

        $this->assertStringContainsString('<th class="doc-c doc-nowrap doc-seam-l" style="width:11.5%;">STOCK NO:</th>', $heldRecap);
        $this->assertStringContainsString('<th class="doc-c doc-nowrap doc-seam-r" style="width:10%;">QNTY</th>', $heldRecap);
        // The line keeps a written line's height rather than going flat: the
        // row is one of the form's, so a withheld figure leaves it empty but
        // standing, the way the list's blank lines do.
        $this->assertStringContainsString('<td class="doc-c doc-nowrap doc-seam-l">&nbsp;</td>', $heldRecap);
        $this->assertStringContainsString('<td class="doc-c doc-seam-r">&nbsp;</td>', $heldRecap);
        $this->assertStringContainsString('<td class="doc-c doc-total doc-seam-r"></td>', $heldRecap);
        $this->assertStringContainsString('>TOTAL</td>', $heldRecap);

        // Asked for, the block carries the stock number and the quantity it
        // counted, and the total row the run's own quantity.
        $shown = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&show_stock=1&show_total=1')
            ->assertOk()
            ->assertSee('SUP-00001')
            ->getContent();

        $recap = $this->recapOf($shown);

        $this->assertStringContainsString('<td class="doc-c doc-nowrap doc-seam-l">SUP-00001</td>', $recap);
        $this->assertStringContainsString('<td class="doc-c doc-seam-r">5</td>', $recap);
        $this->assertStringContainsString('<td class="doc-c doc-total doc-seam-r">5</td>', $recap);

        // The monthly report takes the same filters as the RIS, and the same
        // way round: asked for, every report of its run prints the figures.
        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print?month=8&year=2026&document_type=ris&show_stock=1')
            ->assertOk()
            ->assertSee('STOCK NO:')
            ->assertSee('SUP-00001');
    }

    /**
     * The line that closes the recapitulation stands with it, and can be left
     * off: with everything above it withheld there is nothing left for it to
     * total. The headings and the ruled lines stay either way.
     */
    public function test_the_total_line_can_be_left_off_the_recapitulation(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 5]]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->assertSee('name="show_total"', false);

        // Left off — the default, like the figures above it — the row goes and
        // the block closes with the lines above it.
        $off = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&show_total=0&show_stock=0')
            ->assertOk()
            ->getContent();

        $recap = $this->recapOf($off);

        // Asked for, the block closes with its total line.
        $on = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&show_total=1')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>TOTAL</td>', $this->recapOf($on));
        // And a report left untouched prints neither the line nor the figures:
        // the block stands as the blank one the accounting unit writes into.
        $untouched = $this->recapOf($this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->getContent());

        $this->assertStringNotContainsString('>TOTAL</td>', $untouched);
        $this->assertSame(1, substr_count($untouched, 'doc-blank'));
        $this->assertStringContainsString('TOTAL COST', $untouched);

        $this->assertStringNotContainsString('>TOTAL</td>', $recap);
        // The headings and the ruling stay: with every figure withheld the
        // block closes on the single blank line a blank form carries, and that
        // line is the one that carries the sheet's closing rule out to the left
        // edge — the block still ends on a ruled line with its total off.
        $this->assertStringContainsString('<td class="doc-strip doc-strip-end"></td>', $recap);
        $this->assertStringContainsString('STOCK NO:', $recap);
        $this->assertStringContainsString('TOTAL COST', $recap);
        $this->assertSame(1, substr_count($recap, 'doc-blank'));
        $this->assertSame(2, substr_count($recap, 'Recapitulation'));

        // Asked for, the figures stand and the block still ends on a ruled
        // line: the total line gone, the last figure row carries the rule out
        // to the sheet's left edge in its place.
        $figures = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&show_total=0&show_stock=1')
            ->assertOk()
            ->getContent();

        $block = $this->recapOf($figures);

        $this->assertStringNotContainsString('>TOTAL</td>', $block);
        $this->assertStringContainsString('<td class="doc-c doc-nowrap doc-seam-l">SUP-00001</td>', $block);
        $this->assertSame(1, substr_count($block, 'doc-strip-end'));

        // The monthly report takes the same filter on every sheet.
        $monthly = $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print?month=8&year=2026&document_type=ris&show_total=0')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('>TOTAL</td>', $this->recapOf($monthly));
    }

    /** The first recapitulation block of a rendered report. */
    private function recapOf(string $html): string
    {
        $recap = substr($html, (int) strpos($html, 'doc-grid doc-recap'));

        return substr($recap, 0, (int) strpos($recap, '</table>'));
    }

    /**
     * Everything the recapitulation prints stands behind the one control that
     * names it — three display switches for one block would otherwise take
     * three boxes of the filter line — and the fields inside are sent with the
     * form whether the menu is open or shut.
     */
    public function test_the_recapitulation_choices_stand_behind_one_control(): void
    {
        foreach (['/admin/reports/ris?month=8&year=2026', '/admin/reports/monthly?month=8&year=2026'] as $url) {
            $html = $this->actingAs($this->admin)
                ->get($url)
                ->assertOk()
                ->assertSee('data-menu-trigger', false)
                ->getContent();

            $triggerAt = (int) strpos($html, 'data-menu-trigger');
            $panelAt = (int) strpos($html, 'data-menu-panel');

            $this->assertLessThan($panelAt, $triggerAt);
            // The control names the block it sets, as the sheet names it.
            $this->assertStringContainsString('Recapitulation', substr($html, $triggerAt, $panelAt - $triggerAt));

            // The three choices stand inside the panel it opens.
            $panel = substr($html, $panelAt, (int) strpos($html, '</div>', $panelAt) - $panelAt);

            $this->assertStringContainsString('name="show_costs"', $panel);
            $this->assertStringContainsString('name="show_stock"', $panel);
            $this->assertStringContainsString('name="show_total"', $panel);
        }
    }

    /**
     * A recapitulation with nothing printed in it is ruled as one blank line —
     * the same line a blank form carries. One empty row is as empty as the
     * next, so a second would only say how many stock numbers the run happened
     * to hold; an ICS sheet holding two of them reads the same as the RIS
     * sheet holding one, which is what the offices compare.
     */
    public function test_a_recapitulation_with_nothing_printed_in_it_is_ruled_as_one_line(): void
    {
        $maria = $this->teacher('Maria', 'Santos');
        $this->ics('ICS-2026-00001', $this->equipment(), ['teacher_id' => $maria->id]);
        $this->ics('ICS-2026-00002', $this->equipment([
            'item_code' => 'EQP-00002',
            'property_number' => 'PPE-2026-0002',
            'name' => 'Epson LCD Projector',
        ]), ['teacher_id' => $maria->id]);

        // Asked for its own figures, the sheet's block stands a line deep for
        // each stock number the custodian holds.
        $shown = $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print?month=8&year=2026&document_type=ics&show_stock=1')
            ->assertOk()
            ->getContent();

        $recap = $this->recapOf($shown);

        $this->assertStringContainsString('EQP-00001', $recap);
        $this->assertStringContainsString('EQP-00002', $recap);
        $this->assertSame(0, substr_count($recap, 'doc-blank'));

        // With the stock number and quantity withheld the run is ruled as one
        // blank line instead: no per-entry rows, and nothing on the form that
        // would tell one of those rows from the next.
        $withheld = $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print?month=8&year=2026&document_type=ics&show_stock=0')
            ->assertOk()
            ->getContent();

        $blank = $this->recapOf($withheld);

        $this->assertSame(1, substr_count($blank, 'doc-blank'));
        $this->assertStringNotContainsString('&nbsp;', $blank);
        $this->assertStringNotContainsString('EQP-00001', $blank);
        // The block still names its columns and keeps its band.
        $this->assertStringContainsString('STOCK NO:', $blank);
        $this->assertSame(2, substr_count($blank, 'Recapitulation'));
    }

    /**
     * The number at the head of the sheet is the office's to write. The form
     * arrives holding the number the records give the report, and the sheet
     * prints whatever it is left holding — an emptied box being the way back to
     * the report's own numbering, never a blank line where the number stands.
     */
    public function test_the_report_number_can_be_written_over_by_hand(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        // On the working page the number is a field laid in the line the sheet
        // draws for it, and it is sent with the form the filter line sits in
        // even though it stands outside it, in the document below. A page that
        // has only been opened holds nothing there: the number is the report's
        // own to give, and generating the report is what gives it.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->assertSee('name="report_no[2026-08]" form="report-form"', false)
            ->assertSee('value=""', false)
            ->assertSee('data-no-entry', false);

        // Generated, the number is filled in from the records the report covers.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&generated=1')
            ->assertOk()
            ->assertSee('value="2026-08-001"', false);

        // The printed sheet is always numbered — a paper cannot be filed without
        // one — and is handed it as the written line it looks like, since a
        // paper cannot be typed on either.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->assertDontSee('name="report_no"', false)
            ->assertSee('<span class="doc-sign-line">2026-08-001</span>', false);

        // Typed over, the sheet carries what was written and no longer the
        // number read off the records.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&report_no=2026-08-086')
            ->assertOk()
            ->assertSee('2026-08-086')
            ->assertDontSee('2026-08-001');

        // The PDF is cut from the same fields as the sheet.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/pdf?month=8&year=2026&report_no=2026-08-086')
            ->assertOk();

        // Emptied, the report numbers itself again.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&report_no=')
            ->assertOk()
            ->assertSee('2026-08-001');

        // And on the working page an emptied line is filled in again by the
        // next generation, rather than left standing blank.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&generated=1&report_no=')
            ->assertOk()
            ->assertSee('value="2026-08-001"', false);
    }

    /**
     * A number written by hand belongs to the month it was written in. Typing
     * over August's sheet does not put August's number on September's, and
     * August's is still there when August is the month being reported again —
     * the form carries it on, filed under its own month, rather than losing it
     * or lending it to the next report.
     */
    public function test_a_written_number_belongs_to_the_month_it_was_written_in(): void
    {
        $supply = $this->supply();
        $maria = $this->teacher('Maria', 'Santos');

        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $supply, 'quantity' => 2]], ['teacher_id' => $maria->id]);
        $this->ris('RIS-2026-00090', '2026-09-06', [['supply' => $supply, 'quantity' => 3]], ['teacher_id' => $maria->id]);

        // August's sheet, written over by hand: the field names the month the
        // number was written for.
        $august = $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&generated=1&report_no[2026-08]=2026-08-777')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="report_no[2026-08]" form="report-form"', $august);
        $this->assertStringContainsString('value="2026-08-777"', $august);

        // September, asked for next with August's number still in the form, is
        // numbered from its own papers: August's number is not lent to it, and
        // it is not lost either — the form carries it on, filed under August.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=9&year=2026&generated=1&report_no[2026-08]=2026-08-777')
            ->assertOk()
            ->assertSee('name="report_no[2026-09]" form="report-form"', false)
            ->assertSee('value="2026-09-090"', false)
            ->assertSee('<input type="hidden" name="report_no[2026-08]" value="2026-08-777">', false);

        // And August's sheet carries its own number again when August comes
        // back, which is what the carried field is for.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&generated=1&report_no[2026-08]=2026-08-777')
            ->assertOk()
            ->assertSee('value="2026-08-777"', false)
            ->assertDontSee('value="2026-08-080"', false);
    }

    /**
     * The monthly report is numbered by the report itself, page by page, and
     * the office has nothing to write it into.
     */
    public function test_the_monthly_report_numbers_its_pages_and_offers_nothing_to_write_in(): void
    {
        $supply = $this->supply();
        $maria = $this->teacher('Maria', 'Santos');

        $this->ris('RIS-2026-00080', '2026-01-05', [['supply' => $supply, 'quantity' => 2]], ['teacher_id' => $maria->id]);
        $this->ris('RIS-2026-00090', '2026-02-06', [['supply' => $supply, 'quantity' => 3]], ['teacher_id' => $maria->id]);

        // The pages are numbered in one unbroken run: January's sheet is the
        // first page of it, and February carries on from where January's
        // printing ended rather than starting over.
        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly?month=1&year=2026&document_type=ris')
            ->assertOk()
            ->assertSee('<span class="doc-sign-line">2026-01-01</span>', false)
            ->assertDontSee('name="sheet_no', false);

        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly?month=2&year=2026&document_type=ris')
            ->assertOk()
            ->assertSee('<span class="doc-sign-line">2026-02-02</span>', false)
            ->assertDontSee('<span class="doc-sign-line">2026-02-01</span>', false);

        // A number handed in by hand no longer reaches the sheet, and neither
        // does the field it used to be written into.
        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print?month=1&year=2026&document_type=ris&sheet_no[0]=2026-01-777')
            ->assertOk()
            ->assertSee('<span class="doc-sign-line">2026-01-01</span>', false)
            ->assertDontSee('<span class="doc-sign-line">2026-01-777</span>', false);
    }

    /**
     * The papers are numbered apart: the RIS pages count RIS sheets and the ICS
     * pages count ICS sheets, so a month opens both at 01, and each carries on
     * along its own run. One paper's printing never pushes the other along.
     */
    public function test_the_ris_and_ics_pages_are_numbered_apart(): void
    {
        $supply = $this->supply();
        $equipment = $this->equipment();
        $maria = $this->teacher('Maria', 'Santos');

        $this->ris('RIS-2026-00080', '2026-01-05', [['supply' => $supply, 'quantity' => 2]], ['teacher_id' => $maria->id]);
        $this->ris('RIS-2026-00090', '2026-02-06', [['supply' => $supply, 'quantity' => 3]], ['teacher_id' => $maria->id]);
        $this->ics('ICS-2026-00010', $equipment, ['document_date' => '2026-01-07']);
        $this->ics('ICS-2026-00020', $equipment, ['document_date' => '2026-02-09']);

        // January: both papers open at 01, so the month holds two first pages.
        $january = $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print?month=1&year=2026&document_type=all')
            ->assertOk();

        $this->assertSame(2, substr_count($january->getContent(), '<span class="doc-sign-line">2026-01-01</span>'));
        $this->assertStringNotContainsString('2026-01-02', $january->getContent());

        // February: each carries on along its own run, and neither has been
        // pushed along by the other's pages.
        $february = $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print?month=2&year=2026&document_type=all')
            ->assertOk();

        $this->assertSame(2, substr_count($february->getContent(), '<span class="doc-sign-line">2026-02-02</span>'));
        $this->assertStringNotContainsString('2026-02-01', $february->getContent());

        // Asked for on its own, a paper still carries its own run: the RIS pages
        // are the first and second of the RIS sheets, not of the month's.
        $riskOnly = $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print?month=2&year=2026&document_type=ris')
            ->assertOk();

        $this->assertSame(1, substr_count($riskOnly->getContent(), '<span class="doc-sign-line">2026-02-02</span>'));
    }

    /**
     * The run is not started over by a new year either: a sheet printed in
     * January 2027 carries on from December 2026, and still names its own year
     * and month.
     */
    public function test_the_monthly_run_carries_on_across_the_new_year(): void
    {
        $supply = $this->supply();
        $maria = $this->teacher('Maria', 'Santos');

        // January and February 2026 hold a paper each, so the run stands at two
        // pages when they have been printed. The ten months between March and
        // December 2026 hold nothing, and print the blank sheet each month is
        // asked for, so each of them takes the next number of the run as well:
        // twelve pages stand before January 2027, whose sheet is the thirteenth.
        $this->ris('RIS-2026-00080', '2026-01-05', [['supply' => $supply, 'quantity' => 2]], ['teacher_id' => $maria->id]);
        $this->ris('RIS-2026-00090', '2026-02-06', [['supply' => $supply, 'quantity' => 3]], ['teacher_id' => $maria->id]);
        $this->ris('RIS-2027-00010', '2027-01-08', [['supply' => $supply, 'quantity' => 1]], ['teacher_id' => $maria->id]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly?month=12&year=2026&document_type=ris')
            ->assertOk()
            ->assertSee('<span class="doc-sign-line">2026-12-12</span>', false);

        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly?month=1&year=2027&document_type=ris')
            ->assertOk()
            ->assertSee('<span class="doc-sign-line">2027-01-13</span>', false)
            ->assertDontSee('<span class="doc-sign-line">2027-01-01</span>', false);
    }

    /**
     * The office holds its position on its own account and writes it once: every
     * sheet that office signs carries it — the "Received from — Position" line on
     * the PAR and the ICS, and the title under the certification on the RIS, the
     * monthly report and the custodian slips. An account that holds none signs
     * under the form's own wording, so a paper is never left without a position.
     */
    public function test_the_position_on_the_account_is_what_the_sheets_are_signed_under(): void
    {
        $supply = $this->supply();
        $maria = $this->teacher('Maria', 'Santos');

        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $supply, 'quantity' => 2]], ['teacher_id' => $maria->id]);

        $position = 'Supply Officer III (Supply Office Administrator)';

        // Nothing held on the account: the forms keep the wording they are
        // printed with.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->assertSee('Administrative Officer IV')
            ->assertDontSee($position);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ics')
            ->assertOk()
            ->assertSee('value="Administrative Officer I (Supply Officer I)"', false);

        // Written once on the profile, it stands wherever the form names the
        // office the supply officer signs for.
        $this->admin->update(['position' => $position]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->assertSee($position)
            ->assertDontSee('Administrative Officer IV');

        // The monthly report's sheets are signed under it too.
        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print?month=8&year=2026&document_type=ris')
            ->assertOk()
            ->assertSee($position)
            ->assertDontSee('Administrative Officer IV');

        // And the custodian slips, which are the same certification.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ics/print')
            ->assertOk()
            ->assertSee($position)
            ->assertDontSee('Administrative Officer IV');

        // The PAR and the ICS carry it on the "Received from" line, which the
        // generator now opens holding it rather than the form's own wording.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ics')
            ->assertOk()
            ->assertSee('name="received_from_position" value="'.$position.'"', false);

        $this->actingAs($this->admin)
            ->get('/admin/reports/par')
            ->assertOk()
            ->assertSee('name="received_from_position" value="'.$position.'"', false);
    }

    public function test_a_long_description_is_printed_in_full_and_keeps_its_lines(): void
    {
        $supply = $this->supply([
            'name' => 'Desktop Computer',
            'description' => "Processor: RYZEN 5 5600GT\nMemory (RAM): 8GB RAM DDR4\nStorage: 512GB M.2 NVME SSD\nOperating System: Windows 11 Pro",
            'unit' => 'pc',
            'unit_cost' => 35000,
        ]);

        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $supply, 'quantity' => 1]]);

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->getContent();

        // Nothing is truncated, and every line break is kept so the row expands.
        foreach (['Processor: RYZEN 5 5600GT', 'Memory (RAM): 8GB RAM DDR4', 'Storage: 512GB M.2 NVME SSD', 'Operating System: Windows 11 Pro'] as $line) {
            $this->assertStringContainsString($line, $html);
        }

        $this->assertStringContainsString('<br />', $html);
    }

    public function test_the_ris_report_filters_by_end_user_stock_no_and_category(): void
    {
        $maria = $this->teacher('Maria', 'Santos');
        $jose = $this->teacher('Jose', 'Cruz');

        $office = $this->supply();
        $ict = $this->supply(['item_code' => 'SUP-00077', 'name' => 'Mouse', 'category' => 'ICT Supplies', 'unit_cost' => 300]);

        $forMaria = $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $office, 'quantity' => 1]], ['teacher_id' => $maria->id]);
        $forJose = $this->ris('RIS-2026-00002', '2026-08-06', [['supply' => $ict, 'quantity' => 1]], ['teacher_id' => $jose->id]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&end_user='.$maria->id)
            ->assertOk()
            ->assertSee($forMaria->document_no)
            ->assertDontSee($forJose->document_no)
            ->assertSee('Maria Santos');

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&stock_no=SUP-00077')
            ->assertOk()
            ->assertSee($forJose->document_no)
            ->assertDontSee($forMaria->document_no);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&category=ICT+Supplies')
            ->assertOk()
            ->assertSee($forJose->document_no)
            ->assertDontSee($forMaria->document_no);
    }

    public function test_the_ris_recapitulation_totals_each_stock_number(): void
    {
        $paper = $this->supply(['item_code' => 'SUP-00001', 'unit_cost' => 200]);
        $pen = $this->supply(['item_code' => 'SUP-00002', 'name' => 'Ballpoint Pen', 'unit' => 'piece', 'unit_cost' => 10]);

        $this->ris('RIS-2026-00001', '2026-08-05', [
            ['supply' => $paper, 'quantity' => 3],
            ['supply' => $pen, 'quantity' => 40],
        ]);
        $this->ris('RIS-2026-00002', '2026-08-20', [
            ['supply' => $paper, 'quantity' => 2],
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026&show_costs=1&show_stock=1&show_total=1')
            ->assertOk()
            ->assertSee('Recapitulation')
            ->assertSee('SUP-00001')
            ->assertSee('SUP-00002')
            // Paper: 5 reams at 200.00 = 1,000.00; pens: 40 at 10.00 = 400.00.
            ->assertSee('1,000.00')
            ->assertSee('400.00')
            // Both stock numbers across the month, and the grand total.
            ->assertSee('1,400.00')
            ->assertSee('>45<', false);
    }

    public function test_the_ris_certification_and_signature_lines_are_printed(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]], [
            'issued_by' => $this->admin->id,
            'issued_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->assertSee('I HEREBY CERTIFY TO THE CORRECTNESS OF THE ABOVE INFORMATION')
            ->assertSee('Posted By/ Date:')
            ->assertSee('Administrative Officer IV')
            ->assertSee('Accounting Clerk')
            ->assertSee($this->admin->name);
    }

    /**
     * The form writes the supply officer's name on the rule the two offices
     * sign under, and each office's title under its own rule — the rules stop
     * where the form stops them instead of closing the whole block.
     */
    public function test_the_certification_writes_the_officer_on_the_signing_rule(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]], [
            'issued_by' => $this->admin->id,
            'issued_at' => now(),
        ]);

        $page = $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->getContent();

        $start = strpos($page, 'doc-grid doc-cert');
        $this->assertNotFalse($start, 'the report closes with a certification block');
        $certification = substr($page, $start, strpos($page, '</table>', $start) - $start);

        // The name is written above the rule, not in a row of its own. It is
        // escaped the way Blade escapes it — a name carrying an apostrophe
        // (D'Amore, O'Brien) is printed as an entity, not as itself.
        $this->assertMatchesRegularExpression(
            '/class="doc-cert-name[^"]*"[^>]*>'.preg_quote(e($this->admin->name), '/').'</',
            $certification,
        );
        // Both offices post a copy, and both sign under a rule of their own.
        $this->assertSame(2, substr_count($certification, 'Posted By/ Date:'));
        $this->assertSame(2, substr_count($certification, 'doc-cert-rule'));
        // The statement column is closed off from the posting block.
        $this->assertSame(1, substr_count($certification, 'doc-cert-statement'));
    }

    public function test_a_draft_ris_is_not_reported_as_issued(): void
    {
        $supply = $this->supply();

        $draft = $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $supply, 'quantity' => 1]], ['status' => 'draft']);
        $approved = $this->ris('RIS-2026-00002', '2026-08-06', [['supply' => $supply, 'quantity' => 1]], ['status' => 'approved']);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->assertDontSee($draft->document_no)
            ->assertSee($approved->document_no);
    }

    public function test_an_empty_month_says_there_are_no_records(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=9&year=2026')
            ->assertOk()
            ->assertSee('No records found for the selected period.')
            ->assertDontSee('RIS-2026-00001');
    }

    public function test_an_impossible_period_is_clamped_instead_of_breaking(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=13&year=2026')
            ->assertOk()
            ->assertSee(strtoupper(now()->format('F')));

        // A year off the calendar falls back to the current one; the month
        // that was asked for is kept.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=99999')
            ->assertOk()
            ->assertSee('AUGUST 01-31');
    }

    /* ------------------------------------------------------------------ */
    /* Monthly Report                                                      */
    /* ------------------------------------------------------------------ */

    public function test_the_monthly_report_is_offered_beside_the_other_official_documents(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reports/par-ics-ris')
            ->assertOk()
            ->assertSee('Monthly Report')
            ->assertSee('href="'.route('admin.reports.monthly.index').'"', false);

        // And in the sidebar, under Reports.
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('admin.reports.monthly.index').'"', false);
    }

    public function test_the_monthly_report_offers_no_way_back_into_the_par_ics_ris_picker(): void
    {
        // It is not one of those three papers and is not opened from their
        // picker — it sits on its own under Reports — so its page carries no
        // link back into that list.
        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly')
            ->assertOk()
            ->assertDontSee('Back to PAR / ICS / RIS');

        // The three that are opened from the picker keep theirs, and the
        // printed sheet keeps the generic way back to its generator.
        foreach (['par', 'ics', 'ris'] as $type) {
            $this->actingAs($this->admin)
                ->get("/admin/reports/{$type}")
                ->assertOk()
                ->assertSee('Back to PAR / ICS / RIS');
        }

        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print')
            ->assertOk()
            ->assertSee('Back to generator')
            ->assertDontSee('PAR');
    }

    public function test_the_monthly_report_is_open_to_the_supply_staff(): void
    {
        $role = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Inspection Staff']);
        $staff = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $urls = ['/admin/reports/monthly', '/admin/reports/monthly/print', '/admin/reports/monthly/pdf'];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        // The same official RIS sheet the register holds, printed by the same
        // office: the supply staff may open it, preview it and export it.
        foreach ($urls as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }
    }

    public function test_the_monthly_report_runs_the_months_end_users_onto_one_report(): void
    {
        $maria = $this->teacher('Maria', 'Santos');
        $jose = $this->teacher('Jose', 'Cruz');
        $supply = $this->supply();

        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $supply, 'quantity' => 2]], ['teacher_id' => $maria->id]);
        $this->ris('RIS-2026-00090', '2026-08-06', [['supply' => $supply, 'quantity' => 3]], ['teacher_id' => $jose->id]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index', ['month' => 8, 'year' => 2026, 'document_type' => 'ris', 'generated' => 1]))
            ->assertOk()
            ->assertSee('Monthly Report · one running report per paper')
            ->assertSee('Maria Santos')
            ->assertSee('Jose Cruz')
            ->getContent();

        // The month's paper is one running report, not one page for each person
        // who took something: both end-users ride the same sheet, so their
        // lines follow one another down the form and fill the page it stands
        // on. The RIS run on its own, so no blank ICS report rides along.
        $this->assertSame(1, substr_count($html, 'class="doc doc-sheet"'));

        // The header names nobody — the report covers everyone the month issued
        // to — and each person is named over their own run of lines instead,
        // each of them once in the list.
        $this->assertStringNotContainsString('END-USER:', $html);
        $this->assertSame(1, substr_count($html, '>Maria Santos</td>'));
        $this->assertSame(1, substr_count($html, '>Jose Cruz</td>'));

        // One list closes and one certification signs the whole report: the
        // recapitulation and the signing lines belong to the report, not to
        // each person's part of it.
        $this->assertSame(1, substr_count($html, self::NOTHING_FOLLOWS));
        $this->assertSame(1, substr_count($html, 'I HEREBY CERTIFY TO THE CORRECTNESS OF THE ABOVE INFORMATION'));
        // The band carries the word once over what was issued and once over
        // what it cost, so one report shows one band and no more.
        $this->assertSame(2, substr_count($html, '>Recapitulation</div>'));

        // And the report is written under the month's first number: August's
        // own run of sheets starts where January's did.
        $this->assertStringContainsString('2026-08-01', $html);
        $this->assertStringNotContainsString('2026-08-02', $html);
    }

    /**
     * The Monthly Report numbers its own reports, each paper in a run of its own
     * and with nothing for the office to write into: every report of a paper's
     * run takes the next number, the blank report a month with nothing on it
     * prints takes one too, and the page that names a paper takes none — it is a
     * divider, not a report. The working page carries the same numbers the paper
     * does.
     */
    public function test_the_monthly_report_numbers_every_page_of_the_months_own_run(): void
    {
        $maria = $this->teacher('Maria', 'Santos');
        $jose = $this->teacher('Jose', 'Cruz');
        $supply = $this->supply();

        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $supply, 'quantity' => 2]], ['teacher_id' => $maria->id]);
        $this->ris('RIS-2026-00090', '2026-08-06', [['supply' => $supply, 'quantity' => 3]], ['teacher_id' => $jose->id]);

        $form = ['month' => 8, 'year' => 2026, 'document_type' => 'ris'];

        // August opens the RIS run here: it holds the office's first RIS paper,
        // so there is nothing printed before it for the count to carry on from,
        // and August's sheet is number one. There is no field either: the
        // numbers are the report's to give, not the office's to write.
        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly?'.http_build_query($form))
            ->assertOk()
            ->assertSee('<span class="doc-sign-line">2026-08-01</span>', false)
            ->assertDontSee('<span class="doc-sign-line">2026-08-02</span>', false)
            ->assertDontSee('name="sheet_no', false);

        // The paper carries the same number, and a number handed in by hand no
        // longer reaches it: the run is numbered, not written on.
        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly/print?'.http_build_query($form + ['sheet_no' => ['0' => '2026-08-777']]))
            ->assertOk()
            ->assertSee('<span class="doc-sign-line">2026-08-01</span>', false)
            ->assertDontSee('<span class="doc-sign-line">2026-08-777</span>', false);

        // Both papers, each in its own run: each opens behind a page naming it,
        // and the ICS — which the month holds no papers for — still prints the
        // blank report it is. The two papers are numbered apart, so August's
        // RIS sheet and its blank ICS sheet are both number one of their own
        // run: one paper's sheet does not push the other's along.
        $both = $this->actingAs($this->admin)
            ->get('/admin/reports/monthly?'.http_build_query(['month' => 8, 'year' => 2026, 'document_type' => 'all']))
            ->assertOk()
            ->getContent();

        // Two name pages and the two reports they announce. The name pages are
        // dividers: they take no number, and a run is not pushed on by them.
        $this->assertSame(4, substr_count($both, 'doc doc-sheet'));
        $this->assertSame(2, substr_count($both, 'class="doc-cover-title"'));
        $this->assertStringNotContainsString('doc-cover-number', $both);
        $this->assertSame(2, substr_count($both, '<span class="doc-sign-line">2026-08-01</span>'));
        $this->assertStringNotContainsString('2026-08-02', $both);

        // A paper the month holds nothing for still prints — the blank report
        // it is on the form — so a month with no records at all prints the
        // pair, each behind the page naming it, and each opening its own run.
        $blank = $this->actingAs($this->admin)
            ->get('/admin/reports/monthly?'.http_build_query(['month' => 1, 'year' => 2026, 'document_type' => 'all']))
            ->assertOk()
            ->assertSee('No records found for the selected period.')
            ->getContent();

        $this->assertSame(4, substr_count($blank, 'doc doc-sheet'));
        $this->assertSame(2, substr_count($blank, '<span class="doc-sign-line">2026-01-01</span>'));
        $this->assertStringNotContainsString('2026-01-02', $blank);

        // No filter can move a sheet off its number: a paper prints the same
        // sheets whatever the month happened to hold or the report is filtered
        // to, so the count it is measured against is the same one.
        $this->actingAs($this->admin)
            ->get('/admin/reports/monthly?'.http_build_query(['month' => 8, 'year' => 2026, 'document_type' => 'ris', 'end_user' => $maria->id]))
            ->assertOk()
            ->assertSee('<span class="doc-sign-line">2026-08-01</span>', false);
    }

    public function test_the_monthly_report_covers_only_the_chosen_month(): void
    {
        $supply = $this->supply();
        $this->ris('RIS-2026-00001', '2026-07-31', [['supply' => $supply, 'quantity' => 1]], ['teacher_id' => $this->teacher('Maria', 'Santos')->id]);
        $this->ris('RIS-2026-00080', '2026-08-31', [['supply' => $supply, 'quantity' => 1]], ['teacher_id' => $this->teacher('Jose', 'Cruz')->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('RIS-2026-00080')
            ->assertDontSee('RIS-2026-00001')
            ->assertSee('AUGUST 01-31, 2026');
    }

    public function test_an_empty_month_prints_one_sheet_saying_there_are_no_records(): void
    {
        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index', ['month' => 9, 'year' => 2026]))
            ->assertOk()
            ->assertSee('No records found for the selected period.')
            ->assertDontSee('RIS-2026-00080')
            // An empty sheet has no list to close.
            ->assertDontSee(self::NOTHING_FOLLOWS);
    }

    public function test_the_monthly_report_offers_all_ics_and_ris_in_its_category_filter(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index'))
            ->assertOk()
            ->assertSee('name="document_type"', false)
            ->getContent();

        $filter = substr($html, (int) strpos($html, 'name="document_type"'));
        $filter = substr($filter, 0, (int) strpos($filter, '</select>'));

        // All leads the list and is what the report opens on: the whole month's
        // papers are what the office prints unless one of them is asked for.
        $this->assertMatchesRegularExpression(
            '/<option value="all" selected>All<\/option>\s*<option value="ics"\s*>ICS<\/option>\s*<option value="ris"\s*>RIS<\/option>/',
            $filter
        );
        $this->assertSame(1, substr_count($filter, ' selected>'));
    }

    public function test_generating_all_prints_both_reports_each_behind_a_naming_page(): void
    {
        $maria = $this->teacher('Maria', 'Santos');
        $equipment = $this->equipment();
        $this->ics('ICS-2026-00001', $equipment, ['teacher_id' => $maria->id]);
        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]], ['teacher_id' => $maria->id]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index', ['month' => 8, 'year' => 2026, 'document_type' => 'all']))
            ->assertOk()
            ->assertSee('Bond Paper A4')
            ->assertSee('Desktop Computer')
            ->getContent();

        // Two naming pages, one for each report, and two report sheets.
        $this->assertSame(2, substr_count($html, 'class="doc doc-sheet doc-cover"'));
        $this->assertSame(2, substr_count($html, 'class="doc doc-sheet"'));

        // The RIS report comes first, the ICS report next.
        $ris = strpos($html, 'doc-cover-name">RIS<');
        $ics = strpos($html, 'doc-cover-name">ICS<');
        $this->assertNotFalse($ris);
        $this->assertNotFalse($ics);
        $this->assertLessThan($ics, $ris);

        // And the naming page stands before the report it names.
        $this->assertLessThan(strpos($html, 'Bond Paper A4'), $ris);
        $this->assertLessThan(strpos($html, 'Desktop Computer'), $ics);

        // The run counts both papers.
        $this->assertStringContainsString('1 RIS document(s)', $html);
        $this->assertStringContainsString('1 ICS document(s)', $html);

        // A single-source run carries no naming page at all.
        $plain = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index', ['month' => 8, 'year' => 2026, 'document_type' => 'ris']))
            ->assertOk()
            ->getContent();
        $this->assertSame(0, substr_count($plain, 'class="doc doc-sheet doc-cover"'));
    }

    public function test_all_prints_the_ris_report_even_when_the_month_only_holds_ics_documents(): void
    {
        $maria = $this->teacher('Maria', 'Santos');
        $this->ics('ICS-2026-00001', $this->equipment(), ['teacher_id' => $maria->id]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index', ['month' => 8, 'year' => 2026, 'document_type' => 'all']))
            ->assertOk()
            ->assertSee('Desktop Computer')
            ->getContent();

        // All is the pair of reports, each behind a page naming it — the RIS
        // first — so the month's ICS does not leave the RIS report out of the
        // run the office asked for.
        $this->assertSame(2, substr_count($html, 'class="doc doc-sheet doc-cover"'));
        $this->assertSame(2, substr_count($html, 'class="doc doc-sheet"'));
        $this->assertLessThan(
            strpos($html, 'doc-cover-name">ICS<'),
            strpos($html, 'doc-cover-name">RIS<')
        );

        // The RIS report has nothing to show, so it prints the blank form the
        // accounting unit writes on; the ICS report carries the month's slip.
        $this->assertStringContainsString('RIS NO.', $html);
        $this->assertStringContainsString('No records found for the selected period.', $html);
        $this->assertStringContainsString('ICS NO.', $html);
        $this->assertStringContainsString('ICS-2026-00001', $html);

        // The run still reads out what each paper held.
        $this->assertStringContainsString('0 RIS document(s)', $html);
        $this->assertStringContainsString('1 ICS document(s)', $html);
    }

    public function test_an_all_report_exports_as_a_pdf(): void
    {
        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]], ['teacher_id' => $this->teacher('Maria', 'Santos')->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.pdf', ['month' => 8, 'year' => 2026, 'document_type' => 'all']));

        $response->assertOk();
        $this->assertStringContainsString('Monthly-ALL-2026-08.pdf', $response->headers->get('content-disposition'));
    }

    public function test_the_monthly_report_has_no_mode_selector(): void
    {
        // The report is always the generated one: the mode picker and the
        // blank-form fields it switched to are gone.
        $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index'))
            ->assertOk()
            ->assertDontSee('name="mode"', false)
            ->assertDontSee('Manual — blank form to fill in')
            ->assertDontSee('name="blank_rows"', false)
            ->assertDontSee('name="lines"', false);
    }

    public function test_the_monthly_report_can_be_cut_from_the_ics_documents(): void
    {
        $maria = $this->teacher('Maria', 'Santos');
        $jose = $this->teacher('Jose', 'Cruz');
        $equipment = $this->equipment();
        $this->ics('ICS-2026-00001', $equipment, ['teacher_id' => $maria->id]);

        // A second custodian's property in the same month is the same paper, so
        // it rides the same report rather than opening a page of its own.
        $this->ics('ICS-2026-00002', $this->equipment(['item_code' => 'EQP-00002', 'property_number' => 'PPE-2026-0002']), ['teacher_id' => $jose->id]);

        // A RIS in the same month is a different paper and stays out of it.
        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index', ['month' => 8, 'year' => 2026, 'document_type' => 'ics', 'show_costs' => 1]))
            ->assertOk()
            ->assertSee('Desktop Computer')
            ->assertSee('Maria Santos')
            ->assertSee('Jose Cruz')
            ->getContent();

        // The sheet is cut from the ICS, so its first column says ICS, not RIS.
        $this->assertStringContainsString('ICS NO.', $html);
        $this->assertStringNotContainsString('RIS NO.', $html);
        // An ICS sheet is already one custodian's, so it carries no END-USER row.
        $this->assertStringNotContainsString('END-USER:', $html);
        $this->assertStringContainsString('Maria Santos', $html);

        // The RIS in the month is not swept into an ICS report, and the same
        // month read as a RIS report still leaves the ICS out.
        $this->assertStringNotContainsString('Bond Paper A4', $html);
        $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index', ['month' => 8, 'year' => 2026, 'document_type' => 'ris']))
            ->assertOk()
            ->assertSee('RIS NO.')
            ->assertDontSee('Desktop Computer');

        // One report for the paper, holding both custodians who signed for
        // property that month: each ICS number stands once over its own lines,
        // and the one "Nothing Follows" line closes the whole list.
        $this->assertSame(1, substr_count($html, 'class="doc doc-sheet"'));
        $this->assertSame(1, substr_count($html, self::NOTHING_FOLLOWS));
        $this->assertSame(1, substr_count($html, 'ICS-2026-00001'));
        $this->assertSame(1, substr_count($html, 'ICS-2026-00002'));
        // Amount is still quantity × unit cost: 1 × 35,000.00.
        $this->assertStringContainsString('35,000.00', $html);
    }

    public function test_an_unknown_monthly_source_falls_back_to_all_the_months_papers(): void
    {
        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);
        $this->ics('ICS-2026-00001', $this->equipment(), ['teacher_id' => $this->teacher('Maria', 'Santos')->id]);

        // The fallback is the report's own default, so an unrecognised
        // category reads the month the same way an empty one does.
        $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index', ['month' => 8, 'year' => 2026, 'document_type' => 'nonsense']))
            ->assertOk()
            ->assertSee('Bond Paper A4')
            ->assertSee('Desktop Computer');
    }

    public function test_the_monthly_report_prints_and_exports_like_the_other_documents(): void
    {
        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]], ['teacher_id' => $this->teacher('Maria', 'Santos')->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.print', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Print this document')
            ->assertSee('Maria Santos')
            ->assertDontSee('id="sidebar"', false)
            ->assertDontSee('Generate Report');

        $response = $this->actingAs($this->admin)->get(route('admin.reports.monthly.pdf', ['month' => 8, 'year' => 2026]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        // The export is named for the category it was run for, which without
        // one asked for is the whole month's papers.
        $this->assertStringContainsString('Monthly-ALL-2026-08.pdf', $response->headers->get('content-disposition'));
    }

    public function test_the_plain_ris_report_also_closes_its_list_with_nothing_follows(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->assertSee(self::NOTHING_FOLLOWS);

        // An empty report has no list to close: it says so instead.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=9&year=2026')
            ->assertOk()
            ->assertSee('No records found for the selected period.')
            ->assertDontSee(self::NOTHING_FOLLOWS);
    }

    /* ------------------------------------------------------------------ */
    /* Print and PDF                                                       */
    /* ------------------------------------------------------------------ */

    public function test_the_printed_sheet_carries_no_application_chrome(): void
    {
        $equipment = $this->equipment();

        $this->actingAs($this->admin)
            ->get('/admin/reports/par/print?equipment[]='.$equipment->id)
            ->assertOk()
            ->assertSee('Property Acknowledgement Receipt')
            ->assertSee('Print this document')
            // No sidebar, no header, no filters: just the official form.
            ->assertDontSee('id="sidebar"', false)
            ->assertDontSee('name="equipment[]"', false)
            ->assertDontSee('Generate Report');
    }

    public function test_the_report_name_sits_inside_the_header_frame(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->getContent();

        // One frame holds the name, the agency, the number and the date…
        $head = substr($html, (int) strpos($html, '<table class="doc-grid doc-head">'));
        $head = substr($head, 0, (int) strpos($head, '</table>'));

        $this->assertStringContainsString('Report of Supplies and Materials Issued', $head);
        $this->assertStringContainsString('Isabela State University - Cauayan Campus', $head);
        $this->assertStringContainsString('AGENCY:', $head);
        $this->assertStringContainsString('NO:', $head);
        $this->assertStringContainsString('DATE:', $head);

        // The agency and the date are written straight after their labels, each
        // on a line as long as the value itself, and both stand bold like the
        // form writes them…
        $this->assertSame(2, substr_count($head, 'doc-head-line'));
        $this->assertSame(2, substr_count($head, 'class="doc-l doc-b doc-nowrap"'));
        // …and the line each of them is written on is the sheet's own rule —
        // drawn at the weight every other line of the form is ruled with —
        // rather than the font's underline, which both printers set a shade
        // lighter than the paper it stands on.
        $this->assertStringContainsString('.doc-head .doc-head-line { border-bottom: 1px solid #000; }', $html);
        $this->assertStringNotContainsString('text-decoration: underline', $html);
        // …while the number sits in its own field at the right side of the
        // sheet, on a line drawn for the entry alone: the label stands in front
        // of it, clear of the rule, which never runs under the words. The field
        // is the width of the entry it carries rather than a share of the
        // frame, so the line ends against the right border instead of trailing
        // away across the middle of the sheet, and the entry is written at the
        // head of it, straight after the label, as the form has it.
        $this->assertSame(1, substr_count($head, 'doc-sign-line'));
        $this->assertStringContainsString('class="lbl doc-r"', $head);
        $this->assertStringContainsString('<span class="doc-no-label">NO:</span><span class="doc-sign-line">2026-08-001</span>', $head);
        $this->assertStringNotContainsString('doc-sign-line">NO:', $head);
        $this->assertMatchesRegularExpression(
            '/\.doc-head \.doc-sign-line \{[^}]*min-width: 50pt;/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/\.doc-head \.doc-sign-line \{[^}]*text-align: left;/s',
            $html
        );
        // The rule is drawn into the frame's own border: the cell's right
        // padding would otherwise end it a hair short of the border and leave
        // the two rules looking like one broken edge, so the line overhangs
        // that padding and meets the border as a single rule.
        $this->assertStringContainsString('margin-right: -5px;', $html);

        // …and is not parked above the frame any more.
        $this->assertStringNotContainsString('<h1 class="doc-title">', $html);
        $this->assertStringNotContainsString('<div class="doc-org">Isabela', $html);
    }

    public function test_the_printed_sheet_repeats_the_table_header_and_keeps_rows_whole(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->assertSee('RIS NO.')
            ->assertSee('QNTY')
            // The header repeats on every page and a row is never split.
            ->assertSee('.doc-grid thead { display: table-header-group; }', false)
            ->assertSee('.doc-grid tr { page-break-inside: avoid; }', false);
    }

    public function test_the_print_sheet_can_raise_the_print_dialog_itself(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&print=1')
            ->assertOk()
            ->assertSee('window.print()', false);
    }

    public function test_the_print_sheet_points_out_the_browsers_own_page_furniture(): void
    {
        $this->ris('RIS-2026-00001', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        // The date, the page title and the address a browser prints above and
        // below the sheet are the browser's, and the page can only say so.
        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026')
            ->assertOk()
            ->assertSee('Headers and footers')
            ->assertSee('.doc-bar, .doc-nonprint', false)
            ->getContent();

        $bar = substr($html, (int) strpos($html, 'class="doc-bar"'));
        $bar = substr($bar, 0, (int) strpos($bar, '</div>'));

        // The note rides in the bar, which is never printed, and the links out
        // of it carry only the filters that were actually chosen.
        $this->assertStringContainsString('untick', $bar);
        $this->assertStringContainsString('Export PDF', $bar);
        $this->assertStringNotContainsString('end_user=&', $bar);
        $this->assertStringNotContainsString('stock_no=&', $bar);
        $this->assertStringNotContainsString('category=&', $bar);

        $empty = $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=8&year=2026&end_user=&stock_no=&category=')
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString(
            'href="'.e(route('admin.reports.document.pdf', ['type' => 'ris', 'month' => 8, 'year' => 2026])).'"',
            $empty
        );
    }

    public function test_every_official_document_exports_as_a_pdf(): void
    {
        $equipment = $this->equipment();
        $this->ris('RIS-2026-00080', '2026-08-05', [['supply' => $this->supply(), 'quantity' => 1]]);

        $cases = [
            '/admin/reports/par/pdf?equipment[]='.$equipment->id => 'PAR-2026-00001.pdf',
            '/admin/reports/ics/pdf?equipment[]='.$equipment->id => 'ICS-2026-00001.pdf',
            '/admin/reports/ris/pdf?month=8&year=2026' => 'RIS-2026-08.pdf',
        ];

        foreach ($cases as $url => $filename) {
            $response = $this->actingAs($this->admin)->get($url);

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'));
            $this->assertStringContainsString($filename, $response->headers->get('content-disposition'));
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_the_print_and_pdf_routes_take_the_same_filters_as_the_generator(): void
    {
        $supply = $this->supply();
        $this->ris('RIS-2026-00002', '2026-08-06', [['supply' => $supply, 'quantity' => 1]]);

        // The month window reaches the printed sheet and the PDF alike.
        $this->actingAs($this->admin)
            ->get('/admin/reports/ris/print?month=9&year=2026')
            ->assertOk()
            ->assertSee('No records found for the selected period.')
            ->assertDontSee('RIS-2026-00002');
    }

    public function test_an_unknown_document_type_is_not_a_report(): void
    {
        $this->actingAs($this->admin)->get('/admin/reports/iar')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/reports/iar/print')->assertNotFound();
    }

    /* ------------------------------------------------------------------ */
    /* Paging the list                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * A list long enough to fill the paper is measured and cut into pages
     * before it is printed, and every one of those pages carries the form: the
     * head over the entries, and, on the page the list ends on, the line that
     * closes it, the recapitulation and the certification the office signs.
     * Left to the printer's own page break, the entries ran on over the paper
     * and the page they landed on carried the tail of a list with no head above
     * it; and a certification written on every page would leave each page that
     * carries the list on stopping a foot's height short of the paper.
     */
    public function test_a_long_month_is_cut_into_pages_that_carry_the_form(): void
    {
        $maria = $this->teacher('Maria', 'Santos');
        $supply = $this->supply();
        $reams = $this->supply(['item_code' => 'SUP-00002', 'name' => 'Bond Paper Legal']);

        // A month of papers, each with lines enough that the run cannot stand
        // on one sheet of paper.
        for ($paper = 1; $paper <= 16; $paper++) {
            $this->ris(
                'RIS-2026-'.str_pad((string) $paper, 5, '0', STR_PAD_LEFT),
                '2026-08-'.str_pad((string) ($paper % 28 + 1), 2, '0', STR_PAD_LEFT),
                [
                    ['supply' => $supply, 'quantity' => 1],
                    ['supply' => $reams, 'quantity' => 2],
                    ['supply' => $supply, 'quantity' => 4],
                ],
                ['teacher_id' => $maria->id]
            );
        }

        $html = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly.index', ['month' => 8, 'year' => 2026, 'document_type' => 'ris', 'generated' => 1]))
            ->assertOk()
            ->getContent();

        $pages = substr_count($html, 'class="doc doc-sheet"');
        $this->assertGreaterThan(1, $pages, 'a month this long should print over more than one page');

        // Every page of the run carries the form's own head, and the run is
        // signed once: the certification is the sheet's foot, and it stands on
        // the page the list ends on rather than on every page of it.
        $this->assertSame($pages, substr_count($html, 'class="doc-grid doc-head"'));
        $this->assertSame(1, substr_count($html, 'I HEREBY CERTIFY TO THE CORRECTNESS OF THE ABOVE INFORMATION'));

        // So do the line that closes the list and the recapitulation that
        // stands under it: the pages before it carry the same list on, so none
        // of them claims the list has ended.
        $this->assertSame(1, substr_count($html, self::NOTHING_FOLLOWS));
        $this->assertSame(2, substr_count($html, '>Recapitulation</div>'));

        // Every page printed is a sheet of the month's run and is numbered as
        // it prints: one number each, in order, with none of them shared
        // between two sheets.
        preg_match_all('/doc-sign-line">(2026-08-\d\d)</', $html, $numbers);
        $this->assertCount($pages, $numbers[1]);

        foreach ($numbers[1] as $index => $number) {
            $this->assertSame(sprintf('2026-08-%02d', $index + 1), $number);
        }
    }

    /**
     * The room kept for the recapitulation follows what the block prints, not
     * the lines it is handed: a run with every figure withheld is ruled as one
     * blank line however many stock numbers it holds (see _ris_recap), so the
     * page that ends the list is not measured as though it carried a line for
     * each of them — room held back for lines the sheet never draws is room the
     * entries never get.
     */
    public function test_the_room_kept_for_the_recapitulation_follows_what_it_prints(): void
    {
        $stock = [['stock_no' => 'SUP-00001'], ['stock_no' => 'SUP-00002'], ['stock_no' => 'SUP-00003']];

        // Withheld — the whole block is one blank line, and the TOTAL line
        // stands only where it is asked for (it has nothing to close).
        $this->assertSame(1, SheetPages::recapLines($stock, false, false, false));
        $this->assertSame(1, SheetPages::recapLines($stock, false, false, true));

        // Printed — a line per stock number, and its TOTAL under them.
        $this->assertSame(3, SheetPages::recapLines($stock, true, false, false));
        $this->assertSame(4, SheetPages::recapLines($stock, false, true, true));

        // Nothing to report still prints the block the blank form has.
        $this->assertSame(1, SheetPages::recapLines([], true, true, true));
    }

    /**
     * A page is filled down to the foot of its own room before the next is
     * started: the entries run on until the next of them would carry past the
     * certification, and only then does the paper turn. A page printed with its
     * entries stopping halfway down is a blank half-sheet the office reads
     * past; only the page that ends the list, which the closing blocks stand
     * on, is ever left short.
     */
    public function test_each_page_of_a_report_is_filled_before_the_next_is_started(): void
    {
        $rows = array_fill(0, 30, ['item' => 'Bond Paper A4', 'description' => '', 'end_user' => 'Maria Santos']);

        foreach ([SheetPages::forScreen(), SheetPages::forExport()] as $pager) {
            $tail = $pager->tailMm(SheetPages::recapLines([['stock_no' => 'SUP-00001']], false, false, false));
            $pages = $pager->cut($rows, fn ($row) => $pager->risRowMm($row), $tail);

            $this->assertGreaterThan(1, count($pages));
            $this->assertSame(30, array_sum(array_map('count', $pages)), 'every line is still on a page');

            $last = count($pages) - 1;

            foreach ($pages as $index => $page) {
                $used = array_sum(array_map(fn ($row) => $pager->risRowMm($row), $page));

                // The page that ends the list keeps room for the blocks that
                // close it (the line that ends the list and the recapitulation
                // under it) and for the certification beneath them; the pages
                // before it carry entries to the foot of the paper.
                $allowed = $index === $last
                    ? $pager->roomMm() - $tail
                    : $pager->openRoomMm();

                $this->assertLessThanOrEqual($allowed, $used);

                // No page but the one that ends the list has room to spare:
                // the entry the next page opens with would not fit on it —
                // either because the page is full of entries, or because that
                // entry is the last of the list, and closing the list here
                // would leave the page no room for the blocks the closing page
                // keeps under it (see roomMm).
                if ($index < $last) {
                    $next = $pager->risRowMm($pages[$index + 1][0]);
                    $closesTheList = count($pages[$index + 1]) === 1;

                    $this->assertTrue(
                        $used + $next > $allowed
                            || ($closesTheList && $used + $next + $tail > $pager->roomMm()),
                        'a page was left with room the next entry would have fitted in',
                    );
                }
            }
        }
    }

    /**
     * The page that ends the list takes the entries the paper holds: the head
     * the exporter draws over them, the line that closes the list and the
     * recapitulation under it, and the certification the sheet is signed at the
     * foot of all stand on that one page, and the entries run on under them
     * until the paper is filled. A reservation taken a shade too wide does not
     * print a shorter page — it prints another one, and the page it turned over
     * stands with a third of the sheet blank above the certification while the
     * entries the paper had room for are carried past it.
     *
     * So what the exporter holds back is measured here against the sheet it
     * draws, in its own millimetres: off an exported page, the head from the
     * exporter's top margin down to the rule the list opens on, the blocks that
     * close the list under the last of the entries, and the certification the
     * page is signed at the foot of.
     */
    public function test_the_exporter_keeps_only_the_room_the_sheet_it_draws_needs(): void
    {
        $pager = SheetPages::forExport();

        // The page the list ends on, drawn: the head over the entries and the
        // certification under them, between the margins the paper prints in.
        $head = 59.7;
        $cert = 31.4;
        $paper = 297.0 - 22.0;

        // Everything that page carries: the head, the entries it has room for,
        // and the certification under them. What is left is the room kept back
        // for the measure being a shade out.
        $heldBack = $paper - $head - $pager->roomMm() - $cert;

        $this->assertGreaterThan(0.0, $heldBack, 'the page is measured longer than the paper under it');
        $this->assertLessThanOrEqual(4.0, $heldBack, 'the exporter keeps room the sheet has no use for');

        // The closing blocks under the list, drawn on that page too: the line
        // that ends the list, the recapitulation's band and its headings, and
        // the one blank line a run with every figure withheld is ruled as.
        $tail = $pager->tailMm(SheetPages::recapLines([['stock_no' => 'SUP-00001']], false, false, false));

        $this->assertEqualsWithDelta(26.4, $tail, 1.0, 'the closing blocks are measured off the sheet they stand on');
    }

    /**
     * The plain RIS report is cut the same way: it is the same list on the same
     * paper, and a run of papers long enough to fill a page carries the form's
     * head on every page and is signed once, where it ends.
     */
    public function test_a_long_ris_report_is_cut_into_pages_too(): void
    {
        $supply = $this->supply();
        $reams = $this->supply(['item_code' => 'SUP-00002', 'name' => 'Bond Paper Legal']);

        for ($paper = 1; $paper <= 14; $paper++) {
            $this->ris(
                'RIS-2026-'.str_pad((string) $paper, 5, '0', STR_PAD_LEFT),
                '2026-08-'.str_pad((string) ($paper % 28 + 1), 2, '0', STR_PAD_LEFT),
                [
                    ['supply' => $supply, 'quantity' => 1],
                    ['supply' => $reams, 'quantity' => 2],
                    ['supply' => $supply, 'quantity' => 3],
                    ['supply' => $reams, 'quantity' => 5],
                ]
            );
        }

        $html = $this->actingAs($this->admin)
            ->get('/admin/reports/ris?month=8&year=2026')
            ->assertOk()
            ->getContent();

        $pages = substr_count($html, 'class="doc doc-sheet"');
        $this->assertGreaterThan(1, $pages);
        $this->assertSame($pages, substr_count($html, 'class="doc-grid doc-head"'));
        $this->assertSame(1, substr_count($html, 'I HEREBY CERTIFY TO THE CORRECTNESS OF THE ABOVE INFORMATION'));
        $this->assertSame(1, substr_count($html, self::NOTHING_FOLLOWS));

        // Nothing is lost to the cut: every line of every paper is printed.
        $this->assertSame(56, substr_count($html, 'class="doc-name"'));
    }

    /**
     * The measure the cut is made with: no page takes more than the paper holds
     * between its head and its certification, and the page that ends the list
     * keeps room for the closing blocks as well. A page measured one line too
     * long is a page the printer breaks somewhere else entirely.
     */
    public function test_no_page_of_the_list_takes_more_than_the_paper_holds(): void
    {
        $rows = array_fill(0, 40, ['item' => 'Bond Paper A4', 'description' => 'Substance 20', 'end_user' => 'Maria Santos']);
        $pagesPerPrinter = [];

        foreach ([SheetPages::forScreen(), SheetPages::forExport()] as $pager) {
            $tail = $pager->tailMm(SheetPages::recapLines([['stock_no' => 'SUP-00001'], ['stock_no' => 'SUP-00002']], true, true, true));
            $pages = $pager->cut($rows, fn ($row) => $pager->risRowMm($row), $tail);

            $this->assertGreaterThan(1, count($pages));
            $this->assertSame(40, array_sum(array_map('count', $pages)), 'every line is still on a page');

            $last = count($pages) - 1;

            foreach ($pages as $index => $page) {
                $used = array_sum(array_map(fn ($row) => $pager->risRowMm($row), $page));

                $room = $index === $last
                    ? $pager->roomMm() - $tail
                    : $pager->openRoomMm();

                $this->assertLessThanOrEqual($room, $used);
            }

            $pagesPerPrinter[] = count($pages);
        }

        // The browser sets a shorter line than the exporter, so its pages carry
        // more of the list and there are fewer of them — each printer fills its
        // own paper rather than the other's.
        $this->assertLessThan($pagesPerPrinter[1], $pagesPerPrinter[0]);
    }
}
