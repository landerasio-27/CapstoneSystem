<?php

// The sheet's rows in dompdf are pitched at 22.15px a line where the stylesheet
// asks for 16.25px, which is why pages cut for the exporter leave the browser
// with a lot of room at their foot. This probe renders the same two-line cell
// three ways — as bare text in the cell, as text inside a block, and as two
// blocks under one another, the way the item cell carries its name and
// description — to see which of them the exporter gives the stylesheet's line.
require __DIR__.'/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->setIsRemoteEnabled(false);

$shapes = [
    'bare text' => 'FIRST LINE<br>SECOND LINE',
    'one block' => '<div>FIRST LINE<br>SECOND LINE</div>',
    'two blocks' => '<div>FIRST LINE</div><div>SECOND LINE</div>',
    'two blocks lh' => '<div style="line-height:16.25px;">FIRST LINE</div><div style="line-height:16.25px;">SECOND LINE</div>',
];

$rows = '';
$i = 0;
foreach ($shapes as $label => $content) {
    foreach ([1, 2] as $lines) {
        $text = $lines === 1 ? 'FIRST LINE' : $content;
        $rows .= '<tr><td style="width:60%;">'.$text.'</td><td style="width:40%;">'.$label.'</td></tr>';
    }
    $i++;
}

$html = '<html><head><style>'
    .'@page { size: A4 portrait; margin: 10mm 9mm 12mm 9mm; }'
    .'body { margin: 0; }'
    .'table { width: 100%; border-collapse: collapse; }'
    .'td { padding: 2px 4px; border: 1px solid #000; vertical-align: top; }'
    .'.doc { font-family: "DejaVu Sans", sans-serif; font-size: 13px; line-height: 1.25; }'
    .'</style></head><body><div class="doc"><table>'.$rows.'</table></div></body></html>';

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
file_put_contents(__DIR__.'/pitch3.pdf', $dompdf->output());
echo "wrote pitch3.pdf\n";
