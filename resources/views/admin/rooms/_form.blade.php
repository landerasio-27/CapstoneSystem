@csrf

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label for="room-code" class="mb-1 block text-sm font-medium text-slate-700">Room Number</label>
        <input id="room-code" type="text" name="code" value="{{ old('code', $room->code ?? '') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="room-name" class="mb-1 block text-sm font-medium text-slate-700">Room Name</label>
        <input id="room-name" type="text" name="name" value="{{ old('name', $room->name ?? '') }}" required
               placeholder="e.g. Computer Laboratory 1"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="room-building" class="mb-1 block text-sm font-medium text-slate-700">Building</label>
        <input id="room-building" type="text" name="building" value="{{ old('building', $room->building ?? '') }}"
               placeholder="e.g. CCS Building"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('building') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="room-floor" class="mb-1 block text-sm font-medium text-slate-700">Floor</label>
        <input id="room-floor" type="text" name="floor" value="{{ old('floor', $room->floor ?? '') }}"
               placeholder="e.g. 1st Floor"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('floor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="room-department" class="mb-1 block text-sm font-medium text-slate-700">Department / College</label>
        <input id="room-department" type="text" name="department" value="{{ old('department', $room->department ?? '') }}"
               placeholder="e.g. College of Computing Studies"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('department') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="room-type" class="mb-1 block text-sm font-medium text-slate-700">Room Type</label>
        <select id="room-type" name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Choose a room type&hellip;</option>
            @foreach (\App\Models\Room::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $room->type ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="room-description" class="mb-1 block text-sm font-medium text-slate-700">Description</label>
        <textarea id="room-description" name="description" rows="3"
                  placeholder="What the room is used for, who keeps it, anything the office should know."
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">{{ old('description', $room->description ?? '') }}</textarea>
        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="room-status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
        @php
            $activeValue = old('is_active', ($room->is_active ?? true) ? '1' : '0');
        @endphp
        <select id="room-status" name="is_active" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="1" @selected((string) $activeValue === '1')>Active</option>
            <option value="0" @selected((string) $activeValue === '0')>Inactive</option>
        </select>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('admin.rooms.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="x-mark" class="h-4 w-4" /> Cancel</a>
    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Save Room</button>
</div>
