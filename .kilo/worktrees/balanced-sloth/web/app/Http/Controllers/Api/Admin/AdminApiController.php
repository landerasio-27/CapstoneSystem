<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Base for the admin JSON API consumed by the desktop client. Keeps the
 * {status, message, data} envelope consistent across every resource.
 */
abstract class AdminApiController extends Controller
{
    protected function ok($data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['status' => 'success', 'message' => $message, 'data' => $data], $status);
    }

    protected function fail(string $message, int $status = 422, $data = null): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message, 'data' => $data], $status);
    }
}
