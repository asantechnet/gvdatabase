import type { ContractStatus } from '@/api/contracts'

export type ContractExpiryUrgency = 'none' | 'low' | 'watch' | 'attention' | 'critical' | 'expired' | 'inactive'

export interface ContractExpiryPresentation {
  color: 'secondary' | 'success' | 'info' | 'warning' | 'error'
  days: number | null
  daysRemaining: number | null
  icon: string
  months: number | null
  urgency: ContractExpiryUrgency
}

const DAY_MS = 86_400_000

const parseDateOnly = (value: string): Date | null => {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  if (!match)
    return null

  const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])))

  return Number.isNaN(date.getTime()) ? null : date
}

const todayUtc = (): Date => {
  const now = new Date()

  return new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()))
}

const addMonthsClamped = (date: Date, months: number): Date => {
  const targetMonth = date.getUTCMonth() + months
  const lastDay = new Date(Date.UTC(date.getUTCFullYear(), targetMonth + 1, 0)).getUTCDate()

  return new Date(Date.UTC(date.getUTCFullYear(), targetMonth, Math.min(date.getUTCDate(), lastDay)))
}

export const calendarDurationUntil = (endDate: string, from = todayUtc()): { months: number; days: number; totalDays: number } | null => {
  const end = parseDateOnly(endDate)
  if (!end)
    return null

  const totalDays = Math.round((end.getTime() - from.getTime()) / DAY_MS)
  const earlier = totalDays >= 0 ? from : end
  const later = totalDays >= 0 ? end : from
  let months = (later.getUTCFullYear() - earlier.getUTCFullYear()) * 12 + later.getUTCMonth() - earlier.getUTCMonth()
  let anchor = addMonthsClamped(earlier, months)

  if (anchor > later) {
    months -= 1
    anchor = addMonthsClamped(earlier, months)
  }

  const days = Math.round((later.getTime() - anchor.getTime()) / DAY_MS)

  return { months, days, totalDays }
}

export const contractExpiryPresentation = (
  endDate: string | null,
  status: ContractStatus,
  from = todayUtc(),
): ContractExpiryPresentation => {
  if (!endDate)
    return { color: 'secondary', days: null, daysRemaining: null, icon: 'tabler-calendar-off', months: null, urgency: 'none' }

  const distance = calendarDurationUntil(endDate, from)
  if (!distance)
    return { color: 'secondary', days: null, daysRemaining: null, icon: 'tabler-calendar-off', months: null, urgency: 'none' }

  if (status === 'cancelled' || status === 'terminated')
    return { color: 'secondary', days: distance.days, daysRemaining: distance.totalDays, icon: 'tabler-calendar-x', months: distance.months, urgency: 'inactive' }
  if (status === 'expired' || distance.totalDays < 0)
    return { color: 'error', days: distance.days, daysRemaining: distance.totalDays, icon: 'tabler-calendar-exclamation', months: distance.months, urgency: 'expired' }
  if (distance.totalDays <= 7)
    return { color: 'error', days: distance.days, daysRemaining: distance.totalDays, icon: 'tabler-alert-triangle', months: distance.months, urgency: 'critical' }
  if (distance.totalDays <= 30)
    return { color: 'warning', days: distance.days, daysRemaining: distance.totalDays, icon: 'tabler-clock-exclamation', months: distance.months, urgency: 'attention' }
  if (distance.totalDays <= 90)
    return { color: 'info', days: distance.days, daysRemaining: distance.totalDays, icon: 'tabler-clock', months: distance.months, urgency: 'watch' }

  return { color: 'success', days: distance.days, daysRemaining: distance.totalDays, icon: 'tabler-calendar-check', months: distance.months, urgency: 'low' }
}

export const addContractMonths = (startDate: string, months: number): string | null => {
  const start = parseDateOnly(startDate)

  return start ? addMonthsClamped(start, months).toISOString().slice(0, 10) : null
}

export const dayAfter = (date: string): string | null => {
  const parsed = parseDateOnly(date)
  if (!parsed)
    return null

  parsed.setUTCDate(parsed.getUTCDate() + 1)

  return parsed.toISOString().slice(0, 10)
}
