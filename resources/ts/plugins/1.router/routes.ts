import { setupLayouts } from "virtual:meta-layouts";
import type { Component } from "vue";
import type { RouteRecordRaw } from "vue-router";

const pageComponents: Record<string, Component> = {
  home: () => import("@/pages/home.vue"),
  login: () => import("@/pages/login.vue"),
  forgotPassword: () => import("@/pages/forgot-password.vue"),
  resetPassword: () => import("@/pages/reset-password.vue"),
  createPassword: () => import("@/pages/create-password.vue"),
  verifyEmail: () => import("@/pages/verify-email.vue"),
  finance: () => import("@/pages/finance.vue"),
  income: () => import("@/pages/income.vue"),
  expenses: () => import("@/pages/expenses.vue"),
  stock: () => import("@/pages/stock.vue"),
  reports: () => import("@/pages/reports.vue"),
  hr: () => import("@/pages/hr.vue"),
  staffList: () => import("@/pages/staff_list.vue"),
  timesheet: () => import("@/pages/timesheet.vue"),
  paidTimeOffs: () => import("@/pages/paid_time_offs.vue"),
  bonuslist: () => import("@/pages/bonuslist.vue"),
  staffSalary: () => import("@/pages/staff_salary.vue"),
  contracts: () => import("@/pages/customer_contracts.vue"),
  employeeContracts: () => import("@/pages/employee_contracts.vue"),
  companyContracts: () => import("@/pages/company_contracts.vue"),
  otherContracts: () => import("@/pages/other_contracts.vue"),
  allCustomers: () => import("@/pages/all_customers.vue"),
  cancelledContracts: () => import("@/pages/cancelled_contracts.vue"),
  tasks: () => import("@/pages/tasks.vue"),
  visitor: () => import("@/pages/visitor.vue"),
  innvoice: () => import("@/pages/innvoice.vue"),
  paymentDetails: () => import("@/pages/payment-details.vue"),
  contractPolicies: () => import("@/pages/contract/policies.vue"),
  companies: () => import("@/pages/companies.vue"),
  contractPolicyLibrary: () => import("@/pages/contract/policy_library.vue"),
  optionsServices: () => import("@/pages/options/services.vue"),
  optionsCurrencies: () => import("@/pages/options/currencies.vue"),
  optionsInvoice: () => import("@/pages/options_invoice.vue"),
  optionsHr: () => import("@/pages/options_hr.vue"),
  optionsFinance: () => import("@/pages/options_finance.vue"),
  optionsContract: () => import("@/pages/options_contract.vue"),
  setupUsers: () => import("@/pages/setup/users.vue"),
  setupRoles: () => import("@/pages/setup/roles.vue"),
  setupPositions: () => import("@/pages/setup/positions.vue"),
  setupMarketers: () => import("@/pages/setup/marketers.vue"),
  setupOffices: () => import("@/pages/setup/offices.vue"),
  setupSettings: () => import("@/pages/setup/settings.vue"),
  notifications: () => import("@/pages/notifications.vue"),
  accountSettings: () => import("@/pages/account-settings.vue"),
  recurringEvents: () => import("@/pages/recurring-events.vue"),
};

const routeDefinitions: Array<
  Omit<RouteRecordRaw, "component"> & {
    component: string;
    meta?: Record<string, unknown>;
  }
