<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The documents register filters by the document date, not the moment the row
 * was written, and the range is inclusive on both ends. Blank inputs leave the
 * list alone; the chosen range survives paging.
 */
class DocumentDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function document(string $date, string $status = 'draft', string $number = null): Document
    {
        return Document::create([
            'document_no' => $number ?? 'PAR-2026-' . str_pad((string) (Document::count() + 1), 5, '0', STR_PAD_LEFT),
            'type' => 'PAR',
            'status' => $status,
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'document_date' => $date,
        ]);
    }

    /** One document before, on, and after the range used by the tests. */
    private function threeDocuments(): array
    {
        return [
            'old' => $this->document('2026-08-01', 'draft', 'PAR-2026-00001'),
            'mid' => $this->document('2026-09-10', 'issued', 'PAR-2026-00002'),
            'new' => $this->document('2026-09-20', 'draft', 'PAR-2026-00003'),
        ];
    }

    /** The filter bar itself — the form marked for auto-submit. */
    private function filterBar(string $html): string
    {
        preg_match('/<form\b[^>]*data-auto-filter[^>]*>.*?<\/form>/s', $html, $matches);

        return $matches[0] ?? '';
    }

    public function test_the_filter_bar_applies_without_a_filter_button(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/documents')->assertOk()->getContent();
        $bar = $this->filterBar($html);

        $this->assertNotSame('', $bar, 'The register filter bar should be marked data-auto-filter.');
        $this->assertStringNotContainsString('<button', $bar);
        $this->assertStringContainsString('name="search"', $bar);
        $this->assertStringContainsString('name="type"', $bar);
        $this->assertStringContainsString('name="status"', $bar);
        $this->assertStringContainsString('name="date_from"', $bar);

        // And the fields share the one line on anything wider than a phone: the
        // row is held together and scrolls sideways rather than wrapping.
        $this->assertStringContainsString('sm:flex-nowrap', $bar);
        $this->assertStringContainsString('filter-scroll', $bar);
    }

    public function test_the_new_document_button_is_compact_and_named_for_the_type_picker(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/documents')->assertOk()->getContent();

        // The button leads to the type picker, so it is not named after PAR.
        $this->assertStringContainsString('New Document', $html);
        $this->assertStringNotContainsString('New PAR', $html);

        preg_match('/<a href="[^"]*documents\/create"[^>]*class="([^"]*)"[^>]*>/', $html, $matches);
        $classes = $matches[1] ?? '';

        $this->assertNotSame('', $classes, 'The register should offer a New Document button.');

        // It hugs its label and sits at the right end of the row instead of
        // stretching across it, and that row never stacks it under the pills.
        $this->assertStringContainsString('shrink-0', $classes);
        $this->assertStringNotContainsString('w-full', $classes);
        $this->assertStringNotContainsString('self-end', $classes);

        // The pills and the button share that one row: it is spaced between the
        // two ends and stacks them at no width (the layout's own column classes
        // are elsewhere on the page, so the row is read on its own).
        preg_match('/<div class="([^"]*)">\s*<div class="flex flex-wrap items-center gap-2">\s*<a href=/', $html, $row);
        $rowClasses = $row[1] ?? '';

        $this->assertNotSame('', $rowClasses, 'The pills and the New Document button should share a row.');
        $this->assertStringContainsString('justify-between', $rowClasses);
        $this->assertStringNotContainsString('flex-col', $rowClasses);
    }

    public function test_the_register_offers_from_and_to_date_inputs(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee('name="date_from"', false)
            ->assertSee('name="date_to"', false)
            ->assertSee('From')
            ->assertSee('To');
    }

    public function test_the_chosen_dates_are_kept_in_the_inputs(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/documents?date_from=2026-09-01&date_to=2026-09-15')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/name="date_from"\s+value="2026-09-01"/', $html);
        $this->assertMatchesRegularExpression('/name="date_to"\s+value="2026-09-15"/', $html);
    }

    public function test_date_from_hides_earlier_documents(): void
    {
        ['old' => $old, 'mid' => $mid, 'new' => $new] = $this->threeDocuments();

        $this->actingAs($this->admin)
            ->get('/admin/documents?date_from=2026-09-01')
            ->assertOk()
            ->assertDontSee($old->document_no)
            ->assertSee($mid->document_no)
            ->assertSee($new->document_no);
    }

    public function test_date_to_hides_later_documents(): void
    {
        ['old' => $old, 'mid' => $mid, 'new' => $new] = $this->threeDocuments();

        $this->actingAs($this->admin)
            ->get('/admin/documents?date_to=2026-09-01')
            ->assertOk()
            ->assertSee($old->document_no)
            ->assertDontSee($mid->document_no)
            ->assertDontSee($new->document_no);
    }

    public function test_the_range_includes_both_end_dates(): void
    {
        ['old' => $old, 'mid' => $mid, 'new' => $new] = $this->threeDocuments();

        $this->actingAs($this->admin)
            ->get('/admin/documents?date_from=2026-09-10&date_to=2026-09-10')
            ->assertOk()
            ->assertSee($mid->document_no)
            ->assertDontSee($old->document_no)
            ->assertDontSee($new->document_no);
    }

    public function test_blank_dates_do_not_filter_anything(): void
    {
        ['old' => $old, 'mid' => $mid, 'new' => $new] = $this->threeDocuments();

        $this->actingAs($this->admin)
            ->get('/admin/documents?date_from=&date_to=')
            ->assertOk()
            ->assertSee($old->document_no)
            ->assertSee($mid->document_no)
            ->assertSee($new->document_no);
    }

    public function test_the_date_range_combines_with_the_other_filters(): void
    {
        $par = $this->document('2026-09-10', 'draft', 'PAR-2026-00001');
        $ris = Document::create([
            'document_no' => 'RIS-2026-00001',
            'type' => 'RIS',
            'status' => 'draft',
            'entity_name' => 'Isabela State University - Cauayan Campus',
            'document_date' => '2026-09-11',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/documents?type=RIS&date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk()
            ->assertSee($ris->document_no)
            ->assertDontSee($par->document_no);
    }

    public function test_paging_keeps_the_date_range(): void
    {
        // Two pages' worth, so the paginator renders a link to page 2.
        for ($i = 1; $i <= 16; $i++) {
            $this->document('2026-09-1' . ($i % 10), 'draft', 'PAR-2026-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT));
        }

        $html = $this->actingAs($this->admin)
            ->get('/admin/documents?date_from=2026-09-01&date_to=2026-09-19')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('date_from=2026-09-01', $html);
        $this->assertStringContainsString('date_to=2026-09-19', $html);
        $this->assertStringContainsString('page=2', $html);
    }
}
