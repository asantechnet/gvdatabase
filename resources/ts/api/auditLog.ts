import api from './axios'
import { type ApiReadOptions, backgroundReadConfig } from './backgroundRead'

export type AuditEvent = 'created' | 'updated' | 'deleted' | 'restored' | 'audit_scope_enabled' | 'audit_scope_disabled' | string
export type AuditResolution = 'pending' | 'approved' | 'discarded'
export type AuditState = 'pending' | 'audited'

export interface AuditPerson {
  id: number | null
  name: string
}

export interface AuditChange {
  field: string
  before: unknown
  after: unknown
}

export interface AuditLog {
  id: number
  event: AuditEvent
  resource_key: string
  subject_label: string
  changes: AuditChange[]
  changed_by: AuditPerson | null
  audited_by: AuditPerson | null
  audit_state: AuditState
  resolution: AuditResolution
  audited_at: string | null
  created_at: string
  can_revert: boolean
  revert_blocked_by_id: number | null
}

export interface AuditResource {
  id: number
  resource_key: string
  enabled: boolean
  updated_by: AuditPerson | null
  updated_at: string | null
}

export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface AuditLogParams {
  page?: number
  per_page?: number
  resource?: string
  event?: string
  audit_state?: AuditState
  resolution?: AuditResolution
  changed_by?: number
  audited_by?: number
  from?: string
  to?: string
  search?: string
  sort?: 'created_at' | 'audited_at'
  direction?: 'asc' | 'desc'
}

export const getAuditLogs = async (params?: AuditLogParams): Promise<Paginated<AuditLog>> => {
  const response = await api.get('/audit-logs', { params })

  return response.data as Paginated<AuditLog>
}

export const getAuditLog = async (id: number): Promise<AuditLog> => {
  const response = await api.get(`/audit-logs/${id}`)

  return response.data.data as AuditLog
}

export const getAuditHeartbeat = async (): Promise<AuditLog[]> => {
  const response = await api.get('/audit-logs/heartbeat', backgroundReadConfig({ background: true }))

  return Array.isArray(response.data.data) ? response.data.data as AuditLog[] : []
}

export const getPendingAuditCount = async (options?: ApiReadOptions): Promise<number> => {
  const response = await api.get('/audit-logs/pending-count', backgroundReadConfig(options))

  return Number(response.data.data?.count ?? 0)
}

export const reviewAuditLog = async (id: number, decision: 'approved' | 'discarded'): Promise<AuditLog> => {
  const response = await api.patch(`/audit-logs/${id}/review`, { decision })

  return response.data.data as AuditLog
}

export const bulkReviewAuditLogs = async (auditIds: number[], decision: 'approved' | 'discarded'): Promise<number> => {
  const response = await api.post('/audit-logs/bulk-review', { audit_ids: auditIds, decision })

  return Number(response.data.data?.count ?? 0)
}

export const getAuditResources = async (params?: { page?: number; per_page?: number; state?: 'enabled' | 'disabled'; search?: string }): Promise<Paginated<AuditResource> & { resource_keys: string[] }> => {
  const response = await api.get('/audit-resources', { params })

  return response.data as Paginated<AuditResource> & { resource_keys: string[] }
}

export const updateAuditResource = async (id: number, enabled: boolean): Promise<AuditResource> => {
  const response = await api.patch(`/audit-resources/${id}`, { enabled })

  return response.data.data as AuditResource
}

export default { getAuditLogs, getAuditLog, getAuditHeartbeat, getPendingAuditCount, reviewAuditLog, bulkReviewAuditLogs, getAuditResources, updateAuditResource }
