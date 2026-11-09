import api from './axios'
import type { ReceiptCompany, ReceiptMoney } from './receipts'

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
  class_id: number | null
  class_name: string | null
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

export interface CommissionSlipAccrual {
  id: number
  earned_at: string | null
  entry_type: 'earned' | 'adjustment'
  adjustment_reason: string | null
  contract_number: string | null
  class_name: string | null
  case_name: string | null
  service: string | null
  service_label: string
  eligible_base_amount: number
  percentage: number
  commission_base_amount: number
}

export interface CommissionSlipEmployee {
  id: number
  name: string
  father_name: string | null
  contact: string | null
  position: string | null
  department: string | null
  join_date: string | null
}

export interface CommissionSlipPayload {
  reference: string | null
  document: 'commission_slip'
  date: string | null
  company: ReceiptCompany
  employee: CommissionSlipEmployee | null
  settlement: {
    period_start: string | null
    period_end: string | null
    status: CommissionSettlement['status']
    payment_date: string | null
    notes: string | null
  }
  accruals: CommissionSlipAccrual[]
  totals: {
    gross: number
    count: number
    currency: ReceiptMoney | null
  }
  signature: { url: string | null; name: string | null }
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
  async slip(id: number): Promise<CommissionSlipPayload> {
    return (await api.get<{ data: CommissionSlipPayload }>(`/commission-settlements/${id}/slip`)).data.data
  },
}

export default CommissionApi
