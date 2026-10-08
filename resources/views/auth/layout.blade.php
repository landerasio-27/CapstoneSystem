{{--
    The card the sign-in screens stand on.

    The same card the sign-in screen itself is built on: the seal, the campus,
    one title, one line under it, and then whatever the screen came to ask for.
    On the card a screen may also say what the step before it ended with — the
    rejection a wrong code earned, or the status the step before it left behind
    (see PasswordResetController).

    The three steps of the password reset differ in the field they hold and the
    words over it, and in nothing else, so the chrome they share is written once
    here: a screen fills in its own title, its own line, and its own form.

    Expects, from the screen that extends this: @section('title'),
    @section('subtitle'), @section('form'), and optionally @section('page-title')
    and @section('skip-target') — where the keyboard lands first.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('page-title', 'Sign in &mdash; ISU Supply Account')</title>

    {{-- PWA: installable from these screens too. --}}
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

    @include('auth._styles')
</head>
<body>
    <a class="skip-link" href="@yield('skip-target', '#email')">Skip to the form</a>

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
            <section class="login-card" aria-labelledby="auth-title">
                <header class="brand">
                    <div class="brand-logo">
                        <img src="{{ asset('images/logo.png') }}" alt="Isabela State University seal" width="64" height="64">
                    </div>
                    <p class="brand-name">Isabela State University</p>
                    <p class="brand-campus">Cauayan Campus</p>
                </header>

                <h1 class="title" id="auth-title">@yield('title')</h1>
                <p class="subtitle">@yield('subtitle')</p>

                {{-- What the step before this one ended with: a code that was sent, or
                     the reason the step was refused. --}}
                @if (session('status'))
                    <div class="login-ok" role="status">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    {{-- The one place a refusal is said on these screens: a field the
                         screen can point at goes red, and the words for it stand
                         here rather than under the field beside it. --}}
                    <div class="login-error" role="alert" id="auth-error">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @yield('form')

                <p class="secure-note">Authorized users only</p>
            </section>

            <footer class="page-foot">
                <p>Isabela State University &ndash; Cauayan Campus</p>
                <p>ISU Supply Management System</p>
            </footer>
        </div>
    </main>
</body>
</html>
