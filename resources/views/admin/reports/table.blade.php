@extends('layouts.admin')

@section('title', $title)

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ count($rows) }} record(s)</p>
        {{-- The report is read here and leaves the office as a PDF or a
             spreadsheet: printing the page would put the application's own
             chrome on the paper, and either export is the same figures. --}}
        <div class="flex gap-2">
            <a href="{{ url()->full().(str_contains(url()->full(), '?') ? '&' : '?').'format=pdf' }}" target="_blank"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="download" class="h-4 w-4" /> Export PDF</a>
            <a href="{{ url()->full().(str_contains(url()->full(), '?') ? '&' : '?').'format=xlsx' }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-3 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="download" class="h-4 w-4" /> Export Excel</a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    @foreach ($headings as $heading)
                        <th class="px-4 py-3 text-left font-medium text-slate-500">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td class="px-4 py-3 text-slate-700">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($headings) }}" class="px-4 py-8 text-center text-slate-400">No data found for the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
