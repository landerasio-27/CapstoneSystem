<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Inventory;
use App\Models\Supply;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only analytics for the admin dashboard.
 *
 * Design notes:
 *  - Everything is aggregated in SQL (GROUP BY year/month) so the number of
 *    queries stays fixed no matter how much history exists. Items are never
 *    queried one by one: the per-item cost series for the whole dashboard comes
 *    from one grouped query per type.
 *  - Nothing is invented. A month with no records is reported as having none,
 *    and a trend that cannot be proven from two consecutive months of real
 *    records is reported as "no previous cost data".
 *  - Supplies take their cost history from stock-in transactions (inventory
 *    rows). Equipment has no stock transactions here, so its cost history comes
 *    from the acquisition/accountability documents (PAR/ICS) that record a price.
 */
class InventoryAnalyticsService
{
    public const TYPE_SUPPLIES = 'supplies';

    public const TYPE_EQUIPMENT = 'equipment';

    public const STATUS_HEALTHY = 'healthy';

    public const STATUS_LOW = 'low';

    public const STATUS_CRITICAL = 'critical';

    /** Time periods offered by the dashboard filter, as period key => label. */
    public const PERIODS = [
        '3' => 'Last 3 Months',
        '6' => 'Last 6 Months',
        '12' => 'Last 12 Months',
        'year' => 'This Year',
        'custom' => 'Custom Range',
    ];

    /**
     * Document types that record what an equipment item cost. Inventory Custody
     * Slips and Property Acknowledgement Receipts are the property records; the
     * same acquisition is often written on more than one form, so identical
     * price records for the same item and date are counted once (see
     * documentItemsQuery()).
     */
    private const EQUIPMENT_COST_DOCUMENTS = ['PAR', 'ICS'];

    /** How far back cost trends look for consecutive months of records. */
    private const TREND_LOOKBACK_MONTHS = 24;

    private ?Collection $supplies = null;

    private ?Collection $equipment = null;

    private ?array $summaryCache = null;

    private ?array $classificationCache = null;

    /** Per type: item id => (month => aggregate row) for the trend lookback. */
    private array $costSeriesCache = [];

    // ---------------------------------------------------------------------
    // Filters
    // ---------------------------------------------------------------------

    /**
     * Normalises the dashboard's query string into a filter set. Invalid input
     * falls back to the defaults instead of erroring, so a hand-edited URL can
     * never break the page.
     */
    public function filters(array $input): array
    {
        $type = ($input['type'] ?? null) === self::TYPE_EQUIPMENT ? self::TYPE_EQUIPMENT : self::TYPE_SUPPLIES;

        $period = array_key_exists((string) ($input['period'] ?? ''), self::PERIODS)
            ? (string) $input['period']
            : '6';

        $year = (int) ($input['year'] ?? now()->year);
        if ($year < 2000 || $year > (int) now()->year + 1) {
            $year = (int) now()->year;
        }

        $customFrom = $this->parseMonth($input['from'] ?? null);
        $customTo = $this->parseMonth($input['to'] ?? null);

        if ($period === 'year') {
            $from = Carbon::create($year, 1, 1)->startOfMonth();
            $to = Carbon::create($year, 12, 1)->endOfMonth();
        } elseif ($period === 'custom') {
            // An unset (or half-set) custom range defaults to the last six months
            // but the period stays "custom": the From/To month inputs only exist
            // for this period, so falling back to a preset would hide the very
            // controls needed to choose a range.
            $from = ($customFrom ?? now()->copy()->subMonths(5)->startOfMonth())->copy()->startOfMonth();
            $to = ($customTo ?? now())->copy()->endOfMonth();

            // A backwards range is read as a range typed the other way round.
            if ($from->greaterThan($to)) {
                [$from, $to] = [$to->copy()->startOfMonth(), $from->copy()->endOfMonth()];
            }
        } else {
            $months = max(1, (int) $period);
            $to = now()->copy()->endOfMonth();
            $from = now()->copy()->subMonths($months - 1)->startOfMonth();
        }

        $item = $input['item'] ?? null;
        $item = is_numeric($item) ? (int) $item : null;

        return [
            'type' => $type,
            'type_label' => $type === self::TYPE_EQUIPMENT ? 'Equipment' : 'Supplies',
            'item' => $item,
            'period' => $period,
            'period_label' => self::PERIODS[$period],
            'year' => $year,
            'from' => $from,
            'to' => $to,
            'from_input' => $from->format('Y-m'),
            'to_input' => $to->format('Y-m'),
            'years' => $this->availableYears(),
            'range_label' => $from->format('M Y') . ' – ' . $to->format('M Y'),
        ];
    }

