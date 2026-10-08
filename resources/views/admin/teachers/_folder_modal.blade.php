@php
    // Errors belong in this sheet only when the sheet was what was submitted; the
    // other forms on the page share these field names and show them themselves.
    $folderErrors = old('form') === 'create-folder' && $errors->any();
@endphp

{{-- 
    "Create Custodian Folder" — the sheet that stands over the grid.

    A folder is the person, so filling this in is naming the custodian: the
    fields are the same ones the custodian form takes (drawn from
    admin.teachers._information_fields, so the two cannot drift), and the person
    and their folder come into being together. A custodian who is already in the
    directory needs no sheet at all — their folder is on the grid, and opening it
    is what shows their papers and their property. Expects: $rooms.
--}}
<div id="create-folder-modal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUModal.close('create-folder-modal')"></div>

    <div class="relative max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <x-folder-icon class="h-10 w-11 shrink-0" />
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Create Custodian Folder</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Name the custodian or teacher. Their PAR, ICS, RIS and assigned equipment are gathered into the folder automatically.
                    </p>
                </div>
            </div>
            <button type="button" onclick="ISUModal.close('create-folder-modal')"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <span class="sr-only">Close</span>
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('admin.teachers.folder.store') }}">
            @csrf
            <input type="hidden" name="form" value="create-folder">

            {{-- $teacher is passed empty on purpose: the grid above loops with a
                 variable of that name, and an include would otherwise fill this
                 brand-new person's fields with the last custodian on the page. --}}
            @include('admin.teachers._information_fields', [
                'only' => ['first_name', 'last_name', 'department', 'position', 'room_id'],
                'teacher' => null,
            ])

            <p class="mt-4 text-xs text-slate-400">
                Email, contact number and the rest are on the folder's own page, under Edit Information.
            </p>

            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="ISUModal.close('create-folder-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                    <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                    {{-- A button keeps the monochrome glyph: the gold folder is
                         the folder itself, not a label on a green control. --}}
                    <x-icon name="folder" class="h-4 w-4" /> Create Folder
                </button>
            </div>
        </form>
    </div>
</div>

@if ($folderErrors)
    <script>
        // The sheet was submitted and refused: put the reader back in it, with
        // what they typed still in place.
        ISUModal.open('create-folder-modal');
    </script>
@endif
