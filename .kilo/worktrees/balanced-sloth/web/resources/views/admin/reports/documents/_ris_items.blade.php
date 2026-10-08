{{--
    The issued items, exactly as the official form lays them out: item and
    description share one cell so a long description has the full width to run
    on and the row grows with it.

    $blankRows leaves empty lines to write on when a form asks for them, and
    the "Nothing Follows" line closes the list either way — nothing may be
    added after it.

    $closes (on by default) says whether this is the last of the list's pages: a
    list long enough to run over more than one printed page is cut by the caller
    (see SheetPages) and only the page it ends on carries the line that closes
    it, since nothing may be added after it — the pages before it are the one
    list carried on, and take the next of them in their stride.

    Expects: $rows, $blankRows (0 by default), $showCosts (off by default),
    $closes (on by default), and $noLabel — the heading of the first column,
    "RIS NO." unless the lines come from the ICS, whose own form heads it
    "ICS NO.".

    The UNIT COST and AMOUNT columns belong to the accounting unit: they stand
    on the form either way, and stay blank unless $showCosts asks for the
    figures the report already knows.

    Two tables stand here rather than one: the banner that heads the block over
    the two halves of the sheet, and the list itself. The banner spans its
    columns, and the list holds its own to the shares the form rules them with,
    which a table can only do from the row it opens with — so the banner is
    given a table of its own instead of standing as that row, and both name
    their columns on a line of no height above them. The shares are the
    recapitulation's own, so the verticals of the two blocks run through as one
    line where they meet.

    The first share is the form's, opened out. The sheet rules the number
    column at 12 per cent for a number written in by hand; the number printed
    here is always fourteen characters long — "RIS-2026-00002" — and at the
    sheet's own size that is wider than 12 per cent of the page, so the run
    used to cross the rule and print over the end-user beside it. The column is
    widened to 14.5 and the end-user gives up the width, which keeps every
    other line of the grid — the end-user's own rule, the description's, the
    money columns and the foot the recapitulation opens on — exactly where the
    two blocks already draw them.
--}}
@php
    $rows = $rows ?? [];
    $blankRows = (int) ($blankRows ?? 0);
    $showCosts = (bool) ($showCosts ?? false);
    $closes = (bool) ($closes ?? true);
    $noLabel = $noLabel ?? 'RIS NO.';

    // A line can carry no cost at all (a hand-typed one): print it empty
    // rather than as a zero the office never wrote.
    $money = fn ($value) => $value === null ? '' : number_format((float) $value, 2);

    // The official form writes the RIS number and the end-user once for the
    // run of lines belonging to one paper, leaving both cells blank under it.
    $previousRisNo = null;
    $previousEndUser = null;
@endphp