    private function parseMonth(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Years that actually contain inventory history, newest first. */
    public function availableYears(): array
    {
        $earliest = collect([
            Inventory::min('created_at'),
            Document::min('document_date'),
            now()->toDateString(),
        ])
            ->filter()
            ->map(fn ($value) => (int) Carbon::parse($value)->year)
            ->min();

        return range((int) now()->year, min($earliest, (int) now()->year));
    }

    // ---------------------------------------------------------------------
    // Stock level + status
    // ---------------------------------------------------------------------

    /**
     * Stock status rule, in one place.
     *
     * The comparison against the reorder point follows the system's existing
     * rule (Supply::isLowStock() / Equipment::isLowStock(): quantity <= reorder
     * level). Two extra, documented tiers sit on top of it:
     *   - Critical: nothing left, or at or below half the reorder point.
     *   - Healthy:  above the reorder point.
     * Items with no reorder point configured can only be Critical when they are
     * empty: nothing is assumed about a threshold that was never set.
     */
    public function statusFor(int|float|null $quantity, int|float|null $reorderLevel): string
    {
        $quantity = (float) $quantity;
        $reorderLevel = (float) ($reorderLevel ?? 0);

        if ($quantity <= 0) {
            return self::STATUS_CRITICAL;
        }

        if ($reorderLevel <= 0) {
            return self::STATUS_HEALTHY;
        }

        if ($quantity <= floor($reorderLevel / 2)) {
            return self::STATUS_CRITICAL;
        }

        return $quantity <= $reorderLevel ? self::STATUS_LOW : self::STATUS_HEALTHY;
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_LOW => 'Low',
            self::STATUS_CRITICAL => 'Critical',
            default => 'Healthy',
        };
    }

