@extends('layouts.admin')

@section('title', 'PAR / ICS / RIS')

@section('content')
    @php
        /*
         * Three different papers, so three separate generators. They are grouped
         * the way the supply office files them: the two property forms on one
         * side, the monthly issue report on the other.
         */
        $groups = [
            'Property Documents' => [
                [
                    'code' => 'PAR',
                    'title' => 'Property Acknowledgement Receipt',
                    'route' => 'admin.reports.document.index',
                    'query' => ['type' => 'par'],
                    'description' => 'The receipt a person signs for the property handed to them. Built from the equipment records you select.',
                    'points' => ['Property number, description, unit and cost from the equipment record', 'Accountable person and room as assigned', 'Received from / received by signature block'],
                ],
                [
                    'code' => 'ICS',
                    'title' => 'Inventory Custodian Slip',
                    'route' => 'admin.reports.document.index',
                    'query' => ['type' => 'ics'],
                    'description' => 'One slip per custodian for the property they are accountable for, ready for their signature.',
                    'points' => ['Opens on the property already assigned through an ICS', 'Amount computed as quantity × unit cost', 'Inventory / property number per item'],
                ],
            ],
            'Supply Documents' => [
                [
                    'code' => 'RIS',
                    'title' => 'Report of Supplies and Materials Issued',
                    'route' => 'admin.reports.document.index',
                    'query' => ['type' => 'ris'],
                    'description' => 'The monthly report of everything the supply office issued, with the recapitulation and certification at the foot of the form.',
                    'points' => ['Month by month, from the RIS documents on file', 'Filter by end-user, stock no. or category', 'Recapitulation totals and Posted By / Date lines'],
                ],
                [
                    'code' => 'MR',
                    'title' => 'Monthly Report',
                    'route' => 'admin.reports.monthly.index',
                    'query' => [],
                    'description' => 'The same form, printed as one running report per paper — or left blank for the office to fill in by hand.',
                    'points' => ['One report per paper, with every end-user\'s lines running on down the page', 'Blank form with empty rows and the “Nothing Follows” line', 'Lines can be typed in; amounts are still computed'],
                ],
            ],
        ];
    @endphp

    <div class="mb-6 max-w-3xl">
        <p class="text-sm text-slate-500">
            Generate, preview, print and export the official university forms. Every line is read from a record that
            already exists — the equipment ledger for PAR and ICS, the RIS documents for RIS — and every amount is
            computed from quantity and unit cost, so the paper can never disagree with the records.
        </p>
    </div>

    @foreach ($groups as $group => $documents)
        <section class="mb-8">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">{{ $group }}</h2>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                @foreach ($documents as $document)
                    <a href="{{ route($document['route'], $document['query']) }}"
                       class="group flex h-full flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300">
                        <div class="mb-3 flex items-start gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-slate-50 text-sm font-bold tracking-wide text-slate-700">
                                {{ $document['code'] }}
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-800 group-hover:text-brand-800">{{ $document['title'] }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $document['description'] }}</p>
                            </div>
                        </div>

                        <ul class="mt-1 space-y-1.5 text-xs text-slate-500">
                            @foreach ($document['points'] as $point)
                                <li class="flex items-start gap-2">
                                    <x-icon name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-600" />
                                    <span>{{ $point }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <span class="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-medium text-brand-700">
                            Open generator
                            <x-icon name="arrow-right" class="h-4 w-4" />
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
