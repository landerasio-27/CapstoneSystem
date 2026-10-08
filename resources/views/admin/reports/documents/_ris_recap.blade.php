{{--
    The recapitulation that closes the form: one line per stock number and unit
    cost, so a cost that changed mid-month shows as its own line rather than
    being averaged away. Qty and both money columns come from the lines above.

    The band does not run under the stock-number column: the block begins at the
    stock number, so that column is left as plain paper beside the block and the
    band starts beside it, the first run of the word standing over the stock and
    quantity columns alone. Nothing is ruled down the strip the list's number
    column leaves there — it keeps the width, so the block's headings stay over
    the columns the entries above were written on, but nothing is ruled across
    it: the row rules that would cut it into a column of empty little boxes are
    given up, and the rule the list parts its number column with is carried
    straight on down the strip, so the block stands closed on its left (see
    .doc-strip). One line of it is the sheet's, not the strip's: the rule that
    ends the block runs out to the sheet's left edge as the form draws it, so
    the corner the strip leaves is closed by it (see .doc-strip-end).

    Laid out the way the official sheet has it: the block stands on the columns
    the items above were written in, with the stock number under the end-user
    column, the quantity at the head of the description column and the two money
    columns under the headings that name them —

        |          | STOCK NO: |QNTY|      (open)      | UNIT COST |TOTAL COST| AMOUNT |
        0       14.5          26   36                  62          76.5       86      100

    Those shares are the RIS's own list read off it — the number column opened
    out to 14.5 for the fourteen characters printed in it, the end-user beside
    it giving up the width — and the block is handed them here because it is
    not the RIS's alone: a document whose list is ruled on different columns
    passes its own, so that the verticals of the two blocks run through as one
    line. The band is passed its spans for the same reason.

    Two of those columns are not ruled. The quantity heading sits at the left of
    the open middle and the form draws no line between them, and TOTAL COST and
    AMOUNT share one cell below their headings, so the money group is ruled only
    where it is written on. The row of headings keeps every line, so the figures
    sit in the columns the form names.

    The band above it carries the word twice, once over what was issued and once
    over what it cost: the first run covers the stock and quantity columns and
    the second heads the money group, set a little back from that group's centre
    the way the form prints it. Both stand a shade larger than the headings.

    Expects: $recap (empty on a blank form, which leaves the whole section for
    the accounting unit to fill in), $showCosts (off by default), which decides
    whether the money columns carry the report's own figures or stand blank for
    the pen, and $showStock (off by default too), which does the same for the
    stock number and the quantity. The whole of the block is the accounting
    unit's to fill in, and a report prints the figures it holds only when the
    office asks for them: every one of the three arrives unticked on the filter
    and is sent explicitly when it is wanted (see ReportController), so the
    sheet a report opens on is the blank form the office writes into.

    Either way the columns stand — heading, ruling and width are the form's,
    and it is only the figures that are withheld. Every column of it can be
    withheld at once: the run is then ruled as a single blank line rather than
    one empty row per entry — one empty row is as empty as the next, so a
    second line would only say that the report happened to hold two stock
    numbers. $showTotal (off by default, like the other two) decides whether the
    block closes with its TOTAL line: with everything above it withheld the line
    would have nothing left to say, so it stands only where it is asked for.

    A document that is its own record rather than a report — the ICS slip, which
    the custodian signs for what they hold — passes all three on, because the
    figures on it are the paper's own and are not the office's to ask for.

    Also $columns — the seven shares of the list above — and $band, the spans of
    the band's three runs after the stock-number column; both default to the
    RIS's.
--}}
@php
    $recap = $recap ?? [];
    $showCosts = (bool) ($showCosts ?? false);
    $showStock = (bool) ($showStock ?? false);
    $showTotal = (bool) ($showTotal ?? false);
    // Nothing at all is written into the block: the stock number and quantity
    // are withheld and the money columns are the accounting unit's to fill in.
    $blankRun = ! $showStock && ! $showCosts;
    // The shares each column takes, how the band's runs spread across them, and
    // the name of the grid a document passes its own shares under, so a rule
    // written for that grid can find the block.
    $columns = array_values($columns ?? [14.5, 11.5, 10, 26, 14.5, 9.5, 14]);
    $band = array_values($band ?? [3, 1, 2]);
    $grid = $grid ?? '';
    // A run that covers one column needs no span written on it.
    $span = fn ($run) => $run > 1 ? ' colspan="'.$run.'"' : '';
    // The strip beside the block carries the rule that closes the block's left
    // side and draws nothing across itself; on the line that ends the block it
    // also carries the sheet's closing rule out to the left edge. The line that
    // ends the block is the TOTAL where it prints, and the last line of the
    // block where it is asked to stand without one.
    $totalPrints = (bool) ($recap && $showTotal);
    $strip = fn (bool $closes) => $closes ? 'doc-strip doc-strip-end' : 'doc-strip';
    // Left as a plain sum: the form measures some items in kilos and halves.
    $totalQuantity = array_sum(array_column($recap, 'quantity'));
    $totalCost = round((float) array_sum(array_column($recap, 'total_cost')), 2);
    $totalAmount = round((float) array_sum(array_column($recap, 'amount')), 2);
