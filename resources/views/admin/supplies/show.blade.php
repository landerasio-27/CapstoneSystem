@extends('layouts.admin')

@section('title', $supply->name)

@section('content')
@php
    $classification = $supply->classification();
    $lowStock = $supply->isLowStock();

    // One field in the grid: a small label, the value a step below it.
    $label = 'text-xs font-medium text-slate-500';
    $value = 'mt-1 truncate text-sm text-slate-700';
    $strong = 'mt-1 truncate text-sm font-semibold text-slate-900';
    $code = 'mt-1 truncate font-mono text-[13px] font-semibold text-slate-800';
    $link = 'font-medium text-brand-700 hover:underline';
@endphp

    {{-- The stock record reads like the equipment one: four compact columns of
         label and value, the description on its own line, and the three figures
         the office watches — how much is on hand, whether that is enough, and
         what it last cost — closing the card. --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        {{-- One line, the action at its right, and no room taken beyond it. --}}
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2">
            <h2 class="truncate text-sm font-semibold text-slate-800">Supply Information</h2>

            <div class="flex shrink-0 items-center gap-2">
                @if ($card)
                    <a href="{{ route('admin.cards.show', $card) }}"
                       class="hidden items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-50 sm:inline-flex"
                       title="{{ $card->card_no }} — view this item's Stock Card">
                        <x-icon name="cards" class="h-3.5 w-3.5" /> {{ $card->card_no }}
                    </a>
                @endif
                <button type="button" onclick="ISUAddStock.show()"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md bg-brand-600 px-3 text-xs font-semibold text-white transition hover:bg-brand-900">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Add Stock
                </button>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-3.5 px-4 py-3.5 lg:grid-cols-4">
            <div class="min-w-0">
                <dt class="{{ $label }}">Item Code</dt>
                <dd class="{{ $code }}" title="{{ $supply->item_code }}">{{ $supply->item_code }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Category</dt>
                <dd class="{{ $value }}" title="{{ $supply->category ?? '—' }}">{{ $supply->category ?? '—' }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Unit</dt>
                <dd class="{{ $value }}">{{ $supply->unit }}</dd>
            </div>

            {{-- The paper this item is recorded on, named in full on hover. --}}
            <div class="min-w-0">
                <dt class="{{ $label }}">Document Type</dt>
                <dd class="mt-1">
                    <x-classification-badge :type="$classification"
                                            :reason="\App\Services\ItemClassifier::reason($classification)" />
                </dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Quantity on Hand</dt>
                <dd class="{{ $strong }}">{{ $supply->quantity_on_hand }} {{ $supply->unit }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Reorder Level</dt>
                <dd class="{{ $value }}">{{ $supply->reorder_level }} {{ $supply->unit }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Room</dt>
                <dd class="{{ $value }}">
                    @if ($supply->room)
                        <a href="{{ route('admin.rooms.show', $supply->room) }}" class="{{ $link }}">{{ $supply->room->name }}</a>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Latest Unit Cost</dt>
                <dd class="{{ $value }}">
                    @if ($latestUnitCost === null)
                        <span class="text-slate-400">—</span>
                    @else
                        ₱{{ number_format($latestUnitCost, 2) }}
                    @endif
                </dd>
            </div>

            @if ($supply->description)
                <div class="col-span-2 min-w-0 lg:col-span-4">
                    <dt class="{{ $label }}">Description</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $supply->description }}</dd>
                </div>
            @endif
        </dl>

        {{-- The three figures the office watches, in a short bar: how much is on
             hand, whether that is enough, and what it last cost. --}}
        <dl class="grid grid-cols-3 gap-4 rounded-b-xl border-t border-slate-100 bg-slate-50/70 px-4 py-3">
            <div class="min-w-0">
                <dt class="{{ $label }}">Quantity on Hand</dt>
                <dd class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $supply->quantity_on_hand }} {{ $supply->unit }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Reorder Status</dt>
                <dd class="mt-1">
                    @if ($lowStock)
                        <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset bg-amber-50 text-amber-700 ring-amber-200"
                              title="At or below the re-order level of {{ $supply->reorder_level }} {{ $supply->unit }}">
                            <x-icon name="exclamation" class="h-3 w-3" /> Low Stock
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset bg-brand-50 text-brand-700 ring-brand-200"
                              title="Above the re-order level of {{ $supply->reorder_level }} {{ $supply->unit }}">
                            <x-icon name="check" class="h-3 w-3" /> Stock OK
                        </span>
                    @endif
                </dd>
            </div>

            <div class="min-w-0">
                <dt class="{{ $label }}">Latest Cost</dt>
                <dd class="mt-1 truncate text-sm font-semibold text-slate-900"
                    title="{{ $latestUnitCost === null ? 'No stock received yet' : 'From the latest stock received' }}">
                    @if ($latestUnitCost === null)
                        <span class="font-normal text-slate-400">—</span>
                    @else
                        ₱{{ number_format($latestUnitCost, 2) }}
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    <div class="mt-5 rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <h2 class="border-b border-slate-100 px-4 py-2 text-sm font-semibold text-slate-800">Stock Movement History</h2>

        <div class="overflow-x-auto px-2 py-2">
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

        <div class="px-4 py-3">{{ $movements->links() }}</div>
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
