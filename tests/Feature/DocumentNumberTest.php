<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Document No. on a new PAR / ICS / RIS: the office may type their own
 * number on the form, and leaving the field blank assigns the next free number
 * for that type. Whichever route is taken, the number on file stays unique —
 * including when a typed number is ahead of everything the system generated.
 */
class DocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function equipment(): Equipment
    {
        return Equipment::create([
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

    /** A create-document submission with one valid line item. */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'PAR',
            'document_date' => '2026-09-22',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'items' => [
                ['item' => 'equipment:' . $this->equipment()->id, 'quantity' => 1],
            ],
        ];
    }

    private function existing(string $number, string $type = 'PAR'): Document
    {
        return Document::create([
            'document_no' => $number,
            'type' => $type,
            'status' => 'draft',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'document_date' => '2026-09-01',
        ]);
    }

    public function test_the_create_form_offers_a_document_number_field(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/documents/create?type=PAR')
            ->assertOk()
            ->assertSee('Document No.')
            ->assertSee('Leave blank to use the next number automatically.')
            ->getContent();

        $this->assertStringContainsString('name="document_no"', $html);
        // The suggested number is a placeholder, not a submitted value: two
        // people may have the form open at the same time.
        $this->assertStringContainsString('placeholder="PAR-' . now()->year . '-00001"', $html);
        $this->assertStringNotContainsString('value="PAR-' . now()->year . '-00001"', $html);
    }

    public function test_a_blank_number_is_assigned_automatically(): void
    {
        $this->actingAs($this->admin)->post('/admin/documents', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('PAR-' . now()->year . '-00001', Document::sole()->document_no);
    }

    public function test_a_typed_number_is_kept(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload(['document_no' => 'PAR-2026-00777']))
            ->assertSessionHasNoErrors();

        $this->assertSame('PAR-2026-00777', Document::sole()->document_no);
    }

    public function test_a_typed_number_that_is_already_on_file_is_rejected(): void
    {
        $this->existing('PAR-2026-00001');

        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload(['document_no' => 'PAR-2026-00001']))
            ->assertSessionHasErrors('document_no');

        $this->assertSame(1, Document::count());
    }

    /**
     * The automatic number is one past the highest already used, not a row
     * count: with "PAR-2026-00002" on file a count would offer 00002 again and
     * the create would fail on the unique index.
     */
    public function test_automatic_numbering_survives_a_typed_number_ahead_of_it(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/documents', $this->payload(['document_no' => 'PAR-2026-00002']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post('/admin/documents', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['PAR-2026-00002', 'PAR-2026-00003'],
            Document::orderBy('id')->pluck('document_no')->all(),
        );
    }

    public function test_each_type_numbers_itself(): void
    {
        $this->existing('ICS-2026-00004', 'ICS');

        $this->actingAs($this->admin)->post('/admin/documents', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('PAR-' . now()->year . '-00001', Document::where('type', 'PAR')->sole()->document_no);
    }

    public function test_the_edit_form_offers_the_number_for_correction(): void
    {
        $document = $this->existing('PAR-2026-00001');

        $this->actingAs($this->admin)
            ->get("/admin/documents/{$document->id}/edit")
            ->assertOk()
            ->assertSee('name="document_no"', false)
            ->assertSee('value="PAR-2026-00001"', false)
            ->assertSee('Change it only to correct the number printed on the paper.');
    }

    public function test_a_typed_number_can_be_corrected(): void
    {
        $document = $this->existing('PAR-2026-00001');

        $this->actingAs($this->admin)
            ->put("/admin/documents/{$document->id}", $this->payload(['document_no' => 'PAR-2026-00123']))
            ->assertSessionHasNoErrors();

        $this->assertSame('PAR-2026-00123', $document->refresh()->document_no);
    }

    public function test_saving_the_edit_form_unchanged_keeps_the_number(): void
    {
        $document = $this->existing('PAR-2026-00001');

        // The uniqueness rule has to ignore the document being edited, or simply
        // saving the form would report its own number as taken.
        $this->actingAs($this->admin)
            ->put("/admin/documents/{$document->id}", $this->payload(['document_no' => 'PAR-2026-00001']))
            ->assertSessionHasNoErrors();

        $this->assertSame('PAR-2026-00001', $document->refresh()->document_no);
    }

    public function test_an_edit_that_sends_no_number_keeps_the_stored_one(): void
    {
        $document = $this->existing('PAR-2026-00001');
        $payload = $this->payload();
        unset($payload['document_no']);

        $this->actingAs($this->admin)
            ->put("/admin/documents/{$document->id}", $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('PAR-2026-00001', $document->refresh()->document_no);
    }

    public function test_an_edit_cannot_take_another_documents_number(): void
    {
        $document = $this->existing('PAR-2026-00001');
        $other = $this->existing('PAR-2026-00002');

        $this->actingAs($this->admin)
            ->put("/admin/documents/{$document->id}", $this->payload(['document_no' => $other->document_no]))
            ->assertSessionHasErrors('document_no');

        $this->assertSame('PAR-2026-00001', $document->refresh()->document_no);
    }
}
