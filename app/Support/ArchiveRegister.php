<?php

/*
 * The archive's register: the years records are filed under, the documents and
 * cards still waiting in the register, and what a filed year holds.
 *
 * The archive is read from two places — the Archive page and the Archive panel
 * of the settings page — and both must show the same shelves, the same register
 * and the same filed records. Keeping the queries here is what makes that true:
 * a row that is filed away leaves the register the same way on either page,
 * because there is only one definition of it.
 */

namespace App\Support;

use App\Models\Archive;
use App\Models\Card;
use App\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ArchiveRegister
{
    /** Rows per page in each of the register tables. */
    public const PER_PAGE = 15;

    /**
     * The shelves: every archive year, newest first, with what is filed under
     * it. A year is permanent, so this is the complete list of them.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Archive>
     */
    public static function years()
    {
        return Archive::query()
            ->withCount(['documents', 'cards'])
            ->orderByDesc('year')
            ->get();
    }

    /**
     * Documents still in the register, ready to be filed away.
     *
     * @return LengthAwarePaginator<int, Document>
     */
    public static function documents(Request $request): LengthAwarePaginator
    {
        return Document::query()
            ->notArchived()
            ->with(['teacher', 'room'])
            ->when($request->filled('doc_type'), fn ($q) => $q->where('type', $request->doc_type))
            ->when($request->filled('doc_search'), fn ($q) => $q->where('document_no', 'like', '%'.$request->doc_search.'%'))
            ->latest('document_date')
            ->paginate(self::PER_PAGE, ['*'], 'documents_page')
            ->withQueryString();
    }

    /**
     * Cards still in the register, ready to be filed away.
     *
     * @return LengthAwarePaginator<int, Card>
     */
    public static function cards(Request $request): LengthAwarePaginator
    {
        return static::cardQuery($request)
            ->paginate(self::PER_PAGE, ['*'], 'cards_page')
            ->withQueryString();
    }

    /** Which card list the register is showing: all of them, SC or PC. */
    public static function cardType(Request $request): ?string
    {
        $type = $request->get('card_type');

        return in_array($type, Card::TYPES, true) ? $type : null;
    }

    /**
     * One archive year: everything filed under it, ready to be restored.
     *
     * @return array{documents: LengthAwarePaginator<int, Document>, cards: LengthAwarePaginator<int, Card>, cardType: ?string}
     */
    public static function filed(Archive $archive, Request $request): array
    {
        $cardType = static::cardType($request);

        return [
            'documents' => $archive->documents()
                ->with(['teacher', 'room'])
                ->latest('document_date')
                ->paginate(self::PER_PAGE, ['*'], 'documents_page'),
            'cards' => $archive->cards()
                ->with(['supply', 'equipment'])
                ->when($cardType, fn ($q) => $q->where('type', $cardType))
                ->orderBy('card_no')
                ->paginate(self::PER_PAGE, ['*'], 'cards_page'),
            'cardType' => $cardType,
        ];
    }

    /**
     * Cards still in the register, with the item each one documents, so the
     * panel reads like the cards register itself.
     */
    private static function cardQuery(Request $request): Builder
    {
        return Card::query()
            ->notArchived()
            ->with(['supply', 'equipment'])
            ->when(static::cardType($request), fn ($q, $type) => $q->where('type', $type))
            ->when($request->filled('card_search'), function ($q) use ($request) {
                $search = $request->card_search;

                $q->where(function ($sub) use ($search) {
                    $sub->where('card_no', 'like', "%{$search}%")
                        ->orWhereHas('supply', fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%"))
                        ->orWhereHas('equipment', fn ($e) => $e->where('name', 'like', "%{$search}%")->orWhere('property_number', 'like', "%{$search}%"));
                });
            })
            ->orderBy('card_no');
    }
}
