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

    // Every line is a real inventory record: the picker is the only way in, and
    // the name, unit, cost and consumable answer all come from the record that
    // is picked.
    //
    // Which paper this line belongs on. The catalogue entry knows; a line whose
    // item has left the catalogue between two renders is looked up so the row
    // still shows the classification the server will apply.
    $entry = $catalogue->firstWhere('key', $selectedItem);
    $rowType = $entry?->classification;

    if (! $rowType && preg_match('/^(supply|equipment):(\d+)$/', (string) $selectedItem, $linked)) {
        $record = $linked[1] === 'supply' ? \App\Models\Supply::find($linked[2]) : \App\Models\Equipment::find($linked[2]);
        $rowType = $record ? \App\Services\ItemClassifier::forItem($record) : null;
    }

    // A paper opened for one classification (a custodian's folder, or the edit
    // form of a stored paper) offers only its own items, so the fallback keeps
    // the row on that paper.
    $rowType = $rowType ?: $type;
    $meta = $rowType ? \App\Services\ItemClassifier::labels()[$rowType] : null;
    $itemPlaceholder = $catalogue->isEmpty() ? 'No items with stock on hand yet' : 'Select an item';
@endphp
<div class="item-row relative rounded-lg border border-slate-200 p-3 pr-8" data-classification="{{ $rowType ?? '' }}">
    <button type="button" onclick="this.closest('.item-row').remove(); window.isuDocumentPanel && window.isuDocumentPanel();"
            class="absolute right-2 top-2 text-lg leading-none text-red-500 hover:text-red-700" title="Remove item">&times;</button>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
        <div class="sm:col-span-6">
            <label class="mb-1 block text-xs font-medium text-slate-500">Item</label>

            {{-- Only the catalogue is listed, grouped by the paper each item
                 belongs on; the classification travels on the option so the row
                 badge, the extras and the panel can follow the pick. Nothing is
                 typed in by hand, so every line names a real inventory record. --}}
            <select name="items[{{ $i }}][item]" required
                    class="js-searchable w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <option value="">{{ $itemPlaceholder }}</option>

                @foreach ($catalogue->groupBy('classification') as $groupType => $entries)
                    <optgroup label="{{ $groupType }} — {{ \App\Services\ItemClassifier::summaryLabel($groupType) }}">
                        @foreach ($entries as $option)
                            <option value="{{ $option->key }}" data-classification="{{ $option->classification }}" data-consumable="{{ $option->consumable ? '1' : '0' }}" @selected($selectedItem === $option->key)>{{ $option->name }} ({{ $option->code }}) · ₱{{ number_format($option->unit_cost, 2) }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>

            @error("items.{$i}.item") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="mb-1 block text-xs font-medium text-slate-500">{{ $rowType === 'RIS' ? 'Req. Qty' : 'Quantity' }}</label>
            <input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ old("items.{$i}.quantity", $item->quantity ?? 1) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        </div>

        {{-- The paper the line is recorded on: the office reads it here rather
             than choosing it, and the rule is the one in ItemClassifier. --}}
        <div class="sm:col-span-4">
            <label class="mb-1 block text-xs font-medium text-slate-500">Classified As</label>
            <div class="flex items-center gap-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2">
                <span data-row-badge data-base-class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $meta['tone'] ?? 'bg-slate-100 text-slate-500 ring-slate-200' }}"
                      title="{{ $meta['label'] ?? 'Pick an item to classify this line' }}">{{ $rowType ?? '—' }}</span>
                <span class="text-xs text-slate-500" data-row-summary>{{ $meta['summary'] ?? 'Pick an item' }}</span>
            </div>
            <p class="mt-1 text-xs text-slate-400" data-row-reason>{{ $meta['reason'] ?? '' }}</p>
        </div>
    </div>

    {{-- What the paper records, per classification: a PAR a date acquired, an
         ICS the estimated useful life, a RIS the issue details. Only the line's
         own set is shown. --}}
    <div class="{{ $rowType === 'PAR' ? '' : 'hidden' }}" data-extra="PAR">
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-12">
            <div class="sm:col-span-4">
                <label class="mb-1 block text-xs font-medium text-slate-500">Date Acquired</label>
                <input type="date" name="items[{{ $i }}][date_acquired]" value="{{ old("items.{$i}.date_acquired", $item?->date_acquired?->format('Y-m-d')) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
        </div>
    </div>

    <div class="{{ $rowType === 'ICS' ? '' : 'hidden' }}" data-extra="ICS">
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-12">
            <div class="sm:col-span-4">
                <label class="mb-1 block text-xs font-medium text-slate-500">Estimated Useful Life</label>
                <input type="text" name="items[{{ $i }}][estimated_useful_life]" value="{{ old("items.{$i}.estimated_useful_life", $item->estimated_useful_life ?? '') }}" placeholder="e.g. 5 years"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
        </div>
    </div>

    <div class="{{ $rowType === 'RIS' ? '' : 'hidden' }}" data-extra="RIS">
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-12">
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
            <div class="sm:col-span-8">
                <label class="mb-1 block text-xs font-medium text-slate-500">Remarks</label>
                <input type="text" name="items[{{ $i }}][item_remarks]" value="{{ old("items.{$i}.item_remarks", $item->item_remarks ?? '') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
        </div>
    </div>
</div>
