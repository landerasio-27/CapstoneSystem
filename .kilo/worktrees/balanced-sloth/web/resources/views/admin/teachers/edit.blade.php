@extends('layouts.admin')

@section('title', 'Edit Custodian Information')

@section('content')
    <div class="max-w-2xl">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Edit Custodian Information</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $teacher->full_name }}'s details. The documents and equipment in the folder are not touched.
            </p>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <form method="POST" action="{{ route('admin.teachers.update', $teacher) }}">
                @method('PUT')
                @include('admin.teachers._form')
            </form>
        </div>
    </div>
@endsection
