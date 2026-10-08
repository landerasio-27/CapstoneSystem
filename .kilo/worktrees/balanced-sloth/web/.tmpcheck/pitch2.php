<?php

// dompdf pitches a wrapped line at ~22px for a 13px sheet whose stylesheet asks
// for line-height 1.25 (16.25px) — the browser pitches the same line at
// 16.25px. This probe renders the same cell under two document-root font sizes,
// to find out whether the line box dompdf gives a row comes from the root of
// the document rather than from the cell's own style — which would let the
// sheet set a root size and print the same in both engines.
require __DIR__.'/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->setIsRemoteEnabled(false);

$rows = '';
foreach (['1px' => 'root 16px (default)', '2px' => 'root 13px'] as $id => $label) {
    for ($lines = 1; $lines <= 3; $lines++) {
        $text = implode('<br>', array_fill(0, $lines, 'Epson ink'));
        $rows .= '<tr data-g="'.$id.'"><td style="width:70%;">'.$text.'</td>'
            .'<td style="width:30%;">'.$label.' '.$lines.'</td></tr>';
    }
}

$css = '<style>'
    .'@page { size: A4 portrait; margin: 10mm 9mm 12mm 9mm; }'
    .'body { margin: 0; }'
    .'table { width: 100%; border-collapse: collapse; }'
    .'td { padding: 2px 4px; border: 1px solid #000; vertical-align: top; }'
    .'.doc { font-family: "DejaVu Sans", sans-serif; font-size: 13px; line-height: 1.25; }'
    .'</style>';

foreach (['16px' => 'default', '13px' => 'root13'] as $root => $name) {
    $page = '<html><head>'.str_replace('.doc {', 'html { font-size: '.$root.'; } .doc {', $css)
        .'</head><body><div class="doc"><table>'.$rows.'</table></div></body></html>';
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($page);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    file_put_contents(__DIR__.'/pitch_'.$name.'.pdf', $dompdf->output());
    echo "wrote pitch_{$name}.pdf\n";
}
