<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The custodian is chosen from the custodian directory (the people records
 * managed under Organization → Custodians), not typed as free text. The
 * printed PAR/ICS/RIS take the name and position from that record, so the
 * forms no longer carry a separate "Custodian Position" field.
 */
class CustodianDropdownTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function custodian(array $attributes = []): Teacher
    {
        return Teacher::create($attributes + [
            'employee_id' => 'EMP-' . str_pad((string) (Teacher::count() + 1), 4, '0', STR_PAD_LEFT),
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'department' => 'CCS',
            'position' => 'Administrative Aide III',
            'is_active' => true,
        ]);
    }

    private function equipment(array $attributes = []): Equipment
    {
        return Equipment::create($attributes + [
            'item_code' => 'EQP-' . str_pad((string) (Equipment::count() + 1), 5, '0', STR_PAD_LEFT),
            'name' => 'Dell OptiPlex Desktop',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 35000,
            'reorder_level' => 0,
            'status' => 'available',
        ]);
    }

    public function test_the_equipment_form_chooses_a_custodian_from_the_directory(): void
    {
        $this->custodian();

        $this->actingAs($this->admin)
            ->get('/admin/equipment/create')
            ->assertOk()
            ->assertSee('name="teacher_id"', false)
            ->assertSee('Juan Dela Cruz')
            ->assertDontSee('Name of the person accountable');
    }

    public function test_equipment_saves_the_chosen_custodian(): void
    {
        $custodian = $this->custodian();

        $this->actingAs($this->admin)
            ->post('/admin/equipment', [
                'item_code' => 'EQP-00001',
                'name' => 'Dell OptiPlex Desktop',
                'unit' => 'unit',
                'status' => 'available',
                'teacher_id' => $custodian->id,
            ])
            ->assertRedirect();

        $equipment = Equipment::latest('id')->firstOrFail();

        $this->assertSame($custodian->id, $equipment->teacher_id);
        $this->assertSame('Juan Dela Cruz', $equipment->custodian_name);
    }

    public function test_the_equipment_edit_form_preselects_the_saved_custodian(): void
    {
        $custodian = $this->custodian();
        $equipment = $this->equipment(['teacher_id' => $custodian->id]);

        $html = $this->actingAs($this->admin)
            ->get("/admin/equipment/{$equipment->id}/edit")
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/value="' . $custodian->id . '"\s+selected/', $html);
    }

    public function test_the_document_form_uses_the_same_dropdown_and_drops_the_position_field(): void
    {
        $this->custodian();

        $this->actingAs($this->admin)
            ->get('/admin/documents/create?type=PAR')
            ->assertOk()
            ->assertSee('name="teacher_id"', false)
            ->assertSee('Juan Dela Cruz')
            ->assertDontSee('Custodian Position')
            ->assertDontSee('name="custodian"', false);
    }

    public function test_a_document_records_the_chosen_custodian(): void
    {
        $custodian = $this->custodian();
        $equipment = $this->equipment();

        $this->actingAs($this->admin)
            ->post('/admin/documents', [
                'type' => 'PAR',
                'document_date' => '2026-09-22',
                'entity_name' => 'Isabela State University - Cauayan Campus',
                'teacher_id' => $custodian->id,
                'items' => [['item' => 'equipment:' . $equipment->id, 'quantity' => 1]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document = Document::latest('id')->firstOrFail();

        $this->assertSame($custodian->id, $document->teacher_id);
        $this->assertSame('Juan Dela Cruz', $document->custodian_name);

        // The detail page names the custodian, so the choice is visible.
        $this->actingAs($this->admin)
            ->get("/admin/documents/{$document->id}")
            ->assertOk()
            ->assertSee('Juan Dela Cruz');
    }

    public function test_the_printed_receipt_takes_the_name_and_position_from_the_custodian(): void
    {
        $custodian = $this->custodian();
        $equipment = $this->equipment();

        $this->actingAs($this->admin)->post('/admin/documents', [
            'type' => 'PAR',
            'document_date' => '2026-09-22',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'teacher_id' => $custodian->id,
            'items' => [['item' => 'equipment:' . $equipment->id, 'quantity' => 1]],
        ])->assertRedirect();

        $document = Document::latest('id')->firstOrFail();
        $document->load(['items.equipment', 'items.supply', 'teacher', 'issuedBy', 'preparedBy']);

        $html = view('admin.documents.forms.par', ['document' => $document])->render();

        $this->assertStringContainsString('Juan Dela Cruz', $html);
        $this->assertStringContainsString('Administrative Aide III', $html);
    }

    /**
     * The register keeps the custodians the printed forms are signed by, so the
     * grid opens them rather than offering to remove them. The tile is the only
     * way in: what is in the folder, and the way to edit the record, are read
     * inside the folder the tile opens.
     */
    public function test_the_custodian_register_opens_a_custodian_instead_of_deleting_one(): void
    {
        $custodian = $this->custodian();

        $this->actingAs($this->admin)->get('/admin/teachers')->assertOk()->assertSee('Juan Dela Cruz');

        $html = $this->actingAs($this->admin)->get('/admin/teachers')->getContent();

        // The tile opens the custodian ...
        $this->assertStringContainsString('data-href="' . route('admin.teachers.show', $custodian) . '"', $html);

        // ... and carries no action of its own: editing is offered on the folder
        // page, so the grid stays a cabinet of folders.
        $this->assertStringNotContainsString('<a href="' . route('admin.teachers.edit', $custodian) . '"', $html);

        // Nothing on the line removes the custodian: the route is still there for
        // the API, but the register no longer offers it.
        $this->assertStringNotContainsString('action="' . route('admin.teachers.destroy', $custodian) . '"', $html);
        $this->assertStringNotContainsString('This will permanently delete this teacher.', $html);

        // Editing is still one page away, on the folder the tile opens.
        $this->actingAs($this->admin)->get(route('admin.teachers.show', $custodian))
            ->assertOk()
            ->assertSee('Edit Information');
    }
}