{{-- The banner over the two halves of the sheet. It stands in its own table
    above the list, because the list holds its columns to the shares the form
    rules them with and a table can only do that from its first row: opened
    with a banner spanning its columns instead, the width would be divided
    evenly between them and every vertical in the list would walk off the lines
    the recapitulation rules below it. The banner is ruled on the list's own
    columns, so the line that parts the supply unit's run from the accounting
    unit's carries on down the entries the banner stands over. --}}
<table class="doc-grid doc-banner doc-fixed no-top">
    <thead>
        {{-- The columns the banner and the list are ruled with, named once on a
             line of no height of its own: the shares are read off this row, so
             the spans below it are measured against them rather than against
             an even division of the width. --}}
        <tr class="doc-measure">
            @foreach ([14.5, 11.5, 36, 7, 7.5, 9.5, 14] as $share)
                <td style="width:{{ $share }}%;"></td>
            @endforeach
        </tr>
        <tr>
            <th colspan="5" class="doc-i">To be filled up in the Supply and Property Unit</th>
            <th colspan="2" class="doc-i">To be Filled up by Accounting Unit</th>
        </tr>
    </thead>
</table>

<table class="doc-grid doc-items doc-fixed no-top">
    <thead>
        {{-- The columns the block is ruled with, named once on a line of no
             height of its own: the shares are read off this row, where they sit
             on cells with no border and no padding of their own, so what they
             measure is the column and not the box the headings are printed
             in. --}}
        <tr class="doc-measure">
            @foreach ([14.5, 11.5, 36, 7, 7.5, 9.5, 14] as $share)
                <td style="width:{{ $share }}%;"></td>
            @endforeach
        </tr>
        {{-- The headings name every column of the list. --}}
        <tr>
            <th style="width:14.5%;">{{ $noLabel }}</th>
            <th style="width:11.5%;">End-User</th>
            <th style="width:36%;">Item / Description</th>
            <th style="width:7%;">UNIT</th>
            <th style="width:7.5%;">QNTY<br>ISSUED</th>
            <th style="width:9.5%;">UNIT COST</th>
            <th style="width:14%;">AMOUNT</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php
                $continuesPaper = $row['ris_no'] === $previousRisNo
                    && $row['end_user'] === $previousEndUser;
                // The line that opens an end-user's run is ruled off from the
                // person above them, so the sheet reads as one block per name
                // rather than as one unbroken column of items. The line the
                // list itself opens with keeps no rule: the headings above it
                // already close the top of the block.
                $opensRun = ! $continuesPaper && ! $loop->first;
                $previousRisNo = $row['ris_no'];
                $previousEndUser = $row['end_user'];
            @endphp
            <tr class="doc-noline{{ $opensRun ? ' doc-group' : '' }}">
                {{-- The number holds its column: it is the one entry on the
                     sheet longer than the width the form ruled for it, and it
                     reads as one number or not at all. --}}
                <td class="doc-nowrap">{{ $continuesPaper ? '' : $row['ris_no'] }}</td>
                <td>{{ $continuesPaper ? '' : $row['end_user'] }}</td>
                <td>
                    <div class="doc-name">{{ $row['item'] }}</div>
                    @if ($row['description'])
                        <div class="doc-desc">{!! nl2br(e($row['description'])) !!}</div>
                    @endif
                </td>
                <td class="doc-c">{{ $row['unit'] }}</td>
                <td class="doc-c">{{ $row['quantity'] }}</td>
                <td class="doc-r">{{ $showCosts ? $money($row['unit_cost']) : '' }}</td>
                <td class="doc-r">{{ $showCosts ? $money($row['amount']) : '' }}</td>
            </tr>
        @empty
            {{-- A blank form has no message on it; an empty report does. The
                 message row follows the columns alone like the lines it stands
                 in for, and is closed by the block's foot line below it. --}}
            @if ($blankRows === 0)
                <tr class="doc-noline doc-foot">
                    <td colspan="7" class="doc-c doc-i" style="padding:24px 8px;">No records found for the selected period.</td>
                </tr>
            @endif
        @endforelse

        @for ($line = 0; $line < $blankRows; $line++)
            <tr class="doc-blank doc-noline">
                <td>&nbsp;</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        @endfor

        @if ($rows || $blankRows)
            {{-- The list closes here: the starred line runs from the description
                 column to the end of the unit cost column, so those columns are
                 merged for this row and no border cuts across it. The run is as
                 long as the span it fills, which is why it is counted here.
                 It is the block's foot line too: the list ends on it rather
                 than on a run of empty ruled lines, so the sheet carries no
                 blank rows for the office to write in that nothing may be
                 added to — and the rule the recapitulation opens with comes
                 from the closing line itself.

                 On a page the list runs on from, nothing has ended yet: the
                 row still draws the rule the page's own certification hangs
                 from, and the run of stars is left off it, because the entries
                 below it are on the next page. --}}
            <tr class="doc-noline doc-foot">
                {{-- The strip the block opens beside: the closing line stops at
                     it, so the number column reads on down to the block's foot
                     line with no line drawn across the strip here. The cell
                     keeps the rule parting that column from the end-user
                     beside it, which ends where the list does. --}}
                <td class="doc-strip"></td>
                <td></td>
                <td colspan="4" class="doc-c doc-fill">{{ $closes ? '**************************Nothing Follows**************************' : '' }}</td>
                <td></td>
            </tr>
        @endif
    </tbody>
</table>
