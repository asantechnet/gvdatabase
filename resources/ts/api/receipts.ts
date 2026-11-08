import api from './axios'

export interface ReceiptMoney {
  id?: number
  code?: string | null
  symbol?: string | null
  rate?: number
  decimalPlaces?: number
}

export interface ReceiptCompany {
  name: string | null
  short_name: string | null
  primary_color: string
  secondary_color: string
  logo_url: string | null
  signature_url: string | null
  address: string | null
  phone: string | null
  email: string | null
  website: string | null
  licence: string | null
}

export interface ReceiptApplicant {
  id: number
  name: string
  father_name: string | null
  contact: string | null
  father_phone: string | null
  email: string | null
  address: string | null
  tax_number: string | null
  country: string | null
  education_level: string | null
  created_at: string | null
  status: string | null
}

export interface ReceiptLine {
  id: number
  name: string
  fee: number
  converted_fee: number
  currency: ReceiptMoney | null
  kind?: string
  code?: string | null
  status?: string | null
  purchased_at?: string | null
  contract_id?: number | null
  service?: string | null
  service_label?: string
  service_label_key?: string
  discount?: number
  rate?: number
  payment_status?: string
  payment_status_label?: string
}

export interface ReceiptPayload {
  serial_number: string | null
  document: 'receipt'
  date: string | null
  income: {
    id: number
    name: string
    type: string
    description: string | null
    amount: number
    quantity: number
    line_total: number
    rate: number
    currency: ReceiptMoney | null
    base_amount: number
  }
  company: ReceiptCompany
  applicant: ReceiptApplicant | null
  /** The contract the paid service belongs to (receipts are per service). */
  contract: {
    id: number | null
    contract_number: string | null
  } | null
  service: ReceiptLine | null
  services: ReceiptLine[]
  totals: {
    total_fee: number
    paid_amount: number
    balance: number
    currency: ReceiptMoney | null
  }
  signature: { url: string | null; name: string | null }
  recorded_by: { id: number; name: string } | null
}

const ReceiptApi = {
  async getForIncome(incomeId: number): Promise<ReceiptPayload> {
    const response = await api.get(`/incomes/${incomeId}/receipt`)

    return response.data.data as ReceiptPayload
  },
}

export default ReceiptApi
