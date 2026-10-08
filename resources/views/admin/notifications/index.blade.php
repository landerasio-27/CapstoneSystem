@extends('layouts.admin')

@section('title', 'Notifications')

@section('content')
    <div class="mb-4 flex justify-end">
        <form method="POST" action="{{ route('admin.notifications.read-all') }}">
            @csrf @method('PATCH')
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                Mark all as read
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <ul class="divide-y divide-slate-100">
            @forelse ($notifications as $notification)
                <li class="flex items-start justify-between gap-4 px-5 py-4 {{ $notification->read_at ? '' : 'bg-brand-50/40' }}">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ $notification->title }}</p>
                        <p class="text-sm text-slate-600">{{ $notification->body }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                    @unless ($notification->read_at)
                        <form method="POST" action="{{ route('admin.notifications.read', $notification) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="text-xs text-brand-600 hover:underline">Mark read</button>
                        </form>
                    @endunless
                </li>
            @empty
                <li class="px-5 py-8 text-center text-slate-400">No notifications yet.</li>
            @endforelse
        </ul>
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
