<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public discovery endpoint used by the Flutter app to verify that a device
 * found on the local network is really the ISU supply management backend.
 *
 * Deliberately minimal: no secrets, no database access, no auth — it must be
 * reachable before the app has any credentials. The Flutter app only accepts
 * a server whose response matches the expected service name, so an unrelated
 * device on the LAN can never be mistaken for the real backend.
 */
class ServerDiscoveryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'ISU Supply Management',
            'service_key' => 'isu-supply',
            'version' => '1.0',
            'api' => '/api',
        ]);
    }
}