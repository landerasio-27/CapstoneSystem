@extends('layouts.admin')

@section('title', $supply->name)

@section('content')
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-base font-semibold text-slate-800">Supply Information</h2>
            <button type="button" onclick="ISUAddStock.show()"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                <x-icon name="plus" class="h-4 w-4" /> Add Stock
            </button>
        </div>

        <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div><dt class="text-slate-500">Item Code</dt><dd class="font-medium text-slate-800">{{ $supply->item_code }}</dd></div>
            <div><dt class="text-slate-500">Category</dt><dd class="font-medium text-slate-800">{{ $supply->category ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Unit</dt><dd class="font-medium text-slate-800">{{ $supply->unit }}</dd></div>
            <div><dt class="text-slate-500">Quantity on Hand</dt><dd class="font-medium text-slate-800">{{ $supply->quantity_on_hand }} {{ $supply->unit }}</dd></div>
            <div>
                <dt class="text-slate-500">Reorder Level</dt>
                <dd class="font-medium text-slate-800">
                    {{ $supply->reorder_level }} {{ $supply->unit }}
                    @if ($supply->isLowStock())
                        <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">At or below</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Latest Unit Cost</dt>
                <dd class="font-medium text-slate-800">
                    @if ($latestUnitCost === null)
                        —
                    @else
                        ₱{{ number_format($latestUnitCost, 2) }}
                        <span class="text-xs font-normal text-slate-400">from last stock-in</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Room</dt>
                <dd class="font-medium text-slate-800">
                    @if ($supply->room)
                        <a href="{{ route('admin.rooms.show', $supply->room) }}" class="text-brand-700 hover:underline">{{ $supply->room->name }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            @if ($supply->description)
                <div class="sm:col-span-3"><dt class="text-slate-500">Description</dt><dd class="text-slate-700">{{ $supply->description }}</dd></div>
            @endif
        </dl>
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-4 text-base font-semibold text-slate-800">Stock Movement History</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Date</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Type</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Reference</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Quantity</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Unit Cost</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Total Cost</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Balance After</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">Remarks</th>
                        <th class="whitespace-nowrap px-2 py-2 text-left font-medium text-slate-500">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->created_at->format('M d, Y g:i A') }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-800">{{ $movement->typeLabel() }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->reference ?? '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-2 font-medium {{ $movement->quantity < 0 ? 'text-red-600' : 'text-brand-600' }}">{{ $movement->signedQuantity() }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->unit_cost === null ? '—' : '₱' . number_format((float) $movement->unit_cost, 2) }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->total_cost === null ? '—' : '₱' . number_format((float) $movement->total_cost, 2) }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->balance_after }} {{ $supply->unit }}</td>
                            <td class="px-2 py-2 text-slate-500">{{ $movement->remarks ?? '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $movement->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-2 py-4 text-center text-slate-400">No stock movements recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $movements->links() }}</div>
    </div>

    {{-- Add Stock: records one stock-in transaction and increases the on-hand balance. --}}
    <div id="add-stock-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUAddStock.hide()"></div>
        <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                    <x-icon name="plus" class="h-5 w-5" />
                </span>
                <h3 class="text-base font-semibold text-slate-800">Add Stock</h3>
            </div>

            <form method="POST" action="{{ route('admin.supplies.stock', $supply) }}" id="add-stock-form">
                @csrf

                <div class="mb-4 grid grid-cols-2 gap-3 rounded-lg bg-slate-50 px-3 py-3 text-sm ring-1 ring-slate-100">
                    <div>
                        <p class="text-xs text-slate-500">Item Code</p>
                        <p class="font-medium text-slate-800">{{ $supply->item_code }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Product Name</p>
                        <p class="font-medium text-slate-800">{{ $supply->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Quantity on Hand</p>
                        <p class="font-medium text-slate-800">{{ $supply->quantity_on_hand }} {{ $supply->unit }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Unit</p>
                        <p class="font-medium text-slate-800">{{ $supply->unit }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="stock-quantity" class="mb-1 block text-sm font-medium text-slate-700">Quantity Added</label>
                        <input type="number" id="stock-quantity" name="quantity" min="1" step="1" required
                               value="{{ old('quantity') }}" placeholder="e.g. 100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="stock-unit-cost" class="mb-1 block text-sm font-medium text-slate-700">Unit Cost (₱)</label>
                        <input type="number" id="stock-unit-cost" name="unit_cost" min="0" step="0.01" required
                               value="{{ old('unit_cost', $latestUnitCost !== null ? number_format($latestUnitCost, 2, '.', '') : '') }}" placeholder="e.g. 8.00"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        @error('unit_cost') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="stock-total-cost" class="mb-1 block text-sm font-medium text-slate-700">Total Cost</label>
                        {{-- Calculated live for display only; the server always recomputes it. --}}
                        <input type="text" id="stock-total-cost" readonly value="₱0.00" tabindex="-1"
                               class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Added By</label>
                        <input type="text" value="{{ auth()->user()->name }}" readonly tabindex="-1"
                               class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    </div>
                </div>

                <div class="mt-4">
                    <label for="stock-reference" class="mb-1 block text-sm font-medium text-slate-700">Reference <span class="font-normal text-slate-400">(optional)</span></label>
                    {{-- The document this delivery came in on (IAR / PO / DR no.).
                         It prints on the Stock Card's Reference column. --}}
                    <input type="text" id="stock-reference" name="reference" maxlength="255"
                           value="{{ old('reference') }}" placeholder="e.g. IAR-2026-001"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('reference') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4">
                    <label for="stock-remarks" class="mb-1 block text-sm font-medium text-slate-700">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="text" id="stock-remarks" name="remarks" maxlength="255"
                           value="{{ old('remarks') }}" placeholder="e.g. New delivery from supplier"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('remarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <p class="mt-3 text-xs text-slate-400">
                    Saving adds the quantity to the on-hand balance and records a Stock In row in the stock movement history.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="ISUAddStock.hide()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                        <x-icon name="x-mark" class="h-4 w-4" /> Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                        <x-icon name="check" class="h-4 w-4" /> Save Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.ISUAddStock = {
            show() {
                const m = document.getElementById('add-stock-modal');
                m.classList.remove('hidden');
                m.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                this.recalculate();
                const qty = document.getElementById('stock-quantity');
                if (qty) qty.focus();
            },
            hide() {
                const m = document.getElementById('add-stock-modal');
                m.classList.add('hidden');
                m.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            },
            recalculate() {
                const qty = parseFloat(document.getElementById('stock-quantity').value) || 0;
                const cost = parseFloat(document.getElementById('stock-unit-cost').value) || 0;
                document.getElementById('stock-total-cost').value =
                    '₱' + (qty * cost).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };

        ['stock-quantity', 'stock-unit-cost'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', function () { window.ISUAddStock.recalculate(); });
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') window.ISUAddStock.hide();
        });
    </script>

    @if ($errors->any())
        <script>
            // A failed validation must not silently swallow the modal.
            document.addEventListener('DOMContentLoaded', function () { window.ISUAddStock.show(); });
        </script>
    @endif
@endsection
