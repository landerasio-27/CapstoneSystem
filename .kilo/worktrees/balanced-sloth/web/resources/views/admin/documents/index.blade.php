@extends('layouts.admin')

@section('title', 'PAR / ICS / RIS')

@section('content')
    {{-- Filters apply as they change (see resources/js/filter-bar.js): there is
         no Filter button to press. Everything sits on one line until the
         viewport is too narrow, then the row wraps. --}}
    <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <form method="GET" data-auto-filter class="flex flex-1 flex-wrap items-center gap-2 xl:flex-nowrap">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search document no..."
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 sm:w-52">
            <select name="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All types</option>
                @foreach (['PAR', 'IAR', 'ICS', 'RIS'] as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All statuses</option>
                @foreach (['draft', 'approved', 'issued'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>

            {{-- Date range over the document date (inclusive). --}}
            <div class="flex items-center gap-2">
                <label for="doc-date-from" class="text-xs font-medium text-slate-500">From</label>
                <input id="doc-date-from" type="date" name="date_from" value="{{ request('date_from') }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <label for="doc-date-to" class="text-xs font-medium text-slate-500">To</label>
                <input id="doc-date-to" type="date" name="date_to" value="{{ request('date_to') }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            @if (request()->hasAny(['search', 'type', 'status', 'date_from', 'date_to']))
                <a href="{{ route('admin.documents.index') }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm text-slate-500 hover:text-slate-700">Clear</a>
            @endif
        </form>

        {{-- A compact button that hugs its label, sitting at the right end of
             the row: it must not stretch to the full width when the filters
             wrap onto their own line above it. --}}
        <a href="{{ route('admin.documents.create') }}"
           class="inline-flex w-fit shrink-0 items-center gap-1.5 self-end rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900 xl:self-auto">
            <x-icon name="plus" class="h-4 w-4" /> New Document
        </a>
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
                        <td class="px-4 py-3 text-slate-500">{{ $doc->type }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $doc->custodian_name ?: '—' }} {{ $doc->room ? '/ '.$doc->room->code : '' }}</td>
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
