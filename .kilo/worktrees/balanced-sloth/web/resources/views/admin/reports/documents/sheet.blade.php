<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $typeLabel }} — {{ $documentTitle }}</title>
    @include('admin.reports.documents._styles')
</head>
<body>
    {{-- Page chrome, never printed: this is the sheet on its own, with no
         sidebar, filters or buttons on the paper. --}}
    <div class="doc-bar">
        {{-- The date, the page title and the address a browser puts above and
             below a printed page are the browser's own, not the form's: they
             come from the print dialog's "Headers and footers". Saying so here
             is the only way to have them gone — no page can switch them off. --}}
        <span class="hint">For a clean sheet, untick <strong>Headers and footers</strong> in the print dialog — that is what prints the date, the title and this page's address.</span>
        <a class="spacer" href="{{ $backUrl }}">Back to generator</a>
        <a href="{{ $pdfUrl }}">Export PDF</a>
        <button type="button" class="primary" onclick="window.print()">Print this document</button>
    </div>

    <div class="doc-paper">
        @include('admin.reports.documents._document')
    </div>

    @if ($autoprint)
        <script>
            window.addEventListener('load', function () { window.print(); });
        </script>
    @endif
</body>
</html>
