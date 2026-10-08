@extends('layouts.admin')

@section('title', 'Custodian Folders')

@section('content')
    @php
        $isSearching = filled(request('search'));
        $folderCount = $teachers->total();
    @endphp

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Custodian Folders</h2>
            <p class="text-sm text-slate-500">
                Every custodian and teacher with the documents and equipment filed under them &mdash; the folder is those records, so nothing has to be filed twice.
            </p>
        </div>

        <button type="button" onclick="ISUModal.open('create-folder-modal')"
                class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
            <x-icon name="plus" class="h-4 w-4" /> New Folder
        </button>
    </div>

    {{-- Search and sorting apply as they change (see resources/js/filter-bar.js);
         the button is there for the reader who presses Enter instead. --}}
    <form method="GET" data-auto-filter class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex flex-1 gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search folders..."
                   class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                <x-icon name="search" class="h-4 w-4" /> Search
            </button>
        </div>

        <label class="flex items-center gap-2 text-xs font-medium text-slate-500">
            Sort
            <select name="sort" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-700">
                <option value="name_asc" @selected($sort === 'name_asc')>Name A&ndash;Z</option>
                <option value="name_desc" @selected($sort === 'name_desc')>Name Z&ndash;A</option>
                <option value="recent" @selected($sort === 'recent')>Recently Updated</option>
                <option value="equipment" @selected($sort === 'equipment')>Most Equipment</option>
                <option value="documents" @selected($sort === 'documents')>Most Documents</option>
            </select>
        </label>
    </form>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        @if ($isSearching)
            <a href="{{ route('admin.teachers.index') }}" class="text-xs text-slate-500 hover:text-slate-700">Clear search</a>
        @endif

        <span class="ml-auto text-xs text-slate-400">{{ $folderCount }} {{ $folderCount === 1 ? 'folder' : 'folders' }}</span>
    </div>

    @if ($teachers->isEmpty())
        <div class="mt-4 rounded-xl bg-white p-10 text-center ring-1 ring-slate-200">
            <x-folder-icon class="mx-auto h-14 w-16" />
            <p class="mt-4 font-medium text-slate-700">
                {{ $isSearching ? 'No custodian folders match this search.' : 'No custodian folders yet' }}
            </p>
            <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                {{ $isSearching
                    ? 'Try another name, employee ID, department or room — or clear the search.'
                    : 'A folder is created with the custodian record: add the person and their documents and equipment gather themselves into it.' }}
            </p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                @if ($isSearching)
                    <a href="{{ route('admin.teachers.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                        Clear search
                    </a>
                @endif
                <button type="button" onclick="ISUModal.open('create-folder-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                    <x-icon name="plus" class="h-4 w-4" /> New Folder
                </button>
            </div>
        </div>
    @else
        {{-- Files in a cabinet: many folders across, small and close together the
             way a row of folders in a drawer is, so the grid reads as one cabinet
             rather than as cards in a table. --}}
        <div class="mt-4 grid grid-cols-3 gap-x-3 gap-y-4 sm:grid-cols-4 lg:grid-cols-6 2xl:grid-cols-8">
            @foreach ($teachers as $teacher)
                @php
                    $updatedAt = $teacher->lastActivityAt();
                    $documentCount = (int) $teacher->documents_count;
                @endphp

                {{-- A folder, drawn the way a folder is drawn: the illustration
                     with its name under it and no card drawn around them, so the
                     grid reads as a cabinet rather than a table of cells. What is
                     inside is what opening it shows, so the tile stays a label on
                     a folder — and the figures stay one hover away on the
                     tooltip. The lift and the name are the whole of the hover:
                     there is no border to light up. --}}
                <div data-href="{{ route('admin.teachers.show', $teacher) }}"
                     title="{{ $teacher->full_name }} — {{ $documentCount }} {{ $documentCount === 1 ? 'document' : 'documents' }}, {{ $teacher->equipment_count }} equipment · updated {{ $updatedAt->diffForHumans() }}{{ $teacher->is_active ? '' : ' · Inactive' }}"
                     class="group relative flex cursor-pointer flex-col items-center px-1 py-2 text-center">
                    {{-- The folder fills its cell up to the size a folder in this
                         cabinet is drawn at, and takes its height from its own
                         proportions, so it shrinks with the grid instead of
                         standing out of it. --}}
                    <x-folder-icon class="w-full max-w-28 shrink-0 transition group-hover:-translate-y-1" />

                    <p class="mt-2 line-clamp-2 text-sm font-semibold text-slate-800 transition group-hover:text-brand-800" title="{{ $teacher->full_name }}">{{ $teacher->full_name }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $teachers->links() }}</div>
    @endif

    @include('admin.teachers._folder_modal')

    {{-- A click anywhere on a folder card opens it. A click that lands on a link
         or a control inside the card is left alone: Edit and Open Folder carry
         their own destination, and a reader dragging across the card to copy an
         employee ID gets the text rather than a page change. --}}
    <script>
        document.querySelectorAll('[data-href]').forEach(function (card) {
            card.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, select, label')) return;
                if (String(window.getSelection()).trim() !== '') return;

                window.location.href = card.dataset.href;
            });
        });
    </script>
@endsection
