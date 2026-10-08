<?php

/**
 * Throwaway harness: renders the RIS items list at its true sheet width and
 * reports how wide the document number is, in the browser's font and in the
 * font dompdf embeds for the PDF — so the first column can be sized against
 * the widest thing it has to hold instead of a guess.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = [
    [
        'ris_no' => 'RIS-2026-00002', 'end_user' => 'Cristina Fernandez',
        'item' => 'Bond Paper A4', 'description' => 'sub. 20 (500 sheets/ream)',
        'unit' => 'ream', 'quantity' => 2, 'unit_cost' => null, 'amount' => null,
    ],
    [
        'ris_no' => 'RIS-2026-00003', 'end_user' => 'Ricardo Bautista',
        'item' => 'Ballpen Black', 'description' => null,
        'unit' => 'pc', 'quantity' => 10, 'unit_cost' => null, 'amount' => null,
    ],
    [
        'ris_no' => 'RIS-2026-00004', 'end_user' => 'Amado Fernandez',
        'item' => 'Epson Ink 003', 'description' => null,
        'unit' => 'bottle', 'quantity' => 3, 'unit_cost' => null, 'amount' => null,
    ],
];

$items = view('admin.reports.documents._ris_items', [
    'rows' => $rows, 'blankRows' => 0, 'showCosts' => false,
])->render();

$recap = view('admin.reports.documents._ris_recap', [
    'recap' => [], 'showCosts' => false, 'showStock' => false, 'showTotal' => false,
])->render();

$styles = view('admin.reports.documents._styles')->render();

// What dompdf will measure, since it embeds its own face for the PDF.
$numbers = [
    'RIS-2026-00002', 'RIS-2026-00002-B', 'ICS-2026-00002',
    'RIS NO.', 'ICS NO.', 'STOCK NO:', 'End-User',
];
$metrics = [];

try {
    $dompdf = new \Dompdf\Dompdf();
    $fontMetrics = $dompdf->getFontMetrics();

    foreach ($numbers as $number) {
        foreach (['dejavu sans', 'calibri', 'carlito', 'arial'] as $face) {
            try {
                // dompdf works in points: 13 CSS px is 9.75pt.
                $pt = $fontMetrics->getTextWidth($number, $face, 9.75);
                $metrics[$number][$face] = round($pt * 0.352778, 2);
            } catch (\Throwable $e) {
                $metrics[$number][$face] = 'n/a';
            }
        }
    }
} catch (\Throwable $e) {
    $metrics['error'] = $e->getMessage();
}

$payload = json_encode(['metrics' => $metrics], JSON_PRETTY_PRINT);

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>RIS items — document number column</title>
{$styles}
<style>
    html, body { margin: 0; padding: 0; background: #fff; }
    /* The sheet as the print rule leaves it: A4 portrait, 9mm each side. */
    .sheet { width: 192mm; }
</style>
</head>
<body>
<div class="doc sheet">{$items}{$recap}</div>
<pre id="metrics">{$payload}</pre>
</body>
</html>
HTML;

file_put_contents(__DIR__ . '/ris_items.html', $html);

echo $payload, "\n";
echo "wrote " . strlen($html) . " bytes\n";
