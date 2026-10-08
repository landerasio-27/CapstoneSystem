{{--
    MONTHLY REPORT — Report of Supplies and Materials Issued.

    The same official grid as the RIS, printed as one running report per paper
    for the month: the lines of every end-user follow one another down the form,
    each run of a person's own lines carrying their name once, and the
    recapitulation and certification close the whole report rather than each
    person's part. So a month of small issues fills its page instead of leaving
    a page mostly blank for every end-user who took a little, and a report that
    fills the page carries on over onto another page by itself — the form is
    never cut into a page per end-user.

    A report's lines are already cut into the pages they print on by the office
    that builds it (see ReportController and SheetPages), and each page is
    handed here as a sheet of its own with the number it prints under: a report
    long enough to fill the paper carries the form's head onto every page, and
    the page that ends it carries the line that closes the list, the
    recapitulation and the certification the office signs. The pages are measured for the printer the
    sheet is going to — the browser for the preview and the printed sheet, the
    exporter for the PDF — so each carries as many of the month's lines as its
    own printer can write on a page.

    The number stands in the header's own line and is never typed: every page
    printed is given the next number of the month's run, so a report that fills
    its paper and carries on over another page is numbered on each of them, and
    a sheet can be filed under the month it belongs to and its place in that
    month's printing. There is nothing to write into, on the working page or on
    the paper.

    When one run covers both papers (Category = All) each report is preceded by
    a page naming it, so the RIS report and the ICS report cannot be mixed up.
    That page is a divider rather than a sheet of the run: it carries no number,
    and the numbering does not spend one on it.
--}}
@php
    $sheets = $sheets ?? [];
    // The money columns are the accounting unit's to fill in unless asked for;
    // the stock number and quantity are the report's own record, printed
    // unless the report is asked to hold them back.
    // The recapitulation's figures are the accounting unit's to fill in, so the
    // report prints none of them unless the office asks for them — the money
    // columns, the stock number and quantity, and the line that closes them
    // alike (see ReportController and _ris_recap).
    $showCosts = $showCosts ?? false;
    $showStock = $showStock ?? false;
    $showTotal = $showTotal ?? false;
@endphp

@foreach ($sheets as $index => $sheet)
    @if (! empty($sheet['cover']))
        <div class="doc doc-sheet doc-cover">
            <div class="doc-org">{{ $sheet['cover']['agency'] }}</div>
            <h1 class="doc-cover-title">{{ $sheet['cover']['title'] }}</h1>
            <div class="doc-cover-name">{{ $sheet['cover']['name'] }}</div>
            <div class="doc-cover-period">For the period {{ $sheet['cover']['period'] }}</div>
        </div>
    @endif

    @foreach ($sheet['pages'] ?? [['rows' => $sheet['rows'], 'number' => $sheet['meta']['number'] ?? '']] as $page)
        <div class="doc doc-sheet">
            {{-- The report's name sits inside the header frame, as on the form,
                 and the number is the page's own: every sheet the report prints
                 on is numbered as the run is printed, one page at a time. --}}
            @include('admin.reports.documents._ris_header', [
                'meta' => ['number' => $page['number']] + $sheet['meta'],
                // The report covers everyone the month issued to, and each run
                // of lines names its own end-user in the list below, so the
                // header names nobody: one name there would claim the whole
                // report.
                'endUser' => null,
                // The number is the report's own, so the sheet prints it as the
                // written line it reads as: there is no field to write into.
                'editable' => false,
                'noField' => '',
            ])
            @include('admin.reports.documents._ris_items', [
                'rows' => $page['rows'],
                'blankRows' => $sheet['blank_rows'],
                'showCosts' => $showCosts,
                // A sheet cut from the ICS says so in its first column heading.
                'noLabel' => ($sheet['kind'] ?? 'RIS') === 'ICS' ? 'ICS NO.' : 'RIS NO.',
                // Only the page the month's lines end on closes the list.
                'closes' => $loop->last,
            ])
            {{-- The recapitulation and the certification stand on the page the
                 report's lines end on, where the list is closed: the pages
                 before it are the one list carried on, and they take their
                 entries down to the foot of the paper rather than stopping a
                 foot's height short of it. The certification travels as one
                 piece (see .doc-cert), and it is the sheet's own foot — the
                 office signs where the report ends, once. --}}
            @if ($loop->last)
                @include('admin.reports.documents._ris_recap', [
                    'recap' => $sheet['recap'],
                    'showCosts' => $showCosts,
                    'showStock' => $showStock,
                    'showTotal' => $showTotal,
                ])
                @include('admin.reports.documents._ris_certification', ['meta' => $sheet['meta']])
            @endif
        </div>
    @endforeach
@endforeach
