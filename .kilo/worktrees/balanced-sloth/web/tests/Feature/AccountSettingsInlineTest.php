<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Archive;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The account settings panels act in place: the Open buttons are gone and
 * adding a user or an archive year posts straight from the panel.
 */
class AccountSettingsInlineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => Role::ADMIN, 'display_name' => 'Admin']);
        Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Supply Staff']);

        $this->admin = User::factory()->create(['role_id' => $admin->id, 'is_active' => true]);
    }

    public function test_the_panels_act_in_place_without_open_buttons(): void
    {
        $users = $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=users')
            ->getContent();

        // Adding a user opens a sheet over the page: the form is in the panel,
        // but folded away behind the button until it is asked for.
        $this->assertStringNotContainsString('Open Users', $users);
        $this->assertStringContainsString('Add user', $users);
        $this->assertStringContainsString("ISUModal.open('add-user-modal')", $users);
        $this->assertStringContainsString('id="add-user-modal"', $users);
        $this->assertStringContainsString('data-modal', $users);
        $this->assertStringContainsString(route('admin.account-settings.users.store'), $users);

        // The two roles the office hands out are the two on offer.
        $this->assertStringContainsString('>Admin</option>', $users);
        $this->assertStringContainsString('>Supply Staff</option>', $users);
        $this->assertStringNotContainsString('>Inspection Staff</option>', $users);

        $archive = $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=archive')
            ->getContent();

        $this->assertStringNotContainsString('Open Archive', $archive);
        $this->assertStringContainsString(route('admin.account-settings.archive.store'), $archive);

        $logs = $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=activity-logs')
            ->getContent();

        $this->assertStringNotContainsString('Open Activity Logs', $logs);
    }

    /**
     * The profile panel writes the office the account signs as, beside the name
     * and the email. It is asked for rather than offered — the position prints
     * under the name on every report this office signs — so an account cannot be
     * saved without one, and cannot be emptied back to blank.
     */
    public function test_the_profile_panel_writes_the_position_the_account_signs_as(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=profile')
            ->assertOk()
            ->getContent();

        // The field stands with the name and the email it belongs beside, and it
        // is not marked optional.
        $this->assertStringContainsString('name="position"', $html);
        $this->assertStringContainsString('>Position</label>', $html);
        $this->assertStringNotContainsString('>(optional)</span>', $html);
        $this->assertStringContainsString('value=""', $html);
        $this->assertStringContainsString('position and email address', $html);

        $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=profile')
            ->patch('/admin/profile', [
                'name' => $this->admin->name,
                'position' => 'Administrative Officer I (Supply Officer I)',
                'email' => $this->admin->email,
            ])
            ->assertRedirect('/admin/account-settings?section=profile');

        $this->assertSame(
            'Administrative Officer I (Supply Officer I)',
            $this->admin->refresh()->position
        );

        // It comes back written into the field it was written in.
        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=profile')
            ->assertOk()
            ->assertSee('value="Administrative Officer I (Supply Officer I)"', false);

        // Emptied, the account is refused rather than left without one: the
        // position it signs as is not something it can put down.
        $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=profile')
            ->patch('/admin/profile', [
                'name' => $this->admin->name,
                'position' => '',
                'email' => $this->admin->email,
            ])
            ->assertRedirect('/admin/account-settings?section=profile')
            ->assertSessionHasErrors('position');

        $this->assertSame('Administrative Officer I (Supply Officer I)', $this->admin->refresh()->position);

        // The standalone profile editor carries the same field, so the two
        // places the account can be written down cannot drift apart.
        $this->actingAs($this->admin)
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('name="position"', false);
    }

    /**
     * The activity log panel carries the same five filters as the full activity
     * log page, and each one narrows the strip it prints.
     */
    public function test_the_activity_log_panel_filters_the_strip_it_prints(): void
    {
        $other = User::factory()->create(['name' => 'Ana Reyes', 'is_active' => true]);

        ActivityLog::create([
            'user_id' => $this->admin->id,
            'action' => 'issued',
            'module' => 'documents',
            'description' => 'Issued ICS document ICS-2026-00001.',
        ]);
        ActivityLog::create([
            'user_id' => $other->id,
            'action' => 'login',
            'module' => 'auth',
            'description' => 'Ana Reyes logged in to the admin portal.',
        ]);

        // The filters stand in the panel, with the section riding along so the
        // sheet that comes back is still this one.
        $panel = $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=activity-logs')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="section" value="activity-logs"', $panel);
        $this->assertStringContainsString('All modules', $panel);
        $this->assertStringContainsString('All actions', $panel);
        $this->assertStringContainsString('All users', $panel);
        $this->assertStringContainsString('name="date_from"', $panel);
        $this->assertStringContainsString('name="date_to"', $panel);
        $this->assertStringContainsString('Filter</button>', $panel);

        // A module, an action and a user each cut the strip down on their own.
        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=activity-logs&module=documents')
            ->assertOk()
            ->assertSee('Issued ICS document ICS-2026-00001.')
            ->assertDontSee('Ana Reyes logged in to the admin portal.');

        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=activity-logs&action=login')
            ->assertOk()
            ->assertSee('Ana Reyes logged in to the admin portal.')
            ->assertDontSee('Issued ICS document ICS-2026-00001.');

        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=activity-logs&user_id='.$other->id)
            ->assertOk()
            ->assertSee('Ana Reyes logged in to the admin portal.')
            ->assertDontSee('Issued ICS document ICS-2026-00001.');

        // A window that holds nothing says so, rather than claiming the portal
        // has never logged anything.
        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=activity-logs&date_from='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertSee('No activity matches these filters.')
            ->assertDontSee('No activity recorded yet.');
    }

    public function test_a_user_is_added_from_the_users_panel(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.account-settings.users.store'), [
                'name' => 'Ana Reyes',
                'email' => 'ana.reyes@isu.edu.ph',
                'role_id' => Role::where('name', Role::INSPECTION_STAFF)->value('id'),
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
            ])
            ->assertRedirect(route('admin.account-settings.index', ['section' => 'users']));

        $this->assertDatabaseHas('users', [
            'email' => 'ana.reyes@isu.edu.ph',
            'is_active' => true,
        ]);
    }

    /**
     * A new account signs nothing until it says who it signs as, so its
     * dashboard asks for the position before it asks for anything else and
     * points at the field that holds it. Once the office holds a position, the
     * dashboard goes back to being only a dashboard.
     */
    public function test_the_dashboard_asks_a_new_account_for_its_position(): void
    {
        $this->assertNull($this->admin->position);

        $dashboard = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();

        $dashboard->assertSee('Set your position')
            ->assertSee(route('admin.account-settings.index', ['section' => 'profile']), false);

        // Worked into the register without one, the account is still asked.
        $this->admin->update(['position' => 'Administrative Officer I (Supply Officer I)']);

        $this->actingAs($this->admin->refresh())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('Set your position');

        // An account the office opens for a staff member lands on a dashboard
        // that asks it the same question before it starts signing anything.
        $staff = User::factory()->create([
            'role_id' => Role::where('name', Role::INSPECTION_STAFF)->value('id'),
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Set your position')
            ->assertSee(route('admin.account-settings.index', ['section' => 'profile']), false);

        // Written down, the prompt goes away for the staff account too.
        $staff->update(['position' => 'Administrative Aide I']);

        $this->actingAs($staff->refresh())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('Set your position');
    }

    /**
     * The sheet comes back open on a mistake, so the message stands beside the
     * field it belongs to rather than behind a button the reader has to find.
     */
    public function test_the_add_user_sheet_reopens_when_an_account_is_refused(): void
    {
        // The redirect is followed the way a browser does, so the sheet that
        // comes back is the one carrying the message.
        $page = $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=users')
            ->followingRedirects()
            ->post(route('admin.account-settings.users.store'), [
                // The sheet names itself, so the page reopens the one that came
                // back with the mistake rather than whichever sheet first holds
                // a field of that name: the three account sheets share theirs.
                '_action' => 'add-user',
                'name' => 'Ana Reyes',
                'email' => $this->admin->email,
                'role_id' => Role::where('name', Role::INSPECTION_STAFF)->value('id'),
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('The email has already been taken.', $page);
        $this->assertStringContainsString("ISUModal.open('add-user-modal');", $page);
        // And no other sheet is asked for: the edit and reset sheets stay shut.
        $this->assertStringNotContainsString("ISUUser.reopen(", $page);
    }

    /**
     * Users is the admin's register and nobody else's: a staff account is offered
     * only its own profile and its password, and the people manager — the Add
     * user button and the sheet it opens — is simply not on the page. Asking for
     * the section by name does not open it either.
     */
    public function test_a_supply_staff_account_is_never_shown_the_users_register(): void
    {
        $staff = User::factory()->create([
            'role_id' => Role::where('name', Role::INSPECTION_STAFF)->value('id'),
            'is_active' => true,
        ]);

        $panel = $this->actingAs($staff)
            ->get('/admin/account-settings')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Change Password', $panel);
        $this->assertStringNotContainsString('Activity Logs', $panel);
        $this->assertStringNotContainsString('id="add-user-modal"', $panel);
        $this->assertStringNotContainsString('Add user', $panel);

        // Named outright, the section falls back rather than opening: the staff
        // page still carries its own profile and offers no way to add anyone.
        $asked = $this->actingAs($staff)
            ->get('/admin/account-settings?section=users')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="add-user-modal"', $asked);
        $this->assertStringContainsString('name="position"', $asked);

        // The panel is not the only way in: posting to it is refused too.
        $this->actingAs($staff)
            ->post(route('admin.account-settings.users.store'), [
                'name' => 'Ana Reyes',
                'email' => 'ana.reyes@isu.edu.ph',
                'role_id' => Role::where('name', Role::INSPECTION_STAFF)->value('id'),
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'ana.reyes@isu.edu.ph']);
    }

    public function test_an_archive_year_is_added_from_the_archive_panel(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.account-settings.archive.store'), ['year' => 2031])
            // Back to the panel, with the new year already the one the register
            // is filed under next: it is the year just asked for.
            ->assertRedirect(route('admin.account-settings.index', [
                'section' => 'archive',
                'archive_id' => Archive::sole()->id,
            ]));

        $this->assertDatabaseHas('archives', ['year' => 2031]);
    }
}
