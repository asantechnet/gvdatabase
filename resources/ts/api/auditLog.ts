import api from './axios'

export type AuditEvent = 'created' | 'updated' | 'deleted' | 'restored'
export type AuditReviewStatus = 'pending' | 'approved' | 'discarded'

export interface AuditLog {
  id: number
  event: AuditEvent
  table_name: string
  auditable_id: number
  changed_columns: string[]
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  review_status: AuditReviewStatus
  reviewed_at: string | null
  created_at: string
  user: { id: number; name: string; email: string } | null
  employee: { id: number; name: string } | null
  reviewer: { id: number; name: string; email: string } | null
}

export interface AuditLogPage {
  data: AuditLog[]
  total: number
}

export const getAuditLogs = async (params?: { per_page?: number; table?: string; event?: AuditEvent; review_status?: AuditReviewStatus }): Promise<AuditLogPage> => {
  const response = await api.get('/audit-logs', { params })

  return {
    data: response.data.data as AuditLog[],
    total: response.data.total as number,
  }
}

export const getAuditLog = async (id: number): Promise<AuditLog> => {
  const response = await api.get(`/audit-logs/${id}`)
  return response.data.data as AuditLog
}

export const reviewAuditLog = async (id: number, decision: 'approved' | 'discarded'): Promise<void> => {
  await api.patch(`/audit-logs/${id}/review`, { decision })
}

export default { getAuditLogs, getAuditLog, reviewAuditLog }