    /** Tone key used by the views for badges, bars and dots. */
    public function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_LOW => 'amber',
            self::STATUS_CRITICAL => 'red',
            default => 'brand',
        };
    }

    /** All supplies with their computed status, largest stock first. */
    public function suppliesWithStatus(): Collection
    {
        return $this->supplies()->map(function (Supply $supply) {
            $status = $this->statusFor($supply->quantity_on_hand, $supply->reorder_level);

            return [
                'id' => $supply->id,
                'item_code' => $supply->item_code,
                'name' => $supply->name,
                'quantity' => (int) $supply->quantity_on_hand,
                'unit' => $supply->unit,
                'reorder_level' => (int) $supply->reorder_level,
                'unit_cost' => (float) $supply->unit_cost,
                'value' => round((float) $supply->quantity_on_hand * (float) $supply->unit_cost, 2),
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'status_tone' => $this->statusTone($status),
            ];
        })->sortByDesc('quantity')->values();
    }

    /** Top N supplies by quantity, for the stock level chart. */
    public function stockLevels(int $limit = 8): array
    {
        $all = $this->suppliesWithStatus();

        return [
            'items' => $all->take($limit)->all(),
            'total' => $all->count(),
            'hidden' => max($all->count() - $limit, 0),
        ];
    }

    /** How many supplies sit in each status bucket. */
    public function statusCounts(): array
    {
        $counts = [self::STATUS_HEALTHY => 0, self::STATUS_LOW => 0, self::STATUS_CRITICAL => 0];

        foreach ($this->suppliesWithStatus() as $supply) {
            $counts[$supply['status']]++;
        }

        $total = array_sum($counts);
        $percent = fn (int $value) => $total > 0 ? round($value / $total * 100, 1) : 0.0;

        return [
            'healthy' => $counts[self::STATUS_HEALTHY],
            'low' => $counts[self::STATUS_LOW],
            'critical' => $counts[self::STATUS_CRITICAL],
            'total' => $total,
            'healthy_pct' => $percent($counts[self::STATUS_HEALTHY]),
            'low_pct' => $percent($counts[self::STATUS_LOW]),
            'critical_pct' => $percent($counts[self::STATUS_CRITICAL]),
        ];
    }

    /** Supplies at or below their reorder point, lowest first. */
    public function lowStockSupplies(): Collection
    {
        return $this->suppliesWithStatus()
            ->whereIn('status', [self::STATUS_LOW, self::STATUS_CRITICAL])
            ->sortBy('quantity')
            ->values();
    }

    // ---------------------------------------------------------------------
    // Summary + cost overview
    // ---------------------------------------------------------------------

    /**
     * The compact top-of-dashboard numbers. Monetary values are quantity ×
     * recorded unit cost, never a quantity mistaken for money.
     */
    public function summary(): array
    {
        if ($this->summaryCache !== null) {
            return $this->summaryCache;
        }

        $supplyValue = round((float) Supply::query()->sum(DB::raw('quantity_on_hand * unit_cost')), 2);
        $equipmentValue = round((float) Equipment::query()->sum(DB::raw('quantity_on_hand * unit_cost')), 2);
        $statuses = $this->statusCounts();

        return $this->summaryCache = [
            'supplies' => $this->supplies()->count(),
            'equipment' => $this->equipment()->count(),
            'low_stock' => $statuses['low'] + $statuses['critical'],
            'critical_stock' => $statuses['critical'],
            'supply_value' => $supplyValue,
            'equipment_value' => $equipmentValue,
            'inventory_value' => round($supplyValue + $equipmentValue, 2),
        ];
    }

    /**
     * The property classification figures, in the one place they are counted.
     *
     * Two readings of the same rule (App\Services\ItemClassifier): the register
     * side counts the papers the office actually raised — PAR, ICS and RIS —
     * while the inventory side counts the items themselves and what the property
     * is worth. A consumable is always a RIS however much it costs, so a basket
     * of ballpens cannot turn into property; the unit cost only separates the two
     * property papers, above the Property Classification Threshold for a PAR and
     * at or below it for an ICS.
     *
     * @return array{
     *     papers: array{PAR:int,ICS:int,RIS:int},
     *     items: array{PAR:int,ICS:int,RIS:int},
     *     papers_total: int,
     *     consumable: int,
     *     non_consumable: int,
     *     property_value: float
     * }
     */
    public function classificationSummary(): array
    {
        if ($this->classificationCache !== null) {
            return $this->classificationCache;
        }

        // Drafts are real papers the office is working on, so they are counted
        // here exactly as the register lists them; archived papers are not.
        $papers = Document::query()
            ->notArchived()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $items = ['PAR' => 0, 'ICS' => 0, 'RIS' => 0];
        $propertyValue = 0.0;

        foreach ($this->supplies()->concat($this->equipment()) as $item) {
            $items[ItemClassifier::forItem($item)]++;

            // Property is what the office still holds: non-consumable items at
            // their on-hand quantity and recorded unit cost.
            if (! ItemClassifier::isConsumable($item)) {
                $propertyValue += (int) $item->quantity_on_hand * (float) $item->unit_cost;
            }
        }

        return $this->classificationCache = [
            'papers' => [
                'PAR' => (int) ($papers['PAR'] ?? 0),
                'ICS' => (int) ($papers['ICS'] ?? 0),
                'RIS' => (int) ($papers['RIS'] ?? 0),
            ],
            'items' => $items,
            'papers_total' => (int) ($papers['PAR'] ?? 0) + (int) ($papers['ICS'] ?? 0) + (int) ($papers['RIS'] ?? 0),
            'consumable' => $items['RIS'],
            'non_consumable' => $items['PAR'] + $items['ICS'],
            'property_value' => round($propertyValue, 2),
        ];
    }

    /**
     * Cost overview: what the inventory is worth and what was purchased this
     * month versus last month. The percentage is null (rendered as "no previous
     * month data") whenever the previous month has no purchases, so there is
     * never a division by zero or a made-up percentage.
     */
    public function costOverview(): array
    {
        $summary = $this->summary();

        $thisMonthStart = now()->copy()->startOfMonth();
        $thisMonthEnd = now()->copy()->endOfMonth();
        $lastMonthStart = now()->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->copy()->subMonthNoOverflow()->endOfMonth();

        $thisMonth = $this->purchasesBetween($thisMonthStart, $thisMonthEnd);
        $lastMonth = $this->purchasesBetween($lastMonthStart, $lastMonthEnd);

        $change = round($thisMonth - $lastMonth, 2);

        return [
            'total_value' => $summary['inventory_value'],
            'supplies_value' => $summary['supply_value'],
            'equipment_value' => $summary['equipment_value'],
            'this_month' => round($thisMonth, 2),
            'previous_month' => round($lastMonth, 2),
            'change' => $change,
            'change_pct' => $lastMonth > 0 ? round($change / $lastMonth * 100, 2) : null,
            'this_month_label' => now()->format('F Y'),
            'previous_month_label' => now()->copy()->subMonthNoOverflow()->format('F Y'),
        ];
    }

    /** Total purchase cost (supplies stock-in + equipment PAR/ICS) in a window. */
    private function purchasesBetween(Carbon $from, Carbon $to): float
    {
        $supplies = Inventory::query()
            ->where('type', 'in')
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_cost');

        $equipment = $this->documentItemsQuery()
            ->whereBetween('d.document_date', [$from->toDateString(), $to->toDateString()])
            ->sum(DB::raw('di.quantity * di.unit_cost'));

        return (float) $supplies + (float) $equipment;
    }

    // ---------------------------------------------------------------------
    // Monthly history
    // ---------------------------------------------------------------------

    /**
     * Monthly purchase aggregates for one item, or for every item of the type,
     * inside a month range. One query; the callers below shape it in PHP.
     *
     * @return Collection<int, object{item_id:int, ym:string, quantity:float, total_cost:float, average_unit_cost:float, records:int}>
     */
    private function monthlyAggregates(string $type, ?int $itemId, Carbon $from, Carbon $to): Collection
    {
        if ($type === self::TYPE_EQUIPMENT) {
            $month = $this->monthExpression('d.document_date');

            return $this->documentItemsQuery()
                ->when($itemId, fn ($query) => $query->where('di.equipment_id', $itemId))
                ->whereBetween('d.document_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("di.equipment_id as item_id, {$month} as ym, COUNT(*) as records, SUM(di.quantity) as quantity,
                             SUM(di.quantity * di.unit_cost) as total_cost, AVG(di.unit_cost) as average_unit_cost")
                ->groupByRaw("di.equipment_id, {$month}")
                ->orderBy('ym')
                ->get()
                ->map(fn ($row) => $this->castAggregate($row));
        }

        $month = $this->monthExpression('created_at');

        return Inventory::query()
            ->where('type', 'in')
            ->when($itemId, fn ($query) => $query->where('supply_id', $itemId))
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("supply_id as item_id, {$month} as ym, COUNT(*) as records, SUM(quantity) as quantity,
                         SUM(total_cost) as total_cost, AVG(unit_cost) as average_unit_cost")
            ->groupByRaw("supply_id, {$month}")
            ->orderBy('ym')
            ->get()
            ->map(fn ($row) => $this->castAggregate($row));
    }

    /**
     * Plain object, not the model: the Inventory model casts unit_cost and
     * total_cost to strings, which would defeat the numeric types the analytics
     * maths relies on.
     */
    private function castAggregate(object $row): object
    {
        return (object) [
            'item_id' => (int) $row->item_id,
            'ym' => (string) $row->ym,
            'quantity' => (float) $row->quantity,
            'total_cost' => round((float) $row->total_cost, 2),
            'average_unit_cost' => round((float) $row->average_unit_cost, 2),
            'records' => (int) $row->records,
        ];
    }

    /**
     * A month-by-month series for one item (or the whole type) across the
     * selected period. Every month in the range is present so gaps are visible
     * instead of being silently skipped.
     */
    public function monthlySeries(array $filters, ?int $itemId = null): array
    {
        $rows = $this->monthlyAggregates(
            $filters['type'],
            $itemId,
            $filters['from']->copy()->startOfMonth(),
            $filters['to']->copy()->endOfMonth(),
        )->keyBy('ym');

        $months = [];
        $cursor = $filters['from']->copy()->startOfMonth();
        $end = $filters['to']->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m');
            $row = $rows->get($key);

            $months[] = [
                'key' => $key,
                'label' => $cursor->format('M Y'),
                'short' => $cursor->format('M'),
                'quantity' => $row ? (int) $row->quantity : 0,
                'average_unit_cost' => $row?->average_unit_cost,
                'total_cost' => $row?->total_cost,
                'records' => $row?->records ?? 0,
                'has_data' => (bool) $row,
            ];

            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * The type-wide monthly analysis: purchases per month plus the totals the
     * section header reports. Months without records stay as honest zeros.
     */
    public function monthlyAnalysis(array $filters): array
    {
        $months = collect($this->monthlySeries($filters));
        $withData = $months->where('has_data', true);

        return [
            'months' => $months->all(),
            'months_with_data' => $withData->count(),
            'months_total' => $months->count(),
            'total_cost' => round((float) $withData->sum('total_cost'), 2),
            'total_quantity' => (int) $withData->sum('quantity'),
            'average_unit_cost' => $withData->isNotEmpty() ? round($withData->avg('average_unit_cost'), 2) : null,
            'has_data' => $withData->isNotEmpty(),
        ];
    }

    // ---------------------------------------------------------------------
    // Single item analytics
    // ---------------------------------------------------------------------

    /** Select options for the item picker of the selected type. */
    public function itemOptions(string $type): Collection
    {
        return $type === self::TYPE_EQUIPMENT
            ? $this->equipment()->map(fn (Equipment $item) => [
                'id' => $item->id,
                'label' => $item->name . ' (' . $item->item_code . ')',
            ])->values()
            : $this->supplies()->map(fn (Supply $item) => [
                'id' => $item->id,
                'label' => $item->name . ' (' . $item->item_code . ')',
            ])->values();
    }

    /**
     * Everything the "item analytics" panel shows for the selected item.
     *
     * "Total stock cost" is what the stock on hand is worth at the average
     * recorded purchase cost (quantity × average unit cost). When an item has no
     * purchase records in the period the product's own recorded unit cost is
     * used, and the panel says which basis it used, rather than showing a
     * misleading ₱0.00.
     */
    public function itemAnalytics(array $filters): array
    {
        $itemId = $filters['item'];

        if (! $itemId || ! $this->itemExists($filters['type'], $itemId)) {
            $itemId = $this->defaultItemId($filters['type']);
        }

        return $filters['type'] === self::TYPE_EQUIPMENT
            ? $this->equipmentAnalytics($filters, $itemId)
            : $this->supplyAnalytics($filters, $itemId);
    }

    private function itemExists(string $type, int $id): bool
    {
        return $type === self::TYPE_EQUIPMENT
            ? $this->equipment()->contains('id', $id)
            : $this->supplies()->contains('id', $id);
    }

    /**
     * Default selection: the item with the most recent cost record (the one with
     * something to show), otherwise the first item by name.
     */
    private function defaultItemId(string $type): ?int
    {
        $latest = $this->costSeriesCache($type)->keys()->last();

        return $latest ?: ($type === self::TYPE_EQUIPMENT
            ? $this->equipment()->first()?->id
            : $this->supplies()->first()?->id);
    }

    private function supplyAnalytics(array $filters, ?int $itemId): array
    {
        $supply = $itemId ? $this->supplies()->firstWhere('id', $itemId) : null;

        if (! $supply) {
            return ['found' => false, 'type' => self::TYPE_SUPPLIES];
        }

        $months = $this->monthlySeries($filters, $supply->id);
        $withData = collect($months)->where('has_data', true);
        $latestRecord = $this->latestStockIn($supply->id);

        $averageUnitCost = $withData->isNotEmpty() ? round($withData->avg('average_unit_cost'), 2) : null;
        $latestUnitCost = $latestRecord?->unit_cost !== null
            ? round((float) $latestRecord->unit_cost, 2)
            : (float) $supply->unit_cost;
        $valuationCost = $this->valuationCost($averageUnitCost, (float) $supply->unit_cost);
        $status = $this->statusFor($supply->quantity_on_hand, $supply->reorder_level);

        return [
            'found' => true,
            'type' => self::TYPE_SUPPLIES,
            'id' => $supply->id,
            'name' => $supply->name,
            'item_code' => $supply->item_code,
            'unit' => $supply->unit,
            'quantity' => (int) $supply->quantity_on_hand,
            'reorder_level' => (int) $supply->reorder_level,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'status_tone' => $this->statusTone($status),
            'latest_unit_cost' => $latestUnitCost,
            'latest_unit_cost_at' => $latestRecord?->created_at ? Carbon::parse($latestRecord->created_at) : null,
            'average_unit_cost' => $averageUnitCost,
            'valuation_cost' => round($valuationCost, 2),
            'costed_from_records' => $withData->isNotEmpty(),
            'total_stock_cost' => round($supply->quantity_on_hand * $valuationCost, 2),
            'added_in_period' => (int) $withData->sum('quantity'),
            'spent_in_period' => round((float) $withData->sum('total_cost'), 2),
            'records_in_period' => (int) $withData->sum('records'),
            'months' => $months,
            'months_with_data' => $withData->count(),
            'trend' => $this->trendFor(self::TYPE_SUPPLIES, $supply->id),
            'history' => $this->recentMovements($supply->id),
            'history_kind' => 'movements',
        ];
    }

    private function equipmentAnalytics(array $filters, ?int $itemId): array
    {
        $equipment = $itemId ? $this->equipment()->firstWhere('id', $itemId) : null;

        if (! $equipment) {
            return ['found' => false, 'type' => self::TYPE_EQUIPMENT];
        }

        $months = $this->monthlySeries($filters, $equipment->id);
        $withData = collect($months)->where('has_data', true);
        $latestWithData = collect($months)->last(fn (array $month) => $month['has_data']);

        $averageUnitCost = $withData->isNotEmpty() ? round($withData->avg('average_unit_cost'), 2) : null;
        $latestUnitCost = $latestWithData['average_unit_cost'] ?? (float) $equipment->unit_cost;
        $valuationCost = $this->valuationCost($averageUnitCost, (float) $equipment->unit_cost);
        $status = $this->statusFor($equipment->quantity_on_hand, $equipment->reorder_level);

        return [
            'found' => true,
            'type' => self::TYPE_EQUIPMENT,
            'id' => $equipment->id,
            'name' => $equipment->name,
            'item_code' => $equipment->item_code,
            'unit' => $equipment->unit,
            'quantity' => (int) $equipment->quantity_on_hand,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'status_tone' => $this->statusTone($status),
            'equipment_status' => $equipment->status,
            'date_acquired' => $equipment->date_acquired,
            'latest_unit_cost' => round((float) $latestUnitCost, 2),
            'latest_unit_cost_at' => $latestWithData ? Carbon::createFromFormat('Y-m', $latestWithData['key'])->startOfMonth() : null,
            'average_unit_cost' => $averageUnitCost,
            'valuation_cost' => round($valuationCost, 2),
            'costed_from_records' => $withData->isNotEmpty(),
            'total_stock_cost' => round($equipment->quantity_on_hand * $valuationCost, 2),
            'added_in_period' => (int) $withData->sum('quantity'),
            'spent_in_period' => round((float) $withData->sum('total_cost'), 2),
            'records_in_period' => (int) $withData->sum('records'),
            'months' => $months,
            'months_with_data' => $withData->count(),
            'trend' => $this->trendFor(self::TYPE_EQUIPMENT, $equipment->id),
            'history' => $this->equipmentCostRecords($equipment->id),
            'history_kind' => 'documents',
        ];
    }

    /** Stock transactions carry a price; fall back to the product's own cost. */
    private function valuationCost(?float $averageUnitCost, float $fallback): float
    {
        return $averageUnitCost ?? $fallback;
    }

    private function latestStockIn(int $supplyId): ?object
    {
        return Inventory::query()
            ->where('supply_id', $supplyId)
            ->where('type', 'in')
            ->latest('created_at')
            ->first(['unit_cost', 'created_at']);
    }

    /** Latest stock movements for a supply, for the compact history list. */
    private function recentMovements(int $supplyId, int $limit = 5): Collection
    {
        return Inventory::with('user')
            ->where('supply_id', $supplyId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /** Equipment cost records (the de-duplicated PAR/ICS lines), newest first. */
    private function equipmentCostRecords(int $equipmentId, int $limit = 5): Collection
    {
        return $this->documentItemsQuery()
            ->where('di.equipment_id', $equipmentId)
            ->orderByDesc('d.document_date')
            ->limit($limit)
            ->get([
                'd.document_no',
                'd.document_date',
                'd.type',
                'di.quantity',
                'di.unit_cost',
            ]);
    }

    // ---------------------------------------------------------------------
    // Cost trends, changes and alerts
    // ---------------------------------------------------------------------

    /**
     * Month-over-month unit cost for every item of a type, from one grouped
     * query (cached), keyed by item id.
     *
     * @return Collection<int, Collection<string, object>>
     */
    private function costSeriesCache(string $type): Collection
    {
        return $this->costSeriesCache[$type] ??= $this->monthlyAggregates(
            $type,
            null,
            now()->copy()->subMonths(self::TREND_LOOKBACK_MONTHS)->startOfMonth(),
            now()->copy()->endOfMonth(),
        )->groupBy('item_id')->map(fn (Collection $rows) => $rows->keyBy('ym'));
    }

    /**
     * Compares the newest month that has records against the month directly
     * before it. If that previous month has no records there is nothing to
     * compare against, so the result says "no previous data" — it never guesses.
     */
    public function trendFor(string $type, int $itemId): array
    {
        return $this->trendFromSeries($this->costSeriesCache($type)->get($itemId) ?? collect());
    }

    private function trendFromSeries(Collection $series): array
    {
        $empty = [
            'direction' => 'no_previous_data',
            'current' => null,
            'previous' => null,
            'change' => null,
            'change_pct' => null,
            'from_label' => null,
            'to_label' => null,
            'months_with_data' => $series->count(),
        ];

        if ($series->count() < 2) {
            return $empty;
        }

        $keys = $series->keys()->sort()->values();
        $toKey = $keys->last();
        $fromKey = Carbon::createFromFormat('Y-m', $toKey)->subMonthNoOverflow()->format('Y-m');

        if (! $series->has($fromKey)) {
            return array_merge($empty, ['to_label' => Carbon::createFromFormat('Y-m', $toKey)->format('M Y')]);
        }

        $previous = (float) $series[$fromKey]->average_unit_cost;
        $current = (float) $series[$toKey]->average_unit_cost;
        $change = round($current - $previous, 2);

        return [
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
            'current' => round($current, 2),
            'previous' => round($previous, 2),
            'change' => $change,
            'change_pct' => $previous > 0 ? round($change / $previous * 100, 2) : null,
            'from_label' => Carbon::createFromFormat('Y-m', $fromKey)->format('M Y'),
            'to_label' => Carbon::createFromFormat('Y-m', $toKey)->format('M Y'),
            'months_with_data' => $series->count(),
        ];
    }

    /**
     * The most expensive items (by latest recorded unit cost) across supplies and
     * equipment, each with a month-over-month change when one can be proven.
     */
    public function highestCostItems(int $limit = 5): Collection
    {
        $supplyCosts = $this->latestSupplyUnitCosts();
        $supplyTrends = $this->costSeriesCache(self::TYPE_SUPPLIES);
        $equipmentTrends = $this->costSeriesCache(self::TYPE_EQUIPMENT);

        $supplies = $this->suppliesWithStatus()->map(fn (array $supply) => [
            'type' => self::TYPE_SUPPLIES,
            'type_label' => 'Supply',
            'id' => $supply['id'],
            'name' => $supply['name'],
            'item_code' => $supply['item_code'],
            'latest_unit_cost' => $supplyCosts[$supply['id']] ?? $supply['unit_cost'],
            'trend' => $this->trendFromSeries($supplyTrends->get($supply['id']) ?? collect()),
        ]);

        $equipment = $this->equipment()->map(fn (Equipment $item) => [
            'type' => self::TYPE_EQUIPMENT,
            'type_label' => 'Equipment',
            'id' => $item->id,
            'name' => $item->name,
            'item_code' => $item->item_code,
            'latest_unit_cost' => (float) $item->unit_cost,
            'trend' => $this->trendFromSeries($equipmentTrends->get($item->id) ?? collect()),
        ]);

        return $supplies->concat($equipment)
            ->sortByDesc('latest_unit_cost')
            ->take($limit)
            ->values();
    }

    /**
     * Items whose unit cost actually went up between the two newest consecutive
     * months that have records, plus how many items have no previous month to
     * compare against so the UI can say so plainly.
     */
    public function costAlerts(int $limit = 4): array
    {
        $candidates = $this->suppliesWithStatus()->map(fn (array $supply) => [
            'type' => self::TYPE_SUPPLIES,
            'type_label' => 'Supply',
            'id' => $supply['id'],
            'name' => $supply['name'],
            'unit' => $supply['unit'],
            'trend' => $this->trendFor(self::TYPE_SUPPLIES, $supply['id']),
        ])->concat($this->equipment()->map(fn (Equipment $item) => [
            'type' => self::TYPE_EQUIPMENT,
            'type_label' => 'Equipment',
            'id' => $item->id,
            'name' => $item->name,
            'unit' => $item->unit,
            'trend' => $this->trendFor(self::TYPE_EQUIPMENT, $item->id),
        ]));

        $increases = $candidates
            ->filter(fn (array $item) => $item['trend']['direction'] === 'up')
            ->sortByDesc(fn (array $item) => $item['trend']['change_pct'] ?? 0)
            ->values();

        return [
            'increases' => $increases->take($limit)->values()->all(),
            'increase_count' => $increases->count(),
            'tracked' => $candidates->count(),
            'without_comparison' => $candidates->filter(fn (array $item) => $item['trend']['direction'] === 'no_previous_data')->count(),
        ];
    }

    // ---------------------------------------------------------------------
    // Secondary operations numbers (kept, but compact on the dashboard)
    // ---------------------------------------------------------------------

    public function operationsSummary(): array
    {
        return [
            'missing_equipment' => Equipment::where('status', 'missing')->count(),
            'misplaced_equipment' => Equipment::where('status', 'misplaced')->count(),
        ];
    }

    public function recentActivity(int $limit = 6): Collection
    {
        return ActivityLog::with('user')->latest()->limit($limit)->get();
    }

    // ---------------------------------------------------------------------
    // Shared query plumbing
    // ---------------------------------------------------------------------

    /** One query for every supply, cached for the rest of the request. */
    private function supplies(): Collection
    {
        // is_consumable travels with the row: the classification figures are read
        // off these cached rows rather than queried again.
        return $this->supplies ??= Supply::query()
            ->select(['id', 'item_code', 'name', 'unit', 'quantity_on_hand', 'reorder_level', 'unit_cost', 'is_consumable'])
            ->orderBy('name')
            ->get();
    }

    private function equipment(): Collection
    {
        return $this->equipment ??= Equipment::query()
            ->select(['id', 'item_code', 'name', 'unit', 'quantity_on_hand', 'reorder_level', 'unit_cost', 'is_consumable', 'status', 'date_acquired'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Latest recorded purchase cost per supply in one query (no N+1), keyed by
     * supply id.
     */
    private function latestSupplyUnitCosts(): Collection
    {
        $latestIds = Inventory::query()
            ->select('supply_id', DB::raw('MAX(id) as id'))
            ->where('type', 'in')
            ->groupBy('supply_id');

        return DB::table('inventory as i')
            ->joinSub($latestIds, 't', 't.id', '=', 'i.id')
            ->pluck('i.unit_cost', 'i.supply_id')
            ->map(fn ($cost) => round((float) $cost, 2));
    }

    /**
     * Equipment cost records from acquisition documents, de-duplicated: the same
     * purchase is often recorded on more than one form (for example an ICS and a
     * PAR dated the same day at the same price), so only the first such row is
     * counted instead of inflating the history.
     */
    private function documentItemsQuery()
    {
        return DB::table('document_items as di')
            ->join('documents as d', 'd.id', '=', 'di.document_id')
            ->whereIn('d.type', self::EQUIPMENT_COST_DOCUMENTS)
            ->whereNotNull('di.equipment_id')
            ->where('di.unit_cost', '>', 0)
            ->whereIn('di.id', function ($query) {
                $query->selectRaw('MIN(di2.id)')
                    ->from('document_items as di2')
                    ->join('documents as d2', 'd2.id', '=', 'di2.document_id')
                    ->whereIn('d2.type', self::EQUIPMENT_COST_DOCUMENTS)
                    ->whereNotNull('di2.equipment_id')
                    ->where('di2.unit_cost', '>', 0)
                    ->groupByRaw('di2.equipment_id, d2.document_date, di2.unit_cost');
            });
    }

    /** Portable "year-month" expression for the current database driver. */
    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
