<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Supply;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A year's worth of ICS and RIS papers.
 *
 * The registers, the monthly report and its page numbering are all read a
 * month at a time, so a handful of documents cannot show what any of them do.
 * This seeder writes a run of papers across the current year — four RIS
 * (supplies issued) and four ICS (property handed to a custodian) in every
 * month, so the two papers carry the same weight in a report read as one run
 * rather than one of them thinning the other out, and January a longer one of
 * each — taking the custodians, rooms, items and prices from the records
 * already on file.
 *
 * Every paper carries several lines for the same reason: a report of one or
 * two lines a page cannot show what the column widths, the blank lines at the
 * foot or the recapitulation do.
 *
 * It writes the papers only. Nothing here moves stock or touches a card: the
 * printed reports are cut from the documents table, and the seeded catalogue
 * has never been through Add Stock, so there is no ledger entry to write
 * alongside them.
 *
 * Every row carries the marker below, and a re-run brings each month up to
 * its quota rather than writing the year again: a month already holding its
 * papers is left as it is, and one holding fewer gains only what it is short
 * of — whether the count was raised here, or the month was seeded from an
 * older, thinner sample. So a second run neither doubles the sample year nor
 * takes anything away.
 */
class DocumentSeeder extends Seeder
{
    /** Stamped on every row this seeder writes, so a re-run is a no-op. */
    private const MARKER = 'SampleDataSeeder';

    /**
     * Papers per month: supplies issued, and property handed over. The two
     * counts are kept level so a month's run holds as much of one paper as the
     * other, and the monthly report shows both in full.
     */
    private const RIS_PER_MONTH = 4;

    private const ICS_PER_MONTH = 4;

    /**
     * January carries a longer run than the months after it: the office hands
     * the year's property over and issues its supplies in bulk while the
     * classes are still being set up. The month is written to its own, larger
     * count, with both papers raised together as everywhere else, so its report
     * reads long without one paper thinning the other out.
     */
    private const OPENING_MONTH = 1;

    private const OPENING_PAPERS = 8;

    /**
     * Plain prices for the sample papers, by item code and by category.
     *
     * The catalogue is deliberately seeded without costs — a price belongs to
     * the delivery that brought the item in — so where the record holds none
     * the sample papers use these rather than issuing everything at zero.
     */
    private const PRICES = [
        'SUP-00001' => 235.00,   // Bond Paper A4 (Ream)
        'SUP-00002' => 12.00,    // Ballpoint Pen (Black)
        'SUP-00004' => 45.00,    // Whiteboard Marker
        'SUP-00005' => 8.00,     // Folder (Long, Brown)
        'EQP-00002' => 28500.00, // Epson LCD Projector
        'EQP-00003' => 15900.00, // HP LaserJet Printer
        'EQP-00004' => 42000.00, // Split-Type Air Conditioner 2HP
        'EQP-00005' => 3200.00,  // Office Swivel Chair
        'EQP-00006' => 6500.00,  // Wooden Teacher's Table
        'EQP-00007' => 4800.00,  // Whiteboard 4x8ft
        'EQP-00008' => 7900.00,  // Wireless Microphone Set
    ];

    public function run(): void
    {
        $supplies = Supply::orderBy('id')->get();
        $equipment = Equipment::orderBy('id')->get();
        $custodians = Teacher::orderBy('id')->get();
        $admin = User::orderBy('id')->first();

        if ($supplies->isEmpty() || $custodians->isEmpty() || $admin === null) {
            $this->command?->warn('Seed the master data first: sample papers need supplies, equipment and custodians.');

            return;
        }

        $year = (int) now()->year;
        $written = ['RIS' => 0, 'ICS' => 0];

        foreach (range(1, 12) as $month) {
            // The month is brought up to its quota, not written out again: the
            // papers already on file keep their own dates, lines and numbers,
            // and the new ones simply carry the month past the count it holds.
            for ($nth = $this->papersOnFile('RIS', $year, $month) + 1; $nth <= $this->quota('RIS', $month); $nth++) {
                $this->ris($supplies, $custodians, $admin, $year, $month, $nth);
                $written['RIS']++;
            }

            for ($nth = $this->papersOnFile('ICS', $year, $month) + 1; $nth <= $this->quota('ICS', $month); $nth++) {
                $this->ics($equipment, $custodians, $admin, $year, $month, $nth);
                $written['ICS']++;
            }
        }

        if ($written['RIS'] === 0 && $written['ICS'] === 0) {
            $this->command?->warn('Every month already holds its papers; nothing written.');

            return;
        }

        $this->command?->info(sprintf(
            'Sample papers for %d: %d RIS and %d ICS written, %d and %d now on file.',
            $year,
            $written['RIS'],
            $written['ICS'],
            $this->papersOnFile('RIS', $year),
            $this->papersOnFile('ICS', $year),
        ));
    }

    /**
     * The papers one month of the year carries, by type: the run every month
     * holds, and the longer one January opens the year with.
     */
    private function quota(string $type, int $month): int
    {
        if ($month === self::OPENING_MONTH) {
            return self::OPENING_PAPERS;
        }

        return $type === 'RIS' ? self::RIS_PER_MONTH : self::ICS_PER_MONTH;
    }

