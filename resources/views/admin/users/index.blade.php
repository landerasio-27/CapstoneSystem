@extends('layouts.admin')

@section('title', 'Users')

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-1 gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, employee ID..."
                   class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 sm:w-auto">
            <select name="role" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->display_name }}</option>
                @endforeach
            </select>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="search" class="h-4 w-4" /> Filter</button>
        </form>

        <a href="{{ route('admin.users.create') }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
            <x-icon name="plus" class="h-4 w-4" /> New User
        </a>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
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
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->employee_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->role?->display_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($user->is_active)
                                <span class="rounded-full bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700">Active</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-500">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{-- The same four the Settings panel carries: an account
                                 is edited and reset in a sheet over the register,
                                 and deactivated or deleted from its row. --}}
                            @include('admin.users._actions', ['account' => $user])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>

    {{-- The edit and reset-password sheets, standing over the register. --}}
    @include('admin.users._modals', ['roles' => $roles])
@endsection
