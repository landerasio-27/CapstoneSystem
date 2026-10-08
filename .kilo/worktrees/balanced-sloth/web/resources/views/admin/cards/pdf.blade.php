<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $card->card_no }} — {{ $card->typeLabel() }}</title>
    @include('admin.cards._print_styles')
</head>
<body>
    @include('admin.cards._sheet', [
        'card' => $card,
        'lines' => $card->transactions()->get(),
        'daysToConsume' => $daysToConsume,
        'padTo' => 22,
    ])

    {{-- dompdf page footer: page number of total, on every page. --}}
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(500, 812, "Page {PAGE_NUM} of {PAGE_COUNT}", null, 8, [0, 0, 0]);
        }
    </script>
</body>
</html>
