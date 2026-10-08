{{-- Cost overview + who is getting more expensive, all from recorded purchases. --}}
@php
    $overviewTiles = [
        ['label' => 'Total Inventory Value', 'value' => $peso($costs['total_value']), 'note' => 'supplies + equipment at recorded unit cost'],
        ['label' => 'Supplies Value', 'value' => $peso($costs['supplies_value']), 'note' => 'quantity on hand × unit cost'],
        ['label' => 'Equipment Value', 'value' => $peso($costs['equipment_value']), 'note' => 'quantity on hand × unit cost'],
        ['label' => 'This Month (' . $costs['this_month_label'] . ')', 'value' => $peso($costs['this_month']), 'note' => 'purchases recorded this month'],
        ['label' => 'Previous Month (' . $costs['previous_month_label'] . ')', 'value' => $peso($costs['previous_month']), 'note' => 'purchases recorded last month'],
    ];

    // Links out of this section keep the filter intact. A custom range has to
    // carry its two months as well, or the destination would fall back to the
    // default range and the numbers would silently change.
    $rangeParams = $filters['period'] === 'custom'
        ? ['period' => 'custom', 'from' => $filters['from_input'], 'to' => $filters['to_input']]
        : ['period' => $filters['period']];
@endphp

<div id="cost-overview" class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm xl:col-span-2">
        <h3 class="text-base font-semibold text-slate-800">Cost Overview</h3>
        <p class="text-xs text-slate-400">Value of stock on hand and what was purchased month by month.</p>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($overviewTiles as $tile)
                <div class="rounded-xl border border-slate-100 px-3 py-2.5">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $tile['label'] }}</p>
                    <p class="mt-0.5 text-base font-semibold text-slate-900">{{ $tile['value'] }}</p>
                    <p class="text-[11px] text-slate-400">{{ $tile['note'] }}</p>
                </div>
            @endforeach

            <div class="rounded-xl border border-slate-100 px-3 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Monthly Change</p>
                @if ($costs['change_pct'] === null)
                    <p class="mt-0.5 text-base font-semibold text-slate-400">—</p>
                    <p class="text-[11px] text-slate-400">No previous month data</p>
                @else
                    <p class="mt-0.5 text-base font-semibold {{ $costs['change'] > 0 ? 'text-red-600' : ($costs['change'] < 0 ? 'text-brand-700' : 'text-slate-800') }}">
                        {{ $costs['change'] > 0 ? '+' : '' }}{{ $costs['change_pct'] }}%
                    </p>
                    <p class="text-[11px] text-slate-400">
                        {{ $costs['change'] > 0 ? '+' : '' }}{{ $peso($costs['change']) }} vs {{ $costs['previous_month_label'] }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-800">Cost Changes</h3>
        <p class="text-xs text-slate-400">Items whose unit cost went up against the previous month with records.</p>

        @if ($costAlerts['increase_count'] === 0)
            <div class="mt-4 rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center">
                <p class="text-sm text-slate-500">No cost increases detected.</p>
                <p class="mt-1 text-xs text-slate-400">
                    {{ $costAlerts['without_comparison'] }} of {{ $costAlerts['tracked'] }} items have no previous month to compare against yet.
                </p>
            </div>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($costAlerts['increases'] as $alert)
                    <li>
                        <a href="{{ route('admin.dashboard', array_merge($rangeParams, ['type' => $alert['type'], 'item' => $alert['id']])) }}#item-analytics"
                           class="flex items-start gap-2 rounded-xl border border-slate-100 px-3 py-2.5 transition hover:border-brand-200 hover:bg-brand-50/40">
                            <span class="mt-0.5 text-amber-500">
                                <x-icon name="exclamation" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-slate-800">{{ $alert['name'] }}</span>
                                <span class="block text-xs text-slate-500">
                                    {{ $alert['type_label'] }} &middot; unit cost increased by
                                    {{ $alert['trend']['change_pct'] }}%
                                    ({{ $peso($alert['trend']['previous']) }} &rarr; {{ $peso($alert['trend']['current']) }})
                                </span>
                                <span class="block text-[11px] text-slate-400">
                                    {{ $alert['trend']['from_label'] }} &rarr; {{ $alert['trend']['to_label'] }}
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<div class="mt-4 rounded-2xl border border-slate-100 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
        <div>
            <h3 class="text-base font-semibold text-slate-800">Highest Cost Items</h3>
            <p class="text-xs text-slate-400">Largest latest recorded unit cost across supplies and equipment.</p>
        </div>
    </div>

    @if ($highestCostItems->isEmpty())
        <p class="px-5 py-8 text-center text-sm text-slate-400">No items recorded yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium text-slate-500">Item</th>
                        <th class="px-4 py-2.5 text-left font-medium text-slate-500">Type</th>
                        <th class="px-4 py-2.5 text-right font-medium text-slate-500">Latest unit cost</th>
                        <th class="px-4 py-2.5 text-right font-medium text-slate-500">Monthly change</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($highestCostItems as $entry)
                        <tr class="transition hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-medium">
                                <a href="{{ route('admin.dashboard', array_merge($rangeParams, ['type' => $entry['type'], 'item' => $entry['id']])) }}#item-analytics"
                                   class="text-brand-700 hover:underline">{{ $entry['name'] }}</a>
                                <span class="block text-xs text-slate-400">{{ $entry['item_code'] }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $entry['type_label'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-700">{{ $peso($entry['latest_unit_cost']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">
                                @if ($entry['trend']['direction'] === 'no_previous_data')
                                    <span class="text-slate-400" title="No previous month with records to compare against">—</span>
                                @else
                                    <span class="{{ $entry['trend']['direction'] === 'up' ? 'text-red-600' : ($entry['trend']['direction'] === 'down' ? 'text-brand-700' : 'text-slate-500') }}">
                                        {{ $entry['trend']['change_pct'] > 0 ? '+' : '' }}{{ $entry['trend']['change_pct'] }}%
                                    </span>
                                    <span class="block text-[11px] text-slate-400">
                                        {{ $entry['trend']['from_label'] }} &rarr; {{ $entry['trend']['to_label'] }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
