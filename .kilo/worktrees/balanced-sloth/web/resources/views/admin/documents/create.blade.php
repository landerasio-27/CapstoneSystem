@extends('layouts.admin')

@section('title', 'New Document')

@php $type = old('type', $type ?? 'PAR'); @endphp

@section('content')
    @include('admin.documents._form', [
        'doc' => null,
        'type' => $type,
        'action' => route('admin.documents.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Document',
    ])
@endsection
