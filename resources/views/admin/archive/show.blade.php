@extends('layouts.admin')

@section('title', "Archive — {$archive->year}")

@section('content')
    @include('admin.archive._year', [
        'backUrl' => route('admin.archive.index'),
        'cardTypeUrl' => fn (?string $type) => route('admin.archive.show', array_filter([
            'archive' => $archive,
            'card_type' => $type,
        ])),
    ])
@endsection
