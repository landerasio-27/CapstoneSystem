<?php

// What line box does dompdf give a 13px sheet? The sheet asks for
// line-height 1.25 (16.25px); the rendered PDF pitches a wrapped line at
// 5.86mm (22.2px). This probe renders the same cell under a unitless
// line-height and under an explicit one, so the model the pagination is built
// on can be measured rather than guessed.
require __DIR__.'/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->setIsRemoteEnabled(false);
$options->setDefaultFont('DejaVu Sans');

$cells = '';
foreach (['1.25' => 'unitless', '16.25px' => 'explicit'] as $value => $label) {
    for ($lines = 1; $lines <= 3; $lines++) {
        $text = implode('<br>', array_fill(0, $lines, 'Epson ink'));
        $cells .= '<tr><td style="width:36%;line-height:'.$value.';">'.$text.'</td>'
            .'<td class="n" style="width:4%;">'.$label.' '.$lines.'</td></tr>';
    }
}

$html = '<html><head><style>'
    .'@page { size: A4 portrait; margin: 10mm 9mm 12mm 9mm; }'
    .'body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 13px; }'
    .'.doc { font-size: 13px; line-height: 1.25; }'
    .'table { width: 100%; border-collapse: collapse; }'
    .'td { padding: 2px 4px; border: 1px solid #000; vertical-align: top; }'
    .'</style></head><body><div class="doc"><table>'.$cells.'</table></div></body></html>';

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
file_put_contents(__DIR__.'/pitch.pdf', $dompdf->output());

echo "wrote .tmpcheck/pitch.pdf\n";
