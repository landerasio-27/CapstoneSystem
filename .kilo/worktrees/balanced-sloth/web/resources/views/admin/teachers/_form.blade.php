@csrf

@include('admin.teachers._information_fields')

<div class="mt-6 flex justify-end gap-3">
    {{-- Cancelling an edit returns to the folder it was opened from; a new
         record has no folder yet, so it returns to the grid. --}}
    <a href="{{ isset($teacher) ? route('admin.teachers.show', $teacher) : route('admin.teachers.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="x-mark" class="h-4 w-4" /> Cancel</a>
    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Save</button>
</div>
