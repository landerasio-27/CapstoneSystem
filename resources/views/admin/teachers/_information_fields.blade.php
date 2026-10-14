{{-- The custodian's own fields, drawn once for every place they are edited: the
     create and edit forms, the folder page's "Edit Custodian Information" sheet
     and the grid's "Create Custodian Folder" sheet. A caller that wants only
     some of them passes $only, so a short form is a selection of these fields
     rather than a second copy of the markup that drifts.

     Expects: $rooms, and $teacher when an existing record is being changed.
     Optional: $only — the keys to draw, in the order they are drawn here.
     The employee ID is not among them: the record is given the next number in
     the office's own series when it is created, and keeps it. --}}
@php
    $fields = $only ?? ['first_name', 'last_name', 'department', 'position', 'email', 'contact_number', 'room_id', 'is_active'];
@endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    @if (in_array('first_name', $fields, true))
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">First Name</label>
            <input type="text" name="first_name" value="{{ old('first_name', $teacher->first_name ?? '') }}" required
                   data-modal-focus
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    @if (in_array('last_name', $fields, true))
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Last Name</label>
            <input type="text" name="last_name" value="{{ old('last_name', $teacher->last_name ?? '') }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    @if (in_array('department', $fields, true))
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Department</label>
            <input type="text" name="department" value="{{ old('department', $teacher->department ?? '') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            @error('department') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    @if (in_array('position', $fields, true))
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Position</label>
            <input type="text" name="position" value="{{ old('position', $teacher->position ?? '') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            @error('position') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    @if (in_array('email', $fields, true))
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" value="{{ old('email', $teacher->email ?? '') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    @if (in_array('contact_number', $fields, true))
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Contact Number</label>
            <input type="text" name="contact_number" value="{{ old('contact_number', $teacher->contact_number ?? '') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            @error('contact_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    @if (in_array('room_id', $fields, true))
        {{-- Alone in the grid when the caller draws no Active checkbox, so the
             room is not left as half a row with nothing beside it. --}}
        <div class="{{ in_array('is_active', $fields, true) ? '' : 'sm:col-span-2' }}">
            <label class="mb-1 block text-sm font-medium text-slate-700">Assigned Room</label>
            <select name="room_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">No room</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}" @selected(old('room_id', $teacher->room_id ?? '') == $room->id)>{{ $room->name }}</option>
                @endforeach
            </select>
            @error('room_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    @if (in_array('is_active', $fields, true))
        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $teacher->is_active ?? true)) class="rounded border-slate-300">
                Active
            </label>
        </div>
    @endif
</div>
