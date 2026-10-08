{{--
    "Edit Custodian Information" — the sheet the folder page edits the record
    from, so the office never leaves the folder to change a phone number.

    It changes the custodian, not the folder: the documents and equipment filed
    under them are untouched, and the employee number is the system's, so it is
    not offered here. The fields are the same ones the edit page draws, from
    admin.teachers._information_fields, so the two cannot drift apart.

    Expects: $teacher, $rooms.
--}}
@php
    // Only this sheet's own fields put it back on screen after a refused save:
    // the other sheets on the page have their own reasons to be open.
    $informationErrors = $errors->hasAny([
        'first_name', 'last_name', 'department', 'position', 'email', 'contact_number', 'room_id',
    ]);
@endphp

<div id="edit-information-modal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUModal.close('edit-information-modal')"></div>

    <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-800 text-white">
                    <x-icon name="pencil" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Edit Custodian Information</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $teacher->full_name }}'s details. The documents and equipment in the folder are not touched.
                    </p>
                </div>
            </div>
            <button type="button" onclick="ISUModal.close('edit-information-modal')"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <span class="sr-only">Close</span>
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('admin.teachers.update', $teacher) }}">
            @method('PUT')

            @include('admin.teachers._information_fields')

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="ISUModal.close('edit-information-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                    <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                    <x-icon name="check" class="h-4 w-4" /> Save
                </button>
            </div>
        </form>
    </div>
</div>

@if ($informationErrors)
    <script>ISUModal.open('edit-information-modal');</script>
@endif
