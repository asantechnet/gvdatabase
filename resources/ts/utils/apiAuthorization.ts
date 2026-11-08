import { canAccess } from '@/utils/authorization'

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

interface ResourcePermissions {
  view: string
  create: string
  update: string | string[]
  delete: string
}

const resources: Record<string, ResourcePermissions> = {
  'users': { view: 'view_users', create: 'create_users', update: 'edit_users', delete: 'delete_users' },
  'employees': { view: 'view_employees', create: 'create_employees', update: ['edit_employees', 'edit_own_employee_profile'], delete: 'delete_employees' },
  'departments': { view: 'view_departments', create: 'manage_departments', update: 'manage_departments', delete: 'manage_departments' },
  'positions': { view: 'view_positions', create: 'manage_positions', update: 'manage_positions', delete: 'manage_positions' },
  'offices': { view: 'view_offices', create: 'manage_offices', update: 'manage_offices', delete: 'manage_offices' },
  'salaries': { view: 'view_salaries', create: 'create_salaries', update: 'edit_salaries', delete: 'delete_salaries' },
  'expenses': { view: 'view_expenses', create: 'create_expenses', update: 'edit_expenses', delete: 'delete_expenses' },
  'incomes': { view: 'view_incomes', create: 'create_incomes', update: 'edit_incomes', delete: 'delete_incomes' },
  'visitors': { view: 'view_visitors', create: 'create_visitors', update: 'edit_visitors', delete: 'delete_visitors' },
  'currencies': { view: 'view_currencies', create: 'manage_currencies', update: 'manage_currencies', delete: 'manage_currencies' },
  'settings': { view: 'view_settings', create: 'edit_settings', update: 'edit_settings', delete: 'edit_settings' },
  'contract-templates': { view: 'view_contract_templates', create: 'manage_contract_templates', update: 'manage_contract_templates', delete: 'manage_contract_templates' },
  'contracts': { view: 'view_contracts', create: 'create_contracts', update: 'edit_contracts', delete: 'delete_contracts' },
  'assets': { view: 'view_assets', create: 'create_assets', update: 'edit_assets', delete: 'delete_assets' },
  'applicants': { view: 'view_applicants', create: 'create_applicants', update: 'edit_applicants', delete: 'delete_applicants' },
  'applicant-stages': { view: 'view_applicant_stages', create: 'manage_applicant_stages', update: 'manage_applicant_stages', delete: 'manage_applicant_stages' },
  'applicants-pipeline': { view: 'view_applicants', create: 'view_applicants', update: 'view_applicants', delete: 'view_applicants' },
}

const normalizedPath = (url = '') => `/${url.replace(/^\/?api\/?/, '').replace(/^\//, '').split('?')[0]}`
  .replace(/\/\d+(?=\/|$)/g, '/:id')

const restPermission = (method: Method, path: string): string[] | null => {
  const match = path.match(/^\/([^/]+)(?:\/:id)?$/)
  const resource = match?.[1] && resources[match[1]]
  if (!resource)
    return null

  if (method === 'GET')
    return [resource.view]
  if (method === 'POST' && !path.endsWith('/:id'))
    return [resource.create]
  if (method === 'PUT' || method === 'PATCH')
    return Array.isArray(resource.update) ? resource.update : [resource.update]
  if (method === 'DELETE')
    return [resource.delete]

  return null
}

/**
 * Mirrors API-route permissions before a request is sent. The server remains
 * the source of truth (especially for own/assigned resource checks), while
 * this prevents controls or stale UI from sending an obviously forbidden call.
 */
