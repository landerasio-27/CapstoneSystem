<?php

namespace App\Support;

/**
 * How a sheet of the official forms is broken into printed pages.
 *
 * The list on a form is short enough to fit a page most of the time, and when a
 * month runs long the items simply carried on over the paper's own page break.
 * The page they carried on to then showed the entries with no head above them
 * and no certification under them, because the form's head and the signing
 * block are tables of their own: the printer breaks the list wherever it runs
 * out of paper, and everything outside the list follows the break it was given
 * rather than repeating with the pages.
 *
 * So the break is made here instead. The list is measured before it is printed
 * and cut into pages that each hold the whole form — the head, the entries, and
 * the certification that closes it — with the line that ends the list and the
 * recapitulation on the page that ends it. A form whose items fit a page is cut
 * into one page and prints exactly as it did before.
 *
 * A page is only as full as the printer can make it, and the two printers of
 * these sheets do not agree on how tall a line is. A browser sets a wrapped
 * line at the stylesheet's own 16.25px; the exporter sets it at the font's own
 * box, 22.15px — a third taller — and no rule in the stylesheet moves it. So
 * each is measured for itself: the screen and the printed sheet are cut to the
 * browser's figures, the exported paper to the exporter's, and both come out
 * with pages as full as their own printer can write them. Every measurement is
 * taken off a rendered sheet of that printer and rounded up: a page a row short
 * of items is a form that reads as it should, while a page a row too long is a
 * form the printer breaks somewhere else entirely.
 *
 * Each page is filled to the foot of its own room before the next is started,
 * so only the page that ends the list is ever left short of the paper (see
 * cut).
 */
final class SheetPages
{
    /** The A4 sheet, and the margins every printer of it is given. */
    private const PAGE_MM = 297.0;
    private const MARGIN_MM = 22.0;

    /** The width the sheet's own rules leave between the margins. */
    private const CONTENT_MM = 192.0;

    /** The room a cell's own side padding takes from its column. */
    private const CELL_PAD_MM = 2.1;

    /**
     * How wide a character of the sheet measures. Set to the widest line
     * measured off a printed sheet — 1.9mm a character — so a row is measured
     * as wrapping no later than the sheet wraps it: overcounting a line leaves
     * a page a little short, undercounting one puts an entry on a page that
     * cannot hold it.
     */
    private const CHAR_MM = 1.9;

    /** The shares the RIS list and the monthly sheets rule their columns on. */
    private const RIS_SHARES = ['no' => 14.5, 'end_user' => 11.5, 'item' => 36.0];

    /** The shares the ICS slip rules its own list on. */
    private const ICS_SHARES = ['no' => 14.0, 'item' => 18.0, 'description' => 32.0];

    /**
     * @param  array<string, float>  $mm  the printer's own measurements: the
     *     head over the entries, the certification under them, the line that
     *     ends the list, the recapitulation's head and each line of it, one
     *     wrapped line and the padding a row adds to it, and the room held
     *     back for the measure being a shade out.
     */
    private function __construct(private array $mm)
    {
    }

    /**
     * The sheets a browser reads: the preview on the page, and the sheet the
     * browser prints. Measured on a rendered screen sheet.
     */
    public static function forScreen(): self
    {
        return new self([
            'head' => 39.5,
            'cert' => 26.3,
            'close' => 5.4,
            'recap_head' => 12.0,
            'recap_line' => 5.4,
            'line' => 4.30,
            'row_pad' => 1.06,
            'slack' => 6.0,
        ]);
    }

    /**
     * The sheets the exporter writes: the exported PDF. Measured on an exported
     * sheet, where a line is set a third taller than the browser sets it — the
     * head from the exporter's own top margin down to the rule the list opens
     * on, the certification and the two lines of the recapitulation off the
     * page the list ends on, and a row's padding as the sheet draws it, half a
     * line's worth under a cell's own line. Only a narrow hold-back is kept on
     * top of that, because this measure is already generous: a row is counted
     * at the widest character the sheet sets, so it is never measured for fewer
     * lines than it is drawn with, and reserving a page's worth of room on top
     * of that turned the paper over for entries the sheet had room for.
     */
    public static function forExport(): self
    {
        return new self([
            'head' => 59.7,
            'cert' => 31.4,
            'close' => 6.9,
            'recap_head' => 14.2,
            'recap_line' => 5.4,
            'line' => 5.9,
            'row_pad' => 0.6,
            'slack' => 3.0,
        ]);
    }

    /** The printer this sheet is cut for, from whether it is being exported. */
    public static function for(bool $export): self
    {
        return $export ? self::forExport() : self::forScreen();
    }

    /**
     * The width a column takes on the sheet, from the share it is ruled with.
     */
    public static function columnMm(float $share): float
    {
        return self::CONTENT_MM * $share / 100;
    }

