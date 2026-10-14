@extends('layouts.admin')

@section('title', 'Supplies')

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-wrap flex-1 gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or code..."
                   class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 sm:w-auto">
            <select name="category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600">
                <input type="checkbox" name="low_stock" value="1" @checked(request('low_stock'))> Low stock only
            </label>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="search" class="h-4 w-4" /> Filter</button>
        </form>

        <a href="{{ route('admin.supplies.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
            <x-icon name="plus" class="h-4 w-4" /> New Supply
        </a>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Item</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Code</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Category</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Qty on Hand</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Room</th>
                    <th class="px-4 py-3 text-right font-medium text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($supplies as $supply)
                    {{-- The row itself opens the supply, so no cell has to look
                         like the way in: the name is written as the plain text
                         it reads as, and Edit keeps its own place. --}}
                    <tr data-href="{{ route('admin.supplies.show', $supply) }}"
                        class="cursor-pointer transition hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">
                            <span class="text-slate-700">{{ $supply->name }}</span>
                            <x-classification-badge :type="$supply->classification()" class="ml-1 align-middle" />
                            @if ($supply->isLowStock())
                                <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Low stock</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $supply->item_code }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $supply->category ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $supply->quantity_on_hand }} {{ $supply->unit }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $supply->room?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.supplies.edit', $supply) }}" class="text-brand-600 hover:underline">Edit</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No supplies found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $supplies->links() }}</div>

    {{-- A click anywhere on a row opens that supply. A click that lands on a
         link or a control inside the row is left alone: those carry their own
         destination, and a reader dragging across a row to copy a code gets the
         text rather than a page change. --}}
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
