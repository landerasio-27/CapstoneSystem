@extends('layouts.admin')

@section('title', $equipment->name)

@section('content')
@php
    $classification = $equipment->classification();
    $hasReceipts = $movements->total() > 0;

    // The status reads as a dot and a word rather than a third row of text.
    $statusTone = match ($equipment->status) {
        'available' => 'bg-brand-600',
        'issued' => 'bg-sky-500',
        'damaged', 'misplaced' => 'bg-amber-500',
        'missing' => 'bg-red-500',
        default => 'bg-slate-400',
    };

    // One field in the grid: a small label, the value a step below it.
    $label = 'text-xs font-medium text-slate-500';
    $value = 'mt-1 truncate text-sm text-slate-700';
    $strong = 'mt-1 truncate text-sm font-semibold text-slate-900';
    $code = 'mt-1 truncate font-mono text-[13px] font-semibold text-slate-800';
    $link = 'font-medium text-brand-700 hover:underline';
@endphp

    {{-- "Back to Equipment" comes from the layout's contextual link, so it is
         not repeated here. The QR label is reached from the equipment list and
         the QR Codes page, so it is not repeated as a card on this page
         either.

         The record is laid out the way the office reads it off the form: four
         compact columns of label and value, the description on a line of its
         own, and the three figures that move — quantity, unit cost and total
         value — closed off in a short bar at the foot of the card. --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        {{-- One line, the action at its right, and no room taken beyond it. --}}
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2">
            <h2 class="truncate text-sm font-semibold text-slate-800">Equipment Information</h2>

            <div class="flex shrink-0 items-center gap-2">
                @if ($card)
                    <a href="{{ route('admin.cards.show', $card) }}"
                       class="hidden items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-50 sm:inline-flex"
                       title="{{ $card->card_no }} — view this item's Property Card">
                        <x-icon name="cards" class="h-3.5 w-3.5" /> {{ $card->card_no }}
                    </a>
                @endif
                <button type="button" onclick="ISUAddQuantity.show()"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md bg-brand-600 px-3 text-xs font-semibold text-white transition hover:bg-brand-900">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Add Quantity
                </button>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-3.5 px-4 py-3.5 lg:grid-cols-4">
            <div class="min-w-0">
                <dt class="{{ $label }}">Item Code</dt>
                <dd class="{{ $code }}" title="{{ $equipment->item_code }}">{{ $equipment->item_code }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Property No.</dt>
                <dd class="{{ $code }}" title="{{ $equipment->property_number ?? '—' }}">{{ $equipment->property_number ?? '—' }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Name</dt>
                <dd class="{{ $strong }}" title="{{ $equipment->name }}">{{ $equipment->name }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Category</dt>
                <dd class="{{ $value }}" title="{{ $equipment->category ?? '—' }}">{{ $equipment->category ?? '—' }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Serial Number</dt>
                <dd class="{{ $equipment->serial_number ? 'mt-1 truncate font-mono text-[13px] text-slate-700' : 'mt-1 text-sm text-slate-400' }}"
                    title="{{ $equipment->serial_number ?? '—' }}">{{ $equipment->serial_number ?? '—' }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Unit</dt>
                <dd class="{{ $value }}">{{ $equipment->unit }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Custodian</dt>
                <dd class="{{ $value }}">
                    @if ($equipment->teacher)
                        {{-- The folder is where this person's property is read together. --}}
                        <a href="{{ route('admin.teachers.show', $equipment->teacher) }}" class="{{ $link }}">{{ $equipment->custodian_name }}</a>
                    @else
                        <span class="{{ $equipment->custodian_name ? '' : 'text-slate-400' }}">{{ $equipment->custodian_name ?: '—' }}</span>
                    @endif
                </dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Room</dt>
                <dd class="{{ $value }}">
                    @if ($equipment->room)
                        <a href="{{ route('admin.rooms.show', $equipment->room) }}" class="{{ $link }}">{{ $equipment->room->name }}</a>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Status</dt>
                <dd class="mt-1 flex items-center gap-1.5 text-sm font-medium text-slate-800">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $statusTone }}"></span>
                    {{ ucfirst($equipment->status) }}
                </dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Date Acquired</dt>
                <dd class="{{ $value }}">{{ $equipment->date_acquired?->format('M d, Y') ?? '—' }}</dd>
            </div>

            {{-- The paper the item belongs on, and in two words why. The whole
                 name of the paper and the rule behind it sit in the tooltip. --}}
            <div class="col-span-2 min-w-0">
                <dt class="{{ $label }}">Classification</dt>
                <dd class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <x-classification-badge :type="$classification"
                                            :reason="\App\Services\ItemClassifier::reason($classification)" />
                    <span class="truncate text-xs text-slate-400">{{ \App\Services\ItemClassifier::summaryLabel($classification) }}</span>
                </dd>
            </div>

            <div class="col-span-2 min-w-0 lg:col-span-4">
                <dt class="{{ $label }}">Description</dt>
                <dd class="mt-1 text-sm text-slate-700">{{ $equipment->description ?: '—' }}</dd>
            </div>
        </dl>

        {{-- The three figures that move, in a short bar with no helper text in
             the way: they come from the receipts below, which the values say on
             hover. --}}
        <dl class="grid grid-cols-3 gap-4 rounded-b-xl border-t border-slate-100 bg-slate-50/70 px-4 py-3">
            <div class="min-w-0">
                <dt class="{{ $label }}">Quantity</dt>
                <dd class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $equipment->quantity_on_hand }} {{ $equipment->unit }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Unit Cost</dt>
                <dd class="mt-1 truncate text-sm font-semibold text-slate-900"
                    title="{{ $hasReceipts ? 'From the latest recorded receipt' : 'No receipt recorded yet' }}">
                    ₱{{ number_format((float) $equipment->unit_cost, 2) }}
                </dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Total Value</dt>
                <dd class="mt-1 truncate text-sm font-semibold text-brand-700" title="Quantity × unit cost">
                    ₱{{ number_format($equipment->totalValue(), 2) }}
                </dd>
            </div>
        </dl>
    </div>

    <div class="mt-5 rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <h2 class="border-b border-slate-100 px-4 py-2 text-sm font-semibold text-slate-800">Equipment Movement History</h2>

        <div class="overflow-x-auto px-2 py-2">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Date</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Type</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Reference / PAR No.</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Qty</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Unit Cost</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Total Cost</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Balance</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Source / Supplier</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Received By</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $movement)
                        <tr>
                            {{-- The recorded timestamp is what the audit trail checks, so keep it on hand. --}}
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500"
                                title="Recorded {{ $movement->created_at?->format('M d, Y g:i A') }}">{{ $movement->transaction_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-800">{{ $movement->typeLabel() }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->reference ?? '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-2 font-medium {{ $movement->quantity < 0 ? 'text-red-600' : 'text-brand-600' }}">{{ $movement->signedQuantity() }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">₱{{ number_format((float) $movement->unit_cost, 2) }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">₱{{ number_format((float) $movement->total_cost, 2) }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->balance_after }} {{ $equipment->unit }}</td>
                            <td class="px-2 py-2 text-slate-500">{{ $movement->source ?? '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->user?->name ?? '—' }}</td>
                            <td class="px-2 py-2 text-slate-500">{{ $movement->remarks ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-2 py-4 text-center text-slate-400">No equipment movements recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($movements->total() === 0 && $equipment->quantity_on_hand > 0)
            <p class="px-4 pb-2 text-xs text-slate-400">
                The current quantity of {{ $equipment->quantity_on_hand }} {{ $equipment->unit }} was on record before transactions were tracked here.
                New units are recorded with Add Quantity.
            </p>
        @endif

        <div class="px-4 py-3">{{ $movements->links() }}</div>
    </div>

    {{-- Add Quantity: records one receipt, increases the quantity and keeps the
         price of that receipt in the history. --}}
    <div id="add-quantity-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUAddQuantity.hide()"></div>
        <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                    <x-icon name="plus" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Add Quantity</h3>
                    <p class="text-xs text-slate-500">Record equipment received</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.equipment.quantity', $equipment) }}" id="add-quantity-form">
                @csrf

                <div class="mb-4 grid grid-cols-2 gap-3 rounded-lg bg-slate-50 px-3 py-3 text-sm ring-1 ring-slate-100">
                    <div>
                        <p class="text-xs text-slate-500">Item Code</p>
                        <p class="font-medium text-slate-800">{{ $equipment->item_code }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Property Number</p>
                        <p class="font-medium text-slate-800">{{ $equipment->property_number ?? '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-slate-500">Equipment</p>
                        <p class="font-medium text-slate-800">{{ $equipment->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Current Quantity</p>
                        <p class="font-medium text-slate-800">{{ $equipment->quantity_on_hand }} {{ $equipment->unit }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Unit</p>
                        <p class="font-medium text-slate-800">{{ $equipment->unit }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="quantity-date" class="mb-1 block text-sm font-medium text-slate-700">Date</label>
                        <input type="date" id="quantity-date" name="transaction_date" required
                               value="{{ old('transaction_date', now()->toDateString()) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        @error('transaction_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="quantity-reference" class="mb-1 block text-sm font-medium text-slate-700">Reference / PAR No.</label>
                        <input type="text" id="quantity-reference" name="reference" maxlength="255"
                               value="{{ old('reference') }}" placeholder="e.g. PAR-2026-001"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        @error('reference') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="quantity-received" class="mb-1 block text-sm font-medium text-slate-700">Quantity Received</label>
                        <input type="number" id="quantity-received" name="quantity" min="1" step="1" required
                               value="{{ old('quantity') }}" placeholder="e.g. 5"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="quantity-unit-cost" class="mb-1 block text-sm font-medium text-slate-700">Unit Cost (₱)</label>
                        <input type="number" id="quantity-unit-cost" name="unit_cost" min="0" step="0.01" required
                               value="{{ old('unit_cost', $equipment->unit_cost > 0 ? number_format((float) $equipment->unit_cost, 2, '.', '') : '') }}" placeholder="e.g. 35000.00"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        @error('unit_cost') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="quantity-total-cost" class="mb-1 block text-sm font-medium text-slate-700">Total Cost</label>
                        {{-- Calculated live for display only; the server always recomputes it. --}}
                        <input type="text" id="quantity-total-cost" readonly value="₱0.00" tabindex="-1"
                               class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    </div>

                    <div>
                        <label for="quantity-source" class="mb-1 block text-sm font-medium text-slate-700">Source / Supplier</label>
                        <input type="text" id="quantity-source" name="source" maxlength="255"
                               value="{{ old('source') }}" placeholder="e.g. ISU Procurement Office"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        @error('source') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Received By</label>
                        <input type="text" value="{{ auth()->user()->name }}" readonly tabindex="-1"
                               class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    </div>
                </div>

                <div class="mt-4">
                    <label for="quantity-remarks" class="mb-1 block text-sm font-medium text-slate-700">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="text" id="quantity-remarks" name="remarks" maxlength="255"
                           value="{{ old('remarks') }}" placeholder="e.g. Initial receipt"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('remarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <p class="mt-3 text-xs text-slate-400">
                    Saving adds the quantity to the quantity on hand and records a Receipt row in the equipment movement history
                    with this transaction's own unit cost.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="ISUAddQuantity.hide()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                        <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                        <x-icon name="check" class="h-4 w-4" /> Save Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.ISUAddQuantity = {
            show() {
                const m = document.getElementById('add-quantity-modal');
                m.classList.remove('hidden');
                m.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                this.recalculate();
                const qty = document.getElementById('quantity-received');
                if (qty) qty.focus();
            },
            hide() {
                const m = document.getElementById('add-quantity-modal');
                m.classList.add('hidden');
                m.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            },
            recalculate() {
                const qty = parseFloat(document.getElementById('quantity-received').value) || 0;
                const cost = parseFloat(document.getElementById('quantity-unit-cost').value) || 0;
                document.getElementById('quantity-total-cost').value =
                    '₱' + (qty * cost).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };

        ['quantity-received', 'quantity-unit-cost'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', function () { window.ISUAddQuantity.recalculate(); });
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') window.ISUAddQuantity.hide();
        });
    </script>

    @if ($errors->any())
        <script>
            // A failed validation must not silently swallow the modal.
            document.addEventListener('DOMContentLoaded', function () { window.ISUAddQuantity.show(); });
        </script>
    @endif
@endsection
