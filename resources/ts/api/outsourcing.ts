import api from './axios'

export interface OutsourcingCurrency {
  id: number
  code: string
  symbol: string
  decimal_places: number
}

export interface OutsourcingCompanyRef {
  id: number
  legal_name: string
  trade_name: string | null
}

export interface OutsourcingAccrual {
  id: number
  company_id: number
  company: OutsourcingCompanyRef
  service: { id: number; type: string } | null
  invoice: { id: number; invoice_number: string | null } | null
  base_currency_id: number
  base_currency: OutsourcingCurrency
  earned_at: string
  entry_type: 'earned' | 'adjustment'
  adjustment_reason: string | null
  eligible_base_amount: number
  percentage: number
  outsourcing_base_amount: number
  status: 'pending' | 'approved' | 'paid' | 'void'
  outsourcing_settlement_id: number | null
}

export interface OutsourcingSettlement {
  id: number
  settlement_number: string
  company_id: number
  company: OutsourcingCompanyRef
  base_currency_id: number
  base_currency: OutsourcingCurrency
  period_start: string
  period_end: string
  gross_base_amount: number
  status: 'draft' | 'approved' | 'paid' | 'cancelled'
  notes: string | null
  accruals_count: number
  payment_date: string | null
}

const data = <T>(response: any): T => response?.data?.data ?? response?.data ?? response

export default {
  async accruals(params?: Record<string, unknown>): Promise<OutsourcingAccrual[]> {
    return data<OutsourcingAccrual[]>((await api.get('/outsourcing/accruals', { params })))
  },
  async settlements(params?: Record<string, unknown>): Promise<OutsourcingSettlement[]> {
    return data<OutsourcingSettlement[]>((await api.get('/outsourcing/settlements', { params })))
  },
  async createAdjustment(payload: { company_id: number; base_currency_id: number; effective_date: string; amount: number; reason: string }): Promise<OutsourcingAccrual> {
    return data<OutsourcingAccrual>((await api.post('/outsourcing/adjustments', payload)))
  },
  async createSettlement(payload: { company_id: number; base_currency_id: number; period_start: string; period_end: string; accrual_ids: number[]; notes?: string }): Promise<OutsourcingSettlement> {
    return data<OutsourcingSettlement>((await api.post('/outsourcing/settlements', payload)))
  },
  async approve(id: number): Promise<OutsourcingSettlement> { return data<OutsourcingSettlement>((await api.post(`/outsourcing/settlements/${id}/approve`))) },
  async pay(id: number, payment_date: string): Promise<OutsourcingSettlement> { return data<OutsourcingSettlement>((await api.post(`/outsourcing/settlements/${id}/pay`, { payment_date }))) },
  async cancel(id: number): Promise<OutsourcingSettlement> { return data<OutsourcingSettlement>((await api.post(`/outsourcing/settlements/${id}/cancel`))) },
}
