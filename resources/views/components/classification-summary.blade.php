{{-- The property classification at a glance: how many PAR / ICS / RIS papers
     the register holds, how many catalogue items each classification covers,
     and what the non-consumable property is worth.

     Every number comes from the one rule in App\Services\ItemClassifier (counted
     once in InventoryAnalyticsService::classificationSummary()), so the cards,
     the badges beside an item and the panels on the forms can never disagree.
     Each card opens the register already filtered to its own paper. --}}
@props(['classification'])

@php
    $papers = $classification['papers'];
    $items = $classification['items'];

    $threshold = number_format(\App\Services\ItemClassifier::PROPERTY_THRESHOLD, 2);

    $cards = [
        ['type' => 'PAR', 'label' => 'Property Acknowledgement Receipt', 'sub' => 'non-consumable, unit cost above ₱' . $threshold],
        ['type' => 'ICS', 'label' => 'Inventory Custodian Slip', 'sub' => 'non-consumable, unit cost at or below ₱' . $threshold],
        ['type' => 'RIS', 'label' => 'Requisition and Issue Slip', 'sub' => 'consumable supplies, whatever the price'],
    ];

    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
@endphp

<div {{ $attributes }}>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach ($cards as $card)
            <a href="{{ route('admin.documents.index', ['type' => $card['type']]) }}"
               class="flex items-start justify-between gap-3 rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-sm transition hover:border-brand-200 hover:shadow-md">
                <span class="min-w-0">
                    <span class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ \App\Services\ItemClassifier::tone($card['type']) }}">{{ $card['type'] }}</span>
                        <span class="truncate text-xs font-medium text-slate-500">{{ $card['label'] }}</span>
                    </span>
                    <span class="mt-1.5 block text-2xl font-semibold leading-none text-slate-900">
                        {{ number_format($papers[$card['type']]) }}<span class="ml-1 text-xs font-normal text-slate-400">{{ $papers[$card['type']] === 1 ? 'paper' : 'papers' }}</span>
                    </span>
                    <span class="mt-1.5 block text-[11px] text-slate-400">
                        {{ number_format($items[$card['type']]) }} {{ $items[$card['type']] === 1 ? 'item' : 'items' }} classified
                    </span>
                    <span class="mt-0.5 block truncate text-[11px] text-slate-400" title="{{ $card['sub'] }}">{{ $card['sub'] }}</span>
                </span>
                <x-icon name="arrow-right" class="mt-0.5 h-4 w-4 shrink-0 text-slate-300" />
            </a>
        @endforeach
    </div>

    {{-- The inventory side of the same rule: what the two classifications add up
         to, and what the property the office still holds is worth. --}}
    <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl border border-slate-100 bg-white px-4 py-2.5 text-xs text-slate-500 shadow-sm">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Property Classification</span>

        <span>Total consumable supplies <b class="font-semibold text-slate-700">{{ number_format($classification['consumable']) }}</b></span>

        <span>Total non-consumable property <b class="font-semibold text-slate-700">{{ number_format($classification['non_consumable']) }}</b></span>

        <span>Total property value <b class="font-semibold text-slate-700">{{ $peso($classification['property_value']) }}</b></span>

        <span class="text-slate-400">Threshold ₱{{ $threshold }}</span>
    </div>
</div>
