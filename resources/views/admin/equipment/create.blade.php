@extends('layouts.admin')

@section('title', 'New Equipment')

@section('content')
    <div class="max-w-3xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('admin.equipment.store') }}">
            @include('admin.equipment._form')
        </form>
    </div>
@endsection
