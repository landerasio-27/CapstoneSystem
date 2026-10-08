<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Equipment;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\Supply;
use App\Models\User;
use App\Services\InventoryAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The dashboard must report what the database actually contains: real totals,
 * real month-by-month figures, and honest "no data" states instead of invented
 * numbers or trends that the records cannot support.
 */
class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => Role::ADMIN, 'display_name' => 'Administrator']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function supply(array $attributes = []): Supply
    {
        return Supply::create($attributes + [
            'item_code' => 'SUP-' . str_pad((string) (Supply::count() + 1), 5, '0', STR_PAD_LEFT),
            'name' => 'Ballpoint Pen (Black)',
            'category' => 'Office Supplies',
            'unit' => 'piece',
            'quantity_on_hand' => 100,
            'unit_cost' => 5,
            'reorder_level' => 0,
            'is_active' => true,
        ]);
    }

    private function equipment(array $attributes = []): Equipment
    {
        return Equipment::create($attributes + [
            'item_code' => 'EQP-' . str_pad((string) (Equipment::count() + 1), 5, '0', STR_PAD_LEFT),
            'name' => 'Laptop',
            'category' => 'ICT Equipment',
            'unit' => 'unit',
            'quantity_on_hand' => 1,
            'unit_cost' => 35000,
            'reorder_level' => 0,
            'status' => 'available',
        ]);
    }

    /** A stock-in row dated on purpose (the app itself always writes "now"). */
    private function stockIn(Supply $supply, int $quantity, float $unitCost, string $when): void
    {
        Inventory::insert([
            'supply_id' => $supply->id,
            'type' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 2),
            'balance_after' => $supply->quantity_on_hand,
            'remarks' => null,
            'user_id' => $this->admin->id,
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }

    /** A PAR/ICS line: how the system records what equipment cost. */
    private function propertyDocument(Equipment $equipment, string $type, string $date, float $unitCost, int $quantity = 1): Document
    {
        $document = Document::create([
            'document_no' => $type . '-' . str_pad((string) (Document::count() + 1), 5, '0', STR_PAD_LEFT),
            'type' => $type,
            'status' => 'approved',
            'document_date' => $date,
        ]);

        DocumentItem::create([
            'document_id' => $document->id,
            'equipment_id' => $equipment->id,
            'description' => $equipment->name,
            'unit' => 'unit',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'amount' => $quantity * $unitCost,
        ]);

        return $document;
    }

    private function analytics(): InventoryAnalyticsService
    {
        return app(InventoryAnalyticsService::class);
    }

    private function dashboard(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('admin.dashboard', $query));
    }

    public function test_dashboard_shows_a_compact_summary_with_real_totals(): void
    {
        $this->supply(['item_code' => 'SUP-00001', 'name' => 'Bond Paper A4', 'quantity_on_hand' => 100, 'unit_cost' => 5]);
        $this->supply(['item_code' => 'SUP-00002', 'name' => 'Whiteboard Marker', 'quantity_on_hand' => 10, 'unit_cost' => 20]);
        $this->equipment(['item_code' => 'EQP-00001', 'quantity_on_hand' => 1, 'unit_cost' => 1000]);

        // Supplies 100×5 + 10×20 = 700, equipment 1×1000 = 1000, total 1700.
        $this->dashboard()
            ->assertOk()
            ->assertSee('Inventory Value')
            ->assertSee('₱1,700.00')
            ->assertSee('₱700.00')
            ->assertSee('₱1,000.00')
            ->assertSee('2 supplies measured against their reorder point')
            ->assertSee('Stock Level Analytics')
            ->assertSee('Stock Status')
            ->assertSee('Cost Overview')
            ->assertSee('Highest Cost Items');
    }

    public function test_the_dashboard_shows_the_reorder_point_behind_each_status(): void
    {
        $plenty = $this->supply(['item_code' => 'SUP-00001', 'name' => 'Plenty', 'quantity_on_hand' => 120, 'reorder_level' => 30]);
        $this->supply(['item_code' => 'SUP-00002', 'name' => 'Scarce', 'quantity_on_hand' => 20, 'reorder_level' => 45]);
        $this->equipment(['item_code' => 'EQP-00001', 'quantity_on_hand' => 1, 'reorder_level' => 5]);

        $this->dashboard()
            ->assertOk()
            // The bar chart caption and the status table both name the threshold,
            // so a Low/Critical verdict can be read against the number behind it.
            ->assertSee('Reorder at 30')
            ->assertSee('Reorder point')
            ->assertSee('measured against their reorder point')
            ->assertSee('45');

        // The selected item's own threshold is shown next to its current stock.
        $this->dashboard(['type' => 'supplies', 'item' => $plenty->id])
            ->assertOk()
            ->assertSee('reorder point 30');
    }

    public function test_a_supply_without_a_reorder_point_says_so(): void
    {
        $this->supply(['item_code' => 'SUP-00001', 'name' => 'Unset', 'quantity_on_hand' => 120, 'reorder_level' => 0]);

        $this->dashboard()
            ->assertOk()
            ->assertSee('No reorder point set')
            // Nothing is invented for an item that has no threshold on record.
            ->assertDontSee('Reorder at 0');
    }

    public function test_the_old_large_statistic_cards_are_gone(): void
    {
        $this->supply();
        $this->equipment();

        // Scoped to the main content: the sidebar legitimately carries a
        // "Missing Equipment" nav item; the cards are what must stay gone.
        $html = $this->dashboard()->assertOk()->getContent();
        $main = substr($html, (int) strpos($html, '<main'));

        $this->assertStringNotContainsString('Total Equipment', $main);
        $this->assertStringNotContainsString('Total Supplies', $main);
        $this->assertStringNotContainsString('Low Stock Items', $main);
        $this->assertStringNotContainsString('Misplaced Equipment', $main);
        // ... but the operations numbers are still available, compactly.
        $this->assertStringContainsString('Missing equipment', $main);
    }

    public function test_the_quick_actions_and_activity_cards_are_gone_from_the_dashboard(): void
    {
        $this->supply();
        $this->equipment();

        // The shortcuts only repeated links the sidebar already provides, and the
        // audit feed now lives on the Activity Logs page, so the whole bottom row
        // is gone and the dashboard ends on the analytics sections.
        $this->dashboard()
            ->assertOk()
            ->assertDontSee('Quick Actions')
            ->assertDontSee('New Document (PAR)')
            ->assertDontSee('Generate Report')
            ->assertDontSee('Recent Activity')
            ->assertDontSee('View all');
    }

    public function test_stock_status_follows_the_reorder_point_rule(): void
    {
        $analytics = $this->analytics();

        $this->assertSame('healthy', $analytics->statusFor(120, 30));
        $this->assertSame('low', $analytics->statusFor(30, 50));          // at the reorder point
        $this->assertSame('critical', $analytics->statusFor(25, 50));     // at half the reorder point
        $this->assertSame('low', $analytics->statusFor(26, 50));          // above half, still at/below the point
        $this->assertSame('healthy', $analytics->statusFor(51, 50));
        $this->assertSame('critical', $analytics->statusFor(0, 10));      // empty is always critical
        $this->assertSame('healthy', $analytics->statusFor(7, 0));        // no reorder point configured
    }

    public function test_stock_status_counts_and_table_use_stored_quantities(): void
    {
        $this->supply(['item_code' => 'SUP-00001', 'name' => 'Plenty', 'quantity_on_hand' => 120, 'reorder_level' => 30]);
        $this->supply(['item_code' => 'SUP-00002', 'name' => 'Nearly', 'quantity_on_hand' => 30, 'reorder_level' => 50]);
        $this->supply(['item_code' => 'SUP-00003', 'name' => 'Barely', 'quantity_on_hand' => 10, 'reorder_level' => 50]);
        $this->supply(['item_code' => 'SUP-00004', 'name' => 'Gone', 'quantity_on_hand' => 0, 'reorder_level' => 10]);
        $this->supply(['item_code' => 'SUP-00005', 'name' => 'Unconfigured', 'quantity_on_hand' => 7, 'reorder_level' => 0]);

        $counts = $this->analytics()->statusCounts();

        $this->assertSame(2, $counts['healthy']);
        $this->assertSame(1, $counts['low']);
        $this->assertSame(2, $counts['critical']);
        $this->assertSame(5, $counts['total']);

        $this->dashboard()
            ->assertOk()
            ->assertSee('Supply Stock Status')
            ->assertSee('Plenty')
            ->assertSee('Barely')
            ->assertSee('Critical');
    }

    public function test_monthly_averages_and_quantities_come_from_recorded_transactions(): void
    {
        $supply = $this->supply(['item_code' => 'SUP-00001', 'name' => 'Ballpoint Pen (Black)', 'quantity_on_hand' => 300]);

        // June: two purchases, ₱7.00 then ₱8.00 (average ₱7.50, total ₱750).
        $this->stockIn($supply, 50, 7.00, '2026-06-10 09:00:00');
        $this->stockIn($supply, 50, 8.00, '2026-06-20 09:00:00');
        // July: one purchase at ₱8.50.
        $this->stockIn($supply, 100, 8.50, '2026-07-15 09:00:00');

        $analytics = $this->analytics();
        $filters = $analytics->filters(['type' => 'supplies', 'item' => $supply->id, 'period' => 'custom', 'from' => '2026-06', 'to' => '2026-07']);
        $series = collect($analytics->monthlySeries($filters, $supply->id));

        $june = $series->firstWhere('key', '2026-06');
        $this->assertSame(100, $june['quantity']);
        $this->assertSame(7.5, $june['average_unit_cost']);
        $this->assertSame(750.0, $june['total_cost']);
        $this->assertSame(2, $june['records']);

        $july = $series->firstWhere('key', '2026-07');
        $this->assertSame(100, $july['quantity']);
        $this->assertSame(8.5, $july['average_unit_cost']);
        $this->assertSame(850.0, $july['total_cost']);

        // The totals reported on screen come from those same rows.
        $this->dashboard(['type' => 'supplies', 'item' => $supply->id, 'period' => 'custom', 'from' => '2026-06', 'to' => '2026-07'])
            ->assertOk()
            ->assertSee('₱1,600.00')   // 750 + 850
            ->assertSee('₱7.50')
            ->assertSee('₱8.50')
            ->assertSee('200 piece');  // 100 + 100 added in the period
    }

    public function test_cost_trend_reports_an_increase_with_its_percentage(): void
    {
        $supply = $this->supply(['item_code' => 'SUP-00001', 'name' => 'Printer Ink Bottle']);
        $this->stockIn($supply, 20, 8.00, '2026-06-05 09:00:00');
        $this->stockIn($supply, 20, 8.50, '2026-07-05 09:00:00');

        $trend = $this->analytics()->trendFor('supplies', $supply->id);

        $this->assertSame('up', $trend['direction']);
        $this->assertSame(8.0, $trend['previous']);
        $this->assertSame(8.5, $trend['current']);
        $this->assertSame(0.5, $trend['change']);
        $this->assertSame(6.25, $trend['change_pct']);
        $this->assertSame('Jun 2026', $trend['from_label']);
        $this->assertSame('Jul 2026', $trend['to_label']);

        $this->dashboard(['type' => 'supplies', 'item' => $supply->id])
            ->assertOk()
            ->assertSee('Increasing')
            ->assertSee('+₱0.50')
            ->assertSee('+6.25%');
    }

    public function test_cost_trend_reports_a_decrease_too(): void
    {
        $supply = $this->supply();
        $this->stockIn($supply, 20, 10.00, '2026-06-05 09:00:00');
        $this->stockIn($supply, 20, 9.00, '2026-07-05 09:00:00');

        $trend = $this->analytics()->trendFor('supplies', $supply->id);

        $this->assertSame('down', $trend['direction']);
        $this->assertSame(-1.0, $trend['change']);
        $this->assertSame(-10.0, $trend['change_pct']);

        $this->dashboard(['type' => 'supplies', 'item' => $supply->id])
            ->assertOk()
            ->assertSee('Decreasing');
    }

    public function test_cost_trend_says_no_previous_data_with_only_one_month_of_records(): void
    {
        $supply = $this->supply();
        $this->stockIn($supply, 20, 8.00, '2026-07-05 09:00:00');

        $trend = $this->analytics()->trendFor('supplies', $supply->id);

        $this->assertSame('no_previous_data', $trend['direction']);
        $this->assertNull($trend['change']);
        $this->assertNull($trend['change_pct']);

        $this->dashboard(['type' => 'supplies', 'item' => $supply->id])
            ->assertOk()
            ->assertSee('No previous cost data')
            ->assertDontSee('Increasing');
    }

    public function test_cost_trend_refuses_to_compare_months_that_are_not_consecutive(): void
    {
        $supply = $this->supply();
        $this->stockIn($supply, 20, 8.00, '2026-05-05 09:00:00');
        $this->stockIn($supply, 20, 9.00, '2026-07-05 09:00:00');   // June has no records

        $trend = $this->analytics()->trendFor('supplies', $supply->id);

        $this->assertSame('no_previous_data', $trend['direction']);
        $this->assertSame('Jul 2026', $trend['to_label']);
        $this->assertSame(2, $trend['months_with_data']);
    }

    public function test_cost_alerts_list_only_items_with_a_recorded_increase(): void
    {
        $rising = $this->supply(['item_code' => 'SUP-00001', 'name' => 'Rising Ink']);
        $flat = $this->supply(['item_code' => 'SUP-00002', 'name' => 'Steady Paper']);

        $this->stockIn($rising, 10, 8.00, '2026-06-05 09:00:00');
        $this->stockIn($rising, 10, 8.50, '2026-07-05 09:00:00');
        $this->stockIn($flat, 10, 5.00, '2026-06-05 09:00:00');
        $this->stockIn($flat, 10, 5.00, '2026-07-05 09:00:00');

        $alerts = $this->analytics()->costAlerts();

        $this->assertSame(1, $alerts['increase_count']);
        $this->assertSame('Rising Ink', $alerts['increases'][0]['name']);
        $this->assertSame(6.25, $alerts['increases'][0]['trend']['change_pct']);

        $this->dashboard()
            ->assertOk()
            ->assertSee('Rising Ink')
            ->assertSee('unit cost increased by')
            ->assertDontSee('No cost increases detected.');
    }

    public function test_cost_alerts_explain_when_nothing_can_be_compared_yet(): void
    {
        $supply = $this->supply();
        $this->stockIn($supply, 10, 8.00, '2026-07-05 09:00:00');

        $this->dashboard()
            ->assertOk()
            ->assertSee('No cost increases detected.')
            ->assertSee('have no previous month to compare against');
    }

    public function test_equipment_cost_history_comes_from_property_documents(): void
    {
        $equipment = $this->equipment(['item_code' => 'EQP-00001', 'name' => 'Laptop', 'unit_cost' => 35000]);

        // The same acquisition written on two forms the same day counts once.
        $this->propertyDocument($equipment, 'PAR', '2026-06-05', 35000);
        $this->propertyDocument($equipment, 'ICS', '2026-06-05', 35000);
        // A later purchase at a higher price.
        $this->propertyDocument($equipment, 'ICS', '2026-07-10', 37000);

        $analytics = $this->analytics();
        $filters = $analytics->filters(['type' => 'equipment', 'item' => $equipment->id, 'period' => 'custom', 'from' => '2026-06', 'to' => '2026-07']);
        $series = collect($analytics->monthlySeries($filters, $equipment->id));

        $june = $series->firstWhere('key', '2026-06');
        $this->assertSame(1, $june['records'], 'The same acquisition must not be counted twice.');
        $this->assertSame(35000.0, $june['average_unit_cost']);

        $july = $series->firstWhere('key', '2026-07');
        $this->assertSame(37000.0, $july['average_unit_cost']);

        $trend = $analytics->trendFor('equipment', $equipment->id);
        $this->assertSame('up', $trend['direction']);
        $this->assertSame(2000.0, $trend['change']);

        $this->dashboard(['type' => 'equipment', 'item' => $equipment->id])
            ->assertOk()
            ->assertSee('Equipment Cost Analytics')
            ->assertSee('Increasing')
            ->assertSee('Laptop');
    }

    public function test_equipment_without_enough_records_says_so(): void
    {
        $equipment = $this->equipment(['name' => 'Projector']);
        $this->propertyDocument($equipment, 'PAR', '2026-07-05', 28000);

        $trend = $this->analytics()->trendFor('equipment', $equipment->id);

        $this->assertSame('no_previous_data', $trend['direction']);

        $this->dashboard(['type' => 'equipment', 'item' => $equipment->id])
            ->assertOk()
            ->assertSee('No previous cost data');
    }

    public function test_period_filter_changes_the_window_of_records_used(): void
    {
        $supply = $this->supply();
        $this->stockIn($supply, 40, 5.00, now()->copy()->subMonths(10)->format('Y-m-d') . ' 09:00:00');
        $this->stockIn($supply, 10, 5.00, now()->format('Y-m-d') . ' 09:00:00');

        $analytics = $this->analytics();

        $short = $analytics->itemAnalytics($analytics->filters(['type' => 'supplies', 'item' => $supply->id, 'period' => '3']));
        $this->assertSame(10, $short['added_in_period']);

        $long = $analytics->itemAnalytics($analytics->filters(['type' => 'supplies', 'item' => $supply->id, 'period' => '12']));
        $this->assertSame(50, $long['added_in_period']);
    }

    public function test_custom_range_keeps_its_month_inputs_and_its_window(): void
    {
        $supply = $this->supply();
        $this->stockIn($supply, 7, 5.00, '2026-03-10 09:00:00');
        $this->stockIn($supply, 3, 5.00, '2026-04-10 09:00:00');
        $this->stockIn($supply, 50, 5.00, '2026-08-10 09:00:00');

        // Picking the period on its own must not bounce back to a preset: the
        // From/To month inputs only exist while the period is "custom", so
        // falling back would hide the controls needed to choose a range.
        $bare = $this->analytics()->filters(['period' => 'custom']);
        $this->assertSame('custom', $bare['period']);
        $this->assertSame(now()->format('Y-m'), $bare['to_input']);
        $this->assertSame(now()->copy()->subMonths(5)->format('Y-m'), $bare['from_input']);

        $this->dashboard(['period' => 'custom'])
            ->assertOk()
            ->assertSee('From month')
            ->assertSee('To month');

        // The months the user picks are the window that is actually queried.
        $range = ['type' => 'supplies', 'item' => $supply->id, 'period' => 'custom', 'from' => '2026-03', 'to' => '2026-04'];
        $filters = $this->analytics()->filters($range);

        $dash = "\u{2013}";
        $label = 'Mar 2026 ' . $dash . ' Apr 2026';

        $this->assertSame($label, $filters['range_label']);
        $this->assertCount(2, $this->analytics()->monthlySeries($filters, $supply->id));
        $this->assertSame(10, $this->analytics()->itemAnalytics($filters)['added_in_period']);

        $this->dashboard($range)
            ->assertOk()
            ->assertSee($label)
            ->assertSee('value="2026-03"', false)   // the From month input keeps what was asked for
            ->assertSee('value="2026-04"', false);  // and so does To

        // A range typed the other way round is read as the range intended.
        $swapped = $this->analytics()->filters(['period' => 'custom', 'from' => '2026-08', 'to' => '2026-03']);
        $this->assertSame('Mar 2026 ' . $dash . ' Aug 2026', $swapped['range_label']);
    }

    /**
     * The filter bar has nothing to apply and nothing to reset: each field
     * submits as it changes, so the picker has to show the analytics the page is
     * actually reporting. The period keys are written as numbers and so are
     * held by PHP as integers, which is why they are compared as text.
     */
    public function test_the_filter_pickers_stand_on_the_analytics_being_shown(): void
    {
        $this->supply();

        // The default the dashboard opens on, and a period asked for by name.
        $this->dashboard()
            ->assertOk()
            ->assertSee('<option value="6" selected>Last 6 Months</option>', false)
            ->assertDontSee('Apply')
            ->assertDontSee('Reset');

        $this->dashboard(['period' => '12'])
            ->assertOk()
            ->assertSee('<option value="12" selected>Last 12 Months</option>', false)
            ->assertSee('Last 12 Months (');

        $this->dashboard(['period' => 'custom', 'from' => '2026-03', 'to' => '2026-04'])
            ->assertOk()
            ->assertSee('<option value="custom" selected>Custom Range</option>', false);
    }

    public function test_months_without_records_are_shown_as_empty(): void
    {
        $supply = $this->supply();
        $this->stockIn($supply, 10, 5.00, now()->format('Y-m-d') . ' 09:00:00');

        $months = $this->analytics()->monthlyAnalysis($this->analytics()->filters(['type' => 'supplies', 'period' => '3']));

        $this->assertSame(3, $months['months_total']);
        $this->assertSame(1, $months['months_with_data']);
        $this->assertSame(50.0, $months['total_cost']);
        $this->assertSame(10, $months['total_quantity']);

        $this->dashboard(['period' => '3'])
            ->assertOk()
            ->assertSee('1 of 3 months in this range have purchase records')
            ->assertSee('No records for this month');
    }

    /**
     * An SVG clips whatever leaves its viewBox, so the amount beside the newest
     * month, the prices down the value axis, and the month names under the axis
     * all have to be drawn inside the frame. The chart used to centre every price
     * on its dot, which cut the newest one off at the edge of the card and took the
     * peso sign with it. The month names are turned 45° now, which moves the room
     * they need out of the right gutter and into the left one and the bottom band,
     * so the corners of the turned names are checked too.
     */
    public function test_the_price_beside_the_newest_month_stays_inside_the_chart_frame(): void
    {
        $points = collect(range(1, 6))->map(fn (int $month): array => [
            'label' => now()->startOfYear()->addMonths($month - 1)->format('M Y'),
            'short' => now()->startOfYear()->addMonths($month - 1)->format('M'),
            'value' => $month === 6 ? 1234567.89 : null,
        ])->all();

        $svg = Blade::render(
            '<x-chart.line :points="$points" value-prefix="₱" :decimals="2" />',
            ['points' => $points],
        );

        preg_match('/viewBox="0 0 ([0-9.]+) ([0-9.]+)"/', $svg, $frame);
        $this->assertCount(3, $frame, 'The chart has to declare the frame its labels are measured against.');
        [, $frameWidth, $frameHeight] = $frame;

        preg_match_all(
            '/<text x="(-?[0-9.]+)" y="(-?[0-9.]+)" text-anchor="(\w+)"'
            . '(?:\s+transform="rotate\((-?[0-9.]+) (-?[0-9.]+) (-?[0-9.]+)\)")?'
            . '\s+class="([^"]+)">\s*([^<]*?)\s*<\/text>/s',
            $svg,
            $labels,
            PREG_SET_ORDER,
        );

        $this->assertNotEmpty($labels);

        foreach ($labels as [, $x, $y, $anchor, $angle, $pivotX, $pivotY, $class, $text]) {
            $text = trim($text);
            $width = $this->chartLabelWidth($text);
            $left = match ($anchor) {
                'end' => (float) $x - $width,
                'start' => (float) $x,
                default => (float) $x - $width / 2,
            };

            $box = [$left, (float) $y - self::LABEL_ASCENT, $left + $width, (float) $y + self::LABEL_DESCENT];

            if ($angle !== '') {
                $box = $this->turnedLabelBox((float) $angle, (float) $pivotX, (float) $pivotY, $box);
            }

            [$left, $top, $right, $bottom] = $box;

            $this->assertGreaterThanOrEqual(0, $left, 'The chart clips "' . $text . '" at its left edge.');
            $this->assertLessThanOrEqual((float) $frameWidth, $right, 'The chart clips "' . $text . '" at its right edge.');
            $this->assertGreaterThanOrEqual(0, $top, 'The chart clips "' . $text . '" at its top edge.');
            $this->assertLessThanOrEqual((float) $frameHeight, $bottom, 'The chart clips "' . $text . '" below its axis.');
        }

        // The newest amount is the one that ran off the edge, so it hangs inwards.
        $this->assertStringContainsString('text-anchor="end"', $svg);

        // Flat month names collided on a wide screen, so they are turned instead.
        $this->assertStringContainsString('transform="rotate(-45 ', $svg);
    }

    /** The largest size the stylesheet gives a chart label, in viewBox units. */
    private const LABEL_SIZE = 24.0;

    /** How far type reaches above the line it sits on, at the largest label size. */
    private const LABEL_ASCENT = 0.80 * self::LABEL_SIZE;

    /** How far type drops below the line it sits on, at the largest label size. */
    private const LABEL_DESCENT = 0.25 * self::LABEL_SIZE;

    /**
     * Rough advance width of a chart label in viewBox units, at the 24px worst
     * case. The em widths mirror the estimate the component reserves its gutters
     * with, so a label that fits here fits inside the frame too.
     */
    private function chartLabelWidth(string $text): float
    {
        $advance = ['₱' => 0.72, ' ' => 0.32, ',' => 0.32, '.' => 0.32, '-' => 0.38, '%' => 0.64, '—' => 0.95];

        $units = 0.0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $character) {
            $units += ($advance[$character] ?? 0.60) * self::LABEL_SIZE;
        }

        return $units;
    }

    /**
     * The box a label occupies once it has been turned about its tick, given as
     * [left, top, right, bottom] in viewBox units.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $box
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function turnedLabelBox(float $angle, float $pivotX, float $pivotY, array $box): array
    {
        [$left, $top, $right, $bottom] = $box;
        $cos = cos(deg2rad($angle));
        $sin = sin(deg2rad($angle));
        $x = [];
        $y = [];

        foreach ([[$left, $top], [$right, $top], [$right, $bottom], [$left, $bottom]] as [$cornerX, $cornerY]) {
            $dx = $cornerX - $pivotX;
            $dy = $cornerY - $pivotY;
            $x[] = $pivotX + $dx * $cos - $dy * $sin;
            $y[] = $pivotY + $dx * $sin + $dy * $cos;
        }

        return [min($x), min($y), max($x), max($y)];
    }

    public function test_monthly_change_is_safe_when_the_previous_month_has_no_purchases(): void
    {
        $supply = $this->supply();
        $this->stockIn($supply, 10, 5.00, now()->format('Y-m-d') . ' 09:00:00');

        $costs = $this->analytics()->costOverview();

        $this->assertSame(50.0, $costs['this_month']);
        $this->assertSame(0.0, $costs['previous_month']);
        $this->assertNull($costs['change_pct'], 'A percentage against zero purchases is impossible, so it must be null.');

        $this->dashboard()
            ->assertOk()
            ->assertSee('No previous month data')
            ->assertDontSee('INF')
            ->assertDontSee('NaN');
    }

    public function test_unknown_item_or_invalid_filters_fall_back_instead_of_failing(): void
    {
        $supply = $this->supply();

        $item = $this->analytics()->itemAnalytics($this->analytics()->filters(['item' => 999999]));
        $this->assertTrue($item['found']);
        $this->assertSame($supply->id, $item['id']);

        $this->dashboard(['item' => 'not-a-number', 'period' => '99', 'type' => 'nonsense', 'year' => 'abc'])
            ->assertOk()
            ->assertSee('Stock Level Analytics');

        $this->dashboard(['period' => 'custom', 'from' => 'nonsense', 'to' => '2026-13'])->assertOk();
        $this->dashboard(['period' => 'custom', 'from' => '2026-07', 'to' => '2026-03'])->assertOk();
        $this->dashboard(['type' => 'equipment'])->assertOk();
    }

    public function test_dashboard_is_honest_when_there_is_no_inventory_data(): void
    {
        $this->dashboard()
            ->assertOk()
            ->assertSee('No supplies recorded yet')
            ->assertSee('No stock-in transaction records in this period')
            ->assertSee('No cost increases detected.')
            ->assertSee('No previous month data');
    }

    public function test_the_dashboard_ends_on_analytics_not_an_activity_feed(): void
    {
        $this->supply();
        ActivityLog::record('stock_in', 'supplies', 'Added 20 piece of "Whiteboard Marker" at ₱8.00 each.');

        $this->dashboard()
            ->assertOk()
            ->assertDontSee('Recent Activity')
            ->assertDontSee('No activity recorded yet.')
            ->assertDontSee('Added 20 piece of &quot;Whiteboard Marker&quot; at ₱8.00 each.', false)
            // The last thing on the page is the analytics, not a feed.
            ->assertSee('Cost Overview')
            ->assertSee('Highest Cost Items');
    }

    public function test_the_audit_trail_is_still_on_the_activity_logs_page(): void
    {
        $this->supply();
        ActivityLog::record('stock_in', 'supplies', 'Added 20 piece of "Whiteboard Marker" at ₱8.00 each.');

        // Nothing was removed from the audit trail itself: it is still recorded
        // and still readable on its own page.
        $this->assertSame(1, ActivityLog::count());

        $this->actingAs($this->admin)
            ->get('/admin/activity-logs')
            ->assertOk()
            ->assertSee('Added 20 piece of &quot;Whiteboard Marker&quot; at ₱8.00 each.', false)
            ->assertSee($this->admin->name);
    }

    public function test_guests_cannot_open_the_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }
}
