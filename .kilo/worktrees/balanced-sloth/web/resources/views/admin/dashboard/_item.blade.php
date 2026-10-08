@php
    $trend = $item['trend'] ?? ['direction' => 'no_previous_data', 'months_with_data' => 0, 'change' => null, 'change_pct' => null, 'previous' => null, 'current' => null, 'from_label' => null, 'to_label' => null];
    $costPoints = collect($item['months'] ?? [])->map(fn (array $month) => [
        'label' => $month['label'],
        'short' => $month['short'],
        'value' => $month['average_unit_cost'],
    ])->all();

    $monthsWithData = $item['months_with_data'] ?? 0;
    $monthsTotal = count($item['months'] ?? []);
    $unitCostNote = 'Month average unit cost. ' . $monthsWithData . ' of ' . $monthsTotal . ' months in this range have purchase records.'
        . ($monthsWithData < 2 ? ' A month-over-month trend needs at least two consecutive months of records.' : '');

    if (($item['found'] ?? false) && $item['type'] === 'supplies') {
        $recordUrl = route('admin.supplies.show', $item['id']);
    } elseif ($item['found'] ?? false) {
        $recordUrl = route('admin.equipment.show', $item['id']);
    } else {
        $recordUrl = null;
    }
@endphp

<div class="mt-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
            <h3 class="text-base font-semibold text-slate-800">{{ $filters['type'] === 'equipment' ? 'Equipment Cost Analytics' : 'Supply Analytics' }}</h3>
            <p class="text-xs text-slate-400">
                @if ($item['found'])
                    {{ $item['name'] }} &middot; {{ $item['item_code'] }} &middot;
                @endif
                {{ $filters['period_label'] }} ({{ $filters['range_label'] }})
            </p>
        </div>

        @if ($item['found'])
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium {{ $badges[$item['status_tone']] }}">
                    <span class="text-slate-500">Stock:</span> {{ $item['status_label'] }}
                </span>
                @if ($item['type'] === 'equipment' && ! empty($item['equipment_status']))
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                        <span class="text-slate-400">Property:</span> {{ ucfirst($item['equipment_status']) }}
                    </span>
                @endif
                @if ($recordUrl)
                    <a href="{{ $recordUrl }}" class="text-sm font-medium text-brand-700 transition hover:text-brand-800">Open record</a>
                @endif
            </div>
        @endif
    </div>

    @if (! $item['found'])
        <p class="py-10 text-center text-sm text-slate-400">
            No {{ $filters['type'] === 'equipment' ? 'equipment' : 'supplies' }} recorded yet, so there is nothing to analyse.
        </p>
    @else
        @php
            $kpis = [
                [
                    'label' => 'Current Stock',
                    'value' => number_format($item['quantity']) . ' ' . $item['unit'],
                    // Only supplies carry a re-order point the office sets.
                    'note' => $item['type'] === 'supplies'
                        ? (($item['reorder_level'] ?? 0) > 0 ? 'reorder point ' . number_format($item['reorder_level']) : 'no reorder point set')
                        : null,
                ],
                [
                    'label' => 'Added (' . $filters['period_label'] . ')',
                    'value' => number_format($item['added_in_period']) . ' ' . $item['unit'],
                    'note' => $item['records_in_period'] > 0
                        ? $item['records_in_period'] . ' purchase record' . ($item['records_in_period'] === 1 ? '' : 's')
                        : 'no purchase records in range',
                ],
                [
                    'label' => 'Average Unit Cost',
                    'value' => $item['average_unit_cost'] === null ? '—' : $peso($item['average_unit_cost']),
                    'note' => $item['costed_from_records'] ? 'average of recorded purchases' : 'no purchases in range',
                ],
                [
                    'label' => 'Latest Unit Cost',
                    'value' => $peso($item['latest_unit_cost']),
                    'note' => $item['latest_unit_cost_at']
                        // Supplies record an exact date; equipment is grouped by the month of its document.
                        ? 'recorded ' . $item['latest_unit_cost_at']->format($item['type'] === 'equipment' ? 'M Y' : 'M d, Y')
                        : 'product record (no purchases yet)',
                ],
                [
                    'label' => 'Total Stock Cost',
                    'value' => $peso($item['total_stock_cost']),
                    'note' => $item['costed_from_records']
                        ? 'quantity × average purchase cost'
                        : 'quantity × recorded unit cost',
                ],
            ];
        @endphp

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($kpis as $kpi)
                <div class="rounded-xl border border-slate-100 px-3 py-2.5">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $kpi['label'] }}</p>
                    <p class="mt-0.5 text-base font-semibold text-slate-900">{{ $kpi['value'] }}</p>
                    @if (! empty($kpi['note']))
                        <p class="text-[11px] text-slate-400">{{ $kpi['note'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Cost trend: only ever shown when two consecutive months of records exist. --}}
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Cost trend</span>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold {{ $trendClasses[$trend['direction']] }}">
                {{ $trendArrows[$trend['direction']] }} {{ $trendLabels[$trend['direction']] }}
            </span>

            @if ($trend['direction'] === 'no_previous_data')
                <span class="text-xs text-slate-500">
                    No previous cost data to compare with
                    @if ($trend['months_with_data'] > 0)
                        — {{ $trend['months_with_data'] }} month{{ $trend['months_with_data'] === 1 ? '' : 's' }} of purchase records so far.
                    @else
                        — no purchase records recorded yet.
                    @endif
                </span>
            @else
                <span class="text-xs text-slate-500">
                    {{ $trend['from_label'] }} {{ $peso($trend['previous']) }}
                    &rarr; {{ $trend['to_label'] }} {{ $peso($trend['current']) }}
                </span>
                <span class="text-xs font-semibold {{ $trend['direction'] === 'up' ? 'text-red-600' : ($trend['direction'] === 'down' ? 'text-brand-700' : 'text-slate-500') }}">
                    {{ $trend['change'] > 0 ? '+' . $peso($trend['change']) : $peso($trend['change']) }}
                    @if ($trend['change_pct'] !== null)
                        ({{ $trend['change_pct'] > 0 ? '+' : '' }}{{ $trend['change_pct'] }}%)
                    @endif
                </span>
            @endif
        </div>

        <div class="mt-5">
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <h4 class="text-sm font-semibold text-slate-700">Unit Cost Trend</h4>
                <span class="text-xs text-slate-400">Monthly average unit cost (₱)</span>
            </div>
            <x-chart.line :points="$costPoints" value-prefix="₱" :decimals="2"
                          empty="No cost records in this period."
                          :note="$unitCostNote" />
        </div>
    @endif
</div>

@if ($item['found'])
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">Monthly History</h3>
            <p class="text-xs text-slate-400">Stock added and what it cost, month by month.</p>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Month</th>
                            <th class="px-3 py-2 text-right font-medium text-slate-500">Stock added</th>
                            <th class="px-3 py-2 text-right font-medium text-slate-500">Unit cost</th>
                            <th class="px-3 py-2 text-right font-medium text-slate-500">Total cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($item['months'] as $month)
                            <tr>
                                <td class="px-3 py-2 text-slate-700">{{ $month['label'] }}</td>
                                @if ($month['has_data'])
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-700">
                                        {{ number_format($month['quantity']) }} {{ $item['unit'] }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $peso($month['average_unit_cost']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $peso($month['total_cost']) }}</td>
                                @else
                                    <td colspan="3" class="px-3 py-2 text-center text-xs text-slate-400">No records for this month</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-sm text-slate-400">No months in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">
                {{ $item['history_kind'] === 'documents' ? 'Property Documents' : 'Recent Stock Transactions' }}
            </h3>
            <p class="text-xs text-slate-400">
                {{ $item['history_kind'] === 'documents'
                    ? 'Acquisition cost as recorded on PAR/ICS documents.'
                    : 'Every stock movement recorded for this supply.' }}
            </p>

            <div class="mt-3 max-h-72 overflow-y-auto">
                @if ($item['history_kind'] === 'documents')
                    @if ($item['history']->isEmpty())
                        <p class="py-6 text-center text-sm text-slate-400">No acquisition documents recorded yet.</p>
                    @else
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-slate-500">Date</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-500">Document</th>
                                    <th class="px-3 py-2 text-right font-medium text-slate-500">Qty</th>
                                    <th class="px-3 py-2 text-right font-medium text-slate-500">Unit cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($item['history'] as $record)
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap text-slate-500">
                                            {{ \Illuminate\Support\Carbon::parse($record->document_date)->format('M d, Y') }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">
                                            {{ $record->document_no }}
                                            <span class="block text-xs text-slate-400">{{ $record->type }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ number_format($record->quantity) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $peso($record->unit_cost) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                @else
                    @if ($item['history']->isEmpty())
                        <p class="py-6 text-center text-sm text-slate-400">No stock movements recorded yet.</p>
                    @else
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-slate-500">Date</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-500">Type</th>
                                    <th class="px-3 py-2 text-right font-medium text-slate-500">Qty</th>
                                    <th class="px-3 py-2 text-right font-medium text-slate-500">Unit cost</th>
                                    <th class="px-3 py-2 text-right font-medium text-slate-500">Balance</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-500">By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($item['history'] as $movement)
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap text-slate-500">{{ $movement->created_at->format('M d, Y g:i A') }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $movement->typeLabel() }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums {{ (int) $movement->quantity < 0 ? 'text-red-600' : 'text-brand-700' }}">
                                            {{ $movement->signedQuantity() }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-slate-500">
                                            {{ $movement->unit_cost === null ? '—' : $peso($movement->unit_cost) }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ number_format((int) $movement->balance_after) }}</td>
                                        <td class="px-3 py-2 text-slate-500">{{ $movement->user?->name ?? 'System' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endif