export const permissionsForApiRequest = (method: string | undefined, url: string | undefined, payload?: unknown): string[] | null => {
  const requestMethod = (method ?? 'GET').toUpperCase() as Method
  const path = normalizedPath(url)

  const special: Record<string, string[]> = {
    // Record-level audit state for register rows. Quiet, bounded and gated by
    // the same read grant as the Audits page.
    'GET /audit-logs/row-trails': ['view_audit_logs'],
    // Printable receipt payload for a single income record.
    'GET /incomes/:id/receipt': ['view_incomes'],
    'GET /incomes/applicants': ['view_incomes'],
    'PUT /incomes/applicant-contracts/:id/payment-override': ['override_contract_payments'],
    // Ledger rows are readable through the legacy page grant or through an
    // explicit own/all scope. The server narrows an own-scoped list to the
    // caller's own recorded rows.
    'GET /incomes': ['view_incomes', 'view_own_finance_records', 'view_all_finance_records'],
    'GET /incomes/:id': ['view_incomes', 'view_own_finance_records', 'view_all_finance_records'],
    'POST /incomes': ['create_incomes', 'create_draft_finance_records'],
    'PUT /incomes/:id': ['edit_incomes', 'create_draft_finance_records'],
    'DELETE /incomes/:id': ['delete_incomes', 'create_draft_finance_records'],
    'PATCH /incomes/:id/approve': ['approve_draft_finance_records'],
    'PATCH /incomes/:id/reject': ['approve_draft_finance_records'],
    'GET /expenses': ['view_expenses', 'view_own_finance_records', 'view_all_finance_records'],
    'GET /expenses/:id': ['view_expenses', 'view_own_finance_records', 'view_all_finance_records'],
    'POST /expenses': ['create_expenses', 'create_draft_finance_records'],
    'PUT /expenses/:id': ['edit_expenses', 'create_draft_finance_records'],
    'DELETE /expenses/:id': ['delete_expenses', 'create_draft_finance_records'],
    'PATCH /expenses/:id/approve': ['approve_draft_finance_records'],
    'PATCH /expenses/:id/reject': ['approve_draft_finance_records'],
    'GET /kanban': ['view_all_boards', 'view_assigned_boards', 'create_personal_tasks'],
    'GET /boards': ['view_all_boards', 'view_assigned_boards', 'create_personal_tasks'],
    'GET /task-assignees': ['assign_task_members'],
    'POST /tasks': ['create_tasks', 'create_personal_tasks'],
    'PUT /tasks/:id': ['edit_all_tasks', 'edit_assigned_tasks', 'create_personal_tasks'],
    'DELETE /tasks/:id': ['delete_all_tasks', 'delete_assigned_tasks', 'create_personal_tasks'],
    'PUT /tasks/:id/move': ['move_all_tasks', 'move_assigned_tasks', 'create_personal_tasks'],
    'POST /tasks/:id/subtasks': ['create_subtasks_on_assigned_tasks'],
    'PUT /tasks/:id/subtasks/:id': ['edit_all_subtasks', 'edit_own_subtasks'],
    'DELETE /tasks/:id/subtasks/:id': ['delete_all_subtasks', 'delete_own_subtasks'],
    'POST /tasks/:id/comments': ['create_comments_on_assigned_tasks'],
    'PUT /tasks/:id/comments/:id': ['edit_all_comments', 'edit_own_comments'],
    'DELETE /tasks/:id/comments/:id': ['delete_all_comments', 'delete_own_comments'],
    'GET /permissions': ['view_permissions'],
    'POST /permissions': ['manage_role_permissions'],
    'PUT /permissions/:id': ['manage_role_permissions'],
    'DELETE /permissions/:id': ['manage_role_permissions'],
    'GET /leave-requests': ['view_leave_requests'],
    'PATCH /leave-requests/grant': ['grant_leave_requests'],
    'GET /my-leave-requests': ['request_own_leave'],
    'POST /my-leave-requests': ['request_own_leave'],
    'GET /roles': ['view_roles'],
    'PUT /roles/:id/permissions': ['manage_role_permissions'],
    'PUT /users/:id/status': ['manage_user_status'],
    'POST /auth/account/deactivation-requests': ['request_account_deactivation'],
    'DELETE /auth/account/deactivation-requests/:id': ['cancel_own_deactivation_request'],
    'GET /account-deactivation-requests': ['view_account_deactivation_requests'],
    'PATCH /account-deactivation-requests/:id/review': ['review_account_deactivation_requests'],
    'POST /users/:id/reactivate': ['reactivate_user_accounts'],
    'GET /tags': ['view_task_tags'],
    'POST /tags': ['manage_task_tags_catalog'],
    'PUT /tags/:id': ['manage_task_tags_catalog'],
    'DELETE /tags/:id': ['manage_task_tags_catalog'],
    // Off days: announcing/editing needs manage_off_days, activating or
    // removing an approved holiday needs the dedicated approve_off_days grant.
    'GET /off-days': ['view_off_days'],
    'POST /off-days': ['manage_off_days'],
    'PUT /off-days/:id': ['manage_off_days'],
    'DELETE /off-days/:id': ['manage_off_days'],
    'PATCH /off-days/:id/approve': ['approve_off_days'],
    'PATCH /off-days/:id/reject': ['approve_off_days'],
    'GET /salaries/granted-bonuses': ['view_bonuses'],
    'GET /salaries/attendance-breakdown': ['view_salaries'],
    'POST /salaries/generate': ['create_salaries'],
    'GET /salaries/:id/deductions': ['view_salary_deductions'],
    'POST /salaries/:id/deductions': ['create_salary_deductions'],
    'PATCH /salaries/:id/deductions/:id': ['edit_salary_deductions'],
    'DELETE /salaries/:id/deductions/:id': ['delete_salary_deductions'],
    'PATCH /crm/visitor/:id/notes': ['view_visitors'],
    'GET /attendances': ['view_attendance'],
    'GET /attendances/:id/daily-report': ['view_attendance'],
    'GET /workday/status': ['manage_own_workday'],
    'POST /workday/start': ['manage_own_workday'],
    'POST /workday/end': ['submit_own_daily_report'],
    'GET /workday/geofence': ['manage_own_workday'],
    'POST /workday/geofence': ['manage_own_workday'],
    'POST /attendances': ['record_attendance'],
    'POST /attendances/:id/checkout-correction': ['record_attendance'],
    'GET /attendances/unmarked-staff': ['view_unmarked_attendance_staff'],
    'GET /attendances/off-day': ['view_attendance'],
    'DELETE /attendances/:id': ['delete_attendance'],
    'PUT /contracts/:id/cancel': ['cancel_contracts'],
    'POST /contracts/preview': ['create_contracts', 'edit_contracts'],
    'POST /contracts/scholarship': ['create_contracts'],
    'POST /contracts/test_offer': ['create_contracts'],
    'POST /contracts/academy': ['create_contracts'],
    'POST /contracts/private': ['create_contracts'],
    'GET /contracts/private/options': ['create_contracts'],
    'POST /contract-templates/built-in/private': ['manage_contract_templates'],
    'GET /contracts/scholarship/options': ['create_contracts'],
    'GET /contracts/test_offer/options': ['create_contracts'],
    'GET /contracts/academy/options': ['create_contracts'],
    'POST /visitors/:id/convert-to-applicant': ['create_applicants'],
    'PUT /visitors/:id/follow-up': ['edit_visitors'],
    'PATCH /crm/applicant/:id/notes': ['view_applicants'],
    'POST /applicants/:id/change-stage': ['change_applicant_stage'],
    'GET /applicant-portal': ['view_own_applicant_portal'],
    'PUT /applicant-portal/profile': ['edit_own_applicant_profile'],
    'GET /applicant-portal/attachments': ['view_own_applicant_portal'],
    'POST /applicant-portal/attachments': ['edit_own_applicant_profile'],
    'GET /applicant-portal/attachments/:id/preview': ['view_own_applicant_portal'],
    'DELETE /applicant-portal/attachments/:id': ['edit_own_applicant_profile'],
    'POST /applicants/:id/portal-invitation': ['invite_applicant_portal'],
    'GET /applicants/:id/universities': ['view_applicants'],
    'POST /applicants/:id/universities': ['edit_applicants'],
    'PUT /applicants/:id/universities/:id': ['edit_applicants'],
    'DELETE /applicants/:id/universities/:id': ['edit_applicants'],
    'GET /contract-placeholders': ['view_contract_templates', 'view_contracts'],
    'POST /contract-templates/built-in/scholarship': ['manage_contract_templates'],
    'POST /contract-templates/built-in/test_offer': ['manage_contract_templates'],
    'POST /contract-templates/built-in/academy': ['manage_contract_templates'],
    'GET /contract-templates/:id/versions': ['view_contract_templates'],
    'GET /contract-templates/:id/versions/:id': ['view_contract_templates'],
    'POST /contract-templates/:id/versions': ['create_contract_template_versions'],
    'PUT /contract-templates/:id/versions/:id': ['edit_draft_contract_template_versions'],
    'POST /contract-templates/:id/versions/:id/activate': ['activate_contract_template_versions'],
    'POST /contract-templates/:id/versions/:id/archive': ['archive_contract_template_versions'],
    'POST /contract-types': ['manage_contract_types'],
    'DELETE /contract-types/:id': ['manage_contract_types'],
    'PATCH /notifications/mark-all-read': ['update_own_notifications'],
    'GET /audit-logs': ['view_audit_logs'],
    'GET /audit-logs/pending-count': ['view_audit_logs'],
    'GET /audit-logs/heartbeat': ['view_audit_logs'],
    'GET /audit-logs/deleted': ['view_deleted_records'],
    'GET /audit-logs/:id': ['view_audit_logs'],
    'PATCH /audit-logs/:id/review': ['review_audit_logs'],
    'POST /audit-logs/bulk-review': ['review_audit_logs'],
    'POST /audit-logs/:id/revert': ['revert_audit_changes'],
    'GET /audit-resources': ['view_audit_logs'],
    'PATCH /audit-resources/:id': ['manage_audit_scope'],
    'GET /dashboard/stats': ['view_dashboard'],
    'GET /dashboard/chart-data': ['view_dashboard'],
    'GET /employees/assignment-options': ['view_applicants', 'view_visitors', 'create_contracts', 'view_financial_reports', 'view_employees'],
    'GET /educational-dashboard': ['view_agency_overview'],
    'GET /educational-bonus-sources': ['view_bonuses'],
    'POST /educational-bonus-sources': ['manage_educational_bonuses'],
    'PUT /educational-bonus-sources/:id': ['manage_educational_bonuses'],
    'GET /reports/expences': ['view_financial_reports'],
    'GET /reports/expenses': ['view_financial_reports'],
    'GET /reports/salaries': ['view_salaries'],
    'GET /reports/catalog': ['view_reports'],
    'GET /educational-reports/applicants': ['view_visa_reports'],
    'GET /educational-reports/employee-performance': ['view_visa_reports'],
    'GET /educational-reports/sales': ['view_visa_reports'],
    'GET /educational-reports/visa-outcomes': ['view_visa_reports'],
    'GET /educational-reports/applicants/export': ['view_visa_reports'],
    'GET /educational-reports/employee-performance/export': ['view_visa_reports'],
    'GET /educational-reports/sales/export': ['view_visa_reports'],
    'GET /educational-reports/visa-outcomes/export': ['view_visa_reports'],
    'GET /automation/bonus-rules': ['view_bonuses'],
    'PUT /automation/bonus-rules': ['manage_bonus_rules'],
    'GET /automation/runs': ['view_automation_runs'],
    'GET /automation/analytics': ['view_analytics'],
    'GET /tasks/:id/bonus-preview': ['edit_all_tasks'],
    'PATCH /tasks/:id/bonus-review': ['edit_all_tasks'],
    'POST /tasks/:id/attachments': ['edit_all_tasks', 'edit_assigned_tasks'],
    'DELETE /tasks/:id/attachments/:id': ['edit_all_tasks', 'edit_assigned_tasks'],
    'GET /tasks/:id/attachments/:id/download': ['view_all_tasks', 'view_assigned_tasks'],
    'GET /reports/incomes': ['view_financial_reports'],
    'GET /reports/visitors': ['view_visitors'],
    'POST /files/upload': ['upload_files'],
    'GET /files/list': ['view_files'],
    'GET /files/view/:id': ['view_files'],
    'GET /files/download/:id': ['download_files'],
    'DELETE /files/delete': ['delete_files'],
  }

  // Shared attachment endpoints (expenses, incomes, staff). The server enforces
  // the exact resource permission; here we only need any of the matching ones.
  if (path.startsWith('/attachments/')) {
    return ['view_expenses', 'view_incomes', 'view_employees', 'edit_expenses', 'edit_incomes', 'edit_employees']
  }

  if (path.startsWith('/employees/:id/leave-requests')) {
    if (requestMethod === 'GET')
      return ['view_leave_requests']
    if (requestMethod === 'POST')
      return ['create_leave_requests']
    if (requestMethod === 'PUT')
      return ['edit_leave_requests']
    if (requestMethod === 'DELETE')
      return ['delete_leave_requests']
  }

  if (path === '/worthy-employees' && requestMethod === 'GET')
    return ['view_bonuses']
  if (path === '/worthy-employees/:id' && requestMethod === 'GET')
    return ['view_bonuses']
  if (path === '/worthy-employees' && requestMethod === 'POST')
    return ['nominate_bonus_candidates']
  if (path === '/worthy-employees/:id' && requestMethod === 'PATCH') {
    const hasGrantChange = typeof payload === 'object' && payload !== null
      && Object.prototype.hasOwnProperty.call(payload, 'isGranted')

    return [hasGrantChange ? 'grant_bonuses' : 'edit_bonus_nominations']
  }
  if (path === '/worthy-employees/:id' && requestMethod === 'DELETE')
    return ['delete_bonus_nominations']

  if (path === '/announcements' && requestMethod === 'GET')
    return ['send_announcements']
  if (path === '/announcements' && requestMethod === 'POST')
    return ['send_announcements']
  if (path === '/announcements/audience-options' && requestMethod === 'GET')
    return ['send_announcements']
  if (path === '/announcements/:id' && requestMethod === 'GET')
    return ['send_announcements']

  if (path === '/notifications/heartbeat' && requestMethod === 'GET')
    return ['view_own_notifications']
  if (path === '/notifications' && requestMethod === 'GET')
    return ['view_own_notifications']
  if (path === '/notifications' && requestMethod === 'POST')
    return ['send_notifications']
  if (path === '/notifications/:id' && requestMethod === 'GET')
    return ['view_own_notifications']
  if (path === '/notifications/:id' && requestMethod === 'DELETE')
    return ['delete_own_notifications']
  if (path === '/notifications/:id' && (requestMethod === 'PUT' || requestMethod === 'PATCH')) {
    const isReadOnly = typeof payload === 'object' && payload !== null
      && Object.keys(payload as Record<string, unknown>).every(key => key === 'is_read')

    return [isReadOnly ? 'update_own_notifications' : 'send_notifications']
  }

  if (path.startsWith('/files/view/'))
    return ['view_files']
  if (path.startsWith('/files/download/'))
    return ['download_files']

  const educational = path.match(/^\/educational-master-data\/(countries|education-levels|languages|universities|semesters|subjects|tests|skills|programs|times|durations|teachers|scholarships|classes)(?:\/:id)?$/)
  if (educational) {
    const key = ({ countries: 'country', 'education-levels': 'education_level', languages: 'language', universities: 'university', semesters: 'semester', subjects: 'subject', tests: 'test', skills: 'skill', programs: 'program', times: 'time', durations: 'duration', teachers: 'teacher', scholarships: 'subject', classes: 'program' } as Record<string, string>)[educational[1]]
    const action = requestMethod === 'GET' ? 'view' : requestMethod === 'POST' ? 'create' : requestMethod === 'DELETE' ? 'delete' : 'edit'
    return [`${action}_${key}`]
  }

  const reportType = path.match(/^\/educational-reports\/([^/]+)(?:\/export)?$/)?.[1]
  if (reportType && requestMethod === 'GET') {
    if (reportType === 'financial')
      return ['view_financial_reports']
    if (reportType === 'employee-performance')
      return ['view_employees']
    return ['view_visa_reports']
  }

  return special[`${requestMethod} ${path}`] ?? restPermission(requestMethod, path)
}

export const isApiRequestAllowed = (method: string | undefined, url: string | undefined, payload?: unknown) => {
  const required = permissionsForApiRequest(method, url, payload)

  if (normalizedPath(url) === '/incomes/applicant-contracts/:id/payment-override')
    return canAccess('view_incomes') && canAccess('override_contract_payments')

  return required === null || required.some(canAccess)
}
