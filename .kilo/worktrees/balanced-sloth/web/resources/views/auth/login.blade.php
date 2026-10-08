<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in &mdash; ISU Supply Account</title>

    {{-- PWA: installable from the sign-in screen too. --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#087A16">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/images/pwa-192.png">

    {{-- Respect the saved dark-mode choice (no flash) before CSS paints. --}}
    <script>
        (function () {
            try {
                var m = localStorage.getItem('theme') || 'system';
                var dark = m === 'dark' || (m === 'system' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- The card, the fields and the messages: shared with every other
         screen of the sign-in flow (see _styles). --}}
    @include('auth._styles')
</head>
<body>
    <a class="skip-link" href="#email">Skip to the login form</a>

    {{-- Extremely subtle background accents: two soft green washes and one thin curve. --}}
    <div class="bg-accent" aria-hidden="true">
        <span class="wash wash-1"></span>
        <span class="wash wash-2"></span>
        <svg class="curve" viewBox="0 0 520 260" fill="none" preserveAspectRatio="none">
            <path d="M0 250 C 150 250, 220 120, 340 70 S 480 40, 520 52" stroke="#C4DECE" stroke-width="1.4"/>
        </svg>
    </div>

    <main class="login-page">
        <div class="login-shell">
            @php
                // Presentation only: the stored message is mapped to the wording the
                // portal shows, without touching authentication or its messages.
                $authFailed = str_contains((string) $errors->first(), 'credentials do not match');
            @endphp

            <section class="login-card" aria-labelledby="login-title">
                <header class="brand">
                    <div class="brand-logo">
                        <img src="{{ asset('images/logo.png') }}" alt="Isabela State University seal" width="64" height="64">
                    </div>
                    <p class="brand-name">Isabela State University</p>
                    <p class="brand-campus">Cauayan Campus</p>
                </header>

                <h1 class="title" id="login-title">Welcome Back</h1>
                <p class="subtitle">Sign in to the ISU Supply Management System</p>

                {{-- What the flow before this screen ended with: a password that was
                     just reset, or a rejection the office has to be told about. --}}
                @if (session('status'))
                    <div class="login-ok" role="status">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="login-error" role="alert">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>{{ $authFailed ? 'Invalid email or password.' : $errors->first() }}</span>
                    </div>
                @endif

                {{-- The authentication flow is untouched: same route, same CSRF token,
                     same fields. Only the presentation changed. --}}
                <form id="login-form" class="login-form" method="POST" action="{{ route('login') }}" novalidate>
                    @csrf

                    <label class="field-label" for="email">Email Address</label>
                    <div class="field">
                        <svg class="ficon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                               autocomplete="username" inputmode="email" spellcheck="false"
                               placeholder="Enter your university email"
                               aria-describedby="email-error" @error('email') aria-invalid="true" @enderror>
                    </div>
                    @if ($errors->has('email') && ! $authFailed)
                        <p class="field-err" id="email-error">{{ $errors->first('email') }}</p>
                    @else
                        <p class="field-err" id="email-error" hidden></p>
                    @endif

                    <label class="field-label" for="password">Password</label>
                    <div class="field">
                        <svg class="ficon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input id="password" type="password" name="password" required
                               autocomplete="current-password" placeholder="Enter your password"
                               aria-describedby="password-error" @error('password') aria-invalid="true" @enderror>
                        <button type="button" class="eye" id="eye-btn" aria-label="Show password" aria-pressed="false" aria-controls="password">
                            <svg id="eye-open" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="eye-off" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="field-err" id="password-error">{{ $message }}</p>
                    @else
                        <p class="field-err" id="password-error" hidden></p>
                    @enderror

                    <div class="form-row">
                        {{-- Ticked already: the office signs in at the start of
                             the day and signs out when it is done, so the account
                             is kept open unless the officer says otherwise. It can
                             still be unticked on a machine that is shared. --}}
                        <label class="remember" for="remember">
                            <input id="remember" type="checkbox" name="remember" checked> Keep me signed in
                        </label>
                        {{-- The way back in when the password is lost: the account's own
                             email, a code mailed to it, and a new password written on the
                             strength of that code (see PasswordResetController). --}}
                        <a class="forgot-toggle" href="{{ route('password.request') }}">Forgot Password?</a>
                    </div>

                    <button id="login-btn" type="submit" class="btn-login">Login</button>
                </form>

                <p class="secure-note">Authorized users only</p>
            </section>

            <footer class="page-foot">
                <p>Isabela State University &ndash; Cauayan Campus</p>
                <p>ISU Supply Management System</p>
            </footer>
        </div>
    </main>

    <script>
        // Show / hide password — the eye button stays clickable and announces its state.
        (function () {
            var btn = document.getElementById('eye-btn');
            var input = document.getElementById('password');
            var open = document.getElementById('eye-open');
            var off = document.getElementById('eye-off');
            if (!btn || !input) return;

            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                open.style.display = show ? 'none' : '';
                off.style.display = show ? '' : 'none';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        })();

        // Inline validation + loading state. The server still validates everything;
        // this only saves a round trip and keeps the copy consistent.
        (function () {
            var form = document.getElementById('login-form');
            if (!form) return;

            var email = document.getElementById('email');
            var password = document.getElementById('password');
            var emailError = document.getElementById('email-error');
            var passwordError = document.getElementById('password-error');

            function setError(field, node, message) {
                node.textContent = message;
                node.removeAttribute('hidden');
                field.setAttribute('aria-invalid', 'true');
            }

            function clearError(field, node) {
                node.textContent = '';
                node.setAttribute('hidden', '');
                field.removeAttribute('aria-invalid');
            }

            function check() {
                var ok = true;

                if (!email.value.trim()) {
                    setError(email, emailError, 'Please enter your email address.');
                    ok = false;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                    setError(email, emailError, 'Please enter a valid email address.');
                    ok = false;
                } else {
                    clearError(email, emailError);
                }

                if (!password.value) {
                    setError(password, passwordError, 'Please enter your password.');
                    ok = false;
                } else {
                    clearError(password, passwordError);
                }

                return ok;
            }

            [[email, emailError], [password, passwordError]].forEach(function (pair) {
                pair[0].addEventListener('input', function () {
                    if (!pair[1].hasAttribute('hidden')) clearError(pair[0], pair[1]);
                });
            });

            form.addEventListener('submit', function (e) {
                var btn = document.getElementById('login-btn');

                if (btn.dataset.loading === '1') { e.preventDefault(); return; }

                if (!check()) {
                    e.preventDefault();
                    var firstInvalid = form.querySelector('[aria-invalid="true"]');
                    if (firstInvalid) firstInvalid.focus();
                    return;
                }

                // Loading state, exactly as before: spinner + text, fields locked.
                btn.dataset.loading = '1';
                btn.disabled = true;
                btn.innerHTML = '<span class="app-btn-spinner"></span> Signing in&hellip;';
                email.readOnly = true;
                password.readOnly = true;
            });
        })();

        // Register the PWA service worker.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
        }
    </script>
</body>
</html>
