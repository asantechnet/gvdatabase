<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

/** Seeds conservative defaults; super administrators retain the policy bypass. */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $all = PermissionSeeder::catalogNames();

        $roleIds = Role::query()->pluck('id', 'name')->all();

        // Group-scope permissions shared by every supervisory role.
        $supervisoryScope = [
            'view_subordinate_records', 'manage_subordinate_records',
            'view_departments', 'manage_departments', 'assign_employee_supervisor',
            'view_team_audit_logs', 'view_agency_overview',
        ];

        $grants = [
            // Day-to-day operations, but never identity/role control, hierarchy
            // control, or audit reversal.
            'admin' => array_values(array_diff($all, [
                'view_users', 'create_users', 'edit_users', 'delete_users', 'manage_user_status', 'assign_user_roles',
                'view_roles', 'manage_role_permissions', 'manage_role_hierarchy', 'view_permissions',
                'view_audit_logs', 'view_deleted_records', 'review_audit_logs', 'revert_audit_changes', 'manage_audit_scope',
            ])),

            // Supervises operational work and all task work. Destructive financial and system actions remain excluded.
            'manager' => [
                'view_dashboard',
                'view_reports', 'export_reports', 'view_automation_runs', 'view_analytics',
                'manage_own_workday', 'submit_own_daily_report',
                'request_own_paid_time_off',
                'request_account_deactivation', 'cancel_own_deactivation_request',
                'view_account_deactivation_requests', 'review_account_deactivation_requests', 'reactivate_user_accounts',
                'view_employees', 'view_paid_time_off', 'create_paid_time_off', 'edit_paid_time_off', 'delete_paid_time_off',
                'grant_paid_time_off', 'print_paid_time_off',
                'view_attendance', 'record_attendance', 'view_unmarked_attendance_staff',
                'view_bonuses', 'nominate_bonus_candidates', 'edit_bonus_nominations',
                'view_off_days', 'manage_off_days', 'approve_off_days',
                'view_positions', 'view_offices',
                'view_contracts', 'create_contracts', 'edit_contracts', 'cancel_contracts', 'download_contracts',
                'view_contract_templates', 'manage_contract_templates', 'create_contract_template_versions', 'edit_draft_contract_template_versions', 'activate_contract_template_versions', 'archive_contract_template_versions', 'view_contract_policies',
                'view_contract_types',
                'view_incomes', 'create_incomes', 'edit_incomes',
                'view_expenses', 'create_expenses', 'edit_expenses',
                'view_pending_transactions', 'create_pending_transactions', 'approve_pending_transactions', 'post_pending_transactions',
                'view_recurring_events', 'create_recurring_events', 'edit_recurring_events', 'delete_recurring_events', 'manage_recurring_events', 'execute_recurring_events', 'create_recurring_incomes', 'create_recurring_expenses', 'create_recurring_tasks',
                'view_assets', 'create_assets', 'edit_assets',
                'view_currencies', 'view_financial_reports', 'view_commissions',
                'view_all_boards', 'view_assigned_boards', 'create_boards', 'edit_all_boards', 'edit_assigned_boards', 'reorder_boards', 'assign_board_members',
                'view_all_tasks', 'view_assigned_tasks', 'create_tasks', 'edit_all_tasks', 'edit_assigned_tasks', 'move_all_tasks', 'move_assigned_tasks',
                'assign_task_members', 'manage_task_dependencies', 'manage_task_tags', 'view_task_tags',
                'create_subtasks_on_assigned_tasks', 'edit_all_subtasks', 'edit_own_subtasks', 'delete_all_subtasks', 'delete_own_subtasks',
                'create_comments_on_assigned_tasks', 'edit_all_comments', 'edit_own_comments', 'delete_all_comments', 'delete_own_comments',
                'view_visitors', 'create_visitors', 'edit_visitors',
                'view_own_notifications', 'update_own_notifications', 'delete_own_notifications', 'send_notifications', 'send_all_notifications',
                // Managers may announce to scoped audiences and applicants but
                // not company-wide, and only see their own announcements.
                'send_announcements', 'announce_to_applicants',
                'upload_files', 'view_files', 'download_files',
                'view_country', 'create_country', 'edit_country', 'view_education_level', 'create_education_level', 'edit_education_level',
                'view_language', 'create_language', 'edit_language',
                'view_university', 'create_university', 'edit_university', 'view_semester', 'create_semester', 'edit_semester',
                'view_subject', 'create_subject', 'edit_subject', 'view_test', 'create_test', 'edit_test',
                'view_skill', 'create_skill', 'edit_skill', 'view_program', 'create_program', 'edit_program',
                'view_time', 'create_time', 'edit_time', 'view_duration', 'create_duration', 'edit_duration',
                'view_teacher', 'create_teacher', 'edit_teacher',
                'view_applicants', 'view_all_applicants', 'create_applicants', 'edit_applicants', 'delete_applicants', 'change_applicant_stage', 'view_applicant_stages', 'manage_applicant_stages', 'assign_applicant_employee', 'assign_admission_employee',
                'view_applicant_financials', 'view_applicant_contracts', 'invite_applicant_portal',
                'manage_educational_bonuses', 'view_visa_reports',
            ],

            // Assigned work only. A staff member cannot change a parent task,
            // another person's subtask, or another person's comment.
            'staff' => [
                'view_dashboard', 'request_account_deactivation', 'cancel_own_deactivation_request',
                'manage_own_workday', 'submit_own_daily_report',
                'request_own_paid_time_off',
                'view_assigned_boards', 'view_assigned_tasks', 'view_task_tags',
                'create_subtasks_on_assigned_tasks', 'edit_own_subtasks', 'delete_own_subtasks',
                'create_comments_on_assigned_tasks', 'edit_own_comments', 'delete_own_comments',
                'view_own_notifications', 'update_own_notifications', 'delete_own_notifications',
                'view_contracts', 'create_contracts', 'view_contract_templates',
                'view_country', 'view_education_level', 'view_language', 'view_university', 'view_semester',
                'view_subject', 'view_test', 'view_skill', 'view_program', 'view_time', 'view_duration', 'view_teacher',
                'view_applicants', 'create_applicants', 'edit_applicants', 'change_applicant_stage', 'view_applicant_stages',
                'view_applicant_financials', 'view_applicant_contracts', 'invite_applicant_portal',
                'view_pending_transactions', 'create_pending_transactions',
            ],

            'viewer' => [
                'view_dashboard', 'request_account_deactivation', 'cancel_own_deactivation_request',
                'manage_own_workday', 'submit_own_daily_report',
                'request_own_paid_time_off',
                'view_assigned_boards', 'view_assigned_tasks', 'view_task_tags',
                'view_own_notifications', 'update_own_notifications', 'delete_own_notifications',
            ],

            // Applicants never receive operational permissions. Their portal
            // endpoints additionally resolve the case through applicants.user_id.
            'applicant' => [
                'view_own_applicant_portal', 'edit_own_applicant_profile',
                'view_own_notifications', 'update_own_notifications', 'delete_own_notifications',
            ],
        ];

        // Supervisors inherit the manager toolkit plus the group-scope switch.
        $grants['manager'] = array_values(array_unique([...$grants['manager'], ...$supervisoryScope, 'view_all_contracts']));
        // A team lead supervises the same surface as a manager.
        $grants['team_lead'] = $grants['manager'];

        // A senior counselor can see the team's records but must not change
        // them: only the read scope is granted, never the manage scope.
        $grants['senior_counselor'] = array_values(array_unique([
            ...$grants['staff'], 'view_subordinate_records', 'view_departments', 'view_agency_overview', 'view_visa_reports',
        ]));

        // Visa counselors and case officers work their own assigned records.
        // Counselors own their visitor intake, so they may add and edit
        // visitors; a visitor they create is recorded as their own.
        $grants['counselor'] = array_values(array_unique([
            ...$grants['staff'], 'view_visitors', 'create_visitors', 'edit_visitors',
        ]));
        $grants['senior_counselor'] = array_values(array_unique([
            ...$grants['senior_counselor'], 'view_visitors', 'create_visitors', 'edit_visitors',
        ]));
        $grants['case_officer'] = $grants['staff'];

        // Document officers only handle intake paperwork and files.
        $grants['document_officer'] = [
            'view_dashboard', 'manage_own_workday', 'submit_own_daily_report',
            'request_own_paid_time_off', 'request_account_deactivation', 'cancel_own_deactivation_request',
            'view_own_notifications', 'update_own_notifications', 'delete_own_notifications',
            'view_applicants', 'view_applicant_stages', 'view_applicant_contracts',
            'view_contracts', 'view_contract_templates', 'download_contracts',
            'view_files', 'upload_files', 'download_files',
        ];

        // Finance officers own the money surfaces a branch runs on.
        $grants['finance_officer'] = [
            'override_contract_payments',
            'view_dashboard', 'view_reports', 'view_analytics',
            'manage_own_workday', 'submit_own_daily_report',
            'request_own_paid_time_off', 'request_account_deactivation', 'cancel_own_deactivation_request',
            'view_own_notifications', 'update_own_notifications', 'delete_own_notifications',
            'view_incomes', 'create_incomes', 'edit_incomes',
            'view_expenses', 'create_expenses', 'edit_expenses',
            'view_pending_transactions', 'create_pending_transactions', 'approve_pending_transactions', 'post_pending_transactions',
            'view_currencies', 'view_financial_reports', 'view_contracts', 'download_files',
            'view_commissions', 'manage_commission_settlements', 'approve_commissions', 'pay_commissions',
            // Payroll for finance: the officer records salaries, deductions and
            // advance recovery and prints the monthly payroll register.
            'view_employees', 'view_offices', 'view_positions',
            'view_salaries', 'create_salaries', 'edit_salaries', 'delete_salaries',
            'view_salary_deductions', 'create_salary_deductions', 'edit_salary_deductions', 'delete_salary_deductions',
            'view_bonuses', 'view_off_days',
            'print_paid_time_off',
        ];

        $permissionIds = Permission::query()->whereIn('name', $all)->pluck('id', 'name');

        foreach ($grants as $roleName => $permissionNames) {
            $roleId = $roleIds[$roleName] ?? null;
            if ($roleId === null) {
                continue;
            }
            foreach (array_unique($permissionNames) as $permissionName) {
                $permissionId = $permissionIds[$permissionName] ?? null;
                if ($permissionId) {
                    RolePermission::query()->firstOrCreate([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }
}
