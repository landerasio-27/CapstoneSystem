@php
    $items = $document->items;
    $minRows = 12;
    $pad = max(0, $minRows - $items->count());
    $receivedFrom = $document->issuedBy?->name ?? $document->preparedBy?->name;
    $receivedBy = $document->received_by ?? $document->custodian_name;
    $receivedPos = $document->custodian_position ?: $document->teacher?->position;
@endphp

<div class="form-head">
    <div class="title">Inventory Custodian Slip</div>
    <div class="org">ISABELA STATE UNIVERSITY</div>
    <div class="loc">Cauayan Campus, Cauayan City</div>
</div>

<table class="plain small" style="margin-bottom:2px;">
    <tr>
        <td class="b" style="width:70%;">Entity Name: <span class="fld">{{ $document->entity_name }}</span></td>
        <td class="b"></td>
    </tr>
    <tr>
        <td class="b">Fund Cluster: <span class="fld">{{ $document->fund_cluster }}</span></td>
        <td class="b">ICS No: <span class="fld">{{ $document->document_no }}</span></td>
    </tr>
</table>

<table class="bx">
    <thead>
        <tr>
            <th rowspan="2" style="width:9%;">QUANTITY</th>
            <th rowspan="2" style="width:8%;">UNIT</th>
            <th colspan="2">Amount</th>
            <th rowspan="2">Description</th>
            <th rowspan="2" style="width:12%;">Inventory<br>Item No.</th>
            <th rowspan="2" style="width:12%;">Estimated<br>Useful Life</th>
        </tr>
        <tr>
            <th style="width:11%;">Unit Cost</th>
            <th style="width:11%;">Total Cost</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr class="rowh">
                <td class="tc">{{ $item->quantity }}</td>
                <td class="tc">{{ $item->unit }}</td>
                <td class="tr">{{ number_format($item->unit_cost, 2) }}</td>
                <td class="tr">{{ number_format($item->amount, 2) }}</td>
                <td>{{ $item->description }}</td>
                <td class="tc">{{ $item->property_number ?? $item->equipment?->item_code }}</td>
                <td class="tc">{{ $item->estimated_useful_life }}</td>
            </tr>
        @endforeach
        @for ($i = 0; $i < $pad; $i++)
            <tr class="rowh"><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
    </tbody>
</table>

<table class="bx no-top top">
    <tr class="top">
        <td style="width:50%; height:150px;">
            <div class="b" style="margin-bottom:6px;">Received from:</div>
            <div class="sig-space-lg"></div>
            <div class="tc">
                <div class="b">{!! $receivedFrom ? e($receivedFrom) : '&nbsp;' !!}</div>
                <div class="sig-line">Signature Over Printed Name</div>
            </div>
            <div class="tc" style="margin-top:18px;">
                <div>Administrative Officer I (Supply Officer I)</div>
                <div class="sig-line">Position</div>
            </div>
            <div style="margin-top:16px;">Date: <span style="display:inline-block; width:130px; border-bottom:1px solid #000;">&nbsp;{{ $document->issued_at?->format('m/d/Y') }}</span></div>
        </td>
        <td style="width:50%;">
            <div class="b" style="margin-bottom:6px;">Received by:</div>
            <div class="sig-space-lg"></div>
            <div class="tc">
                <div class="b">{!! $receivedBy ? e($receivedBy) : '&nbsp;' !!}</div>
                <div class="sig-line">Signature Over Printed Name</div>
            </div>
            <div class="tc" style="margin-top:18px;">
                <div>{!! $receivedPos ? e($receivedPos) : '&nbsp;' !!}</div>
                <div class="sig-line">Position</div>
            </div>
            {{-- Filled when the document is issued; older issued documents fall
                 back to their issue date so the line is never blank. --}}
            <div style="margin-top:16px;">Date: <span style="display:inline-block; width:130px; border-bottom:1px solid #000;">&nbsp;{{ ($document->date_received ?? $document->issued_at)?->format('m/d/Y') }}</span></div>
        </td>
    </tr>
</table>
