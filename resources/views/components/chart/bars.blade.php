{{--
    Horizontal bar chart (CSS based, no chart library).

    Each item: ['label' => string, 'value' => number, 'unit' => ?string,
                'tone' => 'brand'|'amber'|'red'|'slate', 'meta' => ?string,
                'href' => ?string]

    Bars are scaled against the largest value in the list, which is stated in the
    footer so the reader knows what the longest bar means.
--}}
@props([
    'items' => [],
    'empty' => 'No data available.',
    'valuePrefix' => '',
    'decimals' => 0,
    'max' => null,
])

@php
    $items = collect($items)->filter(fn ($item) => is_array($item) && array_key_exists('value', $item))->values();
    $highest = $max ? (float) $max : max(1.0, (float) $items->max('value'));
    $tones = [
        'brand' => 'bg-brand-600',
        'amber' => 'bg-amber-500',
        'red' => 'bg-red-500',
        'slate' => 'bg-slate-400',
    ];
@endphp

@if ($items->isEmpty())
    <p class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">{{ $empty }}</p>
@else
    <div class="space-y-3">
        @foreach ($items as $item)
            @php
                $value = (float) $item['value'];
                $share = $highest > 0 ? $value / $highest * 100 : 0;
                $width = $value > 0 ? max($share, 2) : 0;
                $tone = $tones[$item['tone'] ?? 'brand'] ?? $tones['brand'];
            @endphp

            <div class="group">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="min-w-0 truncate text-sm text-slate-700">
                        @if (! empty($item['href']))
                            <a href="{{ $item['href'] }}" class="transition hover:text-brand-700">{{ $item['label'] }}</a>
                        @else
                            {{ $item['label'] }}
                        @endif
                    </span>
                    <span class="shrink-0 text-sm font-semibold tabular-nums text-slate-800">
                        {{ $valuePrefix }}{{ number_format($value, $decimals) }}{{ $item['unit'] ?? '' }}
                    </span>
                </div>
                <div class="chart-track mt-1.5 h-2 w-full overflow-hidden rounded-full">
                    <div class="h-full rounded-full {{ $tone }}" style="width: {{ round(min(100, $width), 2) }}%"></div>
                </div>
                @if (! empty($item['meta']))
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $item['meta'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
@endif
