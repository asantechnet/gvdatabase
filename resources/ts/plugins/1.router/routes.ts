import { setupLayouts } from "virtual:meta-layouts";
import type { Component } from "vue";
import type { RouteRecordRaw } from "vue-router";

const pageComponents: Record<string, Component> = {
  login: () => import("@/pages/login.vue"),
  forgotPassword: () => import("@/pages/forgot-password.vue"),
  resetPassword: () => import("@/pages/reset-password.vue"),
  createPassword: () => import("@/pages/create-password.vue"),
  verifyEmail: () => import("@/pages/verify-email.vue"),

  // The old Finance page was a mock ledger with hard-coded USD amounts. Route the
  // finance entry point to the real reporting view so its KPIs always come from
  // the current-base calculations on the server.
  finance: () => import("@/pages/reports.vue"),
  income: () => import("@/pages/income.vue"),
  expenses: () => import("@/pages/expenses.vue"),
  commissions: () => import("@/pages/commissions.vue"),
  asset: () => import("@/pages/asset.vue"),
  reports: () => import("@/pages/reports.vue"),
  audits: () => import("@/pages/audits.vue"),
  hr: () => import("@/pages/hr.vue"),
  staffList: () => import("@/pages/staff_list.vue"),
  timesheet: () => import("@/pages/timesheet.vue"),
  leaveRequests: () => import("@/pages/leave_requests.vue"),
  offDays: () => import("@/pages/off_days.vue"),
  bonuslist: () => import("@/pages/bonuslist.vue"),
  payrolls: () => import("@/pages/payrolls.vue"),
  serviceContracts: () => import("@/pages/service-contracts.vue"),
  employeeContracts: () => import("@/pages/employee_contracts.vue"),
  otherContracts: () => import("@/pages/other_contracts.vue"),
  allCustomers: () => import("@/pages/all_customers.vue"),
  cancelledContracts: () => import("@/pages/cancelled_contracts.vue"),
  tasks: () => import("@/pages/tasks.vue"),
  visitor: () => import("@/pages/visitor.vue"),
  contractPolicies: () => import("@/pages/contract/policies.vue"),
  contractPolicyLibrary: () => import("@/pages/contract/policy_library.vue"),
  contractDocuments: () => import("@/pages/contract/index.vue"),
  incomeTypes: () => import("@/pages/options/income-types.vue"),
  expenseTypes: () => import("@/pages/options/expense-types.vue"),
  optionsCurrencies: () => import("@/pages/options/currencies.vue"),
  educationalMasterData: () => import("@/pages/educational-master-data.vue"),
  academyData: () => import("@/pages/academy-data.vue"),
  applicants: () => import("@/pages/applicants.vue"),
  applicantPipeline: () => import("@/pages/applicant-pipeline.vue"),
  applicantStages: () => import("@/pages/applicant-stages.vue"),
  educationalDashboard: () => import("@/pages/educational-dashboard.vue"),
  educationalReports: () => import("@/pages/educational-reports.vue"),
  applicantCase: () => import("@/pages/applicant-case.vue"),
  serviceCase: () => import("@/pages/service-case.vue"),
  applicantPortal: () => import("@/pages/applicant-portal.vue"),
  optionsHr: () => import("@/pages/options_hr.vue"),
  optionsContract: () => import("@/pages/options_contract.vue"),
  whatsappMessages: () => import("@/pages/whatsapp-messages.vue"),
  setupUsers: () => import("@/pages/setup/users.vue"),
  setupRoles: () => import("@/pages/setup/roles.vue"),
  setupPositions: () => import("@/pages/setup/positions.vue"),
  setupDepartments: () => import("@/pages/setup/departments.vue"),
  setupSettings: () => import("@/pages/setup/settings.vue"),
  notifications: () => import("@/pages/notifications.vue"),
  announcements: () => import("@/pages/announcements.vue"),
  support: () => import("@/pages/support.vue"),
  accountSettings: () => import("@/pages/account-settings.vue"),
  recurringEvents: () => import("@/pages/recurring-events.vue"),
};

