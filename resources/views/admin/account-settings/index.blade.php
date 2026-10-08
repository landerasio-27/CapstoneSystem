@extends('layouts.admin')

@section('title', 'Account Settings')

@section('content')
    @php
        // One settings row, rendered the same way the main sidebar renders a
        // link: icon + label, brand tint when it is the open section.
        $settingsLink = 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition';
        $settingsIcon = 'h-5 w-5 shrink-0';
        $activeClass = 'bg-brand-50 font-semibold text-brand-800';
        $idleClass = 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
    @endphp

    <div class="mx-auto w-full max-w-7xl">
        {{-- The settings column is not pulled up to the top of the row
             (`items-start`): it stands the full height of the section beside
             it, which is what lets the list inside it stay put as the section
             is read. A column only as tall as its own list would carry the
             list off the page as soon as it scrolled past. --}}
        <div class="lg:grid lg:grid-cols-[248px_minmax(0,1fr)] lg:items-stretch lg:gap-8">

            {{-- Settings sub-navigation: every row stays on this page and swaps
                 the section beside it, so no other page is opened. --}}
            <aside class="mb-6 lg:mb-0">
                {{-- The list is sticky, so it stays in view while a long section
                     (the archive register, the activity logs) is scrolled. It is
                     a block in the narrow layout, where it has the width of the
                     page and nothing to stand beside. --}}
                <nav class="rounded-xl border border-slate-200 bg-white p-2 shadow-sm ring-1 ring-slate-200 lg:sticky lg:top-20"
                     aria-label="Account settings sections">
                    <p class="px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Settings</p>
                    <div class="space-y-0.5">
                        @foreach ($sections as $key => $item)
                            @php $isActive = $key === $section; @endphp
                            <a href="{{ route('admin.account-settings.index', ['section' => $key]) }}"
                               @if ($isActive) aria-current="page" @endif
                               class="{{ $settingsLink }} {{ $isActive ? $activeClass : $idleClass }}">
                                <x-icon :name="$item['icon']"
                                        class="{{ $settingsIcon }} {{ $isActive ? 'text-brand-700' : 'text-slate-400 group-hover:text-slate-600' }}" />
                                <span class="flex-1 truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </nav>
            </aside>

            <div class="space-y-6">

                @if ($section === 'profile')
                    {{-- Profile Information --}}
                    <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-5">
                            <h2 class="text-base font-semibold text-slate-800">Profile Information</h2>
                            <p class="mt-1 text-sm text-slate-500">Update your account's name, position and email address.</p>
                        </div>

                        <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Full Name</label>
                                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- The office this account signs as. It is asked for
                                 rather than offered: the position prints under
                                 the name on every RIS, monthly report and
                                 custodian slip this office signs, so an account
                                 without one signs papers it cannot be placed
                                 on. --}}
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Position</label>
                                <input type="text" name="position" value="{{ old('position', $user->position) }}" required
                                       placeholder="e.g. Administrative Officer I (Supply Officer I)"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                <p class="mt-1 text-xs text-slate-400">Prints under your name on the reports this office signs.</p>
                                @error('position') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Email Address</label>
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Save Changes</button>
                            </div>
                        </form>
                    </section>
                @endif

                @if ($section === 'password')
                    {{-- Change Password --}}
                    <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-5">
                            <h2 class="text-base font-semibold text-slate-800">Change Password</h2>
                            <p class="mt-1 text-sm text-slate-500">Use a strong password of at least 8 characters that you don't use elsewhere.</p>
                        </div>

                        <form method="POST" action="{{ route('admin.profile.password') }}" class="space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Current Password</label>
                                <input type="password" name="current_password" required autocomplete="current-password"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">New Password</label>
                                    <input type="password" name="password" required autocomplete="new-password"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Confirm New Password</label>
                                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Update Password</button>
                            </div>
                        </form>
                    </section>
                @endif

                @if ($section === 'users')
                    {{-- Users: every account on one table. Adding one opens a sheet
                         over the page instead of unfolding a form above the
                         table, so the register never moves under the reader. --}}
                    <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-slate-800">Users</h2>
                                <p class="mt-1 text-sm text-slate-500">Manage accounts, roles and active status.</p>
                            </div>

                            <button type="button" onclick="ISUModal.open('add-user-modal')"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                                <x-icon name="plus" class="h-4 w-4" /> Add user
                            </button>
                        </div>

                        {{-- The add-user sheet, opened by the button above. --}}
                        <div id="add-user-modal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center p-4">
                            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUModal.close('add-user-modal')"></div>
                            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                                <div class="mb-4 flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-base font-semibold text-slate-800">Add user</h3>
                                        <p class="mt-1 text-sm text-slate-500">Create an account and choose the role it works under.</p>
                                    </div>
                                    <button type="button" onclick="ISUModal.close('add-user-modal')"
                                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                                        <span class="sr-only">Close</span>
                                        <x-icon name="x-mark" class="h-4 w-4" />
                                    </button>
                                </div>

                            <form method="POST" action="{{ route('admin.account-settings.users.store') }}">
                                @csrf
                                {{-- Which sheet this form is: the page reopens the
                                     one that came back with a mistake, and the
                                     three account sheets share their fields. --}}
                                <input type="hidden" name="_action" value="add-user">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Full Name</label>
                                        <input type="text" name="name" value="{{ old('name') }}" required
                                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Employee ID</label>
                                        <input type="text" name="employee_id" value="{{ old('employee_id') }}"
                                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        @error('employee_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Email Address</label>
                                        <input type="email" name="email" value="{{ old('email') }}" required
                                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Role</label>
                                        <select name="role_id" required
                                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                            <option value="">Choose a role…</option>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->display_name }}</option>
                                            @endforeach
                                        </select>
                                        @error('role_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                                        <input type="password" name="password" required autocomplete="new-password"
                                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Confirm Password</label>
                                        <input type="password" name="password_confirmation" required autocomplete="new-password"
                                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                    </div>
                                </div>
                                <div class="mt-5 flex justify-end gap-3">
                                    <button type="button" onclick="ISUModal.close('add-user-modal')"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                                        <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                                    </button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Create User</button>
                                </div>
                            </form>
                            </div>
                        </div>

                        {{-- A form that came back with a mistake reopens the sheet,
                             so the error is read where it was made. The opener is
                             a later script than this one, so the sheet is asked
                             for once the page has finished being read. --}}
                        @if (old('_action') === 'add-user')
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    ISUModal.open('add-user-modal');
                                });
                            </script>
                        @endif

                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-slate-500">Name</th>
                                            <th class="px-4 py-3 text-left font-medium text-slate-500">Employee ID</th>
                                            <th class="px-4 py-3 text-left font-medium text-slate-500">Email</th>
                                            <th class="px-4 py-3 text-left font-medium text-slate-500">Role</th>
                                            <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                                            <th class="px-4 py-3 text-right font-medium text-slate-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($users as $account)
                                            <tr>
                                                <td class="px-4 py-3 font-medium text-slate-800">{{ $account->name }}</td>
                                                <td class="px-4 py-3 text-slate-500">{{ $account->employee_id ?? '—' }}</td>
                                                <td class="px-4 py-3 text-slate-500">{{ $account->email }}</td>
                                                <td class="px-4 py-3 text-slate-500">{{ $account->role?->display_name ?? '—' }}</td>
                                                <td class="px-4 py-3">
                                                    @if ($account->is_active)
                                                        <span class="rounded-full bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700">Active</span>
                                                    @else
                                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-500">Inactive</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    {{-- The four things the office does to
                                                         an account, on its own row: the
                                                         same four the Users page carries. --}}
                                                    @include('admin.users._actions', ['account' => $account])
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No users found.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mt-4">{{ $users->links() }}</div>

                        {{-- The edit and reset-password sheets, standing over the
                             register the rows above work on. --}}
                        @include('admin.users._modals', ['roles' => $roles])
                    </section>
                @endif

                @if ($section === 'archive')
                    {{-- Archive: the year form, then the register itself — the
                         same one the Archive page shows, and the first thing
                         the panel opens on. A year opened from the shelves
                         below is shown in place of the register, still inside
                         the panel: filing, opening a year and restoring never
                         leave Settings. --}}
                    <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-slate-800">Archive</h2>
                            <p class="mt-1 text-sm text-slate-500">File and restore PAR / ICS / RIS documents and Stock / Property Cards under archive years.</p>
                        </div>

                        <form method="POST" action="{{ route('admin.account-settings.archive.store') }}" class="mb-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label for="archive-year" class="mb-1 block text-sm font-medium text-slate-700">Archive year</label>
                                <input id="archive-year" type="number" name="year" min="1900" max="2100" value="{{ old('year') }}" required placeholder="{{ date('Y') }}"
                                       class="w-36 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                @error('year') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="folder" class="h-4 w-4" /> Add Year</button>
                        </form>

                    </section>

                    {{-- The shelves the records are filed under stand above the
                         register, as they do on the Archive page itself: the
                         year to file into is chosen before the records are
                         ticked, and the panel that shows which year is open is
                         then read on the way past rather than hunted for at the
                         foot of the page. --}}
                    <section class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <h3 class="text-sm font-semibold text-slate-700">Archive years</h3>
                        @if ($archives->isEmpty())
                            <p class="mt-1 text-sm text-slate-400">No archive years yet — add one above to start filing records.</p>
                        @else
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($archives as $yearRow)
                                    @php $isOpenYear = $openYear && $openYear->id === $yearRow->id; @endphp
                                    <a href="{{ route('admin.account-settings.index', ['section' => 'archive', 'archive' => $yearRow->id]) }}"
                                       @if ($isOpenYear) aria-current="true" @endif
                                       class="group block rounded-xl border p-4 transition hover:border-brand-300 hover:bg-brand-50/40 {{ $isOpenYear ? 'border-brand-300 bg-brand-50/40' : 'border-slate-200' }}">
                                        <p class="flex items-center gap-2 text-base font-semibold text-slate-800 group-hover:text-brand-700">
                                            <x-icon name="folder" class="h-4 w-4 text-slate-400 group-hover:text-brand-600" />
                                            {{ $yearRow->year }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $yearRow->documents_count }} document(s) &middot; {{ $yearRow->cards_count }} card(s)
                                        </p>
                                        @if ($yearRow->notes)
                                            <p class="mt-1 truncate text-xs text-slate-400">{{ $yearRow->notes }}</p>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    @if ($openYear)
                        @include('admin.archive._year', [
                            'archive' => $openYear,
                            'documents' => $yearDocuments,
                            'cards' => $yearCards,
                            'cardType' => $cardType,
                            'backUrl' => route('admin.account-settings.index', ['section' => 'archive']),
                            'cardTypeUrl' => fn (?string $type) => route('admin.account-settings.index', array_filter([
                                'section' => 'archive',
                                'archive' => $openYear->id,
                                'card_type' => $type,
                            ])),
                        ])
                    @else
                        @include('admin.archive._register', [
                            'archives' => $archives,
                            'documents' => $documents,
                            'cards' => $cards,
                            'cardType' => $cardType,
                            // The register's own filters post back here, so the
                            // panel has to stay the one that is open.
                            'keep' => ['section' => 'archive'],
                        ])
                    @endif
                @endif

                @if ($section === 'activity-logs')
                    {{-- Activity Logs: the administrator's audit trail. It is not
                         part of a supply staff account's settings, whose own
                         actions are what the trail is recording. --}}
                    <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-slate-800">Activity Logs</h2>
                            <p class="mt-1 text-sm text-slate-500">Audit trail of account actions across the portal.</p>
                        </div>

                        {{-- The panel's own filters, the same five the full
                             activity log page offers. The section rides along
                             in the query so the panel is still the one open
                             when the sheet comes back. --}}
                        <form method="GET" class="mb-4 flex flex-wrap gap-2">
                            <input type="hidden" name="section" value="activity-logs">
                            <select name="module" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">All modules</option>
                                @foreach ($activityModules as $module)
                                    <option value="{{ $module }}" @selected(request('module') === $module)>{{ ucfirst(str_replace('_', ' ', $module)) }}</option>
                                @endforeach
                            </select>
                            <select name="action" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">All actions</option>
                                @foreach ($activityActions as $action)
                                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ ucfirst($action) }}</option>
                                @endforeach
                            </select>
                            <select name="user_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">All users</option>
                                @foreach ($activityUsers as $activityUser)
                                    <option value="{{ $activityUser->id }}" @selected(request('user_id') == $activityUser->id)>{{ $activityUser->name }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="search" class="h-4 w-4" /> Filter</button>
                        </form>

                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-slate-500">Date</th>
                                            <th class="px-4 py-3 text-left font-medium text-slate-500">User</th>
                                            <th class="px-4 py-3 text-left font-medium text-slate-500">Module</th>
                                            <th class="px-4 py-3 text-left font-medium text-slate-500">Action</th>
                                            <th class="px-4 py-3 text-left font-medium text-slate-500">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($recentActivity as $log)
                                            <tr>
                                                <td class="px-4 py-3 text-slate-500">{{ $log->created_at->format('M d, Y H:i') }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $log->user?->name ?? 'System' }}</td>
                                                <td class="px-4 py-3 text-slate-500">{{ ucfirst(str_replace('_', ' ', $log->module)) }}</td>
                                                <td class="px-4 py-3 text-slate-500">{{ ucfirst($log->action) }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $log->description }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ request()->hasAny(['module', 'action', 'user_id', 'date_from', 'date_to']) ? 'No activity matches these filters.' : 'No activity recorded yet.' }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                @endif

            </div>
        </div>
    </div>
@endsection
