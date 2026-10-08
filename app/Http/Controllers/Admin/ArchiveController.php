<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Archive;
use App\Models\Card;
use App\Models\Document;
use App\Support\ArchiveRegister;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The archive: records that are no longer in daily use are filed under a year
 * instead of being deleted.
 *
 * Two kinds of paper are filed here — the PAR / ICS / RIS documents and the
 * Stock / Property Cards — and each gets its own panel. The index page is the
 * workspace (add a year, then tick the records and file them); a year's own
 * page lists what is filed under it and restores anything that came back into
 * use. Nothing is ever copied or rewritten: archiving only sets the year a
 * record belongs to, so its items, quantities, movements and printed copies
 * stay exactly as they were.
 */
class ArchiveController extends Controller
{
    /**
     * The workspace: the years, and the register waiting to be filed. The same
     * register is shown in the Archive panel of the settings page, which is
     * where the settings sidebar leads — both read it through ArchiveRegister,
     * so the two can never disagree about what is still in it.
     */
    public function index(Request $request): View
    {
        return view('admin.archive.index', [
            'archives' => ArchiveRegister::years(),
            'documents' => ArchiveRegister::documents($request),
            'cards' => ArchiveRegister::cards($request),
            'cardType' => ArchiveRegister::cardType($request),
        ]);
    }

    /** One archive year: everything filed under it, ready to be restored. */
    public function show(Archive $archive, Request $request): View
    {
        $archive->loadCount(['documents', 'cards']);

        return view('admin.archive.show', ArchiveRegister::filed($archive, $request) + [
            'archive' => $archive,
        ]);
    }

    /** Add an archive year. One row per year; the year must not exist yet. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1900', 'max:2100', Rule::unique('archives', 'year')],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['year' => 'archive year']);

        $archive = Archive::create($data + ['created_by' => auth()->id()]);

        ActivityLog::record('created', 'archives', "Added archive year {$archive->year}.");

        return redirect()->route('admin.archive.index', ['archive_id' => $archive->id])
            ->with('success', "Archive year {$archive->year} added. Tick the records to file under it.");
    }

    // Deliberately no destroy(): a year is permanent once added. Removing one
    // would either strand the records filed under it or force them back into the
    // working lists, so a year only ever grows.

    /** File the ticked PAR / ICS / RIS documents under a year. */
    public function archiveDocuments(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'archive_id' => ['required', 'exists:archives,id'],
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['integer', 'exists:documents,id'],
        ], [], [
            'archive_id' => 'archive year',
            'documents' => 'documents',
        ]);

        $archive = Archive::findOrFail($data['archive_id']);

        $count = Document::query()
            ->notArchived()
            ->whereIn('id', $data['documents'])
            ->update(['archive_id' => $archive->id, 'archived_at' => now()]);

        ActivityLog::record('archived', 'archives', "Archived {$count} document(s) under {$archive->year}.");

        return back()->with('success', "{$count} document(s) filed under the {$archive->year} archive.");
    }

    /** File the ticked Stock / Property Cards under a year. */
    public function archiveCards(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'archive_id' => ['required', 'exists:archives,id'],
            'cards' => ['required', 'array', 'min:1'],
            'cards.*' => ['integer', 'exists:cards,id'],
        ], [], [
            'archive_id' => 'archive year',
            'cards' => 'cards',
        ]);

        $archive = Archive::findOrFail($data['archive_id']);

        $count = Card::query()
            ->notArchived()
            ->whereIn('id', $data['cards'])
            ->update(['archive_id' => $archive->id, 'archived_at' => now()]);

        ActivityLog::record('archived', 'archives', "Archived {$count} card(s) under {$archive->year}.");

        return back()->with('success', "{$count} card(s) filed under the {$archive->year} archive.");
    }

    public function restoreDocument(Document $document): RedirectResponse
    {
        return $this->restore($document, 'Document', $document->document_no);
    }

    public function restoreCard(Card $card): RedirectResponse
    {
        return $this->restore($card, $card->typeLabel(), $card->card_no);
    }

    /** Put a filed record back into the working lists. */
    private function restore(Model $record, string $label, string $name): RedirectResponse
    {
        $record->update(['archive_id' => null, 'archived_at' => null]);

        ActivityLog::record('restored', 'archives', "Restored {$label} \"{$name}\" from the archive.");

        return back()->with('success', "{$label} \"{$name}\" restored to the working lists.");
    }

}
