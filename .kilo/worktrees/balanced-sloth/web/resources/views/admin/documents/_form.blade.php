@php
    $doc = $doc ?? null;
    $isEdit = $doc !== null;
    // The number the backend would assign if the field is left blank; shown as
    // a placeholder so it is a suggestion, never a submitted value (two people
    // may have the form open at once).
    $suggestedDocumentNo = $suggestedDocumentNo ?? null;
    // A custodian picked before the form was opened (from their folder) or a
    // re-submit after a validation error.
    $preselectedCustodian = $preselectedCustodian ?? null;
    $v = fn ($key, $default = null) => old($key, $doc?->{$key} ?? $default);
    // Rows come from the just-submitted input after a validation error, otherwise
    // from the document being edited. (Without this, a failed submit that only
    // tripped the validation rules would silently drop every item row.)
    $oldItems = old('items');
    $items = $oldItems !== null
        ? collect($oldItems)->map(function ($row) {
            $row = (object) $row;
            // Old input carries the date as a string; the row partial formats it.
            $row->date_acquired = filled($row->date_acquired ?? null) ? \Illuminate\Support\Carbon::parse($row->date_acquired) : null;

            return $row;
        })
        : ($isEdit ? $doc->items : collect());
    $descriptions = [
        'PAR' => 'Property Acknowledgement Receipt — records property issued to a custodian.',
        'IAR' => 'Inspection and Acceptance Report — records inspection & acceptance of delivered items.',
        'ICS' => 'Inventory Custodian Slip — records low-value semi-expendable items issued to a custodian.',
        'RIS' => 'Requisition and Issue Slip — records requested and issued supplies.',
    ];
@endphp

