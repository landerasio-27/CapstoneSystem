{{--
    RIS — Report of Supplies and Materials Issued.

    The official monthly form: agency / date / number header, one line per
    issued item (with the item and its full description in one merged cell so a
    long description can run on), the line that closes the list, the
    recapitulation, and the certification. Every figure comes from the RIS
    documents on file.

    The list is measured before it is printed and cut into pages, so a report
    long enough to fill the paper carries the form's own head with it onto the
    next page instead of leaving a page of bare entries behind it — with the
    line that ends the list, the recapitulation under it and the certification
    the sheet is signed at the foot of all standing on the page the list ends on
    (see SheetPages). The pages are measured for the printer
    the sheet is going to — the browser for the preview and the printed sheet,
    the exporter for the PDF — and a report that fits its page is cut into one
    page and prints exactly as it always did.
--}}
@php
    // The recapitulation's figures are the accounting unit's to fill in, so a
    // report prints none of them unless the office asks for them (see
    // ReportController and _ris_recap).
    $showCosts = $showCosts ?? false;
    $showStock = $showStock ?? false;
    $showTotal = $showTotal ?? false;
    $recap = $recap ?? [];

    $pager = \App\Support\SheetPages::for($forExport ?? false);
    $pages = $pager->cutRis(
        collect($rows ?? [])->values()->all(),
        $pager->tailMm(\App\Support\SheetPages::recapLines($recap, $showStock, $showCosts, $showTotal)),
    );
@endphp

{{-- Each page of the list is a sheet of its own, and the sheets break after one
     another as the printing reaches them (see .doc-sheet). --}}
@foreach ($pages as $rows)
    <div class="doc doc-sheet">
        {{-- The report's name sits inside the header frame, as on the form. --}}
        {{-- The number on the working page names the month it is written for, so a
             number written in January is January's and is not carried onto another
             month's sheet. --}}
        @include('admin.reports.documents._ris_header', [
            'meta' => $meta,
            'endUser' => null,
            'editable' => $editable ?? false,
            'noField' => 'report_no['.($periodKey ?? '').']',
        ])

        @include('admin.reports.documents._ris_items', [
            'rows' => $rows,
            'blankRows' => 0,
            'showCosts' => $showCosts,
            // Only the page the list ends on closes it.
            'closes' => $loop->last,
        ])

        {{-- The recapitulation, the line that ends the list and the certification
             the sheet is signed at the foot of all stand on the page the list
             ends on: the pages before it are the one list carried on, and they
             take their entries down to the foot of the paper rather than
             stopping a foot's height short of it. --}}
        @if ($loop->last)
            @include('admin.reports.documents._ris_recap', ['recap' => $recap, 'showCosts' => $showCosts, 'showStock' => $showStock, 'showTotal' => $showTotal])
            @include('admin.reports.documents._ris_certification', ['meta' => $meta])
        @endif
    </div>
@endforeach