> = [
  {
    path: "/login",
    component: "login",
    name: "login",
    meta: { unauthenticatedOnly: true, layout: "blank" },
  },
  { path: "/home", component: "home", name: "home", meta: { requiresAuth: true, action: 'read', subject: 'view_dashboard' } },
  { path: "/finance", component: "finance", name: "finance", meta: { requiresAuth: true, action: 'read', subject: 'view_financial_reports' } },
  { path: "/income", component: "income", name: "income", meta: { requiresAuth: true, action: 'read', subject: 'view_incomes' } },
  { path: "/expenses", component: "expenses", name: "expenses", meta: { requiresAuth: true, action: 'read', subject: 'view_expenses' } },
  { path: "/recurring-events/:id?", component: "recurringEvents", name: "recurring-events", meta: { requiresAuth: true, action: 'read', subject: 'view_recurring_events' } },
  { path: "/stock", component: "stock", name: "stock", meta: { requiresAuth: true, action: 'read', subject: 'view_stocks' } },
  { path: "/reports", component: "reports", name: "reports", meta: { requiresAuth: true, action: 'read', subject: 'view_reports' } },
  { path: "/hr", component: "hr", name: "hr", meta: { requiresAuth: true, action: 'read', subject: 'view_employees' } },
  { path: "/staff-list", component: "staffList", name: "staff-list", meta: { requiresAuth: true, action: 'read', subject: 'view_employees' } },
  { path: "/timesheet", component: "timesheet", name: "timesheet", meta: { requiresAuth: true, action: 'read', subject: 'view_attendance' } },
  { path: "/paid-time-offs", component: "paidTimeOffs", name: "paid-time-offs", meta: { requiresAuth: true, action: 'read', subject: 'view_paid_time_off' } },
  {
    path: "/staff-salary",
    component: "staffSalary",
    name: "staff-salary",
    meta: { requiresAuth: true, action: 'read', subject: 'view_salaries' },
  },
  { path: "/bonuslist", component: "bonuslist", name: "bonuslist", meta: { requiresAuth: true, action: 'read', subject: 'view_bonuses' } },
  { path: "/contracts", component: "contracts", name: "contracts", meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: "/employee-contracts", component: "employeeContracts", name: "employee-contracts", meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: "/company-contracts", component: "companyContracts", name: "company-contracts", meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  { path: "/other-contracts", component: "otherContracts", name: "other-contracts", meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' } },
  {
    path: "/all-customers",
    component: "allCustomers",
    name: "all-customers",
    meta: { requiresAuth: true, action: 'read', subject: 'view_customers' },
  },
  {
    path: "/cancelled-contracts",
    component: "cancelledContracts",
    name: "cancelled-contracts",
    meta: { requiresAuth: true, action: 'read', subject: 'view_contracts' },
  },
  { path: "/tasks", component: "tasks", name: "tasks", meta: { requiresAuth: true, anyPermissions: ['view_all_boards', 'view_assigned_boards'] } },
  { path: "/visitor", component: "visitor", name: "visitor", meta: { requiresAuth: true, action: 'read', subject: 'view_visitors' } },
  { path: "/innvoice", component: "innvoice", name: "innvoice", meta: { requiresAuth: true, action: 'read', subject: 'view_invoices' } },
  { path: "/invoice/payment-details", component: "paymentDetails", name: "payment-details", meta: { requiresAuth: true, action: 'read', subject: 'view_payment_details' } },
  {
    path: "/contract-templates",
    component: "contractPolicies",
    name: "contract-templates",
    meta: { requiresAuth: true, action: 'read', subject: 'view_contract_templates' },
  },
  {
    path: "/companies",
    component: "companies",
    name: "companies",
    meta: { requiresAuth: true, action: 'read', subject: 'view_companies' },
  },
  {
    path: "/contract-policies",
    component: "contractPolicyLibrary",
    name: "contract-policies",
    meta: { requiresAuth: true, action: 'read', subject: 'view_contract_policies' },
  },
  {
    path: "/options/services",
    component: "optionsServices",
    name: "options-services",
    meta: { requiresAuth: true, action: 'read', subject: 'view_services' },
  },
  {
    path: "/options/currencies",
    component: "optionsCurrencies",
    name: "options-currencies",
    meta: { requiresAuth: true, action: 'read', subject: 'view_currencies' },
  },
  {
    path: "/options/invoice",
    component: "optionsInvoice",
    name: "options-invoice",
    meta: { requiresAuth: true, action: 'read', subject: 'view_invoices' },
  },
  { path: "/options/hr", component: "optionsHr", name: "options-hr", meta: { requiresAuth: true, action: 'read', subject: 'view_employees' } },
  {
    path: "/options/finance",
    component: "optionsFinance",
    name: "options-finance",
    meta: { requiresAuth: true, action: 'read', subject: 'view_financial_reports' },
  },
  {
    path: "/options/contract",
    component: "optionsContract",
    name: "options-contract",
    meta: { requiresAuth: true, action: 'read', subject: 'view_contract_templates' },
  },
  {
    path: "/setup/users",
    component: "setupUsers",
    name: "setup-users",
    meta: { requiresAuth: true, action: 'read', subject: 'view_users' },
  },
  {
    path: "/setup/roles",
    component: "setupRoles",
    name: "setup-roles",
    meta: { requiresAuth: true, action: 'read', subject: 'view_roles' },
  },
  {
    path: "/setup/positions",
    component: "setupPositions",
    name: "setup-positions",
    meta: { requiresAuth: true, action: 'read', subject: 'view_positions' },
  },
  {
    path: "/setup/settings",
    component: "setupSettings",
    name: "setup-settings",
    meta: { requiresAuth: true, action: 'read', subject: 'view_settings' },
  },
  {
    path: "/setup/marketers",
    component: "setupMarketers",
    name: "setup-marketers",
    meta: { requiresAuth: true, action: 'read', subject: 'view_marketers' },
  },
  {
    path: "/setup/offices",
    component: "setupOffices",
    name: "setup-offices",
    meta: { requiresAuth: true, action: 'read', subject: 'view_offices' },
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
    path: "/create-password",
    component: "createPassword",
    name: "create-password",
    meta: { unauthenticatedOnly: true, layout: "blank" },
  },
  { path: "/notifications", component: "notifications", name: "notifications", meta: { requiresAuth: true, action: 'read', subject: 'view_own_notifications' } },
  { path: "/account-settings/:tab?", component: "accountSettings", name: "account-settings", meta: { requiresAuth: true } },
];

const mappedRoutes = routeDefinitions.map((route) => ({
  ...route,
  component: pageComponents[route.component as keyof typeof pageComponents],
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
