<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property and supplies change hands when a document is issued, so the printed
 * "Received by" date is stamped at that moment instead of being left blank.
 */
class DocumentReceiptDateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function createDocument(string $type = 'PAR'): Document
    {
        $equipment = Equipment::firstOrCreate(
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

        $this->actingAs($this->admin)->post('/admin/documents', [
            'type' => $type,
            'document_date' => '2026-09-22',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'items' => [
                ['item' => 'equipment:' . $equipment->id, 'quantity' => 1, 'date_acquired' => '2026-09-20'],
            ],
        ])->assertRedirect();

        return Document::latest('id')->firstOrFail();
    }

    /** The printed form, rendered the way the PDF controller renders it. */
    private function printed(Document $document, string $form): string
    {
        $document->load(['teacher', 'room', 'preparedBy', 'approvedBy', 'issuedBy', 'items.equipment', 'items.supply']);

        return view("admin.documents.forms.{$form}", ['document' => $document])->render();
    }

    public function test_issuing_stamps_the_date_the_document_was_received(): void
    {
        $document = $this->createDocument('PAR');

        $this->assertNull($document->date_received);

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/approve");
        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue")->assertRedirect();

        $document->refresh();

        $this->assertSame('issued', $document->status);
        $this->assertNotNull($document->date_received);
        // The receipt is dated the day it was issued, not left for later.
        $this->assertSame($document->issued_at->toDateString(), $document->date_received->toDateString());
        $this->assertStringContainsString(
            $document->date_received->format('m/d/Y'),
            $this->printed($document, 'par'),
        );
    }

    public function test_a_date_recorded_by_the_office_is_not_overwritten_on_issue(): void
    {
        $document = $this->createDocument('PAR');

        // The office entered its own Date Received before issuing.
        $document->update(['date_received' => '2026-09-25']);

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/approve");
        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");

        $this->assertSame('2026-09-25', $document->refresh()->date_received->toDateString());
    }

    public function test_the_ics_received_date_is_filled_too(): void
    {
        $document = $this->createDocument('ICS');

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/approve");
        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");

        $document->refresh();

        $this->assertNotNull($document->date_received);
        $this->assertStringContainsString(
            $document->date_received->format('m/d/Y'),
            $this->printed($document, 'ics'),
        );
    }

    public function test_the_ris_received_column_is_filled_too(): void
    {
        $supply = \App\Models\Supply::create([
            'item_code' => 'SUP-00001',
            'name' => 'Bond Paper A4',
            'category' => 'Office Supplies',
            'unit' => 'ream',
            'quantity_on_hand' => 50,
            'unit_cost' => 220,
            'reorder_level' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->post('/admin/documents', [
            'type' => 'RIS',
            'document_date' => '2026-09-22',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'items' => [['item' => 'supply:' . $supply->id, 'quantity' => 2]],
        ])->assertRedirect();

        $document = Document::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/approve");
        $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/issue");

        $document->refresh();

        $this->assertNotNull($document->date_received);
        $this->assertStringContainsString(
            $document->date_received->format('m/d/Y'),
            $this->printed($document, 'ris'),
        );
    }

    public function test_a_document_issued_before_this_change_still_prints_a_date(): void
    {
        $document = $this->createDocument('PAR');

        // An issued document that never had a receipt date recorded.
        $document->update(['status' => 'issued', 'issued_by' => $this->admin->id, 'issued_at' => '2026-09-23 10:00:00']);

        $this->assertNull($document->refresh()->date_received);
        $this->assertStringContainsString('09/23/2026', $this->printed($document, 'par'));
    }
}
