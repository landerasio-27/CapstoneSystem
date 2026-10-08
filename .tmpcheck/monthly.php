<?php

/**
 * Throwaway harness: renders the monthly report's RIS and ICS sheets from a
 * hand-built payload (no database), so the blank lines at the foot of each can
 * be seen and counted.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$line = fn (string $no, string $user, string $item, string $unit, int $qty, float $cost, string $stock) => [
    'ris_no' => $no,
    'end_user' => $user,
    'item' => $item,
    'description' => null,
    'unit' => $unit,
    'quantity' => $qty,
    'unit_cost' => $cost,
    'amount' => round($qty * $cost, 2),
    'stock_no' => $stock,
];

$risRows = [
    $line('RIS-2026-00002', 'Cristina Fernandez', 'Bond Paper A4', 'ream', 2, 245.00, 'SUP-00001'),
    $line('RIS-2026-00002', 'Cristina Fernandez', 'Bond Paper Legal', 'ream', 1, 265.00, 'SUP-00004'),
    $line('RIS-2026-00003', 'Ricardo Bautista', 'Ballpen Black', 'pc', 10, 8.00, 'SUP-00002'),
    $line('RIS-2026-00004', 'Amado Fernandez', 'Epson Ink 003', 'bottle', 3, 320.00, 'SUP-00003'),
];

$icsRows = [
    $line('ICS-2026-00002', 'Maria Santos', 'Desktop Computer', 'unit', 1, 35000.00, 'EQP-00001'),
    $line('ICS-2026-00003', 'Jose Cruz', 'Epson LCD Projector', 'unit', 1, 28000.00, 'EQP-00002'),
];

$recapOf = function (array $rows): array {
    $out = [];

    foreach ($rows as $row) {
        $out[$row['stock_no']] = [
            'stock_no' => $row['stock_no'],
            'quantity' => $row['quantity'],
            'unit_cost' => $row['unit_cost'],
            'total_cost' => $row['amount'],
            'amount' => $row['amount'],
        ];
    }

    return array_values($out);
};

$meta = fn (string $number, string $period) => [
    'agency' => 'Isabela State University - Cauayan Campus',
    'period' => $period,
    'number' => $number,
    'posted_by' => 'Juan Dela Cruz',
    'posted_position' => 'Administrative Officer V',
];

$sheets = [
    [
        'cover' => null,
        'kind' => 'RIS',
        'meta' => $meta('2026-08-01', 'For the month of August 2026'),
        'rows' => $risRows,
        'blank_rows' => 0,
        'recap' => $recapOf($risRows),
        'total' => array_sum(array_column($risRows, 'amount')),
    ],
    [
        'cover' => null,
        'kind' => 'ICS',
        'meta' => $meta('2026-08-02', 'For the month of August 2026'),
        'rows' => $icsRows,
        'blank_rows' => 0,
        'recap' => $recapOf($icsRows),
        'total' => array_sum(array_column($icsRows, 'amount')),
    ],
];

// The stock number and quantity are the report's own record and print unless
// held back; the money columns belong to the accounting unit and stay blank.
$body = view('admin.reports.documents._monthly', [
    'sheets' => $sheets,
    'showCosts' => (bool) ($argv[1] ?? false),
    'showStock' => ! ($argv[2] ?? false),
    'showTotal' => true,
])->render();

$styles = view('admin.reports.documents._styles')->render();

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Monthly report — RIS and ICS sheets</title>
{$styles}
<style>html, body { margin: 0; background: #eef2f7; } .doc-paper { width: 192mm; margin: 0 auto; background: #fff; }</style>
</head>
<body>
<div class="doc-paper">{$body}</div>
</body>
</html>
HTML;

file_put_contents(__DIR__ . '/monthly.html', $html);

echo 'rendered ' . strlen($html) . " bytes\n";
echo 'doc-blank rows: ' . substr_count($body, 'doc-blank') . "\n";
echo 'doc-sheet blocks: ' . substr_count($body, 'doc-sheet') . "\n";
