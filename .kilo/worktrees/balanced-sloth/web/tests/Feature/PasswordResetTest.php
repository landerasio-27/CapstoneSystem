<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordCode;
use App\Models\PasswordResetCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Forgot password: the office writes the email of the account it lost the
 * password of, the system mails a six-digit code to that account, the code is
 * brought back, and only then is a new password written.
 *
 * What the tests hold to: the code goes nowhere but the account's own address,
 * an account the portal would not let sign in is not offered a code at all, the
 * code is spent as it is accepted and wears out on wrong tries, and the last
 * step stands on nothing but a code that was accepted — a page reached by hand
 * changes no password.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /** An account that works the supply office, as the sign-in screen wants. */
    private function account(string $email = 'jdelacruz@isu.edu.ph', array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => Role::ADMIN], ['display_name' => 'Administrator']);

        return User::factory()->create($attributes + [
            'email' => $email,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /** Walk to the code step and hand back the code that was mailed. */
    private function askForCode(string $email): string
    {
        $this->post('/forgot-password', ['email' => $email])->assertRedirect(route('password.code'));

        $code = null;

        Mail::assertSent(ResetPasswordCode::class, function (ResetPasswordCode $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }

    public function test_the_sign_in_screen_offers_the_way_back_in(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Forgot Password?')
            ->assertSee(route('password.request'), false)
            // The screen used to send the office to the administrator by hand:
            // the reset is the system's own now.
            ->assertDontSee('Self-service reset is not enabled');

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee('Send the code');
    }

    public function test_the_code_is_mailed_to_the_account_that_asked_for_one(): void
    {
        Mail::fake();
        $user = $this->account();

        $code = $this->askForCode($user->email);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // The row keeps the hash of the code and not the code: what is in the
        // mailbox is the only copy of the six digits that exists.
        $stored = PasswordResetCode::where('email', $user->email)->sole();
        $this->assertNotSame($code, $stored->code);
        $this->assertTrue(Hash::check($code, $stored->code));
        $this->assertSame(0, $stored->attempts);
        $this->assertTrue($stored->expires_at->isFuture());
    }

    public function test_the_code_page_carries_the_address_it_went_to(): void
    {
        Mail::fake();
        $user = $this->account();

        $this->askForCode($user->email);

        $this->get('/verify-code')
            ->assertOk()
            ->assertSee('Check your email')
            ->assertSee($user->email)
            ->assertSee('Send a new code');
    }

    public function test_a_code_is_not_mailed_to_an_email_with_no_account(): void
    {
        Mail::fake();
        $this->account();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'nobody@isu.edu.ph'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_a_deactivated_account_is_not_mailed_a_code(): void
    {
        Mail::fake();
        $user = $this->account('jdelacruz@isu.edu.ph', ['is_active' => false]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_an_account_that_does_not_work_the_office_is_not_mailed_a_code(): void
    {
        Mail::fake();
        $role = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $user = User::factory()->create(['email' => 'teacher@isu.edu.ph', 'role_id' => $role->id, 'is_active' => true]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_the_address_in_the_session_is_not_one_the_office_can_write_in(): void
    {
        // The account being reset is the one the code was mailed to, not the one
        // typed at the next step: a code for one account cannot be spent on
        // another by carrying a different address along.
        Mail::fake();
        $theirs = $this->account('jdelacruz@isu.edu.ph');
        $mine = $this->account('admin@isu.edu.ph');

        $code = $this->askForCode($theirs->email);

        $this->post('/verify-code', ['code' => $code, 'email' => $mine->email])
            ->assertRedirect(route('password.reset'));

        $this->post('/reset-password', [
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('a-new-password', $theirs->fresh()->password));
        $this->assertFalse(Hash::check('a-new-password', $mine->fresh()->password));
    }

    public function test_the_new_password_is_taken_and_the_office_signs_in_with_it(): void
    {
        Mail::fake();
        $user = $this->account();

        $code = $this->askForCode($user->email);

        $this->post('/verify-code', ['code' => $code])->assertRedirect(route('password.reset'));

        $this->get('/reset-password')
            ->assertOk()
            ->assertSee('Set a new password');

        $this->post('/reset-password', [
            'password' => 'bagong-password',
            'password_confirmation' => 'bagong-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('bagong-password', $user->fresh()->password));

        // The code is spent with the password it opened: the reset is over.
        $this->assertDatabaseCount('password_reset_codes', 0);

        // The old password is gone, and the new one opens the portal.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->post('/login', ['email' => $user->email, 'password' => 'bagong-password'])
            ->assertRedirect(route('admin.dashboard'));

        // The change is written against the account it belongs to, though the
        // office was not signed in when it was made.
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
        ]);
    }

    public function test_the_last_step_cannot_be_reached_without_a_code(): void
    {
        Mail::fake();
        $user = $this->account();

        $this->get('/reset-password')->assertRedirect(route('password.request'));

        $this->post('/reset-password', [
            'password' => 'bagong-password',
            'password_confirmation' => 'bagong-password',
        ])->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_a_wrong_code_does_not_open_the_last_step(): void
    {
        Mail::fake();
        $user = $this->account();

        $code = $this->askForCode($user->email);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->from('/verify-code')
            ->post('/verify-code', ['code' => $wrong])
            ->assertRedirect('/verify-code')
            ->assertSessionHasErrors('code');

        // The refusal is said once and only once, and it is said in the banner the
        // card carries: the boxes hold no words of their own, they only go red and
        // point at that banner.
        $this->get('/verify-code')
            ->assertOk()
            ->assertSee('aria-describedby="auth-error"', false)
            ->assertDontSee('code-error', false);

        $this->get('/reset-password')->assertRedirect(route('password.request'));

        // The wrong try is counted against the code, not against the door.
        $this->assertSame(1, PasswordResetCode::where('email', $user->email)->sole()->attempts);
    }

    public function test_a_wrong_try_does_not_move_the_codes_expiry(): void
    {
        Mail::fake();
        $user = $this->account();

        $code = $this->askForCode($user->email);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->from('/verify-code')
            ->post('/verify-code', ['code' => $wrong])
            ->assertSessionHasErrors('code');

        // Counting the try against the code must not touch the minutes it stands
        // for: a code that died the moment a digit was mistyped would take the
        // mailbox out of the office's hands at the first slip.
        $standing = PasswordResetCode::where('email', $user->email)->sole();

        $this->assertTrue(
            $standing->expires_at->greaterThan(now()->addMinutes(PasswordResetCode::EXPIRES_MINUTES - 1)),
            'A wrong try moved the code\'s expiry: the column itself is keeping time.',
        );

        // Which is the whole point: the code that was mailed still opens the step
        // it was sent for.
        $this->post('/verify-code', ['code' => $code])->assertRedirect(route('password.reset'));

        // On a MySQL/MariaDB server the field has to be a plain datetime, or the
        // server hangs ON UPDATE current_timestamp() on the column and moves the
        // expiry by itself — a rule no sqlite run can show, hence the asking.
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->assertSame('datetime', Schema::getColumnType('password_reset_codes', 'expires_at'));
            $this->assertStringNotContainsString(
                'ON UPDATE',
                DB::selectOne('show create table password_reset_codes')->{'Create Table'},
            );
        }
    }

    public function test_a_code_wears_out_on_wrong_tries(): void
    {
        Mail::fake();
        $user = $this->account();

        $code = $this->askForCode($user->email);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($try = 0; $try < PasswordResetCode::MAX_ATTEMPTS; $try++) {
            $this->post('/verify-code', ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // The code it was sent is the right one and it still does not open:
        // six digits are few enough to walk through, so the tries are spent.
        $this->post('/verify-code', ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/reset-password')->assertRedirect(route('password.request'));
    }

    public function test_a_code_stops_standing_after_its_minutes(): void
    {
        Mail::fake();
        $user = $this->account();

        $code = $this->askForCode($user->email);

        $this->travel(PasswordResetCode::EXPIRES_MINUTES + 1)->minutes();

        $this->post('/verify-code', ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/reset-password')->assertRedirect(route('password.request'));
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_a_code_opens_the_account_once(): void
    {
        Mail::fake();
        $user = $this->account();

        $code = $this->askForCode($user->email);

        $this->post('/verify-code', ['code' => $code])->assertRedirect(route('password.reset'));

        // The code was spent as it was accepted, so a second walk with the same
        // digits opens nothing.
        $this->post('/verify-code', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_asking_again_replaces_the_code_that_was_sent_before(): void
    {
        Mail::fake();
        $user = $this->account();

        $first = $this->askForCode($user->email);
        $second = $this->askForCode($user->email);

        // One row to an account, holding the code that was sent last: the code
        // the office has already asked past cannot still open the account.
        $this->assertDatabaseCount('password_reset_codes', 1);

        $stored = PasswordResetCode::where('email', $user->email)->sole();
        $this->assertTrue(Hash::check($second, $stored->code));

        if ($first !== $second) {
            $this->assertFalse(Hash::check($first, $stored->code));
        }

        $this->post('/verify-code', ['code' => $second])->assertRedirect(route('password.reset'));
    }

    public function test_the_new_password_has_to_stand_up_to_the_offices_own_rules(): void
    {
        Mail::fake();
        $user = $this->account();

        $code = $this->askForCode($user->email);
        $this->post('/verify-code', ['code' => $code])->assertRedirect(route('password.reset'));

        // Too short, and twice-typed wrong: neither is taken, and the account
        // still holds the password it had.
        $this->post('/reset-password', ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->post('/reset-password', ['password' => 'bagong-password', 'password_confirmation' => 'other-password'])
            ->assertSessionHasErrors(['password' => 'The two passwords are not the same.']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_last_step_says_so_when_the_two_passwords_are_not_the_same(): void
    {
        Mail::fake();
        $user = $this->account();

        $code = $this->askForCode($user->email);
        $this->post('/verify-code', ['code' => $code])->assertRedirect(route('password.reset'));

        // The note and the reading that shows it stand on the screen, so a pair
        // that does not read the same is caught while it is still in front of the
        // office rather than at the sign-in screen later on.
        $this->get('/reset-password')
            ->assertOk()
            ->assertSee('id="confirmation-error"', false)
            ->assertSee('The two passwords are not the same.', false)
            ->assertSee("getElementById('password_confirmation')", false)
            ->assertSee("again.setAttribute('aria-invalid', 'true')", false);
    }

    public function test_the_code_step_is_not_left_open_to_be_worked_over(): void
    {
        Mail::fake();
        $user = $this->account();
        $code = $this->askForCode($user->email);
        $wrong = $code === '000000' ? '111111' : '000000';

        // A handful of tries a minute, and then the page stops answering: the
        // gate is shut before the digits can be walked through.
        for ($try = 0; $try < 12; $try++) {
            if ($this->post('/verify-code', ['code' => $wrong])->getStatusCode() === 429) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail('the code step answered more tries than it is allowed to');
    }
}
