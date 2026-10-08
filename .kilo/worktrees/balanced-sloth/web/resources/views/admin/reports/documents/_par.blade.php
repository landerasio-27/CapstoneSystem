{{--
    PAR — Property Acknowledgement Receipt.

    A separate paper from the ICS and the RIS: the receipt for property handed
    over, built from the equipment records the administrator selected. Every
    column is read off those records, and the total is the sum of the computed
    amounts.
--}}
@php
    $rows = $rows ?? [];
    $form = $form ?? [];
    $totalAmount = $totalAmount ?? 0.0;
    $dateLabel = $dateLabel ?? null;
@endphp

<div class="doc">
    <h1 class="doc-title">Property Acknowledgement Receipt</h1>
    <div class="doc-org">Isabela State University - Cauayan Campus</div>

    <table class="doc-grid">
        <tr>
            <th class="lbl" style="width:15%;">Entity Name:</th>
            <td colspan="3">{{ $form['entity_name'] }}</td>
            <th class="lbl" style="width:11%;">PAR No.:</th>
            <td style="width:17%;">{{ $form['document_no'] }}</td>
        </tr>
        <tr>
            <th class="lbl">Fund Cluster:</th>
            <td colspan="3">{{ $form['fund_cluster'] }}</td>
            <th class="lbl">Date:</th>
            <td>{{ $dateLabel }}</td>
        </tr>
        <tr>
            <th class="lbl">Accountable Person:</th>
            <td colspan="2">{{ $form['accountable'] }}</td>
            <th class="lbl">Position:</th>
            <td colspan="2">{{ $form['accountable_position'] }}</td>
        </tr>
        <tr>
            <th class="lbl">Room / Office:</th>
            <td colspan="5">{{ $form['room'] }}</td>
        </tr>
    </table>

    {{-- The list's columns are held to the shares written on its headings, so
         an article's description cannot widen its column and carry the rules
         off the lines the header block draws above them. --}}
    <table class="doc-grid doc-fixed no-top">
        <thead>
            <tr>
                <th style="width:11%;">Property No.</th>
                <th style="width:9%;">Item Code</th>
                <th>Articles and Description</th>
                <th style="width:4%;">Qty</th>
                <th style="width:6%;">Unit</th>
                <th style="width:9%;">Unit Cost</th>
                <th style="width:10%;">Total Amount</th>
                <th style="width:9%;">Date Acquired</th>
                <th style="width:14%;">Accountable Person</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="doc-c">{{ $row['property_no'] }}</td>
                    <td class="doc-c">{{ $row['item_code'] }}</td>
                    <td>
                        <div class="doc-name">{{ $row['name'] }}</div>
                        @if ($row['description'])
                            <div class="doc-desc">{!! nl2br(e($row['description'])) !!}</div>
                        @endif
                    </td>
                    <td class="doc-c">{{ $row['quantity'] }}</td>
                    <td class="doc-c">{{ $row['unit'] }}</td>
                    <td class="doc-r">{{ number_format($row['unit_cost'], 2) }}</td>
                    <td class="doc-r">{{ number_format($row['amount'], 2) }}</td>
                    <td class="doc-c doc-nowrap">{{ $row['date_acquired']?->format('m/d/Y') }}</td>
                    <td>
                        {{ $row['accountable'] }}
                        @if ($row['room'])
                            <div class="doc-small">{{ $row['room'] }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="doc-c doc-i" style="padding:24px 8px;">No records found for the selected period.</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="6" class="doc-r doc-total">TOTAL:</td>
                <td class="doc-r doc-total">{{ number_format($totalAmount, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    {{-- The property is received from the supply office by the person
         accountable for it, so the two parties sign side by side. --}}
    <table class="doc-grid no-top">
        <tbody>
            <tr class="doc-t">
                <td style="width:50%;">
                    @include('admin.reports.documents._signature', [
                        'label' => 'Received from:',
                        'name' => $form['received_from'],
                        'position' => $form['received_from_position'],
                        'dateLabel' => $dateLabel,
                    ])
                </td>
                <td style="width:50%;">
                    @include('admin.reports.documents._signature', [
                        'label' => 'Received by:',
                        'name' => $form['received_by'],
                        'position' => $form['received_by_position'],
                        'dateLabel' => $dateLabel,
                    ])
                </td>
            </tr>
        </tbody>
    </table>
</div>
