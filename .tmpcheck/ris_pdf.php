<?php

/**
 * Throwaway harness: renders the RIS items list through the same dompdf the
 * PDF export uses — Calibri registered with @font-face, the print stylesheet —
 * and writes .tmpcheck/ris.pdf so the document-number column can be measured on
 * the real page instead of trusting font-metric guesswork.
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

// The @font-face block the PDF view registers, so dompdf sets the same face the
// browser does rather than falling back to its own.
$faces = '';
foreach ([
    ['normal', 'normal', 'calibri.ttf'],
    ['normal', 'bold', 'calibrib.ttf'],
] as [$style, $weight, $file]) {
    $path = str_replace('\\', '/', storage_path('fonts/' . $file));
    $faces .= "@font-face { font-family: Calibri; font-style: {$style}; font-weight: {$weight}; "
        . "src: url(\"{$path}\") format(\"truetype\"); }\n";
}

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>{$faces}</style>
{$styles}
</head>
<body>
<div class="doc">
{$items}
{$recap}
</div>
</body>
</html>
HTML;

$pdf = app('dompdf.wrapper');
$pdf->loadHTML($html);
$pdf->setPaper('a4', 'portrait');
$pdf->save(__DIR__ . '/ris.pdf');

echo 'wrote ' . filesize(__DIR__ . '/ris.pdf') . " bytes\n";
