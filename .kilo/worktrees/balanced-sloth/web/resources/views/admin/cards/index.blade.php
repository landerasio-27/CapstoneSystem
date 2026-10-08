@extends('layouts.admin')

@section('title', 'Stock / Property')

@section('content')
    @php
        // Re-open the modal when the server rejected a submission, on the step
        // and with the values the visitor had filled in.
        $reopenType = $errors->any() && in_array(old('type'), ['SC', 'PC'], true) ? old('type') : null;
    @endphp

    <p class="mb-3 text-sm text-slate-500">Manage digital Stock Cards and Property Cards.</p>

    {{-- Filters apply as they change (see resources/js/filter-bar.js): there is
         no Search button to press. The filters and Add Card share one line
         until the viewport is too narrow, then the row wraps. --}}
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

            <button type="button" onclick="ISUNewCard.open()"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                <x-icon name="plus" class="h-4 w-4" /> Add Card
            </button>
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
                                Create a Stock Card for a supply, or a Property Card for a piece of equipment.
                            </p>
                            <button type="button" onclick="ISUNewCard.open()"
                                    class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                                <x-icon name="plus" class="h-4 w-4" /> Add Card
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $cards->links() }}</div>

    {{-- Add Card: pick the card type, then fill the details, without leaving the page. --}}
    <div id="new-card-modal" data-open-step="{{ $reopenType ?? '' }}"
         class="fixed inset-0 z-50 {{ $reopenType ? 'flex' : 'hidden' }} items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUNewCard.close()"></div>

        <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <button type="button" onclick="ISUNewCard.close()" aria-label="Close"
                    class="absolute right-4 top-4 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>

            {{-- Step 1: which kind of card is this? --}}
            <div data-new-card-step="choose">
                <h2 class="text-base font-semibold text-slate-800">Create New Card</h2>
                <p class="mt-1 text-sm text-slate-500">What type of card do you want to create?</p>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <button type="button" onclick="ISUNewCard.choose('SC')"
                            class="group rounded-xl border border-slate-200 p-6 text-center transition hover:border-brand-300 hover:bg-brand-50/40">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                            <x-icon name="archive" class="h-6 w-6" />
                        </span>
                        <span class="mt-3 block font-semibold text-slate-800">Stock Card</span>
                        <span class="block text-sm text-slate-500">For supplies</span>
                        <span class="mt-2 block text-xs uppercase tracking-wider text-slate-400">SC</span>
                    </button>

                    <button type="button" onclick="ISUNewCard.choose('PC')"
                            class="group rounded-xl border border-slate-200 p-6 text-center transition hover:border-brand-300 hover:bg-brand-50/40">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                            <x-icon name="box" class="h-6 w-6" />
                        </span>
                        <span class="mt-3 block font-semibold text-slate-800">Property Card</span>
                        <span class="block text-sm text-slate-500">For PPE / assets</span>
                        <span class="mt-2 block text-xs uppercase tracking-wider text-slate-400">PC</span>
                    </button>
                </div>
            </div>

            {{-- Step 2: the details for that kind of card. --}}
            @foreach (['SC', 'PC'] as $cardType)
                @php $isStockCard = $cardType === 'SC'; @endphp
                <div data-new-card-step="{{ $cardType }}" class="hidden">
                    <div class="mb-4">
                        <button type="button" onclick="ISUNewCard.choose(null)"
                                class="inline-flex items-center text-sm text-slate-500 hover:text-slate-700 hover:underline">
                            Change card type
                        </button>
                    </div>

                    <h2 class="text-base font-semibold text-slate-800">
                        New {{ $isStockCard ? 'Stock Card (SC)' : 'Property Card (PC)' }}
                    </h2>
                    <p class="mt-1 mb-4 text-sm text-slate-500">
                        {{ $isStockCard
                            ? 'The card documents one supply. Item, description, unit and stock number come from the Supplies record.'
                            : 'The card documents one piece of equipment. PPE, description and property number come from the Equipment record.' }}
                    </p>

                    @include('admin.cards._create_form', [
                        'cardType' => $cardType,
                        'options' => $isStockCard ? $supplies : $equipment,
                        'defaults' => $defaults,
                        'inModal' => true,
                    ])
                </div>
            @endforeach
        </div>
    </div>

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

    <script>
        // Add Card: one modal, two steps (choose the type, then fill it in).
        window.ISUNewCard = (function () {
            var modal = document.getElementById('new-card-modal');

            function choose(type) {
                modal.querySelectorAll('[data-new-card-step]').forEach(function (step) {
                    step.classList.toggle('hidden', step.getAttribute('data-new-card-step') !== (type || 'choose'));
                });
            }

            return {
                open: function () {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    choose('choose');
                },
                choose: choose,
                close: function () {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                },
            };
        })();

        // A rejected submission re-opens straight into the step that failed.
        (function () {
            var step = document.getElementById('new-card-modal').getAttribute('data-open-step');
            if (step) window.ISUNewCard.choose(step);
        })();
    </script>

    @include('admin.cards._create_script')
@endsection
