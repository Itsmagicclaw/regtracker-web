<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\ChangeController;
use App\Http\Controllers\Api\Admin\FatfController;
use App\Http\Controllers\Api\Admin\HealthController;
use App\Http\Controllers\Api\Admin\MtoController;
use App\Http\Controllers\Api\Mto\ActionController;
use App\Http\Controllers\Api\Mto\AlertController;
use App\Http\Controllers\Api\Mto\ComplianceLogController;
use App\Http\Controllers\Api\Mto\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RegTracker API Routes
|--------------------------------------------------------------------------
*/

// ── Public ────────────────────────────────────────────────────────────────────
Route::post('/auth/login',  [AuthController::class, 'login']);
Route::get('/health',       fn() => response()->json(['status' => 'ok', 'app' => 'RegTracker']));

// ── Admin routes (Bearer token = ADMIN_SECRET) ────────────────────────────────
Route::prefix('admin')->middleware('admin')->group(function () {

    // MTO Profiles
    Route::get   ('mto',        [MtoController::class, 'index']);
    Route::post  ('mto',        [MtoController::class, 'store']);
    Route::get   ('mto/{id}',   [MtoController::class, 'show']);
    Route::put   ('mto/{id}',   [MtoController::class, 'update']);
    Route::delete('mto/{id}',   [MtoController::class, 'destroy']);

    // Regulatory Changes / QA Queue
    Route::get ('changes',                          [ChangeController::class, 'index']);
    Route::post('changes/{id}/approve',             [ChangeController::class, 'approve']);
    Route::post('changes/{id}/dismiss',             [ChangeController::class, 'dismiss']);
    Route::get ('changes/preview/{mtoId}/{changeId}', [ChangeController::class, 'previewAlert']);

    // Scraper Health
    Route::get('health',         [HealthController::class, 'index']);
    Route::get('health/{id}',    [HealthController::class, 'show']);

    // FATF Manual Updates
    Route::get ('fatf',          [FatfController::class, 'index']);
    Route::post('fatf',          [FatfController::class, 'update']);
});

// ── MTO Owner routes (Sanctum token) ─────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Alerts
    Route::get('/alerts',      [AlertController::class, 'index']);
    Route::get('/alerts/{id}', [AlertController::class, 'show']);

    // Action items
    Route::put('/actions/{id}/status', [ActionController::class, 'updateStatus']);

    // Compliance log + export
    Route::get('/compliance-log',        [ComplianceLogController::class, 'index']);
    Route::get('/compliance-log/export', [ComplianceLogController::class, 'export']);
});
