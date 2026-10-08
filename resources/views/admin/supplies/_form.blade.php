@csrf

@if (! isset($supply))
    <p class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
        Quantity on hand and unit cost are not set here. After saving, record incoming stock with
        <span class="font-medium text-slate-700">+ Add Stock</span> on the supply page so every addition is logged in the stock history.
    </p>
@endif

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Item Code</label>
        <input type="text" name="item_code" value="{{ old('item_code', $supply->item_code ?? '') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('item_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
        <input type="text" name="name" value="{{ old('name', $supply->name ?? '') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
        <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('description', $supply->description ?? '') }}</textarea>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Category</label>
        <input type="text" name="category" value="{{ old('category', $supply->category ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Unit</label>
        <input type="text" name="unit" value="{{ old('unit', $supply->unit ?? 'unit') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
    </div>

    {{-- The consumable answer decides the paper (RIS), and for property the
         unit cost decides between PAR and ICS. --}}
    <x-item-classification :consumable="old('is_consumable', $supply->is_consumable ?? true)"
                           :unit-cost="old('unit_cost', $supply->unit_cost ?? 0)" scol />

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Reorder Level</label>
        <input type="number" name="reorder_level" min="0" value="{{ old('reorder_level', $supply->reorder_level ?? 0) }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <p class="mt-1 text-xs text-slate-400">Minimum quantity that signals the need to re-order this item.</p>
        @error('reorder_level') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Storage Room</label>
        <select name="room_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">No room</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->id }}" @selected(old('room_id', $supply->room_id ?? '') == $room->id)>{{ $room->code }} &mdash; {{ $room->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-end">
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supply->is_active ?? true)) class="rounded border-slate-300">
            Active
        </label>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('admin.supplies.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="x-mark" class="h-4 w-4" /> Cancel</a>
    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Save</button>
</div>
