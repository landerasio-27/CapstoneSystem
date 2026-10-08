<?php

/* Scratch: how the monthly report of January 2026 is cut into pages, for the
   browser profile and the exporter's, with the room each page really uses. */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\ReportController;
use App\Support\SheetPages;
use Illuminate\Http\Request;

$controller = app(ReportController::class);
$payload = new ReflectionMethod($controller, 'monthlyPayload');
$payload->setAccessible(true);

foreach ([['screen', false], ['export', true]] as [$label, $export]) {
    $request = Request::create('/admin/reports/monthly/pdf', 'GET', [
        'month' => (int) (getenv('M') ?: 1),
        'year' => 2026,
        'document_type' => 'All',
    ]);

    $data = $payload->invoke($controller, $request, true, $export);
    $pager = SheetPages::for($export);
    $tail = $pager->tailMm(SheetPages::recapLines($data['sheets'][0]['recap'], $data['showStock'], $data['showCosts'], $data['showTotal']));

    echo "\n=== {$label}: room {$pager->roomMm()}mm, tail {$tail}mm, showTotal=".var_export($data['showTotal'], true)." ===\n";

    foreach ($data['sheets'] as $index => $sheet) {
        $heights = array_map(fn (array $row) => $pager->risRowMm($row), $sheet['rows']);
        $name = $sheet['cover']['name'] ?? 'report';

        if ($index === 0) {
            // The list as the sheet draws it: the measure the cut is made with.
            $shrunk = [];
            $previous = null;

            foreach ($sheet['rows'] as $n => $row) {
                $continues = $previous !== null
                    && ($row['ris_no'] ?? '') === ($previous['ris_no'] ?? '')
                    && ($row['end_user'] ?? '') === ($previous['end_user'] ?? '');
                $shrunk[] = $pager->risRowMm($row, $continues);
                $previous = $row;

                printf(
                    "  row %2d: %5.1fmm (was %5.1f)  %-18s %-28s\n",
                    $n,
                    end($shrunk),
                    $heights[$n],
                    var_export($row['end_user'] ?? '', true),
                    var_export($row['item'] ?? '', true),
                );
            }
        }

        // The cut's own measure: the first row of a page carries the number and
        // the end-user again, and a row carrying its paper's run on is shorter.
        $at = 0;
        $pages = [];
        foreach ($sheet['pages'] as $page) {
            $used = 0.0;
            foreach ($page['rows'] as $offset => $row) {
                $before = $sheet['rows'][$at + $offset - 1] ?? null;
                $continues = $offset > 0 && $before !== null
                    && ($row['ris_no'] ?? '') === ($before['ris_no'] ?? '')
                    && ($row['end_user'] ?? '') === ($before['end_user'] ?? '');
                $used += $pager->risRowMm($row, $continues);
            }
            $at += count($page['rows']);
            $pages[] = sprintf('%d rows / %.1fmm', count($page['rows']), $used);
        }

        printf(
            "sheet %d (%s): %d rows, %d page(s): %s\n",
            $index,
            $name,
            count($sheet['rows']),
            count($sheet['pages']),
            implode(' | ', $pages),
        );
    }
}