    /**
     * The sample papers one month of the year already holds: the whole year's
     * where no month is named.
     *
     * Counted from this seeder's own rows alone. A paper raised by hand through
     * the register belongs to the office and does not stand in for a sample one
     * the month is short of, so a month is brought up to its quota whatever
     * else has been written beside it.
     */
    private function papersOnFile(string $type, int $year, ?int $month = null): int
    {
        return Document::where('source_type', self::MARKER)
            ->where('type', $type)
            ->whereYear('document_date', $year)
            ->when($month !== null, fn ($query) => $query->whereMonth('document_date', $month))
            ->count();
    }

    /**
     * One RIS: office supplies issued to a custodian, priced per unit.
     *
     * @param  Collection<int, Supply>  $supplies
     * @param  Collection<int, Teacher>  $custodians
     */
    private function ris(Collection $supplies, Collection $custodians, User $admin, int $year, int $month, int $nth): void
    {
        $document = Document::create($this->header(
            'RIS',
            $custodians[($month * 2 + $nth) % $custodians->count()],
            $admin,
            $this->dateIn($year, $month, $nth, 2),
        ) + [
            'purpose' => 'Supplies issued for the month.',
        ]);

        // Three to five lines, taken from the catalogue in order, so a month
        // reads like a month's issues rather than a random draw.
        $lines = 3 + (($month + $nth) % 3);

        for ($index = 0; $index < $lines; $index++) {
            $supply = $supplies[($month * 3 + $nth + $index) % $supplies->count()];
            $quantity = 5 + (($month + $nth * 3 + $index * 4) % 20);

            $this->line($document, [
                'supply_id' => $supply->id,
                'description' => $supply->name,
                'unit' => $supply->unit ?: 'piece',
                'quantity' => $quantity,
                // What left the shelf: the office issues what it asked for.
                'issued_quantity' => $quantity,
                'unit_cost' => $this->price($supply),
            ]);
        }
    }

    /**
     * One ICS: property handed to a custodian, one or two items at a time.
     *
     * @param  Collection<int, Equipment>  $equipment
     * @param  Collection<int, Teacher>  $custodians
     */
    private function ics(Collection $equipment, Collection $custodians, User $admin, int $year, int $month, int $nth): void
    {
        $custodian = $custodians[($month * 3 + $nth + 1) % $custodians->count()];

        $document = Document::create($this->header(
            'ICS',
            $custodian,
            $admin,
            $this->dateIn($year, $month, $nth, 6),
        ));

        if ($equipment->isEmpty()) {
            return;
        }

        // Three to five pieces, so a handover fills its paper as an issue of
        // supplies does: the two lists are read side by side in a month.
        $lines = 3 + (($month + $nth) % 3);

        for ($index = 0; $index < $lines; $index++) {
            $item = $equipment[($month + $nth * 2 + $index * 3) % $equipment->count()];

            $this->line($document, [
                'equipment_id' => $item->id,
                'description' => $item->name,
                'unit' => $item->unit ?: 'unit',
                'quantity' => 1,
                'issued_quantity' => 1,
                'unit_cost' => $this->price($item),
                'property_number' => $item->property_number,
                'date_acquired' => $item->date_acquired,
                'estimated_useful_life' => $item->estimated_useful_life,
            ]);
        }
    }

    /**
     * The head of a paper: who it belongs to, who signed it, and the marker
     * that keeps a re-run from writing the sample year twice.
     *
     * @return array<string, mixed>
     */
    private function header(string $type, Teacher $custodian, User $admin, Carbon $date): array
    {
        return [
            'document_no' => Document::nextDocumentNumber($type),
            'type' => $type,
            // Issued, which is what the reports read: a draft has not handed
            // anything over yet.
            'status' => 'issued',
            'teacher_id' => $custodian->id,
            'custodian' => $custodian->full_name,
            'room_id' => $custodian->room_id,
            'prepared_by' => $admin->id,
            'approved_by' => $admin->id,
            'issued_by' => $admin->id,
            'entity_name' => Card::DEFAULT_ENTITY,
            'fund_cluster' => '2',
            'document_date' => $date->toDateString(),
            'approved_at' => $date->copy()->addHours(2),
            'issued_at' => $date->copy()->addHours(3),
            'source_type' => self::MARKER,
            'source_id' => 0,
        ];
    }

    /**
     * One issued line. The amount is never typed: it is the quantity at the
     * unit cost, as it is on every printed report.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function line(Document $document, array $attributes): void
    {
        $quantity = (int) $attributes['quantity'];
        $unitCost = (float) ($attributes['unit_cost'] ?? 0);

        $document->items()->create($attributes + [
            'unit' => 'unit',
            'amount' => round($quantity * $unitCost, 2),
        ]);
    }

    /**
     * What an item is worth on the sample papers: what its own record holds,
     * or a plain price from the table above where the record has none.
     */
    private function price(Supply|Equipment $item): float
    {
        $recorded = (float) $item->unit_cost;

        if ($recorded > 0) {
            return $recorded;
        }

        return self::PRICES[$item->item_code] ?? 100.00;
    }

    /** A day inside the month, so a month's papers are not all dated the same. */
    private function dateIn(int $year, int $month, int $nth, int $step): Carbon
    {
        return Carbon::create($year, $month, min(28, 2 + ($nth - 1) * 8 + $step), 9, 0, 0);
    }
}
