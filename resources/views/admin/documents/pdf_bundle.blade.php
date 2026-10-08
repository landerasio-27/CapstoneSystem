<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Documents' }}</title>
    @include('admin.documents._pdf_styles')
</head>
<body>
    {{-- Each document renders on its own page; page 1 = source (PAR/ICS/RIS),
         page 2 = the auto-generated IAR, etc. --}}
    @foreach ($documents as $index => $doc)
        @if ($index > 0)
            <div class="page-break"></div>
        @endif
        @include('admin.documents._document_body', ['document' => $doc])
    @endforeach
</body>
</html>
