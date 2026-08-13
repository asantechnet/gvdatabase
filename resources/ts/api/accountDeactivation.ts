import api from './axios'

export type DeactivationStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

export interface AccountDeactivationRequest {
  id: number
  reason: string | null
  status: DeactivationStatus
  requested_at: string
  reviewed_at: string | null
  decision_notes: string | null
  cancelled_at: string | null
  requester: {
    id: number
    name: string
    email: string
    roles: string[]
    acc_status: string
  } | null
  employee: { id: number; name: string } | null
  reviewer: { id: number; name: string } | null
}

const unwrap = <T>(response: any): T => response.data.data as T

export const getMyDeactivationRequests = async (): Promise<AccountDeactivationRequest[]> =>
  unwrap(await api.get('/auth/account/deactivation-requests'))

export const requestAccountDeactivation = async (currentPassword: string, reason?: string): Promise<AccountDeactivationRequest> =>
  unwrap(await api.post('/auth/account/deactivation-requests', {
    current_password: currentPassword,
    reason: reason?.trim() || null,
  }))

export const cancelAccountDeactivation = async (id: number): Promise<AccountDeactivationRequest> =>
  unwrap(await api.delete(`/auth/account/deactivation-requests/${id}`))

export const getDeactivationRequests = async (): Promise<AccountDeactivationRequest[]> =>
  unwrap(await api.get('/account-deactivation-requests'))

export const reviewAccountDeactivation = async (
  id: number,
  decision: 'approve' | 'reject',
  decisionNotes?: string,
): Promise<AccountDeactivationRequest> => unwrap(await api.patch(`/account-deactivation-requests/${id}/review`, {
  decision,
  decision_notes: decisionNotes?.trim() || null,
}))

export const reactivateAccount = async (userId: number, decisionNotes?: string): Promise<void> => {
  await api.post(`/users/${userId}/reactivate`, { decision_notes: decisionNotes?.trim() || null })
}

export default {
  getMyDeactivationRequests,
  requestAccountDeactivation,
  cancelAccountDeactivation,
  getDeactivationRequests,
  reviewAccountDeactivation,
  reactivateAccount,
}
