import api from './axios'

export type PendingTransactionType = 'income' | 'expense' | 'salary'
export type PendingTransactionStatus = 'draft' | 'submitted' | 'under_review' | 'approved' | 'rejected' | 'posted'

export interface PendingTransaction {
  id: number
  transaction_type: PendingTransactionType
  reason: string
  amount: string | number
  currency_id: number
  customer_id: number | null
  applicant_id: number | null
  employee_id: number | null
  approval_status: PendingTransactionStatus
  rejection_reason: string | null
  requested_at: string | null
  approved_at: string | null
  rejected_at: string | null
  posted_at: string | null
  currency?: { id: number; code: string; symbol: string }
  customer?: { id: number; name: string }
  employee?: { id: number; name: string }
  requester?: { id: number; name: string }
  approver?: { id: number; name: string }
}

export interface PendingTransactionInput {
  transaction_type: PendingTransactionType
  reason: string
  amount: number
  currency_id: number | null
  customer_id: number | null
  applicant_id: number | null
  employee_id: number | null
}

const unwrap = <T>(response: { data: { data: T } }) => response.data.data

export default {
  list: async () => unwrap<PendingTransaction[]>(await api.get('/pending-transactions')),
  create: async (data: PendingTransactionInput) => unwrap<PendingTransaction>(await api.post('/pending-transactions', data)),
  review: async (id: number) => unwrap<PendingTransaction>(await api.post(`/pending-transactions/${id}/review`)),
  approve: async (id: number) => unwrap<PendingTransaction>(await api.post(`/pending-transactions/${id}/approve`)),
  reject: async (id: number, rejection_reason: string) => unwrap<PendingTransaction>(await api.post(`/pending-transactions/${id}/reject`, { rejection_reason })),
  post: async (id: number) => unwrap<PendingTransaction>(await api.post(`/pending-transactions/${id}/post`)),
}