    /**
     * How many printed lines a cell's text needs in a column that wide.
     *
     * The text is wrapped at a plain word boundary rather than the printer's
     * own measure: what a page needs is the number of lines a cell comes to,
     * and that count is deliberately a line to the generous side (see CHAR_MM).
     * A cell holding nothing still stands a line tall, because the row it is in
     * does.
     */
    public static function lines(?string $text, float $widthMm): int
    {
        $room = max(self::CHAR_MM, $widthMm - self::CELL_PAD_MM);
        $budget = max(1, (int) floor($room / self::CHAR_MM));

        $lines = 0;
        // A description may be written over several lines of its own, and each
        // of them wraps in the column separately.
        foreach (preg_split('/\R/', (string) $text) as $line) {
            $lines += self::wrapped(trim($line), $budget);
        }

        return max(1, $lines);
    }

    /**
     * How many lines a run of text wraps to in a column that holds so many
     * characters. Words are filled in one at a time and the line is broken at
     * the first word that does not fit, which is how a printer wraps it — a
     * count that simply divides by the width would find fewer lines than the
     * sheet has, because a word too long for what is left of the line is
     * carried over to the next rather than split.
     */
    private static function wrapped(string $text, int $budget): int
    {
        if ($text === '') {
            return 1;
        }

        $lines = 1;
        $used = 0;

        foreach (preg_split('/\s+/', $text) as $word) {
            $length = mb_strlen($word);

            if ($used === 0) {
                $used = $length;
            } elseif ($used + 1 + $length <= $budget) {
                $used += 1 + $length;
            } else {
                $lines++;
                $used = $length;
            }
        }

        return $lines;
    }

    /**
     * The height a row takes over the number of lines its tallest cell wraps to.
     */
    public function rowMm(int $lines): float
    {
        return $lines * $this->mm['line'] + $this->mm['row_pad'];
    }

    /**
     * The height of one line of the RIS list — the item and its description in
     * the column the form gives them, or the end-user beside it, whichever
     * stands taller. The number holds its column on one line either way.
     *
     * `$continues` says this line carries on the run of the paper above it.
     * The form writes the number and the end-user once over such a run and
     * leaves both cells blank under it, so a line that carries the run on is
     * only as tall as the item beside it — measuring the name into it as well
     * would count every line of the run a line taller than the sheet draws it.
     */
    public function risRowMm(array $row, bool $continues = false): float
    {
        $item = trim(($row['item'] ?? '')."\n".($row['description'] ?? ''));

        $lines = self::lines($item, self::columnMm(self::RIS_SHARES['item']));

        if (! $continues) {
            $lines = max($lines, self::lines($row['end_user'] ?? '', self::columnMm(self::RIS_SHARES['end_user'])));
        }

        return $this->rowMm($lines);
    }

    /**
     * The height of one line of the ICS slip's list: the item with its item
     * code under it, the property number, and the description, over the columns
     * the slip rules them on.
     */
    public function icsRowMm(array $row): float
    {
        $code = ($row['item_code'] ?? '') === ($row['property_no'] ?? '') ? '' : ($row['item_code'] ?? '');
        $item = trim(($row['name'] ?? '')."\n".$code);

        return $this->rowMm(max(
            self::lines($item, self::columnMm(self::ICS_SHARES['item'])),
            self::lines($row['description'] ?? '', self::columnMm(self::ICS_SHARES['description'])),
            self::lines($row['property_no'] ?? '', self::columnMm(self::ICS_SHARES['no'])),
        ));
    }

    /**
     * How many lines the recapitulation prints, so that the page which ends the
     * list knows the room to keep for it. The block is ruled even when nothing
     * is written into it — one line stands for the whole of it on a blank form
     * — and its TOTAL line closes it wherever the block carries figures. A
     * block whose figures are all withheld still rules one line, so the count
     * never falls short of what the block takes.
     *
     * The count follows the flags the block is handed, not the lines of the
     * recap alone: a run with neither the stock numbers nor the money written
     * into it is ruled as a single blank line, however many stock numbers it
     * holds (see _ris_recap), and reserving a line for each of them would hold
     * a page's worth of room back for rows the sheet never draws.
     */
    public static function recapLines(array $recap, bool $showStock, bool $showCosts, bool $showTotal): int
    {
        if ($recap === [] || (! $showStock && ! $showCosts)) {
            return 1;
        }

        return count($recap) + ($showTotal ? 1 : 0);
    }

    /**
     * The room the closing blocks need under the list on the page that ends it:
     * the line that ends the list, the recapitulation where the report prints
     * one, and anything else that closes that page alone — the ICS slip's
     * signing blocks, which stand under its certification. Everything the page
     * carries either way, the head and the certification, is already held back
     * by the room a page has (see roomMm).
     */
    public function tailMm(int $recapLines = 0, float $extraMm = 0.0): float
    {
        $recap = $recapLines > 0
            ? $this->mm['recap_head'] + $recapLines * $this->mm['recap_line']
            : 0.0;

        return $this->mm['close'] + $recap + $extraMm;
    }

