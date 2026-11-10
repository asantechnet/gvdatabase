/**
 * One explicit list-endpoint → AuditRegistry resource map.
 *
 * The Axios response interceptor tags rows by exact list URL. Anything that is
 * not a recognised register falls back to "no rail" instead of guessing. A
 * page with several auditable tables under one URL can opt out of the shared
 * table rail; keeping the fallback neutral is safer than showing the wrong
 * record's audit state.
 */
export const AUDIT_RESOURCE_KEY = Symbol('audit-resource-key')

export const auditResourceByListUrl: Record<string, string> = {
  '/account-deactivation-requests': 'account_deactivations',
  '/announcements': 'announcements',
  '/applicant-contracts': 'applicant_contracts',
  '/applicant-stages': 'applicant_stages',
  '/applicants': 'applicants',
  '/assets': 'assets',
  '/attendances': 'attendances',
  '/boards': 'boards',
  '/contract-policies': 'contract_policies',
  '/contract-templates': 'contract_templates',
  '/contract-types': 'contract_types',
  '/contracts': 'contracts',
  '/currencies': 'currencies',
  '/daily-reports': 'daily_reports',
  '/educational-bonus-sources': 'educational_bonus_sources',
  '/educational-master-data/academy-classes': 'academy_classes',
  '/educational-master-data/classes': 'academy_classes',
  '/educational-master-data/countries': 'countries',
  '/educational-master-data/durations': 'durations',
  '/educational-master-data/education-levels': 'education_levels',
  '/educational-master-data/languages': 'languages',
  '/educational-master-data/programs': 'programs',
  '/educational-master-data/scholarships': 'educational_scholarships',
  '/educational-master-data/semesters': 'semesters',
  '/educational-master-data/skills': 'skills',
  '/educational-master-data/subjects': 'subjects',
  '/educational-master-data/teachers': 'teachers',
  '/educational-master-data/tests': 'tests',
  '/educational-master-data/times': 'times',
  '/educational-master-data/universities': 'universities',
  '/employee-advances': 'employee_advances',
  '/employees': 'employees',
  '/expenses': 'expenses',
  '/incomes': 'incomes',
  '/leave-requests': 'leaves',
  '/off-days': 'off_days',
  '/permissions': 'permissions',
  '/positions': 'positions',
  '/salaries': 'salaries',
  '/salary-payments': 'salary_payments',
  '/tags': 'tags',
  '/tasks': 'tasks',
  '/users': 'users',
  '/visitors': 'visitors',
  '/worthy-employees': 'worthy_employees',
}

export const auditResourceForUrl = (url?: string | null): string | null => {
  const path = `/${String(url ?? '')
    .replace(/^https?:\/\/[^/]+/i, '')
    .split('?')[0]
    .replace(/^\/?api\/?/, '')
    .replace(/^\/+/, '')
    .replace(/\/+$/, '')}`

  return auditResourceByListUrl[path] ?? null
}

/**
 * Tag list rows with their audit registry key without adding a visible or
 * enumerable field to the API payload. The symbol survives Vue's reactive
 * proxy read, so the shared table does not need every page to pass a key.
 */
export const tagAuditResourceRows = (url: string | undefined, payload: unknown): void => {
  const resource = auditResourceForUrl(url)
  if (!resource || !payload || typeof payload !== 'object')
    return

  const candidate = payload as { data?: unknown }
  const rows = Array.isArray(payload)
    ? payload
    : Array.isArray(candidate.data)
      ? candidate.data
      : null

  if (!rows)
    return

  for (const row of rows) {
    if (!row || typeof row !== 'object' || AUDIT_RESOURCE_KEY in row)
      continue

    Object.defineProperty(row, AUDIT_RESOURCE_KEY, {
      value: resource,
      enumerable: false,
      configurable: true,
    })
  }
}

export const auditResourceOfRow = (row: unknown): string | null => {
  if (!row || typeof row !== 'object')
    return null

  const value = (row as Record<symbol, unknown>)[AUDIT_RESOURCE_KEY]

  return typeof value === 'string' ? value : null
}
