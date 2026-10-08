@extends('layouts.admin')

@section('title', 'Equipment')

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-wrap flex-1 gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, code..."
                   class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 sm:w-auto">
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All statuses</option>
                @foreach (['available', 'issued', 'damaged', 'missing', 'misplaced'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <select name="category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                @endforeach
            </select>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="search" class="h-4 w-4" /> Filter</button>
        </form>

        <a href="{{ route('admin.equipment.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
            <x-icon name="plus" class="h-4 w-4" /> New Equipment
        </a>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Item</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Code</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Room</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Custodian</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Qty</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                    <th class="px-4 py-3 text-right font-medium text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($equipment as $item)
                    {{-- The row itself opens the equipment record, so no cell has
                         to look like the way in: the name is written as the
                         plain text it reads as, and Edit keeps its own place. --}}
                    <tr data-href="{{ route('admin.equipment.show', $item) }}"
                        class="cursor-pointer transition hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">
                            <span class="text-slate-700">{{ $item->name }}</span>
                            {{-- No low-stock badge here: the re-order point is a supply
                                 threshold, so property is never flagged against one. --}}
                            <x-classification-badge :type="$item->classification()" class="ml-1 align-middle" />
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $item->item_code }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $item->room?->code ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $item->custodian_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $item->quantity_on_hand }}</td>
                        <td class="px-4 py-3">
                            @php
                                $badges = [
                                    'available' => 'bg-brand-50 text-brand-700',
                                    'issued' => 'bg-sky-50 text-sky-700',
                                    'damaged' => 'bg-orange-50 text-orange-700',
                                    'missing' => 'bg-red-50 text-red-700',
                                    'misplaced' => 'bg-amber-50 text-amber-700',
                                ];
                            @endphp
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $badges[$item->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($item->status) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            {{-- No delete action: equipment records are kept, and its
                                 quantity/cost history must stay intact. --}}
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('admin.equipment.edit', $item) }}" class="text-brand-600 hover:underline">Edit</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-400">No equipment found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $equipment->links() }}</div>

    {{-- A click anywhere on a row opens that equipment record. A click that
         lands on a link or a control inside the row is left alone: those carry
         their own destination, and a reader dragging across a row to copy a code
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
