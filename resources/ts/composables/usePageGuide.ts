import type { RouteLocationNormalizedLoaded } from 'vue-router'
import { driver } from 'driver.js'
import 'driver.js/dist/driver.css'
import { startAccountSettingsGuide } from './useAccountSettingsGuide'

interface PageGuideDefinition {
  title: string
  description: string
}

// The header guide is intentionally route-aware: it never changes routes or
// opens a walkthrough for a page that is not currently rendered.
const pageGuides: Record<string, PageGuideDefinition> = {
  home: { title: 'Dashboard', description: 'Use this page for a quick read on operational health. Review the key numbers first, then drill into the sections that need attention.' },
  tasks: { title: 'Tasks', description: 'Use this workspace to plan work, assign owners, track progress, and keep discussion beside the work it belongs to.' },
  reports: { title: 'Reports', description: 'Use reports to turn current records into reviewable operational, financial, and people insights before exporting or sharing them.' },
  finance: { title: 'Financial overview', description: 'Use this page to review live financial performance; figures are calculated from your current records rather than sample data.' },
  income: { title: 'Income', description: 'Record and review money received here. Use search and filters before changing an existing financial record.' },
  expenses: { title: 'Expenses', description: 'Record business spending here. Check the date, currency, and supporting details before saving a new expense.' },
  innvoice: { title: 'Invoices', description: 'Create invoices from customer services, track their payment status, and keep issued records intact by voiding rather than deleting them.' },
  asset: { title: 'Assets', description: 'Use this page to track company assets, their assignment, and their current condition.' },
  hr: { title: 'Human resources', description: 'This page brings together the people operations information your role is allowed to review.' },
  'staff-list': { title: 'Staff', description: 'Manage staff profiles, work details, and linked system accounts here. Review a person before editing access-related information.' },
  timesheet: { title: 'Timesheet', description: 'Use the timesheet to review attendance, hours, and daily work information for the selected period.' },
  leaves: { title: 'Time off', description: 'Review and manage leave requests here. Dates and balances should be checked before approving a request.' },
  'staff-salary': { title: 'Payroll', description: 'Use payroll to prepare and review salary records. Confirm the period, employee, currency, and deductions before posting.' },
  bonuslist: { title: 'Bonuses', description: 'Use this page to review bonus nominations and their approval state before they become part of payroll.' },
  commissions: { title: 'Commissions', description: 'Track commission earnings and settlements here. The page distinguishes pending, approved, and paid amounts.' },
  contracts: { title: 'Contracts', description: 'Create and manage customer contracts here. Review parties, terms, dates, and the selected template before finalizing.' },
  'employee-contracts': { title: 'Employee contracts', description: 'Use this page for employee-specific contract records and their printable documents.' },
  'company-contracts': { title: 'Company contracts', description: 'Use this page for company contract records and their current lifecycle state.' },
  'all-customers': { title: 'Customers', description: 'Keep customer details and their active services accurate here; those services feed invoicing and contract workflows.' },
  'cancelled-contracts': { title: 'Cancelled contracts', description: 'This is a historical view of cancelled contracts. Review the record and its reason instead of recreating it blindly.' },
  visitor: { title: 'Visitors', description: 'Capture prospective contacts here, then convert qualified visitors into leads or customers when the relationship is ready.' },
  leads: { title: 'Leads', description: 'Use leads to follow up on qualified prospects and convert them to customers when work is ready to begin.' },
  companies: { title: 'Companies', description: 'Maintain counterparty company records here so contracts and customer relationships use consistent information.' },
  notifications: { title: 'Notifications', description: 'Review the activity that needs your attention, then mark items read once the underlying work has been handled.' },
  'recurring-events': { title: 'Recurring events', description: 'Schedule repeatable operational work here. Review the cadence and next occurrence before enabling an automation.' },
  'setup-users': { title: 'Users', description: 'Manage user access and invitations here. Give only the access that each person needs for their work.' },
  'setup-roles': { title: 'Roles', description: 'Roles define access across the system. Review the permission summary carefully before changing a role.' },
  'setup-positions': { title: 'Positions', description: 'Use positions to keep job titles separate from system-access roles.' },
  'setup-settings': { title: 'System settings', description: 'Manage company, operational, and communication configuration here. Save one deliberate change at a time when settings affect the whole company.' },
  'setup-marketers': { title: 'Marketers', description: 'Manage marketer records and their relationships here.' },
  'setup-offices': { title: 'Offices', description: 'Maintain office details here, including information used by attendance and asset workflows.' },
  'options-services': { title: 'Services', description: 'Define the services your company offers here. These records are used when creating customer services, invoices, and contracts.' },
  'options-currencies': { title: 'Currencies', description: 'Manage supported currencies and exchange rates here. The base currency deserves special care because it affects reporting.' },
  'options-invoice': { title: 'Invoice options', description: 'Configure invoice-related options here before creating new financial documents.' },
  'options-hr': { title: 'HR options', description: 'Configure shared HR choices here so staff records stay consistent.' },
  'options-finance': { title: 'Finance options', description: 'Configure finance choices here; these settings affect how financial records are entered and reported.' },
  'options-contract': { title: 'Contract options', description: 'Configure reusable contract options here to keep new agreements consistent.' },
  'contract-templates': { title: 'Contract templates', description: 'Create and maintain reusable contract documents here. Changes affect future agreements, not already-issued contracts.' },
  'contract-policies': { title: 'Contract policies', description: 'Manage reusable contract policy content here before it is used in agreements.' },
  audits: { title: 'Audit history', description: 'Use the audit trail to understand what changed, when it changed, and who made the change before considering a supervised undo.' },
  'payment-details': { title: 'Payment details', description: 'Manage payment records and their links to invoices here. Financial history is preserved for traceability.' },
}

