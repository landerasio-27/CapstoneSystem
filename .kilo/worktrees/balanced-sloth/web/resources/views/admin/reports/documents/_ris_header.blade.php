{{--
    The RIS header block, laid out as the official form does it: the report's
    name and the agency sit inside the same frame as the details, with no rule
    between them, the labels are right-aligned in their own narrow column, and
    the values are written straight after their labels on lines that run out to
    the edge of the frame. Shared by the RIS and the Monthly Report.

    Expects: $meta (agency, period, number), $endUser (nullable), and
    $editable — true on the generator alone, where the number is written into
    the very rule the sheet draws for it. The printed sheet and the exported
    paper are handed the number as a written line, since neither can be typed
    on.

    $noField names that field. The RIS is one sheet written under one number;
    the Monthly Report numbers its own pages and has nothing to write into, so
    it passes no field at all and its sheets print the number they are given.
--}}
@php
    $endUser = $endUser ?? null;
    $meta = $meta ?? [];
    $editable = $editable ?? false;
    $noField = $noField ?? 'report_no';
@endphp

<table class="doc-grid doc-head">
    <tbody>
        <tr>
            <td colspan="5" class="doc-c doc-title">Report of Supplies and Materials Issued</td>
        </tr>
        <tr>
            <td colspan="5" class="doc-c doc-org">Isabela State University - Cauayan Campus</td>
        </tr>
        <tr>
            {{-- The number is written at the head of its own line, straight
                 after the label that introduces it, with the rule running on
                 under it and out to the edge of the frame — not tucked under
                 the labels with the agency and the date, and not carried to
                 the far end of the line either: it is the entry the line is
                 drawn for, so the entry stands where it is read first. The
                 label stands clear of the rule, so no line runs under the
                 words. --}}
            <td colspan="5" class="lbl doc-r"><span class="doc-no-label">NO:</span>@if ($editable)<input
                        type="text" name="{{ $noField }}" form="report-form" value="{{ $meta['number'] ?? '' }}"
                        aria-label="Report number" autocomplete="off" spellcheck="false"
                        data-no-entry class="doc-no-entry">@else<span class="doc-sign-line">{{ $meta['number'] ?? '' }}</span>@endif</td>
        </tr>
        <tr>
            <th class="lbl doc-r" style="width:12%;">AGENCY:</th>
            <td colspan="4" class="doc-l doc-b doc-nowrap"><span class="doc-head-line">{{ $meta['agency'] ?? '' }}</span></td>
        </tr>
        <tr>
            <th class="lbl doc-r">DATE:</th>
            <td colspan="4" class="doc-l doc-b doc-nowrap"><span class="doc-head-line">{{ $meta['period'] ?? '' }}</span></td>
        </tr>
        @if ($endUser)
            <tr>
                <th class="lbl doc-r">END-USER:</th>
                <td colspan="4" class="doc-b">{{ $endUser }}</td>
            </tr>
        @endif
    </tbody>
</table>
