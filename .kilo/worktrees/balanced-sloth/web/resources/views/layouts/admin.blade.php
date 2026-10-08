<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') &mdash; ISU Supply Account</title>

    {{-- PWA: makes the portal installable as a desktop/standalone app. --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#006400">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ISU Supply">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/images/pwa-192.png">

    {{-- Apply the saved theme BEFORE any CSS/paint so there's no flash of the wrong theme. --}}
    <script>
        (function () {
            function systemDark() {
                return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            }
            function effective(mode) {
                return mode === 'system' ? (systemDark() ? 'dark' : 'light') : mode;
            }
            function apply(mode) {
                var root = document.documentElement;
                root.setAttribute('data-theme', effective(mode));
                root.setAttribute('data-theme-mode', mode);
            }
            var stored;
            try { stored = localStorage.getItem('theme'); } catch (e) {}
            if (stored !== 'light' && stored !== 'dark' && stored !== 'system') stored = 'system';
            apply(stored);

            window.__themeCycle = function () {
                var cur = 'system';
                try { cur = localStorage.getItem('theme') || 'system'; } catch (e) {}
                var next = cur === 'light' ? 'dark' : (cur === 'dark' ? 'system' : 'light');
                try { localStorage.setItem('theme', next); } catch (e) {}
                apply(next);
            };

            // Track OS changes while following the system setting.
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                    var m = 'system';
                    try { m = localStorage.getItem('theme') || 'system'; } catch (e) {}
                    if (m === 'system') apply('system');
                });
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/searchable-select.js', 'resources/js/sidebar.js', 'resources/js/filter-bar.js'])
    <style>
        /* Thin, subtle scrollbar for the sidebar's own independent scroll area */
        .sidebar-scroll { scrollbar-width: thin; scrollbar-color: rgba(148, 163, 184, 0.5) transparent; }
        .sidebar-scroll::-webkit-scrollbar { width: 6px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background-color: rgba(148, 163, 184, 0.45); border-radius: 9999px; }
        .sidebar-scroll:hover::-webkit-scrollbar-thumb { background-color: rgba(148, 163, 184, 0.7); }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased" data-auth-user="{{ auth()->id() }}">
    <!-- Mobile overlay (only shows when the off-canvas drawer is open) -->
    <div id="sidebar-overlay" onclick="toggleSidebar(false)"
         class="fixed inset-0 z-30 hidden bg-slate-900/40 backdrop-blur-sm lg:hidden"></div>

    <!-- Sidebar: fixed to the viewport, full height, never scrolls with the page.
         Off-canvas drawer on mobile (< lg); always visible on desktop (>= lg). -->
    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full transform flex-col border-r border-slate-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0">
        <div class="app-sidebar-head flex h-16 shrink-0 items-center gap-3 border-b border-slate-100 px-4">
            <img src="{{ asset('images/logo.png') }}" alt="Isabela State University seal"
                 class="app-sidebar-logo h-12 w-12 shrink-0 object-contain">
            <div class="min-w-0 leading-tight nav-label-wrap">
                <p class="whitespace-nowrap text-sm font-semibold text-slate-800">Supply Management</p>
                <p class="whitespace-nowrap text-[11px] text-slate-400">Cauayan Campus</p>
            </div>
            {{-- Collapse to an icon rail (desktop). Hidden on mobile where the drawer always shows labels.
                 The button itself must survive the rail (it is the only way back), so it does NOT
                 carry nav-label-wrap — the rail centers it below the seal instead. --}}
            <button type="button" id="sidebar-collapse"
                    class="nav-collapse-btn ml-auto hidden rounded-lg p-1.5 text-white/70 transition hover:bg-white/15 hover:text-white lg:block"
                    title="Collapse sidebar">
                <span class="sr-only">Collapse sidebar</span>
                <svg class="nav-collapse-icon h-4 w-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17-5-5 5-5M18 17l-5-5 5-5"/></svg>
            </button>
        </div>

        @include('layouts.partials._nav', ['navSections' => $navSections])

        <div class="shrink-0 border-t border-slate-100 p-3">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-800">
                    {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->take(2)->implode('') }}
                </div>
                <div class="min-w-0 leading-tight nav-label-wrap">
                    <p class="truncate text-sm font-medium text-slate-800">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ auth()->user()->role?->display_name }}</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main column: offset by the sidebar width on desktop so nothing hides behind it.
         The whole page scrolls here (single scrollbar); only the header is sticky. -->
    {{-- #app-main is also the handle the printable report sheets use to drop
         the sidebar offset when the browser prints the page itself. --}}
    <div id="app-main" class="flex min-h-screen flex-col lg:ml-64">
        <header class="app-header sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar(true)" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                    <span class="sr-only">Open sidebar</span>
                    <x-icon name="menu" class="h-5 w-5" />
                </button>
                <h1 class="text-lg font-semibold text-slate-800">@yield('title', 'Dashboard')</h1>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                {{-- Install-as-app button. It is drawn on every page: the browser
                     offers its one-click install on some loads and not others, so
                     a button that appeared and vanished page by page was read as a
                     missing button. JS takes it away only once the app is already
                     installed, and pressing it when there is no install to offer
                     explains why rather than doing nothing. --}}
                <div class="relative" id="pwa-install-wrap">
                    <button id="pwa-install" type="button" title="Install app"
                            aria-haspopup="true" aria-expanded="false" aria-controls="pwa-install-hint"
                            class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-100 hover:text-slate-800">
                        <x-icon name="download" class="h-4 w-4" />
                        <span class="hidden sm:inline">Install app</span>
                    </button>

                    {{-- Why there is no install to offer, said where the button is. --}}
                    <div id="pwa-install-hint"
                         class="absolute right-0 z-30 mt-2 hidden w-80 rounded-xl border border-slate-200 bg-white p-4 shadow-lg">
                        <p class="text-sm font-semibold text-slate-800">Install this app</p>
                        <p id="pwa-install-hint-text" class="mt-1 text-sm leading-relaxed text-slate-500"></p>
                    </div>
                </div>

                <button type="button" onclick="window.__themeCycle()"
                        title="Switch theme (Light / Dark / System)"
                        class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                    <span class="sr-only">Switch theme</span>
                    {{-- Sun (light) --}}
                    <svg class="theme-ico-light h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>
                    </svg>
                    {{-- Moon (dark) --}}
                    <svg class="theme-ico-dark h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path>
                    </svg>
                    {{-- Auto (system) --}}
                    <svg class="theme-ico-system h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="13" rx="2"></rect>
                        <path d="M8 21h8M12 17v4"></path>
                    </svg>
                </button>

                {{-- The account menu: the name itself opens Settings and Logout,
                     so the header carries no separate sign-out button. --}}
                <div class="relative" id="user-menu">
                    <button type="button" id="user-menu-trigger" aria-haspopup="true" aria-expanded="false"
                            title="Account menu"
                            class="flex max-w-[12rem] items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-slate-100">
                        <span class="min-w-0 leading-tight">
                            <span class="block truncate text-sm font-medium text-slate-800">{{ auth()->user()->name }}</span>
                            <span class="block truncate text-xs text-slate-400">{{ auth()->user()->role?->display_name }}</span>
                        </span>
                        <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400" />
                    </button>

                    <div id="user-menu-panel"
                         class="absolute right-0 z-30 mt-2 hidden w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                        <a href="{{ route('admin.account-settings.index') }}"
                           class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                            <x-icon name="settings" class="h-4 w-4 text-slate-400" />
                            Settings
                        </a>

                        <form method="POST" action="{{ route('admin.logout') }}" onsubmit="showLoading('Logging out…')">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-slate-600 transition hover:bg-red-50 hover:text-red-600">
                                <x-icon name="logout" class="h-4 w-4 text-slate-400" />
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            {{-- Breadcrumbs: Documents / RIS-2026-00003 / Details. The trail is
                 derived from the route + config/navigation.php; the old plain
                 "Back to <list>" link is its ancestor, kept in spirit. --}}
            @php $breadcrumbs = \App\Support\Navigation::breadcrumbs(); @endphp
            @if (count($breadcrumbs) > 1)
                <nav aria-label="Breadcrumb" class="mb-4">
                    <ol class="flex flex-wrap items-center gap-1.5 text-sm">
                        @foreach ($breadcrumbs as $i => $crumb)
                            @if ($i > 0)<li class="text-slate-300">/</li>@endif
                            <li>
                                @if ($crumb['url'] && $i < count($breadcrumbs) - 1)
                                    <a href="{{ $crumb['url'] }}" class="text-slate-500 transition hover:text-brand-700">{{ $crumb['label'] }}</a>
                                @else
                                    <span class="font-medium text-slate-700">{{ $crumb['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @elseif (count($breadcrumbs) === 1)
                <div class="mb-4">
                    <a href="{{ $breadcrumbs[0]['url'] }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 transition hover:text-slate-700">
                        <x-icon name="arrow-left" class="h-4 w-4" />
                        Back to {{ $breadcrumbs[0]['label'] }}
                    </a>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 flex items-start gap-2 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <x-icon name="warning" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{-- The sheet plumbing is defined before the page's own content, not
                 with the rest of the scripts at the foot of the document: a page
                 that was refused a save reopens its sheet as it renders, so the
                 tool it calls has to be in place by then. --}}
            <script>
                // Sheets that open over the page rather than pushing it down —
                // adding a user, and whatever else needs a form of its own. A
                // sheet is addressed by id and marked with data-modal, so Escape
                // closes whichever ones are open; the background is locked while
                // one is up.
                window.ISUModal = {
                    open(id) {
                        const sheet = document.getElementById(id);
                        if (!sheet) return;

                        sheet.classList.remove('hidden');
                        sheet.classList.add('flex');
                        document.body.classList.add('overflow-hidden');

                        const first = sheet.querySelector('[data-modal-focus]');
                        if (first) first.focus();
                    },
                    close(id) {
                        const sheet = document.getElementById(id);
                        if (!sheet) return;

                        sheet.classList.add('hidden');
                        sheet.classList.remove('flex');
                        document.body.classList.remove('overflow-hidden');
                    },
                };
            </script>

            @yield('content')
        </main>
    </div>

    <x-loading-overlay />

    @include('layouts.partials._search-overlay')

    {{-- Reusable confirmation modal. Any <form data-confirm="message"> is intercepted
         and only submitted after the user approves here (replaces window.confirm). --}}
    <div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUConfirm.hide()"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
            <div class="mb-3 flex items-center gap-3">
                <span id="confirm-icon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <x-icon name="trash" class="h-5 w-5" />
                </span>
                <h3 id="confirm-title" class="text-base font-semibold text-slate-800">Please confirm</h3>
            </div>
            <p id="confirm-message" class="mb-6 text-sm text-slate-500">This action cannot be undone.</p>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="ISUConfirm.hide()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                </button>
                <button type="button" id="confirm-ok" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    <x-icon name="trash" class="h-4 w-4" /> <span id="confirm-ok-label">Delete</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar(open) {
            document.getElementById('sidebar').classList.toggle('-translate-x-full', !open);
            document.getElementById('sidebar-overlay').classList.toggle('hidden', !open);
            // Lock background scroll while the mobile drawer is open.
            document.body.classList.toggle('overflow-hidden', open);
        }

        window.ISUConfirm = {
            form: null,
            show(form) {
                this.form = form;
                document.getElementById('confirm-message').textContent =
                    form.getAttribute('data-confirm') || 'This action cannot be undone.';
                document.getElementById('confirm-title').textContent =
                    form.getAttribute('data-confirm-title') || 'Please confirm';
                document.getElementById('confirm-ok-label').textContent =
                    form.getAttribute('data-confirm-button') || 'Delete';
                const m = document.getElementById('confirm-modal');
                m.classList.remove('hidden');
                m.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            },
            hide() {
                const m = document.getElementById('confirm-modal');
                m.classList.add('hidden');
                m.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
                this.form = null;
            },
        };

        // Intercept any form that opts in with data-confirm.
        document.addEventListener('submit', function (e) {
            const f = e.target;
            if (f.matches('[data-confirm]') && !f.dataset.confirmed) {
                e.preventDefault();
                window.ISUConfirm.show(f);
            }
        });

        document.getElementById('confirm-ok').addEventListener('click', function () {
            const f = window.ISUConfirm.form;
            window.ISUConfirm.hide();
            if (f) {
                f.dataset.confirmed = '1';
                f.submit();
            }
        });

        // ISUModal is defined above the page content, where a sheet that came
        // back from a refused save can already reach it.
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            document.querySelectorAll('[data-modal]').forEach(function (sheet) {
                window.ISUModal.close(sheet.id);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') window.ISUConfirm.hide();
        });
    </script>

    {{-- The account menu in the header: clicking the name opens it, clicking
         away or pressing Escape closes it. --}}
    <script>
        (function () {
            const wrap = document.getElementById('user-menu');
            if (!wrap) return;

            const trigger = document.getElementById('user-menu-trigger');
            const panel = document.getElementById('user-menu-panel');

            function close() {
                panel.classList.add('hidden');
                trigger.setAttribute('aria-expanded', 'false');
            }

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                const willOpen = panel.classList.contains('hidden');
                panel.classList.toggle('hidden', !willOpen);
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });

            document.addEventListener('click', function (e) {
                if (!wrap.contains(e.target)) close();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') close();
            });
        })();
    </script>

    {{-- The ⋯ that stands on a register row and the menu behind it: the actions on
         one account, out of the row so the register reads as a list of accounts.

         The menu is drawn against the window rather than the row it belongs to,
         because the registers that carry it are cut off at their own edges — a
         menu anchored inside one would be clipped by the very table it opens
         over. Where it lands is measured as it opens, and turned upward where
         there is no room below, which is what the last row of a register needs. --}}
    <script>
        window.ISUActions = (function () {
            let open = null;

            function hide() {
                if (!open) return;

                // The keyboard goes back to the button it was opened from, but only
                // when it was inside the menu: hiding on an outside click must not
                // pull the focus off whatever was clicked.
                if (open.menu.contains(document.activeElement)) open.button.focus();

                open.menu.classList.add('hidden');
                open.button.setAttribute('aria-expanded', 'false');
                open = null;
            }

            function place(button, menu) {
                const box = button.getBoundingClientRect();
                const edge = 8;

                const left = Math.max(edge, Math.min(
                    box.right - menu.offsetWidth,
                    window.innerWidth - menu.offsetWidth - edge,
                ));

                let top = box.bottom + 6;

                if (top + menu.offsetHeight > window.innerHeight - edge) {
                    top = Math.max(edge, box.top - 6 - menu.offsetHeight);
                }

                menu.style.left = left + 'px';
                menu.style.top = top + 'px';
            }

            function show(button) {
                const menu = document.getElementById(button.getAttribute('aria-controls'));
                if (!menu) return;

                hide();
                menu.classList.remove('hidden');
                place(button, menu);
                button.setAttribute('aria-expanded', 'true');

                const first = menu.querySelector('[role="menuitem"]');
                if (first) first.focus();

                open = { button: button, menu: menu };
            }

            return {
                toggle: function (button) {
                    if (open && open.button === button) hide();
                    else show(button);
                },
                hide: hide,
            };
        })();

        // One listener for every row on the page: the ⋯ opens its own menu, an item
        // that opens a sheet closes the menu behind it, and an item that submits a
        // form leaves it standing until the form has gone — so the button a click
        // was pressed on is not taken out from under it.
        document.addEventListener('click', function (e) {
            const button = e.target.closest('[data-actions-toggle]');

            if (button) {
                window.ISUActions.toggle(button);
                return;
            }

            if (e.target.closest('[data-actions-menu]')) {
                if (!e.target.closest('form')) window.ISUActions.hide();
                return;
            }

            window.ISUActions.hide();
        });

        document.addEventListener('submit', function (e) {
            if (e.target.closest('[data-actions-menu]')) window.ISUActions.hide();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') window.ISUActions.hide();
        });

        // A menu measured against the window cannot ride along with a page that is
        // moving under it, so the page moving closes it.
        window.addEventListener('resize', function () { window.ISUActions.hide(); });
        window.addEventListener('scroll', function () { window.ISUActions.hide(); }, true);
    </script>

    {{-- PWA: register the service worker and wire the "Install app" button. --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {});
            });
        }

        (function () {
            const installBtn = document.getElementById('pwa-install');
            if (!installBtn) return;

            const hintPanel = document.getElementById('pwa-install-hint');
            const hintText = document.getElementById('pwa-install-hint-text');

            // Already installed and running as the app: there is nothing left to install.
            const standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
                || window.navigator.standalone === true;
            if (standalone) {
                document.getElementById('pwa-install-wrap').remove();
                return;
            }

            function hideHint() {
                hintPanel.classList.add('hidden');
                installBtn.setAttribute('aria-expanded', 'false');
            }

            function showHint(message) {
                hintText.textContent = message;
                hintPanel.classList.remove('hidden');
                installBtn.setAttribute('aria-expanded', 'true');
            }

            /*
             * Why the browser offered nothing. The first case is the one a
             * server on the office network falls into: install needs a secure
             * connection (https://, or http://localhost), and over plain HTTP
             * on a hostname the browser will not register the service worker
             * or offer the app at all.
             */
            function noOfferReason() {
                if (!window.isSecureContext) {
                    return 'Your browser only offers to install a site over a secure connection — https://, or http://localhost on the machine running it. This page is on '
                        + location.origin + ', so there is nothing to install from here yet.';
                }

                return 'Your browser is not offering its one-click install right now. Its menu carries "Install app" (Chrome and Edge), "Add to Home screen" (Android), or "Add to Dock" (Safari). Reloading the page often brings the button back too.';
            }

            let deferredInstallPrompt = null;

            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                deferredInstallPrompt = e;
                hideHint();
            });

            installBtn.addEventListener('click', async function () {
                if (!deferredInstallPrompt) {
                    if (hintPanel.classList.contains('hidden')) showHint(noOfferReason());
                    else hideHint();
                    return;
                }

                hideHint();
                deferredInstallPrompt.prompt();
                await deferredInstallPrompt.userChoice;
                deferredInstallPrompt = null;
            });

            window.addEventListener('appinstalled', function () {
                document.getElementById('pwa-install-wrap').remove();
            });

            document.addEventListener('click', function (e) {
                if (!e.target.closest('#pwa-install-wrap')) hideHint();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') hideHint();
            });
        })();
    </script>
</body>
</html>
