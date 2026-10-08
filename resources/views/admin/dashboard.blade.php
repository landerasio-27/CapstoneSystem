@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    @php
        $peso = fn ($value) => '₱' . number_format((float) $value, 2);

        // Tone maps shared by the sections below (dark mode remaps these classes).
        $badges = [
            'brand' => 'bg-brand-50 text-brand-700',
            'amber' => 'bg-amber-50 text-amber-700',
            'red' => 'bg-red-50 text-red-600',
            'sky' => 'bg-sky-50 text-sky-700',
            'indigo' => 'bg-indigo-50 text-indigo-600',
            'slate' => 'bg-slate-100 text-slate-600',
        ];

        $trendArrows = ['up' => '↑', 'down' => '↓', 'stable' => '→', 'no_previous_data' => '•'];
        $trendLabels = [
            'up' => 'Increasing',
            'down' => 'Decreasing',
            'stable' => 'Stable',
            'no_previous_data' => 'No previous data',
        ];
        $trendClasses = [
            'up' => 'bg-red-50 text-red-600',
            'down' => 'bg-brand-50 text-brand-700',
            'stable' => 'bg-slate-100 text-slate-600',
            'no_previous_data' => 'bg-slate-100 text-slate-400',
        ];
    @endphp

    <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Welcome back, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h2>
            <p class="text-sm text-slate-500">
                {{ now()->format('l, F j, Y') }} &middot; what the supply office holds, what it costs, and where prices are moving.
            </p>
        </div>
        <p class="text-xs text-slate-400 sm:text-right">
            Every figure below is calculated from recorded stock transactions and property documents.
        </p>
    </div>

    @if (trim((string) auth()->user()->position) === '')
        {{-- A newly created account signs nothing until it says who it signs as:
             the position is printed under the name on every RIS, monthly report
             and custodian slip this office issues, so the first thing its
             dashboard asks for is the one thing only its owner can write. --}}
        <div class="mb-4 flex flex-col gap-3 rounded-xl bg-amber-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                <div>
                    <p class="text-sm font-medium text-amber-900">Set your position</p>
                    <p class="text-xs text-amber-800">
                        Your account holds none yet, and it is what prints under your name on the reports this office signs.
                    </p>
                </div>
            </div>

            <a href="{{ route('admin.account-settings.index', ['section' => 'profile']) }}"
               class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                <x-icon name="user" class="h-4 w-4" /> Set your position
            </a>
        </div>
    @endif

    @include('admin.dashboard._summary')
    @include('admin.dashboard._filters')
    @include('admin.dashboard._stock')

    <div id="item-analytics" class="scroll-mt-20">
        @include('admin.dashboard._item')
    </div>

    @include('admin.dashboard._monthly')
    @include('admin.dashboard._costs')
    {{-- No activity feed here: the dashboard is for analytics, and the full audit
         trail lives on the Activity Logs page under Insights. --}}
@endsection
