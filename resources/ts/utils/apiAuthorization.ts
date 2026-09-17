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
  'employees': { view: 'view_employees', create: 'create_employees', update: 'edit_employees', delete: 'delete_employees' },
  'positions': { view: 'view_positions', create: 'manage_positions', update: 'manage_positions', delete: 'manage_positions' },
  'marketers': { view: 'view_marketers', create: 'manage_marketers', update: 'manage_marketers', delete: 'manage_marketers' },
  'offices': { view: 'view_offices', create: 'manage_offices', update: 'manage_offices', delete: 'manage_offices' },
  'salaries': { view: 'view_salaries', create: 'create_salaries', update: 'edit_salaries', delete: 'delete_salaries' },
  'invoices': { view: 'view_invoices', create: 'create_invoices', update: 'edit_invoices', delete: 'delete_invoices' },
  'payment-details': { view: 'view_payment_details', create: 'create_payment_details', update: 'edit_payment_details', delete: 'delete_payment_details' },
  'expenses': { view: 'view_expenses', create: 'create_expenses', update: 'edit_expenses', delete: 'delete_expenses' },
  'incomes': { view: 'view_incomes', create: 'create_incomes', update: 'edit_incomes', delete: 'delete_incomes' },
  'visitors': { view: 'view_visitors', create: 'create_visitors', update: 'edit_visitors', delete: 'delete_visitors' },
  'customers': { view: 'view_customers', create: 'create_customers', update: 'edit_customers', delete: 'delete_customers' },
  'services': { view: 'view_services', create: 'manage_services', update: 'manage_services', delete: 'manage_services' },
  'currencies': { view: 'view_currencies', create: 'manage_currencies', update: 'manage_currencies', delete: 'manage_currencies' },
  'settings': { view: 'view_settings', create: 'edit_settings', update: 'edit_settings', delete: 'edit_settings' },
  'contract-templates': { view: 'view_contract_templates', create: 'manage_contract_templates', update: 'manage_contract_templates', delete: 'manage_contract_templates' },
  'contracts': { view: 'view_contracts', create: 'create_contracts', update: 'edit_contracts', delete: 'delete_contracts' },
  'companies': { view: 'view_companies', create: 'create_companies', update: 'edit_companies', delete: 'delete_companies' },
  'assets': { view: 'view_assets', create: 'create_assets', update: 'edit_assets', delete: 'delete_assets' },
  'projects': { view: 'view_projects', create: 'create_projects', update: 'edit_projects', delete: 'delete_projects' },
  'applicants': { view: 'view_applicants', create: 'create_applicants', update: 'edit_applicants', delete: 'delete_applicants' },
  'applicant-stages': { view: 'view_applicants', create: 'view_applicants', update: 'view_applicants', delete: 'view_applicants' },
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
    'GET /invoices/commission-candidates': ['view_invoices'],
    'GET /commissions/accruals': ['view_commissions'],
    'GET /commissions/summary': ['view_commissions'],
    'POST /commissions/adjustments': ['manage_commission_settlements'],
    'GET /commission-settlements': ['view_commissions'],
    'GET /commission-settlements/:id': ['view_commissions'],
    'POST /commission-settlements': ['manage_commission_settlements'],
    'POST /commission-settlements/:id/approve': ['approve_commissions'],
    'POST /commission-settlements/:id/pay': ['pay_commissions'],
    'POST /commission-settlements/:id/cancel': ['manage_commission_settlements'],
    'GET /outsourcing/accruals': ['view_outsourcing'],
    'GET /outsourcing/settlements': ['view_outsourcing'],
    'POST /outsourcing/adjustments': ['manage_outsourcing_settlements'],
    'POST /outsourcing/settlements': ['manage_outsourcing_settlements'],
    'POST /outsourcing/settlements/:id/approve': ['approve_outsourcing'],
    'POST /outsourcing/settlements/:id/pay': ['pay_outsourcing'],
    'POST /outsourcing/settlements/:id/cancel': ['manage_outsourcing_settlements'],
    'GET /kanban': ['view_all_boards', 'view_assigned_boards'],
    'GET /boards': ['view_all_boards', 'view_assigned_boards'],
    'POST /boards': ['create_boards'],
    'PUT /boards/reorder': ['reorder_boards'],
    'PUT /boards/:id/members': ['assign_board_members'],
    'PUT /boards/:id': ['edit_all_boards', 'edit_assigned_boards'],
    'DELETE /boards/:id': ['delete_all_boards', 'delete_assigned_boards'],
    'GET /task-assignees': ['assign_task_members'],
    'POST /tasks': ['create_tasks'],
    'PUT /tasks/:id': ['edit_all_tasks', 'edit_assigned_tasks'],
    'DELETE /tasks/:id': ['delete_all_tasks', 'delete_assigned_tasks'],
    'PUT /tasks/:id/move': ['move_all_tasks', 'move_assigned_tasks'],
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
    'GET /paid-time-offs': ['view_paid_time_off'],
    'PATCH /paid-time-offs/grant': ['grant_paid_time_off'],
    'GET /my-paid-time-offs': ['request_own_paid_time_off'],
    'POST /my-paid-time-offs': ['request_own_paid_time_off'],
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
    'GET /salaries/granted-bonuses': ['view_bonuses'],
    'GET /salaries/:id/deductions': ['view_salary_deductions'],
    'POST /salaries/:id/deductions': ['create_salary_deductions'],
    'PATCH /salaries/:id/deductions/:id': ['edit_salary_deductions'],
    'DELETE /salaries/:id/deductions/:id': ['delete_salary_deductions'],
    'PUT /invoices/:id/void': ['void_invoices'],
    'POST /customers/:id/services': ['manage_customer_services'],
    'PUT /customers/:id/services/:id': ['manage_customer_services'],
    'DELETE /customers/:id/services/:id': ['manage_customer_services'],
    'PATCH /crm/customer/:id/notes': ['view_customers'],
    'PATCH /crm/visitor/:id/notes': ['view_visitors'],
    'PATCH /crm/lead/:id/notes': ['view_visitors'],
    'GET /attendances': ['view_attendance'],
    'GET /attendances/:id/daily-report': ['view_attendance'],
    'GET /workday/status': ['manage_own_workday'],
    'POST /workday/start': ['manage_own_workday'],
    'POST /workday/end': ['submit_own_daily_report'],
    'GET /workday/geofence': ['manage_own_workday'],
    'POST /workday/geofence': ['manage_own_workday'],
    'POST /attendances': ['record_attendance'],
    'GET /attendances/unmarked-staff': ['view_unmarked_attendance_staff'],
    'DELETE /attendances/:id': ['delete_attendance'],
    'PUT /contracts/:id/cancel': ['cancel_contracts'],
    'POST /applicants/:id/change-stage': ['change_applicant_stage'],
    'GET /contract-placeholders': ['view_contract_templates', 'view_contracts'],
    'POST /contract-types': ['manage_contract_types'],
    'DELETE /contract-types/:id': ['manage_contract_types'],
    'PATCH /notifications/mark-all-read': ['update_own_notifications'],
    'GET /audit-logs': ['view_audit_logs'],
    'GET /audit-logs/pending-count': ['view_audit_logs'],
    'GET /audit-logs/deleted': ['view_deleted_records'],
    'GET /audit-logs/:id': ['view_audit_logs'],
    'PATCH /audit-logs/:id/review': ['review_audit_logs'],
    'POST /audit-logs/bulk-review': ['review_audit_logs'],
    'POST /audit-logs/:id/revert': ['revert_audit_changes'],
    'GET /audit-resources': ['view_audit_logs'],
    'PATCH /audit-resources/:id': ['manage_audit_scope'],
    'GET /dashboard/stats': ['view_dashboard'],
    'GET /dashboard/chart-data': ['view_dashboard'],
    'GET /educational-dashboard': ['view_dashboard'],
    'GET /educational-services/options': ['view_services'],
    'GET /educational-services/:id': ['view_services'],
    'GET /educational-services/:id/:id': ['view_services'],
    'POST /educational-services/:id': ['manage_customer_services'],
    'PUT /educational-services/:id/:id': ['manage_customer_services'],
    'DELETE /educational-services/:id/:id': ['manage_customer_services'],
    'GET /educational-bonus-sources': ['view_bonuses'],
    'POST /educational-bonus-sources': ['manage_commission_settlements'],
    'PUT /educational-bonus-sources/:id': ['manage_commission_settlements'],
    'GET /reports/invoices': ['view_financial_reports'],
    'GET /reports/expences': ['view_financial_reports'],
    'GET /reports/expenses': ['view_financial_reports'],
    'GET /reports/salaries': ['view_salaries'],
    'GET /reports/catalog': ['view_reports'],
    'GET /educational-reports/applicants': ['view_reports'],
    'GET /educational-reports/employee-performance': ['view_reports'],
    'GET /educational-reports/sales': ['view_reports'],
    'GET /educational-reports/visa-outcomes': ['view_reports'],
    'GET /educational-reports/applicants/export': ['export_reports'],
    'GET /educational-reports/employee-performance/export': ['export_reports'],
    'GET /educational-reports/sales/export': ['export_reports'],
    'GET /educational-reports/visa-outcomes/export': ['export_reports'],
    'GET /automation/bonus-rules': ['view_bonuses'],
    'PUT /automation/bonus-rules': ['manage_bonus_rules'],
    'GET /automation/runs': ['view_automation_runs'],
    'GET /automation/analytics': ['view_analytics'],
    'PATCH /tasks/:id/bonus-review': ['edit_all_tasks'],
    'POST /tasks/:id/attachments': ['edit_all_tasks', 'edit_assigned_tasks'],
    'DELETE /tasks/:id/attachments/:id': ['edit_all_tasks', 'edit_assigned_tasks'],
    'GET /tasks/:id/attachments/:id/download': ['view_all_tasks', 'view_assigned_tasks'],
    'GET /reports/incomes': ['view_financial_reports'],
    'GET /reports/visitors': ['view_visitors'],
    'GET /reports/invoices/export': ['export_financial_reports'],
    'POST /files/upload': ['upload_files'],
    'GET /files/list': ['view_files'],
    'GET /files/view/:id': ['view_files'],
    'GET /files/download/:id': ['download_files'],
    'DELETE /files/delete': ['delete_files'],
  }

  if (path.startsWith('/employees/:id/paid-time-offs')) {
    if (requestMethod === 'GET')
      return ['view_paid_time_off']
    if (requestMethod === 'POST')
      return ['create_paid_time_off']
    if (requestMethod === 'PUT')
      return ['edit_paid_time_off']
    if (requestMethod === 'DELETE')
      return ['delete_paid_time_off']
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

  const educational = path.match(/^\/educational-master-data\/(countries|education-levels|languages|package-types|universities)(?:\/:id)?$/)
  if (educational) {
    const key = ({ countries: 'country', 'education-levels': 'education_level', languages: 'language', 'package-types': 'package_type', universities: 'university' } as Record<string, string>)[educational[1]]
    const action = requestMethod === 'GET' ? 'view' : requestMethod === 'POST' ? 'create' : requestMethod === 'DELETE' ? 'delete' : 'edit'
    return [`${action}_${key}`]
  }

  const educationalService = path.match(/^\/educational-services\/(scholarships|test-offers)(?:\/:id)?$/)
  if (educationalService) {
    return requestMethod === 'GET'
      ? ['view_services']
      : ['manage_customer_services']
  }

  return special[`${requestMethod} ${path}`] ?? restPermission(requestMethod, path)
}

export const isApiRequestAllowed = (method: string | undefined, url: string | undefined, payload?: unknown) => {
  const required = permissionsForApiRequest(method, url, payload)

  return required === null || required.some(canAccess)
}
