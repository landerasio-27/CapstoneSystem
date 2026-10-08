@extends('layouts.admin')

@section('title', 'Edit ' . $card->card_no)

@section('content')
    {{-- The layout links back to the register, and Cancel returns to this card,
         so no second back link is needed here. --}}
    <div class="mx-auto max-w-2xl">
        <form method="POST" action="{{ route('admin.cards.update', $card) }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @csrf
            @method('PUT')

            <h2 class="text-base font-semibold text-slate-800">Edit {{ $card->typeLabel() }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                Only the card header can be corrected here. Posted transactions are history and cannot be changed —
                post an adjustment instead.
            </p>

            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-100">{{ $errors->first() }}</div>
            @endif

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Item</label>
                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-100">
                        {{ $card->itemName() }}
                        <span class="text-slate-400">· {{ $card->itemCode() ?: 'no code on record' }}</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ $card->isStockCard()
                            ? 'Item, description, unit and stock number come from the Supplies record.'
                            : 'PPE, description and property number come from the Equipment record.' }}
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="entity_name">Entity Name</label>
                    <input type="text" name="entity_name" id="entity_name" required value="{{ old('entity_name', $card->entity_name) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="fund_cluster">Fund Cluster</label>
                    <input type="text" name="fund_cluster" id="fund_cluster" value="{{ old('fund_cluster', $card->fund_cluster) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                </div>

                @if ($card->isStockCard())
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="reorder_point">Re-order Point</label>
                        <input type="number" name="reorder_point" id="reorder_point" min="0" value="{{ old('reorder_point', $card->reorder_point) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    </div>
                @endif
            </div>

            <div class="mt-6 flex items-center justify-end gap-2">
                <a href="{{ route('admin.cards.show', $card) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">Save changes</button>
            </div>
        </form>
    </div>
@endsection
