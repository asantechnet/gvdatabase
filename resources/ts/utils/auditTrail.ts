import type { AuditTrailChange, AuditTrailRow } from '@/api/auditTrail'

export type AuditTrailState = 'created' | 'updated' | 'deleted' | 'neutral'

const KNOWN_EVENTS = ['created', 'updated', 'deleted', 'restored'] as const

export type AuditTrailEvent = typeof KNOWN_EVENTS[number] | 'other'

/** Map restored and unknown custom events onto the visual rail vocabulary. */
export const auditTrailState = (event?: string | null): AuditTrailState => {
  if (event === 'created')
    return 'created'
  if (event === 'deleted')
    return 'deleted'
  if (event === 'updated' || event === 'restored')
    return 'updated'

  return 'neutral'
}

export const auditTrailColor = (state: AuditTrailState): 'success' | 'warning' | 'error' | 'secondary' => {
  if (state === 'created')
    return 'success'
  if (state === 'updated')
    return 'warning'
  if (state === 'deleted')
    return 'error'

  return 'secondary'
}

export const auditEventKey = (event?: string | null): AuditTrailEvent =>
  KNOWN_EVENTS.includes(event as typeof KNOWN_EVENTS[number])
    ? event as typeof KNOWN_EVENTS[number]
    : 'other'

export const auditTrailRowClass = (trail?: AuditTrailRow | null): string => {
  if (!trail)
    return ''

  const state = auditTrailState(trail.event)

  return state === 'neutral' ? 'audit-trail-row' : `audit-trail-row audit-trail-row--${state}`
}

const normalize = (value: string) => value.replace(/[^a-z0-9]/gi, '').toLowerCase()

/** Match a visible column key to an audit change, including camelCase fields. */
export const changedFieldForColumn = (trail: AuditTrailRow | null | undefined, columnKey: string): AuditTrailChange | null => {
  if (!trail?.changed_fields?.length)
    return null

  const candidates = [normalize(columnKey), normalize(columnKey.split('.').pop() ?? '')]

  return trail.changed_fields.find(change => candidates.includes(normalize(change.field))) ?? null
}

export const auditValueText = (value: unknown): string => {
  if (value === null || value === undefined || value === '')
    return '—'
  if (typeof value === 'object')
    return JSON.stringify(value)

  return String(value)
}

export const auditChangeText = (change: AuditTrailChange): string => {
  const field = change.field_label || change.field
  const before = change.old_label || auditValueText(change.before)
  const after = change.new_label || auditValueText(change.after)

  return `${field}: ${before} → ${after}`
}

/** Accessibility summary for a row's changed fields, including hidden columns. */
export const auditChangeSummary = (trail: AuditTrailRow | null | undefined, limit = 3): string =>
  (trail?.changed_fields ?? [])
    .slice(0, limit)
    .map(auditChangeText)
    .join(' · ')

export const auditCellClass = (trail: AuditTrailRow | null | undefined, columnKey: string): string =>
  changedFieldForColumn(trail, columnKey) ? 'audit-trail-cell audit-trail-cell--changed' : ''

export const auditCellTitle = (trail: AuditTrailRow | null | undefined, columnKey: string): string | undefined => {
  const change = changedFieldForColumn(trail, columnKey)

  return change ? auditChangeText(change) : undefined
}
