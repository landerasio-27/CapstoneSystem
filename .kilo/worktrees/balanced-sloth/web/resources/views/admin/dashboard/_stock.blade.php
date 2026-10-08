{{-- Stock level analytics + status breakdown. Suppliers are compared by current quantity on hand. --}}
@php
    $bars = collect($stockLevels['items'])->map(fn (array $item) => [
        'label' => $item['name'],
        'value' => $item['quantity'],
        'unit' => ' ' . $item['unit'],
        'tone' => $item['status_tone'],
        'href' => route('admin.supplies.show', $item['id']),
        'meta' => ($item['reorder_level'] > 0 ? 'Reorder at ' . $item['reorder_level'] : 'No reorder point set')
            . ' · stock value ' . $peso($item['value']),
    ]);

    $segments = [
        ['label' => 'Healthy', 'count' => $statusCounts['healthy'], 'pct' => $statusCounts['healthy_pct'], 'bar' => 'bg-brand-600', 'dot' => 'bg-brand-600'],
        ['label' => 'Low', 'count' => $statusCounts['low'], 'pct' => $statusCounts['low_pct'], 'bar' => 'bg-amber-500', 'dot' => 'bg-amber-500'],
        ['label' => 'Critical', 'count' => $statusCounts['critical'], 'pct' => $statusCounts['critical_pct'], 'bar' => 'bg-red-500', 'dot' => 'bg-red-500'],
    ];
@endphp

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm xl:col-span-2">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <h3 class="text-base font-semibold text-slate-800">Stock Level Analytics</h3>
                <p class="text-xs text-slate-400">Current quantity on hand per supply, largest first.</p>
            </div>
            @if ($stockLevels['hidden'] > 0)
                <span class="text-xs text-slate-400">
                    Showing {{ count($stockLevels['items']) }} of {{ $stockLevels['total'] }}
                </span>
            @endif
        </div>

        <x-chart.bars :items="$bars" empty="No supplies recorded yet." />

        @if (count($stockLevels['items']) > 0)
            <p class="mt-3 text-xs text-slate-400">
                Bars are scaled to the largest stock on hand
                ({{ number_format(max(array_column($stockLevels['items'], 'quantity'))) }}
                {{ $stockLevels['items'][0]['unit'] ?? '' }}).
            </p>
        @endif
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-800">Stock Status</h3>
        <p class="text-xs text-slate-400">{{ $statusCounts['total'] }} supplies measured against their reorder point.</p>

        @if ($statusCounts['total'] > 0)
            <div class="chart-track mt-4 flex h-2.5 w-full overflow-hidden rounded-full">
                @foreach ($segments as $segment)
                    @if ($segment['pct'] > 0)
                        <div class="{{ $segment['bar'] }} h-full" style="width: {{ $segment['pct'] }}%"></div>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="mt-4 space-y-3">
            @foreach ($segments as $segment)
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="flex items-center gap-2 text-slate-600">
                        <span class="h-2.5 w-2.5 rounded-full {{ $segment['dot'] }}"></span>
                        {{ $segment['label'] }}
                    </span>
                    <span class="tabular-nums text-slate-500">
                        <b class="font-semibold text-slate-800">{{ $segment['count'] }}</b>
                        <span class="text-xs text-slate-400">{{ $segment['pct'] }}%</span>
                    </span>
                </div>
            @endforeach
        </div>

        <p class="mt-4 border-t border-slate-100 pt-3 text-[11px] leading-relaxed text-slate-400">
            Critical: empty, or at half the reorder point or less. Low: at or below the
            item's reorder point. Healthy: above it. Calculated from stored quantities —
            nothing is estimated.
        </p>
    </div>
</div>

<div class="mt-4 rounded-2xl border border-slate-100 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
        <div>
            <h3 class="text-base font-semibold text-slate-800">Supply Stock Status</h3>
            <p class="text-xs text-slate-400">Quantity, unit and status for every supply on record.</p>
        </div>
        <a href="{{ route('admin.supplies.index') }}" class="text-sm font-medium text-brand-700 transition hover:text-brand-800">View supplies</a>
    </div>

    @if ($supplyStatus->isEmpty())
        <p class="px-5 py-8 text-center text-sm text-slate-400">No supplies recorded yet.</p>
    @else
        <div class="max-h-80 overflow-y-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="sticky top-0 bg-slate-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium text-slate-500">Item</th>
                        <th class="px-4 py-2.5 text-right font-medium text-slate-500">Quantity</th>
                        <th class="px-4 py-2.5 text-left font-medium text-slate-500">Unit</th>
                        <th class="px-4 py-2.5 text-right font-medium text-slate-500">Reorder point</th>
                        <th class="px-4 py-2.5 text-right font-medium text-slate-500">Stock value</th>
                        <th class="px-4 py-2.5 text-left font-medium text-slate-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($supplyStatus as $row)
                        <tr>
                            <td class="px-4 py-2.5 font-medium">
                                <a href="{{ route('admin.supplies.show', $row['id']) }}" class="text-brand-700 hover:underline">{{ $row['name'] }}</a>
                                <span class="block text-xs text-slate-400">{{ $row['item_code'] }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-700">{{ number_format($row['quantity']) }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $row['unit'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums {{ $row['status'] === 'healthy' ? 'text-slate-500' : 'font-medium text-slate-700' }}">
                                {{ $row['reorder_level'] > 0 ? number_format($row['reorder_level']) : '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-500">{{ $peso($row['value']) }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badges[$row['status_tone']] }}">
                                    {{ $row['status_label'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
