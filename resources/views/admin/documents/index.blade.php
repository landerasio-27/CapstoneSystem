@extends('layouts.admin')

@section('title', 'PAR / ICS / RIS')

@section('content')
    {{-- The classification at the top of the register: how many PAR / ICS / RIS
         papers there are, how many items each covers, and what the property is
         worth. Every number is read from the same rule the badges and the forms
         use, and each card opens this register filtered to its own paper. --}}
    <x-classification-summary :classification="$classification" class="mb-4" />

    @php
        // A pill keeps every other filter in the query string and only swaps the
        // type, so narrowing to PAR never loses the search or the dates.
        $typeUrl = fn (?string $type) => route('admin.documents.index', array_merge(
            array_diff_key(request()->query(), ['type' => null, 'page' => null]),
            $type ? ['type' => $type] : [],
        ));

        $pill = 'inline-flex items-center rounded-full px-3.5 py-1.5 text-xs font-medium ring-1 transition';
        $pillOn = 'bg-brand-800 text-white ring-brand-800';
        $pillOff = 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50';

        // The filter row is asked to show every filter at once on the one line,
        // so its fields are a size down, and share the row between them rather
        // than each claiming its full natural width. The select's first option
        // is the label it carries when it is not set, so it is kept to the one
        // word a narrowed field has room for.
        $field = 'min-w-0 rounded-lg border border-slate-300 px-1.5 py-1.5 text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500';
        $select = 'min-w-0 rounded-lg border border-slate-300 px-1.5 py-1.5 text-xs text-slate-700 sm:min-w-[4.5rem] sm:flex-[1.2]';
        $dateLabel = 'text-[11px] font-medium text-slate-500';
    @endphp

    {{-- Filters apply as they change (see resources/js/filter-bar.js): there is
         no Filter button to press. The paper is chosen above with the pills; the
         rest of the filters sit in the form, which carries the chosen type in a
         hidden field so a search never drops it. Both rows are meant to read as
         one line each: the pills and the button share the top row, and the
         filters share the row under it. --}}
    <div class="mb-4 space-y-3">
        {{-- Pills on the left, New Document on the right, on the one row. It only
             ever stacks on a phone, where there is genuinely no room for both. --}}
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $typeUrl(null) }}" class="{{ $pill }} {{ request('type') ? $pillOff : $pillOn }}">All</a>
                @foreach (['PAR', 'ICS', 'RIS', 'IAR'] as $type)
                    <a href="{{ $typeUrl($type) }}" class="{{ $pill }} {{ request('type') === $type ? $pillOn : $pillOff }}">{{ $type }}</a>
                @endforeach
            </div>

            {{-- A compact button that hugs its label, sitting at the right end of
                 the row: it must not stretch to the full width of the row. --}}
            <a href="{{ route('admin.documents.create') }}"
               class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                <x-icon name="plus" class="h-4 w-4" /> New Document
            </a>
        </div>

        {{-- Every filter on the one line, from the search to the dates, and sized
             to be seen at once: the fields share the row in proportion, the text
             is a step down, and the dates take the share two fields need so their
             numbers still read. Only a window too narrow for even that scrolls. --}}
        <form method="GET" data-auto-filter class="filter-scroll flex flex-wrap items-center gap-2 pb-1 sm:flex-nowrap sm:overflow-x-auto">
            {{-- Chosen with the pills above; kept when the form is submitted. --}}
            <input type="hidden" name="type" value="{{ request('type') }}">

            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search documents..."
                   class="w-full {{ $field }} sm:w-auto sm:min-w-[6rem] sm:flex-[1.2]">

            <select name="status" class="{{ $select }}">
                <option value="">Status</option>
                @foreach (['draft', 'approved', 'issued'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>

            <select name="custodian" class="{{ $select }}">
                <option value="">Custodian</option>
                @foreach ($custodians as $custodian)
                    <option value="{{ $custodian->id }}" @selected((string) request('custodian') === (string) $custodian->id)>{{ $custodian->full_name }}</option>
                @endforeach
            </select>

            <select name="room" class="{{ $select }}">
                <option value="">Room</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}" @selected((string) request('room') === (string) $room->id)>{{ $room->name }}</option>
                @endforeach
            </select>

            <select name="department" class="{{ $select }}">
                <option value="">Department</option>
                @foreach ($departments as $department)
                    <option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>
                @endforeach
            </select>

            <select name="category" class="{{ $select }}">
                <option value="">Category</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                @endforeach
            </select>

            <select name="consumable" class="{{ $select }}">
                <option value="">Consumable</option>
                <option value="1" @selected(request('consumable') === '1')>Consumable (RIS)</option>
                <option value="0" @selected(request('consumable') === '0')>Non-consumable (PAR / ICS)</option>
            </select>

            {{-- Date range over the document date (inclusive). --}}
            <div class="flex min-w-0 items-center gap-1.5 sm:min-w-[9rem] sm:flex-[2.4]">
                <label for="doc-date-from" class="{{ $dateLabel }}">From</label>
                <input id="doc-date-from" type="date" name="date_from" value="{{ request('date_from') }}"
                       class="{{ $field }} flex-1">
                <label for="doc-date-to" class="{{ $dateLabel }}">To</label>
                <input id="doc-date-to" type="date" name="date_to" value="{{ request('date_to') }}"
                       class="{{ $field }} flex-1">
            </div>

            @if (request()->hasAny(['search', 'type', 'status', 'custodian', 'room', 'department', 'category', 'consumable', 'date_from', 'date_to']))
                <a href="{{ route('admin.documents.index') }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs text-slate-500 hover:text-slate-700">Clear</a>
            @endif
        </form>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Document No.</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Type</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Custodian / Room</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                {{-- The row itself opens the document: the list is read row by
                     row, so the whole of it is the target and no column is
                     spent on a View link — nor does the number have to look
                     like the way in, so it is written as the plain text it
                     reads as. --}}
                @forelse ($documents as $doc)
                    <tr data-href="{{ route('admin.documents.show', $doc) }}"
                        class="cursor-pointer transition hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">
                            <span class="text-slate-700">{{ $doc->document_no }}</span>
                        </td>
                        <td class="px-4 py-3">
                            {{-- The paper's own classification, in the same badge the
                                 items carry. --}}
                            <x-classification-badge :type="$doc->type" />
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $doc->custodian_name ?: '—' }} {{ $doc->room ? '/ '.$doc->room->name : '' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $badges = ['draft' => 'bg-slate-100 text-slate-600', 'approved' => 'bg-sky-50 text-sky-700', 'issued' => 'bg-brand-50 text-brand-700'];
                            @endphp
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $badges[$doc->status] }}">{{ ucfirst($doc->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $doc->document_date->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No documents found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $documents->links() }}</div>

    {{-- A click anywhere on a row opens that document. A click that lands on a
         link or a control inside the row is left alone: those carry their own
         destination, and a reader dragging across the row to copy a number
         gets the text rather than a page change. --}}
    <script>
        document.querySelectorAll('[data-href]').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, select, label')) return;
                // A reader who dragged across the row to copy a number is
                // reading, not opening it.
                if (String(window.getSelection()).trim() !== '') return;

                window.location.href = row.dataset.href;
            });
        });
    </script>
@endsection
