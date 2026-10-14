import { setupLayouts } from 'virtual:meta-layouts'
import type { Component } from 'vue'
import type { RouteRecordRaw } from 'vue-router'

const pageComponents: Record<string, Component> = {
  login: () => import('@/pages/login.vue'),
  forgotPassword: () => import('@/pages/forgot-password.vue'),
  resetPassword: () => import('@/pages/reset-password.vue'),
  createPassword: () => import('@/pages/create-password.vue'),
  verifyEmail: () => import('@/pages/verify-email.vue'),

  // The old Finance page was a mock ledger with hard-coded USD amounts. Route the
  // finance entry point to the real reporting view so its KPIs always come from
  // the current-base calculations on the server.
  finance: () => import('@/pages/reports.vue'),
  income: () => import('@/pages/income.vue'),
  expenses: () => import('@/pages/expenses.vue'),
  commissions: () => import('@/pages/commissions.vue'),
  asset: () => import('@/pages/asset.vue'),
  reports: () => import('@/pages/reports.vue'),
  audits: () => import('@/pages/audits.vue'),
  hr: () => import('@/pages/hr.vue'),
  staffList: () => import('@/pages/staff_list.vue'),
  timesheet: () => import('@/pages/timesheet.vue'),
  paidTimeOffs: () => import('@/pages/paid_time_offs.vue'),
  bonuslist: () => import('@/pages/bonuslist.vue'),
  staffSalary: () => import('@/pages/staff_salary.vue'),
  serviceContracts: () => import('@/pages/service-contracts.vue'),
  employeeContracts: () => import('@/pages/employee_contracts.vue'),
  otherContracts: () => import('@/pages/other_contracts.vue'),
  allCustomers: () => import('@/pages/all_customers.vue'),
  cancelledContracts: () => import('@/pages/cancelled_contracts.vue'),
  tasks: () => import('@/pages/tasks.vue'),
  visitor: () => import('@/pages/visitor.vue'),
  contractPolicies: () => import('@/pages/contract/policies.vue'),
  contractPolicyLibrary: () => import('@/pages/contract/policy_library.vue'),
  contractDocuments: () => import('@/pages/contract/index.vue'),
  incomeTypes: () => import('@/pages/options/income-types.vue'),
  expenseTypes: () => import('@/pages/options/expense-types.vue'),
  optionsCurrencies: () => import('@/pages/options/currencies.vue'),
  educationalMasterData: () => import('@/pages/educational-master-data.vue'),
  academyData: () => import('@/pages/academy-data.vue'),
  applicants: () => import('@/pages/applicants.vue'),
  applicantStages: () => import('@/pages/applicant-stages.vue'),
  applicantSearch: () => import('@/pages/search.vue'),
  educationalDashboard: () => import('@/pages/educational-dashboard.vue'),
  educationalReports: () => import('@/pages/educational-reports.vue'),
  applicantCase: () => import('@/pages/applicant-case.vue'),
  applicantPortal: () => import('@/pages/applicant-portal.vue'),
  pendingTransactions: () => import('@/pages/pending-transactions.vue'),
  optionsHr: () => import('@/pages/options_hr.vue'),
  optionsContract: () => import('@/pages/options_contract.vue'),
  whatsappMessages: () => import('@/pages/whatsapp-messages.vue'),
  setupUsers: () => import('@/pages/setup/users.vue'),
  setupRoles: () => import('@/pages/setup/roles.vue'),
  setupPositions: () => import('@/pages/setup/positions.vue'),
  setupDepartments: () => import('@/pages/setup/departments.vue'),
  setupSettings: () => import('@/pages/setup/settings.vue'),
  notifications: () => import('@/pages/notifications.vue'),
  announcements: () => import('@/pages/announcements.vue'),
  support: () => import('@/pages/support.vue'),
  accountSettings: () => import('@/pages/account-settings.vue'),
  recurringEvents: () => import('@/pages/recurring-events.vue'),
}

