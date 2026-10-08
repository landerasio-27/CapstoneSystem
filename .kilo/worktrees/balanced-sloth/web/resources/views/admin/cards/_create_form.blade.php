{{--
    The "new card" form for one card type (SC or PC).

    Used in two places so both entrances stay identical: step two of the modal on
    the Cards page, and the standalone /admin/cards/create?type=… page. Item lists
    come from the real Supplies/Equipment records — nothing is typed twice.

    @param string $cardType   'SC' or 'PC'
    @param iterable $options  supplies (SC) or equipment (PC) that have no card yet
    @param array $defaults    entity name + fund cluster defaults
    @param bool $inModal      cancel closes the modal instead of leaving the page
--}}
@php
    $isStockCard = $cardType === 'SC';
    $options = $options ?? collect();
    $inModal = $inModal ?? false;
    // Only the form the visitor was actually filling gets its values back.
    $wasSubmitted = old('type') === $cardType;
    $selected = $wasSubmitted ? old('item') : null;
@endphp

<form method="POST" action="{{ route('admin.cards.store') }}" data-card-form data-card-type="{{ $cardType }}">
    @csrf
    <input type="hidden" name="type" value="{{ $cardType }}">

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-100">
            {{ $errors->first() }}
        </div>
    @endif

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700" for="item-{{ $cardType }}">
            {{ $isStockCard ? 'Supply item' : 'Equipment item' }}
        </label>

        @if ($options->isEmpty())
            {{-- Two ways to have nothing to offer: the item is already carded, or
                 it has no stock on hand yet (stock arrives through Add Stock /
                 Add Quantity, so an item with none has nothing to record). --}}
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-100">
                No {{ $isStockCard ? 'supply' : 'equipment item' }} can start a new card: each one either
                already has its {{ $isStockCard ? 'Stock' : 'Property' }} Card or has no stock on hand yet.
            </p>
        @else
            <select name="item" id="item-{{ $cardType }}" required
                    class="js-searchable w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <option value="">Choose an item…</option>
                @foreach ($options as $option)
                    @php
                        $value = ($isStockCard ? 'supply:' : 'equipment:') . $option->id;
                        $code = $isStockCard ? $option->item_code : $option->property_number;
                        $reorder = $isStockCard ? $option->reorder_level : null;
                    @endphp
                    <option value="{{ $value }}"
                            data-name="{{ $option->name }}"
                            data-code="{{ $code ?: '—' }}"
                            data-description="{{ $option->description ?: '—' }}"
                            data-unit="{{ $option->unit }}"
                            data-reorder="{{ $reorder }}"
                            @selected($selected === $value)>
                        {{ $option->name }}{{ $code ? " ({$code})" : '' }}
                    </option>
                @endforeach
            </select>
        @endif
    </div>

    {{-- Auto-populated from the chosen record; nothing here is typed twice. --}}
    <div data-card-preview class="mt-4 hidden rounded-lg bg-slate-50 p-4 ring-1 ring-slate-100">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Card details from the item record</p>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-slate-500">{{ $isStockCard ? 'Stock No.' : 'Property Number' }}</dt>
                <dd class="font-medium text-slate-800" data-field="code">—</dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ $isStockCard ? 'Item' : 'Property, Plant and Equipment' }}</dt>
                <dd class="font-medium text-slate-800" data-field="name">—</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-slate-500">Description</dt>
                <dd class="text-slate-700" data-field="description">—</dd>
            </div>
            <div>
                <dt class="text-slate-500">Unit of Measurement</dt>
                <dd class="font-medium text-slate-800" data-field="unit">—</dd>
            </div>
            @if ($isStockCard)
                <div>
                    <dt class="text-slate-500">Existing Re-order Point</dt>
                    <dd class="font-medium text-slate-800" data-field="reorder">—</dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="mb-1 block text-sm font-medium text-slate-700" for="entity_name-{{ $cardType }}">Entity Name</label>
            <input type="text" name="entity_name" id="entity_name-{{ $cardType }}" required
                   value="{{ $wasSubmitted ? old('entity_name') : $defaults['entity_name'] }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700" for="fund_cluster-{{ $cardType }}">Fund Cluster</label>
            <input type="text" name="fund_cluster" id="fund_cluster-{{ $cardType }}" placeholder="e.g. 01"
                   value="{{ $wasSubmitted ? old('fund_cluster') : $defaults['fund_cluster'] }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        </div>

        @if ($isStockCard)
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="reorder_point-{{ $cardType }}">Re-order Point</label>
                <input type="number" name="reorder_point" id="reorder_point-{{ $cardType }}" min="0"
                       value="{{ $wasSubmitted ? old('reorder_point') : '' }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <p class="mt-1 text-xs text-slate-400">Defaults to the reorder level already on the supply record.</p>
            </div>
        @endif
    </div>

    <div class="mt-6 flex items-center justify-end gap-2">
        @if ($inModal)
            <button type="button" onclick="ISUNewCard.close()"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
        @else
            <a href="{{ route('admin.cards.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
        @endif
        <button type="submit" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
            Create Card
        </button>
    </div>
</form>
