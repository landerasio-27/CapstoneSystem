@extends('layouts.admin')

@section('title', 'Edit User')

@section('content')
    <div class="max-w-2xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @method('PUT')
            @include('admin.users._form')
        </form>
    </div>
@endsection
