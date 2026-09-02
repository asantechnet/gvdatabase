import { getI18n, supportedLocales, type SupportedLocale } from '@/plugins/i18n'

export const currentLocale = (): SupportedLocale => {
  const locale = String(getI18n().global.locale.value)
  return supportedLocales.includes(locale as SupportedLocale) ? locale as SupportedLocale : 'en'
}

export const translate = (key: string, values?: Record<string, unknown>): string =>
  String(getI18n().global.t(key, values ?? {}))

export const formatLocalizedDate = (value: string | number | Date | null | undefined, style: 'short' | 'long' | 'time' | Intl.DateTimeFormatOptions = 'short'): string => {
  if (value === null || value === undefined || value === '') return translate('common.messages.notAvailable')
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return typeof style === 'string'
    ? String(getI18n().global.d(date, style))
    : new Intl.DateTimeFormat(currentLocale(), style).format(date)
}

export const formatLocalizedNumber = (value: number | string | null | undefined, style: 'decimal' | 'percent' | Intl.NumberFormatOptions = 'decimal'): string => {
  const number = Number(value)
  if (!Number.isFinite(number)) return translate('common.messages.notAvailable')
  return typeof style === 'string'
    ? String(getI18n().global.n(number, style))
    : new Intl.NumberFormat(currentLocale(), style).format(number)
}

const backendAliases: Record<string, string> = {
  'paid-leave': 'paidLeave',
  'unpaid-leave': 'unpaidLeave',
  in_progress: 'inProgress',
  'in progress': 'inProgress',
  not_started: 'notStarted',
  'not started': 'notStarted',
  top_performer: 'topPerformer',
  'top performer': 'topPerformer',
  project_milestone: 'projectMilestone',
  'project milestone': 'projectMilestone',
  client_praise: 'clientPraise',
  'client praise': 'clientPraise',
  work_anniversary: 'workAnniversary',
  'work anniversary': 'workAnniversary',
  manager_nomination: 'managerNomination',
  'manager nomination': 'managerNomination',
}

const normalizedKey = (value: string) => {
  const normalized = value.trim().toLowerCase().replace(/[^\p{L}\p{N}\s_-]/gu, '')
  return backendAliases[normalized] ?? normalized.replace(/[_\s-]+(.)/g, (_, letter: string) => letter.toUpperCase())
}

export const translateBackendValue = (value: unknown, namespace = 'common.statuses'): string => {
  if (value === null || value === undefined || value === '') return translate('common.messages.notAvailable')
  const key = `${namespace}.${normalizedKey(String(value))}`
  const translated = translate(key)
  return translated.startsWith('[missing:') || translated === '' ? String(value) : translated
}
