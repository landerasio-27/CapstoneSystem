{{--
    Forgot password, step three: the new password.

    The last step, and the only one that changes anything. It stands on the
    code that was accepted a moment ago — walk to it without one and there is no
    account to write a password on, so the reset sends the office back to the
    first step (see PasswordResetController).

    The password is asked for twice because it cannot be read back: what is
    written here is the account's way in from now on, and a mistyped one would
    only be found out at the sign-in screen. The screen therefore reads the two
    answers against each other as they are typed, so a slip is caught here,
    while the password is still in front of the office (see the script at the
    foot of this screen).
--}}
@extends('auth.layout')

@section('page-title', 'Set a new password — ISU Supply Account')
@section('skip-target', '#password')
@section('title', 'Set a new password')
@section('subtitle', 'Choose the password you will sign in with from now on — at least eight characters.')

@section('form')
    <form class="login-form" method="POST" action="{{ route('password.reset') }}" novalidate>
        @csrf

        <label class="field-label" for="password">New Password</label>
        <div class="field">
            <svg class="ficon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input id="password" type="password" name="password" required autofocus
                   autocomplete="new-password" placeholder="At least 8 characters"
                   aria-describedby="password-error" @error('password') aria-invalid="true" @enderror>
            <button type="button" class="eye" data-eye="password" aria-label="Show password" aria-pressed="false">
                <svg data-eye-open width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg data-eye-off width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
        </div>
        @error('password')
            <p class="field-err" id="password-error">{{ $message }}</p>
        @else
            <p class="field-err" id="password-error" hidden></p>
        @enderror

        <label class="field-label" for="password_confirmation">Confirm New Password</label>
        <div class="field">
            <svg class="ficon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                   autocomplete="new-password" placeholder="Type it again"
                   aria-describedby="confirmation-error">
        </div>
        {{-- Said as soon as the two do not read the same, rather than left for the
             sign-in screen to find out later. The same words are what the office
             is told when a mismatch reaches the server instead of being caught
             here (see the message on the confirmed rule in the controller). --}}
        <p class="field-err" id="confirmation-error" hidden>The two passwords are not the same.</p>

        <button type="submit" class="btn-login">Save the password</button>
    </form>

    <p class="forgot-note">
        Once it is saved, this code is spent and the account signs in with the new password.
        <a href="{{ route('login') }}">Back to sign in</a>
    </p>

    <script>
        // Show / hide on each of the password fields, the way the sign-in screen
        // has it: the button stays clickable and announces what it did.
        (function () {
            document.querySelectorAll('.eye[data-eye]').forEach(function (btn) {
                var input = document.getElementById(btn.dataset.eye);
                if (!input) return;
                var open = btn.querySelector('[data-eye-open]');
                var off = btn.querySelector('[data-eye-off]');

                btn.addEventListener('click', function () {
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    open.style.display = show ? 'none' : '';
                    off.style.display = show ? '' : 'none';
                    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                });
            });
        })();

        // The two answers read against each other as they are typed. The warning
        // is kept quiet while the second field is still empty — there is nothing
        // to compare yet — and it lifts the moment they read the same, so the
        // field is red only while it actually holds a different password.
        (function () {
            var first = document.getElementById('password');
            var again = document.getElementById('password_confirmation');
            var note = document.getElementById('confirmation-error');
            if (!first || !again || !note) return;

            function compare() {
                var apart = again.value !== '' && again.value !== first.value;
                note.hidden = !apart;

                if (apart) again.setAttribute('aria-invalid', 'true');
                else again.removeAttribute('aria-invalid');
            }

            [first, again].forEach(function (field) {
                field.addEventListener('input', compare);
            });

            compare();
        })();
    </script>
@endsection
