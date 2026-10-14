@csrf

{{-- Basic information only. Quantity on hand and unit cost are not editable
     here: they are recorded as receipts with the Add Quantity transaction, so
     every change keeps its own history row and its own price. --}}
<div class="mb-4 flex items-start gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500 ring-1 ring-slate-100">
    <x-icon name="archive" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
    <p>
        @isset($equipment)
            Quantity on hand ({{ $equipment->quantity_on_hand }} {{ $equipment->unit }}) and unit cost are managed through
            <a href="{{ route('admin.equipment.show', $equipment) }}" class="font-medium text-brand-700 hover:underline">Add Quantity</a>
            on the equipment details page, so every receipt keeps its own cost and date.
        @else
            The record is created without stock. Record the units on hand with <span class="font-medium text-slate-600">Add Quantity</span>
            on the details page once it is saved.
        @endisset
    </p>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Item Code</label>
        <input type="text" name="item_code" value="{{ old('item_code', $equipment->item_code ?? '') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('item_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Property Number</label>
        <input type="text" name="property_number" value="{{ old('property_number', $equipment->property_number ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('property_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        {{-- The manufacturer's serial, when the unit carries one: shown in the
             custodian's folder beside the property number. --}}
        <label class="mb-1 block text-sm font-medium text-slate-700">Serial Number</label>
        <input type="text" name="serial_number" value="{{ old('serial_number', $equipment->serial_number ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('serial_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
        <input type="text" name="name" value="{{ old('name', $equipment->name ?? '') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Category</label>
        <input type="text" name="category" value="{{ old('category', $equipment->category ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('category') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
        <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('description', $equipment->description ?? '') }}</textarea>
    </div>

    {{-- Equipment is always property: the consumable question belongs to the
         supply form, so it is not asked here. The unit cost the receipts record
         decides between a PAR and an ICS. --}}
    <x-item-classification :consumable="false" :selectable="false"
                           :unit-cost="old('unit_cost', $equipment->unit_cost ?? 0)" scol />

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Unit</label>
        <input type="text" name="unit" value="{{ old('unit', $equipment->unit ?? 'unit') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('unit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Room / Storage Room</label>
        <select name="room_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">No room</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->id }}" @selected(old('room_id', $equipment->room_id ?? '') == $room->id)>{{ $room->name }}</option>
            @endforeach
        </select>
        @error('room_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Assign To Custodian / Teacher</label>
        <select name="teacher_id" class="js-searchable w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">No custodian</option>
            @foreach ($custodians as $custodian)
                <option value="{{ $custodian->id }}" @selected(old('teacher_id', $equipment->teacher_id ?? '') == $custodian->id)>{{ $custodian->full_name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-400">The person this property is accountable to &mdash; it appears in their folder.</p>
        @error('teacher_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
        <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $equipment->status ?? 'available') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Date Acquired</label>
        <input type="date" name="date_acquired" value="{{ old('date_acquired', isset($equipment) ? $equipment->date_acquired?->format('Y-m-d') : '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('date_acquired') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ isset($equipment) ? route('admin.equipment.show', $equipment) : route('admin.equipment.index') }}"
       class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="x-mark" class="h-4 w-4" /> Cancel</a>
    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Save Changes</button>
</div>
