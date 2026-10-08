{{--
    The equipment the PAR and ICS reports are built from.

    A plain checkbox list rather than a single-select dropdown: both papers can
    cover several items at once, and the submitted ids are exactly what the
    report reads. $preSelectViaIcs opens the ICS list on the property already
    assigned through an ICS; PAR opens with nothing ticked.

    Expects: $equipmentPicker, $selected, $hasSelection, $preSelectViaIcs.
--}}
@php
    $selectedIds = array_map('intval', $selected ?? []);
    $preSelectViaIcs = $preSelectViaIcs ?? false;

    // The submitted ids decide what stays ticked; before anything is submitted
    // the ICS list starts on the property that came through an ICS document.
    $isChecked = function (array $item) use ($selectedIds, $hasSelection, $preSelectViaIcs): bool {
        return $hasSelection
            ? in_array((int) $item['id'], $selectedIds, true)
            : ($preSelectViaIcs && $item['via_ics']);
    };
@endphp

<div data-picker>
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-medium text-slate-600">
            Select equipment
            <span class="font-normal text-slate-400">— {{ $equipmentPicker->count() }} on file</span>
        </p>

        @if ($equipmentPicker->isNotEmpty())
            <div class="flex items-center gap-2">
                <input type="search" data-picker-search placeholder="Filter by name, code, custodian…"
                       class="w-56 rounded-lg border border-slate-300 px-2 py-1 text-xs">
                <button type="button" data-picker-all class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">Select all</button>
                <button type="button" data-picker-none class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">Clear</button>
            </div>
        @endif
    </div>

    @if ($equipmentPicker->isEmpty())
        <p class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-xs text-slate-400">
            No equipment on file yet. Equipment is added under Inventory &rarr; Equipment.
        </p>
    @else
        <div class="max-h-72 overflow-y-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($equipmentPicker as $item)
                        <tr data-picker-row data-picker-text="{{ \Illuminate\Support\Str::lower($item['name'].' '.$item['item_code'].' '.$item['property_number'].' '.$item['custodian'].' '.$item['room']) }}">
                            <td class="w-8 px-3 py-2 align-top">
                                <input type="checkbox" name="equipment[]" value="{{ $item['id'] }}"
                                       data-picker-checkbox @checked($isChecked($item))
                                       class="rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                            </td>
                            <td class="px-2 py-2 align-top">
                                <p class="font-medium text-slate-700">
                                    {{ $item['name'] }}
                                    @if ($item['via_ics'])
                                        <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-normal text-slate-500">via ICS</span>
                                    @endif
                                </p>
                                <p class="text-slate-400">
                                    {{ $item['property_number'] ?: '—' }}
                                    @if ($item['item_code'])
                                        &middot; {{ $item['item_code'] }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-2 py-2 align-top text-slate-500">{{ $item['custodian'] ?: '—' }}</td>
                            <td class="px-3 py-2 align-top text-right text-slate-400">{{ $item['room'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
