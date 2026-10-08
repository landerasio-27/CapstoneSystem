{{--
    PAR filters: which equipment the receipt covers, and the signature details
    the form prints. What is typed here is what the paper shows — the report
    never adds a field of its own.
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
        <span class="text-xs font-medium text-slate-500">PAR No.</span>
        <input type="text" name="document_no" value="{{ $form['document_no'] }}"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Date</span>
        <input type="date" name="document_date" value="{{ $form['document_date'] }}"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Accountable Person</span>
        <input type="text" name="accountable" value="{{ $form['accountable'] }}" list="custodian-names" placeholder="Person receiving the property"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Position</span>
        <input type="text" name="accountable_position" value="{{ $form['accountable_position'] }}"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Room / Office</span>
        <input type="text" name="room" value="{{ $form['room'] }}"
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
        <span class="text-xs font-medium text-slate-500">Received by</span>
        <input type="text" name="received_by" value="{{ $form['received_by'] }}"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Received by — Position</span>
        <input type="text" name="received_by_position" value="{{ $form['received_by_position'] }}"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>
</div>

<p class="mt-3 text-xs text-slate-400">
    The accountable person, position and room are filled in for you when every selected item is assigned to the same
    custodian; otherwise each item is named in the Accountable Person column and the header line is left for the office
    to write on. Amounts are the record's quantity at its unit cost — the form never lets one be typed.
</p>

<datalist id="custodian-names">
    @foreach ($custodians as $custodian)
        <option value="{{ $custodian->full_name }}"></option>
    @endforeach
</datalist>

@include('admin.reports.documents._equipment_picker', [
    'equipmentPicker' => $equipmentPicker,
    'selected' => $selected,
    'hasSelection' => $hasSelection,
    'preSelectViaIcs' => false,
])
