@extends('layouts.admin')

@section('title', $room->name)

@section('content')
    {{-- "Back to Rooms" comes from the layout's contextual link, so it is not repeated here. --}}
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg font-semibold text-slate-800">{{ $room->name }}</h2>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $room->code }}</span>
                    @if ($room->is_active)
                        <span class="rounded-full bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700">Active</span>
                    @else
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-500">Inactive</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $room->building ?: '—' }}{{ $room->floor ? ' · '.$room->floor : '' }}
                </p>
            </div>

            {{-- The room is edited here, on its own page: the list is read row by
                 row and carries no actions of its own, and deleting a room holds
                 nothing the office does from this page — the register keeps the
                 rooms its assignments are read against, so nothing is offered to
                 remove one. --}}
            <a href="{{ route('admin.rooms.edit', $room) }}"
               class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                <x-icon name="pencil" class="h-4 w-4" /> Edit Room
            </a>
        </div>

        {{-- Counted from the assignments themselves, never stored on the room. --}}
        <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-slate-100 pt-4 text-sm">
            <span class="text-slate-500">Custodians <span class="ml-1 font-semibold text-slate-800">{{ $room->teachers_count }}</span></span>
            <span class="text-slate-400">|</span>
            <span class="text-slate-500">Equipment <span class="ml-1 font-semibold text-slate-800">{{ $room->equipment_count }}</span></span>
            <span class="text-slate-400">|</span>
            <span class="text-slate-500">Supplies <span class="ml-1 font-semibold text-slate-800">{{ $room->supplies_count }}</span></span>
        </div>

        <h3 class="mt-6 text-sm font-semibold text-slate-800">Room Information</h3>
        <dl class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Code</dt><dd class="font-medium text-slate-800">{{ $room->code }}</dd></div>
            <div><dt class="text-slate-500">Room Name</dt><dd class="font-medium text-slate-800">{{ $room->name }}</dd></div>
            <div><dt class="text-slate-500">Building</dt><dd class="font-medium text-slate-800">{{ $room->building ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Floor</dt><dd class="font-medium text-slate-800">{{ $room->floor ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Type</dt><dd class="font-medium text-slate-800">{{ $room->type ? ucfirst($room->type) : '—' }}</dd></div>
            <div><dt class="text-slate-500">Status</dt><dd class="font-medium text-slate-800">{{ $room->is_active ? 'Active' : 'Inactive' }}</dd></div>
        </dl>

        {{-- Assignments are changed in the module that owns them, not from here. --}}
        <p class="mt-4 text-xs text-slate-400">
            Custodians, equipment and supplies are moved between rooms from their own modules — this page only reads them.
        </p>
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-4 text-base font-semibold text-slate-800">Custodians Assigned ({{ $teachers->count() }})</h2>

        @if ($teachers->isEmpty())
            <p class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-400">
                No custodians are currently assigned to this room.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Name</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Employee ID</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Department</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($teachers as $teacher)
                            {{-- The row opens the custodian, as it does in the
                                 register itself, so no column is spent on a View
                                 link and the name reads as plain text. --}}
                            <tr data-href="{{ route('admin.teachers.show', $teacher) }}"
                                class="cursor-pointer transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-2 py-2 font-medium">
                                    <span class="text-slate-700">{{ $teacher->full_name }}</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $teacher->employee_id }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $teacher->department ?? '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2">
                                    @if ($teacher->is_active)
                                        <span class="rounded-full bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700">Active</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-500">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-4 text-base font-semibold text-slate-800">Equipment Assigned ({{ $equipment->count() }})</h2>

        @if ($equipment->isEmpty())
            <p class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-400">
                No equipment is currently assigned to this room.
            </p>
        @else
            @php
                $badges = [
                    'available' => 'bg-brand-50 text-brand-700',
                    'issued' => 'bg-sky-50 text-sky-700',
                    'damaged' => 'bg-orange-50 text-orange-700',
                    'missing' => 'bg-red-50 text-red-700',
                    'misplaced' => 'bg-amber-50 text-amber-700',
                ];
            @endphp

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Equipment</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Item Code</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Property Number</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Custodian</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Qty</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($equipment as $item)
                            {{-- Read row by row: the row opens the equipment record,
                                 and the name and code are written as the plain
                                 text they read as. --}}
                            <tr data-href="{{ route('admin.equipment.show', $item) }}"
                                class="cursor-pointer transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-2 py-2 font-medium">
                                    <span class="text-slate-700">{{ $item->name }}</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $item->item_code }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $item->property_number ?? '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $item->custodian_name ?: '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $item->quantity_on_hand }} {{ $item->unit }}</td>
                                <td class="whitespace-nowrap px-2 py-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $badges[$item->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($item->status) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-4 text-base font-semibold text-slate-800">Supplies / Items in Room ({{ $supplies->count() }})</h2>

        @if ($supplies->isEmpty())
            <p class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-400">
                No supplies are currently stored in this room.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Item</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Code</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Category</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Quantity</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Unit</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($supplies as $supply)
                            {{-- Read row by row: the row opens the supply, and the
                                 name and code are written as the plain text they
                                 read as. --}}
                            <tr data-href="{{ route('admin.supplies.show', $supply) }}"
                                class="cursor-pointer transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-2 py-2 font-medium">
                                    <span class="text-slate-700">{{ $supply->name }}</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $supply->item_code }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $supply->category ?? '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $supply->quantity_on_hand }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $supply->unit }}</td>
                                <td class="whitespace-nowrap px-2 py-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $supply->is_active ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $supply->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    @if ($supply->isLowStock())
                                        <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Low stock</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- A click anywhere on a row opens the record behind it: the custodian, the
         equipment or the supply. A reader dragging across a row to copy a code
         gets the text rather than a page change. --}}
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
