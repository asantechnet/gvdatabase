<?php

use App\Http\Controllers\AccountDeactivationRequestController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceGeofenceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuditResourceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractPolicyController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\ContractTypeController;
use App\Http\Controllers\CrmInteractionController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerServiceController;
use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\EducationalMasterDataController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MarketerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OutsourcingController;
use App\Http\Controllers\PaidTimeOffController;
use App\Http\Controllers\PaymentDetailController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RecurringEventController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SalaryDeductionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskAssigneeController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\WorkdayController;
use App\Http\Controllers\WorthyEmployeeController;
use Illuminate\Support\Facades\Route;

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
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::post('/complete-invitation', [AuthController::class, 'completeInvitation'])->middleware('throttle:5,1');
    Route::post('/refresh-token', [AuthController::class, 'refreshToken'])->middleware('throttle:10,1');
    Route::post('/email/verification-notification', [AuthController::class, 'resendEmailVerification'])->middleware('throttle:5,1');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
});

// Protected routes (require authentication)
Route::middleware(['auth:sanctum', 'abilities:access', 'active'])->group(function () {
    Route::prefix('workday')->group(function () {
        Route::get('/status', [WorkdayController::class, 'status'])->middleware('permission:manage_own_workday');
        Route::post('/start', [WorkdayController::class, 'start'])->middleware(['permission:manage_own_workday', 'throttle:10,1']);
        Route::post('/end', [WorkdayController::class, 'end'])->middleware(['permission:submit_own_daily_report', 'throttle:10,1']);
        Route::get('/geofence', [AttendanceGeofenceController::class, 'show'])->middleware('permission:manage_own_workday');
        Route::post('/geofence', [AttendanceGeofenceController::class, 'observe'])->middleware(['permission:manage_own_workday', 'throttle:30,1']);
    });
    Route::get('my-work-history', [AttendanceController::class, 'mine']);

    // Any signed-in user can request help; delivery is rate-limited separately
    // because every ticket also creates an outbound email.
    Route::get('support-tickets', [SupportTicketController::class, 'index']);
    Route::post('support-tickets', [SupportTicketController::class, 'store'])->middleware('throttle:5,10');
    Route::get('support-tickets/{supportTicket}/attachments/{attachment}/download', [SupportTicketController::class, 'download']);

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/account', [AuthController::class, 'updateAccount']);
        Route::post('/account/avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('/account/avatar', [AuthController::class, 'removeAvatar']);
        Route::put('/account/password', [AuthController::class, 'updatePassword']);
        Route::put('/account/notifications', [AuthController::class, 'updateNotifications']);
        Route::post('/account/two-factor', [AuthController::class, 'beginTwoFactor']);
        Route::post('/account/two-factor/confirm', [AuthController::class, 'confirmTwoFactor']);
        Route::delete('/account/two-factor', [AuthController::class, 'disableTwoFactor']);
        Route::post('/account/logout-other-devices', [AuthController::class, 'logoutOtherDevices']);
        Route::get('/account/deactivation-requests', [AccountDeactivationRequestController::class, 'mine']);
        Route::post('/account/deactivation-requests', [AccountDeactivationRequestController::class, 'store'])
            ->middleware(['permission:request_account_deactivation', 'throttle:5,1']);
        Route::delete('/account/deactivation-requests/{accountDeactivationRequest}', [AccountDeactivationRequestController::class, 'cancel'])
            ->middleware('permission:cancel_own_deactivation_request');
    });

    // 1. Fetch entire Kanban state (Boards with nested tasks, assignees, tags, etc.)
    Route::get('/kanban', [KanbanController::class, 'index'])->middleware('permission:view_all_boards,view_assigned_boards');

    // 2. Board Management (Columns)
    Route::get('/boards', [BoardController::class, 'index'])->middleware('permission:view_all_boards,view_assigned_boards');
    Route::apiResource('projects', ProjectController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_projects')
        ->middlewareFor('store', 'permission:create_projects')
        ->middlewareFor('update', 'permission:edit_projects')
        ->middlewareFor('destroy', 'permission:delete_projects');
    Route::post('/boards', [BoardController::class, 'store']);
    Route::put('/boards/reorder', [BoardController::class, 'reorder']);
    Route::put('/boards/{board}/members', [BoardController::class, 'syncMembers']);
    Route::put('/boards/{board}', [BoardController::class, 'update']); // Rename board
    Route::delete('/boards/{board}', [BoardController::class, 'destroy']);

    // 3. Task Management (Cards)
    Route::get('/task-assignees', [TaskAssigneeController::class, 'index'])->middleware('permission:assign_task_members');
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::patch('/tasks/{task}/bonus-review', [TaskController::class, 'reviewBonusInputs'])->middleware('permission:edit_all_tasks');
    Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'store']);
    Route::get('/tasks/{task}/attachments/{attachment}/download', [TaskAttachmentController::class, 'download']);
    Route::delete('/tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy']);

    // 4. Task action items (Todos)
    Route::post('/tasks/{task}/subtasks', [SubtaskController::class, 'store']);
    Route::put('/tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'update']);
    Route::delete('/tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'destroy']);

    // 5. Specific endpoint for Drag & Drop to optimize database performance
    Route::put('/tasks/{task}/move', [TaskController::class, 'move']);

    // 6. Task Comments (Chat drawer)
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store']);
    Route::put('/tasks/{task}/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/tasks/{task}/comments/{comment}', [CommentController::class, 'destroy']);

    // Role and permission management
    Route::apiResource('roles', RoleController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_roles')
        ->middlewareFor('store', 'permission:create_roles')
        ->middlewareFor('update', 'permission:edit_roles')
        ->middlewareFor('destroy', 'permission:delete_roles');
    Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
        ->middleware('permission:manage_role_permissions');

    // Permission catalog is system-owned and read-only; role grants are the editable part.
    Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:view_permissions');

    // User routes
    Route::apiResource('users', UserController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_users')
        ->middlewareFor('store', 'permission:create_users')
        ->middlewareFor('update', 'permission:edit_users')
        ->middlewareFor('destroy', 'permission:delete_users');

    Route::put('/users/{user}/status', [UserController::class, 'updateStatus'])->middleware('permission:manage_user_status');
    Route::get('/account-deactivation-requests', [AccountDeactivationRequestController::class, 'index'])
        ->middleware('permission:view_account_deactivation_requests');
    Route::patch('/account-deactivation-requests/{accountDeactivationRequest}/review', [AccountDeactivationRequestController::class, 'review'])
        ->middleware('permission:review_account_deactivation_requests');
    Route::post('/users/{user}/reactivate', [AccountDeactivationRequestController::class, 'reactivate'])
        ->middleware('permission:reactivate_user_accounts');

    // Employee routes
    Route::get('paid-time-offs', [PaidTimeOffController::class, 'all'])->middleware('permission:view_paid_time_off');
    Route::get('my-paid-time-offs', [PaidTimeOffController::class, 'mine'])->middleware('permission:request_own_paid_time_off');
    Route::post('my-paid-time-offs', [PaidTimeOffController::class, 'requestRange'])->middleware('permission:request_own_paid_time_off');
    Route::patch('paid-time-offs/grant', [PaidTimeOffController::class, 'grant'])->middleware('permission:grant_paid_time_off');
    Route::get('employees/{employee}/paid-time-offs', [PaidTimeOffController::class, 'index'])->middleware('permission:view_paid_time_off');
    Route::post('employees/{employee}/paid-time-offs', [PaidTimeOffController::class, 'store'])->middleware('permission:create_paid_time_off');
    Route::put('employees/{employee}/paid-time-offs/{paidTimeOff}', [PaidTimeOffController::class, 'update'])->middleware('permission:edit_paid_time_off');
    Route::delete('employees/{employee}/paid-time-offs/{paidTimeOff}', [PaidTimeOffController::class, 'destroy'])->middleware('permission:delete_paid_time_off');
    // Canonical leave API. Legacy paid-time-off URLs above remain during the
    // compatibility window for older mobile clients.
    Route::get('leaves', [PaidTimeOffController::class, 'all'])->middleware('permission:view_paid_time_off');
    Route::get('my-leaves', [PaidTimeOffController::class, 'mine'])->middleware('permission:request_own_paid_time_off');
    Route::post('my-leaves', [PaidTimeOffController::class, 'requestRange'])->middleware('permission:request_own_paid_time_off');
    Route::patch('leaves/grant', [PaidTimeOffController::class, 'grant'])->middleware('permission:grant_paid_time_off');
    Route::get('employees/{employee}/leaves', [PaidTimeOffController::class, 'index'])->middleware('permission:view_paid_time_off');
    Route::post('employees/{employee}/leaves', [PaidTimeOffController::class, 'store'])->middleware('permission:create_paid_time_off');
    Route::put('employees/{employee}/leaves/{paidTimeOff}', [PaidTimeOffController::class, 'update'])->middleware('permission:edit_paid_time_off');
    Route::delete('employees/{employee}/leaves/{paidTimeOff}', [PaidTimeOffController::class, 'destroy'])->middleware('permission:delete_paid_time_off');
    Route::get('employees/account-options', [EmployeeController::class, 'accountOptions'])->middleware('permission:view_employees');
    Route::get('employees/commission-options', [EmployeeController::class, 'commissionOptions'])->middleware('permission:view_employees');
    Route::get('employees/marketer-options', [EmployeeController::class, 'marketerOptions'])->middleware('permission:view_employees');
    Route::apiResource('employees', EmployeeController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_employees')
        ->middlewareFor('store', 'permission:create_employees')
        ->middlewareFor('update', 'permission:edit_employees')
        ->middlewareFor('destroy', 'permission:delete_employees');

    // Position routes
    Route::apiResource('positions', PositionController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_positions')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_positions');
    Route::get('marketers/account-options', [MarketerController::class, 'accountOptions'])->middleware('permission:view_marketers');
    Route::apiResource('marketers', MarketerController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_marketers')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_marketers');
    Route::apiResource('offices', OfficeController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_offices')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_offices');

    // Bonus nominations and grants
    Route::apiResource('worthy-employees', WorthyEmployeeController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_bonuses')
        ->middlewareFor('store', 'permission:nominate_bonus_candidates')
        ->middlewareFor('update', 'permission:edit_bonus_nominations,grant_bonuses')
        ->middlewareFor('destroy', 'permission:delete_bonus_nominations');

    // Employee Salary routes
    Route::get('salaries/granted-bonuses', [SalaryController::class, 'grantedBonuses'])->middleware('permission:view_bonuses');
    Route::get('salaries/{salary}/deductions', [SalaryDeductionController::class, 'index'])->middleware('permission:view_salary_deductions');
    Route::post('salaries/{salary}/deductions', [SalaryDeductionController::class, 'store'])->middleware('permission:create_salary_deductions');
    Route::patch('salaries/{salary}/deductions/{deduction}', [SalaryDeductionController::class, 'update'])->middleware('permission:edit_salary_deductions');
    Route::delete('salaries/{salary}/deductions/{deduction}', [SalaryDeductionController::class, 'destroy'])->middleware('permission:delete_salary_deductions');
    Route::apiResource('salaries', SalaryController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_salaries')
        ->middlewareFor('store', 'permission:create_salaries')
        ->middlewareFor('update', 'permission:edit_salaries')
        ->middlewareFor('destroy', 'permission:delete_salaries');

    Route::get('commissions/accruals', [CommissionController::class, 'accruals'])->middleware('permission:view_commissions');
    Route::get('commissions/summary', [CommissionController::class, 'summary'])->middleware('permission:view_commissions');
    Route::post('commissions/adjustments', [CommissionController::class, 'storeAdjustment'])->middleware('permission:manage_commission_settlements');
    Route::get('commission-settlements', [CommissionController::class, 'settlements'])->middleware('permission:view_commissions');
    Route::post('commission-settlements', [CommissionController::class, 'storeSettlement'])->middleware('permission:manage_commission_settlements');
    Route::get('commission-settlements/{commissionSettlement}', [CommissionController::class, 'show'])->middleware('permission:view_commissions');
    Route::post('commission-settlements/{commissionSettlement}/approve', [CommissionController::class, 'approve'])->middleware('permission:approve_commissions');
    Route::post('commission-settlements/{commissionSettlement}/pay', [CommissionController::class, 'pay'])->middleware('permission:pay_commissions');
    Route::post('commission-settlements/{commissionSettlement}/cancel', [CommissionController::class, 'cancel'])->middleware('permission:manage_commission_settlements');

    Route::get('outsourcing/accruals', [OutsourcingController::class, 'accruals'])->middleware('permission:view_outsourcing');
    Route::get('outsourcing/settlements', [OutsourcingController::class, 'settlements'])->middleware('permission:view_outsourcing');
    Route::post('outsourcing/adjustments', [OutsourcingController::class, 'storeAdjustment'])->middleware('permission:manage_outsourcing_settlements');
    Route::post('outsourcing/settlements', [OutsourcingController::class, 'storeSettlement'])->middleware('permission:manage_outsourcing_settlements');
    Route::post('outsourcing/settlements/{outsourcingSettlement}/approve', [OutsourcingController::class, 'approve'])->middleware('permission:approve_outsourcing');
    Route::post('outsourcing/settlements/{outsourcingSettlement}/pay', [OutsourcingController::class, 'pay'])->middleware('permission:pay_outsourcing');
    Route::post('outsourcing/settlements/{outsourcingSettlement}/cancel', [OutsourcingController::class, 'cancel'])->middleware('permission:manage_outsourcing_settlements');

    // Invoice routes
    Route::apiResource('payment-details', PaymentDetailController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_payment_details')
        ->middlewareFor('store', 'permission:create_payment_details')
        ->middlewareFor('update', 'permission:edit_payment_details')
        ->middlewareFor('destroy', 'permission:delete_payment_details');
    Route::get('invoices/commission-candidates', [InvoiceController::class, 'commissionCandidates'])->middleware('permission:view_invoices');
    Route::apiResource('invoices', InvoiceController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_invoices')
        ->middlewareFor('store', 'permission:create_invoices')
        ->middlewareFor('update', 'permission:edit_invoices')
        ->middlewareFor('destroy', 'permission:delete_invoices');
    Route::put('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->middleware('permission:void_invoices');
    Route::put('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->middleware('permission:edit_invoices');

    // Expense routes (new correct spelling)
    Route::apiResource('expenses', ExpenseController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_expenses')
        ->middlewareFor('store', 'permission:create_expenses')
        ->middlewareFor('update', 'permission:edit_expenses')
        ->middlewareFor('destroy', 'permission:delete_expenses');

    // Income routes
    Route::apiResource('incomes', IncomeController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_incomes')
        ->middlewareFor('store', 'permission:create_incomes')
        ->middlewareFor('update', 'permission:edit_incomes')
        ->middlewareFor('destroy', 'permission:delete_incomes');

    Route::get('recurring-events', [RecurringEventController::class, 'index'])->middleware('permission:view_recurring_events');
    Route::post('recurring-events', [RecurringEventController::class, 'store'])->middleware('permission:create_recurring_events');
    Route::get('recurring-events/{recurringEvent}', [RecurringEventController::class, 'show'])->middleware('permission:view_recurring_events');
    Route::put('recurring-events/{recurringEvent}', [RecurringEventController::class, 'update'])->middleware('permission:edit_recurring_events,manage_recurring_events');
    Route::delete('recurring-events/{recurringEvent}', [RecurringEventController::class, 'destroy'])->middleware('permission:delete_recurring_events,manage_recurring_events');
    Route::post('recurring-events/{recurringEvent}/pause', [RecurringEventController::class, 'pause'])->middleware('permission:edit_recurring_events,manage_recurring_events');
    Route::post('recurring-events/{recurringEvent}/resume', [RecurringEventController::class, 'resume'])->middleware('permission:edit_recurring_events,manage_recurring_events');
    Route::post('recurring-event-occurrences/{occurrence}/skip', [RecurringEventController::class, 'skip'])->middleware('permission:edit_recurring_events,manage_recurring_events');
    Route::post('recurring-event-occurrences/{occurrence}/execute', [RecurringEventController::class, 'execute'])->middleware('permission:execute_recurring_events');

    // Visitor routes
    Route::apiResource('visitors', VisitorController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_visitors')
        ->middlewareFor('store', 'permission:create_visitors')
        ->middlewareFor('update', 'permission:edit_visitors')
        ->middlewareFor('destroy', 'permission:delete_visitors');
    Route::post('visitors/{visitor}/convert-to-customer', [VisitorController::class, 'convertToCustomer'])
        ->middleware('permission:create_customers');
    Route::post('visitors/{visitor}/convert-to-lead', [LeadController::class, 'fromVisitor'])->middleware('permission:create_visitors');
    Route::apiResource('leads', LeadController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_visitors')
        ->middlewareFor('store', 'permission:create_visitors')
        ->middlewareFor('update', 'permission:edit_visitors')
        ->middlewareFor('destroy', 'permission:delete_visitors');
    Route::post('leads/{lead}/convert-to-customer', [LeadController::class, 'convert'])->middleware('permission:create_customers');
    Route::post('crm/{type}/{id}/interactions', [CrmInteractionController::class, 'store'])
        ->whereIn('type', ['visitor', 'lead', 'customer']);
    Route::patch('crm/{type}/{id}/notes', [CrmInteractionController::class, 'updateNotes'])
        ->whereIn('type', ['visitor', 'lead', 'customer']);
    Route::put('crm/interactions/{interaction}', [CrmInteractionController::class, 'update']);
    Route::delete('crm/interactions/{interaction}', [CrmInteractionController::class, 'destroy']);

    // Tag routes
    Route::apiResource('tags', TagController::class)
        ->middlewareFor('index', 'permission:view_task_tags')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_task_tags_catalog');

    // Customer routes
    Route::apiResource('customers', CustomerController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_customers')
        ->middlewareFor('store', 'permission:create_customers')
        ->middlewareFor('update', 'permission:edit_customers')
        ->middlewareFor('destroy', 'permission:delete_customers');

    // Educational visa agency master data and case management. These routes
    // intentionally reuse configurable RBAC; the dedicated permission catalog
    // is added in the authorization phase before production role rollout.
    Route::prefix('educational-master-data/{catalog}')->whereIn('catalog', ['countries', 'education-levels', 'languages', 'package-types', 'universities'])->group(function () {
        Route::get('/', [EducationalMasterDataController::class, 'index']);
        Route::post('/', [EducationalMasterDataController::class, 'store']);
        Route::get('/{id}', [EducationalMasterDataController::class, 'show']);
        Route::put('/{id}', [EducationalMasterDataController::class, 'update']);
        Route::delete('/{id}', [EducationalMasterDataController::class, 'destroy']);
    });
    Route::get('applicant-stages', [ApplicantController::class, 'stages']);
    Route::apiResource('applicants', ApplicantController::class);
    Route::post('applicants/{applicant}/change-stage', [ApplicantController::class, 'changeStage']);
    Route::post('/customers/{customer}/services', [CustomerServiceController::class, 'store'])->middleware('permission:manage_customer_services');
    Route::put('/customers/{customer}/services/{customerService}', [CustomerServiceController::class, 'update'])->middleware('permission:manage_customer_services');
    Route::delete('/customers/{customer}/services/{customerService}', [CustomerServiceController::class, 'destroy'])->middleware('permission:manage_customer_services');

    // Timesheet routes
    Route::prefix('attendances')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->middleware('permission:view_attendance');
        Route::post('/', [AttendanceController::class, 'store'])->middleware('permission:record_attendance');
        Route::get('/unmarked-staff', [AttendanceController::class, 'unmarkedStaff'])->middleware('permission:view_unmarked_attendance_staff');
        Route::get('/{attendance}/daily-report', [AttendanceController::class, 'dailyReport'])->middleware('permission:view_attendance');
        Route::delete('/{attendance}', [AttendanceController::class, 'destroy'])->middleware('permission:delete_attendance');
    });

    // Service routes
    Route::apiResource('services', ServiceController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_services')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_services');

    // Currency routes
    Route::apiResource('currencies', CurrencyController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_currencies')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_currencies');

    // Setting routes
    Route::get('company-profile', [SettingController::class, 'profile']);
    Route::apiResource('settings', SettingController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_settings')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:edit_settings');

    // Contract Template routes (replaces old policies)
    Route::apiResource('contract-templates', ContractTemplateController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_contract_templates')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_contract_templates');
    Route::apiResource('contract-policies', ContractPolicyController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_contract_policies')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_contract_policies');

    // Contract types (system + user-defined)
    Route::apiResource('contract-types', ContractTypeController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_contract_types')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_contract_types');

    // Context-aware placeholder definitions for the template editor
    Route::get('contract-placeholders', [ContractController::class, 'placeholders'])
        ->middleware('permission:view_contract_templates,view_contracts');

    // Counterparty companies
    Route::apiResource('companies', CompanyController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_companies')
        ->middlewareFor('store', 'permission:create_companies')
        ->middlewareFor('update', 'permission:edit_companies')
        ->middlewareFor('destroy', 'permission:delete_companies');

    // Contract routes
    Route::apiResource('contracts', ContractController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_contracts')
        ->middlewareFor('store', 'permission:create_contracts')
        ->middlewareFor('update', 'permission:edit_contracts')
        ->middlewareFor('destroy', 'permission:delete_contracts');
    Route::put('/contracts/{id}/cancel', [ContractController::class, 'cancel'])->middleware('permission:cancel_contracts');
    Route::post('/contracts/{id}/renew', [ContractController::class, 'renew'])->middleware('permission:create_contracts');

    // Notification routes
    Route::patch('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->middleware('permission:update_own_notifications');
    Route::apiResource('notifications', NotificationController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_own_notifications')
        ->middlewareFor('store', 'permission:send_notifications')
        ->middlewareFor('update', 'permission:update_own_notifications,send_notifications')
        ->middlewareFor('destroy', 'permission:delete_own_notifications');

    // Immutable audit history, deleted-row report, and supervised undo.
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:view_audit_logs');
    Route::get('audit-logs/pending-count', [AuditLogController::class, 'pendingCount'])->middleware('permission:view_audit_logs');
    Route::get('audit-logs/deleted', [AuditLogController::class, 'deleted'])->middleware('permission:view_deleted_records');
    Route::get('audit-resources', [AuditResourceController::class, 'index'])->middleware('permission:view_audit_logs');
    Route::patch('audit-resources/{auditResourceSetting}', [AuditResourceController::class, 'update'])->middleware('permission:manage_audit_scope');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:view_audit_logs');
    Route::patch('audit-logs/{auditLog}/review', [AuditLogController::class, 'review'])->middleware('permission:review_audit_logs');
    Route::post('audit-logs/bulk-review', [AuditLogController::class, 'bulkReview'])->middleware('permission:review_audit_logs');
    Route::post('audit-logs/{auditLog}/revert', [AuditLogController::class, 'revert'])->middleware('permission:revert_audit_changes');

    // Asset routes
    Route::apiResource('assets', AssetController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_assets')
        ->middlewareFor('store', 'permission:create_assets')
        ->middlewareFor('update', 'permission:edit_assets')
        ->middlewareFor('destroy', 'permission:delete_assets');

    // Dashboard routes
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats'])->middleware('permission:view_dashboard');
        Route::get('/chart-data', [DashboardController::class, 'chartData'])->middleware('permission:view_dashboard');
    });

    // Report routes
    Route::prefix('reports')->group(function () {
        Route::get('/catalog', [ReportController::class, 'catalog']);
        Route::get('/{type}/export', [ReportController::class, 'export'])->whereIn('type', ['financial', 'payroll', 'attendance', 'operations']);
        Route::get('/generate/{type}', [ReportController::class, 'generate'])->whereIn('type', ['financial', 'payroll', 'attendance', 'operations']);
        Route::get('/invoices', [ReportController::class, 'generateInvoiceReport'])->middleware('permission:view_financial_reports');
        Route::get('/expences', [ReportController::class, 'generateExpenceReport'])->middleware('permission:view_financial_reports');
        Route::get('/expenses', [ReportController::class, 'generateExpenseReport'])->middleware('permission:view_financial_reports');
        Route::get('/salaries', [ReportController::class, 'generateSalaryReport'])->middleware('permission:view_salaries');
        Route::get('/incomes', [ReportController::class, 'generateIncomeReport'])->middleware('permission:view_financial_reports');
        Route::get('/visitors', [ReportController::class, 'generateVisitorReport'])->middleware('permission:view_visitors');
        Route::get('/invoices/export', [ReportController::class, 'exportInvoiceReport'])->middleware('permission:export_financial_reports');
    });

    Route::prefix('automation')->group(function () {
        Route::get('/bonus-rules', [AutomationController::class, 'bonusRules'])->middleware('permission:view_bonuses');
        Route::put('/bonus-rules', [AutomationController::class, 'updateBonusRules'])->middleware('permission:manage_bonus_rules');
        Route::get('/runs', [AutomationController::class, 'runs'])->middleware('permission:view_automation_runs');
        Route::get('/analytics', [AutomationController::class, 'analytics'])->middleware('permission:view_analytics');
    });

    // File management routes
    Route::prefix('files')->group(function () {
        Route::post('/upload', [FileController::class, 'upload'])->middleware('permission:upload_files');
        Route::get('/download', [FileController::class, 'download'])->middleware('permission:download_files');
        Route::delete('/delete', [FileController::class, 'delete'])->middleware('permission:delete_files');
        Route::get('/list', [FileController::class, 'listFiles'])->middleware('permission:view_files');
        Route::get('/view', [FileController::class, 'viewFile'])->middleware('permission:view_files');
    });
});
