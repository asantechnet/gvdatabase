import type { RouteLocationNormalizedLoaded } from 'vue-router'
import { driver } from 'driver.js'
import 'driver.js/dist/driver.css'
import { startAccountSettingsGuide } from './useAccountSettingsGuide'
import { currentLocale, translate } from '@/utils/localization'

interface PageGuideDefinition {
  title: string
  description: string
  actionTitle?: string
  actionDescription?: string
}

// The header guide is intentionally route-aware: it never changes routes or
// opens a walkthrough for a page that is not currently rendered.
const pageGuides: Record<string, PageGuideDefinition> = {
  home: { title: 'Dashboard', description: 'Use this page for a quick read on operational health. Review the key numbers first, then drill into the sections that need attention.' },
  tasks: { title: 'Tasks', description: 'Use this workspace to plan work, assign owners, track progress, and keep discussion beside the work it belongs to.' },
  reports: { title: 'Reports', description: 'Use reports to turn current records into reviewable operational, financial, and people insights before exporting or sharing them.' },
  finance: { title: 'Financial overview', description: 'Use this page to review live financial performance; figures are calculated from your current records rather than sample data.' },
  income: { title: 'Income', description: 'Record and review money received here. Reconcile the receipt, payment date, currency, and linked customer before changing a financial record.' },
  expenses: { title: 'Expenses', description: 'Record business spending here. Check the date, currency, approval context, and supporting details before saving an expense.' },
  asset: { title: 'Assets', description: 'Use this page to track company assets, their assignment, and their current condition.' },
  hr: { title: 'Human resources', description: 'This page brings together the people operations information your role is allowed to review.' },
  'staff-list': { title: 'Staff', description: 'Manage staff profiles, work details, and linked system accounts here. Review a person before editing access-related information.' },
  timesheet: { title: 'Timesheet', description: 'Use the timesheet to review attendance, hours, and daily work information for the selected period.' },
  'leave-requests': { title: 'Off-day requests', description: 'Review employee requests here. Check the requested duration, the remaining off-day allowance, whether the excess becomes absent, and any recorded work before approving.' },
  'payrolls': { title: 'Payroll', description: 'Use payroll to prepare and review salary records. Confirm the period, employee, currency, attendance effect, and deductions before posting.' },
  bonuslist: { title: 'Bonuses', description: 'Use this page to review bonus nominations and their approval state before they become part of payroll.' },
  'scholarship-contracts': { title: 'Scholarship contracts', description: 'Review scholarship agreements and their current lifecycle state.' },
  'test-offer-contracts': { title: 'Test Offer contracts', description: 'Review Test Offer agreements and their current lifecycle state.' },
  'academy-contracts': { title: 'Academy contracts', description: 'Review academy agreements and their current lifecycle state.' },
  'employee-contracts': { title: 'Employee contracts', description: 'Use this page for employee-specific contract records and their printable documents.' },
  'other-contracts': { title: 'Other contracts', description: 'Use this page for agreements that fall outside customer and employment contracts, and review their current lifecycle state.' },
  'all-customers': { title: 'Customers', description: 'Keep customer details and their active services accurate here; those services feed contract workflows.' },
  'cancelled-contracts': { title: 'Cancelled contracts', description: 'This is a historical view of cancelled contracts. Review the record and its reason instead of recreating it blindly.' },
  visitor: { title: 'Visitors', description: 'Capture prospective contacts here, then convert a qualified visitor into an applicant when the relationship is ready.' },
  notifications: { title: 'Notifications', description: 'Review the activity that needs your attention, then mark items read once the underlying work has been handled.' },
  'recurring-events': { title: 'Recurring events', description: 'Schedule repeatable operational work here. Review the cadence and next occurrence before enabling an automation.' },
  'setup-users': { title: 'Users', description: 'Manage user access and invitations here. Give only the access that each person needs for their work.' },
  'setup-roles': { title: 'Roles', description: 'Roles define access across the system. Review the permission summary carefully before changing a role.' },
  'setup-positions': { title: 'Positions', description: 'Use positions to keep job titles separate from system-access roles.' },
  'setup-settings': { title: 'System settings', description: 'Manage company, operational, and communication configuration here. Save one deliberate change at a time when settings affect the whole company.' },
  'options-currencies': { title: 'Currencies', description: 'Manage supported currencies and exchange rates here. The base currency deserves special care because it affects reporting.' },
  'options-hr': { title: 'HR options', description: 'Configure shared HR choices here so staff records stay consistent.' },
  'options-finance': { title: 'Finance options', description: 'Configure finance choices here; these settings affect how financial records are entered and reported.' },
  'options-contract': { title: 'Contract options', description: 'Configure reusable contract options here to keep new agreements consistent.' },
  'contract-templates': { title: 'Contract templates', description: 'Create and maintain reusable contract documents here. Changes affect future agreements, not already-issued contracts.' },
  'contract-policies': { title: 'Contract policies', description: 'Manage reusable contract policy content here before it is used in agreements.' },
  'contract-documents': { title: 'Contract documents', description: 'Use the tabs to switch between reusable contract templates and the policy clauses they are built from.' },
  audits: { title: 'Audit history', description: 'Use the audit trail to compare before/after values, identify the exact business record affected, and verify the actor and time before any supervised undo.' },
}

