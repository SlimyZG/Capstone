<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConcernController;
use App\Http\Controllers\DeanController;
use App\Http\Controllers\DeptStaffController;
use App\Http\Controllers\InterdepartmentalCoordinationController;
use App\Http\Controllers\RecurringIncidentController;
use App\Http\Controllers\SaoSelfServiceController;
use App\Http\Controllers\StudentSelfServiceController;
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

    // In-App Notifications (All authenticated roles)
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead']);

    // Authenticated Private Attachment Streaming (Role & Scope Verified in Controller)
    Route::get('/concerns/{id}/attachments/{index}', [ConcernController::class, 'showAttachment'])->whereNumber(['id', 'index']);

    // ── Admin & SAO Account Management Routes ──────────────────────────
    Route::middleware('role:admin,sao')->prefix('admin')->group(function () {
        Route::post('/create-account', [AdminController::class, 'createAccount']);
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::put('/users/{id}', [AdminController::class, 'updateAccount']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteAccount']);
    });

    // ── SAO Dedicated Routes ───────────────────────────────────────────
    Route::middleware('role:sao')->prefix('sao')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\SaoController::class, 'index']);
        Route::get('/users', [\App\Http\Controllers\SaoUserController::class, 'index']);
        Route::post('/users', [\App\Http\Controllers\SaoUserController::class, 'store']);
        Route::put('/users/{id}', [\App\Http\Controllers\SaoUserController::class, 'update']);
        Route::post('/users/{id}/deactivate', [\App\Http\Controllers\SaoUserController::class, 'deactivate']);
        Route::get('/oversight', [\App\Http\Controllers\SaoOversightController::class, 'getOversight']);
        Route::get('/assigned-concerns', [\App\Http\Controllers\SaoOversightController::class, 'assignedConcerns']);
        Route::get('/assigned-concerns/export-csv', [\App\Http\Controllers\SaoOversightController::class, 'exportSaoCsv']);
        Route::get('/reports/export-csv', [\App\Http\Controllers\SaoOversightController::class, 'exportSaoCsv']);
        Route::get('/concerns/{id}', [\App\Http\Controllers\SaoOversightController::class, 'showConcern']);
        Route::get('/concerns/{id}/attachments/{index}', [ConcernController::class, 'showAttachment'])->whereNumber(['id', 'index']);
        Route::get('/concerns/{id}/cad/signed-document', [\App\Http\Controllers\SaoOversightController::class, 'downloadCadSignedDocument']);
        Route::post('/concerns/{id}/priority-override', [\App\Http\Controllers\SaoOversightController::class, 'priorityOverride']);
        Route::post('/concerns/{id}/reply', [\App\Http\Controllers\SaoOversightController::class, 'reply']);

        // Interdepartmental Coordination (SAO under Option A)
        Route::post('/concerns/{id}/transfers', [InterdepartmentalCoordinationController::class, 'initiateTransfer'])->whereNumber('id');
        Route::post('/concerns/{id}/transfers/{transferId}/accept', [InterdepartmentalCoordinationController::class, 'acceptTransfer'])->whereNumber(['id', 'transferId']);
        Route::post('/concerns/{id}/transfers/{transferId}/decline', [InterdepartmentalCoordinationController::class, 'declineTransfer'])->whereNumber(['id', 'transferId']);
        Route::post('/concerns/{id}/transfers/{transferId}/cancel', [InterdepartmentalCoordinationController::class, 'cancelTransfer'])->whereNumber(['id', 'transferId']);
        Route::post('/concerns/{id}/collaborations', [InterdepartmentalCoordinationController::class, 'requestCollaboration'])->whereNumber('id');
        Route::post('/concerns/{id}/collaborations/{collabId}/accept', [InterdepartmentalCoordinationController::class, 'acceptCollaboration'])->whereNumber(['id', 'collabId']);
        Route::post('/concerns/{id}/collaborations/{collabId}/decline', [InterdepartmentalCoordinationController::class, 'declineCollaboration'])->whereNumber(['id', 'collabId']);
        Route::post('/concerns/{id}/collaborations/{collabId}/complete', [InterdepartmentalCoordinationController::class, 'completeCollaboration'])->whereNumber(['id', 'collabId']);
        Route::post('/concerns/{id}/collaborations/{collabId}/revoke', [InterdepartmentalCoordinationController::class, 'revokeCollaboration'])->whereNumber(['id', 'collabId']);
        Route::post('/concerns/{id}/pco-approvals/{requestId}/approve', [InterdepartmentalCoordinationController::class, 'approvePcoRequest'])->whereNumber(['id', 'requestId']);
        Route::post('/concerns/{id}/pco-approvals/{requestId}/reject', [InterdepartmentalCoordinationController::class, 'rejectPcoRequest'])->whereNumber(['id', 'requestId']);
        Route::post('/concerns/{id}/pco-approvals/{requestId}/request-clarification', [InterdepartmentalCoordinationController::class, 'requestPcoClarification'])->whereNumber(['id', 'requestId']);
        Route::get('/concerns/{id}/pco-approvals/{requestId}/attachments/{index}', [InterdepartmentalCoordinationController::class, 'showPcoAttachment'])->whereNumber(['id', 'requestId', 'index']);
        Route::get('/concerns/{id}/internal-messages', [InterdepartmentalCoordinationController::class, 'getInternalMessages'])->whereNumber('id');
        Route::post('/concerns/{id}/internal-messages', [InterdepartmentalCoordinationController::class, 'postInternalMessage'])->whereNumber('id');

        Route::get('/recurring-incidents', [RecurringIncidentController::class, 'saoIndex']);
        Route::get('/recurring-incidents/{id}', [RecurringIncidentController::class, 'saoShow']);
        Route::post('/recurring-incidents/{id}/priority-override', [RecurringIncidentController::class, 'saoPriorityOverride']);
        Route::post('/recurring-incidents/{id}/unlink/{concernId}', [RecurringIncidentController::class, 'saoUnlink']);

        // Student Self-Service Assistance Knowledge Base Governance & Analytics (SAO only)
        Route::get('/self-service/entries', [SaoSelfServiceController::class, 'index']);
        Route::get('/self-service/entries/{id}', [SaoSelfServiceController::class, 'show'])->whereNumber('id');
        Route::put('/self-service/entries/{id}', [SaoSelfServiceController::class, 'update'])->whereNumber('id');
        Route::post('/self-service/entries/{id}/publish', [SaoSelfServiceController::class, 'publish'])->whereNumber('id');
        Route::post('/self-service/entries/{id}/unpublish', [SaoSelfServiceController::class, 'unpublish'])->whereNumber('id');
        Route::post('/self-service/entries/{id}/needs-revision', [SaoSelfServiceController::class, 'markNeedsRevision'])->whereNumber('id');
        Route::get('/self-service/analytics', [SaoSelfServiceController::class, 'analytics']);
    });

    // ── Student Routes ─────────────────────────────────────────────────
    Route::middleware('role:student')->group(function () {
        Route::post('/concerns/recommend-routing', [ConcernController::class, 'recommendRouting']);
        Route::post('/concerns', [ConcernController::class, 'store']);
        Route::get('/concerns', [ConcernController::class, 'index']);
        Route::get('/concerns/{id}', [ConcernController::class, 'show']);
        Route::put('/concerns/{id}', [ConcernController::class, 'update']);
        Route::post('/concerns/{id}/messages', [ConcernController::class, 'sendMessage']);
        Route::post('/concerns/{id}/withdraw', [ConcernController::class, 'withdraw']);
        Route::post('/concerns/{id}/reopen', [ConcernController::class, 'reopen']);

        // Student Self-Service Assistance ("Need Help?" — Separate from formal concern submission)
        Route::get('/self-service/categories', [StudentSelfServiceController::class, 'categories']);
        Route::get('/self-service/entries', [StudentSelfServiceController::class, 'entries']);
        Route::get('/self-service/entries/{idOrCode}', [StudentSelfServiceController::class, 'showEntry']);
        Route::get('/self-service/tasks', [StudentSelfServiceController::class, 'myTasks']);
        Route::post('/self-service/tasks', [StudentSelfServiceController::class, 'startTask']);
        Route::get('/self-service/tasks/{id}', [StudentSelfServiceController::class, 'showTask'])->whereNumber('id');
        Route::put('/self-service/tasks/{id}', [StudentSelfServiceController::class, 'updateTask'])->whereNumber('id');
        Route::post('/self-service/tasks/{id}/restart', [StudentSelfServiceController::class, 'restartTaskWithLatestVersion'])->whereNumber('id');
        Route::post('/self-service/feedback', [StudentSelfServiceController::class, 'submitFeedback']);
    });

    // ── Dept Staff Routes ──────────────────────────────────────────────
    Route::middleware('role:dept_staff')->prefix('dept-staff')->group(function () {
        Route::get('/queue', [DeptStaffController::class, 'queue']);
        Route::get('/concerns/{id}', [DeptStaffController::class, 'show']);
        Route::get('/concerns/{id}/attachments/{index}', [ConcernController::class, 'showAttachment'])->whereNumber(['id', 'index']);
        Route::post('/concerns/{id}/reply', [DeptStaffController::class, 'reply']);
        Route::post('/concerns/{id}/transfer', [DeptStaffController::class, 'transfer']);
        Route::post('/concerns/{id}/resolve', [DeptStaffController::class, 'resolve']);
        Route::post('/concerns/{id}/status', [DeptStaffController::class, 'updateStatus']);
        Route::post('/concerns/{id}/escalate', [DeptStaffController::class, 'escalate']);
        Route::post('/concerns/{id}/forward-cad', [DeptStaffController::class, 'forwardToCad']);
        Route::post('/concerns/{id}/cad/initiate', [DeptStaffController::class, 'generateCadDocument']);
        Route::post('/concerns/{id}/cad/generate-document', [DeptStaffController::class, 'generateCadDocument']);
        Route::get('/concerns/{id}/cad/document', [DeptStaffController::class, 'getCadDocument']);
        Route::post('/concerns/{id}/cad/record-submission', [DeptStaffController::class, 'recordCadSubmission']);
        Route::post('/concerns/{id}/cad/upload-decision', [DeptStaffController::class, 'uploadCadDecision']);
        Route::get('/concerns/{id}/cad/signed-document', [DeptStaffController::class, 'downloadCadSignedDocument']);
        Route::get('/concerns/{id}/messages', [DeptStaffController::class, 'getMessages']);

        // Interdepartmental Coordination: Unlimited Handshake Transfers, Collaboration, PCO Approval, & Internal Channel
        Route::post('/concerns/{id}/transfers', [InterdepartmentalCoordinationController::class, 'initiateTransfer'])->whereNumber('id');
        Route::post('/concerns/{id}/transfers/{transferId}/accept', [InterdepartmentalCoordinationController::class, 'acceptTransfer'])->whereNumber(['id', 'transferId']);
        Route::post('/concerns/{id}/transfers/{transferId}/decline', [InterdepartmentalCoordinationController::class, 'declineTransfer'])->whereNumber(['id', 'transferId']);
        Route::post('/concerns/{id}/transfers/{transferId}/cancel', [InterdepartmentalCoordinationController::class, 'cancelTransfer'])->whereNumber(['id', 'transferId']);
        Route::post('/concerns/{id}/collaborations', [InterdepartmentalCoordinationController::class, 'requestCollaboration'])->whereNumber('id');
        Route::post('/concerns/{id}/collaborations/{collabId}/accept', [InterdepartmentalCoordinationController::class, 'acceptCollaboration'])->whereNumber(['id', 'collabId']);
        Route::post('/concerns/{id}/collaborations/{collabId}/decline', [InterdepartmentalCoordinationController::class, 'declineCollaboration'])->whereNumber(['id', 'collabId']);
        Route::post('/concerns/{id}/collaborations/{collabId}/complete', [InterdepartmentalCoordinationController::class, 'completeCollaboration'])->whereNumber(['id', 'collabId']);
        Route::post('/concerns/{id}/collaborations/{collabId}/revoke', [InterdepartmentalCoordinationController::class, 'revokeCollaboration'])->whereNumber(['id', 'collabId']);
        Route::post('/concerns/{id}/pco-approvals', [InterdepartmentalCoordinationController::class, 'submitPcoApprovalRequest'])->whereNumber('id');
        Route::post('/concerns/{id}/pco-approvals/{requestId}/approve', [InterdepartmentalCoordinationController::class, 'approvePcoRequest'])->whereNumber(['id', 'requestId']);
        Route::post('/concerns/{id}/pco-approvals/{requestId}/reject', [InterdepartmentalCoordinationController::class, 'rejectPcoRequest'])->whereNumber(['id', 'requestId']);
        Route::post('/concerns/{id}/pco-approvals/{requestId}/request-clarification', [InterdepartmentalCoordinationController::class, 'requestPcoClarification'])->whereNumber(['id', 'requestId']);
        Route::post('/concerns/{id}/pco-approvals/{requestId}/provide-clarification', [InterdepartmentalCoordinationController::class, 'providePcoClarification'])->whereNumber(['id', 'requestId']);
        Route::post('/concerns/{id}/pco-approvals/{requestId}/cancel', [InterdepartmentalCoordinationController::class, 'cancelPcoRequest'])->whereNumber(['id', 'requestId']);
        Route::get('/concerns/{id}/pco-approvals/{requestId}/attachments/{index}', [InterdepartmentalCoordinationController::class, 'showPcoAttachment'])->whereNumber(['id', 'requestId', 'index']);
        Route::get('/concerns/{id}/internal-messages', [InterdepartmentalCoordinationController::class, 'getInternalMessages'])->whereNumber('id');
        Route::post('/concerns/{id}/internal-messages', [InterdepartmentalCoordinationController::class, 'postInternalMessage'])->whereNumber('id');

        // Reports
        Route::get('/reports', [DeptStaffController::class, 'getReport']);
        Route::get('/reports/export-csv', [DeptStaffController::class, 'exportCsv']);
        Route::get('/recurring-incidents', [RecurringIncidentController::class, 'deptStaffIndex']);
        Route::get('/recurring-incidents/{id}', [RecurringIncidentController::class, 'deptStaffShow']);
        Route::post('/recurring-incidents/{id}/priority-override', [RecurringIncidentController::class, 'deptStaffPriorityOverride']);
        Route::post('/recurring-incidents/{id}/unlink/{concernId}', [RecurringIncidentController::class, 'deptStaffUnlink']);
    });

    // ── Dean Routes ────────────────────────────────────────────────────
    Route::middleware('role:dean')->prefix('dean')->group(function () {
        Route::get('/inbox', [DeanController::class, 'inbox']);
        Route::get('/concerns/{id}', [DeanController::class, 'show']);
        Route::get('/concerns/{id}/attachments/{index}', [ConcernController::class, 'showAttachment'])->whereNumber(['id', 'index']);
        Route::post('/concerns/{id}/approve', [DeanController::class, 'approve']);
        Route::post('/concerns/{id}/return', [DeanController::class, 'returnForAction']);
        Route::post('/concerns/{id}/reject', [DeanController::class, 'reject']);
        Route::post('/concerns/{id}/resolve', [DeanController::class, 'resolve']);
        Route::post('/concerns/{id}/reply', [DeanController::class, 'reply']);
        Route::post('/concerns/{id}/priority-override', [DeanController::class, 'priorityOverride']);
        Route::get('/concerns/{id}/messages', [DeanController::class, 'getMessages']);

        // PCO Department Approval & Internal Messages when Academic Unit is configured as an eligible approving unit
        Route::post('/concerns/{id}/pco-approvals/{requestId}/approve', [InterdepartmentalCoordinationController::class, 'approvePcoRequest'])->whereNumber(['id', 'requestId']);
        Route::post('/concerns/{id}/pco-approvals/{requestId}/reject', [InterdepartmentalCoordinationController::class, 'rejectPcoRequest'])->whereNumber(['id', 'requestId']);
        Route::post('/concerns/{id}/pco-approvals/{requestId}/request-clarification', [InterdepartmentalCoordinationController::class, 'requestPcoClarification'])->whereNumber(['id', 'requestId']);
        Route::get('/concerns/{id}/pco-approvals/{requestId}/attachments/{index}', [InterdepartmentalCoordinationController::class, 'showPcoAttachment'])->whereNumber(['id', 'requestId', 'index']);
        Route::get('/concerns/{id}/internal-messages', [InterdepartmentalCoordinationController::class, 'getInternalMessages'])->whereNumber('id');
        Route::post('/concerns/{id}/internal-messages', [InterdepartmentalCoordinationController::class, 'postInternalMessage'])->whereNumber('id');
        
        // Class List Management
        Route::get('/class-list', [\App\Http\Controllers\DeanClassListController::class, 'index']);
        Route::post('/class-list', [\App\Http\Controllers\DeanClassListController::class, 'store']);
        Route::post('/class-list/upload', [\App\Http\Controllers\DeanClassListController::class, 'upload']);

        // Reports
        Route::get('/reports', [\App\Http\Controllers\AnalyticsController::class, 'getDeanReport']);
        Route::get('/reports/export-csv', [\App\Http\Controllers\AnalyticsController::class, 'exportDeanCsv']);

        // Recurring Incidents
        Route::get('/recurring-incidents', [RecurringIncidentController::class, 'deanIndex']);
        Route::get('/recurring-incidents/{id}', [RecurringIncidentController::class, 'deanShow']);
        Route::post('/recurring-incidents/{id}/priority-override', [RecurringIncidentController::class, 'deanPriorityOverride']);
        Route::post('/recurring-incidents/{id}/unlink/{concernId}', [RecurringIncidentController::class, 'deanUnlink']);
    });

    // ── Analytics Routes (Admin, Dean, SAO, Dept Staff) ──────────────
    Route::middleware('role:admin,dean,sao,dept_staff')->prefix('analytics')->group(function () {
        Route::get('/sentiment', [\App\Http\Controllers\AnalyticsController::class, 'getSentiment']);
        Route::get('/trends', [\App\Http\Controllers\AnalyticsController::class, 'getTrends']);
    });
});
