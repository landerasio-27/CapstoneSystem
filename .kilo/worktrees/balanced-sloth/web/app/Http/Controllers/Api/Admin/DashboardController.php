<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Supply;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Admin dashboard summary for the desktop client. Mirrors the stats shown on
     * the web dashboard so the desktop app renders the same numbers.
     */
    public function index(): JsonResponse
    {
        $stats = [
            'total_equipment' => Equipment::count(),
            'total_supplies' => Supply::count(),
            'low_stock_supplies' => Supply::whereColumn('quantity_on_hand', '<=', 'reorder_level')->count(),
            'low_stock_equipment' => Equipment::whereColumn('quantity_on_hand', '<=', 'reorder_level')->count(),
            'missing_equipment' => Equipment::where('status', 'missing')->count(),
            'misplaced_equipment' => Equipment::where('status', 'misplaced')->count(),
            'documents_draft' => Document::where('status', 'draft')->count(),
        ];

        $recentActivity = ActivityLog::with('user')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'description' => $log->description,
                'module' => $log->module,
                'user' => $log->user?->name ?? 'System',
                'created_at_human' => $log->created_at->diffForHumans(),
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dashboard loaded.',
            'data' => [
                'stats' => $stats,
                'recent_activity' => $recentActivity,
            ],
        ]);
    }
}