const isVisible = (element: Element | null): element is HTMLElement => Boolean(element instanceof HTMLElement && element.offsetParent)

const firstVisible = (root: HTMLElement, selectors: string[]) => selectors
  .flatMap(selector => [...root.querySelectorAll<HTMLElement>(selector)])
  .find(isVisible)

const primaryAction = (root: HTMLElement) => [...root.querySelectorAll<HTMLElement>('button, a[role="button"]')]
  .find(element => isVisible(element) && /\b(add|create|new|issue|record|invite|save|send|submit|request)\b/i.test(element.textContent ?? ''))

const driverProgressText = () => ({
  en: '{{current}} of {{total}}',
  'fa-AF': '{{current}} از {{total}}',
  'ps-AF': '{{current}} له {{total}}',
}[currentLocale()] ?? '{{current}} of {{total}}')

export function startPageGuide(route: RouteLocationNormalizedLoaded) {
  if (route.name === 'account-settings') {
    startAccountSettingsGuide(String(route.params.tab ?? 'account'))

    return
  }

  const pageRoot = document.querySelector<HTMLElement>('main.layout-page-content')
  if (!pageRoot)
    return

  const definition = route.name === 'support' ? {
    title: translate('pages.support.guide.title'),
    description: translate('pages.support.guide.description'),
    actionTitle: translate('pages.support.guide.actionTitle'),
    actionDescription: translate('pages.support.guide.actionDescription'),
  } : pageGuides[String(route.name)] ?? {
    title: 'This page',
    description: 'This guide explains the page currently open. It will not navigate you away or describe controls from another page.',
  }
  const heading = firstVisible(pageRoot, ['[data-page-title]', 'h1', 'h2', '.v-card-title'])
  const filters = firstVisible(pageRoot, ['input[type="search"]', '.v-data-table-header__content input', '[data-page-guide="filters"]'])
  const action = primaryAction(pageRoot)
  const results = firstVisible(pageRoot, ['.v-data-table', 'table', '[role="table"]', '[data-page-guide="results"]'])

  const isEnglish = currentLocale() === 'en'
  const visiblePageName = heading?.textContent?.trim() || translate('common.pageGuide.thisPage')
  const localizedDefinition = isEnglish || route.name === 'support' ? definition : {
    title: translate('common.pageGuide.pageTitle', { page: visiblePageName }),
    description: translate('common.pageGuide.pageDescription', { page: visiblePageName }),
  }
  const guideText = (englishTitle: string, englishDescription: string, step: 'filters' | 'action' | 'results') => isEnglish ? {
    title: englishTitle,
    description: englishDescription,
  } : {
    title: translate(`common.pageGuide.${step}Title`),
    description: translate(`common.pageGuide.${step}Description`),
  }

  const steps = [
    {
      element: heading,
      popover: { title: localizedDefinition.title, description: localizedDefinition.description, side: 'bottom' as const, align: 'start' as const },
    },
    ...(filters ? [{
      element: filters,
      popover: { ...guideText('Find the right record', 'Use the visible search or filter controls to narrow this page before opening or editing a record.', 'filters'), side: 'bottom' as const, align: 'start' as const },
    }] : []),
    ...(action ? [{
      element: action,
      popover: {
        title: isEnglish ? definition.actionTitle ?? 'Start an action' : translate('common.pageGuide.actionTitle'),
        description: isEnglish ? definition.actionDescription ?? 'Use this action to add or create a record. Review required fields before saving.' : translate('common.pageGuide.actionDescription'),
        side: 'bottom' as const,
        align: 'end' as const,
      },
    }] : []),
    ...(results ? [{
      element: results,
      popover: { ...guideText('Review the current data', 'This area shows the records available to you. Open a row or its actions when you need more detail.', 'results'), side: 'top' as const, align: 'center' as const },
    }] : []),
  ]

  driver({
    animate: true,
    allowClose: true,
    overlayOpacity: 0.55,
    showProgress: true,
    progressText: driverProgressText(),
    nextBtnText: translate('common.pageGuide.next'),
    prevBtnText: translate('common.pageGuide.back'),
    doneBtnText: translate('common.pageGuide.done'),
    steps,
  }).drive()
}
