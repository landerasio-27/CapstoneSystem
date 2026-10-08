{{--
    "Add Inventory to Room" — the sheet a room is filled from.

    Placing an item here does not create a second record: the equipment or supply
    row itself is pointed at this room (`room_id`), so the item leaves wherever it
    was and appears in this room's folder. The move is written to the room's
    history, which is where the location change is remembered.

    Quantity is shown, not typed: stock and price belong to the item's own
    recorded receipts (Add Stock / Add Quantity), so a location change never
    silently rewrites what is on hand.

    Expects: $room, $assignableEquipment, $assignableSupplies.
--}}
@php
    $assignable = $assignableEquipment->concat($assignableSupplies);
@endphp

<div id="add-item-modal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUModal.close('add-item-modal')"></div>

    <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-800 text-white">
                    <x-icon name="plus" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Add Inventory to Room</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        The item is added to {{ $room->code }} &mdash; {{ $room->name }} by naming this room as its location. No duplicate record is created.
                    </p>
                </div>
            </div>
            <button type="button" onclick="ISUModal.close('add-item-modal')"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <span class="sr-only">Close</span>
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>

        @if ($assignable->isEmpty())
            <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 ring-1 ring-slate-100">
                Everything in the register is already in this room. Add a supply or a piece of equipment first, then place it here.
            </p>

            <div class="mt-5 flex justify-end">
                <button type="button" onclick="ISUModal.close('add-item-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                    Close
                </button>
            </div>
        @else
            <form method="POST" action="{{ route('admin.rooms.items.store', $room) }}">
                @csrf
                {{-- Which sheet this submission came from: two sheets on one page
                     both carry `item` and `notes`, so the one that was refused
                     needs to be the one that reopens. --}}
                <input type="hidden" name="_form" value="add">

                {{-- Item Type: which half of the catalogue the picker is read
                     from. Both halves stay selectable, so the sheet works with
                     the script switched off. --}}
                <fieldset>
                    <legend class="mb-1 block text-sm font-medium text-slate-700">Item Type</legend>
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="radio" name="item_kind" value="equipment" @checked(old('item_kind', 'equipment') === 'equipment')
                                   class="js-item-kind border-slate-300 text-brand-600 focus:ring-brand-500">
                            Equipment / Property
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="radio" name="item_kind" value="supply" @checked(old('item_kind') === 'supply')
                                   class="js-item-kind border-slate-300 text-brand-600 focus:ring-brand-500">
                            Supply
                        </label>
                    </div>
                </fieldset>

                <div class="mt-4">
                    <label for="add-item-picker" class="mb-1 block text-sm font-medium text-slate-700">Item</label>
                    <select id="add-item-picker" name="item" class="js-searchable w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" data-modal-focus>
                        <option value="">Choose an item&hellip;</option>

                        <optgroup label="Equipment / Property">
                            @foreach ($assignableEquipment as $item)
                                <option value="equipment:{{ $item->id }}" data-kind="equipment" data-quantity="{{ $item->quantity_on_hand }} {{ $item->unit }}" @selected(old('_form') === 'add' && old('item') === 'equipment:'.$item->id)>
                                    {{ $item->item_code }} &mdash; {{ $item->name }}@if ($item->room) (currently in {{ $item->room->code }})@endif
                                </option>
                            @endforeach
                        </optgroup>

                        <optgroup label="Supplies">
                            @foreach ($assignableSupplies as $item)
                                <option value="supply:{{ $item->id }}" data-kind="supply" data-quantity="{{ $item->quantity_on_hand }} {{ $item->unit }}" @selected(old('_form') === 'add' && old('item') === 'supply:'.$item->id)>
                                    {{ $item->item_code }} &mdash; {{ $item->name }}@if ($item->room) (currently in {{ $item->room->code }})@endif
                                </option>
                            @endforeach
                        </optgroup>
                    </select>
                    @error('item') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="add-item-room" class="mb-1 block text-sm font-medium text-slate-700">Room</label>
                        <input id="add-item-room" type="text" value="{{ $room->code }} — {{ $room->name }}" disabled
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    </div>

                    <div>
                        <label for="add-item-quantity" class="mb-1 block text-sm font-medium text-slate-700">Quantity</label>
                        <input id="add-item-quantity" type="text" value="Choose an item" readonly
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    </div>
                </div>

                <p class="mt-2 text-xs text-slate-400">
                    Quantity is held on the item's own record and changes only through a recorded receipt (Add Stock / Add Quantity), so a location change never rewrites what is on hand.
                </p>

                <div class="mt-4">
                    <label for="add-item-notes" class="mb-1 block text-sm font-medium text-slate-700">Notes <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="add-item-notes" name="notes" rows="2"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">{{ old('_form') === 'add' ? old('notes') : '' }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" onclick="ISUModal.close('add-item-modal')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                        <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                        <x-icon name="plus" class="h-4 w-4" /> Add to Room
                    </button>
                </div>
            </form>

            {{-- The Item Type choice narrows the picker to that half of the
                 catalogue. It only hides options: the select still submits
                 whatever is chosen, and with the script off both halves are
                 listed and either can be picked. --}}
            <script>
                (function () {
                    var sheet = document.getElementById('add-item-modal');
                    if (!sheet) return;

                    var radios = sheet.querySelectorAll('.js-item-kind');
                    var picker = sheet.querySelector('#add-item-picker');

                    function apply() {
                        var chosen = sheet.querySelector('.js-item-kind:checked');
                        var kind = chosen ? chosen.value : 'equipment';

                        picker.querySelectorAll('option[data-kind]').forEach(function (option) {
                            var match = option.dataset.kind === kind;
                            option.hidden = !match;
                            option.disabled = !match;
                        });

                        var selected = picker.selectedOptions[0];
                        if (selected && selected.disabled) picker.value = '';
                        picker.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    picker.addEventListener('change', function () {
                        var selected = picker.selectedOptions[0];
                        sheet.querySelector('#add-item-quantity').value = selected && selected.dataset.quantity
                            ? selected.dataset.quantity : 'Choose an item';
                    });

                    radios.forEach(function (radio) {
                        radio.addEventListener('change', apply);
                    });

                    apply();
                })();
            </script>
        @endif
    </div>
</div>

@if (old('_form') === 'add' && $errors->any())
    <script>ISUModal.open('add-item-modal');</script>
@endif
