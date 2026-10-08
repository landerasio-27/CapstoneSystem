<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::with('user')
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->module))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $modules = ActivityLog::query()->distinct()->orderBy('module')->pluck('module');
        $actions = ActivityLog::query()->distinct()->orderBy('action')->pluck('action');
        $users = \App\Models\User::orderBy('name')->get();

        return view('admin.activity-logs.index', compact('logs', 'modules', 'actions', 'users'));
    }
}
