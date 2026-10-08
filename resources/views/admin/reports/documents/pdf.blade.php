<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $typeLabel }} — {{ $documentTitle }}</title>
    {{-- The forms are set in Calibri. A browser already has the font on the
         machine, but dompdf has to be handed the file itself, so these rules
         live only in the PDF view — the print sheet and the preview would only
         log failed fetches for them. Absent files simply fall back to the
         "DejaVu Sans" in the stack. --}}
    <style>
        @font-face {
            font-family: Calibri;
            font-style: normal;
            font-weight: normal;
            src: url("{{ str_replace('\\', '/', storage_path('fonts/calibri.ttf')) }}") format("truetype");
        }
        @font-face {
            font-family: Calibri;
            font-style: normal;
            font-weight: bold;
            src: url("{{ str_replace('\\', '/', storage_path('fonts/calibrib.ttf')) }}") format("truetype");
        }
        @font-face {
            font-family: Calibri;
            font-style: italic;
            font-weight: normal;
            src: url("{{ str_replace('\\', '/', storage_path('fonts/calibrii.ttf')) }}") format("truetype");
        }
        @font-face {
            font-family: Calibri;
            font-style: italic;
            font-weight: bold;
            src: url("{{ str_replace('\\', '/', storage_path('fonts/calibriz.ttf')) }}") format("truetype");
        }
    </style>
    @include('admin.reports.documents._styles')
</head>
<body>
    {{-- The very same body the print sheet and the generator preview render, so
         the PDF cannot drift away from what was on screen. It is told which
         printer it is for, so the pages of a long list are measured to what
         this one sets on a page — the exporter's own line, a third taller
         than the browser's (see SheetPages). --}}
    @include('admin.reports.documents._document', ['forExport' => true])

    {{-- dompdf page footer: page number of total, on every page. --}}
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(500, 812, "Page {PAGE_NUM} of {PAGE_COUNT}", null, 8, [0, 0, 0]);
        }
    </script>
</body>
</html>
