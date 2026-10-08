<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The sidebar navigation: config-driven sections, role visibility, the
 * Ctrl+K search overlay, favorites/recents mounts, and breadcrumbs.
 */
class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $staff = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Inspection Staff']);

        $this->admin = User::factory()->create(['role_id' => $admin->id, 'is_active' => true]);
        $this->staff = User::factory()->create(['role_id' => $staff->id, 'is_active' => true]);
    }

    public function test_the_sidebar_renders_the_configured_sections(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->getContent();
        $nav = substr($html, (int) strpos($html, 'id="app-nav"'));
        $nav = substr($nav, 0, (int) strpos($nav, '</nav>'));

        // Dashboard is a standalone top item; the Overview section and the
        // Analytics duplicate are gone from the menu. (Labels are title-case
        // in the HTML; the all-caps look comes from CSS.)
        $this->assertStringContainsString('Dashboard', $nav);
        $this->assertStringNotContainsString('Overview', $nav);
        $this->assertStringNotContainsString('Analytics', $nav);
        $this->assertStringContainsString('Inventory', $nav);
        $this->assertStringContainsString('Documents', $nav);
        $this->assertStringContainsString('Organization', $nav);
        $this->assertStringContainsString('Reports', $nav);

        // The inspection, QR-code, missing-equipment, announcement and
        // monitoring screens are gone from the portal.
        $this->assertStringNotContainsString('Inspections', $nav);
        $this->assertStringNotContainsString('QR Codes', $nav);
        $this->assertStringNotContainsString('Missing Equipment', $nav);
        $this->assertStringNotContainsString('Announcements', $nav);
        $this->assertStringNotContainsString('Monitoring', $nav);
        $this->assertStringNotContainsString('Low Stock', $nav);
        $this->assertStringNotContainsString('Stock Movement', $nav);
        $this->assertStringContainsString('Reports', $nav);
        $this->assertStringContainsString('Administration', $nav);

        // The sections are steady: each one heads its group and its items are
        // always drawn, so there is no fold-away control, no chevron and no
        // collapsed state for the script to keep.
        $this->assertSame(5, substr_count($nav, 'nav-section-heading'));
        $this->assertStringNotContainsString('data-section-toggle', $nav);
        $this->assertStringNotContainsString('data-section-body', $nav);
        $this->assertStringNotContainsString('nav-chevron', $nav);

        // Search mount. Favorites and recent pages are no longer part of the
        // sidebar, so only the command-palette trigger remains.
    }

    public function test_every_sidebar_item_points_at_a_real_route(): void
    {
        $items = collect(config('navigation.sections'))
            ->flatMap(fn ($items) => $items)
            ->merge(config('navigation.top', []));

        foreach ($items as $item) {
            $this->assertTrue(
                Route::has($item['route']),
                "Config item {$item['label']} points at missing route [{$item['route']}]."
            );
        }
    }

    public function test_the_administrator_sees_the_full_navigation(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->getContent();
        $nav = substr($html, (int) strpos($html, 'id="app-nav"'));
        $nav = substr($nav, 0, (int) strpos($nav, '</nav>'));

        $this->assertStringContainsString('Stock / Property', $nav);
        // One door to the back office: the users, the archive years and the
        // activity log are panels of the settings page, so they are not listed
        // here beside it.
        $this->assertStringContainsString('Settings', $nav);
        $this->assertStringNotContainsString('Users', $nav);
        $this->assertStringNotContainsString('Activity Logs', $nav);
        $this->assertStringNotContainsString('Archive', $nav);
        // The announcement and missing-equipment screens are gone.
        $this->assertStringNotContainsString('Announcements', $nav);
        $this->assertStringNotContainsString('Missing Equipment', $nav);
        $this->assertStringContainsString('href="' . route('admin.documents.index') . '"', $nav);
        // The low-stock view is a filter on the Supplies page now, reached from
        // that page and from the dashboard tile rather than from the menu.
        $this->assertStringNotContainsString('low_stock=1', $nav);

        // Stock/Property Cards are tabs of the cards register, not menu items.
        $this->assertStringNotContainsString(route('admin.cards.stock'), $nav);
        $this->assertStringNotContainsString(route('admin.cards.property'), $nav);

        // The per-type document shortcuts are not menu entries: the register
        // itself carries the type filter, so only one door leads to it.
        $this->assertStringNotContainsString('type=RIS', $nav);
        $this->assertStringNotContainsString('type=PAR', $nav);
        $this->assertStringNotContainsString('type=ICS', $nav);
        $this->assertStringNotContainsString('type=IAR', $nav);
    }

    public function test_inventory_holds_only_supplies_and_equipment(): void
    {
        $labels = collect(config('navigation.sections.Inventory'))->pluck('label')->all();

        $this->assertSame(['Supplies', 'Equipment'], $labels);

        // The reference data moved to its own group, still pointing at the
        // same pages it always did.
        $organization = collect(config('navigation.sections.Organization'))->pluck('label')->all();
        $this->assertSame(['Rooms', 'Custodian Folders'], $organization);
    }

    public function test_the_documents_group_carries_the_register_and_the_cards(): void
    {
        $labels = collect(config('navigation.sections.Documents'))->pluck('label')->all();

        $this->assertSame(['PAR / ICS / RIS', 'Stock / Property'], $labels);

        // The card types are still reachable from the register and from search.
        $cards = collect(config('navigation.sections.Documents'))->firstWhere('label', 'Stock / Property');
        $this->assertStringContainsString('stock cards', $cards['keywords']);
        $this->assertStringContainsString('property cards', $cards['keywords']);

        // The register lists every document type, so the plain words people
        // type — "documents", "iar" — still find it.
        $register = collect(config('navigation.sections.Documents'))->firstWhere('label', 'PAR / ICS / RIS');
        $this->assertStringContainsString('documents', $register['keywords']);
        $this->assertStringContainsString('iar', $register['keywords']);
    }

    public function test_the_inspection_staff_see_their_modules_but_not_administration(): void
    {
        $html = $this->actingAs($this->staff)->get('/admin/dashboard')->getContent();

        // Their modules: the shared inventory, the reference data, the
        // document register the office works in, and the cards register the
        // stock and property ledger is kept in.
        $this->assertStringContainsString('Supplies', $html);
        $this->assertStringContainsString('Rooms', $html);
        $this->assertStringContainsString('href="' . route('admin.documents.index') . '"', $html);
        $this->assertStringContainsString('href="' . route('admin.cards.index') . '"', $html);

        // Admin-only: the archive and user management never render.
        $this->assertStringNotContainsString('href="' . route('admin.archive.index') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('admin.users.index') . '"', $html);
    }

    public function test_admin_only_pages_stay_guarded_against_inspection_staff(): void
    {
        $this->actingAs($this->staff)->get('/admin/users')->assertForbidden();
        $this->actingAs($this->staff)->get('/admin/archive')->assertForbidden();
        $this->actingAs($this->staff)->get('/admin/activity-logs')->assertForbidden();

        // The audit trail is still the administrator's to read.
        $this->actingAs($this->admin)->get('/admin/activity-logs')->assertOk();

        // Shared pages remain open, the document register and the cards
        // register among them: the office raises the papers and keeps the
        // stock and property ledger itself.
        $this->actingAs($this->staff)->get('/admin/rooms')->assertOk();
        $this->actingAs($this->staff)->get('/admin/equipment')->assertOk();
        $this->actingAs($this->staff)->get('/admin/supplies')->assertOk();
        $this->actingAs($this->staff)->get('/admin/documents')->assertOk();
        $this->actingAs($this->staff)->get('/admin/cards')->assertOk();
    }

    public function test_resolved_nav_items_no_longer_carry_a_starred_flag(): void
    {
        $sections = \App\Support\Navigation::sectionsFor($this->admin);

        foreach ($sections['top'] as $item) {
            $this->assertArrayNotHasKey('starred', $item, "top item {$item['label']} still carries a starred key.");
        }

        foreach ($sections['sections'] as $section => $items) {
            foreach ($items as $item) {
                $this->assertArrayNotHasKey('starred', $item, "{$section} item {$item['label']} still carries a starred key.");
            }
        }
    }

    public function test_the_ctrl_k_overlay_is_present_on_every_admin_page(): void
    {
        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('nav-search-overlay')
            ->assertSee('nav-search-input')
            ->assertSee('Ctrl K');
    }

    public function test_detail_pages_render_breadcrumbs(): void
    {
        $room = \App\Models\Room::create([
            'code' => 'A101',
            'name' => 'Computer Laboratory 1',
            'building' => 'Main Building A',
            'floor' => '1st Floor',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)->get("/admin/rooms/{$room->id}")
            ->assertOk()
            ->assertSee('Breadcrumb')
            ->assertSee('Rooms')
            ->assertSee('Computer Laboratory 1');
    }

    /**
     * The JSON API registers the bare resource names ("documents.index"), so a
     * breadcrumb lookup without the admin prefix links at /api/… and answers
     * "Unauthenticated" when a person clicks it.
     */
    public function test_breadcrumb_links_point_at_admin_pages_not_the_api(): void
    {
        foreach (['documents', 'equipment', 'rooms', 'supplies'] as $resource) {
            $html = $this->actingAs($this->admin)->get("/admin/{$resource}/create")->assertOk()->getContent();

            $bar = substr($html, (int) strpos($html, 'aria-label="Breadcrumb"'));
            $bar = substr($bar, 0, (int) strpos($bar, '</nav>'));

            $this->assertStringContainsString('href="' . route("admin.{$resource}.index") . '"', $bar);
            $this->assertStringNotContainsString('/api/', $bar);
        }
    }

    public function test_show_page_breadcrumbs_link_at_the_admin_index(): void
    {
        $room = \App\Models\Room::create([
            'code' => 'B209',
            'name' => 'Spare Classroom',
            'building' => 'Main Building B',
            'floor' => '2nd Floor',
            'status' => 'active',
        ]);

        $html = $this->actingAs($this->admin)->get("/admin/rooms/{$room->id}")->assertOk()->getContent();
        $bar = substr($html, (int) strpos($html, 'aria-label="Breadcrumb"'));
        $bar = substr($bar, 0, (int) strpos($bar, '</nav>'));

        $this->assertStringContainsString('href="' . route('admin.rooms.index') . '"', $bar);
        $this->assertStringNotContainsString('/api/', $bar);
    }

    public function test_index_pages_do_not_render_a_breadcrumb_trail(): void
    {
        $this->actingAs($this->admin)->get('/admin/rooms')
            ->assertOk()
            ->assertDontSee('aria-label="Breadcrumb"', false);
    }

    public function test_the_account_settings_page_shows_one_section_at_a_time(): void
    {
        // The page opens on Profile, with the other sections left off it.
        $this->actingAs($this->admin)
            ->get('/admin/account-settings')
            ->assertOk()
            ->assertSee('Profile Information')
            ->assertSee('action="' . route('admin.profile.update') . '"', false)
            ->assertDontSee("Use a strong password of at least 8 characters")
            ->assertDontSee('Manage accounts, roles and active status.')
            ->assertDontSee('Archive years')
            ->assertDontSee('Audit trail of account actions across the portal.');

        // Each sidebar row swaps in its own section, on this same page.
        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=password')
            ->assertOk()
            ->assertSee("Use a strong password of at least 8 characters")
            ->assertSee('action="' . route('admin.profile.password') . '"', false)
            ->assertDontSee('Profile Information');

        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=users')
            ->assertOk()
            ->assertSee('Manage accounts, roles and active status.')
            ->assertDontSee('Profile Information');

        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=archive')
            ->assertOk()
            ->assertSee('Archive years')
            ->assertDontSee('Profile Information');

        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=activity-logs')
            ->assertOk()
            ->assertSee('Audit trail of account actions across the portal.')
            ->assertDontSee('Profile Information');

        // The profile section keeps the form itself and no longer points at the
        // old single-purpose editor: a name and an email are changed in one
        // place, and that route stays reachable by direct link alone.
        $this->actingAs($this->admin)
            ->get('/admin/account-settings')
            ->assertOk()
            ->assertDontSee('Open the full profile editor');

        // An unknown section falls back to the first one instead of blanking.
        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=nonsense')
            ->assertOk()
            ->assertSee('Profile Information');
    }

    public function test_the_account_settings_page_carries_a_settings_sidebar(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/account-settings')->assertOk()->getContent();

        $nav = substr($html, (int) strpos($html, 'aria-label="Account settings sections"'));
        $nav = substr($nav, 0, (int) strpos($nav, '</nav>'));

        // Every section of the page is a sidebar row…
        foreach (['Profile', 'Change Password', 'Users', 'Archive', 'Activity Logs'] as $label) {
            $this->assertStringContainsString($label, $nav);
        }

        // …each one a link back into this same page, carrying its icon.
        foreach (['profile', 'password', 'users', 'archive', 'activity-logs'] as $section) {
            $this->assertStringContainsString(
                'href="' . route('admin.account-settings.index', ['section' => $section]) . '"',
                $nav
            );
        }
        $this->assertGreaterThanOrEqual(5, substr_count($nav, '<svg'));

        // The row for the open section is marked as the current page.
        $this->assertStringContainsString('aria-current="page"', $nav);

        // The list stays in view as a long section is read: it is sticky under
        // the header, and the column holding it stands the full height of the
        // section beside it — a column only as tall as the list would carry the
        // list off the page as soon as it scrolled past.
        $settings = substr($html, (int) strpos($html, 'lg:grid-cols-[248px_minmax'));
        $settings = substr($settings, 0, (int) strpos($settings, '</nav>') + 6);

        $this->assertStringContainsString('lg:items-stretch', $settings);
        $this->assertStringNotContainsString('lg:items-start', $settings);
        $this->assertStringContainsString('lg:sticky', $settings);
        $this->assertStringContainsString('lg:top-20', $settings);
    }

    public function test_the_account_menu_replaces_the_notification_bell_and_the_logout_button(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The bell and the standalone sign-out button are gone from the header.
        $this->assertStringNotContainsString(route('admin.notifications.index'), $html);

        // The name opens a menu holding Account Settings and Logout.
        $menu = substr($html, (int) strpos($html, 'id="user-menu"'));
        $menu = substr($menu, 0, (int) strpos($menu, '</div>'));

        $this->assertStringContainsString('id="user-menu-trigger"', $menu);
        $this->assertStringContainsString('href="' . route('admin.account-settings.index') . '"', $menu);
        $this->assertStringContainsString('Settings', $menu);
        $this->assertStringContainsString('action="' . route('admin.logout') . '"', $menu);
        $this->assertStringContainsString('Logout', $menu);
    }

    /**
     * The green bar remaps its inner slate text to white, and the account menu
     * hangs inside that bar: unless the popover states its own colours it comes
     * out white on white — a panel that looks empty when it is opened.
     */
    public function test_the_account_menu_states_its_own_colours_inside_the_green_bar(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.app-header #user-menu-panel button {', $css);
        $this->assertStringContainsString('color: #0f172a;', $css);
        // The way out keeps a red tint of its own rather than the bar's white.
        $this->assertStringContainsString('.app-header #user-menu-panel button:hover {', $css);
    }

    public function test_the_account_settings_link_replaces_the_old_profile_link_in_the_sidebar(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->getContent();
        $nav = substr($html, (int) strpos($html, 'id="app-nav"'));
        $nav = substr($nav, 0, (int) strpos($nav, '</nav>'));

        $this->assertStringContainsString('href="' . route('admin.account-settings.index') . '"', $nav);
        $this->assertStringContainsString('Settings', $nav);

        // No star/favorite controls anywhere in the sidebar.
        $this->assertStringNotContainsString('data-star', $nav);
        $this->assertStringNotContainsString('Add to favorites', $nav);
    }

    public function test_inspection_staff_sees_only_their_own_sections_in_account_settings(): void
    {
        $html = $this->actingAs($this->staff)->get('/admin/account-settings')->assertOk()->getContent();

        // Profile + password are theirs.
        $this->assertStringContainsString('Profile Information', $html);
        $this->assertStringContainsString('Change Password', $html);

        // The audit trail is the administrator's. A supply staff account is
        // one of the accounts the trail is recording, so the section is not
        // offered to them and asking for it by name does not open it either.
        $this->assertStringNotContainsString('Activity Logs', $html);
        $this->assertStringNotContainsString('section=activity-logs', $html);

        // Users and Archive are admin-only: not even offered as a section.
        $this->assertStringNotContainsString('Users', $html);
        $this->assertStringNotContainsString('Archive', $html);
        $this->assertStringNotContainsString('section=users', $html);
        $this->assertStringNotContainsString('section=archive', $html);

        // Asking for a section they may not see falls back to their first one.
        $this->actingAs($this->staff)
            ->get('/admin/account-settings?section=users')
            ->assertOk()
            ->assertSee('Profile Information');

        $this->actingAs($this->staff)
            ->get('/admin/account-settings?section=activity-logs')
            ->assertOk()
            ->assertSee('Profile Information')
            ->assertDontSee('Audit trail of account actions across the portal.');
    }

    /**
     * The portal belongs to the supply office, and both of its roles work in it:
     * a supply staff account signs in the way an administrator does and reaches
     * the dashboard, where the role-gated navigation keeps back what it must.
     */
    public function test_a_supply_staff_account_can_sign_in_to_the_portal(): void
    {
        $this->staff->update(['password' => 'secret1234']);

        $this->post('/login', [
            'email' => $this->staff->email,
            'password' => 'secret1234',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->staff->refresh());

        // The session it signed in with opens the dashboard it was sent to.
        $this->get('/admin/dashboard')->assertOk();
    }

    /** An account that is no part of the supply office is still turned away. */
    public function test_an_account_outside_the_supply_office_is_turned_away_at_the_door(): void
    {
        $outsider = Role::create(['name' => 'custodian', 'display_name' => 'Custodian']);

        $user = User::factory()->create([
            'role_id' => $outsider->id,
            'is_active' => true,
            'password' => 'secret1234',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret1234',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
