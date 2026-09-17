<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The canonical permission vocabulary for the application.
 *
 * Permission names are stable machine identifiers. They must not be renamed
 * from the UI: policies, API authorization, and CASL abilities depend on them.
 * Scope is explicit rather than inferred from a page or HTTP verb. In task
 * management, "assigned" means the current user is assigned to the task (or
 * is a member of the board); "own" means the current user created the child
 * resource such as a subtask or comment.
 */
class PermissionSeeder extends Seeder
{
    private const LEGACY_MOCK_PERMISSIONS = [
        'view_users',
        'create_users',
        'edit_users',
        'delete_users',
        'view_roles',
        'create_roles',
        'edit_roles',
        'delete_roles',
        'view_permissions',
        'assign_permissions',
    ];

    /** @var array<int, array{name: string, category: string}> */
    private const CATALOG = [
        // Core administration
        ['name' => 'view_dashboard', 'category' => 'core'],
        ['name' => 'view_reports', 'category' => 'core'],
        ['name' => 'export_reports', 'category' => 'core'],
        ['name' => 'view_automation_runs', 'category' => 'core'],
        ['name' => 'view_analytics', 'category' => 'core'],
        ['name' => 'view_settings', 'category' => 'core'],
        ['name' => 'edit_settings', 'category' => 'core'],
        ['name' => 'view_audit_logs', 'category' => 'core'],
        ['name' => 'view_deleted_records', 'category' => 'core'],
        ['name' => 'review_audit_logs', 'category' => 'core'],
        ['name' => 'revert_audit_changes', 'category' => 'core'],
        ['name' => 'manage_audit_scope', 'category' => 'core'],

        // Identity and access management. Roles and permissions are
        // system-owned catalog data; access assignments are managed separately.
        ['name' => 'view_users', 'category' => 'identity_access'],
        ['name' => 'create_users', 'category' => 'identity_access'],
        ['name' => 'edit_users', 'category' => 'identity_access'],
        ['name' => 'delete_users', 'category' => 'identity_access'],
        ['name' => 'manage_user_status', 'category' => 'identity_access'],
        ['name' => 'request_account_deactivation', 'category' => 'identity_access'],
        ['name' => 'cancel_own_deactivation_request', 'category' => 'identity_access'],
        ['name' => 'view_account_deactivation_requests', 'category' => 'identity_access'],
        ['name' => 'review_account_deactivation_requests', 'category' => 'identity_access'],
        ['name' => 'reactivate_user_accounts', 'category' => 'identity_access'],
        ['name' => 'assign_user_roles', 'category' => 'identity_access'],
        ['name' => 'view_roles', 'category' => 'identity_access'],
        ['name' => 'create_roles', 'category' => 'identity_access'],
        ['name' => 'edit_roles', 'category' => 'identity_access'],
        ['name' => 'delete_roles', 'category' => 'identity_access'],
        ['name' => 'manage_role_permissions', 'category' => 'identity_access'],
        ['name' => 'view_permissions', 'category' => 'identity_access'],

        // HR directory and leave ledger
        ['name' => 'view_employees', 'category' => 'human_resources'],
        ['name' => 'create_employees', 'category' => 'human_resources'],
        ['name' => 'edit_employees', 'category' => 'human_resources'],
        ['name' => 'delete_employees', 'category' => 'human_resources'],
        ['name' => 'view_paid_time_off', 'category' => 'human_resources'],
        ['name' => 'create_paid_time_off', 'category' => 'human_resources'],
        ['name' => 'edit_paid_time_off', 'category' => 'human_resources'],
        ['name' => 'delete_paid_time_off', 'category' => 'human_resources'],
        ['name' => 'request_own_paid_time_off', 'category' => 'human_resources'],
        ['name' => 'grant_paid_time_off', 'category' => 'human_resources'],
        ['name' => 'print_paid_time_off', 'category' => 'human_resources'],
        ['name' => 'view_attendance', 'category' => 'human_resources'],
        ['name' => 'record_attendance', 'category' => 'human_resources'],
        ['name' => 'delete_attendance', 'category' => 'human_resources'],
        ['name' => 'view_unmarked_attendance_staff', 'category' => 'human_resources'],
        ['name' => 'manage_own_workday', 'category' => 'human_resources'],
        ['name' => 'submit_own_daily_report', 'category' => 'human_resources'],
        ['name' => 'view_salaries', 'category' => 'human_resources'],
        ['name' => 'create_salaries', 'category' => 'human_resources'],
        ['name' => 'edit_salaries', 'category' => 'human_resources'],
        ['name' => 'delete_salaries', 'category' => 'human_resources'],
        ['name' => 'view_salary_deductions', 'category' => 'human_resources'],
        ['name' => 'create_salary_deductions', 'category' => 'human_resources'],
        ['name' => 'edit_salary_deductions', 'category' => 'human_resources'],
        ['name' => 'delete_salary_deductions', 'category' => 'human_resources'],
        ['name' => 'view_commissions', 'category' => 'human_resources'],
        ['name' => 'manage_commission_settlements', 'category' => 'human_resources'],
        ['name' => 'approve_commissions', 'category' => 'human_resources'],
        ['name' => 'pay_commissions', 'category' => 'human_resources'],
        ['name' => 'view_bonuses', 'category' => 'human_resources'],
        ['name' => 'nominate_bonus_candidates', 'category' => 'human_resources'],
        ['name' => 'edit_bonus_nominations', 'category' => 'human_resources'],
        ['name' => 'grant_bonuses', 'category' => 'human_resources'],
        ['name' => 'manage_bonus_rules', 'category' => 'human_resources'],
        ['name' => 'delete_bonus_nominations', 'category' => 'human_resources'],
        ['name' => 'view_positions', 'category' => 'human_resources'],
        ['name' => 'manage_positions', 'category' => 'human_resources'],
        ['name' => 'view_marketers', 'category' => 'human_resources'],
        ['name' => 'manage_marketers', 'category' => 'human_resources'],
        ['name' => 'view_offices', 'category' => 'human_resources'],
        ['name' => 'manage_offices', 'category' => 'human_resources'],

        // Customers, contracts, and their service catalog
        ['name' => 'view_customers', 'category' => 'customer_contracts'],
        ['name' => 'create_customers', 'category' => 'customer_contracts'],
        ['name' => 'edit_customers', 'category' => 'customer_contracts'],
        ['name' => 'delete_customers', 'category' => 'customer_contracts'],
        ['name' => 'manage_customer_services', 'category' => 'customer_contracts'],
        ['name' => 'view_contracts', 'category' => 'customer_contracts'],
        ['name' => 'create_contracts', 'category' => 'customer_contracts'],
        ['name' => 'edit_contracts', 'category' => 'customer_contracts'],
        ['name' => 'cancel_contracts', 'category' => 'customer_contracts'],
        ['name' => 'delete_contracts', 'category' => 'customer_contracts'],
        ['name' => 'download_contracts', 'category' => 'customer_contracts'],
        ['name' => 'view_contract_templates', 'category' => 'customer_contracts'],
        ['name' => 'manage_contract_templates', 'category' => 'customer_contracts'],
        ['name' => 'create_contract_template_versions', 'category' => 'educational_visa'],
        ['name' => 'activate_contract_template_versions', 'category' => 'educational_visa'],
        ['name' => 'view_contract_policies', 'category' => 'customer_contracts'],
        ['name' => 'manage_contract_policies', 'category' => 'customer_contracts'],
        ['name' => 'view_contract_types', 'category' => 'customer_contracts'],
        ['name' => 'manage_contract_types', 'category' => 'customer_contracts'],
        ['name' => 'view_companies', 'category' => 'customer_contracts'],
        ['name' => 'create_companies', 'category' => 'customer_contracts'],
        ['name' => 'edit_companies', 'category' => 'customer_contracts'],
        ['name' => 'delete_companies', 'category' => 'customer_contracts'],
        ['name' => 'view_services', 'category' => 'customer_contracts'],
        ['name' => 'manage_services', 'category' => 'customer_contracts'],

        // Finance
        ['name' => 'view_invoices', 'category' => 'finance'],
        ['name' => 'create_invoices', 'category' => 'finance'],
        ['name' => 'edit_invoices', 'category' => 'finance'],
        ['name' => 'void_invoices', 'category' => 'finance'],
        ['name' => 'delete_invoices', 'category' => 'finance'],
        ['name' => 'view_payment_details', 'category' => 'finance'],
        ['name' => 'create_payment_details', 'category' => 'finance'],
        ['name' => 'edit_payment_details', 'category' => 'finance'],
        ['name' => 'delete_payment_details', 'category' => 'finance'],
        ['name' => 'view_incomes', 'category' => 'finance'],
        ['name' => 'create_incomes', 'category' => 'finance'],
        ['name' => 'edit_incomes', 'category' => 'finance'],
        ['name' => 'delete_incomes', 'category' => 'finance'],
        ['name' => 'view_expenses', 'category' => 'finance'],
        ['name' => 'create_expenses', 'category' => 'finance'],
        ['name' => 'edit_expenses', 'category' => 'finance'],
        ['name' => 'delete_expenses', 'category' => 'finance'],
        ['name' => 'view_recurring_events', 'category' => 'finance'],
        ['name' => 'create_recurring_events', 'category' => 'finance'],
        ['name' => 'edit_recurring_events', 'category' => 'finance'],
        ['name' => 'delete_recurring_events', 'category' => 'finance'],
        ['name' => 'manage_recurring_events', 'category' => 'finance'],
        ['name' => 'execute_recurring_events', 'category' => 'finance'],
        ['name' => 'create_recurring_incomes', 'category' => 'finance'],
        ['name' => 'create_recurring_expenses', 'category' => 'finance'],
        ['name' => 'create_recurring_tasks', 'category' => 'task_management'],
        ['name' => 'view_assets', 'category' => 'finance'],
        ['name' => 'create_assets', 'category' => 'finance'],
        ['name' => 'edit_assets', 'category' => 'finance'],
        ['name' => 'delete_assets', 'category' => 'finance'],
        ['name' => 'view_currencies', 'category' => 'finance'],
        ['name' => 'manage_currencies', 'category' => 'finance'],
        ['name' => 'view_financial_reports', 'category' => 'finance'],
        ['name' => 'export_financial_reports', 'category' => 'finance'],

        // Task management. Assigned scope is based on the task/board member
        // relationship; all scope is supervisory. These scopes are enforced
        // by backend policies, never by the frontend alone.
        ['name' => 'view_projects', 'category' => 'task_management'],
        ['name' => 'create_projects', 'category' => 'task_management'],
        ['name' => 'edit_projects', 'category' => 'task_management'],
        ['name' => 'delete_projects', 'category' => 'task_management'],
        ['name' => 'view_assigned_boards', 'category' => 'task_management'],
        ['name' => 'view_all_boards', 'category' => 'task_management'],
        ['name' => 'create_boards', 'category' => 'task_management'],
        ['name' => 'edit_assigned_boards', 'category' => 'task_management'],
        ['name' => 'edit_all_boards', 'category' => 'task_management'],
        ['name' => 'delete_assigned_boards', 'category' => 'task_management'],
        ['name' => 'delete_all_boards', 'category' => 'task_management'],
        ['name' => 'reorder_boards', 'category' => 'task_management'],
        ['name' => 'assign_board_members', 'category' => 'task_management'],
        ['name' => 'view_assigned_tasks', 'category' => 'task_management'],
        ['name' => 'view_all_tasks', 'category' => 'task_management'],
        ['name' => 'create_tasks', 'category' => 'task_management'],
        ['name' => 'edit_assigned_tasks', 'category' => 'task_management'],
        ['name' => 'edit_all_tasks', 'category' => 'task_management'],
        ['name' => 'delete_assigned_tasks', 'category' => 'task_management'],
        ['name' => 'delete_all_tasks', 'category' => 'task_management'],
        ['name' => 'move_assigned_tasks', 'category' => 'task_management'],
        ['name' => 'move_all_tasks', 'category' => 'task_management'],
        ['name' => 'assign_task_members', 'category' => 'task_management'],
        ['name' => 'manage_task_dependencies', 'category' => 'task_management'],
        ['name' => 'manage_task_tags', 'category' => 'task_management'],
        ['name' => 'view_task_tags', 'category' => 'task_management'],
        ['name' => 'manage_task_tags_catalog', 'category' => 'task_management'],
        ['name' => 'create_subtasks_on_assigned_tasks', 'category' => 'task_management'],
        ['name' => 'edit_own_subtasks', 'category' => 'task_management'],
        ['name' => 'edit_all_subtasks', 'category' => 'task_management'],
        ['name' => 'delete_own_subtasks', 'category' => 'task_management'],
        ['name' => 'delete_all_subtasks', 'category' => 'task_management'],
        ['name' => 'create_comments_on_assigned_tasks', 'category' => 'task_management'],
        ['name' => 'edit_own_comments', 'category' => 'task_management'],
        ['name' => 'edit_all_comments', 'category' => 'task_management'],
        ['name' => 'delete_own_comments', 'category' => 'task_management'],
        ['name' => 'delete_all_comments', 'category' => 'task_management'],
        ['name' => 'override_edit_window', 'category' => 'task_management'],

        // Other operational records
        ['name' => 'view_country', 'category' => 'educational_visa'],
        ['name' => 'create_country', 'category' => 'educational_visa'],
        ['name' => 'edit_country', 'category' => 'educational_visa'],
        ['name' => 'delete_country', 'category' => 'educational_visa'],
        ['name' => 'view_education_level', 'category' => 'educational_visa'],
        ['name' => 'create_education_level', 'category' => 'educational_visa'],
        ['name' => 'edit_education_level', 'category' => 'educational_visa'],
        ['name' => 'delete_education_level', 'category' => 'educational_visa'],
        ['name' => 'view_language', 'category' => 'educational_visa'],
        ['name' => 'create_language', 'category' => 'educational_visa'],
        ['name' => 'edit_language', 'category' => 'educational_visa'],
        ['name' => 'delete_language', 'category' => 'educational_visa'],
        ['name' => 'view_package_type', 'category' => 'educational_visa'],
        ['name' => 'create_package_type', 'category' => 'educational_visa'],
        ['name' => 'edit_package_type', 'category' => 'educational_visa'],
        ['name' => 'delete_package_type', 'category' => 'educational_visa'],
        ['name' => 'view_university', 'category' => 'educational_visa'],
        ['name' => 'create_university', 'category' => 'educational_visa'],
        ['name' => 'edit_university', 'category' => 'educational_visa'],
        ['name' => 'delete_university', 'category' => 'educational_visa'],
        ['name' => 'view_visitors', 'category' => 'operations'],
        ['name' => 'create_visitors', 'category' => 'operations'],
        ['name' => 'edit_visitors', 'category' => 'operations'],
        ['name' => 'delete_visitors', 'category' => 'operations'],
        ['name' => 'view_own_notifications', 'category' => 'operations'],
        ['name' => 'update_own_notifications', 'category' => 'operations'],
        ['name' => 'delete_own_notifications', 'category' => 'operations'],
        ['name' => 'send_notifications', 'category' => 'operations'],
        ['name' => 'upload_files', 'category' => 'operations'],
        ['name' => 'view_files', 'category' => 'operations'],
        ['name' => 'download_files', 'category' => 'operations'],
        ['name' => 'delete_files', 'category' => 'operations'],
    ];

    /** @return array<int, string> */
    public static function catalogNames(): array
    {
        return array_column(self::CATALOG, 'name');
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            // Remove only the original mock names. We deliberately preserve
            // unrecognized rows until Phase 2 retires the mutable catalog API.
            // Most legacy names evolved into real catalog names. Delete only
            // the obsolete mock rows; deleting a canonical name here would
            // sever its role grants and create needless foreign-key churn.
            $obsoleteLegacyNames = array_values(array_diff(
                self::LEGACY_MOCK_PERMISSIONS,
                self::catalogNames(),
            ));

            if ($obsoleteLegacyNames !== []) {
                Permission::query()
                    ->whereIn('name', $obsoleteLegacyNames)
                    ->delete();
            }

            foreach (self::CATALOG as $permission) {
                $row = Permission::firstOrNew(['name' => $permission['name']]);
                $row->fill(['category' => $permission['category'], 'guard_name' => 'api']);
                $row->save();
            }
        });
    }
}
