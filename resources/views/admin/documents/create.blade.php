@extends('layouts.admin')

@section('title', 'New Document')

{{-- Nothing is chosen by hand: the items decide which paper(s) this becomes.
     A type still arrives from a custodian's folder (?type=PAR), where it only
     narrows the picker to the items that belong on that paper. --}}
@php $type = $type ?? null; @endphp

@section('content')
    @include('admin.documents._form', [
        'doc' => null,
        'type' => $type,
        'action' => route('admin.documents.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Document',
    ])
@endsection
