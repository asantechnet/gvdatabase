import api from './axios'

export interface ReceiptMoney {
  id?: number
  code?: string | null
  symbol?: string | null
  rate?: number
  decimalPlaces?: number
}

export interface ReceiptLetterhead {
  source: 'none' | 'contract' | 'custom'
  url: string | null
  raw: string | null
  safe: { x: number; y: number; width: number; height: number }
}

export interface ReceiptCompany {
  name: string | null
  short_name: string | null
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
  application_date: string | null
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
  invoice: {
    id: number
    invoice_number: string
    status: string
    issue_date: string | null
    due_date: string | null
    amount: number
    currency: ReceiptMoney | null
  } | null
  services: ReceiptLine[]
  packages: ReceiptLine[]
  totals: {
    total_fee: number
    paid_amount: number
    balance: number
    currency: ReceiptMoney | null
  }
  letterhead: ReceiptLetterhead
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
