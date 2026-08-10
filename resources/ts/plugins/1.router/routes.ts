import { setupLayouts } from "virtual:meta-layouts";
import type { Component } from "vue";
import type { RouteRecordRaw } from "vue-router";

const pageComponents: Record<string, Component> = {
  home: () => import("@/pages/home.vue"),
  login: () => import("@/pages/login.vue"),
  finance: () => import("@/pages/finance.vue"),
  income: () => import("@/pages/income.vue"),
  expenses: () => import("@/pages/expenses.vue"),
  stock: () => import("@/pages/stock.vue"),
  reports: () => import("@/pages/reports.vue"),
  hr: () => import("@/pages/hr.vue"),
  staffList: () => import("@/pages/staff_list.vue"),
  timesheet: () => import("@/pages/timesheet.vue"),
  bonuslist: () => import("@/pages/bonuslist.vue"),
  staffSalary: () => import("@/pages/staff_salary.vue"),
  contracts: () => import("@/pages/all_contracts.vue"),
  allCustomers: () => import("@/pages/all_customers.vue"),
  cancelledContracts: () => import("@/pages/cancelled_contracts.vue"),
  notes: () => import("@/pages/notes.vue"),
  visitor: () => import("@/pages/visitor.vue"),
  innvoice: () => import("@/pages/innvoice.vue"),
  contractPolicies: () => import("@/pages/contract/policies.vue"),
  optionsServices: () => import("@/pages/options/services.vue"),
  optionsCurrencies: () => import("@/pages/options/currencies.vue"),
  optionsInvoice: () => import("@/pages/options_invoice.vue"),
  optionsHr: () => import("@/pages/options_hr.vue"),
  optionsFinance: () => import("@/pages/options_finance.vue"),
  optionsContract: () => import("@/pages/options_contract.vue"),
  setupUsers: () => import("@/pages/setup/users.vue"),
  setupRoles: () => import("@/pages/setup/roles.vue"),
  setupSettings: () => import("@/pages/setup/settings.vue"),
  notifications: () => import("@/pages/notifications.vue"),
  accountSettings: () => import("@/pages/account-settings.vue"),
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
  { path: "/home", component: "home", name: "home", meta: { requiresAuth: true } },
  { path: "/finance", component: "finance", name: "finance", meta: { requiresAuth: true } },
  { path: "/income", component: "income", name: "income", meta: { requiresAuth: true } },
  { path: "/expenses", component: "expenses", name: "expenses", meta: { requiresAuth: true } },
  { path: "/stock", component: "stock", name: "stock", meta: { requiresAuth: true } },
  { path: "/reports", component: "reports", name: "reports", meta: { requiresAuth: true } },
  { path: "/hr", component: "hr", name: "hr", meta: { requiresAuth: true } },
  { path: "/staff-list", component: "staffList", name: "staff-list", meta: { requiresAuth: true } },
  { path: "/timesheet", component: "timesheet", name: "timesheet", meta: { requiresAuth: true } },
  {
    path: "/staff-salary",
    component: "staffSalary",
    name: "staff-salary",
    meta: { requiresAuth: true },
  },
  { path: "/bonuslist", component: "bonuslist", name: "bonuslist", meta: { requiresAuth: true } },
  { path: "/contracts", component: "contracts", name: "contracts", meta: { requiresAuth: true } },
  {
    path: "/all-customers",
    component: "allCustomers",
    name: "all-customers",
    meta: { requiresAuth: true },
  },
  {
    path: "/cancelled-contracts",
    component: "cancelledContracts",
    name: "cancelled-contracts",
    meta: { requiresAuth: true },
  },
  { path: "/notes", component: "notes", name: "notes", meta: { requiresAuth: true } },
  { path: "/visitor", component: "visitor", name: "visitor", meta: { requiresAuth: true } },
  { path: "/innvoice", component: "innvoice", name: "innvoice", meta: { requiresAuth: true } },
  {
    path: "/contract-policies",
    component: "contractPolicies",
    name: "contract-policies",
    meta: { requiresAuth: true },
  },
  {
    path: "/options/services",
    component: "optionsServices",
    name: "options-services",
    meta: { requiresAuth: true },
  },
  {
    path: "/options/currencies",
    component: "optionsCurrencies",
    name: "options-currencies",
    meta: { requiresAuth: true },
  },
  {
    path: "/options/invoice",
    component: "optionsInvoice",
    name: "options-invoice",
    meta: { requiresAuth: true },
  },
  { path: "/options/hr", component: "optionsHr", name: "options-hr", meta: { requiresAuth: true } },
  {
    path: "/options/finance",
    component: "optionsFinance",
    name: "options-finance",
    meta: { requiresAuth: true },
  },
  {
    path: "/options/contract",
    component: "optionsContract",
    name: "options-contract",
    meta: { requiresAuth: true },
  },
  {
    path: "/setup/users",
    component: "setupUsers",
    name: "setup-users",
    meta: { requiresAuth: true },
  },
  {
    path: "/setup/roles",
    component: "setupRoles",
    name: "setup-roles",
    meta: { requiresAuth: true },
  },
  {
    path: "/setup/settings",
    component: "setupSettings",
    name: "setup-settings",
    meta: { requiresAuth: true },
  },
  { path: "/notifications", component: "notifications", name: "notifications", meta: { requiresAuth: true } },
  { path: "/account-settings/:tab?", component: "accountSettings", name: "account-settings", meta: { requiresAuth: true } },
];

export const routes: RouteRecordRaw[] = routeDefinitions.map((route) => ({
  ...route,
  component: pageComponents[route.component as keyof typeof pageComponents],
  meta: {
    ...route.meta,
  },
}));

export const explicitRoutes = routes.map((route) => setupLayouts([route])[0]);
