{{--
    The official form itself: Appendix 54 (Stock Card) or Appendix 69 (Property
    Card), reproduced from the supply office's printed cards.

    Rendered both on screen (styled by resources/css/app.css) and inside the
    print/PDF views (styled by _print_styles.blade.php, since dompdf never loads
    the Tailwind bundle).

    @param \App\Models\Card $card
    @param iterable $lines            posted card transactions, oldest first
    @param array $daysToConsume       optional [transaction id => days|null]
    @param int $padTo                 pad the sheet with blank rows to this total
    @param bool $paper                repeat the header on each printed page

    Nothing is printed under the table: the official form ends at the last ruled
    row, so closing balances and explanatory notes stay out of the sheet.
--}}
@php
    $isStockCard = $card->isStockCard();
    $lines = collect($lines);
    $daysToConsume = $daysToConsume ?? [];
    $padTo = $padTo ?? 0;
    $blankRows = max(0, $padTo - $lines->count());
@endphp

<div class="sheet">
    <table style="width:100%; border-collapse:collapse; font-size:11px;">
        <tr>
            <td style="text-align:left; font-style:italic; color:#64748b;">Revised March 2016</td>
            <td style="text-align:right; font-style:italic; color:#64748b;">{{ $isStockCard ? 'Appendix 54' : 'Appendix 69' }}</td>
        </tr>
    </table>

    <p class="sheet-title" style="margin:14px 0 0;">{{ $isStockCard ? 'Stock Card' : 'Property Card' }}</p>

    <table class="sheet-meta">
        <tr>
            <th>Entity Name:</th>
            <td class="sheet-value">{{ $card->entity_name }}</td>
            <th>Fund Cluster:</th>
            <td class="sheet-value">{{ $card->fund_cluster ?: '—' }}</td>
        </tr>

        @if ($isStockCard)
            <tr>
                <th>Item:</th>
                <td class="sheet-value">{{ $card->itemName() }}</td>
                <th>Stock No.:</th>
                <td class="sheet-value">{{ $card->itemCode() ?: '—' }}</td>
            </tr>
            <tr>
                <th>Description:</th>
                <td class="sheet-value">{{ $card->description() ?: '—' }}</td>
                <th>Re-order Point:</th>
                <td class="sheet-value">{{ $card->reorder_point !== null ? $card->reorder_point . ' ' . $card->unit() : '—' }}</td>
            </tr>
            <tr>
                <th>Unit of Measurement:</th>
                <td class="sheet-value" colspan="3">{{ $card->unit() }}</td>
            </tr>
        @else
            <tr>
                <th>Property, Plant and Equipment:</th>
                <td class="sheet-value">{{ $card->itemName() }}</td>
                <th>Property Number:</th>
                <td class="sheet-value">{{ $card->itemCode() ?: '—' }}</td>
            </tr>
            <tr>
                <th>Description:</th>
                <td class="sheet-value" colspan="3">{{ $card->description() ?: '—' }}</td>
            </tr>
        @endif
    </table>

    <table class="sheet-table">
        <thead>
            @if ($isStockCard)
                <tr>
                    <th rowspan="2" style="width:9%;">Date</th>
                    <th rowspan="2" style="width:16%;">Reference</th>
                    <th rowspan="2" style="width:9%;">Receipt<br><span style="font-weight:400;">Qty.</span></th>
                    <th colspan="2">Issue</th>
                    <th rowspan="2" style="width:10%;">Balance<br><span style="font-weight:400;">Qty.</span></th>
                    <th rowspan="2" style="width:16%;">No. of Days to Consume</th>
                </tr>
                <tr>
                    <th style="width:9%;">Qty.</th>
                    <th style="width:19%;">Office</th>
                </tr>
            @else
                <tr>
                    <th rowspan="2" style="width:9%;">Date</th>
                    <th rowspan="2" style="width:15%;">Reference/<br>PAR No.</th>
                    <th rowspan="2" style="width:9%;">Receipt<br><span style="font-weight:400;">Qty.</span></th>
                    <th colspan="2">Issue/Transfer/ Disposal</th>
                    <th rowspan="2" style="width:9%;">Balance<br><span style="font-weight:400;">Qty.</span></th>
                    <th rowspan="2" style="width:12%;">Amount</th>
                    <th rowspan="2" style="width:16%;">Remarks</th>
                </tr>
                <tr>
                    <th style="width:9%;">Qty.</th>
                    <th style="width:14%;">Office/Officer</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td class="sheet-center">{{ $line->transaction_date?->format('m/d/y') }}</td>
                    <td>{{ $line->reference ?: '—' }}</td>
                    <td class="sheet-center {{ $line->receipt_qty > 0 ? 'sheet-in' : '' }}">
                        {{ $line->receipt_qty > 0 ? $line->receipt_qty : '—' }}
                    </td>
                    <td class="sheet-center {{ $line->issue_qty > 0 ? 'sheet-out' : '' }}">
                        {{ $line->issue_qty > 0 ? $line->issue_qty : '—' }}
                    </td>
                    <td>{{ $line->office ?: '—' }}</td>
                    <td class="sheet-center">{{ $line->balance_qty }}</td>

                    @if ($isStockCard)
                        <td class="sheet-center">
                            {{ ($daysToConsume[$line->id] ?? null) !== null ? $daysToConsume[$line->id] : 'N/A' }}
                        </td>
                    @else
                        <td class="sheet-num">{{ $line->amount !== null ? '₱' . number_format((float) $line->amount, 2) : '—' }}</td>
                        <td>{{ $line->remarks ?: '—' }}</td>
                    @endif
                </tr>
            @endforeach

            {{-- The printed form is a full page of ruled rows, not a two-line table. --}}
            @for ($i = 0; $i < $blankRows; $i++)
                <tr class="sheet-empty-row">
                    @for ($c = 0; $c < ($isStockCard ? 7 : 8); $c++)
                        <td>&nbsp;</td>
                    @endfor
                </tr>
            @endfor

            @if ($lines->isEmpty() && $blankRows === 0)
                <tr>
                    <td colspan="{{ $isStockCard ? 7 : 8 }}" class="sheet-center" style="padding:20px; color:#94a3b8;">
                        No transactions posted to this card yet.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
