{{--
    "Assign Equipment" — the sheet the folder page assigns property from.

    Saving sets the equipment's own custodian (`teacher_id`), which is what puts
    it in the folder: no copy of the item is filed here, and no second record is
    created. An item already held by somebody else is offered too, so a transfer
    is the same single change. Expects: $teacher, $assignable.
--}}
<div id="assign-equipment-modal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUModal.close('assign-equipment-modal')"></div>

    <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-800 text-white">
                    <x-icon name="box" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Assign Equipment</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        The item appears in {{ $teacher->full_name }}'s folder because it is assigned to them &mdash; nothing is duplicated.
                    </p>
                </div>
            </div>
            <button type="button" onclick="ISUModal.close('assign-equipment-modal')"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <span class="sr-only">Close</span>
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>

        @if ($assignable->isEmpty())
            <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 ring-1 ring-slate-100">
                Every piece of equipment in the register is already assigned to this custodian.
            </p>

            <div class="mt-5 flex justify-end">
                <button type="button" onclick="ISUModal.close('assign-equipment-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                    Close
                </button>
            </div>
        @else
            <form method="POST" action="{{ route('admin.teachers.equipment.store', $teacher) }}">
                @csrf

                <label for="assign-equipment-item" class="mb-1 block text-sm font-medium text-slate-700">Equipment</label>
                <select id="assign-equipment-item" name="equipment_id" class="js-searchable w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" data-modal-focus>
                    <option value="">Choose equipment&hellip;</option>
                    @foreach ($assignable as $item)
                        <option value="{{ $item->id }}" @selected((string) old('equipment_id') === (string) $item->id)>
                            {{ $item->name }} &mdash; {{ $item->item_code }}@if ($item->teacher) (with {{ $item->teacher->full_name }})@endif
                        </option>
                    @endforeach
                </select>
                @error('equipment_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                <p class="mt-3 text-xs text-slate-400">
                    Assign To: <span class="font-medium text-slate-600">{{ $teacher->full_name }}</span>
                </p>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" onclick="ISUModal.close('assign-equipment-modal')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                        <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                        <x-icon name="box" class="h-4 w-4" /> Assign Equipment
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>

@if ($errors->has('equipment_id'))
    <script>ISUModal.open('assign-equipment-modal');</script>
@endif
