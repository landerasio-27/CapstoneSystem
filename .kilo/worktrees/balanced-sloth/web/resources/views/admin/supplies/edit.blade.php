@extends('layouts.admin')

@section('title', 'Edit Supply')

@section('content')
    <div class="max-w-3xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('admin.supplies.update', $supply) }}">
            @method('PUT')
            @include('admin.supplies._form')
        </form>
    </div>
@endsection