<div class="max-w-5xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <form method="POST" action="{{ $action }}">
        @csrf
        @if ($method !== 'POST') @method($method) @endif

        {{-- Document header: type, date, entity, fund cluster, custodian & room in a 3-column grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Document Type</label>
                @if ($isEdit)
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="text" value="{{ $type }}" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                @else
                    <select name="type" onchange="window.location.href='{{ route('admin.documents.create') }}?type=' + this.value"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        {{-- IAR is not created directly; it is auto-generated as page 2 of the PAR/ICS/RIS download. --}}
                        @foreach (['PAR', 'ICS', 'RIS'] as $docType)
                            <option value="{{ $docType }}" @selected($type === $docType)>{{ $docType }}</option>
                        @endforeach
                    </select>
                @endif
                <p class="mt-1 text-xs text-slate-400">{{ $descriptions[$type] ?? '' }}</p>
            </div>

            {{-- The PAR / ICS / RIS number: type your own or leave it blank and
                 the next number for the type is assigned on save. On an existing
                 document it can be corrected here (the paper was misnumbered,
                 the office renumbered it), but it must stay unique. --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Document No.</label>
                @if ($isEdit)
                    <input type="text" name="document_no" value="{{ old('document_no', $doc->document_no) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-400">Change it only to correct the number printed on the paper.</p>
                    @error('document_no') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @else
                    <input type="text" name="document_no" value="{{ old('document_no') }}"
                           placeholder="{{ $suggestedDocumentNo ?: 'Auto-generated' }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-400">Leave blank to use the next number automatically.</p>
                    @error('document_no') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @endif
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Document Date</label>
                <input type="date" name="document_date" required
                       value="{{ old('document_date', $doc?->document_date?->format('Y-m-d') ?? now()->toDateString()) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Entity Name</label>
                <input type="text" name="entity_name" value="{{ $v('entity_name', 'Isabela State University - Cauayan Campus') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Fund Cluster</label>
                <input type="text" name="fund_cluster" value="{{ $v('fund_cluster') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div class="sm:col-span-2 lg:col-span-1">
                <label class="mb-1 block text-sm font-medium text-slate-700">Assign To Custodian / Teacher</label>
                <select name="teacher_id" class="js-searchable w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">No custodian</option>
                    @foreach ($custodians as $custodian)
                        <option value="{{ $custodian->id }}" @selected($v('teacher_id', $preselectedCustodian) == $custodian->id)>{{ $custodian->full_name }}</option>
                    @endforeach
                </select>
                {{-- The choice files the document: this is what puts the saved PAR
                     / ICS / RIS into that custodian's folder by itself. --}}
                <p class="mt-1 text-xs text-slate-400">Saved papers are filed into this custodian's folder automatically.</p>
                @error('teacher_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Room</label>
                <select name="room_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">None</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected($v('room_id') == $room->id)>{{ $room->code }} &mdash; {{ $room->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Signatories + IAR details, side by side. Blank signatory falls back
             to the linked teacher's name. "Requested By" only prints on the RIS
             layout; "Received By" prints on PAR, ICS and RIS. --}}
        @if (in_array($type, ['PAR', 'ICS', 'RIS'], true))
            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
            <div class="rounded-lg bg-slate-50 p-4">
                <h3 class="mb-1 text-sm font-semibold text-slate-700">Signatories</h3>
                <p class="mb-3 text-xs text-slate-400">Leave blank to use the custodian's name.</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Requested By</label>
                        <input type="text" name="requested_by" value="{{ $v('requested_by') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Received By</label>
                        <input type="text" name="received_by" value="{{ $v('received_by') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Inspection Officer</label>
                        <input type="text" name="inspection_officer" value="{{ $v('inspection_officer') }}"
                               placeholder="Printed on the Inspection Officer line of the IAR"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            </div>

            {{-- IAR details: captured here so the auto-generated IAR (page 2 of the
                 "{{ $type }} + IAR" download) shows supplier, PO and invoice info
                 instead of leaving them blank. --}}
            <div class="rounded-lg bg-slate-50 p-4">
                <h3 class="mb-1 text-sm font-semibold text-slate-700">IAR Details</h3>
                <p class="mb-3 text-xs text-slate-400">Used for the auto-generated Inspection &amp; Acceptance Report (page 2 of the {{ $type }} + IAR download). Optional.</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Supplier</label>
                        <input type="text" name="supplier" value="{{ $v('supplier') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">PO No.</label>
                        <input type="text" name="po_no" value="{{ $v('po_no') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">PO Date</label>
                        <input type="date" name="po_date" value="{{ old('po_date', $doc?->po_date?->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Invoice No.</label>
                        <input type="text" name="invoice_no" value="{{ $v('invoice_no') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Invoice Date</label>
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', $doc?->invoice_date?->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Acceptance</label>
                        <select name="acceptance_status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">—</option>
                            <option value="complete" @selected($v('acceptance_status') === 'complete')>Completed</option>
                            <option value="partial" @selected($v('acceptance_status') === 'partial')>Partial</option>
                        </select>
                    </div>
                </div>
            </div>
            </div>
        @endif

        {{-- IAR-specific header (only reachable when editing a legacy IAR document) --}}
        @if ($type === 'IAR')
            <div class="mt-4 rounded-lg bg-slate-50 p-4">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Inspection &amp; Acceptance Details</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Supplier</label>
                        <input type="text" name="supplier" value="{{ $v('supplier') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Requisition Office / Department</label>
                        <input type="text" name="requisition_office" value="{{ $v('requisition_office') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">PO No.</label>
                        <input type="text" name="po_no" value="{{ $v('po_no') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">PO Date</label>
                        <input type="date" name="po_date" value="{{ old('po_date', $doc?->po_date?->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Invoice No.</label>
                        <input type="text" name="invoice_no" value="{{ $v('invoice_no') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Invoice Date</label>
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', $doc?->invoice_date?->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Requisition Center Code</label>
                        <input type="text" name="requisition_center_code" value="{{ $v('requisition_center_code') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div></div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Date Inspected</label>
                        <input type="date" name="date_inspected" value="{{ old('date_inspected', $doc?->date_inspected?->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Date Received</label>
                        <input type="date" name="date_received" value="{{ old('date_received', $doc?->date_received?->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Acceptance</label>
                        <select name="acceptance_status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">—</option>
                            <option value="complete" @selected($v('acceptance_status') === 'complete')>Completed</option>
                            <option value="partial" @selected($v('acceptance_status') === 'partial')>Partial</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Inspection Officer</label>
                        <input type="text" name="inspection_officer" value="{{ $v('inspection_officer') }}"
                               placeholder="Printed on the Inspection Officer signature line"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
        @endif

        {{-- RIS-specific header --}}
        @if ($type === 'RIS')
            <div class="mt-4 rounded-lg bg-slate-50 p-4">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Requisition Details</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Division</label>
                        <input type="text" name="division" value="{{ $v('division') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Office</label>
                        <input type="text" name="office" value="{{ $v('office') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Responsibility Center Code</label>
                        <input type="text" name="responsibility_center_code" value="{{ $v('responsibility_center_code') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Purpose</label>
                        <textarea name="purpose" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $v('purpose') }}</textarea>
                    </div>
                </div>
            </div>
        @endif

        {{-- Remarks (not RIS, which uses Purpose) --}}
        @if ($type !== 'RIS')
            <div class="mt-4">
                <label class="mb-1 block text-sm font-medium text-slate-700">Remarks</label>
                <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $v('remarks') }}</textarea>
            </div>
        @endif

        {{-- Items --}}
        <div class="mt-6">
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-700">Items</h3>
                <button type="button" onclick="addItemRow()" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50"><x-icon name="plus" class="h-3.5 w-3.5" /> Add Item</button>
            </div>
            @error('items') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror

            <div id="item-rows" class="space-y-3">
                @forelse ($items as $idx => $item)
                    @include('admin.documents._item_row', ['type' => $type, 'index' => $idx, 'equipment' => $equipment, 'supplies' => $supplies, 'item' => $item])
                @empty
                    @include('admin.documents._item_row', ['type' => $type, 'index' => 0, 'equipment' => $equipment, 'supplies' => $supplies])
                @endforelse
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ $isEdit ? route('admin.documents.show', $doc) : route('admin.documents.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="x-mark" class="h-4 w-4" /> Cancel</a>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> {{ $submitLabel }}</button>
        </div>
    </form>
</div>

<template id="item-row-template">
    @include('admin.documents._item_row', ['type' => $type, 'index' => '__INDEX__', 'equipment' => $equipment, 'supplies' => $supplies])
</template>

<script>
    let itemIndex = {{ max(1, $items->count()) }};
    function addItemRow() {
        const html = document.getElementById('item-row-template').innerHTML.replaceAll('__INDEX__', itemIndex++);
        document.getElementById('item-rows').insertAdjacentHTML('beforeend', html);
        // New rows carry a searchable item picker too.
        if (window.isuSearchable) window.isuSearchable.rescan(document.getElementById('item-rows'));
    }
</script>
