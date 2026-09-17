import api from './axios'

export interface CommissionCurrency {
  id: number
  code: string
  symbol: string
  decimal_places: number
}

export interface CommissionAccrual {
  id: number
  employee_id: number
  employee: string
  service_id: number
  service: string
  invoice_id: number
  invoice_number: string
  income_id: number
  receipt_number: string
  base_currency_id: number
  base_currency: CommissionCurrency
  earned_at: string
  entry_type: 'earned' | 'adjustment'
  commission_role: 'delivery' | 'marketing' | 'adjustment'
  adjustment_reason: string | null
  eligible_base_amount: number
  percentage: number
  commission_base_amount: number
  status: 'pending' | 'approved' | 'paid' | 'void'
  settlement_id: number | null
}

export interface CommissionSettlement {
  id: number
  settlement_number: string
  employee_id: number
  employee: string
  base_currency_id: number
  base_currency: CommissionCurrency
  period_start: string
  period_end: string
  gross_base_amount: number
  status: 'draft' | 'approved' | 'paid' | 'cancelled'
  notes: string | null
  accrual_count: number
  approved_at: string | null
  payment_date: string | null
  paid_at: string | null
  expense_id: number | null
}

export interface CommissionSummary {
  base_currency_id: number
  currency: CommissionCurrency
  status: CommissionAccrual['status']
  count: number
  amount: number
}

const data = <T>(response: any): T => response?.data?.data ?? response?.data ?? response

export default {
  async accruals(params?: Record<string, unknown>): Promise<CommissionAccrual[]> {
    return data<CommissionAccrual[]>((await api.get('/commissions/accruals', { params })))
  },
  async summary(params?: Record<string, unknown>): Promise<CommissionSummary[]> {
    return data<CommissionSummary[]>((await api.get('/commissions/summary', { params })))
  },
  async createAdjustment(payload: { employee_id: number; base_currency_id: number; effective_date: string; amount: number; reason: string }): Promise<CommissionAccrual> {
    return data<CommissionAccrual>((await api.post('/commissions/adjustments', payload)))
  },
  async settlements(params?: Record<string, unknown>): Promise<CommissionSettlement[]> {
    return data<CommissionSettlement[]>((await api.get('/commission-settlements', { params })))
  },
  async createSettlement(payload: { employee_id: number; base_currency_id: number; period_start: string; period_end: string; accrual_ids?: number[]; notes?: string }): Promise<CommissionSettlement> {
    return data<CommissionSettlement>((await api.post('/commission-settlements', payload)))
  },
  async approve(id: number): Promise<CommissionSettlement> {
    return data<CommissionSettlement>((await api.post(`/commission-settlements/${id}/approve`)))
  },
  async pay(id: number, payment_date: string): Promise<CommissionSettlement> {
    return data<CommissionSettlement>((await api.post(`/commission-settlements/${id}/pay`, { payment_date })))
  },
  async cancel(id: number): Promise<CommissionSettlement> {
    return data<CommissionSettlement>((await api.post(`/commission-settlements/${id}/cancel`)))
  },
}
