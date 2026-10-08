{{--
    The archive register: the documents and the cards that are still in daily
    use, each with a tick box and the year to file them under.

    Two pages show this same register — the Archive page and the Archive panel
    of the settings page — so it is written once here. $keep carries whatever
    query the reader's page must not lose when a filter is applied (the settings
    panel needs its own section back); a page with nothing to keep passes
    nothing and the forms stay plain.

    Expects: $documents, $cards (both paginated), $archives (the years), and
    $cardType — the card list being shown, if one is being narrowed to.
--}}
@php
    $keep = $keep ?? [];

    // The year the "File under" picker opens on: the one just added, when the
    // reader has just added one, otherwise the newest.
    $selectedYear = (int) request('archive_id');
@endphp

{{-- The two lists stand one under the other, each taking the full width of
    the page: the papers are read first and the cards follow them, and every
    column of a table is given the room it needs rather than a half of it. --}}
<div class="space-y-6">

{{-- Documents still in the register, ready to be filed away. --}}
<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-700">Documents &mdash; PAR / ICS / RIS</h2>
            <p class="text-xs text-slate-400">{{ $documents->total() }} still in the register. Tick the ones to file away.</p>
        </div>
        <form method="GET" data-auto-filter class="flex flex-wrap items-center gap-2">
            @foreach ($keep as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="card_type" value="{{ $cardType }}">
            <input type="hidden" name="card_search" value="{{ request('card_search') }}">
            <input type="text" name="doc_search" value="{{ request('doc_search') }}" placeholder="Search document no..."
                   class="w-44 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <select name="doc_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All types</option>
                @foreach (\App\Models\Document::TYPES as $documentType)
                    <option value="{{ $documentType }}" @selected(request('doc_type') === $documentType)>{{ $documentType }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.archive.documents.store') }}">
        @csrf
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <input type="checkbox" data-check-all="documents[]" title="Select all on this page"
                                   class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Document No.</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Custodian / Room</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($documents as $document)
                        <tr>
                            <td class="px-4 py-3">
                                <input type="checkbox" name="documents[]" value="{{ $document->id }}" data-check-item="documents[]"
                                       class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <a href="{{ route('admin.documents.show', $document) }}" class="text-brand-700 hover:underline">{{ $document->document_no }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $document->type }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $document->custodian_name ?: '—' }}{{ $document->room ? ' / '.$document->room->code : '' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ ucfirst($document->status) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $document->document_date->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No documents left in the register.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3">
            <div class="min-w-0">{{ $documents->links() }}</div>
            @if ($archives->isEmpty())
                <p class="text-xs text-slate-400">Add an archive year before filing documents.</p>
            @else
                <div class="flex items-center gap-2">
                    <label for="documents-year" class="text-xs font-medium text-slate-500">File under</label>
                    <select id="documents-year" name="archive_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($archives as $option)
                            <option value="{{ $option->id }}" @selected($selectedYear === $option->id)>{{ $option->year }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                        <x-icon name="archive" class="h-4 w-4" /> Archive Selected
                    </button>
                </div>
            @endif
        </div>
    </form>
</div>

{{-- Stock / Property Cards still in the register, ready to be filed away. --}}
<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-700">Stock / Property Cards</h2>
            <p class="text-xs text-slate-400">{{ $cards->total() }} still in the register. Tick the ones to file away.</p>
        </div>
        <form method="GET" data-auto-filter class="flex flex-wrap items-center gap-2">
            @foreach ($keep as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="doc_type" value="{{ request('doc_type') }}">
            <input type="hidden" name="doc_search" value="{{ request('doc_search') }}">
            <input type="text" name="card_search" value="{{ request('card_search') }}" placeholder="Search card no. or item..."
                   class="w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <select name="card_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All cards</option>
                <option value="SC" @selected($cardType === 'SC')>Stock Cards (SC)</option>
                <option value="PC" @selected($cardType === 'PC')>Property Cards (PC)</option>
            </select>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.archive.cards.store') }}">
        @csrf
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <input type="checkbox" data-check-all="cards[]" title="Select all on this page"
                                   class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Card No.</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Item</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Item Code</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($cards as $card)
                        <tr>
                            <td class="px-4 py-3">
                                <input type="checkbox" name="cards[]" value="{{ $card->id }}" data-check-item="cards[]"
                                       class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <a href="{{ route('admin.cards.show', $card) }}" class="text-brand-700 hover:underline">{{ $card->card_no }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $card->typeLabel() }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $card->itemName() }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $card->itemCode() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No cards left in the register.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3">
            <div class="min-w-0">{{ $cards->links() }}</div>
            @if ($archives->isEmpty())
                <p class="text-xs text-slate-400">Add an archive year before filing cards.</p>
            @else
                <div class="flex items-center gap-2">
                    <label for="cards-year" class="text-xs font-medium text-slate-500">File under</label>
                    <select id="cards-year" name="archive_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($archives as $option)
                            <option value="{{ $option->id }}" @selected($selectedYear === $option->id)>{{ $option->year }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                        <x-icon name="archive" class="h-4 w-4" /> Archive Selected
                    </button>
                </div>
            @endif
        </div>
    </form>
</div>

</div>

{{-- Tick the header box to take the whole page. --}}
<script>
    document.querySelectorAll('[data-check-all]').forEach(function (box) {
        box.addEventListener('change', function () {
            document.querySelectorAll('[data-check-item="' + box.dataset.checkAll + '"]').forEach(function (item) {
                item.checked = box.checked;
            });
        });
    });
</script>
