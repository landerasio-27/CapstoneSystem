@php
    $items = $document->items;
    $minRows = 14;
    $pad = max(0, $minRows - $items->count());
    // The officer recorded on the document when there is one; the approver /
    // preparer is only a fallback for documents saved without that name.
    $inspector = $document->inspection_officer ?: ($document->approvedBy?->name ?? $document->preparedBy?->name);
    $supplyOfficer = $document->issuedBy?->name ?? $document->preparedBy?->name;
@endphp

{{-- Title + meta (top of the single box) --}}
<table class="bx">
    <tr>
        <td colspan="4" class="tc" style="border-bottom:none;">
            <div class="form-head" style="margin:0;">
                <div class="title">Inspection and Acceptance Report</div>
                <div class="org">ISABELA STATE UNIVERSITY</div>
                <div class="loc">Cauayan City, Isabela</div>
            </div>
        </td>
    </tr>
    <tr>
        <td style="width:50%;"><span class="b">Entity Name:</span> {{ $document->entity_name }}</td>
        <td colspan="3" style="width:50%;"><span class="b">Fund Cluster:</span> {{ $document->fund_cluster }}</td>
    </tr>
    <tr>
        <td><span class="b">Supplier:</span> {{ $document->supplier }}</td>
        <td colspan="3"><span class="b">IAR No:</span> {{ $document->document_no }}</td>
    </tr>
    <tr>
        <td><span class="b">PO No./Date:</span> {{ $document->po_no }}{{ $document->po_date ? ' / '.$document->po_date->format('m/d/Y') : '' }}</td>
        <td colspan="3"><span class="b">Date:</span> {{ $document->document_date?->format('F d, Y') }}</td>
    </tr>
    <tr>
        <td><span class="b">Requisition Office/Department:</span> {{ $document->requisition_office ?? $document->room?->name }}</td>
        <td colspan="3"><span class="b">Invoice No.:</span> {{ $document->invoice_no }}</td>
    </tr>
    <tr>
        <td><span class="b">Requisition Center Code:</span> {{ $document->requisition_center_code }}</td>
        <td colspan="3"><span class="b">Date:</span> {{ $document->invoice_date?->format('m/d/Y') }}</td>
    </tr>
</table>

{{-- Items --}}
<table class="bx no-top">
    <thead>
        <tr>
            <th style="width:12%;">Stock No.</th>
            <th>D E S C R I P T I O N</th>
            <th style="width:12%;">Unit</th>
            <th style="width:16%;">Quantity</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr class="rowh">
                <td class="tc">{{ $item->equipment?->item_code ?? $item->property_number }}</td>
                <td>{{ $item->description }}</td>
                <td class="tc">{{ $item->unit }}</td>
                <td class="tc">{{ $item->quantity }}</td>
            </tr>
        @endforeach
        <tr class="rowh">
            <td></td>
            <td class="tc">************************* Nothing follows *************************</td>
            <td></td>
            <td></td>
        </tr>
        @for ($i = 0; $i < $pad; $i++)
            <tr class="rowh"><td>&nbsp;</td><td></td><td></td><td></td></tr>
        @endfor
    </tbody>
</table>

{{-- Inspection / Acceptance --}}
<table class="bx no-top top">
    <tr>
        <th style="width:50%;">I N S P E C T I O N</th>
        <th style="width:50%;">A C C E P T A N C E</th>
    </tr>
    {{-- Both columns carry their signature at the foot, and both cells set
         their content to the bottom of the row: the two blocks then hang from
         the same height across the sheet however the notes above them run, and
         they stand on rules of one width (the .sig-block of the shared
         stylesheet), so the pair reads as one line of the form. --}}
    <tr class="top">
        <td style="height:150px; vertical-align:bottom;">
            <div style="margin-bottom:10px;">Date Inspected: <span style="display:inline-block; width:150px; border-bottom:1px solid #000;">&nbsp;{{ ($document->date_inspected ?? $document->approved_at)?->format('m/d/Y') }}</span></div>
            <table class="plain">
                <tr class="top">
                    <td style="width:110px;"><div class="insp-box"></div></td>
                    <td class="small">Inspected, verified and found in order as to quantity and specification</td>
                </tr>
            </table>
            <div class="tc">
                <div class="sig-block">
                    <div class="b">{{ $inspector }}</div>
                    <div class="sig-line">Inspection Officer</div>
                </div>
            </div>
        </td>
        <td style="vertical-align:bottom;">
            <div style="margin-bottom:14px;">Date Received: <span style="display:inline-block; width:150px; border-bottom:1px solid #000;">&nbsp;{{ ($document->date_received ?? $document->issued_at)?->format('m/d/Y') }}</span></div>
            <div style="margin-bottom:12px;"><span class="chk">{!! $document->acceptance_status === 'complete' ? '&#10003;' : '&nbsp;' !!}</span> Completed</div>
            <div style="margin-bottom:12px;"><span class="chk">{!! $document->acceptance_status === 'partial' ? '&#10003;' : '&nbsp;' !!}</span> Partial (pls specify quantity)</div>
            <div class="tc">
                <div class="sig-block">
                    <div class="b">{{ $supplyOfficer }}</div>
                    <div class="sig-line">Administrative Officer I (Supply Officer I)</div>
                </div>
            </div>
        </td>
    </tr>
</table>
