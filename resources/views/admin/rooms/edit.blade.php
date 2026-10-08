@extends('layouts.admin')

@section('title', 'Edit Room')

@section('content')
    <div class="max-w-2xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-slate-800">Edit Room</h2>
            <p class="mt-1 text-sm text-slate-500">
                What the room is. What is inside it is read from the items themselves &mdash; add and transfer those from the room's own page.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.rooms.update', $room) }}">
            @method('PUT')
            @include('admin.rooms._form')
        </form>
    </div>
@endsection
