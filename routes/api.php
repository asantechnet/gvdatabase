<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\WorthyEmployeeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerServiceController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\PermissionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/account', [AuthController::class, 'updateAccount']);
        Route::post('/account/avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('/account/avatar', [AuthController::class, 'removeAvatar']);
        Route::put('/account/password', [AuthController::class, 'updatePassword']);
        Route::put('/account/notifications', [AuthController::class, 'updateNotifications']);
        Route::put('/account/connections', [AuthController::class, 'updateConnections']);
        Route::post('/account/two-factor', [AuthController::class, 'beginTwoFactor']);
        Route::post('/account/two-factor/confirm', [AuthController::class, 'confirmTwoFactor']);
        Route::delete('/account/two-factor', [AuthController::class, 'disableTwoFactor']);
        Route::post('/account/api-keys', [AuthController::class, 'createApiKey']);
        Route::delete('/account/api-keys/{tokenId}', [AuthController::class, 'revokeApiKey']);
        Route::post('/account/logout-other-devices', [AuthController::class, 'logoutOtherDevices']);
        Route::post('/account/deactivate', [AuthController::class, 'deactivate']);
    });

    // 1. Fetch entire Kanban state (Boards with nested tasks, assignees, tags, etc.)
    Route::get('/kanban', [KanbanController::class, 'index']);

    // 2. Board Management (Columns)
    Route::post('/boards', [BoardController::class, 'store']);
    Route::put('/boards/{board}', [BoardController::class, 'update']); // Rename board
    Route::delete('/boards/{board}', [BoardController::class, 'destroy']);

    // 3. Task Management (Cards)
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    // 4. Task action items (Todos)
    Route::post('/tasks/{task}/subtasks', [SubtaskController::class, 'store']);
    Route::put('/tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'update']);
    Route::delete('/tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'destroy']);

    // 5. Specific endpoint for Drag & Drop to optimize database performance
    Route::put('/tasks/{task}/move', [TaskController::class, 'move']);

    // 6. Task Comments (Chat drawer)
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store']);

    // Permission routes
    Route::apiResource('permissions', PermissionController::class);

    // User routes
    Route::apiResource('users', UserController::class);

    Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);

    // Employee routes
    Route::apiResource('employees', EmployeeController::class);

    // Position routes
    Route::apiResource('positions', PositionController::class);

    // Bonus nominations and grants
    Route::apiResource('worthy-employees', WorthyEmployeeController::class);

    // Employee Salary routes
    Route::get('salaries/granted-bonuses', [SalaryController::class, 'grantedBonuses']);
    Route::apiResource('salaries', SalaryController::class);

    // Invoice routes
    Route::apiResource('invoices', InvoiceController::class);
    Route::put('/invoices/{invoice}/void', [InvoiceController::class, 'void']);

    // Expense routes (new correct spelling)
    Route::apiResource('expenses', ExpenseController::class);

    // Income routes
    Route::apiResource('incomes', IncomeController::class);

    // Visitor routes
    Route::apiResource('visitors', VisitorController::class);

    // Tag routes
    Route::apiResource('tags', TagController::class);

    // Customer routes
    Route::apiResource('customers', CustomerController::class);
    Route::post('/customers/{customer}/services', [CustomerServiceController::class, 'store']);
    Route::put('/customers/{customer}/services/{customerService}', [CustomerServiceController::class, 'update']);
    Route::delete('/customers/{customer}/services/{customerService}', [CustomerServiceController::class, 'destroy']);

    // Timesheet routes
    Route::prefix('attendances')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/', [AttendanceController::class, 'store']);
        Route::get('/unmarked-staff', [AttendanceController::class, 'unmarkedStaff']);
        Route::delete('/{attendance}', [AttendanceController::class, 'destroy']);
    });

    // Service routes
    Route::apiResource('services', ServiceController::class);

    // Currency routes
    Route::apiResource('currencies', CurrencyController::class);

    // Setting routes
    Route::apiResource('settings', SettingController::class);

    // Contract Template routes (replaces old policies)
    Route::apiResource('contract-templates', ContractTemplateController::class);

    // Contract routes
    Route::apiResource('contracts', ContractController::class);
    Route::put('/contracts/{id}/cancel', [ContractController::class, 'cancel']);
    Route::get('/contracts/{contract}/download', [ContractController::class, 'downloadContractFile']);

    // Notification routes
    Route::patch('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::apiResource('notifications', NotificationController::class);

    // Stock routes
    Route::apiResource('stocks', StockController::class);

    // Dashboard routes
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/chart-data', [DashboardController::class, 'chartData']);
    });

    // Report routes
    Route::prefix('reports')->group(function () {
        Route::get('/invoices', [ReportController::class, 'generateInvoiceReport']);
        Route::get('/expences', [ReportController::class, 'generateExpenceReport']);
        Route::get('/expenses', [ReportController::class, 'generateExpenseReport']);
        Route::get('/salaries', [ReportController::class, 'generateSalaryReport']);
        Route::get('/incomes', [ReportController::class, 'generateIncomeReport']);
        Route::get('/visitors', [ReportController::class, 'generateVisitorReport']);
        Route::get('/invoices/export', [ReportController::class, 'exportInvoiceReport']);
    });

    // File management routes
    Route::prefix('files')->group(function () {
        Route::post('/upload', [FileController::class, 'upload']);
        Route::get('/download/{filePath}', [FileController::class, 'download']);
        Route::delete('/delete', [FileController::class, 'delete']);
        Route::get('/list', [FileController::class, 'listFiles']);
        Route::get('/view/{filePath}', [FileController::class, 'viewFile']);
    });
});
