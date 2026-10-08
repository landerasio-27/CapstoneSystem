@php
    $costPoints = collect($monthly['months'])->map(fn (array $month) => [
        'label' => $month['label'],
        'short' => $month['short'],
        'value' => $month['total_cost'],
    ])->all();

    $unitCostPoints = collect($monthly['months'])->map(fn (array $month) => [
        'label' => $month['label'],
        'short' => $month['short'],
        'value' => $month['average_unit_cost'],
    ])->all();

    $coverageNote = $monthly['months_with_data'] . ' of ' . $monthly['months_total'] . ' months in this range have purchase records.';
@endphp

<div class="mt-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
            <h3 class="text-base font-semibold text-slate-800">Monthly Cost Analysis &mdash; {{ $filters['type_label'] }}</h3>
            <p class="text-xs text-slate-400">{{ $filters['period_label'] }} ({{ $filters['range_label'] }}) &middot; {{ $coverageNote }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-5 text-sm">
            <span class="text-slate-500">
                Total purchases
                <b class="ml-1 font-semibold text-slate-800">{{ $peso($monthly['total_cost']) }}</b>
            </span>
            <span class="text-slate-500">
                Average unit cost
                <b class="ml-1 font-semibold text-slate-800">
                    {{ $monthly['average_unit_cost'] === null ? '—' : $peso($monthly['average_unit_cost']) }}
                </b>
            </span>
        </div>
    </div>

    @unless ($monthly['has_data'])
        <p class="mt-4 rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-400">
            No {{ $filters['type'] === 'equipment' ? 'property document' : 'stock-in transaction' }} records in this period, so no cost analysis is available yet.
        </p>
    @endunless

    <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="chart-compact">
            <h4 class="mb-2 text-sm font-semibold text-slate-700">Monthly purchase cost</h4>
            <x-chart.line :points="$costPoints" value-prefix="₱" :decimals="0"
                          empty="No purchase records in this period."
                          :note="$coverageNote . ' Months without records are left blank on purpose.'" />
        </div>
        <div class="chart-compact">
            <h4 class="mb-2 text-sm font-semibold text-slate-700">Monthly average unit cost</h4>
            <x-chart.line :points="$unitCostPoints" value-prefix="₱" :decimals="2"
                          empty="No purchase records in this period."
                          note="Average of the unit costs recorded in each month." />
        </div>
    </div>

    <div class="mt-5 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-slate-500">Month</th>
                    <th class="px-3 py-2 text-right font-medium text-slate-500">Quantity added</th>
                    <th class="px-3 py-2 text-right font-medium text-slate-500">Average unit cost</th>
                    <th class="px-3 py-2 text-right font-medium text-slate-500">Total cost</th>
                    <th class="px-3 py-2 text-right font-medium text-slate-500">Records</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($monthly['months'] as $month)
                    <tr>
                        <td class="px-3 py-2 text-slate-700">{{ $month['label'] }}</td>
                        @if ($month['has_data'])
                            <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ number_format($month['quantity']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $peso($month['average_unit_cost']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $peso($month['total_cost']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ $month['records'] }}</td>
                        @else
                            <td colspan="4" class="px-3 py-2 text-center text-xs text-slate-400">No records for this month</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
