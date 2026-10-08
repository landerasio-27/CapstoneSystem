{{--
    One archive year: what is filed under it, and the way back to the register.

    The Archive page and the Archive panel of the settings page both open a
    year, so the year is written once here. $backUrl is where "Back" goes (the
    register the reader came from) and $cardTypeUrl builds the card-list filter
    links, so each page keeps its own address. A filed year is a record of what
    was put away, not a workspace: every number is plain text and the only
    action is putting a record back.

    Expects: $archive, $documents, $cards (both paginated), $cardType, $backUrl,
    $cardTypeUrl.
--}}
{{-- The year itself: what is filed under it, and the way back. --}}
<div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <div>
        <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-800">
            <x-icon name="folder" class="h-5 w-5 text-slate-400" /> {{ $archive->year }}
        </h2>
        <p class="mt-1 text-xs text-slate-500">
            {{ $archive->documents_count }} document(s) &middot; {{ $archive->cards_count }} card(s)
        </p>
        @if ($archive->notes)
            <p class="mt-1 text-xs text-slate-400">{{ $archive->notes }}</p>
        @endif
    </div>

    <a href="{{ $backUrl }}"
       class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
        <x-icon name="arrow-left" class="h-4 w-4" /> Back to the register
    </a>
</div>

{{-- Documents filed under this year. --}}
<div class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="border-b border-slate-100 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-700">Documents &mdash; PAR / ICS / RIS</h2>
        <p class="text-xs text-slate-400">Restore a document to put it back in the register.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Document No.</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Type</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Custodian / Room</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Date</th>
                    <th class="px-4 py-3 text-right font-medium text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($documents as $document)
                    <tr>
                        {{-- A filed document is read-only here: the number is
                             plain text, so nothing opens the live register. --}}
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $document->document_no }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $document->type }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $document->custodian_name ?: '—' }}{{ $document->room ? ' / '.$document->room->code : '' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ ucfirst($document->status) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $document->document_date->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.archive.documents.restore', $document) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-brand-600 hover:bg-brand-50"><x-icon name="arrow-left" class="h-3.5 w-3.5" /> Restore</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No documents filed under {{ $archive->year }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($documents->hasPages())
        <div class="border-t border-slate-100 px-5 py-3">{{ $documents->links() }}</div>
    @endif
</div>

{{-- Stock / Property Cards filed under this year. --}}
<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-700">Stock / Property Cards</h2>
            <p class="text-xs text-slate-400">Restore a card to put it back in the register.</p>
        </div>
        <div class="inline-flex overflow-hidden rounded-lg border border-slate-300 text-sm">
            <a href="{{ $cardTypeUrl(null) }}"
               class="px-3 py-1.5 {{ $cardType === null ? 'bg-brand-50 font-medium text-brand-800' : 'text-slate-600 hover:bg-slate-50' }}">All</a>
            <a href="{{ $cardTypeUrl('SC') }}"
               class="border-l border-slate-300 px-3 py-1.5 {{ $cardType === 'SC' ? 'bg-brand-50 font-medium text-brand-800' : 'text-slate-600 hover:bg-slate-50' }}">Stock Cards</a>
            <a href="{{ $cardTypeUrl('PC') }}"
               class="border-l border-slate-300 px-3 py-1.5 {{ $cardType === 'PC' ? 'bg-brand-50 font-medium text-brand-800' : 'text-slate-600 hover:bg-slate-50' }}">Property Cards</a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Card No.</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Type</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Item</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Filed</th>
                    <th class="px-4 py-3 text-right font-medium text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($cards as $card)
                    <tr>
                        {{-- A filed card is read-only here: the number is plain
                             text, so nothing opens the live card. --}}
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $card->card_no }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $card->typeLabel() }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $card->itemName() }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $card->archived_at?->format('M d, Y') ?? '—' }}</td>
                        {{-- Restore is the only action a filed card offers: it is
                             out of the register, so there is nothing to post,
                             sync, edit, print or export until it goes back. --}}
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.archive.cards.restore', $card) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-brand-600 hover:bg-brand-50"><x-icon name="arrow-left" class="h-3.5 w-3.5" /> Restore</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No cards filed under {{ $archive->year }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($cards->hasPages())
        <div class="border-t border-slate-100 px-5 py-3">{{ $cards->links() }}</div>
    @endif
</div>
