{{--
    The four things the office does to an account, standing behind the ⋯ on the
    row it does them to: edit it, write it a new password, take its way in away —
    or give it back — and delete it. Both registers that list accounts, the
    Settings panel and the Users page, carry the same four, so an account is
    managed the same way wherever it is found.

    The four stand in a menu rather than spread across the row: four words on
    every line is a wall to read past, and the row is there to be read as an
    account first. Each one is marked with its own icon, so the eye can find the
    one it wants without reading all four.

    Editing and resetting open the sheets that stand over the register (see
    _modals), and the row hands each sheet the account it works on. Deactivating
    and deleting post straight from the menu; deleting asks first.

    The menu is opened, placed and closed by ISUActions (see layouts.admin), which
    finds its button by data-actions-toggle and the menu it belongs to by
    aria-controls.

    Expects: $account — the account the row stands for.
--}}
@php($menu = 'account-actions-'.$account->id)
<div class="flex items-center justify-end" data-actions>
    <button type="button" data-actions-toggle aria-haspopup="menu"
            aria-expanded="false" aria-controls="{{ $menu }}"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
        <span class="sr-only">Actions for {{ $account->name }}</span>
        <x-icon name="ellipsis-vertical" class="h-5 w-5" />
    </button>

    {{-- Drawn against the window rather than the row: the register is cut off at
         its own edges, and a menu anchored inside it would be clipped by the
         very table it opens over. Where it lands is worked out as it opens. --}}
    <div id="{{ $menu }}" data-actions-menu role="menu" aria-label="Actions for {{ $account->name }}"
         class="fixed z-30 hidden w-56 rounded-xl bg-white py-1 text-left shadow-lg ring-1 ring-slate-200">
        <button type="button" role="menuitem" onclick="ISUUser.edit(this)"
                data-id="{{ $account->id }}"
                data-name="{{ $account->name }}"
                data-employee-id="{{ $account->employee_id }}"
                data-email="{{ $account->email }}"
                data-role-id="{{ $account->role_id }}"
                class="flex w-full items-center gap-2.5 px-3 py-2 text-sm text-brand-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
            <x-icon name="pencil" class="h-4 w-4 shrink-0 text-slate-400" />
            Edit</button>

        <button type="button" role="menuitem" onclick="ISUUser.resetPassword(this)"
                data-id="{{ $account->id }}"
                data-name="{{ $account->name }}"
                class="flex w-full items-center gap-2.5 px-3 py-2 text-sm text-brand-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
            <x-icon name="lock" class="h-4 w-4 shrink-0 text-slate-400" />
            Reset password</button>

        {{-- One action either way, wearing the words and the icon of what it will
             do: an account that is standing is taken away, and one that was taken
             away is given back. --}}
        <form method="POST" action="{{ route('admin.users.toggle-active', $account) }}">
            @csrf
            @method('PATCH')
            <button type="submit" role="menuitem"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
                <x-icon name="{{ $account->is_active ? 'logout' : 'login' }}" class="h-4 w-4 shrink-0 text-slate-400" />
                {{ $account->is_active ? 'Deactivate' : 'Activate' }}</button>
        </form>

        <form method="POST" action="{{ route('admin.users.destroy', $account) }}"
              data-confirm="This will permanently delete this user account. An account that holds records on file is held back instead — deactivate it to keep the records and take away the way in."
              data-confirm-title="Delete user?">
            @csrf
            @method('DELETE')
            <button type="submit" role="menuitem"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 focus:bg-red-50 focus:outline-none">
                <x-icon name="trash" class="h-4 w-4 shrink-0" />
                Delete</button>
        </form>
    </div>
</div>
