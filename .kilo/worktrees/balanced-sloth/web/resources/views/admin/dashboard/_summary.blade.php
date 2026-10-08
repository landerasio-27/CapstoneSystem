{{-- Compact summary: four small tiles instead of a large card per statistic. --}}
@php
    $tiles = [
        [
            'label' => 'Supplies',
            'value' => number_format($summary['supplies']),
            'sub' => 'items in the catalogue',
            'icon' => 'archive',
            'tone' => 'brand',
            'href' => route('admin.supplies.index'),
        ],
        [
            'label' => 'Equipment',
            'value' => number_format($summary['equipment']),
            'sub' => 'registered property items',
            'icon' => 'box',
            'tone' => 'sky',
            'href' => route('admin.equipment.index'),
        ],
        [
            'label' => 'Low Stock',
            'value' => number_format($summary['low_stock']),
            'sub' => $summary['low_stock'] > 0 ? 'at or below their reorder point' : 'all supplies above their reorder point',
            'icon' => 'exclamation',
            'tone' => $summary['low_stock'] > 0 ? 'amber' : 'slate',
            'href' => route('admin.supplies.index', ['low_stock' => 1]),
        ],
        [
            'label' => 'Inventory Value',
            'value' => $peso($summary['inventory_value']),
            'sub' => 'supplies ' . $peso($summary['supply_value']) . ' + equipment ' . $peso($summary['equipment_value']),
            'icon' => 'chart',
            'tone' => 'indigo',
            'href' => '#cost-overview',
        ],
    ];
@endphp

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($tiles as $tile)
        <a href="{{ $tile['href'] }}"
           class="flex items-center gap-3 rounded-xl border border-slate-100 bg-white px-3 py-2.5 shadow-sm transition hover:border-brand-200 hover:shadow-md">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $badges[$tile['tone']] }}">
                <x-icon :name="$tile['icon']" class="h-4 w-4" />
            </span>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $tile['label'] }}</span>
                <span class="block text-lg font-semibold leading-tight text-slate-900">{{ $tile['value'] }}</span>
                <span class="block truncate text-[11px] text-slate-400" title="{{ $tile['sub'] }}">{{ $tile['sub'] }}</span>
            </span>
        </a>
    @endforeach
</div>

{{-- Operations numbers stay available, but as one compact strip rather than cards. --}}
<div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-slate-100 bg-white px-4 py-2.5 text-xs text-slate-500 shadow-sm">
    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Operations</span>

    <span>Missing equipment <b class="font-semibold text-slate-700">{{ $operations['missing_equipment'] }}</b></span>

    <span>Misplaced equipment <b class="font-semibold text-slate-700">{{ $operations['misplaced_equipment'] }}</b></span>
</div>
