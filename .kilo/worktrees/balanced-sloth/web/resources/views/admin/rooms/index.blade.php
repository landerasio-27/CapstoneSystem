@extends('layouts.admin')

@section('title', 'Rooms')

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-1 gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search room name, code or building..."
                   class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 sm:w-auto">
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="search" class="h-4 w-4" /> Filter</button>
        </form>

        <a href="{{ route('admin.rooms.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
            <x-icon name="plus" class="h-4 w-4" /> New Room
        </a>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Code</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Building / Floor</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Custodians</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Equipment</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rooms as $room)
                    {{-- The row itself opens the room, so the assignments can be
                         inspected without going through an Edit link, and no
                         column is spent on actions: the code and the name are
                         written as the plain text they read as, and a room is
                         edited or deleted from its own page. --}}
                    <tr data-href="{{ route('admin.rooms.show', $room) }}"
                        class="cursor-pointer transition hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">
                            <span class="text-slate-700">{{ $room->code }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-slate-600">{{ $room->name }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $room->building }} {{ $room->floor ? '· '.$room->floor : '' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $room->teachers_count }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $room->equipment_count }}</td>
                        <td class="px-4 py-3">
                            @if ($room->is_active)
                                <span class="rounded-full bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700">Active</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-500">Inactive</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No rooms found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rooms->links() }}</div>

    {{-- A click anywhere on a row opens that room. A reader dragging across a
         row to copy a code gets the text rather than a page change, and the
         row is the only thing on the line to click. --}}
    <script>
        document.querySelectorAll('[data-href]').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, select, label')) return;
                if (String(window.getSelection()).trim() !== '') return;

                window.location.href = row.dataset.href;
            });
        });
    </script>
@endsection