const isVisible = (element: Element | null): element is HTMLElement => Boolean(element instanceof HTMLElement && element.offsetParent)

const firstVisible = (root: HTMLElement, selectors: string[]) => selectors
  .flatMap(selector => [...root.querySelectorAll<HTMLElement>(selector)])
  .find(isVisible)

const primaryAction = (root: HTMLElement) => [...root.querySelectorAll<HTMLElement>('button, a[role="button"]')]
  .find(element => isVisible(element) && /\b(add|create|new|issue|record|invite|save)\b/i.test(element.textContent ?? ''))

export function startPageGuide(route: RouteLocationNormalizedLoaded) {
  if (route.name === 'account-settings') {
    startAccountSettingsGuide(String(route.params.tab ?? 'account'))

    return
  }

  const pageRoot = document.querySelector<HTMLElement>('main.layout-page-content')
  if (!pageRoot)
    return

  const definition = pageGuides[String(route.name)] ?? {
    title: 'This page',
    description: 'This guide explains the page currently open. It will not navigate you away or describe controls from another page.',
  }
  const heading = firstVisible(pageRoot, ['[data-page-title]', 'h1', 'h2', '.v-card-title'])
  const filters = firstVisible(pageRoot, ['input[type="search"]', '.v-data-table-header__content input', '[data-page-guide="filters"]'])
  const action = primaryAction(pageRoot)
  const results = firstVisible(pageRoot, ['.v-data-table', 'table', '[role="table"]', '[data-page-guide="results"]'])

  const steps = [
    {
      element: heading,
      popover: { title: definition.title, description: definition.description, side: 'bottom' as const, align: 'start' as const },
    },
    ...(filters ? [{
      element: filters,
      popover: { title: 'Find the right record', description: 'Use the visible search or filter controls to narrow this page before opening or editing a record.', side: 'bottom' as const, align: 'start' as const },
    }] : []),
    ...(action ? [{
      element: action,
      popover: { title: 'Start an action', description: 'Use this action to add or create a record. Review required fields before saving.', side: 'bottom' as const, align: 'end' as const },
    }] : []),
    ...(results ? [{
      element: results,
      popover: { title: 'Review the current data', description: 'This area shows the records available to you. Open a row or its actions when you need more detail.', side: 'top' as const, align: 'center' as const },
    }] : []),
  ]

  driver({
    animate: true,
    allowClose: true,
    overlayOpacity: 0.55,
    showProgress: true,
    progressText: '{{current}} of {{total}}',
    nextBtnText: 'Next',
    prevBtnText: 'Back',
    doneBtnText: 'Done',
    steps,
  }).drive()
}
