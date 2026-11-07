import api from './axios'
import type { Attachment } from './attachments'
import type { Currency } from './currency'
import { cleanListParams, readPage, type ListPage, type ListPageParams } from './pagination'

type MoneyCurrency = Pick<Currency, 'id' | 'code' | 'symbol' | 'rate' | 'decimalPlaces'>

export interface IncomeApplicantOption {
  id: number
  name: string
  contact: string | null
}

export const getIncomeApplicants = async (search = ''): Promise<IncomeApplicantOption[]> => {
  const response = await api.get('/incomes/applicants', { params: { search } })

  return Array.isArray(response.data.data) ? response.data.data : []
}

/** The contracted service line a receipt pays for. */
export interface IncomeContractService {
  id: number
  contract_id: number | null
  contract_number: string | null
  service: string | null
  service_label: string
  service_label_key: string
  fee: number
  discount: number
  payable_total: number
  amount_paid: number
  remaining_total: number
  payment_status: string
  payment_status_label: string
  currency: MoneyCurrency | null
}

/** A payable service line of one applicant, for the payment picker. */
export interface ApplicantContractPaymentOption {
  id: number
  contract_id: number | null
  contract_number: string | null
  contract_status?: string | null
  is_cancelled: boolean
  currency_rate: number
  service: string | null
  service_label: string
  service_label_key: string
  catalogue_service?: string | null
  fee: number
  discount: number
  payable_total: number
  amount_paid: number
  recorded_amount_paid: number
  amount_paid_overridden: boolean
  payment_status_overridden: boolean
  remaining_total: number
  payment_status: string
  payment_status_label: string
  currency: MoneyCurrency | null
  created_at?: string
}

export interface ContractPaymentOverrideData {
  amount_paid: number | null
  payment_status: 'paid' | 'unpaid' | 'partially_paid' | null
}

export const setContractPaymentOverride = async (lineId: number, data: ContractPaymentOverrideData): Promise<ApplicantContractPaymentOption> => {
  const response = await api.put(`/incomes/applicant-contracts/${lineId}/payment-override`, data)

  return response.data.data
}

export type FinanceRecordStatus = 'draft' | 'approved' | 'rejected'

export interface Income {
  id: number
  receipt_number: string
  attachments?: Attachment[]
  applicant_id: number | null
  applicant_contract_id: number | null
  contract_service?: IncomeContractService | null
  currency_id: number
  type: string
  description?: string
  amount: number
  quantity: number
  currency: MoneyCurrency | null
  cnv_curr_shrt: string
  base_curr_shrt: string
  rate: number
  base_amount: number
  /** Why an issued receipt's value was changed; null when never revalued. */
  revaluation_reason: string | null
  currentBaseAmount: number | null
  currentBaseCurrency: Pick<Currency, 'id' | 'code' | 'symbol' | 'decimalPlaces'> | null
  date: string
  recorded_by?: number | null
  recordedBy?: { id: number; name: string } | null
  status: FinanceRecordStatus
  approved_by?: number | null
  approved_at?: string | null
  rejected_at?: string | null
  rejection_reason?: string | null
  approver?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export interface IncomeCreateData {
  applicant_id?: number | null
  applicant_contract_id?: number | null
  currency_id: number
  type: string
  description?: string
  amount: number
  quantity: number
  rate: number
  date: string
  /** Omitted means the record is approved and posted directly. */
  status?: Extract<FinanceRecordStatus, 'draft' | 'approved'>
}

export interface IncomeUpdateData {
  applicant_id?: number | null
  applicant_contract_id?: number | null
  currency_id?: number
  type?: string
  description?: string | null
  amount?: number
  quantity?: number
  rate?: number
  date?: string
  /** Mandatory when a value field changes; audited with the receipt. */
  revaluation_reason?: string
}

export interface IncomeParams {
  search?: string
  type?: string
  status?: FinanceRecordStatus
  currency_id?: number
  applicant_id?: number
  applicant_contract_id?: number
  date_from?: string
  date_to?: string
}

/** Every matching receipt; also used by the print report. */
export const getIncomes = async (params?: IncomeParams): Promise<Income[]> => {
  try {
    const response = await api.get('/incomes', { params })

    // Handle nested data structure where actual data is in response.data.data
    return Array.isArray(response.data.data) ? response.data.data : []
  }
  catch (error) {
    console.error('Error fetching incomes:', error)
    throw error
  }
}

/** One page of the receipt ledger. */
export const getIncomesPage = async (params?: IncomeParams & ListPageParams): Promise<ListPage<Income>> => {
  const response = await api.get('/incomes', {
    params: cleanListParams({ ...params, page: params?.page, per_page: params?.perPage }),
  })

  return readPage<Income>(response.data)
}

export const getIncome = async (id: number): Promise<Income> => {
  try {
    const response = await api.get(`/incomes/${id}`)

    // Handle nested data structure
    return response.data.data
  }
  catch (error) {
    console.error(`Error fetching income ${id}:`, error)
    throw error
  }
}

/** The service lines of one applicant that a payment can be recorded against. */
export const getApplicantContractPayments = async (applicantId: number): Promise<ApplicantContractPaymentOption[]> => {
  try {
    const response = await api.get('/incomes/applicant-contracts', { params: { applicant_id: applicantId } })

    return Array.isArray(response.data.data) ? response.data.data : []
  }
  catch (error) {
    console.error(`Error fetching contract services for applicant ${applicantId}:`, error)
    throw error
  }
}

export const createIncome = async (data: IncomeCreateData): Promise<Income> => {
  try {
    const response = await api.post('/incomes', data)

    // Handle nested data structure
    return response.data.data
  }
  catch (error) {
    console.error('Error creating income:', error)
    throw error
  }
}

export const updateIncome = async (id: number, data: IncomeUpdateData): Promise<Income> => {
  try {
    const response = await api.put(`/incomes/${id}`, data)

    // Handle nested data structure
    return response.data.data
  }
  catch (error) {
    console.error(`Error updating income ${id}:`, error)
    throw error
  }
}

export const approveIncome = async (id: number): Promise<Income> => {
  const response = await api.patch(`/incomes/${id}/approve`)

  return response.data.data
}

export const rejectIncome = async (id: number, rejectionReason: string): Promise<Income> => {
  const response = await api.patch(`/incomes/${id}/reject`, { rejection_reason: rejectionReason })

  return response.data.data
}

export const deleteIncome = async (id: number): Promise<void> => {
  try {
    await api.delete(`/incomes/${id}`)
  }
  catch (error) {
    console.error(`Error deleting income ${id}:`, error)
    throw error
  }
}