@endphp

<table class="doc-grid doc-recap doc-fixed no-top doc-keep{{ $grid ? ' '.$grid : '' }}">
    <tbody>
        {{-- The columns the block is ruled with, named once on a line of no
             height of its own. The block is held to its columns as the list
             above it is, so the verticals of the two run through as one line
             instead of the headings walking the ruling a hair off it — the
             words "TOTAL COST" are a shade wider than the column the form
             gives them. The shares are read off this row, so they are written
             on cells with no border and no padding of their own. --}}
        <tr class="doc-measure">
            @foreach ($columns as $share)
                <td style="width:{{ $share }}%;"></td>
            @endforeach
        </tr>
        {{-- The band the form prints across the head of the block: the word once
             over the issued stock and its quantity, once over the cost column,
             and the rest of the band left open. --}}
        <tr>
            {{-- The column the band stops at: the strip the list's number
                 column leaves, carrying the width and the rule that closes the
                 block's left side, so the block opens at the stock number. --}}
            <td class="{{ $strip(false) }}"></td>
            <td{!! $span($band[0]) !!} class="band doc-seam-l doc-seam-r"><div class="band-issue">Recapitulation</div></td>
            <td{!! $span($band[1]) !!} class="band doc-seam-l"><div class="band-cost">Recapitulation</div></td>
            <td{!! $span($band[2]) !!} class="band"></td>
        </tr>
        <tr>
            <th class="doc-c {{ $strip(false) }}" style="width:{{ $columns[0] }}%;"></th>
            <th class="doc-c doc-nowrap doc-seam-l" style="width:{{ $columns[1] }}%;">STOCK NO:</th>
            <th class="doc-c doc-nowrap doc-seam-r" style="width:{{ $columns[2] }}%;">QNTY</th>
            <th class="doc-c doc-seam-l" style="width:{{ $columns[3] }}%;"></th>
            <th class="doc-c doc-nowrap" style="width:{{ $columns[4] }}%;">UNIT COST</th>
            <th class="doc-c doc-nowrap" style="width:{{ $columns[5] }}%;">TOTAL COST</th>
            <th class="doc-c doc-nowrap" style="width:{{ $columns[6] }}%;">AMOUNT</th>
        </tr>

        @if ($blankRun || ! $recap)
            {{-- The block with nothing written in it: a blank form, or a run
                 whose every figure has been withheld. One ruled line stands
                 for the whole of it, as it does on a blank form: the reader
                 fills the same columns the figures would have sat in, and on
                 a form there is nothing to tell two empty lines apart. --}}
            <tr class="doc-blank">
                <td class="{{ $strip(! $totalPrints) }}"></td>
                <td class="doc-seam-l"></td>
                <td class="doc-seam-r"></td>
                <td class="doc-seam-l"></td>
                <td></td>
                <td class="doc-seam-r"></td>
                <td class="doc-seam-l"></td>
            </tr>
        @else
            @foreach ($recap as $line)
                <tr>
                    <td class="{{ $strip($loop->last && ! $totalPrints) }}"></td>
                    {{-- A withheld figure leaves the cell empty but not flat:
                         the line is one of the form's, and it keeps a written
                         line's height, as the list's blank lines do, so the
                         block rules the same whether the figures are printed
                         or not. --}}
                    <td class="doc-c doc-nowrap doc-seam-l">@if ($showStock){{ $line['stock_no'] }}@else{!! '&nbsp;' !!}@endif</td>
                    <td class="doc-c doc-seam-r">@if ($showStock){{ $line['quantity'] }}@else{!! '&nbsp;' !!}@endif</td>
                    <td class="doc-seam-l"></td>
                    <td class="doc-r">{{ $showCosts && $line['unit_cost'] !== null ? number_format((float) $line['unit_cost'], 2) : '' }}</td>
                    <td class="doc-r doc-seam-r">{{ $showCosts ? number_format((float) $line['total_cost'], 2) : '' }}</td>
                    <td class="doc-r doc-seam-l">{{ $showCosts ? number_format((float) $line['amount'], 2) : '' }}</td>
                </tr>
            @endforeach
        @endif

        @if ($recap && $showTotal)
            <tr>
                <td class="{{ $strip(true) }}"></td>
                <td class="doc-r doc-total doc-seam-l">TOTAL</td>
                <td class="doc-c doc-total doc-seam-r">{{ $showStock ? $totalQuantity : '' }}</td>
                <td class="doc-seam-l"></td>
                <td></td>
                <td class="doc-r doc-total doc-seam-r">{{ $showCosts ? number_format($totalCost, 2) : '' }}</td>
                <td class="doc-r doc-total doc-seam-l">{{ $showCosts ? number_format($totalAmount, 2) : '' }}</td>
            </tr>
        @endif
    </tbody>
</table>
