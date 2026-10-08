<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The portal is left signed in. An officer signs in at the start of the day and
 * signs out when they are done; sitting idle in between, or closing the window
 * and coming back, does not sign them out.
 */
class SessionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => Role::ADMIN, 'display_name' => 'Admin']);

        $this->admin = User::factory()->create([
            'role_id' => $admin->id,
            'is_active' => true,
            'password' => 'secret1234',
        ]);
    }

    /**
     * The session is given a year before it is allowed to go idle, and its
     * cookie is not thrown away when the browser closes — so only signing out
     * ends it. Read off the configuration the application actually runs under,
     * which is what the sign-in is bound by.
     */
    public function test_the_session_is_kept_until_the_account_signs_out(): void
    {
        // A year of idleness: long enough that no working day reaches it.
        $this->assertGreaterThanOrEqual(
            60 * 24 * 365,
            (int) config('session.lifetime'),
            'The session is timed out sooner than the office asked: it must survive an idle day.'
        );

        // And the cookie it rides on outlives the browser being closed.
        $this->assertFalse((bool) config('session.expire_on_close'));
    }

    /**
     * The sign-in page offers to keep the account open, and offers it already
     * ticked: the officer asks to be signed out rather than asking to stay.
     */
    public function test_the_sign_in_page_keeps_the_account_open_by_default(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Keep me signed in')
            ->assertSee('name="remember" checked', false);
    }

    /**
     * Signing in with the box ticked leaves a cookie that rebuilds the session
     * on a later visit, so a cleared session — a browser restarted, a session
     * swept — still finds the account signed in.
     */
    public function test_signing_in_leaves_a_cookie_that_finds_the_account_again(): void
    {
        $response = $this->post('/login', [
            'email' => $this->admin->email,
            'password' => 'secret1234',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('admin.dashboard'));

        $remember = collect($response->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertNotNull($remember, 'Signing in left nothing that finds the account again.');
        // Months out rather than minutes out: this cookie is what carries the
        // account across a session that has gone.
        $this->assertGreaterThan(now()->addDays(300)->getTimestamp(), $remember->getExpiresTime());

        // The session cookie itself is no browser-session cookie either.
        $session = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($session);
        $this->assertGreaterThan(now()->addDays(300)->getTimestamp(), $session->getExpiresTime());
    }
}
