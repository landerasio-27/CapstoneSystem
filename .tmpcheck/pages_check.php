<?php

// What did the paginator estimate for each page it cut, and how much room did
// it think the page had? Read straight off the markup the app printed, so the
// numbers are the ones the view worked with.
require __DIR__.'/../vendor/autoload.php';

use App\Support\SheetPages;

$html = file_get_contents(__DIR__.'/jan_All.html');
$segments = preg_split('/<div class="doc doc-sheet/', $html);

foreach (['screen' => SheetPages::forScreen(), 'export' => SheetPages::forExport()] as $name => $pager) {
    echo $name.' profile: room '.round($pager->roomMm(), 1)."mm\n";
}

$pager = SheetPages::forScreen();

foreach ($segments as $i => $segment) {
    if ($i === 0) {
        continue;
    }

    preg_match_all('/<tr class="doc-noline">(.*?)<\/tr>/s', $segment, $rows, PREG_SET_ORDER);

    $total = 0.0;
    $lines = 0;

    foreach ($rows as $row) {
        $cell = $row[1];
        preg_match('/<div class="doc-name">(.*?)<\/div>/s', $cell, $name);
        preg_match('/<div class="doc-desc">(.*?)<\/div>/s', $cell, $desc);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $cell, $cells);

        $endUser = isset($cells[1]) ? trim(strip_tags($cells[1][1] ?? '')) : '';
        $description = str_replace(['<br />', '<br>'], "\n", $desc[1] ?? '');

        $height = $pager->risRowMm([
            'item' => trim(strip_tags($name[1] ?? '')),
            'description' => trim(strip_tags($description)),
            'end_user' => $endUser,
        ]);

        $total += $height;
        $lines += (int) round(($height - 1.06) / 4.3);
    }

    if ($total > 0) {
        printf(
            "  sheet %d: rows %d, lines %d, estimated %.1fmm of %.1fmm\n",
            $i,
            count($rows),
            $lines,
            $total,
            $pager->roomMm()
        );
    }
}