    /**
     * The room a page has for the entries of its list where the list ends on it:
     * what the paper leaves between the margins, under the head and over the
     * certification the sheet is signed at the foot of, with a little held back
     * for the measure being a shade out. The blocks that stand under the entries
     * and close the list — the line that ends it and the recapitulation — are
     * held back separately, because they stand on this page alone (see tailMm).
     */
    public function roomMm(): float
    {
        return $this->roomOfPage() - $this->mm['cert'];
    }

    /**
     * The room a page has where the list carries on over it — every page of a
     * run but the one that ends it. Nothing stands under those entries but the
     * line that closes the block of them: the certification is the sheet's own
     * foot, so it is written once, on the page the list ends on, and the pages
     * before it carry entries down to the foot of the paper.
     */
    public function openRoomMm(): float
    {
        return $this->roomOfPage() - $this->mm['close'];
    }

    /**
     * What the paper leaves a page between the margins and under the head, less
     * the room held back for the measure being a shade out.
     */
    private function roomOfPage(): float
    {
        return self::PAGE_MM
            - self::MARGIN_MM
            - $this->mm['head']
            - $this->mm['slack'];
    }

    /**
     * Cut the list into the pages it will be printed on.
     *
     * Each page is filled to the foot of its own room before the next is
     * started: the entries run down the sheet until the next of them would
     * carry past the certification, and only then does the paper turn. A form
     * is read from the top, so a page printed with its entries stopping
     * halfway down leaves the office looking at a blank half-sheet — the
     * measure is spent rather than shared out, and the page that ends the list
     * simply takes what is left of it along with the closing blocks.
     *
     * @param  array<int, mixed>  $rows  the entries, in the order they print
     * @param  callable(mixed, int, bool): float  $heightOf  an entry's own
     *     height in mm, given the entry, its place in the list, and whether it
     *     is the one the page it lands on opens with
     * @param  float  $tailMm  the room the closing blocks need on the last page
     * @return array<int, array<int, mixed>> the rows of each page, in order
     */
    public function cut(array $rows, callable $heightOf, float $tailMm = 0.0): array
    {
        if ($rows === []) {
            return [[]];
        }

        $rows = array_values($rows);
        $pages = [];
        $at = 0;

        foreach ($this->sizes($rows, $heightOf, $tailMm) as $size) {
            $pages[] = array_slice($rows, $at, $size);
            $at += $size;
        }

        return $pages;
    }

    /**
     * Cut the RIS list — the entries themselves, measured the way the sheet
     * draws them: the form writes the paper's number and the end-user once over
     * the run of lines belonging to one paper, and again at the head of every
     * page the run carries on over, so the measure follows the same two rules
     * the list itself is printed by.
     *
     * @param  array<int, array>  $rows
     * @return array<int, array<int, array>> the rows of each page, in order
     */
    public function cutRis(array $rows, float $tailMm = 0.0): array
    {
        $rows = array_values($rows);

        return $this->cut($rows, function (array $row, int $index, bool $opensPage) use ($rows): float {
            $previous = $index > 0 ? $rows[$index - 1] : null;

            $continues = ! $opensPage
                && $previous !== null
                && ($row['ris_no'] ?? '') === ($previous['ris_no'] ?? '')
                && ($row['end_user'] ?? '') === ($previous['end_user'] ?? '');

            return $this->risRowMm($row, $continues);
        }, $tailMm);
    }

    /**
     * How many entries each page takes, in order: every page but the one that
     * ends the list is filled to the foot of its own room, and the entry that
     * will not fit is the one the next page opens with.
     *
     * An entry's height can depend on which page it lands on — a line of the
     * RIS list carries the paper's number and the end-user again when it opens
     * one (see cutRis) — so the entry that opens a page is measured for the
     * page it opens rather than for the one it just missed.
     *
     * @param  array<int, mixed>  $rows
     * @param  callable(mixed, int, bool): float  $heightOf
     * @return array<int, int>
     */
    private function sizes(array $rows, callable $heightOf, float $tailMm): array
    {
        $pages = [];
        $taken = 0;
        $used = 0.0;
        $last = count($rows) - 1;
        $opensPage = true;

        foreach ($rows as $index => $row) {
            $height = $heightOf($row, $index, $opensPage);

            // The line that ends the list, the recapitulation under it and the
            // certification the sheet is signed at the foot of all stand on the
            // page the last entry lands on — so that entry counts for its own
            // height and the closing blocks together, and the page it lands on
            // is measured as the one that ends the list. Every page before it
            // carries entries alone, down to the foot of the paper (see
            // openRoomMm). Everything either page carries anyway, the head, is
            // already held back by the room it has.
            $closing = $index === $last ? $tailMm : 0.0;
            $room = $index === $last ? $this->roomMm() : $this->openRoomMm();

            if ($taken > 0 && $used + $height + $closing > $room) {
                $pages[] = $taken;
                $taken = 0;
                $used = 0.0;
                $opensPage = true;
                $height = $heightOf($row, $index, true);
            }

            $taken++;
            $used += $height;
            $opensPage = false;
        }

        if ($taken > 0) {
            $pages[] = $taken;
        }

        return $pages ?: [0];
    }
}
