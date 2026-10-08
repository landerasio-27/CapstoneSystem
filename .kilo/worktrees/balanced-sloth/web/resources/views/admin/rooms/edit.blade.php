@extends('layouts.admin')

@section('title', 'Edit Room')

@section('content')
    <div class="max-w-2xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('admin.rooms.update', $room) }}">
            @method('PUT')
            @include('admin.rooms._form')
        </form>
    </div>
@endsection
