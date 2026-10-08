<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\Room;
use App\Models\Supply;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Custodian Folders — one custody folder per custodian, filled automatically.
 *
 * The folder holds no records of its own: it is the custodian's `teachers` row
 * seen together with the documents and equipment that name it. These tests pin
 * that down from the outside — a paper or an item assigned anywhere else in the
 * system turns up in the folder, the counts are read from the relations, and
 * the folder page offers the two ways to fill it.
 */
class CustodianFolderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => Role::ADMIN, 'display_name' => 'Admin']);
        $staff = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Supply Staff']);

        $this->admin = User::factory()->create(['role_id' => $admin->id, 'is_active' => true]);
        $this->staff = User::factory()->create(['role_id' => $staff->id, 'is_active' => true]);
    }

    private function room(array $attributes = []): Room
    {
        return Room::create($attributes + [
            'code' => 'LR-2',
            'name' => 'Lecture Room 2',
            'building' => 'College of Business',
            'floor' => '2nd Floor',
            'is_active' => true,
        ]);
    }

    private function custodian(array $attributes = []): Teacher
    {
        return Teacher::create($attributes + [
            'employee_id' => 'FAC-'.str_pad((string) (Teacher::count() + 1), 4, '0', STR_PAD_LEFT),
            'first_name' => 'Cristina',
            'last_name' => 'Bautista',
            'department' => 'College of Business',
            'position' => 'Associate Professor',
            'contact_number' => '09171234567',
            'is_active' => true,
        ]);
    }

    private function equipment(array $attributes = []): Equipment
    {
        return Equipment::create($attributes + [
            'item_code' => 'EQP-'.str_pad((string) (Equipment::count() + 1), 5, '0', STR_PAD_LEFT),
            'name' => 'Dell OptiPlex Desktop',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 35000,
            'reorder_level' => 0,
            'status' => 'available',
        ]);
    }

    private function supply(array $attributes = []): Supply
    {
        return Supply::create($attributes + [
            'item_code' => 'SUP-'.str_pad((string) (Supply::count() + 1), 5, '0', STR_PAD_LEFT),
            'name' => 'Bond Paper A4 (Ream)',
            'category' => 'Office Supplies',
            'unit' => 'ream',
            'quantity_on_hand' => 50,
            'unit_cost' => 220,
            'reorder_level' => 0,
            'is_active' => true,
        ]);
    }

    private function document(array $attributes = []): Document
    {
        return Document::create($attributes + [
            'document_no' => 'PAR-2026-'.str_pad((string) (Document::count() + 1), 5, '0', STR_PAD_LEFT),
            'type' => 'PAR',
            'status' => 'draft',
            'document_date' => now()->toDateString(),
        ]);
    }

    /**
     * A paper raised through the form, for a custodian: the workflow the office
     * actually uses, so the document is filed by the save and not by a test.
     */
    private function raiseDocument(Teacher $custodian, string $type = 'PAR'): Document
    {
        $item = $type === 'RIS'
            ? 'supply:'.$this->supply()->id
            : 'equipment:'.$this->equipment()->id;

        $this->actingAs($this->admin)
            ->post('/admin/documents', [
                'type' => $type,
                'document_date' => now()->toDateString(),
                'entity_name' => 'Isabela State University - Cauayan Campus',
                'teacher_id' => $custodian->id,
                'items' => [['item' => $item, 'quantity' => 1]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return Document::latest('id')->firstOrFail();
    }

    // ---------------------------------------------------------------------
    // The grid
    // ---------------------------------------------------------------------

    /**
     * The folder page's header card — the one standing over the custodian's own
     * name — so what it does and does not offer can be read on its own.
     */
    private function folderHeader(string $html, Teacher $custodian): string
    {
        $header = $this->element($html, '//div[contains(@class, "rounded-xl")][.//h2]');

        $this->assertStringContainsString(
            $custodian->full_name,
            $header,
            'The header card on the page is not this custodian\'s.'
        );

        return $header;
    }

    /**
     * The markup of one element found by an XPath query, so a sheet or a card
     * can be read without the page around it. The page has no ids to look up
     * with getElementById: an id is only an id to libxml when a DTD says so.
     */
    private function element(string $html, string $xpath): string
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $node = (new \DOMXPath($document))->query($xpath)->item(0);

        $this->assertNotNull($node, "No element matched {$xpath} on the page.");

        return (string) $document->saveHTML($node);
    }

    /**
     * The markup of one folder tile, taken out of the page around it, so what a
     * tile itself carries is readable on its own rather than through the page
     * around it.
     */
    private function folderTile(string $html, Teacher $custodian): string
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $tile = (new \DOMXPath($document))
            ->query('//*[@data-href="'.route('admin.teachers.show', $custodian).'"]')
            ->item(0);

        $this->assertNotNull($tile, 'The grid drew no folder tile for this custodian.');

        return (string) $document->saveHTML($tile);
    }

    public function test_the_grid_draws_each_custodian_as_a_folder_carrying_their_name(): void
    {
        $custodian = $this->custodian(['room_id' => $this->room()->id]);
        $this->document(['teacher_id' => $custodian->id, 'document_no' => 'PAR-2026-00004']);
        $this->document(['teacher_id' => $custodian->id, 'document_no' => 'ICS-2026-00012', 'type' => 'ICS']);
        $this->equipment(['teacher_id' => $custodian->id, 'name' => 'Dell OptiPlex Desktop']);

        $html = $this->actingAs($this->admin)->get('/admin/teachers')
            ->assertOk()
            ->assertSee('Custodian Folders')
            ->assertSee('New Folder')
            ->assertSee('Search folders...')
            ->assertSee('Cristina Bautista')
            ->getContent();

        // The tile is a folder with its name under it, and it opens the folder
        // when it is clicked. The illustration itself is drawn — the gold of the
        // folder, not a monochrome glyph.
        $this->assertStringContainsString('viewBox="0 0 48 44"', $html);

        // The folder is drawn, not glyphed: a gold back with its tab standing up
        // behind a paler front, the way a folder in a drawer is seen.
        $this->assertStringContainsString('#E9A32B', $html);
        $this->assertStringContainsString('#FADD9E', $html);

        // Nothing else is written on the tile: no employee ID, no status chip,
        // no pencil. The record is read on the folder page the tile opens.
        $tile = $this->folderTile($html, $custodian);

        $this->assertStringContainsString('data-href="'.route('admin.teachers.show', $custodian).'"', $tile);
        $this->assertStringContainsString('Cristina Bautista', $tile);
        $this->assertStringNotContainsString($custodian->employee_id, $tile);
        $this->assertStringNotContainsString('Active', $tile);
        $this->assertStringNotContainsString('<a ', $tile);

        // Nothing is drawn round the folder either: no card, no ring, no shadow.
        // The illustration and the name are the tile.
        foreach (['rounded-xl', 'ring-1', 'shadow', 'bg-white'] as $chrome) {
            $this->assertStringNotContainsString($chrome, $tile);
        }

        // A cabinet, not cards in a table: many folders across, each one the size
        // a folder in the cabinet is drawn at rather than as wide as its cell,
        // sitting close together with the name just under it.
        $this->assertStringContainsString('gap-x-3 gap-y-4', $html);
        $this->assertStringContainsString('lg:grid-cols-6', $html);
        $this->assertStringContainsString('px-1 py-2', $tile);
        $this->assertStringContainsString('class="w-full max-w-28 ', $tile);
        $this->assertMatchesRegularExpression('/class="mt-2 line-clamp-2 text-sm font-semibold/', $tile);

        // What is in the folder is written on the tile's tooltip rather than as
        // a row of its own, so the grid stays a cabinet of folders.
        $this->assertStringContainsString('2 documents, 1 equipment', $tile);
    }

    public function test_the_grid_searches_names_departments_employee_ids_and_rooms(): void
    {
        $this->custodian(['room_id' => $this->room()->id]);
        $other = $this->custodian([
            'first_name' => 'Angelica',
            'last_name' => 'Fernandez',
            'department' => 'College of Computing',
            'position' => 'Instructor',
            'room_id' => $this->room(['code' => 'CL-1', 'name' => 'Computer Laboratory 1'])->id,
        ]);

        // Which folders the search leaves on the grid. The sheet that stands
        // over the grid still lists the whole directory, so the check is made
        // against the folders shown, not the whole page.
        $found = fn (string $query) => $this->actingAs($this->admin)
            ->get('/admin/teachers?search='.urlencode($query))
            ->assertOk()
            ->viewData('teachers')
            ->pluck('last_name')
            ->all();

        $this->assertSame(['Bautista'], $found('Business'));   // department
        $this->assertSame(['Fernandez'], $found('Computer Laboratory')); // room
        $this->assertSame(['Fernandez'], $found($other->employee_id));   // employee ID
        $this->assertSame(['Fernandez'], $found('Angelica'));  // first name
        $this->assertSame([], $found('Nothing Here'));
    }

    /**
     * The grid is a cabinet of folders, not a report: it carries no Faculty /
     * Staff / Active / With Documents / With Equipment chips to cut it up. The
     * whole directory is shown, and a leftover filter= in an old link is
     * ignored rather than quietly hiding someone's folder.
     */
    public function test_the_grid_has_no_filter_chips_and_ignores_a_leftover_filter(): void
    {
        $faculty = $this->custodian(['position' => 'Associate Professor']);
        $this->custodian([
            'first_name' => 'Ricardo', 'last_name' => 'Ramos',
            'department' => 'Supply Office', 'position' => 'Administrative Aide III',
        ]);
        $this->document(['teacher_id' => $faculty->id]);
        $this->equipment(['teacher_id' => $faculty->id]);

        $page = $this->actingAs($this->admin)->get('/admin/teachers')->assertOk();

        // None of the chip labels are drawn, and nothing on the page links to a
        // filtered grid any more. ("Active" is left out of the list: it is also
        // the status chip each folder carries.)
        foreach (['Faculty', 'Staff', 'With Documents', 'With Equipment'] as $chip) {
            $page->assertDontSee($chip);
        }

        $page->assertDontSee('filter=', false);

        // Every folder is on the grid, including one that no chip would keep.
        $this->custodian(['first_name' => 'Rosa', 'last_name' => 'Lim', 'is_active' => false]);

        $shown = fn (string $query) => $this->actingAs($this->admin)
            ->get('/admin/teachers'.$query)
            ->assertOk()
            ->viewData('teachers')
            ->pluck('last_name')
            ->sort()
            ->values()
            ->all();

        $everything = ['Bautista', 'Lim', 'Ramos'];

        $this->assertSame($everything, $shown(''));
        $this->assertSame($everything, $shown('?filter=active'));
        $this->assertSame($everything, $shown('?filter=equipment'));

        // The custodian who has left keeps their folder, and the state is on
        // their tile's tooltip rather than as a chip on the folder.
        $departed = Teacher::query()->where('last_name', 'Lim')->firstOrFail();

        $this->assertStringContainsString(
            '· Inactive',
            $this->folderTile($this->actingAs($this->admin)->get('/admin/teachers')->getContent(), $departed)
        );
    }

    public function test_the_grid_sorts_by_name_documents_equipment_and_recency(): void
    {
        $many = $this->custodian(['first_name' => 'Zara', 'last_name' => 'Abad']);
        $few = $this->custodian(['first_name' => 'Ana', 'last_name' => 'Zamora']);

        foreach (range(1, 3) as $i) {
            $this->document(['teacher_id' => $many->id, 'document_no' => "PAR-2026-0000{$i}"]);
        }

        $this->equipment(['teacher_id' => $few->id]);

        $order = fn (string $query) => $this->actingAs($this->admin)
            ->get('/admin/teachers?'.$query)
            ->assertOk()
            ->viewData('teachers')
            ->pluck('id')
            ->all();

        $this->assertSame([$many->id, $few->id], $order('sort=name_asc'));
        $this->assertSame([$few->id, $many->id], $order('sort=name_desc'));
        $this->assertSame([$many->id, $few->id], $order('sort=documents'));
        $this->assertSame([$few->id, $many->id], $order('sort=equipment'));

        // The folder that changed last is read off its newest document.
        $many->documents()->latest('id')->firstOrFail()->touch();

        $this->assertSame($many->id, $order('sort=recent')[0]);
    }

    // ---------------------------------------------------------------------
    // The folder itself
    // ---------------------------------------------------------------------

    public function test_a_folder_page_shows_the_custodian_their_documents_and_their_property(): void
    {
        $custodian = $this->custodian(['room_id' => $this->room()->id]);
        $this->document([
            'teacher_id' => $custodian->id,
            'document_no' => 'PAR-2026-00004',
            'document_date' => '2026-10-07',
        ]);
        $this->equipment([
            'teacher_id' => $custodian->id,
            'name' => 'Dell OptiPlex Desktop',
            'serial_number' => 'SN-778812',
        ]);

        // The folder opens on its papers, under a header naming its custodian.
        $page = $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->assertSee('Cristina Bautista')
            ->assertSee('College of Business')
            ->assertSee('Edit Information')
            ->assertSee('Property Acknowledgement Receipt')
            ->assertSee('PAR-2026-00004')
            ->assertSee('View')
            ->assertSee('Download');

        // The header names the custodian and their department. The record's own
        // number is the system's, and is not printed on the page.
        $header = $this->folderHeader($page->getContent(), $custodian);

        $this->assertStringContainsString('Cristina Bautista', $header);
        $this->assertStringContainsString('College of Business', $header);
        $this->assertStringNotContainsString($custodian->employee_id, $header);
        $page->assertDontSee($custodian->employee_id);

        // The rest of the record is edited where it is kept.
        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}/edit")
            ->assertOk()
            ->assertSee('Associate Professor')
            ->assertSee('Lecture Room 2')
            ->assertSee('09171234567');

        // The property is the other tab of the same folder.
        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}?panel=equipment")
            ->assertOk()
            ->assertSee('Assigned Equipment')
            ->assertSee('Dell OptiPlex Desktop')
            ->assertSee('SN-778812')
            ->assertSee('Assign Equipment')
            // The papers are the other tab: their list and search are not drawn
            // underneath the property table.
            ->assertDontSee('Search documents...');
    }

    /**
     * The two halves of a folder are nav tabs, each carrying its own count, and
     * the panel that is open is the one the address asks for.
     */
    public function test_the_folder_reads_its_papers_and_its_property_as_tabs(): void
    {
        $custodian = $this->custodian();
        $this->document(['teacher_id' => $custodian->id, 'document_no' => 'PAR-2026-00004']);
        $this->document(['teacher_id' => $custodian->id, 'document_no' => 'ICS-2026-00012', 'type' => 'ICS']);
        $this->equipment(['teacher_id' => $custodian->id, 'name' => 'Dell OptiPlex Desktop']);

        $documents = $this->actingAs($this->admin)
            ->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->assertSee('Add Document')
            ->assertSee('Search documents...')
            ->assertSee('PAR-2026-00004')
            // The property table belongs to the other tab.
            ->assertDontSee('Property assigned to this custodian');

        $this->assertSame('documents', $documents->viewData('panel'));

        // Each tab is named with the count of what it holds.
        $html = $documents->getContent();
        $this->assertMatchesRegularExpression('/Documents\s*<span[^>]*>\s*2\s*<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Assigned Equipment\s*<span[^>]*>\s*1\s*<\/span>/', $html);
        // The other half of the folder has an address of its own.
        $this->assertStringContainsString('panel=equipment', $html);

        // Adding a paper or a piece of property is offered on the tab that holds
        // it, so the header above them carries only the record's own action
        // rather than repeating both.
        $header = $this->folderHeader($html, $custodian);

        $this->assertStringContainsString('Edit Information', $header);
        $this->assertStringNotContainsString('Add Document', $header);
        $this->assertStringNotContainsString('Assign Equipment', $header);

        $equipment = $this->actingAs($this->admin)
            ->get("/admin/teachers/{$custodian->id}?panel=equipment")
            ->assertOk()
            ->assertSee('Property assigned to this custodian')
            ->assertSee('Dell OptiPlex Desktop')
            ->assertSee('Serial')
            ->assertSee('Assign Equipment')
            ->assertDontSee('Search documents...');

        $this->assertSame('equipment', $equipment->viewData('panel'));
    }

    /**
     * On the property tab an assigned item is opened from its row, the way it is
     * opened in the register: the name reads as plain text instead of being the
     * one link in the row.
     */
    public function test_an_assigned_item_opens_its_equipment_record_from_the_row(): void
    {
        $custodian = $this->custodian();
        $equipment = $this->equipment([
            'teacher_id' => $custodian->id,
            'name' => 'Dell OptiPlex Desktop',
            'item_code' => 'EQP-00001',
        ]);

        $html = $this->actingAs($this->admin)
            ->get("/admin/teachers/{$custodian->id}?panel=equipment")
            ->assertOk()
            ->assertSee('Dell OptiPlex Desktop')
            ->getContent();

        $url = route('admin.equipment.show', $equipment);

        // The row carries the destination ...
        $this->assertStringContainsString('<tr data-href="'.$url.'"', $html);

        // ... the name is the plain text it reads as ...
        $this->assertStringContainsString('<span class="font-medium text-slate-800">Dell OptiPlex Desktop</span>', $html);

        // ... and no cell is a link of its own any more.
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*href="'.preg_quote($url, '/').'"/', $html);
    }

    /**
     * The record is edited on the folder page itself: Edit Information opens the
     * "Edit Custodian Information" sheet with what the record already says, and
     * saving it comes back to the folder.
     */
    public function test_edit_information_opens_the_record_in_a_sheet(): void
    {
        $room = $this->room();
        $custodian = $this->custodian([
            'room_id' => $room->id,
            'email' => 'cristina@isu.edu.ph',
        ]);

        $html = $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->assertSee('Edit Information')
            ->getContent();

        // The button opens the sheet here rather than sending the reader off to
        // the edit page.
        $this->assertStringContainsString("onclick=\"ISUModal.open('edit-information-modal')\"", $html);
        $this->assertStringNotContainsString('href="'.route('admin.teachers.edit', $custodian).'"', $html);

        // Nothing has been refused, so the sheet is on the page but shut: it is
        // only reopened by its own mistakes.
        $this->assertStringNotContainsString("<script>ISUModal.open('edit-information-modal');</script>", $html);

        // The sheet is titled for what it does, and carries the record as it
        // stands — down to which room the person sits in.
        $sheet = $this->element($html, '//*[@id="edit-information-modal"]');

        $this->assertStringContainsString('Edit Custodian Information', $sheet);
        $this->assertStringContainsString('value="Cristina"', $sheet);
        $this->assertStringContainsString('value="Bautista"', $sheet);
        $this->assertStringContainsString('value="College of Business"', $sheet);
        $this->assertStringContainsString('value="Associate Professor"', $sheet);
        $this->assertStringContainsString('value="cristina@isu.edu.ph"', $sheet);
        $this->assertStringContainsString('value="09171234567"', $sheet);
        $this->assertStringContainsString('<option value="'.$room->id.'" selected>', $sheet);

        // Saving it comes back to the folder, with the record changed.
        $this->actingAs($this->admin)->put("/admin/teachers/{$custodian->id}", [
            'first_name' => 'Cristina',
            'last_name' => 'Bautista',
            'department' => 'College of Computing Studies',
            'position' => 'Professor',
            'email' => 'cristina@isu.edu.ph',
            'contact_number' => '09991234567',
            'room_id' => $room->id,
            'is_active' => '1',
        ])->assertRedirect(route('admin.teachers.show', $custodian))->assertSessionHasNoErrors();

        $custodian->refresh();

        $this->assertSame('College of Computing Studies', $custodian->department);
        $this->assertSame('Professor', $custodian->position);
        $this->assertSame('09991234567', $custodian->contact_number);

        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->assertSee('College of Computing Studies');
    }

    /**
     * A save the sheet refuses puts the reader back in the sheet, with the
     * reason, instead of dropping them on the folder to guess what went wrong.
     */
    public function test_a_refused_save_puts_the_information_sheet_back_on_screen(): void
    {
        $custodian = $this->custodian(['email' => 'cristina@isu.edu.ph']);

        // The reader is on the folder page, which is where the sheet lives, and
        // the redirect is followed the way a browser follows it, so the page
        // that comes back is the one carrying what was refused.
        $page = $this->actingAs($this->admin)
            ->from("/admin/teachers/{$custodian->id}")
            ->followingRedirects()
            ->put("/admin/teachers/{$custodian->id}", [
                'first_name' => '',
                'last_name' => 'Bautista',
                'department' => 'College of Business',
                // Typed but not kept: the sheet comes back holding what the
                // office entered, not what the record still says.
                'position' => 'Professor',
                'email' => 'not-an-email',
                'contact_number' => '09171234567',
                'is_active' => '1',
            ])
            ->assertOk()
            ->getContent();

        // The sheet is reopened on itself — the script, not merely the button
        // that is always on the page — with the reason beside the field.
        $this->assertStringContainsString("<script>ISUModal.open('edit-information-modal');</script>", $page);
        $this->assertStringContainsString('The first name field is required.', $page);
        $this->assertStringContainsString('The email field must be a valid email address.', $page);

        // The page can only reopen the sheet because the plumbing is in place
        // before the sheet asks for it: the definition has to be read first.
        $this->assertStringContainsString('window.ISUModal = {', $page);
        $this->assertLessThan(
            strpos($page, "<script>ISUModal.open('edit-information-modal');</script>"),
            strpos($page, 'window.ISUModal = {'),
        );

        $sheet = $this->element($page, '//*[@id="edit-information-modal"]');
        $this->assertStringContainsString('value="Professor"', $sheet);

        // The record itself is left as it was, down to the fields the sheet was
        // given: a refused save changes nothing.
        $custodian->refresh();
        $this->assertSame('Cristina', $custodian->first_name);
        $this->assertSame('Associate Professor', $custodian->position);
        $this->assertSame('cristina@isu.edu.ph', $custodian->email);
    }

    public function test_a_folder_with_no_property_says_so_on_the_property_tab(): void
    {
        $custodian = $this->custodian();
        $this->document(['teacher_id' => $custodian->id]);

        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}?panel=equipment")
            ->assertOk()
            ->assertSee('No equipment has been assigned to this custodian yet.')
            ->assertSee('Assign Equipment');
    }

    public function test_the_folder_summary_counts_the_records_it_holds(): void
    {
        $custodian = $this->custodian();

        $this->document(['teacher_id' => $custodian->id, 'type' => 'PAR']);
        $this->document(['teacher_id' => $custodian->id, 'type' => 'ICS', 'document_no' => 'ICS-2026-00001']);
        $this->document(['teacher_id' => $custodian->id, 'type' => 'RIS', 'document_no' => 'RIS-2026-00001']);
        $this->equipment(['teacher_id' => $custodian->id]);
        // Somebody else's records never count here.
        $this->document(['type' => 'PAR', 'document_no' => 'PAR-2026-00099']);
        $this->equipment(['name' => 'Whiteboard 4x8ft']);

        $summary = $this->actingAs($this->admin)
            ->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->viewData('summary');

        $this->assertSame([
            'documents' => 3,
            'equipment' => 1,
            'PAR' => 1,
            'ICS' => 1,
            'RIS' => 1,
        ], $summary);
    }

    public function test_the_folder_breadcrumb_names_the_module_and_the_custodian(): void
    {
        $custodian = $this->custodian();

        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->assertSee('Custodian Folders')
            ->assertSee(route('admin.teachers.index'), false)
            ->assertSee('Cristina Bautista');
    }

    public function test_an_empty_folder_offers_to_be_filled_instead_of_printing_tables(): void
    {
        $custodian = $this->custodian();

        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->assertSee('This folder is empty')
            ->assertSee('No documents or equipment have been assigned to this custodian yet.')
            ->assertSee('Add Document')
            ->assertSee('Assign Equipment')
            ->assertDontSee('Assigned Equipment');
    }

    public function test_the_folder_documents_can_be_filtered_by_type_and_searched(): void
    {
        $custodian = $this->custodian();
        $this->document(['teacher_id' => $custodian->id, 'document_no' => 'PAR-2026-00004']);
        $this->document(['teacher_id' => $custodian->id, 'document_no' => 'RIS-2026-00021', 'type' => 'RIS']);
        $this->equipment(['teacher_id' => $custodian->id]);

        // Which documents the list shows, read from the list itself.
        $listed = fn (string $query) => $this->actingAs($this->admin)
            ->get("/admin/teachers/{$custodian->id}".$query)
            ->assertOk()
            ->viewData('documents')
            ->pluck('document_no')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['PAR-2026-00004'], $listed('?documents=PAR'));
        $this->assertSame(['RIS-2026-00021'], $listed('?documents=RIS'));
        $this->assertSame(['RIS-2026-00021'], $listed('?doc_q=00021'));
        $this->assertSame(['PAR-2026-00004', 'RIS-2026-00021'], $listed(''));

        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}?documents=PAR")
            ->assertOk()->assertSee('PAR-2026-00004');

        // Filtering never changes what the folder is counted to hold.
        $summary = $this->actingAs($this->admin)
            ->get("/admin/teachers/{$custodian->id}?documents=PAR")
            ->viewData('summary');

        $this->assertSame(2, $summary['documents']);
    }

    public function test_the_folder_lists_ten_documents_and_offers_the_rest(): void
    {
        $custodian = $this->custodian();

        foreach (range(1, 13) as $i) {
            $this->document([
                'teacher_id' => $custodian->id,
                'document_no' => 'PAR-2026-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'document_date' => now()->subDays($i)->toDateString(),
            ]);
        }

        $listed = fn (string $query) => $this->actingAs($this->admin)
            ->get("/admin/teachers/{$custodian->id}".$query)
            ->assertOk();

        // The first ten, newest first, with the rest one link away.
        $page = $listed('');
        $this->assertCount(10, $page->viewData('documents'));
        $this->assertSame(13, $page->viewData('documentsMatching'));
        $page->assertSee('Showing the first 10 of 13 documents.')
            ->assertSee('Show all 13')
            ->assertDontSee('PAR-2026-00011');

        // "Show all" hands over the whole list, and "Show first 10" puts it back.
        $all = $listed('?doc_all=1');
        $this->assertCount(13, $all->viewData('documents'));
        $all->assertSee('Showing all 13 documents.')->assertSee('Show first 10')->assertSee('PAR-2026-00011');

        // A filter narrows both the list and what it says it is showing.
        $filtered = $listed('?documents=PAR&doc_q=00011');
        $this->assertCount(1, $filtered->viewData('documents'));
        $this->assertSame(1, $filtered->viewData('documentsMatching'));
        $filtered->assertSee('PAR-2026-00011')->assertDontSee('Showing the first');
    }

    /**
     * The downloaded paper is the paper: every form carries its number in its own
     * field, and no coding strip is printed over the top of it.
     */
    public function test_the_downloaded_form_carries_no_coding_strip(): void
    {
        $custodian = $this->custodian();

        // How each form prints its own number: a PAR and an ICS fill a field,
        // a RIS and an IAR label theirs. Matching the field itself means these
        // assertions cannot be met by the number appearing anywhere else.
        $numberField = fn (string $type, string $no) => in_array($type, ['PAR', 'ICS'], true)
            ? "{$type} No: <span class=\"fld\">{$no}"
            : "{$type} No:</span> {$no}";

        foreach (['PAR', 'ICS', 'RIS', 'IAR'] as $type) {
            $document = $this->raiseDocument($custodian, $type);

            // The view the PDF is built from — the same one the route hands
            // dompdf, so what is asserted here is what is printed.
            $html = view('admin.documents.pdf', [
                'document' => $document->load(['teacher', 'room', 'preparedBy', 'approvedBy', 'issuedBy', 'items.equipment', 'items.supply']),
            ])->render();

            // The form's own field still carries the number.
            $this->assertStringContainsString(
                $numberField($type, $document->document_no),
                $html,
                "The {$type} form prints its own number."
            );

            // The strip that used to sit above it — "PAR No. PAR-2026-00001 ·
            // DRAFT" — is gone, and no rule is left for one to be drawn with.
            $this->assertStringNotContainsString("{$type} No. {$document->document_no}", $html);
            $this->assertStringNotContainsString('status-note', $html);
        }

        // The bundled download prints two papers: the source document and the
        // IAR built from it. Neither page carries a strip — the IAR is a page of
        // this download, not a document that was ever saved, so it is checked
        // here rather than through the route above.
        $par = $this->raiseDocument($custodian, 'PAR')->load(['teacher', 'room', 'preparedBy', 'approvedBy', 'issuedBy', 'items.equipment', 'items.supply']);

        $iar = $par->replicate();
        $iar->type = 'IAR';
        $iar->document_no = preg_replace('/^[A-Za-z]+/', 'IAR', $par->document_no);

        $bundle = view('admin.documents.pdf_bundle', [
            'documents' => collect([$par, $iar]),
            'title' => "{$par->type} + IAR - {$par->document_no}",
        ])->render();

        // Both forms still carry their own number.
        $this->assertStringContainsString($numberField('PAR', $par->document_no), $bundle);
        $this->assertStringContainsString($numberField('IAR', $iar->document_no), $bundle);

        // And neither page of the bundle carries a strip.
        $this->assertStringNotContainsString('status-note', $bundle);
        $this->assertStringNotContainsString("{$par->type} No. {$par->document_no}", $bundle);
        $this->assertStringNotContainsString("IAR No. {$iar->document_no}", $bundle);
    }

    /**
     * The folder's Download saves the paper together with the report it is filed
     * with — a PAR/ICS/RIS and its IAR in one click — and saves it as a file
     * rather than opening the paper alone in a reader tab.
     */
    public function test_the_folder_downloads_a_paper_together_with_its_iar(): void
    {
        $custodian = $this->custodian();
        $par = $this->raiseDocument($custodian, 'PAR');
        $ics = $this->document(['teacher_id' => $custodian->id, 'document_no' => 'ICS-2026-00012', 'type' => 'ICS']);
        $iar = $this->document(['teacher_id' => $custodian->id, 'document_no' => 'IAR-2026-00001', 'type' => 'IAR']);

        $html = $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->getContent();

        // A paper and its acceptance report are one save away.
        $this->assertStringContainsString(
            route('admin.documents.bundle-pdf', ['document' => $par, 'download' => 1]),
            $html
        );
        $this->assertStringContainsString(
            route('admin.documents.bundle-pdf', ['document' => $ics, 'download' => 1]),
            $html
        );

        // An IAR is already the second half of that pair: it downloads as itself,
        // and asking for a bundle of it is never offered.
        $this->assertStringContainsString(
            route('admin.documents.pdf', ['document' => $iar, 'download' => 1]),
            $html
        );
        $this->assertStringNotContainsString(
            route('admin.documents.bundle-pdf', ['document' => $iar, 'download' => 1]),
            $html
        );

        // Pressing Download really saves a file, and the file carries both pages:
        // the paper and the IAR built from it.
        $bundle = $this->actingAs($this->admin)
            ->get(route('admin.documents.bundle-pdf', ['document' => $par, 'download' => 1]))
            ->assertOk();

        $bundle->assertDownload("{$par->type}-{$par->document_no}-with-IAR.pdf");

        // The bundle is assembled for the download only: the IAR it prints is
        // not a record, so the folder is not counted a document richer.
        $this->assertSame(3, Document::query()->where('teacher_id', $custodian->id)->count());
    }

    // ---------------------------------------------------------------------
    // Automatic filing
    // ---------------------------------------------------------------------

    public function test_a_document_saved_for_a_custodian_is_filed_in_their_folder(): void
    {
        $custodian = $this->custodian();

        $par = $this->raiseDocument($custodian, 'PAR');

        $this->assertSame($custodian->id, $par->teacher_id);

        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->assertSee($par->document_no);

        // And an ICS / RIS the same way.
        foreach (['ICS', 'RIS'] as $type) {
            $document = $this->raiseDocument($custodian, $type);

            $this->assertSame($custodian->id, $document->teacher_id);

            $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}")
                ->assertOk()
                ->assertSee($document->document_no);
        }

        $summary = $this->actingAs($this->admin)
            ->get("/admin/teachers/{$custodian->id}")
            ->viewData('summary');

        $this->assertSame(3, $summary['documents']);
    }

    public function test_the_document_form_carries_the_assign_to_field_and_can_open_preselected(): void
    {
        $custodian = $this->custodian();

        $html = $this->actingAs($this->admin)
            ->get('/admin/documents/create?type=PAR&teacher_id='.$custodian->id)
            ->assertOk()
            ->assertSee('Assign To Custodian / Teacher')
            ->assertSee('Cristina Bautista')
            ->getContent();

        // The custodian the folder sent the form for arrives chosen.
        $this->assertMatchesRegularExpression('/value="'.$custodian->id.'"\s+selected/', $html);
    }

    public function test_equipment_assigned_to_a_custodian_is_filed_in_their_folder(): void
    {
        $custodian = $this->custodian();
        $equipment = $this->equipment(['name' => 'Wireless Microphone Set']);

        $this->actingAs($this->admin)->post('/admin/equipment', [
            'item_code' => 'EQP-00077',
            'name' => 'Whiteboard 4x8ft',
            'unit' => 'unit',
            'status' => 'available',
            'teacher_id' => $custodian->id,
        ])->assertRedirect();

        $whiteboard = Equipment::query()->where('item_code', 'EQP-00077')->firstOrFail();

        // The item is in the folder's property, which is its own tab.
        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}?panel=equipment")
            ->assertOk()
            ->assertSee('Whiteboard 4x8ft');

        // The item that belongs to nobody is not in the folder's equipment.
        $this->assertSame(['EQP-00077'], $this->actingAs($this->admin)
            ->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->viewData('equipment')
            ->pluck('item_code')
            ->all());

        $this->assertSame($custodian->id, $whiteboard->teacher_id);
        $this->assertNull($equipment->refresh()->teacher_id);
    }

    public function test_assigning_equipment_from_the_folder_does_not_duplicate_the_item(): void
    {
        $custodian = $this->custodian();
        $equipment = $this->equipment(['name' => 'Dell OptiPlex Desktop']);
        $before = Equipment::count();

        $this->actingAs($this->admin)
            ->post("/admin/teachers/{$custodian->id}/equipment", ['equipment_id' => $equipment->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // One item, one record: the folder is the relationship, not a copy.
        $this->assertSame($before, Equipment::count());
        $this->assertSame($custodian->id, $equipment->refresh()->teacher_id);
        // Handing it to a person makes an available unit issued, as a PAR does.
        $this->assertSame('issued', $equipment->status);

        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}?panel=equipment")
            ->assertOk()
            ->assertSee('Dell OptiPlex Desktop')
            ->assertSee('Issued');
    }

    public function test_an_item_already_held_is_transferred_to_the_new_custodian(): void
    {
        $first = $this->custodian(['first_name' => 'Ricardo', 'last_name' => 'Ramos']);
        $second = $this->custodian(['first_name' => 'Angelica', 'last_name' => 'Fernandez']);
        $equipment = $this->equipment(['teacher_id' => $first->id, 'status' => 'issued']);

        $this->actingAs($this->admin)
            ->post("/admin/teachers/{$second->id}/equipment", ['equipment_id' => $equipment->id])
            ->assertRedirect();

        // The same row changed hands: it leaves one folder and appears in another.
        $this->assertSame($second->id, $equipment->refresh()->teacher_id);

        $held = fn (Teacher $teacher) => $this->actingAs($this->admin)
            ->get("/admin/teachers/{$teacher->id}")
            ->assertOk()
            ->viewData('equipment')
            ->pluck('name')
            ->all();

        $this->assertSame([], $held($first));
        $this->assertSame(['Dell OptiPlex Desktop'], $held($second));
    }

    public function test_assigning_equipment_asks_for_an_item(): void
    {
        $custodian = $this->custodian();

        $this->actingAs($this->admin)
            ->post("/admin/teachers/{$custodian->id}/equipment", [])
            ->assertSessionHasErrors('equipment_id');
    }

    // ---------------------------------------------------------------------
    // Create Folder
    // ---------------------------------------------------------------------

    /**
     * The sheet asks for the person: the fields the custodian form takes, drawn
     * where the office can type them, rather than a read-only echo of a record
     * picked from a list. Opening somebody already in the directory is the grid's
     * job — their folder is a tile on it.
     */
    public function test_the_create_folder_sheet_asks_for_the_person(): void
    {
        $this->room();
        $this->custodian();

        $html = $this->actingAs($this->admin)
            ->get('/admin/teachers')
            ->assertOk()
            ->assertSee('Create Custodian Folder')
            ->getContent();

        $sheet = $this->element($html, '//*[@id="create-folder-modal"]');

        // The five fields, as things to fill in.
        $this->assertStringContainsString('name="first_name"', $sheet);
        $this->assertStringContainsString('name="last_name"', $sheet);
        $this->assertStringContainsString('name="department"', $sheet);
        $this->assertStringContainsString('name="position"', $sheet);
        $this->assertStringContainsString('name="room_id"', $sheet);
        $this->assertStringContainsString('<option value="', $sheet);
        $this->assertStringContainsString('Create Folder', $sheet);

        // Empty: a new person's own form, not the custodian the grid happened to
        // finish listing, and not the last person filed either.
        $this->assertStringContainsString('name="first_name" value=""', $sheet);
        $this->assertStringContainsString('name="last_name" value=""', $sheet);
        $this->assertStringNotContainsString('Cristina', $sheet);
        $this->assertStringNotContainsString('selected', $sheet);

        // Nothing asks the office to pick from a list, echo a record back at
        // them, or tick that the person is missing from a directory.
        $this->assertStringNotContainsString('teacher_id', $sheet);
        $this->assertStringNotContainsString('folder-new-person', $sheet);
        $this->assertStringNotContainsString('Employee ID', $sheet);
        $this->assertStringNotContainsString("Filled in from the person's record.", $sheet);
    }

    public function test_create_folder_asks_for_the_person(): void
    {
        $page = $this->actingAs($this->admin)
            ->from('/admin/teachers')
            ->followingRedirects()
            ->post('/admin/teachers/folder', ['form' => 'create-folder'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('The first name field is required.', $page);
        $this->assertStringContainsString('The last name field is required.', $page);

        // The sheet comes back open on what it is waiting for, which it can only
        // do because the plumbing is defined before the page's own content asks
        // for it.
        $this->assertStringContainsString('window.ISUModal = {', $page);
        $this->assertLessThan(
            strrpos($page, "ISUModal.open('create-folder-modal')"),
            strpos($page, 'window.ISUModal = {'),
        );

        $this->assertSame(0, Teacher::count());
    }

    public function test_create_folder_adds_the_custodian_and_opens_their_folder(): void
    {
        $room = $this->room();

        $this->actingAs($this->admin)
            ->post('/admin/teachers/folder', [
                'form' => 'create-folder',
                'first_name' => 'Angelica',
                'last_name' => 'Fernandez',
                'department' => 'College of Computing',
                'position' => 'Instructor',
                'room_id' => $room->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $custodian = Teacher::query()->where('last_name', 'Fernandez')->firstOrFail();

        $this->assertSame('Angelica Fernandez', $custodian->full_name);
        $this->assertSame($room->id, $custodian->room_id);

        // Nobody typed a number: the record is the first in the series.
        $this->assertSame('FAC-0001', $custodian->employee_id);

        // The folder they were sent to is the one that opens.
        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}")
            ->assertOk()
            ->assertSee('Angelica Fernandez')
            ->assertSee('This folder is empty');
    }

    /**
     * The employee number is the system's: the create and edit forms do not ask
     * for it, each new record takes the next in the office's series, and an edit
     * leaves the number alone.
     */
    public function test_the_employee_number_is_the_systems_and_follows_the_series(): void
    {
        $first = $this->custodian();   // FAC-0001

        // Nothing on the create or edit form asks for a number.
        $this->actingAs($this->admin)->get('/admin/teachers/create')
            ->assertOk()
            ->assertDontSee('Employee ID');

        $this->actingAs($this->admin)->get("/admin/teachers/{$first->id}/edit")
            ->assertOk()
            ->assertDontSee('Employee ID');

        // A record filed through the form takes the next number …
        $this->actingAs($this->admin)->post('/admin/teachers', [
            'first_name' => 'Rosa',
            'last_name' => 'Lim',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('FAC-0002', Teacher::query()->where('last_name', 'Lim')->firstOrFail()->employee_id);

        // … and so does one filed through the New Folder sheet.
        $this->actingAs($this->admin)->post('/admin/teachers/folder', [
            'form' => 'create-folder',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('FAC-0003', Teacher::query()->where('last_name', 'Dela Cruz')->firstOrFail()->employee_id);

        // Editing the record leaves the number where it was.
        $lim = Teacher::query()->where('last_name', 'Lim')->firstOrFail();

        $this->actingAs($this->admin)->put("/admin/teachers/{$lim->id}", [
            'first_name' => 'Rosa',
            'last_name' => 'Lim',
            'department' => 'Supply Office',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('FAC-0002', $lim->refresh()->employee_id);
        $this->assertSame('Supply Office', $lim->department);
    }

    // ---------------------------------------------------------------------
    // Access
    // ---------------------------------------------------------------------

    public function test_a_supply_staff_account_works_the_folder_pages(): void
    {
        $custodian = $this->custodian();
        $equipment = $this->equipment();

        $this->actingAs($this->staff)->get('/admin/teachers')->assertOk()->assertSee('Custodian Folders');
        $this->actingAs($this->staff)->get("/admin/teachers/{$custodian->id}")->assertOk();
        $this->actingAs($this->staff)->get("/admin/teachers/{$custodian->id}/edit")->assertOk();

        $this->actingAs($this->staff)
            ->post("/admin/teachers/{$custodian->id}/equipment", ['equipment_id' => $equipment->id])
            ->assertRedirect();

        $this->assertSame($custodian->id, $equipment->refresh()->teacher_id);

        // Staff file a custodian through the same sheet the admin uses.
        $this->actingAs($this->staff)
            ->post('/admin/teachers/folder', [
                'form' => 'create-folder',
                'first_name' => 'Angelica',
                'last_name' => 'Fernandez',
            ])
            ->assertRedirect(route('admin.teachers.show', Teacher::query()->where('last_name', 'Fernandez')->firstOrFail()))
            ->assertSessionHasNoErrors();
    }

    public function test_the_serial_number_is_recorded_on_the_equipment_form(): void
    {
        $custodian = $this->custodian();

        $this->actingAs($this->admin)->post('/admin/equipment', [
            'item_code' => 'EQP-00088',
            'serial_number' => 'SN-99120-A',
            'name' => 'Office Chair',
            'unit' => 'unit',
            'status' => 'issued',
            'teacher_id' => $custodian->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $chair = Equipment::query()->where('item_code', 'EQP-00088')->firstOrFail();

        $this->assertSame('SN-99120-A', $chair->serial_number);

        $this->actingAs($this->admin)->get("/admin/teachers/{$custodian->id}?panel=equipment")
            ->assertOk()
            ->assertSee('SN-99120-A');
    }
}
