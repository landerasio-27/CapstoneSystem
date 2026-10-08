<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->type }} - {{ $document->document_no }}</title>
    @include('admin.documents._pdf_styles')
</head>
<body>
    @include('admin.documents._document_body', ['document' => $document])
</body>
</html>
