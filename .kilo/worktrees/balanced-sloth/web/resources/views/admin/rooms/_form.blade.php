@csrf

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Room Code</label>
        <input type="text" name="code" value="{{ old('code', $room->code ?? '') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Room Name</label>
        <input type="text" name="name" value="{{ old('name', $room->name ?? '') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Building</label>
        <input type="text" name="building" value="{{ old('building', $room->building ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Floor</label>
        <input type="text" name="floor" value="{{ old('floor', $room->floor ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Type</label>
        <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach (['classroom', 'laboratory', 'office', 'storage'] as $type)
                <option value="{{ $type }}" @selected(old('type', $room->type ?? '') === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-end">
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $room->is_active ?? true)) class="rounded border-slate-300">
            Active
        </label>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('admin.rooms.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="x-mark" class="h-4 w-4" /> Cancel</a>
    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Save</button>
</div>
