@extends('layouts.admin')

@section('title', 'Rooms')

@section('content')
    @php
        $pill = 'inline-flex items-center rounded-full px-3.5 py-1.5 text-xs font-medium ring-1 transition';
        $pillOn = 'bg-brand-800 text-white ring-brand-800';
        $pillOff = 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50';

        // A pill keeps every other filter in the query string and only swaps its
        // own value, so narrowing by status never loses the search or the type.
        $statusUrl = fn (?string $value) => route('admin.rooms.index', array_merge(
            array_diff_key(request()->query(), ['status' => null, 'page' => null]),
            $value && $value !== 'all' ? ['status' => $value] : [],
        ));
        $groupUrl = fn (string $value) => route('admin.rooms.index', array_merge(
            array_diff_key(request()->query(), ['group' => null, 'page' => null]),
            $value !== 'all' ? ['group' => $value] : [],
        ));

        $totalItems = $rooms->sum(fn ($room) => $room->equipment_count + $room->supplies_count);
        $filtersOn = $search !== '' || $status !== 'all' || $group !== 'all' || $type !== '';
    @endphp

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Rooms</h2>
            <p class="text-sm text-slate-500">
                Every room as a folder: what it is, and what is physically inside it &mdash; the equipment and supplies that name it as their location.
            </p>
        </div>

        <a href="{{ route('admin.rooms.create') }}"
           class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
            <x-icon name="plus" class="h-4 w-4" /> New Room
        </a>
    </div>

    {{-- Status and kind are chosen with the pills; the form keeps them in hidden
         fields so a search or a sort never drops them. --}}
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $statusUrl('all') }}" class="{{ $pill }} {{ $status === 'all' ? $pillOn : $pillOff }}">All</a>
            <a href="{{ $statusUrl('active') }}" class="{{ $pill }} {{ $status === 'active' ? $pillOn : $pillOff }}">Active</a>
            <a href="{{ $statusUrl('inactive') }}" class="{{ $pill }} {{ $status === 'inactive' ? $pillOn : $pillOff }}">Inactive</a>

            <span class="mx-1 h-5 w-px bg-slate-200"></span>

            <a href="{{ $groupUrl('all') }}" class="{{ $pill }} {{ $group === 'all' ? $pillOn : $pillOff }}">All kinds</a>
            @foreach (['offices' => 'Offices', 'classrooms' => 'Classrooms', 'laboratories' => 'Laboratories', 'storage' => 'Storage Rooms'] as $value => $label)
                <a href="{{ $groupUrl($value) }}" class="{{ $pill }} {{ $group === $value ? $pillOn : $pillOff }}">{{ $label }}</a>
            @endforeach
        </div>

        {{-- Search and sorting apply as they change (see resources/js/filter-bar.js);
             the button is there for the reader who presses Enter instead. The
             filters read from the left and the search sits at the right end of
             the same line: one row on any desktop window. --}}
        <form method="GET" data-auto-filter class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="group" value="{{ $group }}">

            <select name="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                <option value="">All room types</option>
                @foreach (\App\Models\Room::TYPES as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <label class="flex items-center gap-2 text-xs font-medium text-slate-500">
                Sort
                <select name="sort" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-700">
                    <option value="name_asc" @selected($sort === 'name_asc')>Room Name A&ndash;Z</option>
                    <option value="name_desc" @selected($sort === 'name_desc')>Room Name Z&ndash;A</option>
                    <option value="equipment" @selected($sort === 'equipment')>Most Equipment</option>
                    <option value="supplies" @selected($sort === 'supplies')>Most Supplies</option>
                    <option value="recent" @selected($sort === 'recent')>Recently Updated</option>
                </select>
            </label>

            {{-- The search takes the right end of that line: the margin holds it
                 away from the filters, and on a phone it drops to its own row. --}}
            <div class="ml-auto flex w-full gap-2 sm:w-auto">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search rooms..."
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 sm:w-72">
                <button type="submit" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                    <x-icon name="search" class="h-4 w-4" /> Search
                </button>
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-2">
            @if ($filtersOn)
                <a href="{{ route('admin.rooms.index') }}" class="text-xs text-slate-500 hover:text-slate-700">Clear filters</a>
            @endif

            <span class="ml-auto text-xs text-slate-400">
                {{ $rooms->total() }} {{ $rooms->total() === 1 ? 'room' : 'rooms' }}
                &middot; {{ number_format($totalItems) }} {{ $totalItems === 1 ? 'item' : 'items' }} on this page
            </span>
        </div>
    </div>

    @if ($rooms->isEmpty())
        <div class="mt-4 rounded-xl bg-white p-10 text-center ring-1 ring-slate-200">
            <x-folder-icon class="mx-auto h-14 w-16" />
            <p class="mt-4 font-medium text-slate-700">
                {{ $filtersOn ? 'No rooms match these filters.' : 'No rooms yet' }}
            </p>
            <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                {{ $filtersOn
                    ? 'Try another name, building, department or kind of room — or clear the filters.'
                    : 'A room is created here and then filled from its own page: the equipment and supplies placed in it gather themselves into the folder.' }}
            </p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                @if ($filtersOn)
                    <a href="{{ route('admin.rooms.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                        Clear filters
                    </a>
                @endif
                <a href="{{ route('admin.rooms.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                    <x-icon name="plus" class="h-4 w-4" /> New Room
                </a>
            </div>
        </div>
    @else
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($rooms as $room)
                @php
                    $items = $room->equipment_count + $room->supplies_count;
                @endphp                    {{-- A room as a folder: the folder itself, then what is inside it
                     in numbers, then the way in. The whole card opens the room —
                     a click that lands on a link or a control is left alone, and
                     a reader dragging across the name gets the text rather than
                     a page change. --}}
                <div data-href="{{ route('admin.rooms.show', $room) }}"
                     class="group flex cursor-pointer flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-brand-200">
                    <div class="flex items-start gap-3">
                        <x-folder-icon class="mt-0.5 h-10 w-11 shrink-0" />

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate font-semibold text-slate-800 transition group-hover:text-brand-800" title="{{ $room->name }}">
                                    {{ $room->name }}
                                </p>

                                @if ($room->is_active)
                                    <span class="shrink-0 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700">Active</span>
                                @else
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">Inactive</span>
                                @endif
                            </div>

                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $room->department ?: 'No department on record' }}</p>

                            <p class="mt-1 text-[11px] text-slate-400">
                                {{ $room->typeLabel() }}@if ($room->building) &middot; {{ $room->building }}@endif{{ $room->floor ? ' · '.$room->floor : '' }}
                            </p>
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-3 text-center">
                        <div>
                            <dt class="flex items-center justify-center gap-1 text-[11px] font-medium text-slate-500"><x-icon name="box" class="h-3.5 w-3.5" /> Equipment</dt>
                            <dd class="mt-0.5 text-base font-semibold text-slate-800">{{ $room->equipment_count }}</dd>
                        </div>
                        <div>
                            <dt class="flex items-center justify-center gap-1 text-[11px] font-medium text-slate-500"><x-icon name="archive" class="h-3.5 w-3.5" /> Supplies</dt>
                            <dd class="mt-0.5 text-base font-semibold text-slate-800">{{ $room->supplies_count }}</dd>
                        </div>
                        <div>
                            <dt class="flex items-center justify-center gap-1 text-[11px] font-medium text-slate-500"><x-icon name="clipboard" class="h-3.5 w-3.5" /> Total</dt>
                            <dd class="mt-0.5 text-base font-semibold text-slate-800">{{ $items }}</dd>
                        </div>
                    </dl>

                    <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                        <span class="text-[11px] text-slate-400">{{ $room->teachers_count }} {{ $room->teachers_count === 1 ? 'custodian' : 'custodians' }}</span>
                        <span class="inline-flex items-center gap-1 text-sm font-medium text-brand-800 transition group-hover:gap-1.5">
                            Open Room <x-icon name="arrow-right" class="h-4 w-4" />
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $rooms->links() }}</div>
    @endif

    {{-- A click anywhere on a folder card opens it. A click that lands on a link
         or a control inside the card is left alone: the card carries its own
         destination and nothing in it is a link of its own. --}}
    <script>
        document.querySelectorAll('[data-href]').forEach(function (card) {
            card.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, select, label')) return;
                if (String(window.getSelection()).trim() !== '') return;

                window.location.href = card.dataset.href;
            });
        });
    </script>
@endsection
