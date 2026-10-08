@extends('layouts.admin')

@section('title', $equipment->name)

@section('content')
    {{-- "Back to Equipment" comes from the layout's contextual link, so it is
         not repeated here. --}}
    {{-- The QR label is reached from the equipment list and the QR Codes page, so
         it is not repeated as a card on this page. --}}
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-base font-semibold text-slate-800">Equipment Information</h2>
            <button type="button" onclick="ISUAddQuantity.show()"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                <x-icon name="plus" class="h-4 w-4" /> Add Quantity
            </button>
        </div>

        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Item Code</dt><dd class="font-medium text-slate-800">{{ $equipment->item_code }}</dd></div>
            <div><dt class="text-slate-500">Property Number</dt><dd class="font-medium text-slate-800">{{ $equipment->property_number ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Serial Number</dt><dd class="font-medium text-slate-800">{{ $equipment->serial_number ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Name</dt><dd class="font-medium text-slate-800">{{ $equipment->name }}</dd></div>
            <div><dt class="text-slate-500">Category</dt><dd class="font-medium text-slate-800">{{ $equipment->category ?? '—' }}</dd></div>
            <div class="sm:col-span-2">
                <dt class="text-slate-500">Description</dt>
                <dd class="text-slate-700">{{ $equipment->description ?: '—' }}</dd>
            </div>
            <div><dt class="text-slate-500">Unit</dt><dd class="font-medium text-slate-800">{{ $equipment->unit }}</dd></div>
            <div>
                <dt class="text-slate-500">Room</dt>
                <dd class="font-medium text-slate-800">
                    @if ($equipment->room)
                        <a href="{{ route('admin.rooms.show', $equipment->room) }}" class="text-brand-700 hover:underline">{{ $equipment->room->name }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Custodian</dt>
                <dd class="font-medium text-slate-800">
                    @if ($equipment->teacher)
                        {{-- The folder is where this person's property is read together. --}}
                        <a href="{{ route('admin.teachers.show', $equipment->teacher) }}" class="text-brand-700 hover:underline">{{ $equipment->custodian_name }}</a>
                    @else
                        {{ $equipment->custodian_name ?: '—' }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Status</dt>
                <dd class="font-medium text-slate-800">{{ ucfirst($equipment->status) }}</dd>
            </div>
            <div><dt class="text-slate-500">Date Acquired</dt><dd class="font-medium text-slate-800">{{ $equipment->date_acquired?->format('M d, Y') ?? '—' }}</dd></div>
        </dl>

        {{-- Quantity and cost are read-only here: they come from the receipts. --}}
        <div class="mt-6 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-slate-500">Current Quantity</dt>
                <dd class="text-lg font-semibold text-slate-800">{{ $equipment->quantity_on_hand }} {{ $equipment->unit }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Current Unit Cost</dt>
                <dd class="text-lg font-semibold text-slate-800">₱{{ number_format((float) $equipment->unit_cost, 2) }}</dd>
                <p class="text-xs text-slate-400">
                    {{ $movements->total() > 0 ? 'from the latest recorded receipt' : 'no receipt recorded yet' }}
                </p>
            </div>
            <div>
                <dt class="text-slate-500">Current Total Value</dt>
                <dd class="text-lg font-semibold text-slate-800">₱{{ number_format($equipment->totalValue(), 2) }}</dd>
                <p class="text-xs text-slate-400">quantity &times; unit cost</p>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-4 text-base font-semibold text-slate-800">Equipment Movement History</h2>

        <div class="overflow-x-auto">
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
            <p class="mt-3 text-xs text-slate-400">
                The current quantity of {{ $equipment->quantity_on_hand }} {{ $equipment->unit }} was on record before transactions were tracked here.
                New units are recorded with Add Quantity.
            </p>
        @endif

        <div class="mt-4">{{ $movements->links() }}</div>
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
