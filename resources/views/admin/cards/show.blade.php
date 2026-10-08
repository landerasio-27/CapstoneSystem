@extends('layouts.admin')

@section('title', $card->card_no . ' — ' . $card->typeLabel())

@section('content')
    @php
        $isStockCard = $card->isStockCard();
        $lines = collect($transactions->items());
        $types = $isStockCard
            ? ['receipt' => 'Receipt (stock in)', 'issue' => 'Issue (stock out)', 'adjustment' => 'Adjustment (correction)']
            : ['receipt' => 'Receipt (acquired)', 'issue' => 'Issue (to end-user)', 'transfer' => 'Transfer', 'disposal' => 'Disposal'];
    @endphp

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        {{-- No back arrow beside the name: the layout already links back to the
             register, above this header. --}}
        <div class="flex items-center gap-3">
            <div>
                <p class="flex items-center gap-2">
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $isStockCard ? 'bg-brand-50 text-brand-800' : 'bg-slate-100 text-slate-700' }}">
                        {{ $card->type }}
                    </span>
                    <span class="text-sm text-slate-500">{{ $card->card_no }}</span>
                </p>
                <h2 class="text-lg font-semibold text-slate-800">{{ $card->itemName() }}</h2>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="ISUCard.openPost()"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                <x-icon name="plus" class="h-4 w-4" /> Add Transaction
            </button>
            <form method="POST" action="{{ route('admin.cards.sync', $card) }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50" title="Post anything the source records gained since">
                    <x-icon name="history" class="h-4 w-4" /> Sync
                </button>
            </form>
            <a href="{{ route('admin.cards.edit', $card) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                <x-icon name="pencil" class="h-4 w-4" /> Edit
            </a>
            <a href="{{ route('admin.cards.print', $card) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                <x-icon name="printer" class="h-4 w-4" /> Print
            </a>
            <a href="{{ route('admin.cards.pdf', $card) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                <x-icon name="download" class="h-4 w-4" /> PDF
            </a>
        </div>
    </div>

    @if ($gap !== null)
        <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-100">
            <strong>Card balance and stock on hand differ by {{ $gap }} {{ $card->unit() }}.</strong>
            The card balance ({{ $card->balance() }} {{ $card->unit() }}) is built from posted transactions only, so stock that was
            recorded without a stock movement — before this card existed — is not on it. Post an adjustment to bring the card in line.
        </div>
    @endif

    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base font-semibold text-slate-800">Card Information</h3>
            <p class="text-sm text-slate-500">
                Balance: <span class="font-semibold text-slate-800">{{ $card->balance() }} {{ $card->unit() }}</span>
            </p>
        </div>

        <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
            <div><dt class="text-slate-500">Entity Name</dt><dd class="font-medium text-slate-800">{{ $card->entity_name }}</dd></div>
            <div><dt class="text-slate-500">Fund Cluster</dt><dd class="font-medium text-slate-800">{{ $card->fund_cluster ?: '—' }}</dd></div>
            <div>
                <dt class="text-slate-500">{{ $isStockCard ? 'Item' : 'Property, Plant and Equipment' }}</dt>
                <dd class="font-medium text-slate-800">{{ $card->itemName() }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ $isStockCard ? 'Stock No.' : 'Property Number' }}</dt>
                <dd class="font-medium text-slate-800">{{ $card->itemCode() ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-slate-500">Description</dt>
                <dd class="text-slate-700">{{ $card->description() ?: '—' }}</dd>
            </div>
            <div><dt class="text-slate-500">Unit of Measurement</dt><dd class="font-medium text-slate-800">{{ $card->unit() }}</dd></div>
            @if ($isStockCard)
                <div>
                    <dt class="text-slate-500">Re-order Point</dt>
                    <dd class="font-medium text-slate-800">{{ $card->reorder_point !== null ? $card->reorder_point . ' ' . $card->unit() : '—' }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-slate-500">Created</dt>
                <dd class="text-slate-700">
                    {{ $card->created_at?->format('M d, Y g:i A') }}
                    <span class="block text-xs text-slate-400">{{ $card->creator?->name ?? 'System' }}</span>
                </dd>
            </div>
        </dl>

        <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-400">
            @if ($isStockCard)
                Receipts post automatically from Add Stock transactions, and issues from RIS releases. Transfers and corrections are posted here.
            @else
                Receipts post automatically from approved PAR/ICS/IAR documents. Issues, transfers and disposals are posted here.
            @endif
            Posted lines are never edited or deleted — a mistake is corrected with an adjustment.
        </p>
    </div>

    <div class="mt-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:p-6">
        <h3 class="mb-4 text-base font-semibold text-slate-800">Transaction History</h3>

        <div class="overflow-x-auto">
            @include('admin.cards._sheet', [
                'card' => $card,
                'lines' => $lines,
                'daysToConsume' => $daysToConsume,
                'padTo' => 0,
            ])
        </div>

        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>

    {{-- Add Transaction: issues without a RIS, transfers, disposals, corrections. --}}
    <div id="card-post-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="ISUCard.closePost()"></div>
        <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                    <x-icon name="plus" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Add Transaction</h3>
                    <p class="text-xs text-slate-500">{{ $card->card_no }} · {{ $card->itemName() }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.cards.transactions.store', $card) }}">
                @csrf

                <div class="mb-4 grid grid-cols-2 gap-3 rounded-lg bg-slate-50 px-3 py-3 text-sm ring-1 ring-slate-100">
                    <div>
                        <p class="text-slate-500">Current balance</p>
                        <p class="font-semibold text-slate-800">{{ $card->balance() }} {{ $card->unit() }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Balance after posting</p>
                        <p id="card-new-balance" class="font-semibold text-slate-800">{{ $card->balance() }} {{ $card->unit() }}</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="transaction_type">Transaction</label>
                        <select name="type" id="transaction_type" required onchange="ISUCard.recalc()"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="transaction_date">Date</label>
                        <input type="date" name="transaction_date" id="transaction_date" required value="{{ old('transaction_date', now()->toDateString()) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="quantity">Quantity</label>
                        <input type="number" name="quantity" id="quantity" min="1" required value="{{ old('quantity') }}" oninput="ISUCard.recalc()"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="reference">Reference</label>
                        <input type="text" name="reference" id="reference" value="{{ old('reference') }}"
                               placeholder="{{ $isStockCard ? 'e.g. IAR-2026-0001' : 'e.g. PAR-2026-0001' }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="office">
                            {{ $isStockCard ? 'Office (issued to)' : 'Office / Officer (issued, transferred or disposed to)' }}
                        </label>
                        <input type="text" name="office" id="office" value="{{ old('office') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div class="sm:col-span-2" id="card-amount-field">
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="amount">Amount (₱)</label>
                        <input type="number" name="amount" id="amount" step="0.01" min="0" value="{{ old('amount') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-400">Only used for receipts; recorded as the cost of what was received.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="remarks">Remarks</label>
                        <input type="text" name="remarks" id="remarks" maxlength="255" value="{{ old('remarks') }}"
                               placeholder="e.g. Transferred to CICT"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>

                <p class="mt-4 text-xs text-slate-400">
                    @if ($isStockCard)
                        The balance is calculated by the system (previous balance + receipt − issue) and is never typed in.
                        An issue larger than the balance is rejected.
                    @else
                        The balance counts the property received: a receipt adds to it, while an issue, transfer or disposal
                        records where the property went and does not reduce it. Posting more than was received is rejected.
                    @endif
                </p>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" onclick="ISUCard.closePost()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">Post Transaction</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.ISUCard = (function () {
            var cardBalance = {{ $card->balance() }};
            var unit = @json($card->unit());
            // A stock card's issue consumes stock. A property card's issue,
            // transfer or disposal only moves custody, so the balance holds.
            var stockCard = @json($isStockCard);

            function modal() { return document.getElementById('card-post-modal'); }

            function recalc() {
                var type = document.getElementById('transaction_type');
                var quantity = document.getElementById('quantity');
                var output = document.getElementById('card-new-balance');
                var amountField = document.getElementById('card-amount-field');

                if (!type || !quantity || !output) return;

                var qty = parseInt(quantity.value || '0', 10);
                var incoming = type.value === 'receipt';
                // Nothing can leave a card that was never received on it; how the
                // balance moves after that depends on the kind of card.
                var overdraws = !incoming && qty > cardBalance;
                var next = incoming
                    ? cardBalance + qty
                    : (stockCard ? cardBalance - qty : cardBalance);

                output.textContent = qty > 0
                    ? (overdraws ? 'Insufficient stock (available ' + cardBalance + ')' : next + ' ' + unit)
                    : cardBalance + ' ' + unit;

                output.className = overdraws && qty > 0
                    ? 'font-semibold text-red-600'
                    : 'font-semibold text-slate-800';

                if (amountField) {
                    amountField.classList.toggle('hidden', !incoming);
                }
            }

            return {
                show: function () { modal().classList.remove('hidden'); modal().classList.add('flex'); recalc(); },
                openPost: function () { this.show(); },
                closePost: function () { modal().classList.add('hidden'); modal().classList.remove('flex'); },
                recalc: recalc,
            };
        })();

        // Reopen the form when the server rejected a posting (e.g. insufficient stock).
        @if ($errors->any())
            ISUCard.show();
        @endif
    </script>
@endsection
