<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConcernController;
use App\Http\Controllers\DeanController;
use App\Http\Controllers\DeptStaffController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| SCMS API routes: Public auth, protected role-based endpoints.
|
*/

// ── Public Routes ──────────────────────────────────────────────────────

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::get('/academic-units', [AdminController::class, 'getUnits']);
Route::get('/departments', [AdminController::class, 'getDepartments']);

// ── Protected Routes (auth:sanctum) ────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Auth & Profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // ── Admin Routes ───────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::post('/create-account', [AdminController::class, 'createAccount']);
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::put('/users/{id}', [AdminController::class, 'updateAccount']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteAccount']);
    });

    // ── Student Routes ─────────────────────────────────────────────────
    Route::middleware('role:student')->group(function () {
        Route::post('/concerns', [ConcernController::class, 'store']);
        Route::get('/concerns', [ConcernController::class, 'index']);
        Route::get('/concerns/{id}', [ConcernController::class, 'show']);
        Route::put('/concerns/{id}', [ConcernController::class, 'update']);
        Route::post('/concerns/{id}/messages', [ConcernController::class, 'sendMessage']);
        Route::post('/concerns/{id}/withdraw', [ConcernController::class, 'withdraw']);
        Route::post('/concerns/{id}/reopen', [ConcernController::class, 'reopen']);
    });

    // ── Dept Staff Routes ──────────────────────────────────────────────
    Route::middleware('role:dept_staff')->prefix('dept-staff')->group(function () {
        Route::get('/queue', [DeptStaffController::class, 'queue']);
        Route::post('/concerns/{id}/reply', [DeptStaffController::class, 'reply']);
        Route::post('/concerns/{id}/transfer', [DeptStaffController::class, 'transfer']);
        Route::post('/concerns/{id}/resolve', [DeptStaffController::class, 'resolve']);
        Route::post('/concerns/{id}/status', [DeptStaffController::class, 'updateStatus']);
        Route::post('/concerns/{id}/escalate', [DeptStaffController::class, 'escalate']);
        Route::get('/concerns/{id}/messages', [DeptStaffController::class, 'getMessages']);
    });

    // ── Dean Routes ────────────────────────────────────────────────────
    Route::middleware('role:dean')->prefix('dean')->group(function () {
        Route::get('/inbox', [DeanController::class, 'inbox']);
        Route::post('/concerns/{id}/approve', [DeanController::class, 'approve']);
        Route::post('/concerns/{id}/reject', [DeanController::class, 'reject']);
        Route::post('/concerns/{id}/resolve', [DeanController::class, 'resolve']);
        Route::post('/concerns/{id}/reply', [DeanController::class, 'reply']);
        Route::get('/concerns/{id}/messages', [DeanController::class, 'getMessages']);
    });

    // ── Analytics Routes (Admin & Dean) ────────────────────────────────
    Route::middleware('role:admin,dean')->prefix('analytics')->group(function () {
        Route::get('/sentiment', [\App\Http\Controllers\AnalyticsController::class, 'getSentiment']);
        Route::get('/trends', [\App\Http\Controllers\AnalyticsController::class, 'getTrends']);
    });
});
