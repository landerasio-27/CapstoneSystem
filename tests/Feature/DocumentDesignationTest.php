<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\Supply;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The RIS closes with its signature block: every column prints a name and, on
 * the row under it, the designation that person signs under. The requester and
 * the receiver are the custodian the paper is filed under, so both carry that
 * record's position; the approver and the issuer are the accounts that took the
 * paper through approval and issuance, so they sign under their own.
 */
class DocumentDesignationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'name' => 'Supply Office Administrator',
        ]);
    }

    private function custodian(string $position = 'Associate Professor'): Teacher
    {
        return Teacher::create([
            'employee_id' => 'EMP-' . str_pad((string) (Teacher::count() + 1), 4, '0', STR_PAD_LEFT),
            'first_name' => 'Cristina',
            'last_name' => 'Bautista',
            'department' => 'CCS',
            'position' => $position,
            'is_active' => true,
        ]);
    }

    private function supply(): Supply
    {
        return Supply::create([
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

    /** A RIS filed to the custodian and taken through approval and issuance. */
    private function issuedRis(Teacher $custodian): Document
    {
        $supply = $this->supply();

        $this->actingAs($this->admin)->post('/admin/documents', [
            'type' => 'RIS',
            'document_date' => '2026-09-22',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'teacher_id' => $custodian->id,
            'items' => [['item' => 'supply:' . $supply->id, 'quantity' => 5]],
        ])->assertRedirect();

        $document = Document::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/approve")->assertRedirect();
        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue")->assertRedirect();

        return $document->refresh();
    }

    /**
     * The four designations the printed block carries, read off the rendered
     * form the way the PDF controller renders it, in the form's own order:
     * requested, approved, issued, received.
     *
     * @return list<string>
     */
    private function designations(Document $document): array
    {
        $document->load(['teacher', 'room', 'preparedBy', 'approvedBy', 'issuedBy', 'items.equipment', 'items.supply']);

        $html = view('admin.documents.forms.ris', ['document' => $document])->render();

        preg_match('/Designation:<\/td>(.*?)<\/tr>/s', $html, $row);
        $this->assertNotEmpty($row, 'The RIS prints a Designation row.');

        preg_match_all('/<td class="tc">(.*?)<\/td>/s', $row[1], $cells);

        return array_map(fn ($cell) => trim($cell), $cells[1]);
    }

    public function test_the_designation_row_names_each_signatorys_position(): void
    {
        $this->admin->update(['position' => 'Administrative Officer IV']);

        $document = $this->issuedRis($this->custodian());

        $this->assertSame(
            ['Associate Professor', 'Administrative Officer IV', 'Administrative Officer IV', 'Associate Professor'],
            $this->designations($document),
        );
    }

    public function test_an_account_holding_no_position_signs_under_the_supply_office_title(): void
    {
        $document = $this->issuedRis($this->custodian());

        // The custodian's record still titles both of that person's columns; the
        // office that issues falls back to the title the PAR and the ICS print.
        $this->assertSame(
            ['Associate Professor', '', 'Administrative Officer I (Supply Officer I)', 'Associate Professor'],
            $this->designations($document),
        );
    }
}
