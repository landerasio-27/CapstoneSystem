{{--
    "Transfer Item" — the sheet a room is emptied through, item by item.

    A transfer is one update to the item's own `room_id`, so it leaves this room
    and appears in the destination in the same moment. The reason is chosen from
    a fixed list and the move is written to both rooms' history; the papers
    already raised for the item are left exactly as they were filed.

    Expects: $room, $roomItems, $destinations.
--}}
<div id="transfer-item-modal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUModal.close('transfer-item-modal')"></div>

    <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-800 text-white">
                    <x-icon name="swap" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Transfer Item</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Move an item out of {{ $room->code }} and into another room. Its current room is updated; nothing is copied and no paper is rewritten.
                    </p>
                </div>
            </div>
            <button type="button" onclick="ISUModal.close('transfer-item-modal')"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <span class="sr-only">Close</span>
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>

        @if ($roomItems->isEmpty())
            <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 ring-1 ring-slate-100">
                This room is empty &mdash; there is nothing in it to transfer out.
            </p>

            <div class="mt-5 flex justify-end">
                <button type="button" onclick="ISUModal.close('transfer-item-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                    Close
                </button>
            </div>
        @else
            <form method="POST" action="{{ route('admin.rooms.transfer', $room) }}">
                @csrf
                <input type="hidden" name="_form" value="transfer">

                <label for="transfer-item-picker" class="mb-1 block text-sm font-medium text-slate-700">Item</label>
                <select id="transfer-item-picker" name="item" class="js-searchable w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" data-modal-focus>
                    <option value="">Choose an item in this room&hellip;</option>
                    @foreach ($roomItems as $item)
                        <option value="{{ $item instanceof \App\Models\Equipment ? 'equipment:' : 'supply:' }}{{ $item->id }}"
                                @selected(old('_form') === 'transfer' && old('item') === ($item instanceof \App\Models\Equipment ? 'equipment:' : 'supply:').$item->id)>
                            {{ $item->item_code }} &mdash; {{ $item->name }}
                        </option>
                    @endforeach
                </select>
                @error('item') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="transfer-current-room" class="mb-1 block text-sm font-medium text-slate-700">Current Room</label>
                        <input id="transfer-current-room" type="text" value="{{ $room->code }} — {{ $room->name }}" disabled
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    </div>

                    <div>
                        <label for="transfer-to-room" class="mb-1 block text-sm font-medium text-slate-700">Transfer To</label>
                        <select id="transfer-to-room" name="to_room_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Choose a room&hellip;</option>
                            @foreach ($destinations as $destination)
                                <option value="{{ $destination->id }}" @selected((string) old('to_room_id') === (string) $destination->id)>
                                    {{ $destination->code }} &mdash; {{ $destination->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('to_room_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <label for="transfer-reason" class="mb-1 block text-sm font-medium text-slate-700">Reason</label>
                    <select id="transfer-reason" name="reason" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select reason&hellip;</option>
                        @foreach (\App\Models\RoomMovement::REASONS as $value => $label)
                            <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4">
                    <label for="transfer-notes" class="mb-1 block text-sm font-medium text-slate-700">Notes <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="transfer-notes" name="notes" rows="2"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">{{ old('_form') === 'transfer' ? old('notes') : '' }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" onclick="ISUModal.close('transfer-item-modal')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                        <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                        <x-icon name="swap" class="h-4 w-4" /> Transfer
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>

@if (old('_form') === 'transfer' && $errors->any())
    <script>ISUModal.open('transfer-item-modal');</script>
@endif
