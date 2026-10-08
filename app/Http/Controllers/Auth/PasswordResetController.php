<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordCode as ResetPasswordCodeMail;
use App\Models\ActivityLog;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Forgot password, in three steps.
 *
 * The office writes the email of the account it lost the password of, the
 * system mails a six-digit code to that account, the office types the code
 * back, and only then may a new password be written. Nothing is changed by the
 * first two steps: the email proves the account is the one being reset, the
 * code proves the person typing is the one holding the mailbox, and the
 * password is set on the strength of both.
 *
 * The steps are held in the session rather than in the URL: a code stands for
 * one account (see PasswordResetCode), so the account being reset is remembered
 * here, the code page can only be opened after a code was asked for, and the
 * new-password page only after that code was accepted. Walking to the last step
 * by hand therefore does nothing — there is no account to write a password on.
 *
 * The mail goes out over the office's own mailbox (see config/mail.php), so the
 * code arrives from the address the office already answers, and a send that
 * fails is reported as a failed step rather than as a reset that quietly did
 * nothing.
 */
class PasswordResetController extends Controller
{
    /** The account whose password is being reset, between the steps. */
    private const SESSION_EMAIL = 'password_reset.email';

    /** When the code was accepted — the only thing that opens the last step. */
    private const SESSION_VERIFIED_AT = 'password_reset.verified_at';

    /**
     * How long the accepted code opens the last step. Long enough for a password
     * to be thought about and typed twice, and no longer: the account is left
     * alone again as soon as the office walks away from the screen.
     */
    private const VERIFIED_MINUTES = 15;

    /**
     * The first step: the form the office writes the account's email on.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($this->pendingEmail($request) !== null) {
            return redirect()->route('password.code');
        }

        return view('auth.forgot-password');
    }

    /**
     * Send the code: the account is looked up, a fresh code is written behind it
     * and mailed to the very address the reset is for.
     *
     * This is also the step the office comes back to for another code — from the
     * code page, where the email is carried along — so asking twice replaces the
     * first code rather than leaving two of them standing.
     */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = $this->account($data['email']);

        $code = PasswordResetCode::issue($user->email);

        try {
            Mail::to($user->email)->send(new ResetPasswordCodeMail(
                $code,
                PasswordResetCode::EXPIRES_MINUTES,
                $user->name,
            ));
        } catch (Throwable $e) {
            // The code is spent with the mail that carried it: a row nobody was
            // told about would only stand in the way of the next try.
            PasswordResetCode::clear($user->email);

            Log::error('Password reset code could not be sent', [
                'email' => $user->email,
                'exception' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'The code could not be sent just now. Please try again in a moment.',
            ]);
        }

        $request->session()->put(self::SESSION_EMAIL, $user->email);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'requested',
            'module' => 'auth',
            'description' => "{$user->name} asked for a password reset code from the sign-in screen.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('password.code')
            ->with('status', 'A six-digit code is on its way to that email address.');
    }

    /**
     * The second step: the form the code is typed into. It can only be reached
     * once a code has been asked for.
     */
    public function code(Request $request): View|RedirectResponse
    {
        $email = $this->pendingEmail($request);

        if ($email === null) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-code', [
            'email' => $email,
            'minutes' => PasswordResetCode::EXPIRES_MINUTES,
        ]);
    }

    /**
     * Take the code back. Only an accepted code opens the last step, and it is
     * spent as it is accepted, so the same code cannot be brought twice.
     */
    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $email = $this->pendingEmail($request);

        if ($email === null) {
            return redirect()->route('password.request');
        }

        if (! PasswordResetCode::verify($email, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That code is not right, or it has expired. You can ask for another one.',
            ]);
        }

        $request->session()->put(self::SESSION_VERIFIED_AT, now()->timestamp);

        return redirect()->route('password.reset');
    }

    /**
     * The last step: the form the new password is written on, which stands only
     * on the strength of a code that was accepted a moment ago.
     */
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $this->verified($request)) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'Verify the code we sent you before choosing a new password.']);
        }

        return view('auth.reset-password');
    }

    /**
     * Write the new password and close the reset: the code is given up, the
     * account is left to sign in with what it now holds, and the change is
     * written into the activity log against the account it belongs to — the
     * office is not signed in at this point, so the log is written by hand
     * rather than through ActivityLog::record, which names whoever is.
     */
    public function update(Request $request): RedirectResponse
    {
        $email = $this->pendingEmail($request);

        if ($email === null || ! $this->verified($request)) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'Verify the code we sent you before choosing a new password.']);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            // The same words the screen says while the two are being typed, so a
            // mismatch that reaches the server reads as it would have there.
            'password.confirmed' => 'The two passwords are not the same.',
        ]);

        $user = User::where('email', $email)->firstOrFail();

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        PasswordResetCode::clear($email);

        $request->session()->forget([self::SESSION_EMAIL, self::SESSION_VERIFIED_AT]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'updated',
            'module' => 'auth',
            'description' => "{$user->name} set a new password from the sign-in screen.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. Sign in with your new password.');
    }

    /**
     * The account a code may be sent to: the one the email belongs to, and only
     * while the portal would let it sign in at all. A code for an account that
     * is deactivated, or that does not work the supply office, would open a door
     * the sign-in screen keeps shut — so the same three answers are given here
     * as there, and no code is sent.
     */
    private function account(string $email): User
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'We could not find an account with that email address.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Contact the supply office administrator.',
            ]);
        }

        if (! $user->isAdmin() && ! $user->isInspectionStaff()) {
            throw ValidationException::withMessages([
                'email' => 'This portal is for supply office accounts only.',
            ]);
        }

        return $user;
    }

    /** The account between the steps, or null where none was asked for. */
    private function pendingEmail(Request $request): ?string
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        return is_string($email) && $email !== '' ? $email : null;
    }

    /** Whether a code was accepted at this door, and recently enough to count. */
    private function verified(Request $request): bool
    {
        $at = $request->session()->get(self::SESSION_VERIFIED_AT);

        if (! is_int($at) && ! is_numeric($at)) {
            return false;
        }

        return now()->timestamp - (int) $at <= self::VERIFIED_MINUTES * 60;
    }
}
