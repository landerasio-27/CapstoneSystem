{{--
    ICS filters: the property the slip covers. The list opens on the equipment
    already assigned through an ICS document, and each custodian's items print
    as their own slip.
--}}
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Entity Name</span>
        <input type="text" name="entity_name" value="{{ $form['entity_name'] }}"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Fund Cluster</span>
        <input type="text" name="fund_cluster" value="{{ $form['fund_cluster'] }}" placeholder="e.g. 101"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">ICS No.</span>
        <input type="text" name="document_no" value="{{ $form['document_no'] }}"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Date</span>
        <input type="date" name="document_date" value="{{ $form['document_date'] }}"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Received from (supply office)</span>
        <input type="text" name="received_from" value="{{ $form['received_from'] }}"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Received from — Position</span>
        <input type="text" name="received_from_position" value="{{ $form['received_from_position'] }}"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Custodian position (fallback)</span>
        <input type="text" name="received_by_position" value="{{ $form['received_by_position'] }}" placeholder="Used when the custodian has no position on file"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>
</div>

@include('admin.reports.documents._equipment_picker', [
    'equipmentPicker' => $equipmentPicker,
    'selected' => $selected,
    'hasSelection' => $hasSelection,
    'preSelectViaIcs' => true,
])
