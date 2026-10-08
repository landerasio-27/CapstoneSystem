@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
    @php
        // The three table reports read the same records the register holds, so
        // they are offered to both office roles — an administrator and a
        // supply staff account open the same page.
        $reports = [
            [
                'title' => 'Inventory Summary',
                'route' => 'admin.reports.inventory-summary',
                'description' => 'Supplies and equipment stock levels, with low-stock flags.',
                'filters' => ['category', 'room_id'],
            ],
            [
                'title' => 'Supply Issuance',
                'route' => 'admin.reports.supply-issuance',
                'description' => 'Supplies issued against approved requests (RIS).',
                'filters' => ['date_from', 'date_to'],
            ],
            [
                'title' => 'Document Register',
                'route' => 'admin.reports.document-register',
                'description' => 'PAR / IAR / ICS / RIS document log.',
                'filters' => ['type', 'status_document', 'date_from', 'date_to'],
            ],
        ];
    @endphp

    {{-- The official forms are a different kind of output from the tables
         below: documents to sign and file, not figures to read. They are the
         one part of this page both office roles use. --}}
    <a href="{{ route('admin.reports.documents.index') }}"
       class="group mb-6 flex flex-wrap items-center gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-slate-50 text-sm font-bold tracking-wide text-slate-700">
            PAR
        </span>
        <div class="min-w-0 flex-1">
            <h3 class="font-semibold text-slate-800 group-hover:text-brand-800">PAR / ICS / RIS documents</h3>
            <p class="mt-1 text-sm text-slate-500">
                Generate, preview, print and export the official forms — Property Acknowledgement Receipt,
                Inventory Custodian Slip and Report of Supplies and Materials Issued — from the records on file.
            </p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700">
            Open
            <x-icon name="arrow-right" class="h-4 w-4" />
        </span>
    </a>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        @foreach ($reports as $report)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="font-semibold text-slate-800">{{ $report['title'] }}</h3>
                <p class="mb-4 text-sm text-slate-500">{{ $report['description'] }}</p>

                <form method="GET" action="{{ route($report['route']) }}" target="_blank" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        @if (in_array('category', $report['filters']))
                            <select name="category" class="col-span-2 rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                <option value="">All categories</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}">{{ $category }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if (in_array('room_id', $report['filters']))
                            <select name="room_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                <option value="">All rooms</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}">{{ $room->code }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if (in_array('teacher_id', $report['filters']))
                            <select name="teacher_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                <option value="">All custodians</option>
                                @foreach ($teachers as $teacher)
                                    <option value="{{ $teacher->id }}">{{ $teacher->full_name }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if (in_array('type', $report['filters']))
                            <select name="type" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                <option value="">All types</option>
                                <option value="PAR">PAR</option>
                                <option value="IAR">IAR</option>
                                <option value="ICS">ICS</option>
                                <option value="RIS">RIS</option>
                            </select>
                        @endif

                        @if (in_array('status_document', $report['filters']))
                            <select name="status" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                <option value="">All statuses</option>
                                <option value="draft">Draft</option>
                                <option value="approved">Approved</option>
                                <option value="issued">Issued</option>
                            </select>
                        @endif

                        @if (in_array('date_from', $report['filters']))
                            <input type="date" name="date_from" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="From">
                            <input type="date" name="date_to" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="To">
                        @endif
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" name="format" value="html" class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-600 hover:bg-slate-50"><x-icon name="file" class="h-3.5 w-3.5" /> View</button>
                        <button type="submit" name="format" value="pdf" class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-600 hover:bg-slate-50"><x-icon name="download" class="h-3.5 w-3.5" /> PDF</button>
                        <button type="submit" name="format" value="xlsx" class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg bg-brand-800 px-3 py-2 text-xs font-medium text-white hover:bg-brand-900"><x-icon name="download" class="h-3.5 w-3.5" /> Excel</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
@endsection
