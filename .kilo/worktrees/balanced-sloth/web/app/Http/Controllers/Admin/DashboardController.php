<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InventoryAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly InventoryAnalyticsService $analytics) {}

    /**
     * Inventory analytics dashboard.
     *
     * The analytics below are filters over the stock history, not separate
     * pages: type (supplies/equipment), item and period all arrive on the query
     * string and every number is recalculated from the database on each request.
     */
    public function index(Request $request): View
    {
        $filters = $this->analytics->filters($request->only(['type', 'item', 'period', 'year', 'from', 'to']));

        $item = $this->analytics->itemAnalytics($filters);

        // Keep the item picker in sync when the requested item does not exist.
        $filters['item'] = $item['id'] ?? null;

        return view('admin.dashboard', [
            'filters' => $filters,
            'summary' => $this->analytics->summary(),
            'operations' => $this->analytics->operationsSummary(),
            'stockLevels' => $this->analytics->stockLevels(8),
            'supplyStatus' => $this->analytics->suppliesWithStatus(),
            'statusCounts' => $this->analytics->statusCounts(),
            'lowStock' => $this->analytics->lowStockSupplies(),
            'itemOptions' => $this->analytics->itemOptions($filters['type']),
            'item' => $item,
            'monthly' => $this->analytics->monthlyAnalysis($filters),
            'costs' => $this->analytics->costOverview(),
            'highestCostItems' => $this->analytics->highestCostItems(5),
            'costAlerts' => $this->analytics->costAlerts(4),
        ]);
    }
}
