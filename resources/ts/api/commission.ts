import api from './axios'

export interface CommissionCurrency {
  id: number
  code: string
  symbol: string
  decimal_places: number
}

export interface CommissionAccrual {
  id: number
  contract_id: number | null
  contract_number: string | null
  // The case that earned the accrual, and the stage it sits at now — the
  // backend only pays out while the case is in the completed stage.
  case_id: number | null
  case_name: string | null
  case_stage_key: string | null
  case_stage_name: string | null
  applicant_contract_id: number | null
  employee_id: number
  employee: string
  service: string | null
  base_currency_id: number
  base_currency: CommissionCurrency
  earned_at: string
  entry_type: 'earned' | 'adjustment'
  adjustment_reason: string | null
  source_base_amount: number
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

// Laravel API Resources wrap the payload in a second `data` key.
const CommissionApi = {
  async accruals(params?: Record<string, unknown>): Promise<CommissionAccrual[]> {
    return (await api.get<{ data: CommissionAccrual[] }>('/commissions/accruals', { params })).data.data
  },
  async summary(params?: Record<string, unknown>): Promise<CommissionSummary[]> {
    return (await api.get<{ data: CommissionSummary[] }>('/commissions/summary', { params })).data.data
  },
  /** Rebuild accruals from the cases sitting in the completed stage. */
  async sync(): Promise<{ cases: number; voided: number }> {
    return (await api.post<{ data: { cases: number; voided: number } }>('/commissions/sync')).data.data
  },
  async createAdjustment(payload: { employee_id: number; base_currency_id: number; effective_date: string; amount: number; reason: string }): Promise<CommissionAccrual> {
    return (await api.post<{ data: CommissionAccrual }>('/commissions/adjustments', payload)).data.data
  },
  async settlements(params?: Record<string, unknown>): Promise<CommissionSettlement[]> {
    return (await api.get<{ data: CommissionSettlement[] }>('/commission-settlements', { params })).data.data
  },
  async createSettlement(payload: { employee_id: number; base_currency_id: number; period_start: string; period_end: string; accrual_ids?: number[]; notes?: string }): Promise<CommissionSettlement> {
    return (await api.post<{ data: CommissionSettlement }>('/commission-settlements', payload)).data.data
  },
  async approve(id: number): Promise<CommissionSettlement> {
    return (await api.post<{ data: CommissionSettlement }>(`/commission-settlements/${id}/approve`)).data.data
  },
  async pay(id: number, payment_date: string): Promise<CommissionSettlement> {
    return (await api.post<{ data: CommissionSettlement }>(`/commission-settlements/${id}/pay`, { payment_date })).data.data
  },
  async cancel(id: number): Promise<CommissionSettlement> {
    return (await api.post<{ data: CommissionSettlement }>(`/commission-settlements/${id}/cancel`)).data.data
  },
}

export default CommissionApi
