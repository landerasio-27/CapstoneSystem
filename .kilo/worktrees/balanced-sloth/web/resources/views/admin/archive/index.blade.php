@extends('layouts.admin')

@section('title', 'Archive')

@section('content')
    {{-- Archive years: the shelves records are filed under. --}}
    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-sm font-semibold text-slate-700">Archive years</h2>
        <p class="mt-0.5 text-xs text-slate-400">
            Add a year, then file PAR / ICS / RIS documents and Stock / Property Cards under it. Archived records
            leave the working lists but keep their items, quantities, movements and printed copies.
        </p>

        <form method="POST" action="{{ route('admin.archive.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label for="year" class="mb-1 block text-xs font-medium text-slate-500">Year</label>
                <input id="year" type="number" name="year" min="1900" max="2100" required
                       value="{{ old('year', now()->year) }}"
                       class="w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                @error('year') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="min-w-[12rem] flex-1">
                <label for="notes" class="mb-1 block text-xs font-medium text-slate-500">Note (optional)</label>
                <input id="notes" type="text" name="notes" value="{{ old('notes') }}" placeholder="e.g. Old office records"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                <x-icon name="plus" class="h-4 w-4" /> Add Year
            </button>
        </form>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($archives as $archive)
                {{-- A year is permanent: records are filed under it and can only
                     leave by being restored, never by the year disappearing. --}}
                <a href="{{ route('admin.archive.show', $archive) }}"
                   class="group block rounded-xl border border-slate-200 p-4 transition hover:border-brand-300 hover:bg-brand-50/40">
                    <p class="flex items-center gap-2 text-base font-semibold text-slate-800 group-hover:text-brand-700">
                        <x-icon name="folder" class="h-4 w-4 text-slate-400 group-hover:text-brand-600" />
                        {{ $archive->year }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $archive->documents_count }} document(s) &middot; {{ $archive->cards_count }} card(s)
                    </p>
                    @if ($archive->notes)
                        <p class="mt-1 truncate text-xs text-slate-400">{{ $archive->notes }}</p>
                    @endif
                </a>
            @empty
                <p class="text-sm text-slate-400">No archive years yet — add one above to start filing records.</p>
            @endforelse
        </div>
    </div>

    @include('admin.archive._register')
@endsection
