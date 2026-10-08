@extends('layouts.admin')

@section('title', 'Edit Equipment')

@section('content')
    <div class="max-w-3xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('admin.equipment.update', $equipment) }}">
            @method('PUT')
            @include('admin.equipment._form')
        </form>
    </div>
@endsection
