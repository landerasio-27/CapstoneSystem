<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends AdminApiController
{
    public function index(Request $request): JsonResponse
    {
        $logs = ActivityLog::with('user')
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->module))
            ->when($request->filled('search'), fn ($q) => $q->where('description', 'like', "%{$request->search}%"))
            ->latest()
            ->limit(300)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->module,
                'description' => $log->description,
                'user' => $log->user?->name ?? 'System',
                'created_at' => $log->created_at->format('Y-m-d H:i'),
                'created_at_human' => $log->created_at->diffForHumans(),
            ]);

        return $this->ok($logs);
    }
}
