{{--
    The two account sheets: editing an account, and writing it a new password.
    Both stand over the register instead of opening a page of their own, so the
    officer keeps their place in it — the register is read, worked on, and left
    where it was.

    Each sheet is pointed at the account whose row opened it: the row carries the
    account's values on its button, the button fills the sheet in, and the form's
    address for that account is read off the template the form carries. The
    add-user sheet lives with the register that offers it, since only that
    register adds accounts.

    Expects: $roles — the roles an account can be given.
--}}

<div id="edit-user-modal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUModal.close('edit-user-modal')"></div>
    <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-slate-800">Edit user</h3>
                <p class="mt-1 text-sm text-slate-500">Change the account's name or identity, or the role it works under. Its password is set from <span class="font-medium">Reset password</span>.</p>
            </div>
            <button type="button" onclick="ISUModal.close('edit-user-modal')"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <span class="sr-only">Close</span>
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>

        <form id="edit-user-form" method="POST"
              action="{{ route('admin.users.update', ['user' => '__USER__']) }}"
              data-action-template="{{ route('admin.users.update', ['user' => '__USER__']) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="_action" value="edit-user">
            <input type="hidden" name="_user" value="{{ old('_user') }}">
            <input type="hidden" name="_user_name" value="{{ old('_user_name') }}">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                        @foreach ($roles ?? [] as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->display_name }}</option>
                        @endforeach
                    </select>
                    @error('role_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="ISUModal.close('edit-user-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                    <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                    <x-icon name="check" class="h-4 w-4" /> Save changes
                </button>
            </div>
        </form>
    </div>
</div>

<div id="reset-password-modal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUModal.close('reset-password-modal')"></div>
    <div class="relative max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-slate-800">Reset password</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Setting a new password for
                    <span data-user-name class="font-medium text-slate-700">{{ old('_user_name') }}</span>.
                    The old password is not asked for — the office cannot know it — so the new one is typed twice.
                </p>
            </div>
            <button type="button" onclick="ISUModal.close('reset-password-modal')"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <span class="sr-only">Close</span>
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>

        <form id="reset-password-form" method="POST"
              action="{{ route('admin.users.reset-password', ['user' => '__USER__']) }}"
              data-action-template="{{ route('admin.users.reset-password', ['user' => '__USER__']) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_action" value="reset-password">
            <input type="hidden" name="_user" value="{{ old('_user') }}">
            <input type="hidden" name="_user_name" value="{{ old('_user_name') }}">

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

            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="ISUModal.close('reset-password-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                    <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                    <x-icon name="lock" class="h-4 w-4" /> Reset password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // The two account sheets, and the account each one is pointed at. A row's
    // button carries the account's own values, so the sheet is filled in from
    // the row rather than from a page of its own, and the form's address for
    // that account is read off the template it was given.
    (function () {
        function point(form, id) {
            form.action = form.dataset.actionTemplate.replace('__USER__', id);

            const account = form.querySelector('[name="_user"]');
            if (account) account.value = id;
        }

        function open(id, name) {
            const modal = document.getElementById(id);
            const named = modal ? modal.querySelector('[data-user-name]') : null;

            if (named) named.textContent = name || '';

            ISUModal.open(id);
        }

        window.ISUUser = {
            edit: function (button) {
                const form = document.getElementById('edit-user-form');

                point(form, button.dataset.id);
                form.querySelector('[name="name"]').value = button.dataset.name || '';
                form.querySelector('[name="employee_id"]').value = button.dataset.employeeId || '';
                form.querySelector('[name="email"]').value = button.dataset.email || '';
                form.querySelector('[name="role_id"]').value = button.dataset.roleId || '';
                form.querySelector('[name="_user_name"]').value = button.dataset.name || '';

                open('edit-user-modal', button.dataset.name);
            },

            resetPassword: function (button) {
                const form = document.getElementById('reset-password-form');

                point(form, button.dataset.id);
                form.querySelector('[name="_user_name"]').value = button.dataset.name || '';

                open('reset-password-modal', button.dataset.name);
            },

            // A sheet that came back with a mistake opens again where it was
            // made, named for the account it was written for, so the message is
            // read beside the field it belongs to.
            reopen: function (which, id, name) {
                const editing = which === 'edit';
                const form = document.getElementById(editing ? 'edit-user-form' : 'reset-password-form');

                point(form, id);

                open(editing ? 'edit-user-modal' : 'reset-password-modal', name);
            }
        };
    })();
</script>

{{-- The sheets are opened once the page has finished being read: the sheet
     opener is a later script than this one, so a call made here and now would
     reach for it before it exists. --}}
@if (old('_action') === 'edit-user')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            ISUUser.reopen('edit', @json(old('_user')), @json(old('_user_name')));
        });
    </script>
@endif

@if (old('_action') === 'reset-password')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            ISUUser.reopen('reset', @json(old('_user')), @json(old('_user_name')));
        });
    </script>
@endif
