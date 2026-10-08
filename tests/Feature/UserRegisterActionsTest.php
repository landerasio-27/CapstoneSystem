<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\Supply;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * What the office does to an account from the register that lists them: edit
 * it, write it a new password, take its way in away or give it back, and delete
 * it. The four stand on every row of the register, and the two accounts the
 * office cannot afford to lose are held back.
 */
class UserRegisterActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => Role::ADMIN, 'display_name' => 'Admin']);
        $this->staffRole = Role::create(['name' => Role::INSPECTION_STAFF, 'display_name' => 'Supply Staff']);

        $this->admin = User::factory()->create([
            'role_id' => $admin->id,
            'is_active' => true,
            'password' => 'secret1234',
        ]);
    }

    /** The actions menu the register draws for one account, cut out of the page. */
    private function menuFor(string $html, int $id): string
    {
        $start = strpos($html, 'id="account-actions-'.$id.'"');
        $this->assertNotFalse($start, 'The register drew no menu for that account.');

        // The menu holds nothing but the four items, so the first close of a div
        // after it opens is its own.
        $end = strpos($html, '</div>', $start);

        return substr($html, $start, $end - $start);
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'role_id' => $this->staffRole->id,
            'is_active' => true,
            'password' => 'secret1234',
        ]);
    }

    /**
     * Signed out, the way the portal is left before a sign-in is tried: the
     * guard the test signed in with is dropped as well as the session, so the
     * next request is read as a fresh visitor rather than as the officer the
     * test acted as.
     */
    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->assertGuest();
    }

    /**
     * The register carries all four on every row, each aimed at the account's
     * own record, and the two sheets stand over the page rather than on pages of
     * their own.
     */
    public function test_the_register_carries_the_four_account_actions(): void
    {
        $staff = $this->staff(['name' => 'Juan Dela Cruz']);

        $html = $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=users')
            ->assertOk()
            ->assertSee('Actions')
            ->getContent();

        $this->assertStringContainsString('Edit</button>', $html);
        $this->assertStringContainsString('Reset password</button>', $html);
        $this->assertStringContainsString('Deactivate', $html);
        $this->assertStringContainsString('Delete</button>', $html);

        // Each one aims at the row's own account.
        $this->assertStringContainsString(route('admin.users.toggle-active', $staff), $html);
        $this->assertStringContainsString(route('admin.users.destroy', $staff), $html);
        $this->assertStringContainsString('data-action-template="'.route('admin.users.update', ['user' => '__USER__']).'"', $html);
        $this->assertStringContainsString('data-action-template="'.route('admin.users.reset-password', ['user' => '__USER__']).'"', $html);
        $this->assertStringContainsString('data-id="'.$staff->id.'"', $html);
        $this->assertStringContainsString('data-email="'.$staff->email.'"', $html);

        // The four stand behind the row's ⋯ rather than across it, and every one
        // of them wears an icon of its own.
        $menu = $this->menuFor($html, $staff->id);

        $this->assertStringContainsString('aria-haspopup="menu"', $html);
        $this->assertStringContainsString('aria-controls="account-actions-'.$staff->id.'"', $html);
        $this->assertSame(4, substr_count($menu, 'role="menuitem"'));
        $this->assertSame(4, substr_count($menu, '<svg'));

        // The sheets themselves: the fields to write, and nothing that opens a
        // page for either job.
        $this->assertStringContainsString('id="edit-user-modal"', $html);
        $this->assertStringContainsString('id="reset-password-modal"', $html);
        $this->assertStringContainsString('id="edit-user-form"', $html);
        $this->assertStringContainsString('id="reset-password-form"', $html);
        $this->assertStringNotContainsString(route('admin.users.edit', $staff), $html);
    }

    public function test_an_account_is_edited_from_the_register(): void
    {
        $staff = $this->staff(['name' => 'Juan Dela Cruz', 'employee_id' => 'INS-0001']);

        $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=users')
            ->put(route('admin.users.update', $staff), [
                '_action' => 'edit-user',
                '_user' => (string) $staff->id,
                'name' => 'Juan D. Dela Cruz',
                'employee_id' => 'INS-0007',
                'email' => 'juan.delacruz@isu.edu.ph',
                'role_id' => $this->staffRole->id,
            ])
            // Back to the register it was edited from, not to a page of its own.
            ->assertRedirect('/admin/account-settings?section=users')
            ->assertSessionHas('success');

        $staff->refresh();

        $this->assertSame('Juan D. Dela Cruz', $staff->name);
        $this->assertSame('INS-0007', $staff->employee_id);
        $this->assertSame('juan.delacruz@isu.edu.ph', $staff->email);
        // Left blank, the password is not touched: the sheet has no password
        // field, and the edit must not blank the account's way in.
        $this->assertTrue(Hash::check('secret1234', $staff->password));
    }

    public function test_editing_an_account_refuses_another_accounts_email(): void
    {
        $staff = $this->staff();
        $other = $this->staff(['email' => 'taken@isu.edu.ph']);

        $page = $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=users')
            ->followingRedirects()
            ->put(route('admin.users.update', $staff), [
                '_action' => 'edit-user',
                '_user' => (string) $staff->id,
                '_user_name' => $staff->name,
                'name' => $staff->name,
                'email' => $other->email,
                'role_id' => $this->staffRole->id,
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('The email has already been taken.', $page);
        // The sheet opens again, on the account it was written for.
        $this->assertStringContainsString("ISUUser.reopen('edit', \"".$staff->id."\"", $page);
    }

    public function test_an_accounts_password_is_reset_from_the_register(): void
    {
        $staff = $this->staff();

        $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=users')
            ->patch(route('admin.users.reset-password', $staff), [
                '_action' => 'reset-password',
                '_user' => (string) $staff->id,
                'password' => 'brandnew1234',
                'password_confirmation' => 'brandnew1234',
            ])
            ->assertRedirect('/admin/account-settings?section=users')
            ->assertSessionHas('success');

        $staff->refresh();

        $this->assertTrue(Hash::check('brandnew1234', $staff->password));
        // Only the password moved: the account signs in under the same name.
        $this->assertTrue($staff->is_active);

        // The new password is the one that opens the portal with the account,
        // and the old one no longer does.
        $this->signOut();

        $this->post('/login', ['email' => $staff->email, 'password' => 'secret1234'])
            ->assertSessionHasErrors('email');

        $this->signOut();

        $this->post('/login', ['email' => $staff->email, 'password' => 'brandnew1234'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staff->refresh());
    }

    public function test_a_reset_password_has_to_be_typed_twice(): void
    {
        $staff = $this->staff();

        $page = $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=users')
            ->followingRedirects()
            ->patch(route('admin.users.reset-password', $staff), [
                '_action' => 'reset-password',
                '_user' => (string) $staff->id,
                '_user_name' => $staff->name,
                'password' => 'brandnew1234',
                'password_confirmation' => 'somethingelse',
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("ISUUser.reopen('reset', \"".$staff->id."\"", $page);
        $this->assertTrue(Hash::check('secret1234', $staff->refresh()->password));
    }

    public function test_an_account_is_deactivated_and_given_back_from_the_register(): void
    {
        $staff = $this->staff(['name' => 'Juan Dela Cruz']);

        $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=users')
            ->patch(route('admin.users.toggle-active', $staff))
            ->assertRedirect('/admin/account-settings?section=users')
            ->assertSessionHas('success');

        $this->assertFalse($staff->refresh()->is_active);

        // Deactivated, the register offers it back rather than away.
        $this->actingAs($this->admin)
            ->get('/admin/account-settings?section=users')
            ->assertOk()
            ->assertSee('Activate');

        // And the account cannot sign in while it is out.
        $this->signOut();

        $this->post('/login', ['email' => $staff->email, 'password' => 'secret1234'])
            ->assertSessionHasErrors('email');

        // Given back, it signs in again.
        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-active', $staff))
            ->assertSessionHas('success');

        $this->assertTrue($staff->refresh()->is_active);

        $this->signOut();

        $this->post('/login', ['email' => $staff->email, 'password' => 'secret1234'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_an_account_is_deleted_from_the_register(): void
    {
        $staff = $this->staff(['name' => 'Juan Dela Cruz']);

        $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=users')
            ->delete(route('admin.users.destroy', $staff))
            ->assertRedirect('/admin/account-settings?section=users')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    /**
     * The accounts the office cannot afford to lose: the one the officer is
     * signed in as, and the last active administrator. Both are refused, and the
     * office is told why rather than left without a way back in.
     */
    public function test_the_office_keeps_the_account_it_signs_in_with_and_its_last_administrator(): void
    {
        $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=users')
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-active', $this->admin))
            ->assertSessionHas('error');

        $this->assertTrue($this->admin->refresh()->is_active);

        // A second administrator makes the first one's account ordinary: it can
        // be deactivated, and only then — the office still has a way in.
        $other = User::factory()->create([
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=users')
            ->delete(route('admin.users.destroy', $other))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    /**
     * An account that filed supply requests keeps them: the requests would be
     * deleted along with the account, so the office is asked to deactivate it
     * instead — the record stays, and the way in goes.
     */
    public function test_an_account_holding_records_is_deactivated_rather_than_deleted(): void
    {
        $staff = $this->staff();

        SupplyRequest::create([
            'request_no' => 'SR-2026-0001',
            'requested_by' => $staff->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->from('/admin/account-settings?section=users')
            ->delete(route('admin.users.destroy', $staff))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('supply_requests', ['requested_by' => $staff->id]);

        // Deactivating is what the office is pointed at, and it works.
        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-active', $staff))
            ->assertSessionHas('success');

        $this->assertFalse($staff->refresh()->is_active);
        $this->assertDatabaseHas('supply_requests', ['requested_by' => $staff->id]);
    }

    public function test_a_supply_staff_account_cannot_work_the_register(): void
    {
        $staff = $this->staff();
        $other = $this->staff();

        $this->actingAs($staff)->get('/admin/account-settings?section=users')->assertOk();

        $this->actingAs($staff)
            ->put(route('admin.users.update', $other), ['name' => 'Anyone'])
            ->assertForbidden();

        $this->actingAs($staff)
            ->patch(route('admin.users.reset-password', $other), [
                'password' => 'brandnew1234',
                'password_confirmation' => 'brandnew1234',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->patch(route('admin.users.toggle-active', $other))
            ->assertForbidden();

        $this->actingAs($staff)
            ->delete(route('admin.users.destroy', $other))
            ->assertForbidden();

        $this->assertTrue(Hash::check('secret1234', $other->refresh()->password));
        $this->assertTrue($other->is_active);
    }
}