const routeDefinitions: Array<{
  path: string
  name?: string
  component?: string
  redirect?: RouteRecordRaw['redirect']
  meta?: Record<string, unknown>
}> = [
  {
    path: '/login',
    component: 'login',
    name: 'login',
    meta: { unauthenticatedOnly: true, layout: 'blank' },
  },
  { path: '/home', redirect: { name: 'agency-overview' }, name: 'home', meta: { requiresAuth: true, action: 'read', subject: 'view_agency_overview' } },
  { path: '/finance', component: 'finance', name: 'finance', meta: { requiresAuth: true, action: 'read', subject: 'view_financial_reports' } },
  { path: '/income', component: 'income', name: 'income', meta: { requiresAuth: true, action: 'read', subject: 'view_incomes' } },
  { path: '/expenses', component: 'expenses', name: 'expenses', meta: { requiresAuth: true, action: 'read', subject: 'view_expenses' } },
  { path: '/commissions', component: 'commissions', name: 'commissions', meta: { requiresAuth: true, action: 'read', subject: 'view_commissions' } },
  { path: '/recurring-events/:id?', component: 'recurringEvents', name: 'recurring-events', meta: { requiresAuth: true, action: 'read', subject: 'view_recurring_events' } },
  { path: '/asset', component: 'asset', name: 'asset', meta: { requiresAuth: true, action: 'read', subject: 'view_assets' } },
  { path: '/reports', component: 'reports', name: 'reports', meta: { requiresAuth: true, action: 'read', subject: 'view_reports' } },
  { path: '/audits', component: 'audits', name: 'audits', meta: { requiresAuth: true, action: 'read', subject: 'view_audit_logs' } },
  { path: '/hr', component: 'hr', name: 'hr', meta: { requiresAuth: true, action: 'read', subject: 'view_employees' } },
  { path: '/staff-list', component: 'staffList', name: 'staff-list', meta: { requiresAuth: true, action: 'read', subject: 'view_employees' } },
  { path: '/timesheet', component: 'timesheet', name: 'timesheet', meta: { requiresAuth: true, action: 'read', subject: 'view_attendance' } },
  { path: '/leaves', component: 'paidTimeOffs', name: 'leaves', meta: { requiresAuth: true, action: 'read', subject: 'view_paid_time_off' } },
  { path: '/paid-time-offs', redirect: { name: 'leaves' } },
  {
    path: '/staff-salary',
    component: 'staffSalary',
    name: 'staff-salary',
    meta: { requiresAuth: true, action: 'read', subject: 'view_salaries' },
  },
  { path: '/bonuslist', component: 'bonuslist', name: 'bonuslist', meta: { requiresAuth: true, action: 'read', subject: 'view_bonuses' } },
  { path: '/contracts/scholarship', component: 'serviceContracts', name: 'scholarship-contracts', meta: { builtInService: 'scholarship', requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: '/contracts/test-offer', component: 'serviceContracts', name: 'test-offer-contracts', meta: { builtInService: 'test_offer', requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: '/contracts/private', component: 'serviceContracts', name: 'private-contracts', meta: { builtInService: 'private', requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: '/contracts/academy', component: 'serviceContracts', name: 'academy-contracts', meta: { builtInService: 'academy', requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: '/employee-contracts', component: 'employeeContracts', name: 'employee-contracts', meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: '/other-contracts', component: 'otherContracts', name: 'other-contracts', meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  {
    path: '/all-customers',
    component: 'allCustomers',
    name: 'all-customers',
    meta: { requiresAuth: true, action: 'read', subject: 'view_applicants' },
  },
  {
    path: '/cancelled-contracts',
    component: 'cancelledContracts',
    name: 'cancelled-contracts',
    meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' },
  },
  { path: '/tasks', component: 'tasks', name: 'tasks', meta: { requiresAuth: true, anyPermissions: ['view_all_boards', 'view_assigned_boards'] } },
  { path: '/projects', redirect: { name: 'tasks' } },
  { path: '/visitor', component: 'visitor', name: 'visitor', meta: { requiresAuth: true, action: 'read', subject: 'view_visitors' } },
  {
    // Templates and policies live on one page with in-page tab navigation.
    // The tab is kept in `?tab=` so each section stays deep-linkable.
    path: '/options/contracts/documents',
    component: 'contractDocuments',
    name: 'contract-documents',
    meta: { requiresAuth: true, anyPermissions: ['view_contract_templates', 'view_contract_policies'] },
  },
  { path: '/contract-documents', redirect: to => ({ name: 'contract-documents', query: to.query }) },
  {
    path: '/contract-templates',
    name: 'contract-templates',
    redirect: to => ({ name: 'contract-documents', query: { ...to.query, tab: 'templates' } }),
  },
  {
    path: '/contract-policies',
    name: 'contract-policies',
    redirect: to => ({ name: 'contract-documents', query: { ...to.query, tab: 'policies' } }),
  },
  {
    path: '/options/currencies',
    component: 'optionsCurrencies',
    name: 'options-currencies',
    meta: { requiresAuth: true, action: 'read', subject: 'view_currencies' },
  },
  { path: '/educational-master-data', component: 'educationalMasterData', name: 'educational-master-data', meta: { requiresAuth: true, action: 'read', subject: 'view_country' } },
  // Academy scheduling records (times, durations, teachers, classes) live on
  // their own page, reached from Options -> Contracts. The path is kept as
  // `/contracts/academy-data` so existing deep links keep working.
  { path: '/contracts/academy-data', component: 'academyData', name: 'academy-data', meta: { requiresAuth: true, action: 'read', subject: 'view_country' } },
  { path: '/applications', component: 'applicants', name: 'applications', meta: { requiresAuth: true, action: 'read', subject: 'view_applicants' } },
  // Legacy path kept as a query-preserving redirect (deep links such as
  // `/applicants?contact=12` must keep working after the rename).
  { path: '/applicants', name: 'applicants', redirect: to => ({ name: 'applications', query: to.query }), meta: { requiresAuth: true, action: 'read', subject: 'view_applicants' } },
  { path: '/applicant-pipeline', redirect: { name: 'applications' }, name: 'applicant-pipeline', meta: { requiresAuth: true, action: 'read', subject: 'view_applicants' } },
  { path: '/applicant-stages', component: 'applicantStages', name: 'applicant-stages', meta: { requiresAuth: true, action: 'manage', subject: 'manage_applicant_stages' } },
  { path: '/search', component: 'applicantSearch', name: 'search', meta: { requiresAuth: true } },
  { path: '/agency-overview', component: 'educationalDashboard', name: 'agency-overview', meta: { requiresAuth: true, action: 'read', subject: 'view_agency_overview' } },
  { path: '/visa-reports', component: 'educationalReports', name: 'visa-reports', meta: { requiresAuth: true, action: 'read', subject: 'view_visa_reports' } },
  { path: '/applicants/:id', component: 'applicantCase', name: 'applicant-case', meta: { requiresAuth: true, action: 'read', subject: 'view_applicants' } },
  { path: '/applications/:id', redirect: to => ({ name: 'applicant-case', params: { id: to.params.id }, query: to.query }) },
  { path: '/my-case', component: 'applicantPortal', name: 'applicant-portal', meta: { requiresAuth: true, action: 'read', subject: 'view_own_applicant_portal' } },
  { path: '/payment-requests', component: 'pendingTransactions', name: 'payment-requests', meta: { requiresAuth: true, action: 'read', subject: 'view_pending_transactions' } },
  { path: '/pending-transactions', redirect: { name: 'payment-requests' }, name: 'pending-transactions', meta: { requiresAuth: true, action: 'read', subject: 'view_pending_transactions' } },
  { path: '/loans', redirect: { name: 'payment-requests' }, name: 'loans', meta: { requiresAuth: true, action: 'read', subject: 'view_pending_transactions' } },
  { path: '/options/finance/income-types', component: 'incomeTypes', name: 'options-income-types', meta: { requiresAuth: true, action: 'read', subject: 'view_settings' } },
  { path: '/options/finance/expense-types', component: 'expenseTypes', name: 'options-expense-types', meta: { requiresAuth: true, action: 'read', subject: 'view_settings' } },
  { path: '/options/hr', component: 'optionsHr', name: 'options-hr', meta: { requiresAuth: true, action: 'read', subject: 'view_employees' } },
  {
    path: '/options/finance',
    redirect: { name: 'options-income-types' },
    name: 'options-finance',
    meta: { requiresAuth: true, action: 'read', subject: 'view_settings' },
  },
  {
    path: '/options/contract',
    component: 'optionsContract',
    name: 'options-contract',
    meta: { requiresAuth: true, action: 'read', subject: 'view_contract_templates' },
  },
  {
    // WhatsApp message templates, one subpage per audience. Access follows the
    // record type the templates belong to, so any of the three view
    // permissions opens the page; each subpage additionally hides itself
    // without its own permission.
    path: '/options/whatsapp-messages',
    component: 'whatsappMessages',
    name: 'whatsapp-messages',
    meta: { requiresAuth: true, anyPermissions: ['view_visitors', 'view_applicants', 'view_employees'] },
  },
  {
    path: '/setup/users',
    component: 'setupUsers',
    name: 'setup-users',
    meta: { requiresAuth: true, action: 'read', subject: 'view_users' },
  },
  {
    path: '/setup/roles',
    component: 'setupRoles',
    name: 'setup-roles',
    meta: { requiresAuth: true, action: 'read', subject: 'view_roles' },
  },
  {
    path: '/setup/positions',
    component: 'setupPositions',
    name: 'setup-positions',
    meta: { requiresAuth: true, action: 'read', subject: 'view_positions' },
  },
  {
    path: '/setup/departments',
    component: 'setupDepartments',
    name: 'setup-departments',
    meta: { requiresAuth: true, action: 'read', subject: 'view_departments' },
  },
  {
    path: '/setup/settings/:section?',
    component: 'setupSettings',
    name: 'setup-settings',
    meta: { requiresAuth: true, action: 'read', subject: 'view_settings' },
  },
  { path: '/setup/offices', redirect: { name: 'setup-settings', params: { section: 'office' } } },
  {
    path: '/forgot-password',
    component: 'forgotPassword',
    name: 'forgot-password',
    meta: { unauthenticatedOnly: true, layout: 'blank' },
  },
  {
    path: '/reset-password',
    component: 'resetPassword',
    name: 'reset-password',
    meta: { unauthenticatedOnly: true, layout: 'blank' },
  },
  {
    path: '/verify-email',
    component: 'verifyEmail',
    name: 'verify-email',
    meta: { public: true, layout: 'blank' },
  },
  {
    path: '/create-password',
    component: 'createPassword',
    name: 'create-password',
    meta: { unauthenticatedOnly: true, layout: 'blank' },
  },
  { path: '/notifications', component: 'notifications', name: 'notifications', meta: { requiresAuth: true, action: 'read', subject: 'view_own_notifications' } },
  { path: '/announcements', component: 'announcements', name: 'announcements', meta: { requiresAuth: true, action: 'read', subject: 'send_announcements' } },
  { path: '/support', component: 'support', name: 'support', meta: { requiresAuth: true } },
  { path: '/account-settings/:tab?', component: 'accountSettings', name: 'account-settings', meta: { requiresAuth: true } },
]

const mappedRoutes = routeDefinitions.map(route => ({
  ...route,
  component: route.component ? pageComponents[route.component] : undefined,
  meta: {
    ...route.meta,
  },
})) as RouteRecordRaw[]

// Keep old bookmarks functional while exposing the feature under its real name.
export const routes: RouteRecordRaw[] = [
  ...mappedRoutes,
  { path: '/notes', redirect: { name: 'tasks' } },
]

export const explicitRoutes = routes.map(route => setupLayouts([route])[0])
