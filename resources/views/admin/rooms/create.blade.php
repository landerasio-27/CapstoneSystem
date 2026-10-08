@extends('layouts.admin')

@section('title', 'Create New Room')

@section('content')
    <div class="max-w-2xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-slate-800">Create New Room</h2>
            <p class="mt-1 text-sm text-slate-500">
                A room starts empty. Once it is on the register, open it and add the equipment and supplies that are physically in it.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.rooms.store') }}">
            @include('admin.rooms._form')
        </form>
    </div>
@endsection
