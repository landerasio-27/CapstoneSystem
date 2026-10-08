{{--
    Forgot password, step two: the code that was mailed.

    The address the code went to is written out in full — the office is checking
    the mail in the next window, and the words here are what tells them which
    account they are opening. The code is spent as it is accepted, and only an
    accepted one opens the last step (see PasswordResetController).

    The code is typed into six boxes, one digit each, the way it reads on the
    mail it came in; the boxes are gathered into the one field the form sends,
    which is the field the controller reads (see the script at the foot of this
    screen, and PasswordResetController).

    Expects: $email (the account the code was sent to), $minutes (how long it
    stands for).
--}}
@extends('auth.layout')

@section('page-title', 'Verify your code — ISU Supply Account')
@section('skip-target', '#code-1')
@section('title', 'Check your email')

@section('subtitle')
    We sent a six-digit code to <strong>{{ $email }}</strong>. It stands for {{ $minutes }} minutes.
@endsection

@section('form')
    <form class="login-form" method="POST" action="{{ route('password.code.verify') }}" novalidate>
        @csrf

        {{-- What the office types lives in the six boxes below; this is the one
             field they add up to, and the only one with a name to send. --}}
        <input type="hidden" name="code" id="code" value="{{ old('code') }}">

        <label class="field-label" for="code-1">Six-digit code</label>
        {{-- A rejected code is said once and only once: the words stand in the
             banner at the top of the card, which is where the reading looks when
             the screen comes back (see auth.layout), and the boxes carry the rest
             of it by going red. Saying it again under the boxes said it twice. --}}
        <div class="code-row" id="code-row" role="group" aria-label="Six-digit code"
             aria-describedby="auth-error" @error('code') aria-invalid="true" @enderror>
            @php($typed = preg_replace('/\D/', '', (string) old('code')))
            @for ($digit = 0; $digit < 6; $digit++)
                <input class="code-box" id="code-{{ $digit + 1 }}" type="text" maxlength="1"
                       inputmode="numeric" pattern="[0-9]" value="{{ $typed[$digit] ?? '' }}"
                       autocomplete="{{ $digit === 0 ? 'one-time-code' : 'off' }}"
                       aria-label="Digit {{ $digit + 1 }} of 6" spellcheck="false"
                       @if ($digit === 0) autofocus @endif>
            @endfor
        </div>

        <button type="submit" class="btn-login">Verify the code</button>
    </form>

    <div class="forgot-note">
        <p style="margin:0;">Nothing in your inbox? Look in the spam folder first, then ask for another code.</p>
        <div class="note-actions">
            {{-- Asking again from here carries the address along, so the office does
                 not type it a second time — and the code it replaces is spent by it
                 rather than left standing beside the new one. --}}
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <button type="submit" class="link-btn">Send a new code</button>
            </form>
            <span aria-hidden="true">&middot;</span>
            <a href="{{ route('password.request') }}">Use a different email</a>
        </div>
    </div>

    <script>
        // Six boxes, one code. A digit typed lands in the box it was typed in and
        // passes the caret on; a code pasted from the mail is spread across the
        // boxes from wherever it was pasted rather than piled into the first one;
        // and the sixth digit puts the code in the office's hands without making
        // them reach for the button.
        (function () {
            var boxes = Array.prototype.slice.call(document.querySelectorAll('.code-box'));
            var field = document.getElementById('code');
            if (!boxes.length || !field) return;

            var form = field.form;

            function gather() {
                var code = boxes.map(function (box) { return box.value.replace(/\D/g, ''); }).join('');
                field.value = code;
                boxes.forEach(function (box) {
                    if (box.value) box.setAttribute('data-filled', '');
                    else box.removeAttribute('data-filled');
                });
                return code;
            }

            function settle(index) {
                var box = boxes[Math.max(0, Math.min(index, boxes.length - 1))];
                box.focus();
                box.select();
            }

            // Send the code the moment all six boxes hold a digit: it cannot be
            // made shorter, and the office has nothing left to do on this screen.
            function send() {
                if (!form || gather().length !== 6) return;
                if (form.requestSubmit) form.requestSubmit();
                else form.submit();
            }

            boxes.forEach(function (box, at) {
                box.addEventListener('input', function () {
                    // Only a digit stands, and only the one just typed.
                    box.value = box.value.replace(/\D/g, '').slice(-1);
                    gather();
                    if (box.value) settle(at + 1);
                    send();
                });

                box.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace' && !box.value && at > 0) {
                        event.preventDefault();
                        boxes[at - 1].value = '';
                        gather();
                        settle(at - 1);
                    } else if (event.key === 'ArrowLeft' && at > 0) {
                        event.preventDefault();
                        settle(at - 1);
                    } else if (event.key === 'ArrowRight' && at < boxes.length - 1) {
                        event.preventDefault();
                        settle(at + 1);
                    }
                });

                box.addEventListener('focus', function () { box.select(); });

                box.addEventListener('paste', function (event) {
                    var text = (event.clipboardData || window.clipboardData).getData('text') || '';
                    var digits = text.replace(/\D/g, '');
                    if (!digits) return;
                    event.preventDefault();
                    for (var i = 0; i < digits.length && at + i < boxes.length; i++) {
                        boxes[at + i].value = digits[i];
                    }
                    gather();
                    settle(at + digits.length);
                    send();
                });
            });

            // Come back to this screen rejected and the code is put back as it was
            // typed, so it can be read over rather than written out again.
            gather();
            if (field.value.length < 6) settle(field.value.length);
        })();
    </script>
@endsection
