import { type SupportedLocale, getI18n, supportedLocales } from '@/plugins/i18n'

export const currentLocale = (): SupportedLocale => {
  const locale = String(getI18n().global.locale.value)

  return supportedLocales.includes(locale as SupportedLocale) ? locale as SupportedLocale : 'en'
}

export const translate = (key: string, values?: Record<string, unknown>): string =>
  String(getI18n().global.t(key, values ?? {}))

const calendarDate = (value: string | number | Date): Date => {
  // Date-only API values represent a local calendar day. Parsing them as UTC
  // can display the previous day for users west of UTC.
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value))
    return new Date(`${value}T00:00:00`)

  return value instanceof Date ? value : new Date(value)
}

export const formatLocalizedDate = (value: string | number | Date | null | undefined, style: 'short' | 'long' | 'time' | Intl.DateTimeFormatOptions = 'short'): string => {
  if (value === null || value === undefined || value === '')
    return translate('common.messages.notAvailable')
  const date = calendarDate(value)
  if (Number.isNaN(date.getTime()))
    return String(value)

  return typeof style === 'string'
    ? String(getI18n().global.d(date, style))
    : new Intl.DateTimeFormat(currentLocale(), style).format(date)
}

export const formatLocalizedNumber = (value: number | string | null | undefined, style: 'decimal' | 'percent' | Intl.NumberFormatOptions = 'decimal'): string => {
  const number = Number(value)
  if (!Number.isFinite(number))
    return translate('common.messages.notAvailable')

  return typeof style === 'string'
    ? String(getI18n().global.n(number, style))
    : new Intl.NumberFormat(currentLocale(), style).format(number)
}

const backendAliases: Record<string, string> = {
  'paid-leave': 'paidLeave',
  'unpaid-leave': 'unpaidLeave',
  'in_progress': 'inProgress',
  'in progress': 'inProgress',
  'not_started': 'notStarted',
  'not started': 'notStarted',
  'top_performer': 'topPerformer',
  'top performer': 'topPerformer',
  'project_milestone': 'projectMilestone',
  'project milestone': 'projectMilestone',
  'client_praise': 'clientPraise',
  'client praise': 'clientPraise',
  'work_anniversary': 'workAnniversary',
  'work anniversary': 'workAnniversary',
  'manager_nomination': 'managerNomination',
  'manager nomination': 'managerNomination',
}

const normalizedKey = (value: string) => {
  const normalized = value.trim().toLowerCase().replace(/[^\p{L}\p{N}\s_-]/gu, '')

  return backendAliases[normalized] ?? normalized.replace(/[_\s-]+(.)/g, (_, letter: string) => letter.toUpperCase())
}

export const translateBackendValue = (value: unknown, namespace = 'common.statuses'): string => {
  if (value === null || value === undefined || value === '')
    return translate('common.messages.notAvailable')
  const key = `${namespace}.${normalizedKey(String(value))}`
  const translated = translate(key)

  return translated.startsWith('[missing:') || translated === '' ? String(value) : translated
}

const policyTypeBases = [
  'pages.settings.policies.policies.policyLibrary.types',
  'pages.settings.policies.policies.contractTemplate.types',
]

const humanizeSlug = (slug: string) =>
  slug.replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/\b\w/g, letter => letter.toUpperCase())

// Contract policy clauses are scoped by contract-type slug. System and
// educational slugs have catalogue entries; user-defined types fall back to
// the stored contract-type name and finally to a humanized slug so the UI
// never renders a raw machine key.
export const translateContractPolicyType = (slug: unknown, fallbackName?: string | null): string => {
  const raw = String(slug ?? '').trim()
  if (!raw)
    return translate('common.messages.notAvailable')
  for (const base of policyTypeBases) {
    const translated = translate(`${base}.${raw}`)
    if (translated !== '' && !translated.startsWith('[missing:'))
      return translated
  }
  if (fallbackName)
    return fallbackName

  return humanizeSlug(raw) || raw
}

export const translateContractTypeRecord = (type: { slug: string; name: string } | null | undefined): string => {
  if (!type)
    return ''
  const translated = translateContractPolicyType(type.slug)

  // translateContractPolicyType only returns the raw/humanized slug when no
  // catalogue entry exists; in that case the stored custom name is correct.
  return translated === humanizeSlug(type.slug) ? type.name : translated
}