const routeDefinitions: Array<{
  path: string;
  name?: string;
  component?: string;
  redirect?: RouteRecordRaw["redirect"];
  meta?: Record<string, unknown>;
}> = [
  {
    path: "/login",
    component: "login",
    name: "login",
    meta: { unauthenticatedOnly: true, layout: "blank" },
  },
  {
    path: "/home",
    redirect: { name: "agency-overview" },
    name: "home",
    meta: {
      requiresAuth: true,
      action: "read",
      subject: "view_agency_overview",
    },
  },
  {
    path: "/finance",
    component: "finance",
    name: "finance",
    meta: {
      requiresAuth: true,
      action: "read",
      subject: "view_financial_reports",
    },
  },
  {
    path: "/income",
    component: "income",
    name: "income",
    meta: { requiresAuth: true, action: "read", subject: "view_incomes" },
  },
  {
    path: "/expenses",
    component: "expenses",
    name: "expenses",
    meta: { requiresAuth: true, action: "read", subject: "view_expenses" },
  },
  {
    path: "/commissions",
    component: "commissions",
    name: "commissions",
    meta: { requiresAuth: true, action: "read", subject: "view_commissions" },
  },
  {
    path: "/recurring-events/:id?",
    component: "recurringEvents",
    name: "recurring-events",
    meta: {
      requiresAuth: true,
      action: "read",
      subject: "view_recurring_events",
    },
  },
  {
    path: "/asset",
    component: "asset",
    name: "asset",
    meta: { requiresAuth: true, action: "read", subject: "view_assets" },
  },
  {
    path: "/reports",
    component: "reports",
    name: "reports",
    meta: { requiresAuth: true, action: "read", subject: "view_reports" },
  },
  {
    path: "/audits",
    component: "audits",
    name: "audits",
    meta: { requiresAuth: true, action: "read", subject: "view_audit_logs" },
  },
  {
    path: "/hr",
    component: "hr",
    name: "hr",
    meta: { requiresAuth: true, action: "read", subject: "view_employees" },
  },
  {
    path: "/staff-list",
    component: "staffList",
    name: "staff-list",
    meta: { requiresAuth: true, action: "read", subject: "view_employees" },
  },
  {
    path: "/timesheet",
    component: "timesheet",
    name: "timesheet",
    meta: { requiresAuth: true, action: "read", subject: "view_attendance" },
  },
  {
    path: "/leave-requests",
    component: "leaveRequests",
    name: "leave-requests",
    meta: {
      requiresAuth: true,
      action: "read",
      subject: "view_leave_requests",
    },
  },
  { path: "/leaves", redirect: { name: "leave-requests" } },
  { path: "/paid-time-offs", redirect: { name: "leave-requests" } },
  {
    path: "/off-days",
    component: "offDays",
    name: "off-days",
    meta: { requiresAuth: true, action: "read", subject: "view_off_days" },
  },
  {
    path: "/payrolls",
    component: "payrolls",
    name: "payrolls",
    meta: { requiresAuth: true, action: "read", subject: "view_salaries" },
  },
  { path: "/staff-salary", redirect: { name: "payrolls" } },
  {
    path: "/bonuslist",
    component: "bonuslist",
    name: "bonuslist",
    meta: { requiresAuth: true, action: "read", subject: "view_bonuses" },
  },
  // Private is the main contracts workspace. Keep a direct /contracts URL so
  // menu links and old bookmarks land on the same default service.
  {
    path: "/contracts",
    redirect: { name: "private-contracts" },
    name: "contracts",
    meta: { requiresAuth: true, action: "read", subject: "view_contracts" },
  },
  {
    path: "/contracts/scholarship",
    component: "serviceContracts",
    name: "scholarship-contracts",
    meta: {
      builtInService: "scholarship",
      navActiveLink: "private-contracts",
      requiresAuth: true,
      action: "read",
      subject: "view_contracts",
    },
  },
  {
    path: "/contracts/test-offer",
    component: "serviceContracts",
    name: "test-offer-contracts",
    meta: {
      builtInService: "test_offer",
      navActiveLink: "private-contracts",
      requiresAuth: true,
      action: "read",
      subject: "view_contracts",
    },
  },
  {
    path: "/contracts/private",
    component: "serviceContracts",
    name: "private-contracts",
    meta: {
      builtInService: "private",
      requiresAuth: true,
      action: "read",
      subject: "view_contracts",
    },
  },
  {
    path: "/contracts/academy",
    component: "serviceContracts",
    name: "academy-contracts",
    meta: {
      builtInService: "academy",
      navActiveLink: "private-contracts",
      requiresAuth: true,
      action: "read",
      subject: "view_contracts",
    },
  },
  {
    path: "/employee-contracts",
    component: "employeeContracts",
    name: "employee-contracts",
    meta: { requiresAuth: true, action: "read", subject: "view_contracts" },
  },
  {
    path: "/other-contracts",
    component: "otherContracts",
    name: "other-contracts",
    meta: {
      navActiveLink: "private-contracts",
      requiresAuth: true,
      action: "read",
      subject: "view_contracts",
    },
  },
  {
    path: "/all-customers",
    component: "allCustomers",
    name: "all-customers",
    meta: { requiresAuth: true, action: "read", subject: "view_applicants" },
  },
  {
    path: "/cancelled-contracts",
    component: "cancelledContracts",
    name: "cancelled-contracts",
    meta: {
      navActiveLink: "private-contracts",
      requiresAuth: true,
      action: "read",
      subject: "view_contracts",
    },
  },
  {
    path: "/tasks",
    component: "tasks",
    name: "tasks",
    meta: {
      requiresAuth: true,
      anyPermissions: [
        "view_all_boards",
        "view_assigned_boards",
        "create_personal_tasks",
        "view_private_contracts",
        "view_scholarship_contracts",
        "view_test_offer_contracts",
        "view_academy_contracts",
        "view_other_contracts",
      ],
    },
  },
  { path: "/projects", redirect: { name: "tasks" } },
  {
    path: "/visitor",
    component: "visitor",
    name: "visitor",
    meta: { requiresAuth: true, action: "read", subject: "view_visitors" },
  },
  {
    // Templates and policies live on one page with in-page tab navigation.
    // The tab is kept in `?tab=` so each section stays deep-linkable.
    path: "/options/contracts/documents",
    component: "contractDocuments",
    name: "contract-documents",
    meta: {
      requiresAuth: true,
      anyPermissions: ["view_contract_templates", "view_contract_policies"],
    },
  },
  {
    path: "/contract-documents",
    redirect: (to) => ({ name: "contract-documents", query: to.query }),
  },
  {
    path: "/contract-templates",
    name: "contract-templates",
    redirect: (to) => ({
      name: "contract-documents",
      query: { ...to.query, tab: "templates" },
    }),
  },
  {
    path: "/contract-policies",
    name: "contract-policies",
    redirect: (to) => ({
      name: "contract-documents",
      query: { ...to.query, tab: "policies" },
    }),
  },
  {
    path: "/options/currencies",
    component: "optionsCurrencies",
    name: "options-currencies",
    meta: { requiresAuth: true, action: "read", subject: "view_currencies" },
  },
  {
    path: "/educational-master-data",
    component: "educationalMasterData",
    name: "educational-master-data",
    meta: { requiresAuth: true, action: "read", subject: "view_country" },
  },
  // Academy scheduling records (times, durations, teachers, classes) live on
  // their own page, reached from Options -> Contracts. The path is kept as
  // `/contracts/academy-data` so existing deep links keep working.
  {
    path: "/contracts/academy-data",
    component: "academyData",
    name: "academy-data",
    meta: { requiresAuth: true, action: "read", subject: "view_country" },
  },
  {
    path: "/applications",
    component: "applicants",
    name: "applications",
    meta: { requiresAuth: true, action: "read", subject: "view_applicants" },
  },
  // Legacy path kept as a query-preserving redirect (deep links such as
  // `/applicants?contact=12` must keep working after the rename).
  {
    path: "/applicants",
    name: "applicants",
    redirect: (to) => ({ name: "applications", query: to.query }),
    meta: { requiresAuth: true, action: "read", subject: "view_applicants" },
  },
  // The service-line board: one service at a time, one card per contract line.
  {
    path: "/applicant-pipeline",
    redirect: { name: "applications", query: { view: "kanban" } },
    name: "applicant-pipeline",
    meta: { requiresAuth: true, action: "read", subject: "view_applicants" },
  },
  // Editing a stage additionally requires `manage_applicant_stages`; the page
  // itself is readable with `view_applicant_stages`, matching the nav item.
  {
    path: "/applicant-stages",
    component: "applicantStages",
    name: "applicant-stages",
    meta: {
      requiresAuth: true,
      action: "read",
      subject: "view_applicant_stages",
    },
  },
  {
    path: "/agency-overview",
    component: "educationalDashboard",
    name: "agency-overview",
    meta: {
      requiresAuth: true,
      action: "read",
      subject: "view_agency_overview",
    },
  },
  {
    path: "/application-reports",
    component: "educationalReports",
    name: "application-reports",
    meta: {
      reportWorkspace: "applications",
      navActiveLink: "applications",
      requiresAuth: true,
      action: "read",
      subject: "view_visa_reports",
    },
  },
  {
    path: "/visa-reports",
    name: "visa-reports",
    redirect: { name: "application-reports" },
  },
  {
    path: "/finance/activity-report",
    component: "educationalReports",
    name: "finance-activity-report",
    meta: {
      reportWorkspace: "finance",
      requiresAuth: true,
      action: "read",
      subject: "view_financial_reports",
    },
  },
  {
    path: "/hr/employee-performance",
    component: "educationalReports",
    name: "employee-performance-report",
    meta: {
      reportWorkspace: "hrm",
      requiresAuth: true,
      action: "read",
      subject: "view_employees",
    },
  },
  {
    path: "/applicants/:id",
    component: "applicantCase",
    name: "applicant-case",
    meta: { requiresAuth: true, action: "read", subject: "view_applicants" },
  },
  // One contracted service line is one case; the line id is the workspace key.
  {
    path: "/service-cases/:line",
    component: "serviceCase",
    name: "service-case",
    meta: { requiresAuth: true, action: "read", subject: "view_applicants" },
  },
  {
    path: "/applications/:id",
    redirect: (to) => ({
      name: "applicant-case",
      params: { id: to.params.id },
      query: to.query,
    }),
  },
  {
    path: "/my-case",
    component: "applicantPortal",
    name: "applicant-portal",
    meta: {
      requiresAuth: true,
      action: "read",
      subject: "view_own_applicant_portal",
    },
  },
  {
    path: "/options/finance/income-types",
    component: "incomeTypes",
    name: "options-income-types",
    meta: { requiresAuth: true, action: "read", subject: "view_settings" },
  },
  {
    path: "/options/finance/expense-types",
    component: "expenseTypes",
    name: "options-expense-types",
    meta: { requiresAuth: true, action: "read", subject: "view_settings" },
  },
  {
    path: "/options/hr",
    component: "optionsHr",
    name: "options-hr",
    meta: { requiresAuth: true, action: "read", subject: "view_employees" },
  },
  {
    path: "/options/finance",
    redirect: { name: "options-income-types" },
    name: "options-finance",
    meta: { requiresAuth: true, action: "read", subject: "view_settings" },
  },
  {
    path: "/options/contract",
    component: "optionsContract",
    name: "options-contract",
    meta: {
      requiresAuth: true,
      action: "read",
      subject: "view_contract_templates",
    },
  },
  {
    // WhatsApp message templates, one subpage per audience. Access follows the
    // record type the templates belong to, so any of the three view
    // permissions opens the page; each subpage additionally hides itself
    // without its own permission.
    path: "/options/whatsapp-messages",
    component: "whatsappMessages",
    name: "whatsapp-messages",
    meta: {
      requiresAuth: true,
      anyPermissions: ["view_visitors", "view_applicants", "view_employees"],
    },
  },
  {
    path: "/setup/users",
    component: "setupUsers",
    name: "setup-users",
    meta: { requiresAuth: true, action: "read", subject: "view_users" },
  },
  {
    path: "/setup/roles",
    component: "setupRoles",
    name: "setup-roles",
    meta: { requiresAuth: true, action: "read", subject: "view_roles" },
  },
  {
    path: "/setup/positions",
    component: "setupPositions",
    name: "setup-positions",
    meta: { requiresAuth: true, action: "read", subject: "view_positions" },
  },
  {
    path: "/setup/departments",
    component: "setupDepartments",
    name: "setup-departments",
    meta: { requiresAuth: true, action: "read", subject: "view_departments" },
  },
  {
    path: "/setup/settings/:section?",
    component: "setupSettings",
    name: "setup-settings",
    meta: { requiresAuth: true, action: "read", subject: "view_settings" },
  },
  {
    path: "/setup/offices",
    redirect: { name: "setup-settings", params: { section: "office" } },
  },
  {
    path: "/forgot-password",
    component: "forgotPassword",
    name: "forgot-password",
    meta: { unauthenticatedOnly: true, layout: "blank" },
  },
  {
    path: "/reset-password",
    component: "resetPassword",
    name: "reset-password",
    meta: { unauthenticatedOnly: true, layout: "blank" },
  },
  {
    path: "/verify-email",
    component: "verifyEmail",
    name: "verify-email",
    meta: { public: true, layout: "blank" },
  },
  {
    path: "/accept-invitation",
    component: "createPassword",
    name: "accept-invitation",
    meta: { unauthenticatedOnly: true, layout: "blank" },
  },
  {
    path: "/create-password",
    redirect: (to) => ({ name: "accept-invitation", query: to.query }),
  },
  {
    path: "/notifications",
    component: "notifications",
    name: "notifications",
    meta: {
      requiresAuth: true,
      action: "read",
      subject: "view_own_notifications",
    },
  },
  {
    path: "/announcements",
    component: "announcements",
    name: "announcements",
    meta: { requiresAuth: true, action: "read", subject: "send_announcements" },
  },
  {
    path: "/support",
    component: "support",
    name: "support",
    meta: { requiresAuth: true },
  },
  {
    path: "/account-settings/:tab?",
    component: "accountSettings",
    name: "account-settings",
    meta: { requiresAuth: true },
  },
];

const mappedRoutes = routeDefinitions.map((route) => ({
  ...route,
  component: route.component ? pageComponents[route.component] : undefined,
  meta: {
    ...route.meta,
  },
})) as RouteRecordRaw[];

// Keep old bookmarks functional while exposing the feature under its real name.
export const routes: RouteRecordRaw[] = [
  ...mappedRoutes,
  { path: "/notes", redirect: { name: "tasks" } },
];

export const explicitRoutes = routes.map((route) => setupLayouts([route])[0]);
