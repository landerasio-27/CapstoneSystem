@extends('layouts.admin')

@section('title', 'New Supply')

@section('content')
    <div class="max-w-3xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('admin.supplies.store') }}">
            @include('admin.supplies._form')
        </form>
    </div>
@endsection
