<?php

use App\Http\Controllers\Api\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Api\Admin\EquipmentController as AdminEquipmentController;
use App\Http\Controllers\Api\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Api\Admin\SupplyController as AdminSupplyController;
use App\Http\Controllers\Api\Admin\TeacherController as AdminTeacherController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ServerDiscoveryController;
use Illuminate\Support\Facades\Route;

// Public: lets the Flutter app verify a LAN server before trusting it. Must
// stay outside every auth/role group — discovery happens before login.
Route::get('/server-discovery', [ServerDiscoveryController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'role:inspection_staff'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/fcm-token', [NotificationController::class, 'updateFcmToken']);
    Route::post('/fcm-token/remove', [NotificationController::class, 'removeFcmToken']);

});

/*
|--------------------------------------------------------------------------
| Admin API — consumed by the desktop client (desktop/).
|--------------------------------------------------------------------------
| Mirrors the web admin portal as JSON. Auth uses the same POST /api/login
| (any active user); these routes additionally require the `admin` role.
| Resource endpoints are added here as each screen is ported to the desktop.
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    Route::apiResource('rooms', AdminRoomController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('teachers', AdminTeacherController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('equipment', AdminEquipmentController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['equipment' => 'equipment']);
    Route::apiResource('supplies', AdminSupplyController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('users', AdminUserController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/roles', [AdminRoleController::class, 'index']);
    Route::get('/activity-logs', [AdminActivityLogController::class, 'index']);

    // Account settings (self)
    Route::get('/profile', [AdminProfileController::class, 'show']);
    Route::put('/profile', [AdminProfileController::class, 'update']);
    Route::put('/profile/password', [AdminProfileController::class, 'password']);

    // Documents
    Route::apiResource('documents', AdminDocumentController::class)->only(['index', 'show', 'store']);
    Route::post('/documents/{document}/approve', [AdminDocumentController::class, 'approve']);
    Route::post('/documents/{document}/issue', [AdminDocumentController::class, 'issue']);
    Route::get('/documents/{document}/pdf', [AdminDocumentController::class, 'pdf']);

    // Reports
    Route::get('/reports/options', [AdminReportController::class, 'options']);
    Route::get('/reports/inventory-summary', [AdminReportController::class, 'inventorySummary']);
    Route::get('/reports/supply-issuance', [AdminReportController::class, 'supplyIssuance']);
    Route::get('/reports/document-register', [AdminReportController::class, 'documentRegister']);
});
