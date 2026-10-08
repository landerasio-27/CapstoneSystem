<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\Card;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The archive: years are added from the sidebar page, and PAR / ICS / RIS
 * documents and Stock / Property Cards are filed under them. Filing never
 * deletes or copies: the record keeps its history and simply leaves the
 * working lists until it is restored.
 */
class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $staffRole = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Inspection Staff']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $this->staff = User::factory()->create(['role_id' => $staffRole->id, 'is_active' => true]);
    }

    private function document(string $number = 'PAR-2026-00001'): Document
    {
        return Document::create([
            'document_no' => $number,
            'type' => 'PAR',
            'status' => 'issued',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'document_date' => '2026-08-01',
        ]);
    }

    private function supply(): Supply
    {
        return Supply::create([
            'item_code' => 'SUP-00001',
            'name' => 'Bond Paper A4 (Ream)',
            'category' => 'Office Supplies',
            'unit' => 'ream',
            'quantity_on_hand' => 50,
            'unit_cost' => 220,
            'reorder_level' => 0,
            'is_active' => true,
        ]);
    }

    private function equipment(): Equipment
    {
        return Equipment::create([
            'item_code' => 'EQP-00001',
            'name' => 'Epson LCD Projector',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 28000,
            'reorder_level' => 0,
            'status' => 'available',
        ]);
    }

    /** A Stock Card (SC) or Property Card (PC), the way the office writes one. */
    private function card(string $type = 'SC', ?string $cardNo = null): Card
    {
        $stock = $type === 'SC';

        return Card::create([
            'type' => $type,
            'card_no' => $cardNo ?? ($stock ? 'SC-00001' : 'PC-00001'),
            'supply_id' => $stock ? $this->supply()->id : null,
            'equipment_id' => $stock ? null : $this->equipment()->id,
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'created_by' => $this->admin->id,
        ]);
    }

    private function archive(int $year = 2025): Archive
    {
        return Archive::create(['year' => $year, 'created_by' => $this->admin->id]);
    }

    public function test_the_archive_is_reached_from_the_settings_panel_not_the_sidebar(): void
    {
        $archive = $this->archive(2025);

        // The archive is a panel of the settings page, so the sidebar no longer
        // carries a door of its own.
        $dashboard = $this->actingAs($this->admin)->get('/admin/dashboard')->getContent();
        $this->assertStringNotContainsString('href="' . route('admin.archive.index') . '"', $dashboard);

        // The panel is the register itself — the documents and the cards still
        // waiting to be filed — and a year opens inside it.
        $settings = $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=archive')
            ->assertOk()
            ->assertSee('Archive years')
            ->assertSee('Documents &mdash; PAR / ICS / RIS', false)
            ->assertSee('Stock / Property Cards')
            ->assertSee('Archive Selected')
            ->getContent();
        $this->assertStringContainsString(
            'href="' . e(route('admin.account-settings.index', ['section' => 'archive', 'archive' => $archive->id])) . '"',
            $settings,
        );

        // Filing records is administrator work, like the registers themselves.
        $this->actingAs($this->staff)->get('/admin/archive')->assertForbidden();
        $this->assertStringNotContainsString(
            'Archive Selected',
            $this->actingAs($this->staff)->get('/admin/account-settings?section=profile')->getContent(),
        );
        // A section the role may not open falls back rather than reading it.
        $this->assertStringNotContainsString(
            'Archive Selected',
            $this->actingAs($this->staff)->get('/admin/account-settings?section=archive')->getContent(),
        );
    }

    /**
     * A year opened from the settings panel is shown there: the reader files,
     * opens a year, and restores all without leaving Settings.
     */
    public function test_a_year_opens_inside_the_settings_panel(): void
    {
        $archive = $this->archive(2025);
        $document = $this->document();
        $card = $this->card();

        $this->actingAs($this->admin)->post('/admin/archive/documents', [
            'archive_id' => $archive->id,
            'documents' => [$document->id],
        ]);
        $this->actingAs($this->admin)->post('/admin/archive/cards', [
            'archive_id' => $archive->id,
            'cards' => [$card->id],
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.account-settings.index', ['section' => 'archive', 'archive' => $archive->id]))
            ->assertOk()
            ->assertSee('PAR-2026-00001')
            ->assertSee('SC-00001')
            ->assertSee('Restore')
            // Still the settings page, with its own sections around the year,
            // and the register one link away instead of a page of its own.
            ->assertSee('aria-label="Account settings sections"', false)
            ->assertSee('Back to the register')
            ->assertSee(route('admin.archive.documents.restore', $document), false)
            ->getContent();

        // While a year is open the register itself is put away with it.
        $this->assertStringNotContainsString('Archive Selected', $html);

        // A year that does not exist leaves the panel on the register rather
        // than failing the page.
        $this->actingAs($this->admin)
            ->get(route('admin.account-settings.index', ['section' => 'archive', 'archive' => 999]))
            ->assertOk()
            ->assertSee('Archive Selected');
    }

    public function test_the_archive_page_lists_the_years_and_the_working_records(): void
    {
        $this->document();
        $this->card();

        $this->actingAs($this->admin)->get('/admin/archive')
            ->assertOk()
            ->assertSee('Archive years')
            ->assertSee('Add Year')
            ->assertSee('PAR-2026-00001')
            ->assertSee('SC-00001')
            ->assertSee('No archive years yet');
    }

    public function test_a_year_can_be_added(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/archive', ['year' => 2025, 'notes' => 'Old office records'])
            ->assertSessionHasNoErrors();

        $archive = Archive::sole();

        $this->assertSame(2025, $archive->year);
        $this->assertSame('Old office records', $archive->notes);

        $this->actingAs($this->admin)->get('/admin/archive')->assertOk()->assertSee('2025');
    }

    public function test_the_same_year_cannot_be_added_twice(): void
    {
        $this->archive();

        $this->actingAs($this->admin)
            ->post('/admin/archive', ['year' => 2025])
            ->assertSessionHasErrors('year');

        $this->assertSame(1, Archive::count());
    }

    public function test_documents_are_filed_under_a_year_and_leave_the_register(): void
    {
        $archive = $this->archive();
        $document = $this->document();

        $this->actingAs($this->admin)
            ->post('/admin/archive/documents', [
                'archive_id' => $archive->id,
                'documents' => [$document->id],
            ])
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame($archive->id, $document->archive_id);
        $this->assertNotNull($document->archived_at);

        // Gone from the register, kept on the year's own page.
        $this->actingAs($this->admin)->get('/admin/documents')->assertOk()->assertDontSee('PAR-2026-00001');
        $this->actingAs($this->admin)->get("/admin/archive/{$archive->id}")
            ->assertOk()
            ->assertSee('PAR-2026-00001');
    }

    public function test_cards_are_filed_under_a_year_and_leave_the_register(): void
    {
        $archive = $this->archive();
        $card = $this->card();

        $this->actingAs($this->admin)
            ->post('/admin/archive/cards', [
                'archive_id' => $archive->id,
                'cards' => [$card->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($archive->id, $card->refresh()->archive_id);
        $this->assertNotNull($card->archived_at);

        // Gone from the cards register, kept on the year's own page.
        $this->actingAs($this->admin)->get('/admin/cards')->assertOk()->assertDontSee('SC-00001');
        $this->actingAs($this->admin)->get("/admin/archive/{$archive->id}")
            ->assertOk()
            ->assertSee('SC-00001')
            ->assertSee('Bond Paper A4 (Ream)');
    }

    public function test_a_property_card_is_filed_the_same_way(): void
    {
        $archive = $this->archive(2024);
        $card = $this->card('PC');

        $this->actingAs($this->admin)
            ->post('/admin/archive/cards', ['archive_id' => $archive->id, 'cards' => [$card->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame($archive->id, $card->refresh()->archive_id);

        $this->actingAs($this->admin)->get('/admin/cards')->assertOk()->assertDontSee('PC-00001');
        $this->actingAs($this->admin)->get("/admin/archive/{$archive->id}?card_type=PC")
            ->assertOk()
            ->assertSee('PC-00001')
            ->assertSee('Epson LCD Projector');
    }

    /**
     * A filed year is a record of what was put away, not a workspace: the
     * numbers are plain text and the only action is putting a record back.
     */
    public function test_a_filed_year_is_read_only_and_offers_only_restore(): void
    {
        $archive = $this->archive();
        $document = $this->document();
        $card = $this->card();

        $this->actingAs($this->admin)->post('/admin/archive/documents', [
            'archive_id' => $archive->id,
            'documents' => [$document->id],
        ]);
        $this->actingAs($this->admin)->post('/admin/archive/cards', [
            'archive_id' => $archive->id,
            'cards' => [$card->id],
        ]);

        $html = $this->actingAs($this->admin)->get("/admin/archive/{$archive->id}")
            ->assertOk()
            ->assertSee('PAR-2026-00001')
            ->assertSee('SC-00001')
            ->getContent();

        // Nothing opens the working registers or the card workspace.
        foreach ([
            route('admin.documents.show', $document),
            route('admin.cards.show', $card),
            route('admin.cards.sync', $card),
            route('admin.cards.edit', $card),
            route('admin.cards.print', $card),
            route('admin.cards.pdf', $card),
        ] as $offLimits) {
            $this->assertStringNotContainsString($offLimits, $html);
        }

        $this->assertStringNotContainsString('Add Trans.', $html);

        // The one action left puts the record back in the register.
        $this->assertStringContainsString(route('admin.archive.documents.restore', $document), $html);
        $this->assertStringContainsString(route('admin.archive.cards.restore', $card), $html);
    }

    public function test_a_filed_record_can_be_restored(): void
    {
        $archive = $this->archive();
        $document = $this->document();
        $card = $this->card();

        $this->actingAs($this->admin)->post('/admin/archive/documents', [
            'archive_id' => $archive->id,
            'documents' => [$document->id],
        ]);
        $this->actingAs($this->admin)->post('/admin/archive/cards', [
            'archive_id' => $archive->id,
            'cards' => [$card->id],
        ]);

        $this->actingAs($this->admin)
            ->patch("/admin/archive/documents/{$document->id}/restore")
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->patch("/admin/archive/cards/{$card->id}/restore")
            ->assertSessionHasNoErrors();

        $this->assertNull($document->refresh()->archive_id);
        $this->assertNull($document->archived_at);
        $this->assertNull($card->refresh()->archive_id);
        $this->assertNull($card->archived_at);

        $this->actingAs($this->admin)->get('/admin/documents')->assertOk()->assertSee('PAR-2026-00001');
        $this->actingAs($this->admin)->get('/admin/cards')->assertOk()->assertSee('SC-00001');
    }

    /**
     * A year is permanent: no archive year can be deleted, so a filed record can
     * never lose the year it belongs to. Records leave only by being restored,
     * which is what keeps the year itself intact.
     */
    public function test_an_archive_year_cannot_be_deleted(): void
    {
        $archive = $this->archive();
        $document = $this->document();

        $this->actingAs($this->admin)->post('/admin/archive/documents', [
            'archive_id' => $archive->id,
            'documents' => [$document->id],
        ]);

        $this->assertFalse(Route::has('admin.archive.destroy'));

        $this->actingAs($this->admin)
            ->delete("/admin/archive/{$archive->id}")
            ->assertStatus(405);

        $this->assertSame(1, Archive::count());

        // The record can still be restored to the working lists; the year stays.
        $this->actingAs($this->admin)
            ->patch("/admin/archive/documents/{$document->id}/restore")
            ->assertSessionHasNoErrors();

        $this->assertNull($document->refresh()->archive_id);
        $this->assertSame(1, Archive::count());
    }

    public function test_filing_nothing_archives_nothing(): void
    {
        $archive = $this->archive();
        $this->card();

        $this->actingAs($this->admin)
            ->post('/admin/archive/cards', ['archive_id' => $archive->id])
            ->assertSessionHasErrors('cards');

        $this->assertSame(0, Card::whereNotNull('archive_id')->count());
    }

    public function test_an_unknown_year_is_rejected(): void
    {
        $document = $this->document();

        $this->actingAs($this->admin)
            ->post('/admin/archive/documents', ['archive_id' => 999, 'documents' => [$document->id]])
            ->assertSessionHasErrors('archive_id');

        $this->assertNull($document->refresh()->archive_id);
    }
}
