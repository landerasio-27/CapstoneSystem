<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $card->card_no }} — {{ $card->typeLabel() }}</title>
    @include('admin.cards._print_styles')
</head>
<body>
    {{-- Hidden when printing: this is the page chrome, not the document. --}}
    <div class="print-bar">
        <a href="{{ route('admin.cards.show', $card) }}">Back to card</a>
        <a href="{{ route('admin.cards.pdf', $card) }}">Download PDF</a>
        <button type="button" class="primary" onclick="window.print()">Print Card</button>
    </div>

    <div class="paper">
        @include('admin.cards._sheet', [
            'card' => $card,
            'lines' => $card->transactions()->get(),
            'daysToConsume' => $daysToConsume,
            'padTo' => 22,
        ])
    </div>
</body>
</html>
