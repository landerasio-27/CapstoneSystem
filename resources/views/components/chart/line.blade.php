{{--
    Line chart rendered as inline SVG on the server (no chart library, so it
    works offline and every value comes from the database).

    Each point: ['label' => 'Sep 2026', 'short' => 'Sep', 'value' => float|null]

    Months without records leave a gap in the line with a small marker on the
    axis: the line is never interpolated across data that does not exist.
--}}
@props([
    'points' => [],
    'empty' => 'No data available.',
    'decimals' => 2,
    'valuePrefix' => '',
    'valueSuffix' => '',
    'note' => null,
])

@php
    $points = collect($points)->values();

    // The plot keeps a fixed height and the frame grows around it, because the
    // month names are turned 45° (see below) and the band that needs cannot be
    // crammed into the space a flat caption used to take.
    $width = 720;
    $padLeft = 68;
    $padRight = 20;
    $padTop = 22;
    $plotHeight = 192;
    $count = $points->count();
    $showLabelEvery = $count > 12 ? 2 : 1;
    $useShortLabels = $count > 6;

    $numeric = $points->filter(fn ($point) => isset($point['value']) && is_numeric($point['value']));
    $hasData = $numeric->isNotEmpty();

    $min = $hasData ? (float) $numeric->min('value') : 0.0;
    $max = $hasData ? (float) $numeric->max('value') : 0.0;

    if ($hasData && $max - $min < 0.0001) {
        $spread = abs($max) > 0 ? abs($max) * 0.15 : 1.0;
        $min -= $spread;
        $max += $spread;
    } elseif ($hasData) {
        $spread = ($max - $min) * 0.18;
        $min = max(0, $min - $spread);
        $max += $spread;
    }

    $span = max($max - $min, 0.0001);

    $format = fn (float $value) => $valuePrefix . number_format($value, $decimals) . $valueSuffix;

    // Labels are drawn in viewBox units and an SVG clips whatever falls outside
    // its frame, so each gutter is sized against the widest string it has to
    // print. Without this the value axis loses the head of its prices against the
    // left edge of the card. Widths are estimated from the glyphs, in ems, at the
    // largest size the stylesheet gives a chart label, which is the tightest
    // case, and the same estimate measures the room a turned month name needs.
    $labelSize = 24.0;
    $advance = ['₱' => 0.72, ' ' => 0.32, ',' => 0.32, '.' => 0.32, '-' => 0.38, '%' => 0.64, '—' => 0.95];
    $textWidth = function (string $text, float $bold = 1.06) use ($labelSize, $advance): float {
        $width = 0.0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $character) {
            $width += ($advance[$character] ?? 0.60) * $labelSize * $bold;
        }

        return $width;
    };

    $ticks = array_map(fn (int $tick) => $format($min + $span * ($tick / 4)), range(0, 4));
    $padLeft = max($padLeft, (int) ceil(max(array_map(fn (string $tick) => $textWidth($tick, 1.0), $ticks))) + 12);

    $monthLabels = $points->map(
        fn (array $point, int $i) => $useShortLabels && $i > 0 ? ($point['short'] ?? $point['label']) : $point['label'],
    )->all();

    // Month names are not written flat under the axis: at the sizes this chart
    // reaches on a wide screen two neighbouring names collide ("May 2026Jun
    // 2026"). Every name is turned 45° instead — like a "/" — so it leans away
    // from the month beside it and no two can ever touch, whatever the range.
    // A name then reaches sideways and downwards from its tick by cos(45°) of
    // its own width, plus its glyph height across the turn, so the bottom band
    // and the left gutter are both measured from the widest name that is drawn.
    $printedLabels = [];

    foreach ($points as $i => $point) {
        if ($i % $showLabelEvery === 0 || $i === $count - 1) {
            $printedLabels[] = (string) $monthLabels[$i];
        }
    }

    $turned = 0.70711;
    $glyphAscent = 0.80 * $labelSize;
    $glyphDescent = 0.25 * $labelSize;
    $widestMonth = $printedLabels === []
        ? 0.0
        : max(array_map(fn (string $label) => $textWidth($label, 1.0), $printedLabels));

    // The axis band: air between the axis line and the name, the drop of the
    // longest name, and a little room under the last line of type.
    $axisLabelGap = 16;
    $padBottom = (int) ceil($axisLabelGap + $turned * ($widestMonth + $glyphDescent)) + 6;
    $height = $padTop + $plotHeight + $padBottom;

    // A turned name leans back to the left of its tick, so the first one needs
    // the room the leftmost tick used to be given.
    $padLeft = max($padLeft, (int) ceil($turned * ($widestMonth + $glyphAscent)) + 6);

    $plotWidth = $width - $padLeft - $padRight;

    // Where every turned month name hangs from: a little under the axis line.
    $monthLabelY = $padTop + $plotHeight + $axisLabelGap;

    $xAt = fn (int $i) => $count <= 1
        ? $padLeft + $plotWidth / 2
        : $padLeft + $i * ($plotWidth / ($count - 1));

    $yAt = fn (float $value) => $padTop + $plotHeight - (($value - $min) / $span) * $plotHeight;

    // Consecutive months with records form one segment; gaps break the line.
    $segments = [];
    $segment = [];

    foreach ($points as $i => $point) {
        $value = $point['value'] ?? null;

        if (! is_numeric($value)) {
            if ($segment !== []) {
                $segments[] = $segment;
                $segment = [];
            }

            continue;
        }

        $segment[] = [
            'x' => round($xAt($i), 2),
            'y' => round($yAt((float) $value), 2),
            'value' => (float) $value,
        ];
    }

    if ($segment !== []) {
        $segments[] = $segment;
    }

    // Pre-build the SVG path of every run of consecutive months with records.
    $segmentPaths = array_map(
        fn (array $linePoints) => count($linePoints) > 1
            ? 'M ' . implode(' L ', array_map(fn (array $point) => $point['x'] . ' ' . $point['y'], $linePoints))
            : null,
        $segments,
    );

    // The outermost prices hang inwards from their dot, so the first and the last
    // amount on the line stay inside the frame however long they are.
    $firstSegment = $segments[0] ?? [];
    $lastSegment = $segments === [] ? [] : $segments[array_key_last($segments)];
    $firstDataX = $firstSegment === [] ? null : $firstSegment[0]['x'];
    $lastDataX = $lastSegment === [] ? null : $lastSegment[array_key_last($lastSegment)]['x'];
