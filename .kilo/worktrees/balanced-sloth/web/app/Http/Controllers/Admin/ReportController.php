<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Equipment;
use App\Models\Report;
use App\Models\Room;
use App\Models\Supply;
use App\Models\SupplyRequestItem;
use App\Models\Teacher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /**
     * The reports hub: the official forms tile and the three table reports
     * below it. All of them read records the supply office keeps, so both
     * roles are handed the same page and the same filter lists.
     */
    public function index(): View
    {
        return view('admin.reports.index', [
            'rooms' => Room::orderBy('name')->get(),
            'teachers' => Teacher::orderBy('last_name')->get(),
            'categories' => Supply::query()->whereNotNull('category')->distinct()->pluck('category'),
        ]);
    }

    public function inventorySummary(Request $request): Response|View|BinaryFileResponse
    {
        $supplies = Supply::with('room')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->room_id))
            ->orderBy('name')->get();

        $equipment = Equipment::with('room')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->room_id))
            ->orderBy('name')->get();

        $headings = ['Type', 'Item Code', 'Name', 'Category', 'Qty on Hand', 'Reorder Level', 'Unit Cost', 'Room', 'Status'];

        $rows = collect()
            // Only supplies carry a re-order point the office can set.
            ->concat($supplies->map(fn ($s) => ['Supply', $s->item_code, $s->name, $s->category, $s->quantity_on_hand, $s->reorder_level, number_format($s->unit_cost, 2), $s->room?->code, $s->isLowStock() ? 'Low Stock' : 'OK']))
            ->concat($equipment->map(fn ($e) => ['Equipment', $e->item_code, $e->name, $e->category, $e->quantity_on_hand, '—', number_format($e->unit_cost, 2), $e->room?->code, ucfirst($e->status)]))
            ->toArray();

        return $this->respond($request, 'Inventory Summary', $headings, $rows);
    }

    public function supplyIssuance(Request $request): Response|View|BinaryFileResponse
    {
        $items = SupplyRequestItem::with(['supply', 'supplyRequest.requestedBy'])
            ->whereHas('supplyRequest', function ($q) use ($request) {
                $q->where('status', 'issued');
                $q->when($request->filled('date_from'), fn ($sub) => $sub->whereDate('updated_at', '>=', $request->date_from));
                $q->when($request->filled('date_to'), fn ($sub) => $sub->whereDate('updated_at', '<=', $request->date_to));
            })
            ->where('quantity_issued', '>', 0)
            ->get();

        $headings = ['Request No.', 'Supply', 'Quantity Issued', 'Requested By', 'Date'];

        $rows = $items->map(fn ($i) => [
            $i->supplyRequest->request_no,
            $i->supply->name,
            $i->quantity_issued,
            $i->supplyRequest->requestedBy?->name,
            $i->supplyRequest->updated_at->format('Y-m-d'),
        ])->toArray();

        return $this->respond($request, 'Supply Issuance', $headings, $rows);
    }

    public function documentRegister(Request $request): Response|View|BinaryFileResponse
    {
        $documents = Document::with(['teacher', 'room'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('document_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('document_date', '<=', $request->date_to))
            ->latest('document_date')
            ->get();

        $headings = ['Document No.', 'Type', 'Status', 'Custodian', 'Room', 'Date'];

        $rows = $documents->map(fn ($d) => [
            $d->document_no,
            $d->type,
            ucfirst($d->status),
            $d->custodian_name,
            $d->room?->code,
            $d->document_date->format('Y-m-d'),
        ])->toArray();

        return $this->respond($request, 'Document Register', $headings, $rows);
    }

    /*
     * =====================================================================
     * PAR / ICS / RIS — the official documents
     * =====================================================================
     *
     * Three separate papers with three separate templates: a Property
     * Acknowledgement Receipt, an Inventory Custodian Slip and a Report of
     * Supplies and Materials Issued. They are documents to sign and file, not
     * tables of figures, so they are generated beside the reports above and
     * never share a layout.
     *
     * Nothing here writes to the database and nothing is invented. Every line
     * is read from a record that already exists — equipment for PAR and ICS,
     * RIS documents for RIS — and every amount is quantity × unit cost, worked
     * out here so it can never be typed in by hand or disagree with the
     * ledger.
     */

    /** The official documents, and the label each one is known by. */
    private const DOCUMENT_TYPES = ['par' => 'PAR', 'ics' => 'ICS', 'ris' => 'RIS', 'monthly' => 'Monthly Report'];

    /** The position printed under the supply officer's name on the PAR/ICS. */
    private const SUPPLY_OFFICER_POSITION = 'Administrative Officer I (Supply Officer I)';

    /**
     * The title the supply office's certification is signed under on the RIS and
     * the monthly report — the office's own wording, which the account's profile
     * position stands in for once it has one.
     */
    private const SUPPLY_OFFICER_TITLE = 'Administrative Officer IV';

    /** Lines whose RIS names no end-user still get a report of their own. */
    /** The report picker: property documents vs. supply documents. */
    public function documents(): View
    {
        return view('admin.reports.documents.index');
    }

    /** A report generator: the filters, and the document they produce. */
    public function document(Request $request, string $type): View
    {
        return $this->generator(
            $request,
            $type,
            route('admin.reports.document.index', ['type' => $type]),
            route('admin.reports.document.print', ['type' => $type]),
            route('admin.reports.document.pdf', ['type' => $type]),
            route('admin.reports.documents.index'),
        );
    }

    /**
     * The Monthly Report generator. It prints the same official grid as the
     * RIS, so it is built by the same code, but it takes its own routes: a run
     * of the month's papers is not a document type the {type} route could
     * carry.
     */
    public function monthlyDocument(Request $request): View
    {
        return $this->generator(
            $request,
            'monthly',
            route('admin.reports.monthly.index'),
            route('admin.reports.monthly.print'),
            route('admin.reports.monthly.pdf'),
            // No way back to the PAR / ICS / RIS picker: this report is not one
            // of those three papers and is not opened from there — it sits on
            // its own under Reports.
            null,
        );
    }

    /**
     * The generator page: the report's data plus the pickers its form needs.
     * $backUrl is the list this report was opened from, when it has one.
     */
    private function generator(Request $request, string $type, string $formAction, string $printUrl, string $pdfUrl, ?string $backUrl): View
    {
        return view('admin.reports.documents.generator', $this->documentPayload($request, $type) + [
            'formAction' => $formAction,
            'printUrl' => $printUrl,
            'pdfUrl' => $pdfUrl,
            'backUrl' => $backUrl,
            'backLabel' => 'Back to PAR / ICS / RIS',
            'equipmentPicker' => in_array($type, ['par', 'ics'], true) ? $this->equipmentPicker() : collect(),
            'custodians' => Teacher::orderBy('last_name')->orderBy('first_name')->get(),
            'categories' => Supply::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'months' => $this->monthOptions(),
            'years' => $this->risYears(),
        ]);
    }

    /**
     * The document on its own, ready to print. Same filters, same template as
     * the generator, but with no application chrome around it — the sheet is
     * the whole page, so printing cannot pull the sidebar or the filters onto
     * the paper.
     */
    public function printDocument(Request $request, string $type): View
    {
        return $this->printSheet($request, $type, 'admin.reports.document.index', 'admin.reports.document.pdf', ['type' => $type]);
    }

    public function printMonthlyDocument(Request $request): View
    {
        return $this->printSheet($request, 'monthly', 'admin.reports.monthly.index', 'admin.reports.monthly.pdf');
    }

    private function printSheet(Request $request, string $type, string $backRoute, string $pdfRoute, array $params = []): View
    {
        // The chrome links hand the reader's filters straight back, so the PDF
        // and the generator can be reopened from the printed page. A filter
        // that was left empty is dropped rather than carried along: it narrows
        // nothing, and every box on a browser's printed page is one more thing
        // the office has to read past.
        $params += collect($request->query())
            ->except('print')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        return view('admin.reports.documents.sheet', $this->documentPayload($request, $type, true) + [
            'autoprint' => $request->boolean('print'),
            'backUrl' => route($backRoute, $params),
            'pdfUrl' => route($pdfRoute, $params),
        ]);
    }

    /** The document as a PDF. Rendered from the same body partial as print. */
    public function pdfDocument(Request $request, string $type): Response
    {
        return $this->documentPdf($request, $type);
    }

    public function pdfMonthlyDocument(Request $request): Response
    {
        return $this->documentPdf($request, 'monthly');
    }

    private function documentPdf(Request $request, string $type): Response
    {
        $payload = $this->documentPayload($request, $type, true, true);

        $pdf = Pdf::loadView('admin.reports.documents.pdf', $payload)->setPaper('a4', 'portrait');

        return $pdf->download($payload['filename']);
    }

    /**
     * The one place that decides what a report contains, per document type.
     *
     * `$paper` says whether this is the copy that leaves the office — the
     * printed sheet or the exported PDF — rather than the working page the
     * report is built on. The two differ in one thing only: a paper is always
     * written under a number, while the working page holds one only once the
     * report has been generated.
     *
     * `$export` says which of those two is being written, the sheet the browser
     * prints or the PDF: a long list is cut into pages measured for each of them
     * separately, because the two printers set a line at different heights (see
     * SheetPages).
     */
    private function documentPayload(Request $request, string $type, bool $paper = false, bool $export = false): array
    {
        abort_unless(array_key_exists($type, self::DOCUMENT_TYPES), 404);

        return array_merge([
            'type' => $type,
            'typeLabel' => self::DOCUMENT_TYPES[$type],
            'forExport' => $export,
        ], match ($type) {
            'par' => $this->parPayload($request),
            'ics' => $this->icsPayload($request),
            'ris' => $this->risPayload($request, $paper),
            'monthly' => $this->monthlyPayload($request, $paper, $export),
        });
    }

    /*
     * ---------------------------------------------------------------------
     * PAR — Property Acknowledgement Receipt
     * ---------------------------------------------------------------------
     */

    /**
     * The PAR is built from the equipment records the administrator picks: the
     * property number, description, unit, cost and date acquired all come off
     * the record, and the person accountable for it is the custodian the
     * equipment is actually assigned to.
     */
    private function parPayload(Request $request): array
    {
        $equipment = $this->selectedEquipment($request);
        $rows = $equipment->map(fn (Equipment $item) => $this->equipmentRow($item))->all();
        $total = round((float) array_sum(array_column($rows, 'amount')), 2);

        $shared = $this->sharedCustodian($equipment);
        $date = $this->documentDate($request);

        $form = [
            'entity_name' => (string) ($request->input('entity_name') ?: Card::DEFAULT_ENTITY),
            'fund_cluster' => (string) $request->input('fund_cluster', ''),
            // Blank means "the number the office would get next", read off the
            // PARs already on file rather than made up here.
            'document_no' => (string) ($request->input('document_no') ?: Document::suggestedDocumentNumber('PAR')),
            'document_date' => $date->toDateString(),
            'accountable' => (string) ($request->input('accountable') ?: $shared['name']),
            'accountable_position' => (string) ($request->input('accountable_position') ?: $shared['position']),
            'room' => (string) ($request->input('room') ?: $shared['room']),
            'received_from' => (string) ($request->input('received_from') ?: auth()->user()?->name),
            'received_from_position' => (string) ($request->input('received_from_position') ?: $this->supplyOfficePosition(self::SUPPLY_OFFICER_POSITION)),
            'received_by' => (string) ($request->input('received_by') ?: $shared['name']),
            'received_by_position' => (string) ($request->input('received_by_position') ?: $shared['position']),
        ];

        return [
            'documentTitle' => 'Property Acknowledgement Receipt',
            'filename' => 'PAR-'.$form['document_no'].'.pdf',
            'form' => $form,
            // What the form submits is what the paper prints, so the two cannot
            // drift: the header reads the very same values.
            'meta' => $form,
            'dateLabel' => $date->format('F d, Y'),
            'rows' => $rows,
            'totalAmount' => $total,
            'selected' => $equipment->pluck('id')->all(),
            'hasSelection' => $request->has('equipment'),
            'empty' => $rows === [],
            'summary' => count($rows).' equipment record(s) on the receipt &middot; Total '.number_format($total, 2),
        ];
    }

    /*
     * ---------------------------------------------------------------------
     * ICS — Inventory Custodian Slip
     * ---------------------------------------------------------------------
     */

    /**
     * An ICS is the paper one person signs for, so the selected equipment is
     * grouped by its custodian and a slip is printed per custodian. The
     * selection starts from the property that was assigned through an ICS (see
     * equipmentPicker), and the administrator can add to or trim it.
     */
    private function icsPayload(Request $request): array
    {
        $equipment = $this->selectedEquipment($request);
        $date = $this->documentDate($request);
        $base = (string) ($request->input('document_no') ?: Document::suggestedDocumentNumber('ICS'));

        $form = [
            'entity_name' => (string) ($request->input('entity_name') ?: Card::DEFAULT_ENTITY),
            'fund_cluster' => (string) $request->input('fund_cluster', ''),
            'document_no' => $base,
            'document_date' => $date->toDateString(),
            'received_from' => (string) ($request->input('received_from') ?: auth()->user()?->name),
            'received_from_position' => (string) ($request->input('received_from_position') ?: $this->supplyOfficePosition(self::SUPPLY_OFFICER_POSITION)),
            'received_by_position' => (string) $request->input('received_by_position', ''),
        ];

        $sheets = $equipment
            ->groupBy(fn (Equipment $item) => $item->custodian_name ?: '')
            ->sortKeys()
            ->values()
            ->map(function (Collection $group, int $index) use ($base, $form) {
                $rows = $group->map(fn (Equipment $item) => $this->equipmentRow($item))->values()->all();
                $custodian = $group->first();

                // The recapitulation reads each item's property number as its
                // stock number, then reuses the RIS arithmetic so the slip and
                // the monthly report total a custodian's holdings the same way.
                $recapRows = array_map(
                    fn (array $row) => ['stock_no' => $row['property_no']] + $row,
                    $rows,
                );

                return [
                    // Sheets take the next free ICS numbers on file
                    // (ICS-2026-00007, ICS-2026-00008, …) instead of every
                    // slip carrying the same number.
                    'number' => $this->nthDocumentNumber($base, $index),
                    'custodian' => $custodian->custodian_name,
                    'custodian_position' => $custodian->teacher?->position ?: $form['received_by_position'],
                    'total' => round((float) array_sum(array_column($rows, 'amount')), 2),
                    'recap' => $this->risRecapitulation($recapRows),
                    'rows' => $rows,
                ];
            })
            ->all();

        $itemCount = (int) array_sum(array_map(fn (array $sheet) => count($sheet['rows']), $sheets));
        $sheetCount = count($sheets);
        $total = round((float) array_sum(array_column($sheets, 'total')), 2);

        // Nothing to report still prints as a blank slip: the form the office
        // would fill in by hand, with the message where its lines would go.
        if ($sheets === []) {
            $sheets = [[
                'number' => $base,
                'custodian' => '',
                'custodian_position' => '',
                'total' => 0.0,
                'recap' => [],
                'rows' => [],
            ]];
        }

        return [
            'documentTitle' => 'Inventory Custodian Slip',
            'filename' => 'ICS-'.$base.'.pdf',
            'form' => $form,
            'meta' => [
                'agency' => Card::DEFAULT_ENTITY,
                'date' => $form['document_date'],
                'posted_by' => $form['received_from'],
                'posted_position' => $this->supplyOfficePosition(self::SUPPLY_OFFICER_TITLE),
            ],
            'dateLabel' => $date->format('F d, Y'),
            'sheets' => $sheets,
            'totalAmount' => $total,
            'selected' => $equipment->pluck('id')->all(),
            'hasSelection' => $request->has('equipment'),
            'empty' => $itemCount === 0,
            'summary' => $sheetCount.' custodian slip(s) &middot; '.$itemCount.' item(s) &middot; Total '.number_format($total, 2),
        ];
    }

    /*
     * ---------------------------------------------------------------------
     * RIS — Report of Supplies and Materials Issued
     * ---------------------------------------------------------------------
     */

    /**
     * A monthly report of what the supply office actually issued: every RIS
     * document dated inside the chosen month, expanded line by line. The
     * window is the month's own first and last day, so nothing outside it can
     * slip in, and the amounts are all quantity × unit cost.
     */
    private function risPayload(Request $request, bool $paper = false): array
    {
        [$start, $end] = $this->risPeriod($request);
        $periodKey = $this->periodKey($start);
        $documents = $this->risDocuments($request, $start, $end);
        $rows = $this->risRows($documents);
        $total = round((float) array_sum(array_column($rows, 'amount')), 2);

        // The number the records give the report, unless the office wrote its
        // own on the sheet for this month — the sheet then carries that one.
        $written = $this->writtenNumbers($request, 'report_no', $periodKey);
        $number = $this->reportNumber(
            (string) ($written['written'][''] ?? ''),
            $this->risReportNumber($documents->first()?->document_no, $start),
            $this->reportIsNumbered($request, $paper),
        );

        return [
            'documentTitle' => 'Report of Supplies and Materials Issued',
            'filename' => sprintf('RIS-%s-%s.pdf', $start->format('Y'), $start->format('m')),
            'form' => $this->risForm($request, $start, $number),
            // The month the numbers on the sheet belong to, and the numbers
            // written on other months' sheets, which the form carries on.
            'periodKey' => $periodKey,
            'carriedNumbers' => $written['carried'],
            'meta' => [
                'agency' => Card::DEFAULT_ENTITY,
                'period' => $this->periodLabel($start, $end),
                'number' => $number,
                'posted_by' => $this->risIssuer($documents),
                // The office signs the sheet under its own position, held on the
                // account rather than typed on every report.
                'posted_position' => $this->supplyOfficePosition(self::SUPPLY_OFFICER_TITLE),
            ],
            'rows' => $rows,
            'recap' => $this->risRecapitulation($rows),
            // The accounting unit fills the money columns in by hand unless the
            // report is asked to print the figures it already knows.
            'showCosts' => $request->boolean('show_costs'),
            // The stock number and quantity are the report's own record, so
            // they are printed unless the report is asked to leave them blank.
            'showStock' => $this->wantsStockColumns($request),
            // The total line closes the block and is printed with it unless the
            // report is asked to leave it off.
            'showTotal' => $this->wantsTotalRow($request),
            'documentCount' => $documents->count(),
            'totalAmount' => $total,
            'empty' => $rows === [],
            'summary' => $documents->count().' RIS document(s) &middot; '.count($rows).' item line(s) &middot; Total '.number_format($total, 2),
        ];
    }

    /**
     * The RIS documents a monthly report is built from: those dated inside the
     * chosen month, narrowed by whatever filters were sent.
     *
     * @return Collection<int, Document>
     */
    private function risDocuments(Request $request, Carbon $start, Carbon $end, string $type = 'RIS'): Collection
    {
        return $this->risDocumentQuery($request, $start, $end, $type)->get();
    }

    /**
     * The query those documents are read with. It stands apart from reading
     * them because the monthly report numbers its pages over a whole year, and
     * a page counter is in no position to re-ask the database month by month.
     *
     * @return Builder<Document>
     */
    private function risDocumentQuery(Request $request, Carbon $start, Carbon $end, string $type = 'RIS'): Builder
    {
        return Document::query()
            // The monthly report can be cut from either the RIS (supplies
            // issued) or the ICS (property handed to a custodian); the plain
            // RIS report always reads its own type.
            ->where('type', $type)
            // A draft has not issued anything yet; the report covers the papers
            // the supply office has already committed to.
            ->whereIn('status', ['approved', 'issued'])
            // Filtered on the document date — the day the paper is dated — not
            // on when the row happened to be written. Compared by day rather
            // than as a raw string so the last day of the month is included
            // whatever the column happens to store behind it.
            ->whereDate('document_date', '>=', $start->toDateString())
            ->whereDate('document_date', '<=', $end->toDateString())
            ->when($request->filled('end_user'), fn ($q) => $q->where('teacher_id', $request->input('end_user')))
            ->when($request->filled('stock_no'), function ($q) use ($request) {
                $term = '%'.$request->input('stock_no').'%';

                $q->whereHas('items', fn ($i) => $i
                    ->where('property_number', 'like', $term)
                    ->orWhereHas('supply', fn ($s) => $s->where('item_code', 'like', $term))
                    ->orWhereHas('equipment', fn ($e) => $e
                        ->where('item_code', 'like', $term)
                        ->orWhere('property_number', 'like', $term)));
            })
            ->when($request->filled('category'), function ($q) use ($request) {
                $q->whereHas('items', fn ($i) => $i
                    ->whereHas('supply', fn ($s) => $s->where('category', $request->input('category')))
                    ->orWhereHas('equipment', fn ($e) => $e->where('category', $request->input('category'))));
            })
            ->with(['teacher', 'room', 'issuedBy', 'preparedBy', 'items.supply', 'items.equipment'])
            ->orderBy('document_date')
            ->orderBy('document_no');
    }

    /**
     * Every issued line of those documents, one row per line, in the order the
     * papers were written.
     *
     * @param  Collection<int, Document>  $documents
     * @return array<int, array>
     */
    private function risRows(Collection $documents): array
    {
        $rows = [];

        foreach ($documents as $document) {
            foreach ($document->items as $item) {
                $record = $item->supply ?: $item->equipment;
                $name = (string) ($record?->name ?: $item->description);
                $description = $record?->description;

                // The item's own record holds the long description; when it is
                // only the name repeated there is nothing more to print.
                if ($description === $name || $description === null) {
                    $description = null;
                }

                // What left the shelf: the issue quantity when the form
                // recorded one, otherwise the requested quantity.
                $quantity = (int) ($item->issued_quantity ?: $item->quantity);
                $unitCost = (float) $item->unit_cost;

                $rows[] = [
                    'ris_no' => $document->document_no,
                    'end_user' => $document->custodian_name,
                    'item' => $name,
                    'description' => $description,
                    'unit' => (string) $item->unit,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    // Never typed in: amount is always quantity × unit cost.
                    'amount' => round($quantity * $unitCost, 2),
                    'stock_no' => (string) ($record?->item_code ?: $item->property_number),
                ];
            }
        }

        return $rows;
    }

    /**
     * The month-and-year filters, as the forms and the printed period show
     * them. `show_costs` is off by default: the money columns belong to the
     * accounting unit, and the form prints them only when it is asked to.
     * `report_no` is the number the sheet is written under; the caller passes
     * the one the records give it, so the box can show it until it is edited.
     * `show_stock` is off by default too: the stock number and quantity stand
     * on the form only when the officer asks for them, and the box beside its
     * hidden companion says so. `show_total` follows the same rule — the whole
     * of the recapitulation's menu arrives unticked, so the block prints as the
     * accounting unit's blank lines until one of its figures is asked for.
     */
    private function risForm(Request $request, Carbon $start, ?string $number = null): array
    {
        return [
            'month' => (int) $start->month,
            'year' => (int) $start->year,
            'end_user' => $request->input('end_user'),
            'stock_no' => (string) $request->input('stock_no', ''),
            'category' => $request->input('category'),
            'show_costs' => $request->boolean('show_costs'),
            'show_stock' => $this->wantsStockColumns($request),
            'show_total' => $this->wantsTotalRow($request),
            // The number the report is written under, handed in by the caller:
            // the sheet prints it, so the form cannot disagree with the paper.
            'report_no' => (string) ($number ?? ''),
        ];
    }

    /**
     * The number the sheet is written under: what the office typed into its
     * line, or — when that line is left empty — the number read off the
     * records, once the report is being written under one at all. On the
     * working page just opened there is no such number yet: the sheet stands
     * blank until the report is generated, and the number comes off the records
     * then. Blanking the line afterwards is how the report is put back on its
     * own numbering, so an emptied field is not an error and never prints an
     * empty line where a number belongs.
     */
    private function reportNumber(string $typed, string $generated, bool $numbered): string
    {
        if ($typed !== '') {
            return $typed;
        }

        return $numbered ? $generated : '';
    }

    /**
     * The month a report is written for, as the key the numbers written on it
     * are filed under: "2026-01".
     */
    private function periodKey(Carbon $start): string
    {
        return $start->format('Y-m');
    }

    /**
     * The numbers written over by hand, each kept to the month it was written
     * in.
     *
     * A number the office types belongs to the month it was typed on, so the
     * field it is typed into names that month — `report_no[2026-01]`. Only the
     * numbers filed under the month being reported are handed back to be
     * written on the sheet; every other month's are handed back separately, as
     * the fields the form carries on untouched, so January's typing is still
     * there in January after February has been read. A number that names no
     * month of its own — a bare `report_no` asked for by name in a link — was
     * written for the month in hand.
     *
     * @return array{written: array<string, string>, carried: array<string, string>}
     */
    private function writtenNumbers(Request $request, string $field, string $periodKey): array
    {
        $values = $request->input($field, []);
        $filed = [];

        if (! is_array($values)) {
            $filed[$periodKey][''] = trim((string) $values);
            $values = [];
        }

        foreach ($values as $key => $value) {
            $key = (string) $key;
            $number = trim((string) $value);

            // report_no[2026-01]: one number, filed under its month, which is
            // what its own name says. Anything else was written for the month
            // being reported.
            if (preg_match('/^\d{4}-\d{2}$/', $key) === 1) {
                $filed[$key][''] = $number;
            } else {
                $filed[$periodKey][$key] = $number;
            }
        }

        $written = array_filter($filed[$periodKey] ?? [], fn (string $number) => $number !== '');
        $carried = [];

        foreach ($filed as $period => $places) {
            if ($period === $periodKey) {
                continue;
            }

            foreach ($places as $place => $number) {
                if ($number === '') {
                    continue;
                }

                $carried[$field.'['.$period.']'] = $number;
            }
        }

        return ['written' => $written, 'carried' => $carried];
    }

    /**
     * Whether the report is written under a number yet. A paper always is — it
     * cannot be filed without one — while the working page carries one only
     * once it has been generated: every button in the generator's form sends
     * the marker that says so, so generating the report fills its number in,
     * and Preview, Print and Export PDF carry the same number on with them.
     */
    private function reportIsNumbered(Request $request, bool $paper): bool
    {
        return $paper || $request->boolean('generated');
    }

    /**
     * Whether the report prints the stock number and quantity it holds. Off by
     * default, like the money columns: the two are the report's own record and
     * are printed when the officer asks for them on the working page, and the
     * filter sends that "yes" explicitly rather than resting on a box that
     * arrives ticked.
     */
    private function wantsStockColumns(Request $request): bool
    {
        return $request->boolean('show_stock');
    }

    /**
     * Whether the recapitulation closes with its TOTAL line. Off by default,
     * like the money columns and the stock columns: the block belongs to the
     * accounting unit, and the office asks for the report's own figures rather
     * than having to clear boxes that were ticked for it.
     */
    private function wantsTotalRow(Request $request): bool
    {
        return $request->boolean('show_total');
    }

    /**
     * The position the supply office signs under.
     *
     * The office holds it on its own account and writes it once, so every sheet
     * that office signs carries it — the "Received from" line on the PAR and the
     * ICS, and the title under the certification on the RIS and the monthly
     * report. An account that holds none signs under the form's own wording: the
     * paper is never left without a position because nobody filled a profile in.
     */
    private function supplyOfficePosition(string $formWording): string
    {
        $position = trim((string) (auth()->user()?->position ?? ''));

        return $position === '' ? $formWording : $position;
    }

    /**
     * The period the printed form asks for, spelled out the way it does:
     * "AUGUST 01-31, 2026".
     */
    private function periodLabel(Carbon $start, Carbon $end): string
    {
        return strtoupper($start->format('F')).' '.$start->format('d').'-'.$end->format('d').', '.$end->year;
    }

    /**
     * The month the RIS report covers: the first and last day of the chosen
     * month, clamped to something a calendar can hold so a hand-edited URL
     * cannot blow the report up.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function risPeriod(Request $request): array
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()->startOfDay()];
    }

    /**
     * The report's own number, e.g. 2026-08-086: the year and month of the
     * period followed by the sequence of the first RIS it covers, taken from
     * the documents on file. Reading it off the records keeps the number
     * repeatable — the same month prints the same number every time — instead
     * of inventing a serial the office cannot tie back to anything.
     *
     * The parts are joined with hyphens and the sequence is carried to three
     * digits, the way the printed form writes them: 2026-08-086, never
     * 2026=08=86 and never 2026-8-86.
     */
    private function risReportNumber(?string $firstDocumentNo, Carbon $period): string
    {
        $sequence = preg_match('/(\d+)$/', (string) $firstDocumentNo, $matches) ? (int) $matches[1] : 1;

        // The month's first paper reads 2026-08-001 rather than 2026-08-1, and
        // a sequence already past two digits keeps all three it has.
        return sprintf('%s-%s-%03d', $period->format('Y'), $period->format('m'), $sequence);
    }

    /**
     * The recapitulation that closes the RIS: one line per stock number and
     * unit cost, so a cost that changed mid-month shows as its own line rather
     * than being averaged away. Qty and both money columns are the same
     * quantity × unit cost the body rows carry.
     *
     * @param  array<int, array>  $rows
     * @return array<int, array>
     */
    private function risRecapitulation(array $rows): array
    {
        return collect($rows)
            // A line with no cost on it (a hand-typed one) is left out entirely
            // rather than totalled as zero: the accounting unit fills in its
            // own figures, and a zero the office never wrote would be a claim.
            ->filter(fn (array $row) => $row['unit_cost'] !== null)
            ->groupBy(fn (array $row) => $row['stock_no'].'|'.number_format($row['unit_cost'], 2, '.', ''))
            ->map(function (Collection $group) {
                $first = $group->first();
                $amount = round((float) $group->sum('amount'), 2);

                return [
                    'stock_no' => $first['stock_no'],
                    // Not cast to an integer: a line can be measured in halves
                    // and kilos as the form shows them.
                    'quantity' => $group->sum('quantity'),
                    'unit_cost' => $first['unit_cost'],
                    'total_cost' => $amount,
                    // The accounting column: the same arithmetic, since the
                    // office never types an amount by hand.
                    'amount' => $amount,
                ];
            })
            ->sortBy('stock_no')
            ->values()
            ->all();
    }

    /**
     * The supply officer whose name the RIS prints ("Administrative Officer
     * IV" in the sample form): whoever issued the most recent RIS in the
     * period, or prepared it when it was never issued.
     */
    private function risIssuer(Collection $documents): ?string
    {
        $issuer = $documents
            ->sortByDesc(fn (Document $document) => $document->issued_at?->timestamp ?? $document->approved_at?->timestamp ?? 0)
            ->first(fn (Document $document) => (bool) ($document->issuedBy?->name ?? $document->preparedBy?->name));

        return $issuer?->issuedBy?->name ?? $issuer?->preparedBy?->name;
    }

    /*
     * ---------------------------------------------------------------------
     * MONTHLY REPORT — one running report per paper
     * ---------------------------------------------------------------------
     */

    /**
     * The Monthly Report is the same official RIS grid, printed as one running
     * report per paper for the month: the lines of every end-user follow one
     * another down the form, and the sheet fills the page and carries on over
     * onto another page when it runs out of room — it is not cut into a page
     * per end-user, which would leave a page mostly blank for every person who
     * took a little. The lines come from the month's issued papers — the RIS,
     * the ICS, or both when All is asked for — and nothing is ever stored: the
     * report is a view, and the printed paper is the record.
     */
    private function monthlyPayload(Request $request, bool $paper = false, bool $export = false): array
    {
        [$start, $end] = $this->risPeriod($request);
        $periodKey = $this->periodKey($start);
        $period = $this->periodLabel($start, $end);

        // Which papers the report is cut from: the RIS the supply office
        // issued, the ICS it handed to custodians, or both. They print on the
        // same official grid, so only the source of the lines changes.
        $source = $this->monthlySourceType($request);
        $types = $source === 'ALL' ? ['RIS', 'ICS'] : [$source];

        // Every paper in the run is read before any of it is printed.
        $documents = [];
        foreach ($types as $type) {
            $documents[$type] = $this->risDocuments($request, $start, $end, $type);
        }

        // Every paper asked for is printed, whether or not the month holds it:
        // All is both reports, each behind a page naming it, and a paper the
        // month has nothing for prints the blank sheet it is on the official
        // form rather than dropping out of the run. A month with nothing at all
        // is no exception — the pair is still the pair.
        $printed = $types;

        $sheets = [];

        foreach ($printed as $type) {
            $found = $documents[$type];
            $group = $this->monthlyReport($found, $period, $this->risIssuer($found), $type);

            // One run over both papers: each report opens with a page naming
            // it, so the reader always knows which one follows.
            if (count($printed) > 1) {
                $group[0]['cover'] = [
                    'agency' => Card::DEFAULT_ENTITY,
                    'title' => 'Report of Supplies and Materials Issued',
                    'name' => $type,
                    'period' => $period,
                ];
            }

            $sheets = array_merge($sheets, $group);
        }

        // Each report is cut into the pages it prints on, and every one of those
        // pages is a sheet of the office's paper with a number of its own: the
        // report's number is the page's, counted as the run is printed, so a
        // report that fills its paper and carries on over another page is
        // numbered 01, 02 and so on rather than sharing one number between two
        // sheets. The run restarts with each month, so the number a sheet is
        // filed under names the month it belongs to and its place in that
        // month's printing. The page that names a paper is a divider, not a
        // sheet of the run: it carries no number and does not take one up.
        $pager = \App\Support\SheetPages::for($export);
        $showStock = $this->wantsStockColumns($request);
        $showCosts = $request->boolean('show_costs');
        $showTotal = $this->wantsTotalRow($request);
        $number = 0;

        foreach ($sheets as $index => $sheet) {
            $pages = $pager->cutRis(
                $sheet['rows'],
                $pager->tailMm(\App\Support\SheetPages::recapLines($sheet['recap'], $showStock, $showCosts, $showTotal)),
            );

            $sheets[$index]['pages'] = [];

            foreach ($pages as $rows) {
                $sheets[$index]['pages'][] = [
                    'rows' => $rows,
                    'number' => $this->monthlyPageNumber($periodKey, ++$number),
                ];
            }

            // The report's own number is the first sheet's, for anything that
            // reads the report rather than prints it.
            $sheets[$index]['meta']['number'] = $sheets[$index]['pages'][0]['number'];
        }

        $rowCount = (int) array_sum(array_map(fn (array $sheet) => count($sheet['rows']), $sheets));
        $endUsers = count(array_unique(array_filter(collect($sheets)->flatMap(fn (array $sheet) => array_column($sheet['rows'], 'end_user'))->all())));
        $total = round((float) array_sum(array_column($sheets, 'total')), 2);

        return [
            'documentTitle' => 'Report of Supplies and Materials Issued',
            // The same paper as the RIS, so the heading says which copy this is.
            'subtitle' => 'Monthly Report · one running report per paper',
            'filename' => sprintf('Monthly-%s-%s-%s.pdf', $source, $start->format('Y'), $start->format('m')),
            'form' => $this->risForm($request, $start) + [
                'document_type' => strtolower($source),
            ],
            // The month the numbers on the sheets belong to, and the numbers
            // written on other months' sheets, which the form carries on.
            'periodKey' => $periodKey,
            'meta' => ['agency' => Card::DEFAULT_ENTITY, 'period' => $period],
            'showCosts' => $showCosts,
            'showStock' => $showStock,
            'showTotal' => $showTotal,
            'sheets' => $sheets,
            'documentCount' => array_sum(array_map('count', $documents)),
            'totalAmount' => $total,
            'empty' => $rowCount === 0,
            // The run is described by the papers it prints, so a report
            // carried along blank still says what the month held for it.
            'summary' => collect($printed)
                    ->map(fn (string $type) => $documents[$type]->count().' '.$type.' document(s)')
                    ->implode(' &middot; ')
                .' &middot; '.$endUsers.' end-user(s) &middot; '
                .$rowCount.' item line(s) &middot; Total '.number_format($total, 2),
        ];
    }

    /**
     * The number a report of the monthly report is written under: the year and
     * the month it belongs to, then its place in the year's run of reports.
     */
    private function monthlyPageNumber(string $periodKey, int $number): string
    {
        return sprintf('%s-%02d', $periodKey, $number);
    }

    /**
     * Which document type the automatic monthly report is built from: ALL by
     * default — every paper the month holds, the RIS and the ICS together — the
     * ICS when it is asked for on its own, or the RIS alone. Anything else
     * falls back to the default rather than printing an empty report.
     */
    private function monthlySourceType(Request $request): string
    {
        $asked = strtoupper((string) $request->input('document_type'));

        return in_array($asked, ['RIS', 'ICS', 'ALL'], true) ? $asked : 'ALL';
    }

    /**
     * One running report for the month's paper: every line of it, in the order
     * the papers were written, with the end-user written once over each run of
     * their own lines and the recapitulation and certification closing the
     * whole of it rather than each person's part. The report is not split by
     * end-user, so a month of small issues fills its page instead of printing a
     * page for each person who took a little, and it runs on to another page
     * only when it has filled the one it is on.
     *
     * A month with nothing in it still prints one report, with the message where
     * its lines would be — an empty report, not a missing page.
     *
     * @param  Collection<int, Document>  $documents
     * @return array<int, array>
     */
    private function monthlyReport(Collection $documents, string $periodLabel, ?string $postedBy, string $type): array
    {
        $rows = $this->risRows($documents);

        return [[
            'end_user' => '',
            // A report cut from the ICS heads its first column "ICS NO.", the
            // way that paper does; everything else about the grid is the same.
            'kind' => $type,
            'meta' => [
                'agency' => Card::DEFAULT_ENTITY,
                'period' => $periodLabel,
                // Blank until the run numbers it: the report gives its own
                // number, and nothing written here could know its place.
                'number' => '',
                'posted_by' => $postedBy,
                'posted_position' => $this->supplyOfficePosition(self::SUPPLY_OFFICER_TITLE),
            ],
            'rows' => $rows,
            'blank_rows' => 0,
            'recap' => $this->risRecapitulation($rows),
            'total' => round((float) array_sum(array_column($rows, 'amount')), 2),
        ]];
    }

    /**
     * The months and years the RIS report can be run for. The years come from
     * the RIS already on file (plus the current one), so the picker never
     * offers a year that has no records to show.
     */
    private function risYears(): Collection
    {
        return Document::query()
            // The monthly report can be run for either type, so a year with
            // only ICS papers on file is still a year worth offering.
            ->whereIn('type', ['RIS', 'ICS'])
            ->whereNotNull('document_date')
            ->pluck('document_date')
            ->map(fn ($date) => (int) Carbon::parse($date)->year)
            ->push((int) now()->year)
            ->unique()
            ->sortDesc()
            ->values();
    }

    /** @return array<int, string> month number => month name */
    private function monthOptions(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month) => [$month => Carbon::create(2000, $month, 1)->format('F')])
            ->all();
    }

    /*
     * ---------------------------------------------------------------------
     * Shared pieces of the three documents
     * ---------------------------------------------------------------------
     */

    /**
     * The equipment the report was asked for, in name order. An empty
     * selection is not an error: it is an empty report, and the paper says so
     * rather than showing invented rows.
     *
     * @return Collection<int, Equipment>
     */
    private function selectedEquipment(Request $request): Collection
    {
        $ids = collect($request->input('equipment', []))
            ->flatMap(fn ($value) => is_array($value) ? $value : [$value])
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        return $ids === []
            ? collect()
            : Equipment::with(['room', 'teacher'])->whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * The equipment the PAR and ICS forms offer. Each row is marked when the
     * item is property already handed over through an ICS document, so the ICS
     * list opens on exactly those items and the administrator only adjusts it.
     */
    private function equipmentPicker(): Collection
    {
        $viaIcs = DocumentItem::query()
            ->whereNotNull('equipment_id')
            ->whereHas('document', fn ($q) => $q->where('type', 'ICS'))
            ->pluck('equipment_id')
            ->flip();

        return Equipment::with(['room', 'teacher'])->orderBy('name')->get()
            ->map(fn (Equipment $item) => [
                'id' => $item->id,
                'item_code' => (string) $item->item_code,
                'property_number' => (string) $item->property_number,
                'name' => (string) $item->name,
                'custodian' => $item->custodian_name,
                'room' => (string) ($item->room?->code ?: $item->room?->name),
                'via_ics' => $viaIcs->has($item->id),
            ]);
    }

    /**
     * One PAR/ICS line, read straight off the equipment record. The amount is
     * the quantity on the record at its unit cost — computed, never typed.
     */
    private function equipmentRow(Equipment $item): array
    {
        // A property record with nothing on hand still names one physical
        // item, so the quantity printed is never zero.
        $quantity = max(1, (int) $item->quantity_on_hand);
        $unitCost = (float) $item->unit_cost;

        return [
            'property_no' => (string) ($item->property_number ?: $item->item_code),
            'item_code' => (string) $item->item_code,
            'name' => (string) $item->name,
            'description' => $item->description,
            'quantity' => $quantity,
            'unit' => (string) $item->unit,
            'unit_cost' => $unitCost,
            'amount' => round($quantity * $unitCost, 2),
            'date_acquired' => $item->date_acquired,
            'accountable' => $item->custodian_name,
            'room' => (string) ($item->room?->code ?: $item->room?->name),
        ];
    }

    /**
     * The custodian the selected equipment share, when they share one — used
     * to pre-fill the accountable person instead of leaving the form blank.
     * A selection spanning several people guesses nothing.
     *
     * @param  Collection<int, Equipment>  $equipment
     * @return array{name: string, position: ?string, room: ?string}
     */
    private function sharedCustodian(Collection $equipment): array
    {
        $names = $equipment->map(fn (Equipment $item) => $item->custodian_name)->filter()->unique()->values();
        $rooms = $equipment->map(fn (Equipment $item) => $item->room?->code ?: $item->room?->name)->filter()->unique()->values();
        $room = $rooms->count() === 1 ? $rooms->first() : null;

        if ($names->count() !== 1) {
            return ['name' => '', 'position' => null, 'room' => $room];
        }

        $match = $equipment->first(fn (Equipment $item) => $item->custodian_name === $names->first());

        return ['name' => $names->first(), 'position' => $match?->teacher?->position, 'room' => $room];
    }

    /** The date the form was filled in, falling back to today on bad input. */
    private function documentDate(Request $request): Carbon
    {
        $value = $request->input('document_date');

        try {
            return $value ? Carbon::parse($value) : now();
        } catch (\Exception) {
            return now();
        }
    }

    /**
     * The base number advanced by $offset — the next free numbers on file, so
     * a second ICS sheet is numbered ICS-2026-00008 rather than repeating
     * ICS-2026-00007. A number with no trailing digits is left as typed.
     */
    private function nthDocumentNumber(string $base, int $offset): string
    {
        if ($offset === 0) {
            return $base;
        }

        return (string) preg_replace_callback(
            '/(\d+)$/',
            fn (array $matches) => str_pad((string) ((int) $matches[1] + $offset), strlen($matches[1]), '0', STR_PAD_LEFT),
            $base,
        );
    }

    private function respond(Request $request, string $title, array $headings, array $rows): Response|View|BinaryFileResponse
    {
        $format = $request->get('format', 'html');

        Report::create([
            'type' => \Illuminate\Support\Str::slug($title, '_'),
            'format' => $format,
            'generated_by' => auth()->id(),
            'filters' => $request->except(['format']),
        ]);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('admin.reports.pdf', compact('title', 'headings', 'rows'))->setPaper('a4', 'landscape');

            return $pdf->stream(\Illuminate\Support\Str::slug($title).'.pdf');
        }

        if ($format === 'xlsx') {
            return Excel::download(new ArrayExport($headings, $rows, $title), \Illuminate\Support\Str::slug($title).'.xlsx');
        }

        return view('admin.reports.table', compact('title', 'headings', 'rows'));
    }
}
