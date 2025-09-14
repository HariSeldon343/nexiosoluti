<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AuditLogController;

/*
|--------------------------------------------------------------------------
| Route API
|--------------------------------------------------------------------------
|
| Tutte le route API sono prefissate con /api e utilizzano il middleware tenant
| Versioning: /api/v1
|
*/

// Route pubbliche (senza autenticazione)
Route::prefix('v1')->group(function () {

    // Autenticazione
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
    });
});

// Route autenticate con tenant
Route::prefix('v1')->middleware(['auth:api', 'tenant'])->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);

        // 2FA
        Route::post('2fa/enable', [AuthController::class, 'enable2FA']);
        Route::post('2fa/disable', [AuthController::class, 'disable2FA']);
        Route::post('2fa/verify', [AuthController::class, 'verify2FA']);
    });

    // Tenant Management
    Route::prefix('tenants')->group(function () {
        Route::get('/', [TenantController::class, 'index'])->middleware('role:super-admin');
        Route::get('current', [TenantController::class, 'show']);
        Route::get('{id}', [TenantController::class, 'show']);
        Route::put('{id}', [TenantController::class, 'update']);
        Route::put('{id}/theme', [TenantController::class, 'updateTheme']);
        Route::post('switch', [TenantController::class, 'switchTenant']);
    });

    // Companies (Aziende)
    Route::prefix('companies')->group(function () {
        Route::get('/', [CompanyController::class, 'index']);
        Route::post('/', [CompanyController::class, 'store']);
        Route::get('{id}', [CompanyController::class, 'show']);
        Route::put('{id}', [CompanyController::class, 'update']);
        Route::delete('{id}', [CompanyController::class, 'destroy']);
        Route::post('{id}/users', [CompanyController::class, 'assignUsers']);
        Route::put('{id}/custom-fields', [CompanyController::class, 'customFields']);
    });

    // Users (Utenti)
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('{id}', [UserController::class, 'show']);
        Route::put('{id}', [UserController::class, 'update']);
        Route::delete('{id}', [UserController::class, 'destroy']);
        Route::post('{id}/role', [UserController::class, 'assignRole']);
        Route::post('{id}/companies', [UserController::class, 'assignCompanies']);
        Route::put('{id}/multi-tenant', [UserController::class, 'toggleMultiTenant']);
    });

    // Calendar & Events
    Route::prefix('calendar')->group(function () {
        Route::get('/', [CalendarController::class, 'index']);
        Route::get('events', [CalendarController::class, 'events']);
        Route::post('events', [CalendarController::class, 'createEvent']);
        Route::put('events/{id}', [CalendarController::class, 'updateEvent']);
        Route::delete('events/{id}', [CalendarController::class, 'deleteEvent']);
        Route::post('events/{id}/attendees', [CalendarController::class, 'inviteAttendees']);
        Route::post('sync-caldav', [CalendarController::class, 'syncCalDAV']);
    });

    // Tasks (Attività)
    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskController::class, 'index']);
        Route::post('/', [TaskController::class, 'store']);
        Route::get('{id}', [TaskController::class, 'show']);
        Route::put('{id}', [TaskController::class, 'update']);
        Route::delete('{id}', [TaskController::class, 'destroy']);
        Route::post('{id}/users', [TaskController::class, 'assignUsers']);
        Route::put('{id}/occurrences', [TaskController::class, 'setOccurrences']);
        Route::put('{id}/progress', [TaskController::class, 'updateProgress']);
    });

    // Files & Folders
    Route::prefix('files')->group(function () {
        Route::get('/', [FileController::class, 'index']);
        Route::post('upload', [FileController::class, 'upload']);
        Route::get('{id}/download', [FileController::class, 'download']);
        Route::get('{id}/preview', [FileController::class, 'preview']);
        Route::post('folder', [FileController::class, 'createFolder']);
        Route::post('move', [FileController::class, 'move']);
        Route::put('{id}/rename', [FileController::class, 'rename']);
        Route::delete('/', [FileController::class, 'delete']);
        Route::post('{id}/share', [FileController::class, 'share']);
        Route::get('{id}/versions', [FileController::class, 'versions']);
        Route::post('{id}/approve', [FileController::class, 'approve']);
        Route::post('{id}/reject', [FileController::class, 'reject']);
    });

    // Chat
    Route::prefix('chat')->group(function () {
        Route::get('rooms', [ChatController::class, 'rooms']);
        Route::get('rooms/{roomId}/messages', [ChatController::class, 'messages']);
        Route::post('rooms', [ChatController::class, 'createRoom']);
        Route::post('send', [ChatController::class, 'sendMessage']);
        Route::post('rooms/{roomId}/read', [ChatController::class, 'markAsRead']);
        Route::post('rooms/{roomId}/typing', [ChatController::class, 'typing']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
        Route::post('subscribe', [NotificationController::class, 'subscribe']);
        Route::post('unsubscribe', [NotificationController::class, 'unsubscribe']);
        Route::get('settings', [NotificationController::class, 'settings']);
        Route::put('settings', [NotificationController::class, 'settings']);
        Route::post('test', [NotificationController::class, 'test']);
    });

    // Audit Logs
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('export', [AuditLogController::class, 'export']);
        Route::delete('/', [AuditLogController::class, 'deleteLogs'])->middleware('role:super-admin');
        Route::get('statistics', [AuditLogController::class, 'statistics']);
    });
});