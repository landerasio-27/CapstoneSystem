@php
    $i = $index;
    $item = $item ?? null;

    // The picker carries both kinds of item as "supply:12" / "equipment:5".
    // (Old input rows are plain objects, so the linked ids are read defensively.)
    $linkedSupply = $item->supply_id ?? null;
    $linkedEquipment = $item->equipment_id ?? null;
    $selectedItem = old("items.{$i}.item") ?? match (true) {
        (bool) $linkedSupply => 'supply:' . $linkedSupply,
        (bool) $linkedEquipment => 'equipment:' . $linkedEquipment,
        default => '',
    };

    // What this document type records decides what the picker lists (a PAR is
    // property, an ICS/RIS is supplies), so only the offered catalogue is shown.
    $offersSupplies = $supplies->isNotEmpty();
    $offersEquipment = $equipment->isNotEmpty();
    $itemPlaceholder = match (true) {
        $offersSupplies && $offersEquipment => 'Select supply or equipment',
        $offersEquipment => 'Select equipment',
        $offersSupplies => 'Select supply',
        // Nothing is offered until an item has stock on hand.
        default => 'No items with stock on hand yet',
    };
@endphp
<div class="item-row relative rounded-lg border border-slate-200 p-3 pr-8">
    <button type="button" onclick="this.closest('.item-row').remove()"
            class="absolute right-2 top-2 text-lg leading-none text-red-500 hover:text-red-700" title="Remove item">&times;</button>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
        <div class="sm:col-span-6">
            <label class="mb-1 block text-xs font-medium text-slate-500">Item</label>
            {{-- Only the catalogue this document type records is listed: a PAR is
                 a property receipt, an ICS/RIS records supplies.
                 js-searchable: type-to-filter when the list is long. --}}
            <select name="items[{{ $i }}][item]" required
                    class="js-searchable w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <option value="">{{ $itemPlaceholder }}</option>

                @if ($offersSupplies)
                    <optgroup label="Supplies">
                        @foreach ($supplies as $sup)
                            <option value="supply:{{ $sup->id }}" @selected($selectedItem === 'supply:' . $sup->id)>{{ $sup->name }} ({{ $sup->item_code }})</option>
                        @endforeach
                    </optgroup>
                @endif

                @if ($offersEquipment)
                    <optgroup label="Equipment">
                        @foreach ($equipment as $eqp)
                            <option value="equipment:{{ $eqp->id }}" @selected($selectedItem === 'equipment:' . $eqp->id)>{{ $eqp->name }} ({{ $eqp->item_code }})</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
        </div>

        <div class="sm:col-span-2">
            <label class="mb-1 block text-xs font-medium text-slate-500">{{ $type === 'RIS' ? 'Req. Qty' : 'Quantity' }}</label>
            <input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ old("items.{$i}.quantity", $item->quantity ?? 1) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        </div>

        @if ($type === 'PAR')
            <div class="sm:col-span-4">
                <label class="mb-1 block text-xs font-medium text-slate-500">Date Acquired</label>
                <input type="date" name="items[{{ $i }}][date_acquired]" value="{{ old("items.{$i}.date_acquired", $item?->date_acquired?->format('Y-m-d')) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
        @elseif ($type === 'ICS')
            <div class="sm:col-span-4">
                <label class="mb-1 block text-xs font-medium text-slate-500">Estimated Useful Life</label>
                <input type="text" name="items[{{ $i }}][estimated_useful_life]" value="{{ old("items.{$i}.estimated_useful_life", $item->estimated_useful_life ?? '') }}" placeholder="e.g. 5 years"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
        @elseif ($type === 'RIS')
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-slate-500">Stock Available</label>
                <select name="items[{{ $i }}][stock_available]"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <option value="">—</option>
                    <option value="yes" @selected(old("items.{$i}.stock_available", $item->stock_available ?? '') === 'yes')>Yes</option>
                    <option value="no" @selected(old("items.{$i}.stock_available", $item->stock_available ?? '') === 'no')>No</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-slate-500">Issue Qty</label>
                <input type="number" min="0" name="items[{{ $i }}][issued_quantity]" value="{{ old("items.{$i}.issued_quantity", $item->issued_quantity ?? '') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
            <div class="sm:col-span-12">
                <label class="mb-1 block text-xs font-medium text-slate-500">Remarks</label>
                <input type="text" name="items[{{ $i }}][item_remarks]" value="{{ old("items.{$i}.item_remarks", $item->item_remarks ?? '') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
        @endif
    </div>
</div>
