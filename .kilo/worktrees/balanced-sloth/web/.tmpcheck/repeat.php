<?php

/**
 * Throwaway probe: does dompdf repeat a thead that holds a nested table (the
 * form's header block) and a tfoot (the certification) on every page, with the
 * body's rows flowing between them?
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$styles = view('admin.reports.documents._styles')->render();

$rows = '';
for ($i = 1; $i <= 90; $i++) {
    $rows .= '<tr><td>Line '.$i.'</td><td>Something issued</td><td>piece</td><td class="doc-c">'.$i.'</td></tr>';
}

$html = <<<HTML
<!doctype html>
<html><head><meta charset="utf-8">{$styles}
<style>
    @page { size: A4 portrait; margin: 10mm 9mm 12mm 9mm; }
    body { margin: 0; }
</style>
</head><body>
<div class="doc">
<table class="doc-grid doc-fixed no-top" style="width:100%; border-collapse: collapse;">
    <thead>
        <tr><td colspan="4" style="padding:0; border:none;">
            <table class="doc-grid" style="width:100%;">
                <tr><td class="doc-c" style="font-weight:bold;">HEADER-BLOCK-REPEATED</td></tr>
            </table>
        </td></tr>
        <tr>
            <th style="width:40%;">Item</th>
            <th style="width:30%;">Description</th>
            <th style="width:15%;">Unit</th>
            <th style="width:15%;">Qty</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
    <tfoot>
        <tr><td colspan="4" style="padding:0; border:none;">
            <table class="doc-grid doc-cert" style="width:100%;">
                <tr><td class="doc-c">FOOTER-BLOCK-REPEATED</td></tr>
            </table>
        </td></tr>
    </tfoot>
</table>
</div>
</body></html>
HTML;

$pdf = Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
file_put_contents(__DIR__.'/repeat.pdf', $pdf->output());

echo "rendered repeat.pdf\n";
