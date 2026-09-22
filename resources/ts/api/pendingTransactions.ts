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
  applicant?: { id: number }
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

export interface PendingTransactionOptions {
  currencies: Array<{ id: number; name: string; code: string; symbol: string; is_base: boolean }>
  customers: Array<{ id: number; name: string }>
  employees: Array<{ id: number; name: string }>
  applicants: Array<{ id: number; customer_id: number; customer?: { id: number; name: string } }>
}
export interface PendingTransactionPage {
  data: PendingTransaction[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const unwrap = <T>(response: { data: { data: T } }) => response.data.data

export default {
  list: async (page = 1, perPage = 25) => unwrap<PendingTransactionPage>(await api.get('/loans', { params: { page, per_page: perPage } })),
  create: async (data: PendingTransactionInput) => unwrap<PendingTransaction>(await api.post('/loans', data)),
  update: async (id: number, data: Partial<PendingTransactionInput>) => unwrap<PendingTransaction>(await api.put(`/loans/${id}`, data)),
  options: async () => unwrap<PendingTransactionOptions>(await api.get('/loans/options')),
  review: async (id: number) => unwrap<PendingTransaction>(await api.post(`/loans/${id}/review`)),
  approve: async (id: number) => unwrap<PendingTransaction>(await api.post(`/loans/${id}/approve`)),
  reject: async (id: number, rejection_reason: string) => unwrap<PendingTransaction>(await api.post(`/loans/${id}/reject`, { rejection_reason })),
  post: async (id: number) => unwrap<PendingTransaction>(await api.post(`/loans/${id}/post`)),
}
