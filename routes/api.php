<?php

use App\Http\Controllers\AccountDeactivationRequestController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ApplicantAttachmentController;
use App\Http\Controllers\ApplicantContractStageController;
use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\ApplicantPortalController;
use App\Http\Controllers\ApplicantStageController;
use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\ApplicantUniversityController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceGeofenceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuditResourceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractDocumentDesignController;
use App\Http\Controllers\ContractPolicyController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\ContractTemplateVersionController;
use App\Http\Controllers\ContractTypeController;
use App\Http\Controllers\CrmInteractionController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EducationalBonusSourceController;
use App\Http\Controllers\EducationalDashboardController;
use App\Http\Controllers\EducationalMasterDataController;
use App\Http\Controllers\EducationalReportController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FinanceTypeController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OffDayController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\PendingTransactionController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\RecurringEventController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\EmployeeAdvanceController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SalaryDeductionController;
use App\Http\Controllers\SalaryPaymentController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskAssigneeController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\WhatsappTemplateController;
use App\Http\Controllers\WorkdayController;
use App\Http\Controllers\WorthyEmployeeController;
use App\Http\Middleware\AttendanceBrowser;
use App\Services\ReportService;
use App\Support\BuiltInService;
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
// Deployed frontend build version. Never cached by the service worker.
Route::get('version', AppVersionController::class);
// Authentication screens use this deliberately limited identity before a user has a session.
Route::get('company-branding', [SettingController::class, 'publicBrandingProfile']);

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
Route::middleware(['auth:sanctum', 'abilities:access', 'active', 'edit-window'])->group(function () {
    Route::prefix('workday')->middleware(AttendanceBrowser::class)->group(function () {
        Route::get('/status', [WorkdayController::class, 'status'])->middleware('permission:manage_own_workday');
        Route::post('/start', [WorkdayController::class, 'start'])->middleware(['permission:manage_own_workday', 'throttle:10,1']);
        Route::post('/end', [WorkdayController::class, 'end'])->middleware(['permission:submit_own_daily_report', 'throttle:10,1']);
        Route::get('/geofence', [AttendanceGeofenceController::class, 'show'])->middleware('permission:manage_own_workday');
        Route::post('/geofence', [AttendanceGeofenceController::class, 'observe'])->middleware(['permission:manage_own_workday', 'throttle:30,1']);
    });
    Route::get('my-work-history', [AttendanceController::class, 'mine']);
    Route::get('my-work-history/{attendance}/daily-report', [AttendanceController::class, 'ownDailyReport']);

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
        Route::put('/account/ui-scale', [AuthController::class, 'updateUiScale']);
        Route::post('/account/two-factor', [AuthController::class, 'beginTwoFactor']);
        Route::post('/account/two-factor/confirm', [AuthController::class, 'confirmTwoFactor']);
        Route::delete('/account/two-factor', [AuthController::class, 'disableTwoFactor']);
        Route::post('/account/logout-other-devices', [AuthController::class, 'logoutOtherDevices']);
        Route::get('/account/sessions', [AuthController::class, 'sessions']);
        Route::delete('/account/sessions/{token}', [AuthController::class, 'revokeSession'])->whereNumber('token');
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

    // Off-day requests (employee leave). An approved request is an off_day up
    // to the employee's allowance and an absence beyond it.
    Route::get('leave-requests', [LeaveRequestController::class, 'all'])->middleware('permission:view_leave_requests');
    Route::get('my-leave-requests', [LeaveRequestController::class, 'mine'])->middleware('permission:request_own_leave');
    Route::post('my-leave-requests', [LeaveRequestController::class, 'requestRange'])->middleware('permission:request_own_leave');
    Route::patch('leave-requests/grant', [LeaveRequestController::class, 'grant'])->middleware('permission:grant_leave_requests');
    Route::get('employees/{employee}/leave-requests', [LeaveRequestController::class, 'index'])->middleware('permission:view_leave_requests');
    Route::post('employees/{employee}/leave-requests', [LeaveRequestController::class, 'store'])->middleware('permission:create_leave_requests');
    Route::put('employees/{employee}/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])->middleware('permission:edit_leave_requests');
    Route::delete('employees/{employee}/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->middleware('permission:delete_leave_requests');

    // Company-wide off days. Announcing needs manage_off_days; activating and
    // removing an approved holiday needs the dedicated approve_off_days grant.
    Route::get('off-days', [OffDayController::class, 'index'])->middleware('permission:view_off_days');
    Route::post('off-days', [OffDayController::class, 'store'])->middleware('permission:manage_off_days');
    Route::put('off-days/{offDay}', [OffDayController::class, 'update'])->middleware('permission:manage_off_days');
    Route::patch('off-days/{offDay}/approve', [OffDayController::class, 'approve'])->middleware('permission:approve_off_days');
    Route::patch('off-days/{offDay}/reject', [OffDayController::class, 'reject'])->middleware('permission:approve_off_days');
    Route::delete('off-days/{offDay}', [OffDayController::class, 'destroy'])->middleware('permission:manage_off_days');
    // Departments are the firm's groups; the manager assignment is what makes
    // a senior role see a team's data.
    Route::get('departments/manager-options', [DepartmentController::class, 'managerOptions'])
        ->middleware('permission:view_departments,manage_departments');
    Route::get('departments', [DepartmentController::class, 'index'])->middleware('permission:view_departments');
    Route::post('departments', [DepartmentController::class, 'store'])->middleware('permission:manage_departments');
    Route::put('departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:manage_departments');
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:manage_departments');
    Route::get('employees/assignment-options', [EmployeeController::class, 'assignmentOptions'])->middleware('permission:view_applicants,view_visitors,create_contracts,view_financial_reports,view_employees');
    Route::get('employees/account-options', [EmployeeController::class, 'accountOptions'])->middleware('permission:view_employees');
    Route::apiResource('employees', EmployeeController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_employees')
        ->middlewareFor('store', 'permission:create_employees')
        // A self-editor may only update their own profile and only the fields
        // the controller allows; see EmployeeController::update.
        ->middlewareFor('update', 'permission:edit_employees,edit_own_employee_profile')
        ->middlewareFor('destroy', 'permission:delete_employees');
    Route::post('employees/{employee}/document', [EmployeeController::class, 'uploadDocument'])->middleware('permission:edit_employees');
    Route::get('employees/{employee}/document', [EmployeeController::class, 'downloadDocument'])->middleware('permission:view_employees');
    Route::delete('employees/{employee}/document', [EmployeeController::class, 'removeDocument'])->middleware('permission:edit_employees');

    // Position routes
    Route::apiResource('positions', PositionController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_positions')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_positions');
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
    Route::get('salaries/attendance-breakdown', [SalaryController::class, 'attendanceBreakdown'])->middleware('permission:view_salaries');
    Route::post('salaries/generate', [SalaryController::class, 'generate'])->middleware('permission:create_salaries');
    // Registered before the resource so `register` is not read as a salary id.
    Route::get('salaries/register', [SalaryController::class, 'register'])->middleware('permission:view_salaries');
        // Employee salary advances (an employee-level ledger, not tied to one salary).
        Route::get('employee-advances', [EmployeeAdvanceController::class, 'index'])->middleware('permission:view_salaries');
        Route::post('employee-advances', [EmployeeAdvanceController::class, 'store'])->middleware('permission:create_salaries');
        Route::delete('employee-advances/{employeeAdvance}', [EmployeeAdvanceController::class, 'destroy'])->middleware('permission:delete_salaries');
        Route::post('salaries/{salary}/payments', [SalaryPaymentController::class, 'store'])->middleware('permission:create_salaries');
    Route::delete('salaries/{salary}/payments/{payment}', [SalaryPaymentController::class, 'destroy'])->middleware('permission:delete_salaries');
    Route::get('salaries/{salary}/deductions', [SalaryDeductionController::class, 'index'])->middleware('permission:view_salary_deductions');
    Route::post('salaries/{salary}/deductions', [SalaryDeductionController::class, 'store'])->middleware('permission:create_salary_deductions');
    Route::patch('salaries/{salary}/deductions/{deduction}', [SalaryDeductionController::class, 'update'])->middleware('permission:edit_salary_deductions');
    Route::delete('salaries/{salary}/deductions/{deduction}', [SalaryDeductionController::class, 'destroy'])->middleware('permission:delete_salary_deductions');
    Route::apiResource('salaries', SalaryController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_salaries')
        ->middlewareFor('store', 'permission:create_salaries')
        ->middlewareFor('update', 'permission:edit_salaries')
        ->middlewareFor('destroy', 'permission:delete_salaries');

    // Expense routes (new correct spelling)
    Route::apiResource('expenses', ExpenseController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_expenses')
        ->middlewareFor('store', 'permission:create_expenses')
        ->middlewareFor('update', 'permission:edit_expenses')
        ->middlewareFor('destroy', 'permission:delete_expenses');
    Route::post('expenses/{expense}/document', [ExpenseController::class, 'uploadDocument'])->middleware('permission:edit_expenses');
    Route::get('expenses/{expense}/document', [ExpenseController::class, 'downloadDocument'])->middleware('permission:view_expenses');
    Route::delete('expenses/{expense}/document', [ExpenseController::class, 'removeDocument'])->middleware('permission:edit_expenses');

    // Commission ledger. Accruals are earned when a contract is completed;
    // settlements move them through draft → approved → paid (paid books an
    // expense). The former invoice-driven endpoints stay retired.
    Route::get('commissions/accruals', [CommissionController::class, 'accruals'])->middleware('permission:view_commissions');
    Route::get('commissions/summary', [CommissionController::class, 'summary'])->middleware('permission:view_commissions');
    Route::post('commissions/adjustments', [CommissionController::class, 'storeAdjustment'])->middleware('permission:manage_commission_settlements');
    Route::post('commissions/sync', [CommissionController::class, 'sync'])->middleware('permission:manage_commission_settlements');
    Route::get('commission-settlements', [CommissionController::class, 'settlements'])->middleware('permission:view_commissions');
    Route::post('commission-settlements', [CommissionController::class, 'storeSettlement'])->middleware('permission:manage_commission_settlements');
    Route::get('commission-settlements/{commissionSettlement}', [CommissionController::class, 'show'])->middleware('permission:view_commissions');
    Route::post('commission-settlements/{commissionSettlement}/approve', [CommissionController::class, 'approve'])->middleware('permission:approve_commissions');
    Route::post('commission-settlements/{commissionSettlement}/pay', [CommissionController::class, 'pay'])->middleware('permission:pay_commissions');
    Route::post('commission-settlements/{commissionSettlement}/cancel', [CommissionController::class, 'cancel'])->middleware('permission:manage_commission_settlements');

    // Income routes
    // Payments attach to a contracted service line, so the picker needs the
    // applicant's own lines (declared before the resource route below).
    Route::get('incomes/applicants', [IncomeController::class, 'applicants'])
        ->middleware('permission:view_incomes');
    Route::get('incomes/applicant-contracts', [IncomeController::class, 'applicantContracts'])
        ->middleware('permission:view_incomes');
    Route::put('incomes/applicant-contracts/{line}/payment-override', [IncomeController::class, 'paymentOverride'])
        ->middleware(['permission:view_incomes', 'permission:override_contract_payments']);
    Route::prefix('finance-types/{kind}')->whereIn('kind', ['income', 'expense'])->group(function () {
        Route::get('/', [FinanceTypeController::class, 'index'])->middleware('permission:view_incomes,view_expenses,view_settings,create_incomes,edit_incomes,create_expenses,edit_expenses');
        Route::get('/{id}', [FinanceTypeController::class, 'show'])->middleware('permission:view_settings');
        Route::post('/', [FinanceTypeController::class, 'store'])->middleware('permission:edit_settings');
        Route::put('/{id}', [FinanceTypeController::class, 'update'])->middleware('permission:edit_settings');
        Route::delete('/{id}', [FinanceTypeController::class, 'destroy'])->middleware('permission:edit_settings');
    });
    Route::apiResource('incomes', IncomeController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_incomes')
        ->middlewareFor('store', 'permission:create_incomes')
        ->middlewareFor('update', 'permission:edit_incomes')
        ->middlewareFor('destroy', 'permission:delete_incomes');
    Route::get('incomes/{income}/receipt', [IncomeController::class, 'receipt'])->middleware('permission:view_incomes');

    // Shared attachment sections. One implementation serves expenses, incomes
    // and staff; the controller enforces each resource's view/edit permission.
    Route::get('attachments/{type}/{id}', [AttachmentController::class, 'index']);
    Route::post('attachments/{type}/{id}', [AttachmentController::class, 'store']);
    Route::get('attachments/{type}/{id}/{attachment}/download', [AttachmentController::class, 'download']);
    Route::delete('attachments/{type}/{id}/{attachment}', [AttachmentController::class, 'destroy']);

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
    // Marking a visitor as worthy of follow-up (and taking it back off the
    // list) is an edit of the visitor record, so it reuses that permission.
    Route::put('visitors/{visitor}/follow-up', [VisitorController::class, 'setFollowUp'])
        ->middleware('permission:edit_visitors');
    Route::put('visitors/{visitor}/consultant-review', [VisitorController::class, 'updateConsultantReview'])
        ->middleware('permission:review_visitors');
    // Visitors reach the pipeline by becoming applicants; there is no lead stage.
    Route::post('visitors/{visitor}/convert-to-applicant', [VisitorController::class, 'convertToApplicant'])
        ->middleware('permission:create_applicants');
    Route::post('crm/{type}/{id}/interactions', [CrmInteractionController::class, 'store'])
        ->whereIn('type', ['visitor', 'applicant']);
    Route::patch('crm/{type}/{id}/notes', [CrmInteractionController::class, 'updateNotes'])
        ->whereIn('type', ['visitor', 'applicant']);
    Route::put('crm/interactions/{interaction}', [CrmInteractionController::class, 'update']);
    Route::delete('crm/interactions/{interaction}', [CrmInteractionController::class, 'destroy']);

    // Tag routes
    Route::apiResource('tags', TagController::class)
        ->middlewareFor('index', 'permission:view_task_tags')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_task_tags_catalog');

    // Educational visa agency master data and case management. These routes
    // intentionally reuse configurable RBAC; the dedicated permission catalog
    // is added in the authorization phase before production role rollout.
    Route::prefix('educational-master-data/{catalog}')->whereIn('catalog', ['countries', 'education-levels', 'languages', 'universities', 'semesters', 'subjects', 'tests', 'skills', 'programs', 'times', 'durations', 'teachers', 'scholarships', 'classes', 'provinces', 'english-levels', 'acquisition-routes'])->group(function () {
        Route::get('/', [EducationalMasterDataController::class, 'index']);
        Route::post('/', [EducationalMasterDataController::class, 'store']);
        Route::get('/{id}', [EducationalMasterDataController::class, 'show']);
        Route::put('/{id}', [EducationalMasterDataController::class, 'update']);
        Route::delete('/{id}', [EducationalMasterDataController::class, 'destroy']);
    });
    Route::get('applicant-stages', [ApplicantStageController::class, 'index'])->middleware('permission:view_applicant_stages');
    Route::post('applicant-stages', [ApplicantStageController::class, 'store'])->middleware('permission:manage_applicant_stages');
    Route::put('applicant-stages/{applicantStage}', [ApplicantStageController::class, 'update'])->middleware('permission:manage_applicant_stages');
    Route::delete('applicant-stages/{applicantStage}', [ApplicantStageController::class, 'destroy'])->middleware('permission:manage_applicant_stages');
    Route::get('applicant-portal', [ApplicantPortalController::class, 'show'])->middleware('permission:view_own_applicant_portal');
    Route::put('applicant-portal/profile', [ApplicantPortalController::class, 'updateProfile'])->middleware('permission:edit_own_applicant_profile');
    Route::get('applicant-portal/attachments', [ApplicantPortalController::class, 'attachments'])->middleware('permission:view_own_applicant_portal');
    Route::post('applicant-portal/attachments', [ApplicantPortalController::class, 'uploadAttachment'])->middleware('permission:edit_own_applicant_profile');
    Route::get('applicant-portal/attachments/{attachment}/preview', [ApplicantPortalController::class, 'previewAttachment'])->middleware('permission:view_own_applicant_portal');
    Route::delete('applicant-portal/attachments/{attachment}', [ApplicantPortalController::class, 'destroyAttachment'])->middleware('permission:edit_own_applicant_profile');
    // Credential suggestion for a contract that has not been saved yet; the
    // contract's creation issues the account with exactly these values.
    Route::post('applicants/portal-credentials', [ApplicantPortalController::class, 'credentials'])->middleware('permission:create_contracts');
    Route::post('applicants/{applicant}/portal-invitation', [ApplicantPortalController::class, 'invite'])->middleware('permission:invite_applicant_portal');
    Route::put('applicants/{applicant}/portal-access', [ApplicantPortalController::class, 'setAccess'])->middleware('permission:invite_applicant_portal');
    Route::delete('applicants/{applicant}/portal-user', [ApplicantPortalController::class, 'destroyUser'])->middleware('permission:invite_applicant_portal');
    Route::get('applicants-pipeline', [ApplicantController::class, 'pipeline'])->middleware('permission:view_applicants');
    Route::apiResource('applicants', ApplicantController::class)->middlewareFor(['index', 'show'], 'permission:view_applicants')->middlewareFor('store', 'permission:create_applicants')->middlewareFor('update', 'permission:edit_applicants')->middlewareFor('destroy', 'permission:delete_applicants');
    Route::put('applicants/{applicant}/consultant-review', [ApplicantController::class, 'updateConsultantReview'])
        ->middleware('permission:review_visitors');
    Route::get('applicants/{applicant}/attachments', [ApplicantAttachmentController::class, 'index'])->middleware('permission:view_applicants');
    Route::post('applicants/{applicant}/attachments', [ApplicantAttachmentController::class, 'store'])->middleware('permission:edit_applicants');
    Route::put('applicants/{applicant}/attachments/{attachment}', [ApplicantAttachmentController::class, 'update'])->middleware('permission:edit_applicants');
    Route::get('applicants/{applicant}/attachments/{attachment}/download', [ApplicantAttachmentController::class, 'download'])->middleware('permission:view_applicants');
    Route::delete('applicants/{applicant}/attachments/{attachment}', [ApplicantAttachmentController::class, 'destroy'])->middleware('permission:edit_applicants');
    Route::post('applicants/{applicant}/change-stage', [ApplicantController::class, 'changeStage'])->middleware('permission:change_applicant_stage');
    // Per-service workflow: each contracted line is its own case and moves
    // through its own service's stage list independently of the applicant's
    // other services. The read sits beside the writer so both stay line-scoped.
    Route::get('applicant-contracts/{line}', [ApplicantContractStageController::class, 'show'])->middleware('permission:view_applicants');
    Route::post('applicant-contracts/{line}/change-stage', [ApplicantContractStageController::class, 'changeStage'])->middleware('permission:change_applicant_stage');
    Route::get('applicants/{applicant}/universities', [ApplicantUniversityController::class, 'index'])->middleware('permission:view_applicants');
    Route::post('applicants/{applicant}/universities', [ApplicantUniversityController::class, 'store'])->middleware('permission:edit_applicants');
    Route::put('applicants/{applicant}/universities/{application}', [ApplicantUniversityController::class, 'update'])->middleware('permission:edit_applicants');
    Route::delete('applicants/{applicant}/universities/{application}', [ApplicantUniversityController::class, 'destroy'])->middleware('permission:edit_applicants');
    // Agency operations overview. Read-only aggregates over applicant,
    // sales, task and approval rows; any dashboard viewer may open it.
    Route::get('educational-dashboard', [EducationalDashboardController::class, 'index'])->middleware('permission:view_agency_overview');
    // Visa-agency tabular reports plus CSV export. New controller on purpose:
    // the finance report surface stays untouched by agency reporting.
    // Report authorization is type-specific in the controller: application,
    // finance and HR reports belong to their respective workspaces.
    Route::get('educational-reports/{type}', [EducationalReportController::class, 'show']);
    Route::get('educational-reports/{type}/export', [EducationalReportController::class, 'export']);
    Route::get('educational-bonus-sources', [EducationalBonusSourceController::class, 'index'])->middleware('permission:view_bonuses');
    Route::post('educational-bonus-sources', [EducationalBonusSourceController::class, 'store'])->middleware('permission:manage_educational_bonuses');
    Route::put('educational-bonus-sources/{educationalBonusSource}', [EducationalBonusSourceController::class, 'update'])->middleware('permission:manage_educational_bonuses');
    // Payment requests are the canonical public workflow: an admission officer
    // or counselor raises one, and the finance manager approves, posts and
    // prints it. The legacy `loans` and `pending-transactions` paths are gone,
    // so this prefix is the only public address of the workflow.
    Route::prefix('payment-requests')->group(function () {
        Route::get('/', [PendingTransactionController::class, 'index'])->middleware('permission:view_pending_transactions');
        Route::get('options', [PendingTransactionController::class, 'options'])->middleware('permission:create_pending_transactions');
        Route::post('/', [PendingTransactionController::class, 'store'])->middleware('permission:create_pending_transactions');
        Route::put('{pendingTransaction}', [PendingTransactionController::class, 'update'])->middleware('permission:create_pending_transactions');
        Route::post('{pendingTransaction}/review', [PendingTransactionController::class, 'review'])->middleware('permission:approve_pending_transactions');
        Route::post('{pendingTransaction}/approve', [PendingTransactionController::class, 'approve'])->middleware('permission:approve_pending_transactions');
        Route::post('{pendingTransaction}/reject', [PendingTransactionController::class, 'reject'])->middleware('permission:approve_pending_transactions');
        Route::post('{pendingTransaction}/post', [PendingTransactionController::class, 'post'])->middleware('permission:post_pending_transactions');
        Route::get('{pendingTransaction}/receipt', [PendingTransactionController::class, 'receipt'])->middleware('permission:view_pending_transactions');
    });
    // Timesheet routes
    Route::prefix('attendances')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->middleware('permission:view_attendance');
        Route::post('/', [AttendanceController::class, 'store'])->middleware('permission:record_attendance');
        Route::post('/{attendance}/checkout-correction', [AttendanceController::class, 'correctCheckout'])->middleware(['permission:record_attendance', 'throttle:10,1']);
        Route::get('/unmarked-staff', [AttendanceController::class, 'unmarkedStaff'])->middleware('permission:view_unmarked_attendance_staff');
        Route::get('/{attendance}/daily-report', [AttendanceController::class, 'dailyReport'])->middleware('permission:view_attendance');
        Route::delete('/{attendance}', [AttendanceController::class, 'destroy'])->middleware('permission:delete_attendance');
    });

    // Currency routes
    Route::apiResource('currencies', CurrencyController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_currencies')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_currencies');

    // Setting routes
    Route::get('company-profile', [SettingController::class, 'profile'])
        ->middleware('permission:view_settings,view_contracts');
    Route::apiResource('settings', SettingController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_settings')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:edit_settings');

    // Contract Template routes (replaces old policies)
    Route::post('contract-templates/built-in/{service}', [ContractTemplateController::class, 'ensureBuiltIn'])
        ->whereIn('service', BuiltInService::ALL)
        ->middleware('permission:manage_contract_templates');
    Route::apiResource('contract-templates', ContractTemplateController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_contract_templates')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_contract_templates');
    Route::get('contract-templates/{template}/versions', [ContractTemplateVersionController::class, 'index'])->middleware('permission:view_contract_templates');
    Route::post('contract-templates/{template}/versions', [ContractTemplateVersionController::class, 'store'])->middleware('permission:create_contract_template_versions');
    Route::get('contract-templates/{template}/versions/{version}', [ContractTemplateVersionController::class, 'show'])->middleware('permission:view_contract_templates');
    Route::put('contract-templates/{template}/versions/{version}', [ContractTemplateVersionController::class, 'update'])->middleware('permission:edit_draft_contract_template_versions');
    Route::post('contract-templates/{template}/versions/{version}/activate', [ContractTemplateVersionController::class, 'activate'])->middleware('permission:activate_contract_template_versions');
    Route::post('contract-templates/{template}/versions/{version}/archive', [ContractTemplateVersionController::class, 'archive'])->middleware('permission:archive_contract_template_versions');
    Route::apiResource('contract-policies', ContractPolicyController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_contract_policies')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_contract_policies');

    // Contract types (system + user-defined)
    Route::apiResource('contract-types', ContractTypeController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_contract_types')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:manage_contract_types');
    Route::apiResource('contract-document-designs', ContractDocumentDesignController::class)->only(['index', 'store', 'update'])
        ->middlewareFor('index', 'permission:view_contract_types')
        ->middlewareFor(['store', 'update'], 'permission:manage_contract_types');

    // Context-aware placeholder definitions for the template editor
    Route::get('contract-placeholders', [ContractController::class, 'placeholders'])
        ->middleware('permission:view_contract_templates,view_contracts');

    // Contract routes
    Route::post('contracts/preview', [ContractController::class, 'preview'])
        ->middleware('permission:create_contracts,edit_contracts');
    Route::get('contracts/{service}/options', [ContractController::class, 'serviceOptions'])
        ->whereIn('service', BuiltInService::ALL)
        ->middleware('permission:create_contracts');
    Route::get('contracts/{service}/version-options', [ContractController::class, 'versionOptions'])
        ->whereIn('service', BuiltInService::ALL)
        ->middleware('permission:view_contracts');
    Route::post('contracts/{service}', [ContractController::class, 'storeService'])
        ->whereIn('service', BuiltInService::ALL)
        ->middleware('permission:create_contracts');
    Route::put('contracts/{service}/{id}', [ContractController::class, 'updateService'])
        ->whereIn('service', BuiltInService::ALL)
        ->middleware('permission:edit_contracts');
    // Receipt-like A5 enrolment slip for an individual academy contract.
    // Registered before the resource so `counterparties` is not read as an id.
    Route::get('contracts/counterparties', [ContractController::class, 'counterparties'])
        ->middleware('permission:view_contracts');
    Route::get('contracts/{id}/academy-slip', [ContractController::class, 'academySlip'])
        ->middleware('permission:view_contracts');
    Route::apiResource('contracts', ContractController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_contracts')
        ->middlewareFor('store', 'permission:create_contracts')
        ->middlewareFor('update', 'permission:edit_contracts')
        ->middlewareFor('destroy', 'permission:delete_contracts');
    Route::put('/contracts/{id}/cancel', [ContractController::class, 'cancel'])->middleware('permission:cancel_contracts');

    // Notification routes
    Route::patch('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->middleware('permission:update_own_notifications');
    // Real-time poll endpoint. Must stay above apiResource('notifications') so
    // `heartbeat` is not captured by the {notification} route binding.
    Route::get('notifications/heartbeat', [NotificationController::class, 'heartbeat'])->middleware('permission:view_own_notifications');
    Route::apiResource('notifications', NotificationController::class)
        ->middlewareFor(['index', 'show'], 'permission:view_own_notifications')
        ->middlewareFor('store', 'permission:send_notifications')
        ->middlewareFor('update', 'permission:update_own_notifications,send_notifications')
        ->middlewareFor('destroy', 'permission:delete_own_notifications');

    // Immutable audit history, deleted-row report, and supervised undo.
    // Team reviewers reach the same surface but the controller narrows the
    // rows to their group; organisation-wide review needs view_all_audits.
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:view_audit_logs,view_team_audit_logs');
    Route::get('audit-logs/pending-count', [AuditLogController::class, 'pendingCount'])->middleware('permission:view_audit_logs,view_team_audit_logs');
    // Must precede `audit-logs/{auditLog}` so the route binding cannot swallow
    // the heartbeat segment. The endpoint drains a per-user flag exactly once.
    Route::get('audit-logs/heartbeat', [AuditLogController::class, 'heartbeat'])->middleware('permission:view_audit_logs,view_team_audit_logs');
    Route::get('audit-logs/deleted', [AuditLogController::class, 'deleted'])->middleware('permission:view_deleted_records');
    Route::get('audit-resources', [AuditResourceController::class, 'index'])->middleware('permission:view_audit_logs');
    Route::patch('audit-resources/{auditResourceSetting}', [AuditResourceController::class, 'update'])->middleware('permission:manage_audit_scope');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:view_audit_logs,view_team_audit_logs');
    Route::patch('audit-logs/{auditLog}/review', [AuditLogController::class, 'review'])->middleware('permission:review_audit_logs');
    Route::post('audit-logs/bulk-review', [AuditLogController::class, 'bulkReview'])->middleware('permission:review_audit_logs');
    Route::post('audit-logs/{auditLog}/revert', [AuditLogController::class, 'revert'])->middleware('permission:revert_audit_changes');

    // Asset routes
    // Registered before the resource so `models` is not read as an asset id.
    Route::get('assets/models', [AssetController::class, 'models'])->middleware('permission:view_assets');
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
        // Bound to the service's catalogue so a new report family can never be
        // reachable in the UI while its routes still answer 404.
        Route::get('/{type}/export', [ReportController::class, 'export'])->whereIn('type', ReportService::TYPES);
        Route::get('/generate/{type}', [ReportController::class, 'generate'])->whereIn('type', ReportService::TYPES);
        Route::get('/expences', [ReportController::class, 'generateExpenceReport'])->middleware('permission:view_financial_reports');
        Route::get('/expenses', [ReportController::class, 'generateExpenseReport'])->middleware('permission:view_financial_reports');
        Route::get('/salaries', [ReportController::class, 'generateSalaryReport'])->middleware('permission:view_salaries');
        Route::get('/incomes', [ReportController::class, 'generateIncomeReport'])->middleware('permission:view_financial_reports');
        Route::get('/visitors', [ReportController::class, 'generateVisitorReport'])->middleware('permission:view_visitors');
    });

    Route::prefix('automation')->group(function () {
        Route::get('/bonus-rules', [AutomationController::class, 'bonusRules'])->middleware('permission:view_bonuses');
        Route::put('/bonus-rules', [AutomationController::class, 'updateBonusRules'])->middleware('permission:manage_bonus_rules');
        Route::get('/runs', [AutomationController::class, 'runs'])->middleware('permission:view_automation_runs');
        Route::get('/analytics', [AutomationController::class, 'analytics'])->middleware('permission:view_analytics');
    });

    // WhatsApp message templates. A template belongs to one record type, so
    // access follows that record type's permissions instead of adding a new
    // permission: reading needs the record's view permission, managing needs
    // its edit permission. Delivery stays a `wa.me` deep link built on the
    // client, so nothing here talks to WhatsApp and no send route exists.
    Route::get('whatsapp-template-placeholders', [WhatsappTemplateController::class, 'placeholders'])
        ->middleware('permission:view_visitors,view_applicants,view_employees');
    Route::apiResource('whatsapp-templates', WhatsappTemplateController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:view_visitors,view_applicants,view_employees')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:edit_visitors,edit_applicants,edit_employees');

    // Announcements are immutable: no update/destroy routes exist by design.
    Route::get('announcements', [AnnouncementController::class, 'index'])->middleware('permission:send_announcements');
    Route::post('announcements', [AnnouncementController::class, 'store'])->middleware('permission:send_announcements');
    Route::get('announcements/audience-options', [AnnouncementController::class, 'audienceOptions'])->middleware('permission:send_announcements');
    Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->middleware('permission:send_announcements');

    // File management routes
    Route::prefix('files')->group(function () {
        Route::post('/upload', [FileController::class, 'upload'])->middleware('permission:upload_files');
        Route::get('/download', [FileController::class, 'download'])->middleware('permission:download_files');
        Route::delete('/delete', [FileController::class, 'delete'])->middleware('permission:delete_files');
        Route::get('/list', [FileController::class, 'listFiles'])->middleware('permission:view_files');
        Route::get('/view', [FileController::class, 'viewFile'])->middleware('permission:view_files');
    });
});
 
