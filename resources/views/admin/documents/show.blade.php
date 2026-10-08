@extends('layouts.admin')

@section('title', "{$document->type} {$document->document_no}")

@php
    $badges = ['draft' => 'bg-slate-100 text-slate-600', 'approved' => 'bg-sky-50 text-sky-700', 'issued' => 'bg-brand-50 text-brand-700'];
@endphp

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        {{-- Header + actions --}}
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-base font-semibold text-slate-800">{{ $document->type }} &mdash; {{ $document->document_no }}</h2>
                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $badges[$document->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($document->status) }}</span>
                </div>

                <div class="flex flex-wrap gap-2">
                    {{-- Edit only while the document is still a draft (not yet approved). --}}
                    @if ($document->status === 'draft')
                        <a href="{{ route('admin.documents.edit', $document) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                            <x-icon name="pencil" class="h-4 w-4" /> Edit
                        </a>
                    @endif

                    <a href="{{ route('admin.documents.pdf', ['document' => $document, 'download' => 1]) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                        <x-icon name="download" class="h-4 w-4" /> Download
                    </a>

                    {{-- PAR/ICS/RIS also download as a 2-page bundle: the source doc + an
                         auto-generated IAR (page 1 = this doc, page 2 = IAR). --}}
                    @if (in_array($document->type, ['PAR', 'ICS', 'RIS'], true))
                        <a href="{{ route('admin.documents.bundle-pdf', ['document' => $document, 'download' => 1]) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-brand-300 bg-brand-50 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-100">
                            <x-icon name="download" class="h-4 w-4" /> Download {{ $document->type }} + IAR
                        </a>
                    @endif

                    @if ($document->status === 'draft')
                        <form method="POST" action="{{ route('admin.documents.approve', $document) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-sky-500 px-4 py-2 text-sm font-medium text-white hover:bg-sky-400"><x-icon name="check" class="h-4 w-4" /> Approve</button>
                        </form>
                    @endif

                    @if ($document->status === 'approved')
                        <form method="POST" action="{{ route('admin.documents.issue', $document) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="send" class="h-4 w-4" /> Issue</button>
                        </form>
                    @endif
                </div>
            </div>

            <p class="mt-2 text-xs text-slate-500">
                {{ $document->custodian_name ?: 'No custodian' }} &middot;
                {{ $document->room?->name ?? 'No room' }} &middot;
                {{ $document->document_date->format('M d, Y') }} &middot;
                Prepared by {{ $document->preparedBy?->name ?? '—' }}
                @if ($document->approvedBy) &middot; Approved by {{ $document->approvedBy->name }} @endif
            </p>
        </div>

        {{-- The actual document layout (PAR / IAR / ICS / RIS) rendered inline.
             For PAR/ICS/RIS the preview shows the same 2-page bundle you download
             (source doc + auto-generated IAR) so preview matches the download. --}}
        @php
            $previewRoute = in_array($document->type, ['PAR', 'ICS', 'RIS'], true)
                ? route('admin.documents.bundle-pdf', $document)
                : route('admin.documents.pdf', $document);
        @endphp
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <iframe src="{{ $previewRoute }}#toolbar=0"
                    title="{{ $document->type }} {{ $document->document_no }} document"
                    class="h-[1100px] w-full" style="border: 0;"></iframe>
        </div>
    </div>
@endsection
