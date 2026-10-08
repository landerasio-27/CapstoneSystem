@extends('layouts.admin')

@section('title', 'Add Card')

@section('content')
    @if ($type === null)
        {{-- The type chooser. New cards are opened automatically with their item,
             so this page serves the items that predate that: it is reached by a
             direct link rather than from the register. --}}
        <div class="mx-auto max-w-3xl">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-semibold text-slate-800">Create New Card</h2>
                <p class="mt-1 text-sm text-slate-500">What type of card do you want to create?</p>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <a href="{{ route('admin.cards.create', ['type' => 'SC']) }}"
                       class="group rounded-xl border border-slate-200 p-6 text-center transition hover:border-brand-300 hover:bg-brand-50/40">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                            <x-icon name="archive" class="h-6 w-6" />
                        </span>
                        <p class="mt-3 font-semibold text-slate-800">Stock Card</p>
                        <p class="text-sm text-slate-500">For supplies</p>
                        <p class="mt-2 text-xs uppercase tracking-wider text-slate-400">SC</p>
                    </a>

                    <a href="{{ route('admin.cards.create', ['type' => 'PC']) }}"
                       class="group rounded-xl border border-slate-200 p-6 text-center transition hover:border-brand-300 hover:bg-brand-50/40">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                            <x-icon name="box" class="h-6 w-6" />
                        </span>
                        <p class="mt-3 font-semibold text-slate-800">Property Card</p>
                        <p class="text-sm text-slate-500">For PPE / assets</p>
                        <p class="mt-2 text-xs uppercase tracking-wider text-slate-400">PC</p>
                    </a>
                </div>
            </div>
        </div>
    @else
        @php $isStockCard = $type === 'SC'; @endphp

        <div class="mx-auto max-w-3xl">
            <div class="mb-4">
                <a href="{{ route('admin.cards.create') }}" class="inline-flex items-center text-sm text-slate-500 hover:text-slate-700 hover:underline">
                    Change card type
                </a>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-semibold text-slate-800">
                    New {{ $isStockCard ? 'Stock Card (SC)' : 'Property Card (PC)' }}
                </h2>
                <p class="mt-1 mb-4 text-sm text-slate-500">
                    {{ $isStockCard
                        ? 'The card documents one supply. Item, description, unit and stock number come from the Supplies record.'
                        : 'The card documents one piece of equipment. PPE, description and property number come from the Equipment record.' }}
                </p>

                @include('admin.cards._create_form', [
                    'cardType' => $type,
                    'options' => $isStockCard ? $supplies : $equipment,
                    'defaults' => $defaults,
                ])
            </div>
        </div>

        @include('admin.cards._create_script')
    @endif
@endsection
