import api from './axios'

export interface AuditTrailChange {
  field: string
  field_label?: string
  before: unknown
  after: unknown
  old_label?: string | null
  new_label?: string | null
}

export interface AuditTrailRow {
  id: number
  audit_id: number
  event: string
  changed_fields: AuditTrailChange[]
  created_at: string | null
  changed_by: { id: number | null; name: string } | null
  audit_state: 'pending' | 'audited'
  resolution: 'pending' | 'approved' | 'discarded' | null
}

export const AUDIT_ROW_TRAILS_PATH = '/audit-logs/row-trails'

/**
 * One bounded latest-audit read for a page of rows. The endpoint is quiet: a
 * permission gap or transient failure simply leaves the table neutral.
 */
export const getAuditRowTrails = async (resource: string, ids: number[]): Promise<AuditTrailRow[]> => {
  const uniqueIds = [...new Set(ids.filter(id => Number.isInteger(id) && id > 0))].slice(0, 100)

  if (!resource || !uniqueIds.length)
    return []

  const response = await api.get(AUDIT_ROW_TRAILS_PATH, {
    params: { resource, ids: uniqueIds },
    timeout: 10000,
    headers: { 'X-Suppress-Error-Notification': 'true' },
  })

  return Array.isArray(response.data?.data) ? response.data.data as AuditTrailRow[] : []
}
