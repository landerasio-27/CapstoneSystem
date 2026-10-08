<?php

/**
 * Throwaway probe: does dompdf repeat a `position: fixed` element on every page
 * (the only way a footer block can sit at the foot of each page), and where does
 * it land relative to the page's margin box?
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$styles = view('admin.reports.documents._styles')->render();

$rows = '';
for ($i = 1; $i <= 90; $i++) {
    $rows .= '<tr><td style="height:6mm;">Line '.$i.'</td><td>Something issued</td><td class="doc-c">'.$i.'</td></tr>';
}

$html = <<<HTML
<!doctype html>
<html><head><meta charset="utf-8">{$styles}
<style>
    @page { size: A4 portrait; margin: 10mm 9mm 70mm 9mm; }
    body { margin: 0; }
    .fixed-foot {
        position: fixed;
        bottom: 10mm;
        left: 0;
        right: 0;
        border: 1px solid #000;
        padding: 2mm;
        text-align: center;
    }
</style>
</head><body>
<div class="doc">
    <div class="fixed-foot">FIXED-FOOTER-REPEATED</div>
    <table class="doc-grid doc-fixed no-top" style="width:100%; border-collapse: collapse;">
        <thead>
            <tr><td colspan="3" style="padding:0; border:none;">HEADER-REPEATED</td></tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>
</div>
</body></html>
HTML;

$pdf = Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
file_put_contents(__DIR__.'/repeat2.pdf', $pdf->output());

echo "rendered repeat2.pdf\n";
