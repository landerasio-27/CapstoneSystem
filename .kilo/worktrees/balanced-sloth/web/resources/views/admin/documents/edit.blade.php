@extends('layouts.admin')

@section('title', 'Edit ' . $document->type . ' — ' . $document->document_no)

@section('content')
    @include('admin.documents._form', [
        'doc' => $document,
        'type' => $document->type,
        'action' => route('admin.documents.update', $document),
        'method' => 'PUT',
        'submitLabel' => 'Save Changes',
    ])
@endsection
