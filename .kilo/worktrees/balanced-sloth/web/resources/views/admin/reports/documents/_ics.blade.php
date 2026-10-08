{{--
    ICS — Inventory Custodian Slip.

    One slip per custodian: an ICS is the paper a person signs for the property
    they hold, so the selection is grouped by custodian and each group prints on
    its own sheet. Amounts are quantity × unit cost, as on the record.

    The slip closes the same way the RIS does: the "Nothing Follows" line under
    the items, the recapitulation of what this custodian holds, and the
    certification the supply officer signs.
--}}
@php
    $sheets = $sheets ?? [];
    $form = $form ?? [];
    $dateLabel = $dateLabel ?? null;
    $meta = $meta ?? [];
@endphp

@foreach ($sheets as $sheet)
    <div class="doc doc-sheet">
        <h1 class="doc-title">Inventory Custodian Slip</h1>
        <div class="doc-org">Isabela State University - Cauayan Campus</div>

        <table class="doc-grid">
            {{-- The label column is the first column of the list below it: the
                 line between the entity's name and its value stands on the line
                 between the property number and the item, and runs on down the
                 list without a step where the two tables meet. --}}
            <tr>
                <th class="lbl" style="width:14%;">Entity Name:</th>
                <td colspan="3">{{ $form['entity_name'] }}</td>
                <th class="lbl" style="width:11%;">ICS No.:</th>
                <td style="width:17%;">{{ $sheet['number'] }}</td>
            </tr>
            <tr>
                <th class="lbl">Fund Cluster:</th>
                <td colspan="3">{{ $form['fund_cluster'] }}</td>
                <th class="lbl">Date:</th>
                <td>{{ $dateLabel }}</td>
            </tr>
            <tr>
                <th class="lbl">Custodian:</th>
                <td colspan="5" class="doc-b">{{ $sheet['custodian'] ?: '—' }}</td>
            </tr>
        </table>

        {{-- The list's columns are held to the shares written on its headings, so
             the line they are ruled by runs straight down from the header block:
             an entry's description cannot widen its column and carry the rule
             off the line above it. --}}
        <table class="doc-grid doc-fixed no-top">
            <thead>
                <tr>
                    <th style="width:14%;">Inventory / Property No.</th>
                    <th style="width:18%;">Item</th>
                    <th>Description</th>
                    <th style="width:6%;">Qty</th>
                    <th style="width:7%;">Unit</th>
                    <th style="width:11%;">Unit Cost</th>
                    <th style="width:12%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sheet['rows'] as $row)
                    <tr>
                        <td class="doc-c">{{ $row['property_no'] }}</td>
                        <td>
                            <div class="doc-name">{{ $row['name'] }}</div>
                            @if ($row['item_code'] && $row['item_code'] !== $row['property_no'])
                                <div class="doc-small">{{ $row['item_code'] }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($row['description'])
                                {!! nl2br(e($row['description'])) !!}
                            @endif
                        </td>
                        <td class="doc-c">{{ $row['quantity'] }}</td>
                        <td class="doc-c">{{ $row['unit'] }}</td>
                        <td class="doc-r">{{ number_format($row['unit_cost'], 2) }}</td>
                        <td class="doc-r">{{ number_format($row['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="doc-c doc-i" style="padding:24px 8px;">No records found for the selected period.</td>
                    </tr>
                @endforelse

                {{-- Closes the list: nothing may be added after it. --}}
                @if ($sheet['rows'])
                    {{-- Closes the list: nothing may be added after it. The line
                         runs from the description column to the end of the unit
                         cost column, as wide as those four columns together, so
                         it is counted for this table's own widths. --}}
                    <tr class="doc-foot">
                        {{-- The closing line stops at the strip the block opens
                             beside, as it does on the RIS: the cell keeps the
                             rule parting the item column from the entries
                             beside it, and the column reads on down the strip
                             to the block's foot line. --}}
                        <td class="doc-strip"></td>
                        <td></td>
                        <td colspan="4" class="doc-c doc-fill">************************Nothing Follows************************</td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </table>

        {{-- The ICS keeps its own money columns: it is the slip a custodian
             signs for what they hold, so the costs are part of the record. The
             block is given the shares its own list is ruled on — the stock
             number under the item column, the quantity at the head of the
             description column, the money group under the columns that name
             it — so its verticals meet the lines above them, and the band is
             given the spans those shares leave it. --}}
        @include('admin.reports.documents._ris_recap', [
            'recap' => $sheet['recap'] ?? [],
            // The slip is its own record rather than a report over the office's
            // month: the custodian signs for what they hold, so the figures and
            // the total that closes them are the paper's own and are printed
            // whether or not a report would have asked for them.
            'showCosts' => true,
            'showStock' => true,
            'showTotal' => true,
            'columns' => [14, 18, 10, 22, 13, 11, 12],
            'band' => [3, 2, 1],
            'grid' => 'doc-recap-ics',
        ])
        @include('admin.reports.documents._ris_certification', [
            'meta' => [
                'posted_by' => $meta['posted_by'] ?? '',
                'posted_position' => $meta['posted_position'] ?? null,
            ],
        ])

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
                            'name' => $sheet['custodian'],
                            'position' => $sheet['custodian_position'],
                            'dateLabel' => $dateLabel,
                        ])
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
@endforeach
