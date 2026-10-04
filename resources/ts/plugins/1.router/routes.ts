import { setupLayouts } from 'virtual:meta-layouts'
import type { Component } from 'vue'
import type { RouteRecordRaw } from 'vue-router'

const pageComponents: Record<string, Component> = {
  home: () => import('@/pages/home.vue'),
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
  asset: () => import('@/pages/asset.vue'),
  reports: () => import('@/pages/reports.vue'),
  audits: () => import('@/pages/audits.vue'),
  hr: () => import('@/pages/hr.vue'),
  staffList: () => import('@/pages/staff_list.vue'),
  timesheet: () => import('@/pages/timesheet.vue'),
  paidTimeOffs: () => import('@/pages/paid_time_offs.vue'),
  bonuslist: () => import('@/pages/bonuslist.vue'),
  staffSalary: () => import('@/pages/staff_salary.vue'),
  commissions: () => import('@/pages/commissions.vue'),
  contracts: () => import('@/pages/customer_contracts.vue'),
  employeeContracts: () => import('@/pages/employee_contracts.vue'),
  otherContracts: () => import('@/pages/other_contracts.vue'),
  allCustomers: () => import('@/pages/all_customers.vue'),
  cancelledContracts: () => import('@/pages/cancelled_contracts.vue'),
  tasks: () => import('@/pages/tasks.vue'),
  visitor: () => import('@/pages/visitor.vue'),
  leads: () => import('@/pages/leads.vue'),
  innvoice: () => import('@/pages/innvoice.vue'),
  paymentDetails: () => import('@/pages/payment-details.vue'),
  contractPolicies: () => import('@/pages/contract/policies.vue'),
  contractPolicyLibrary: () => import('@/pages/contract/policy_library.vue'),
  contractDocuments: () => import('@/pages/contract/index.vue'),
  optionsServices: () => import('@/pages/options/services.vue'),
  optionsCurrencies: () => import('@/pages/options/currencies.vue'),
  educationalMasterData: () => import('@/pages/educational-master-data.vue'),
  applicants: () => import('@/pages/applicants.vue'),
  applicantPipeline: () => import('@/pages/applicant-pipeline.vue'),
  applicantStages: () => import('@/pages/applicant-stages.vue'),
  applicantSearch: () => import('@/pages/search.vue'),
  educationalDashboard: () => import('@/pages/educational-dashboard.vue'),
  educationalReports: () => import('@/pages/educational-reports.vue'),
  applicantCase: () => import('@/pages/applicant-case.vue'),
  applicantPortal: () => import('@/pages/applicant-portal.vue'),
  pendingTransactions: () => import('@/pages/pending-transactions.vue'),
  optionsHr: () => import('@/pages/options_hr.vue'),
  optionsFinance: () => import('@/pages/options_finance.vue'),
  optionsContract: () => import('@/pages/options_contract.vue'),
  setupUsers: () => import('@/pages/setup/users.vue'),
  setupRoles: () => import('@/pages/setup/roles.vue'),
  setupPositions: () => import('@/pages/setup/positions.vue'),
  setupMarketers: () => import('@/pages/setup/marketers.vue'),
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
  { path: '/home', component: 'home', name: 'home', meta: { requiresAuth: true, action: 'read', subject: 'view_dashboard' } },
  { path: '/finance', component: 'finance', name: 'finance', meta: { requiresAuth: true, action: 'read', subject: 'view_financial_reports' } },
  { path: '/income', component: 'income', name: 'income', meta: { requiresAuth: true, action: 'read', subject: 'view_incomes' } },
  { path: '/expenses', component: 'expenses', name: 'expenses', meta: { requiresAuth: true, action: 'read', subject: 'view_expenses' } },
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
  { path: '/contracts', component: 'contracts', name: 'contracts', meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: '/employee-contracts', component: 'employeeContracts', name: 'employee-contracts', meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: '/other-contracts', component: 'otherContracts', name: 'other-contracts', meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: '/company-contracts', redirect: { name: 'other-contracts' } },
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
  { path: '/projects', component: 'tasks', name: 'projects', meta: { requiresAuth: true, anyPermissions: ['view_all_boards', 'view_assigned_boards'] } },
  { path: '/tasks', redirect: { name: 'projects' } },
  { path: '/visitor', component: 'visitor', name: 'visitor', meta: { requiresAuth: true, action: 'read', subject: 'view_visitors' } },
  { path: '/leads', component: 'leads', name: 'leads', meta: { requiresAuth: true, action: 'read', subject: 'view_visitors' } },
  { path: '/innvoice', component: 'innvoice', name: 'innvoice', meta: { requiresAuth: true, action: 'read', subject: 'view_invoices' } },
  { path: '/invoice/payment-details', component: 'paymentDetails', name: 'payment-details', meta: { requiresAuth: true, action: 'read', subject: 'view_payment_details' } },
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
  { path: '/commissions', component: 'commissions', name: 'commissions', meta: { requiresAuth: true, action: 'read', subject: 'view_commissions' } },
  {
    path: '/contract-policies',
    name: 'contract-policies',
    redirect: to => ({ name: 'contract-documents', query: { ...to.query, tab: 'policies' } }),
  },
  {
    path: '/options/services',
    component: 'optionsServices',
    name: 'options-services',
    meta: { requiresAuth: true, action: 'read', subject: 'view_services' },
  },
  {
    path: '/options/currencies',
    component: 'optionsCurrencies',
    name: 'options-currencies',
    meta: { requiresAuth: true, action: 'read', subject: 'view_currencies' },
  },
  { path: '/educational-master-data', component: 'educationalMasterData', name: 'educational-master-data', meta: { requiresAuth: true, action: 'read', subject: 'view_country' } },
  { path: '/applicants', component: 'applicants', name: 'applicants', meta: { requiresAuth: true, action: 'read', subject: 'view_applicants' } },
  { path: '/applicant-pipeline', component: 'applicantPipeline', name: 'applicant-pipeline', meta: { requiresAuth: true, action: 'read', subject: 'view_applicants' } },
  { path: '/applicant-stages', component: 'applicantStages', name: 'applicant-stages', meta: { requiresAuth: true, action: 'manage', subject: 'manage_applicant_stages' } },
  { path: '/search', component: 'applicantSearch', name: 'search', meta: { requiresAuth: true } },
  { path: '/agency-overview', component: 'educationalDashboard', name: 'agency-overview', meta: { requiresAuth: true, action: 'read', subject: 'view_dashboard' } },
  { path: '/visa-reports', component: 'educationalReports', name: 'visa-reports', meta: { requiresAuth: true, action: 'read', subject: 'view_reports' } },
  { path: '/applicants/:id', component: 'applicantCase', name: 'applicant-case', meta: { requiresAuth: true, action: 'read', subject: 'view_applicants' } },
  { path: '/my-case', component: 'applicantPortal', name: 'applicant-portal', meta: { requiresAuth: true, action: 'read', subject: 'view_own_applicant_portal' } },
  { path: '/pending-transactions', component: 'pendingTransactions', name: 'pending-transactions', meta: { requiresAuth: true, action: 'read', subject: 'view_pending_transactions' } },
  { path: '/options/hr', component: 'optionsHr', name: 'options-hr', meta: { requiresAuth: true, action: 'read', subject: 'view_employees' } },
  {
    path: '/options/finance',
    component: 'optionsFinance',
    name: 'options-finance',
    meta: { requiresAuth: true, action: 'read', subject: 'view_financial_reports' },
  },
  {
    path: '/options/contract',
    component: 'optionsContract',
    name: 'options-contract',
    meta: { requiresAuth: true, action: 'read', subject: 'view_contract_templates' },
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
  {
    path: '/setup/marketers',
    component: 'setupMarketers',
    name: 'setup-marketers',
    meta: { requiresAuth: true, action: 'read', subject: 'view_marketers' },
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
  { path: '/notes', redirect: { name: 'projects' } },
]

export const explicitRoutes = routes.map(route => setupLayouts([route])[0])
