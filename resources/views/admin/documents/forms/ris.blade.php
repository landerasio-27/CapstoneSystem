@php
    $items = $document->items;
    $minRows = 10;
    $pad = max(0, $minRows - $items->count());
    $requestedBy = $document->requested_by ?? $document->custodian_name;
    // The requester and the receiver are the custodian the paper is filed
    // under, so both carry that record's position.
    $requestedDes = $document->custodian_position ?: $document->teacher?->position;
    $approvedBy = $document->approvedBy?->name;
    $approvedDes = $document->approvedBy?->position;
    $issuedBy = $document->issuedBy?->name ?? $document->preparedBy?->name;
    // The office that issues holds its title on its own account; until it says
    // which one, the paper signs under the supply office's own wording — the
    // same title the PAR and the ICS print for that office.
    $issuedDes = $document->issuedBy?->position
        ?: $document->preparedBy?->position
        ?: 'Administrative Officer I (Supply Officer I)';
    $receivedBy = $document->received_by ?? $document->custodian_name;
    $receivedDes = $document->custodian_position ?: $document->teacher?->position;
@endphp

<div class="form-head">
    <div class="title">Requisition and Issue Slip</div>
    <div class="org">ISABELA STATE UNIVERSITY</div>
    <div class="loc">Cauayan Campus, Cauayan City</div>
</div>

{{-- Header meta --}}
<table class="bx">
    <tr>
        <td style="width:62%;"><span class="b">Entity Name:</span> {{ $document->entity_name }}</td>
        <td colspan="3"><span class="b">Fund Cluster:</span> {{ $document->fund_cluster }}</td>
    </tr>
    <tr>
        <td><span class="b">Division:</span> {{ $document->division ?? $document->room?->name }}</td>
        <td colspan="3"><span class="b">Responsibility Center Code:</span> {{ $document->responsibility_center_code }}</td>
    </tr>
    <tr>
        <td><span class="b">Office:</span> {{ $document->office }}</td>
        <td colspan="3"><span class="b">RIS No:</span> {{ $document->document_no }}</td>
    </tr>
</table>

{{-- Grouped items --}}
<table class="bx no-top">
    <thead>
        <tr>
            <th colspan="4" class="i">Requisition</th>
            <th colspan="2" class="i">Stock Available</th>
            <th colspan="2" class="i">Issue</th>
        </tr>
        <tr>
            <th style="width:11%;">Stock No.</th>
            <th style="width:9%;">Unit</th>
            <th>Description</th>
            <th style="width:9%;">Qnty</th>
            <th style="width:7%;">Yes</th>
            <th style="width:7%;">No</th>
            <th style="width:9%;">Qnty</th>
            <th style="width:16%;">Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr class="rowh">
                <td class="tc">{{ $item->property_number ?? $item->equipment?->item_code }}</td>
                <td class="tc">{{ $item->unit }}</td>
                <td>{{ $item->description }}</td>
                <td class="tc">{{ $item->quantity }}</td>
                <td class="tc">{!! $item->stock_available === 'yes' ? '&#10003;' : '' !!}</td>
                <td class="tc">{!! $item->stock_available === 'no' ? '&#10003;' : '' !!}</td>
                <td class="tc">{{ $item->issued_quantity }}</td>
                <td>{{ $item->item_remarks }}</td>
            </tr>
        @endforeach
        @for ($i = 0; $i < $pad; $i++)
            <tr class="rowh"><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
        <tr>
            <td colspan="8" style="height:34px;"><span class="b">Purpose:</span> {{ $document->purpose ?? $document->remarks }}</td>
        </tr>
    </tbody>
</table>

{{-- Signatories --}}
<table class="bx no-top">
    <tr>
        <td style="width:16%;"></td>
        <td class="b tc">Requested By:</td>
        <td class="b tc">Approved By:</td>
        <td class="b tc">Issued By:</td>
        <td class="b tc">Received By:</td>
    </tr>
    <tr class="rowh">
        <td class="b">Signature:</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
    <tr class="rowh">
        <td class="b">Printed Name:</td>
        <td class="tc">{{ $requestedBy }}</td>
        <td class="tc">{{ $approvedBy }}</td>
        <td class="tc">{{ $issuedBy }}</td>
        <td class="tc">{{ $receivedBy }}</td>
    </tr>
    <tr class="rowh">
        <td class="b">Designation:</td>
        <td class="tc">{{ $requestedDes }}</td>
        <td class="tc">{{ $approvedDes }}</td>
        <td class="tc">{{ $issuedDes }}</td>
        <td class="tc">{{ $receivedDes }}</td>
    </tr>
    <tr class="rowh">
        <td class="b">Date:</td>
        <td></td>
        <td class="tc">{{ $document->approved_at?->format('m/d/Y') }}</td>
        <td class="tc">{{ $document->issued_at?->format('m/d/Y') }}</td>
        {{-- The receipt is dated when the document is issued. --}}
        <td class="tc">{{ ($document->date_received ?? $document->issued_at)?->format('m/d/Y') }}</td>
    </tr>
</table>
