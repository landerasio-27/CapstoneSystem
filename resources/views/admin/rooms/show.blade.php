@extends('layouts.admin')

@section('title', $room->name)

@section('content')
    @php
        // The tabs keep the reader's place: the same room, the same search, and
        // the part of the inventory they asked for.
        $tabUrl = fn (string $value) => route('admin.rooms.show', array_filter([
            'room' => $room->id,
            'q' => $search !== '' ? $search : null,
            'tab' => $value,
        ]));

        $pill = 'inline-flex items-center rounded-full px-3.5 py-1.5 text-xs font-medium ring-1 transition';
        $pillOn = 'bg-brand-800 text-white ring-brand-800';
        $pillOff = 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50';

        $statusBadges = [
            'available' => 'bg-brand-50 text-brand-700',
            'issued' => 'bg-sky-50 text-sky-700',
            'damaged' => 'bg-orange-50 text-orange-700',
            'missing' => 'bg-red-50 text-red-700',
            'misplaced' => 'bg-amber-50 text-amber-700',
        ];

        $itemCount = $room->equipment_count + $room->supplies_count;
        $nothingAtAll = $itemCount === 0;
    @endphp

    {{-- "Back to Rooms" comes from the layout's contextual link, so it is not repeated here. --}}
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 items-start gap-4">
                <x-folder-icon class="h-14 w-16 shrink-0" />

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold text-slate-800">{{ $room->name }}</h2>

                        @if ($room->is_active)
                            <span class="rounded-full bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700">Active</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-500">Inactive</span>
                        @endif

                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $room->typeLabel() }}</span>
                    </div>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ $room->department ?: 'No department on record' }}
                        @if ($room->building) &middot; {{ $room->building }}@endif
                        @if ($room->floor) &middot; {{ $room->floor }}@endif
                    </p>
                </div>
            </div>

            {{-- The three things the office does from a room: fill it, move
                 something out of it, correct the room itself. --}}
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="ISUModal.open('add-item-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                    <x-icon name="plus" class="h-4 w-4" /> Add Item
                </button>

                <button type="button" onclick="ISUModal.open('transfer-item-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    <x-icon name="swap" class="h-4 w-4" /> Transfer Item
                </button>

                <a href="{{ route('admin.rooms.edit', $room) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    <x-icon name="pencil" class="h-4 w-4" /> Edit Room
                </a>
            </div>
        </div>

        <h3 class="mt-6 text-sm font-semibold text-slate-800">Room Information</h3>
        <dl class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-slate-500">Room Name</dt><dd class="font-medium text-slate-800">{{ $room->name }}</dd></div>
            <div><dt class="text-slate-500">Building</dt><dd class="font-medium text-slate-800">{{ $room->building ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Floor</dt><dd class="font-medium text-slate-800">{{ $room->floor ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Department / College</dt><dd class="font-medium text-slate-800">{{ $room->department ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Room Type</dt><dd class="font-medium text-slate-800">{{ $room->typeLabel() }}</dd></div>
            <div><dt class="text-slate-500">Status</dt><dd class="font-medium text-slate-800">{{ $room->is_active ? 'Active' : 'Inactive' }}</dd></div>
            <div><dt class="text-slate-500">Custodians in Room</dt><dd class="font-medium text-slate-800">{{ $room->teachers_count }}</dd></div>
            @if ($room->description)
                <div class="sm:col-span-2 lg:col-span-4">
                    <dt class="text-slate-500">Description</dt>
                    <dd class="text-slate-700">{{ $room->description }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- What is inside, read from the items themselves: equipment in the room
         counted by the paper it is recorded on, supplies counted as RIS. A
         supply is a RIS at any price — only property separates into PAR and ICS
         by the unit cost. --}}
    @php
        $summaryCards = [
            [
                'label' => 'Equipment',
                'count' => $room->equipment_count,
                'sub' => 'Non-consumable property in this room',
                'icon' => 'box',
                'href' => $tabUrl('equipment'),
            ],
            [
                'label' => 'Supplies',
                'count' => $room->supplies_count,
                'sub' => 'Consumables stored in this room',
                'icon' => 'archive',
                'href' => $tabUrl('supplies'),
            ],
            [
                'label' => 'PAR',
                'count' => $classificationCounts['PAR'] ?? 0,
                'sub' => 'Property above ₱' . number_format(\App\Services\ItemClassifier::PROPERTY_THRESHOLD, 2),
                'icon' => 'file',
                'href' => $tabUrl('equipment'),
            ],
            [
                'label' => 'ICS',
                'count' => $classificationCounts['ICS'] ?? 0,
                'sub' => 'Property at or below ₱' . number_format(\App\Services\ItemClassifier::PROPERTY_THRESHOLD, 2),
                'icon' => 'file',
                'href' => $tabUrl('equipment'),
            ],
            [
                'label' => 'RIS',
                'count' => $classificationCounts['RIS'] ?? 0,
                'sub' => 'Consumable supplies, at any price',
                'icon' => 'file',
                'href' => $tabUrl('supplies'),
            ],
        ];
    @endphp

    <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach ($summaryCards as $card)
            <a href="{{ $card['href'] }}"
               class="{{ $pillOff }} flex flex-col gap-1 rounded-xl p-4 shadow-sm transition hover:bg-brand-50">
                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500">
                    <x-icon :name="$card['icon']" class="h-4 w-4" /> {{ $card['label'] }}
                </span>
                <span class="text-2xl font-semibold text-slate-800">{{ $card['count'] }}</span>
                <span class="text-[11px] leading-tight text-slate-400">{{ $card['sub'] }}</span>
            </a>
        @endforeach
    </div>

    @if ($nothingAtAll)
        <div class="mt-4 rounded-xl bg-white p-10 text-center ring-1 ring-slate-200">
            <x-folder-icon class="mx-auto h-14 w-16" />
            <p class="mt-4 font-medium text-slate-700">This room is currently empty</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                No equipment or supplies have been assigned to this room. Placing an item here does not create a second record &mdash; the item itself is updated to name this room as its location.
            </p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                <button type="button" onclick="ISUModal.open('add-item-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                    <x-icon name="plus" class="h-4 w-4" /> Add Item
                </button>
            </div>
        </div>
    @else
        <div class="mt-6 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $tabUrl('all') }}" class="{{ $pill }} {{ $tab === 'all' ? $pillOn : $pillOff }}">
                    All <span class="ml-1 opacity-70">{{ $room->equipment_count + $room->supplies_count }}</span>
                </a>
                <a href="{{ $tabUrl('equipment') }}" class="{{ $pill }} {{ $tab === 'equipment' ? $pillOn : $pillOff }}">
                    Equipment <span class="ml-1 opacity-70">{{ $room->equipment_count }}</span>
                </a>
                <a href="{{ $tabUrl('supplies') }}" class="{{ $pill }} {{ $tab === 'supplies' ? $pillOn : $pillOff }}">
                    Supplies <span class="ml-1 opacity-70">{{ $room->supplies_count }}</span>
                </a>
            </div>

            {{-- Searches the room's own inventory: the equipment name and codes,
                 the supply name and code, the custodian holding it, and the
                 number of the paper it is printed on. --}}
            <form method="GET" data-auto-filter class="flex gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search inventory..."
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 xl:w-72">
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                    <x-icon name="search" class="h-4 w-4" />
                </button>
                @if ($search !== '')
                    <a href="{{ route('admin.rooms.show', ['room' => $room, 'tab' => $tab]) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm text-slate-500 hover:text-slate-700">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        @if ($search !== '' && $equipment->isEmpty() && $supplies->isEmpty())
            <p class="mt-4 rounded-xl bg-white px-4 py-10 text-center text-sm text-slate-500 ring-1 ring-slate-200">
                Nothing in this room matches &ldquo;{{ $search }}&rdquo;. Try an item name, a code, a custodian or a document number &mdash; or clear the search.
            </p>
        @endif

        {{-- Equipment & Property --}}
        @if ($tab !== 'supplies' && $equipment->isNotEmpty())
            <div class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-slate-800">Equipment &amp; Property ({{ $equipment->count() }})</h3>
                    <p class="text-xs text-slate-400">Non-consumable property recorded in this room</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Equipment</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Property Code</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Classification</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Category</th>
                                <th class="whitespace-nowrap px-2 py-2 text-right font-medium text-slate-500">Unit Cost</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Assigned To</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Status</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Date Placed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($equipment as $item)
                                @php
                                    $placed = $placedAt->get(($item instanceof \App\Models\Equipment ? 'equipment:' : 'supply:') . $item->id);
                                @endphp
                                <tr data-href="{{ route($item instanceof \App\Models\Equipment ? 'admin.equipment.show' : 'admin.supplies.show', $item) }}"
                                    class="cursor-pointer transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-2 py-2 font-medium">
                                        <span class="text-slate-700">{{ $item->name }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-2 text-slate-500">
                                        {{ $item->item_code }}
                                        @if ($item->property_number)
                                            <span class="block text-[11px] text-slate-400">{{ $item->property_number }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-2">
                                        <x-classification-badge :type="$item->classification()" />
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $item->category ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-2 py-2 text-right text-slate-500">₱{{ number_format((float) $item->unit_cost, 2) }}</td>
                                    <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $item instanceof \App\Models\Equipment ? ($item->custodian_name ?: '—') : '—' }}</td>
                                    <td class="whitespace-nowrap px-2 py-2">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium {{ $statusBadges[$item->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $item instanceof \App\Models\Equipment ? ucfirst($item->status) : ($item->is_active ? 'Available' : 'Inactive') }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $placed?->format('M d, Y') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Supplies --}}
        @if ($tab !== 'equipment' && $supplies->isNotEmpty())
            <div class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-slate-800">Supplies ({{ $supplies->count() }})</h3>
                    <p class="text-xs text-slate-400">Consumables stored in this room &mdash; recorded on a RIS at any price</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Supply</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Stock Code</th>
                                <th class="whitespace-nowrap px-2 py-2 text-right font-medium text-slate-500">Quantity</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Unit</th>
                                <th class="whitespace-nowrap px-2 py-2 text-right font-medium text-slate-500">Unit Cost</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($supplies as $supply)
                                <tr data-href="{{ route($supply instanceof \App\Models\Equipment ? 'admin.equipment.show' : 'admin.supplies.show', $supply) }}"
                                    class="cursor-pointer transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-2 py-2 font-medium">
                                        <span class="text-slate-700">{{ $supply->name }}</span>
                                        <x-classification-badge :type="$supply->classification()" class="ml-1 align-middle" />
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $supply->item_code }}</td>
                                    <td class="whitespace-nowrap px-2 py-2 text-right font-medium text-slate-700">{{ $supply->quantity_on_hand }}</td>
                                    <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $supply->unit }}</td>
                                    <td class="whitespace-nowrap px-2 py-2 text-right text-slate-500">₱{{ number_format((float) $supply->unit_cost, 2) }}</td>
                                    <td class="whitespace-nowrap px-2 py-2">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium {{ $supply->is_active ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $supply instanceof \App\Models\Equipment ? ucfirst($supply->status) : (! $supply->is_active ? 'Inactive' : ($supply->quantity_on_hand > 0 ? 'In Stock' : 'Out of Stock')) }}
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
            </div>
        @endif

        {{-- A tab can leave one of the two sections out; say so rather than
             showing a blank page under the tabs. --}}
        @if ($tab === 'equipment' && $equipment->isEmpty() && $supplies->isNotEmpty())
            <p class="mt-4 rounded-xl bg-white px-4 py-8 text-center text-sm text-slate-500 ring-1 ring-slate-200">
                No equipment is currently assigned to this room.
            </p>
        @elseif ($tab === 'supplies' && $supplies->isEmpty() && $equipment->isNotEmpty())
            <p class="mt-4 rounded-xl bg-white px-4 py-8 text-center text-sm text-slate-500 ring-1 ring-slate-200">
                No supplies are currently stored in this room.
            </p>
        @endif
    @endif

    {{-- The papers raised for this room (a document's own room): the register
         keeps them, and this is the way in from the room they were written for. --}}
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-semibold text-slate-800">Documents Filed to This Room ({{ $room->documents_count }})</h3>
                <p class="mt-0.5 text-xs text-slate-400">
                    PAR / ICS / RIS papers raised for this room. A paper keeps the room it was written for, so transferring an item does not rewrite it.
                </p>
            </div>

            <a href="{{ route('admin.documents.index', ['room' => $room->id]) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                Open in register <x-icon name="arrow-right" class="h-3.5 w-3.5" />
            </a>
        </div>

        @if ($documents->isEmpty())
            <p class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-400">
                No documents are filed to this room yet.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Document No.</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Type</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Custodian</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($documents as $document)
                            <tr data-href="{{ route('admin.documents.show', $document) }}"
                                class="cursor-pointer transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-2 py-2 font-medium text-slate-700">{{ $document->document_no }}</td>
                                <td class="whitespace-nowrap px-2 py-2"><x-classification-badge :type="$document->type" /></td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $document->custodian_name ?: '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $document->document_date?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Inventory history: every move involving this room, written as it
         happened — one row per move, in or out, with who did it. --}}
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex items-center gap-2">
            <x-icon name="history" class="h-5 w-5 text-slate-400" />
            <h3 class="text-base font-semibold text-slate-800">Inventory History</h3>
        </div>

        @if ($history->isEmpty())
            <p class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-400">
                Nothing has moved in or out of this room yet. Adding or transferring an item records it here.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Date / Time</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Item</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Action</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">From Room</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">To Room</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Reason</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($history as $movement)
                            @php
                                $tone = match ($movement->action) {
                                    'added' => 'bg-brand-50 text-brand-700',
                                    'transferred' => 'bg-sky-50 text-sky-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->created_at->format('M d, Y g:i A') }}</td>
                                <td class="px-2 py-2">
                                    @if ($movement->itemUrl())
                                        <a href="{{ $movement->itemUrl() }}" class="font-medium text-slate-700 hover:text-brand-800">{{ $movement->item_label }}</a>
                                    @else
                                        <span class="font-medium text-slate-700">{{ $movement->item_label }}</span>
                                    @endif
                                    <span class="block text-[11px] text-slate-400">{{ $movement->item_code }} &middot; {{ $movement->typeLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $tone }}">{{ $movement->actionLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->fromRoom?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->toRoom?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">
                                    {{ $movement->reasonLabel() ?? '—' }}
                                    @if ($movement->notes)
                                        <span class="block max-w-xs truncate text-[11px] text-slate-400" title="{{ $movement->notes }}">{{ $movement->notes }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->user?->name ?? 'System' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Custodians who work in this room: the same person's folder holds the
         equipment assigned to them, which is also listed above as equipment in
         this room — one record, read from both. --}}
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base font-semibold text-slate-800">Custodians Assigned ({{ $room->teachers_count }})</h3>
        </div>

        @if ($custodians->isEmpty())
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
                            <th class="whitespace-nowrap px-2 py-2 text-right font-medium text-slate-500">Equipment Held</th>
                            <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($custodians as $custodian)
                            <tr data-href="{{ route('admin.teachers.show', $custodian) }}"
                                class="cursor-pointer transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-2 py-2 font-medium text-slate-700">{{ $custodian->full_name }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $custodian->employee_id }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $custodian->department ?? '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right text-slate-500">{{ $custodian->equipment_count ?? '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2">
                                    @if ($custodian->is_active)
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

    @include('admin.rooms._add_item_modal')
    @include('admin.rooms._transfer_item_modal')

    {{-- A click anywhere on a row opens the record behind it: the equipment, the
         supply or the custodian. A reader dragging across a row to copy a code
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
