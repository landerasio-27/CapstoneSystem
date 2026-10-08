<?php

/**
 * Throwaway harness: renders the dashboard's line charts with the exact props
 * the dashboard passes, wraps them in a page that inlines the chart CSS rules
 * from app.css, and writes .tmpcheck/charts.html so the result can be eyeballed
 * without a database.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$months = [
    ['label' => 'May 2026', 'short' => 'May'],
    ['label' => 'Jun 2026', 'short' => 'Jun'],
    ['label' => 'Jul 2026', 'short' => 'Jul'],
    ['label' => 'Aug 2026', 'short' => 'Aug'],
    ['label' => 'Sep 2026', 'short' => 'Sep'],
    ['label' => 'Oct 2026', 'short' => 'Oct'],
];

$costPoints = array_map(
    fn (array $month) => $month + ['value' => $month['label'] === 'Oct 2026' ? 600.0 : null],
    $months,
);

$unitCostPoints = array_map(
    fn (array $month) => $month + ['value' => $month['label'] === 'Oct 2026' ? 6.0 : null],
    $months,
);

$trendPoints = array_map(
    fn (array $month) => $month + ['value' => match ($month['label']) {
        'Jun 2026' => 5.25,
        'Jul 2026' => 5.90,
        'Aug 2026' => 6.40,
        'Sep 2026' => 6.10,
        'Oct 2026' => 6.0,
        default => 5.0,
    }],
    $months,
);

$coverageNote = '1 of 6 months in this range have purchase records.';

$costChart = view('components.chart.line', [
    'points' => $costPoints,
    'valuePrefix' => '₱',
    'decimals' => 0,
    'empty' => 'No purchase records in this period.',
    'note' => $coverageNote . ' Months without records are left blank on purpose.',
])->render();

$unitCostChart = view('components.chart.line', [
    'points' => $unitCostPoints,
    'valuePrefix' => '₱',
    'decimals' => 2,
    'empty' => 'No purchase records in this period.',
    'note' => 'Average of the unit costs recorded in each month.',
])->render();

$trendChart = view('components.chart.line', [
    'points' => $trendPoints,
    'valuePrefix' => '₱',
    'decimals' => 2,
    'empty' => 'No unit cost records in this period.',
    'note' => 'Unit cost recorded in each month.',
])->render();

// The densest case the dashboard can ask for: the 12-month range, where every
// month is labelled with its short name.
$yearMonths = [
    ['label' => 'Nov 2025', 'short' => 'Nov'],
    ['label' => 'Dec 2025', 'short' => 'Dec'],
    ['label' => 'Jan 2026', 'short' => 'Jan'],
    ['label' => 'Feb 2026', 'short' => 'Feb'],
    ['label' => 'Mar 2026', 'short' => 'Mar'],
    ['label' => 'Apr 2026', 'short' => 'Apr'],
    ['label' => 'May 2026', 'short' => 'May'],
    ['label' => 'Jun 2026', 'short' => 'Jun'],
    ['label' => 'Jul 2026', 'short' => 'Jul'],
    ['label' => 'Aug 2026', 'short' => 'Aug'],
    ['label' => 'Sep 2026', 'short' => 'Sep'],
    ['label' => 'Oct 2026', 'short' => 'Oct'],
];

$yearPoints = array_map(
    fn (array $month) => $month + ['value' => in_array($month['label'], ['May 2026', 'Jun 2026', 'Jul 2026', 'Aug 2026', 'Sep 2026', 'Oct 2026'], true) ? 400.0 + array_search($month['label'], array_column($yearMonths, 'label'), true) * 25 : null],
    $yearMonths,
);

$yearChart = view('components.chart.line', [
    'points' => $yearPoints,
    'valuePrefix' => '₱',
    'decimals' => 0,
    'empty' => 'No purchase records in this period.',
    'note' => 'The 12-month range, which labels every month.',
])->render();

// The chart CSS from app.css, inlined at the >=1280px media query values, which
// are the tightest case: the two compact charts print their labels at 22px and
// the wide one at 11px.
$css = <<<'CSS'
body { margin: 0; padding: 24px; background: #f8fafc; font-family: "Segoe UI", system-ui, sans-serif; }
.card { max-width: 1560px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; }
.card + .card { margin-top: 20px; }
.card h3 { margin: 0; font-size: 17px; color: #1e293b; }
.sub { margin: 4px 0 0; font-size: 13px; color: #94a3b8; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 18px; }
.grid h4 { margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #334155; }
.note { margin: 4px 0 0; font-size: 12px; color: #94a3b8; }
svg { height: auto; width: 100%; }
.chart-grid { stroke: #e2e8f0; stroke-width: 1px; }
.chart-axis { stroke: #cbd5e1; stroke-width: 1px; }
.chart-line { fill: none; stroke: #006400; stroke-width: 2.5px; stroke-linecap: round; stroke-linejoin: round; }
.chart-point { fill: #006400; stroke: #ffffff; stroke-width: 2; }
.chart-axis-label { fill: #64748b; font-size: 11px; }
.chart-axis-label-muted { fill: #cbd5e1; font-size: 11px; }
.chart-value-label { fill: #334155; font-size: 11px; font-weight: 600; }
.chart-compact .chart-axis-label, .chart-compact .chart-axis-label-muted, .chart-compact .chart-value-label { font-size: 22px; }
.chart-gap { fill: #cbd5e1; }
CSS;

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Dashboard line charts — turned month labels</title>
<style>{$css}</style>
</head>
<body>
<div class="card">
    <h3>Monthly Cost Analysis — Supplies</h3>
    <p class="sub">Last 6 Months (May 2026 – Oct 2026) · {$coverageNote}</p>
    <div class="grid">
        <div class="chart-compact">
            <h4>Monthly purchase cost</h4>
            {$costChart}
        </div>
        <div class="chart-compact">
            <h4>Monthly average unit cost</h4>
            {$unitCostChart}
        </div>
    </div>
</div>
<div class="card">
    <h3>Monthly purchase cost — 12 month range</h3>
    <p class="sub">The densest range the dashboard offers: all twelve short names are printed.</p>
    <div class="grid">
        <div class="chart-compact">
            {$yearChart}
        </div>
        <div class="chart-compact">
            {$yearChart}
        </div>
    </div>
</div>
<div class="card">
    <h3>Unit Cost Trend</h3>
    <p class="sub">The wide, full-width chart (labels at the 11px step).</p>
    {$trendChart}
</div>
</body>
</html>
HTML;

file_put_contents(__DIR__ . '/charts.html', $html);

echo "rendered " . strlen($html) . " bytes\n";