@endphp

@if (! $hasData)
    <div class="flex h-48 items-center justify-center rounded-xl border border-dashed border-slate-200 px-4 text-center text-sm text-slate-400">
        {{ $empty }}
    </div>
@else
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-auto w-full" role="img"
         aria-label="Line chart of {{ $points->pluck('label')->implode(', ') }}">
        {{-- Horizontal grid + value axis --}}
        @foreach (range(0, 4) as $tick)
            @php
                $tickValue = $min + $span * ($tick / 4);
                $tickY = round($yAt($tickValue), 2);
            @endphp
            <line x1="{{ $padLeft }}" y1="{{ $tickY }}" x2="{{ $width - $padRight }}" y2="{{ $tickY }}" class="chart-grid" vector-effect="non-scaling-stroke" />
            <text x="{{ $padLeft - 10 }}" y="{{ $tickY + 4 }}" text-anchor="end" class="chart-axis-label">{{ $format($tickValue) }}</text>
        @endforeach

        <line x1="{{ $padLeft }}" y1="{{ $padTop + $plotHeight }}" x2="{{ $width - $padRight }}" y2="{{ $padTop + $plotHeight }}" class="chart-axis" vector-effect="non-scaling-stroke" />

        {{-- Month axis: the first tick keeps the year, later ticks stay short, and
             every name is turned so neighbouring months cannot collide. --}}
        @foreach ($points as $i => $point)
            @php
                $x = round($xAt($i), 2);
                $label = $useShortLabels ? ($point['short'] ?? $point['label']) : $point['label'];
                if ($useShortLabels && $i === 0) {
                    $label = $point['label'];
                }
            @endphp
            <line x1="{{ $x }}" y1="{{ $padTop + $plotHeight }}" x2="{{ $x }}" y2="{{ $padTop + $plotHeight + 4 }}" class="chart-axis" vector-effect="non-scaling-stroke" />
            @if ($point['value'] === null)
                {{-- A month with no records is marked on the axis, not on the line.
                     It sits close to the axis line so it belongs to the tick
                     rather than to the turned name hanging under it. --}}
                <circle cx="{{ $x }}" cy="{{ $padTop + $plotHeight + 7 }}" r="2.5" class="chart-gap" />
            @endif
            @if ($i % $showLabelEvery === 0 || $i === $count - 1)
                {{-- Turned 45° about its own tick: the name reads upwards like a
                     "/", ending at the tick, so the month to its right is never
                     underneath it. The band and the gutter were both measured for
                     the widest name, so the turn cannot run out of the frame. --}}
                <text x="{{ $x }}" y="{{ $monthLabelY }}" text-anchor="end"
                      transform="rotate(-45 {{ $x }} {{ $monthLabelY }})"
                      class="chart-axis-label {{ $point['value'] === null ? 'chart-axis-label-muted' : '' }}">{{ $label }}</text>
            @endif
        @endforeach

        {{-- The line, broken wherever a month has no records --}}
        @foreach ($segments as $segmentIndex => $linePoints)
            @if ($segmentPaths[$segmentIndex])
                <path class="chart-line" d="{{ $segmentPaths[$segmentIndex] }}" vector-effect="non-scaling-stroke" />
            @endif

            @foreach ($linePoints as $linePoint)
                <circle cx="{{ $linePoint['x'] }}" cy="{{ $linePoint['y'] }}" r="4.5" class="chart-point" />
                @if ($count <= 12)
                    @php
                        $anchor = 'middle';

                        if ($count > 1 && $linePoint['x'] >= $lastDataX) {
                            $anchor = 'end';
                        } elseif ($count > 1 && $linePoint['x'] <= $firstDataX) {
                            $anchor = 'start';
                        }
                    @endphp
                    <text x="{{ $linePoint['x'] }}" y="{{ $linePoint['y'] - 13 }}" text-anchor="{{ $anchor }}" class="chart-value-label">
                        {{ $format($linePoint['value']) }}
                    </text>
                @endif
            @endforeach
        @endforeach
    </svg>

    @if ($note)
        <p class="mt-1 text-xs text-slate-400">{{ $note }}</p>
    @endif
@endif
