@extends('layouts.admin')

@section('title', 'Activity Logs')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="module" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All modules</option>
            @foreach ($modules as $module)
                <option value="{{ $module }}" @selected(request('module') === $module)>{{ ucfirst(str_replace('_', ' ', $module)) }}</option>
            @endforeach
        </select>
        <select name="action" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All actions</option>
            @foreach ($actions as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>{{ ucfirst($action) }}</option>
            @endforeach
        </select>
        <select name="user_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All users</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="search" class="h-4 w-4" /> Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Date</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">User</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Module</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Action</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Description</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-slate-500">{{ $log->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ ucfirst(str_replace('_', ' ', $log->module)) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ ucfirst($log->action) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $log->description }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ request()->hasAny(['module', 'action', 'user_id', 'date_from', 'date_to']) ? 'No activity matches these filters.' : 'No activity recorded yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
