@extends('layouts.admin')

@section('title', 'Stock / Property')

@section('content')
    <p class="mb-3 text-sm text-slate-500">Manage digital Stock Cards and Property Cards.</p>

    {{-- Filters apply as they change (see resources/js/filter-bar.js): there is
         no Search button to press. Cards are not opened from here either: each
         one is created automatically with the supply or equipment it documents,
         so this page only reads the register. --}}
    <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex flex-1 flex-wrap items-center gap-2 xl:flex-nowrap">
            <form method="GET" data-auto-filter class="flex flex-1 flex-wrap items-center gap-2 xl:flex-nowrap">
                @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search cards..."
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 sm:w-52">

                {{-- Date range over the card's last activity, inclusive on both ends. --}}
                <label for="card-date-from" class="text-xs font-medium text-slate-500">From</label>
                <input id="card-date-from" type="date" name="date_from" value="{{ request('date_from') }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <label for="card-date-to" class="text-xs font-medium text-slate-500">To</label>
                <input id="card-date-to" type="date" name="date_to" value="{{ request('date_to') }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm">

                @if (request()->hasAny(['search', 'date_from', 'date_to']))
                    <a href="{{ url()->current() }}" class="inline-flex items-center rounded-lg px-2 py-2 text-sm text-slate-500 hover:text-slate-700">Clear</a>
                @endif
            </form>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach ([
            ['label' => 'All Cards', 'route' => 'admin.cards.index', 'count' => $counts['all'], 'active' => $type === null],
            ['label' => 'Stock Cards', 'route' => 'admin.cards.stock', 'count' => $counts['SC'], 'active' => $type === 'SC'],
            ['label' => 'Property Cards', 'route' => 'admin.cards.property', 'count' => $counts['PC'], 'active' => $type === 'PC'],
        ] as $tab)
            <a href="{{ route($tab['route']) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 {{ $tab['active'] ? 'border-brand-200 bg-brand-50 font-medium text-brand-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                {{ $tab['label'] }}
                <span class="rounded-full bg-white px-2 py-0.5 text-xs text-slate-500 ring-1 ring-slate-200">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Card Type</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Item</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Card No.</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Balance</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Last activity</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($cards as $card)
                    @php $lastLine = $card->transactions()->reorder()->orderByDesc('transaction_date')->orderByDesc('id')->first(); @endphp
                    {{-- The row itself opens the card, so the list is read row by
                         row and no column is spent on actions at all — nor does
                         any cell have to look like the way in: the item is
                         written as the plain text it reads as, and a card is
                         edited or printed from its own page. --}}
                    <tr data-href="{{ route('admin.cards.show', $card) }}"
                        class="cursor-pointer transition hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $card->isStockCard() ? 'bg-brand-50 text-brand-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $card->type }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-700">{{ $card->itemName() }}</span>
                            <span class="block text-xs text-slate-400">{{ $card->itemCode() ?: '—' }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $card->card_no }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $card->balance() }} {{ $card->unit() }}</td>
                        <td class="px-4 py-3 text-slate-500">
                            @if ($lastLine)
                                {{ $lastLine->transaction_date->format('M d, Y') }}
                                <span class="block text-xs text-slate-400">{{ $card->transactions()->count() }} transaction(s)</span>
                            @else
                                <span class="text-slate-400">No transactions yet</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center">
                            <p class="text-slate-500">No cards yet.</p>
                            <p class="mt-1 text-sm text-slate-400">
                                A Stock Card is opened for every new supply, and a Property Card for every new piece of
                                equipment, as soon as the record is saved.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $cards->links() }}</div>

    {{-- A click anywhere on a row opens that card. A reader dragging across a row
         to copy a number gets the text rather than a page change, and the row is
         the only thing on the line to click. --}}
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
