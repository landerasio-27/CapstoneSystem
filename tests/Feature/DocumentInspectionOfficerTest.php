<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The name on the Inspection and Acceptance Report's "Inspection Officer" line.
 *
 * It is recorded on the document and printed on the IAR (page two of the
 * PAR/ICS/RIS bundle). When the office leaves it blank the printed line keeps
 * falling back to the approver / preparer, exactly as it did before.
 */
class DocumentInspectionOfficerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'name' => 'Supply Office Administrator']);
    }

    /** Reused across calls: a test may build more than one payload. */
    private function equipment(): Equipment
    {
        return Equipment::firstOrCreate(
            ['item_code' => 'EQP-00001'],
            [
                'name' => 'Epson LCD Projector',
                'category' => 'ICT Equipment',
                'unit' => 'unit',
                'quantity_on_hand' => 1,
                'unit_cost' => 28000,
                'reorder_level' => 0,
                'status' => 'available',
            ],
        );
    }

    private function payload(array $extra = [], string $type = 'PAR'): array
    {
        return $extra + [
            'type' => $type,
            'document_date' => '2026-09-22',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'items' => [
                ['item' => 'equipment:' . $this->equipment()->id, 'quantity' => 1, 'date_acquired' => '2026-09-20'],
            ],
        ];
    }

    /** The IAR page of the bundle, built the way the controller builds it. */
    private function iarPage(Document $document): string
    {
        $document->load(['teacher', 'room', 'preparedBy', 'approvedBy', 'issuedBy', 'items.equipment', 'items.supply']);

        $iar = $document->replicate();
        $iar->type = 'IAR';
        $iar->document_no = preg_replace('/^[A-Za-z]+/', 'IAR', $document->document_no);

        return view('admin.documents.forms.iar', ['document' => $iar])->render();
    }

    public function test_the_form_offers_an_inspection_officer_field(): void
    {
        $this->equipment();

        foreach (['PAR', 'ICS', 'RIS'] as $type) {
            $this->actingAs($this->admin)
                ->get("/admin/documents/create?type={$type}")
                ->assertOk()
                ->assertSee('Inspection Officer')
                ->assertSee('name="inspection_officer"', false);
        }
    }

    public function test_the_officer_is_stored_when_the_document_is_created_and_edited(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload(['inspection_officer' => 'Lander Asio']))
            ->assertRedirect();

        $document = Document::latest('id')->firstOrFail();
        $this->assertSame('Lander Asio', $document->inspection_officer);

        // The edit form brings the stored name back, and can change it.
        $this->actingAs($this->admin)
            ->get("/admin/documents/{$document->id}/edit")
            ->assertOk()
            ->assertSee('value="Lander Asio"', false);

        $this->actingAs($this->admin)
            ->put("/admin/documents/{$document->id}", $this->payload(['inspection_officer' => 'Maria Santos']))
            ->assertRedirect();

        $this->assertSame('Maria Santos', $document->refresh()->inspection_officer);
    }

    public function test_the_recorded_officer_prints_on_the_inspection_line(): void
    {
        $approver = User::factory()->create([
            'role_id' => $this->admin->role_id,
            'is_active' => true,
            'name' => 'Ricardo Ramos',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload(['inspection_officer' => 'Lander Asio']))
            ->assertRedirect();

        $document = Document::latest('id')->firstOrFail();
        $document->update(['approved_by' => $approver->id]);

        $html = $this->iarPage($document->refresh());

        $this->assertStringContainsString('Inspection Officer', $html);
        $this->assertStringContainsString('Lander Asio', $html);
        // The typed name takes the line over; the approver is not substituted.
        $this->assertStringNotContainsString('Ricardo Ramos', $html);
    }

    public function test_a_blank_officer_keeps_falling_back_to_the_approver(): void
    {
        $approver = User::factory()->create([
            'role_id' => $this->admin->role_id,
            'is_active' => true,
            'name' => 'Ricardo Ramos',
        ]);

        $this->actingAs($this->admin)->post('/admin/documents', $this->payload())->assertRedirect();

        $document = Document::latest('id')->firstOrFail();
        $this->assertNull($document->inspection_officer);

        $document->update(['approved_by' => $approver->id]);
        $html = $this->iarPage($document->refresh());

        // Nothing was invented, but the line is not left nameless either.
        $this->assertStringContainsString('Ricardo Ramos', $html);
    }

    public function test_the_officer_is_optional_and_length_checked(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload(['inspection_officer' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('inspection_officer');

        $this->assertSame(0, Document::count());
    }

    public function test_the_bundle_pdf_still_renders_with_the_officer(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload(['inspection_officer' => 'Lander Asio']))
            ->assertRedirect();

        $document = Document::latest('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->get("/admin/documents/{$document->id}/bundle-pdf")
            ->assertOk();
    }
}
