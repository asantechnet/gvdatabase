import api from './axios'

export interface InvoiceCurrency {
  id: number
  name: string
  rate: number
  symbol: string
  shortname: string
}

export interface InvoiceService {
  id: number
  name: string
  price: number
  quantity: number
  line_total: number
  source_fee: number
  source_currency: {
    code: string
    symbol: string
    rate: number
  }
  currency: InvoiceCurrency
  subDescription?: string
}

export interface Invoice {
  id: number
  invoice_number: string
  customer_id: number
  customer: {
    id: number
    name: string
    company?: string
    contact?: string
    email?: string
    address?: string
    tax_number?: string
  }
  'class-type': 'Standard' | 'Proforma' | 'Credit Note' | 'Debit Note'
  services: InvoiceService[]
  deptor: string
  status: 'Issued' | 'Partially Paid' | 'Paid' | 'Overdue' | 'Void'
  issue_date?: string | null
  due_date?: string | null
  tax_percent: number
  currency: InvoiceCurrency
  subtotal: number
  amount: number
  total_amount: number
  paid_amount: number
  balance: number
  discount: number
  notes?: string
  payment_method?: string | null
  payment_instructions: string[]
  voided_at?: string | null
  void_reason?: string | null
  created_at?: string
  updated_at?: string
}

export interface InvoiceParams {
  customer_id?: number
  status?: string
  search?: string
  unpaid?: boolean
}

export interface CreateInvoiceData {
  customer_id: number
  currency_id: number
  class_type: Invoice['class-type']
  issue_date?: string
  due_date?: string
  services: Array<{ id: number; quantity: number }>
  tax_percent: number
  discount?: number
  notes?: string
  payment_method?: string
  payment_instructions?: string[]
}

const unwrapData = <T>(response: unknown): T => {
  let payload = response

  while (payload !== null && typeof payload === 'object' && 'data' in payload)
    payload = (payload as { data: unknown }).data

  return payload as T
}

const numberValue = (value: unknown): number => {
  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : 0
}

const normalizeInvoice = (invoice: Invoice): Invoice => ({
  ...invoice,
  invoice_number: String(invoice.invoice_number || `INV-${invoice.id}`),
  tax_percent: numberValue(invoice.tax_percent),
  subtotal: numberValue(invoice.subtotal),
  amount: numberValue(invoice.amount),
  total_amount: numberValue(invoice.total_amount),
  paid_amount: numberValue(invoice.paid_amount),
  balance: numberValue(invoice.balance),
  discount: numberValue(invoice.discount),
  currency: {
    ...invoice.currency,
    id: numberValue(invoice.currency.id),
    rate: numberValue(invoice.currency.rate),
  },
  services: (invoice.services ?? []).map(service => ({
    ...service,
    price: numberValue(service.price),
    quantity: numberValue(service.quantity) || 1,
    line_total: numberValue(service.line_total),
    source_fee: numberValue(service.source_fee),
  })),
})

const InvoiceApi = {
  async getAll(params?: InvoiceParams): Promise<Invoice[]> {
    const response = await api.get('/invoices', { params })
    const invoices = unwrapData<Invoice[]>(response)

    if (!Array.isArray(invoices))
      throw new Error('Invalid invoice list response')

    return invoices.map(normalizeInvoice)
  },

  async getById(id: number): Promise<Invoice> {
    const response = await api.get(`/invoices/${id}`)
    return normalizeInvoice(unwrapData<Invoice>(response))
  },

  async create(data: CreateInvoiceData): Promise<Invoice> {
    const response = await api.post('/invoices', data)
    return normalizeInvoice(unwrapData<Invoice>(response))
  },

  async void(id: number, reason: string): Promise<Invoice> {
    const response = await api.put(`/invoices/${id}/void`, { reason })
    return normalizeInvoice(unwrapData<Invoice>(response))
  },
}

export default InvoiceApi
