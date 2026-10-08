@extends('layouts.admin')

@section('title', 'New Custodian Folder')

@section('content')
    <div class="max-w-2xl">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">New Custodian Folder</h2>
            <p class="mt-1 text-sm text-slate-500">
                The record below is the folder: every PAR, ICS, RIS and piece of equipment assigned to this person is filed under it automatically.
            </p>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <form method="POST" action="{{ route('admin.teachers.store') }}">
                @include('admin.teachers._form')
            </form>
        </div>
    </div>
@endsection
