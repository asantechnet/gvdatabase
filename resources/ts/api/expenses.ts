import api from './axios'
import type { Attachment } from './attachments'
import type { Currency } from './currency'
import { cleanListParams, readPage, type ListPage, type ListPageParams } from './pagination'

export interface Expense {
  id: number
  applicant_id: number | null
  applicant?: { id: number; name: string } | null
  currency_id: number
  type: string
  description: string
  document?: { name: string; mimeType: string; size: number; downloadUrl: string } | null
  attachments?: Attachment[]
  amount: number
  quantity: number
  currency: Pick<Currency, 'id' | 'code' | 'symbol' | 'rate' | 'decimalPlaces'> | null
  cnv_curr_shrt: string; base_curr_shrt: string; rate: number; base_amount: number
  currentBaseAmount: number | null
  currentBaseCurrency: Pick<Currency, 'id' | 'code' | 'symbol' | 'decimalPlaces'> | null
  date: string
  recorded_by?: number | null
  recordedBy?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export interface ExpenseCreateData {
  applicant_id?: number | null
  currency_id: number
  type: string
  description: string
  amount: number
  quantity: number
  rate: number
  date: string
}

export interface ExpenseUpdateData {
  applicant_id?: number | null
  currency_id?: number
  type?: string
  description?: string
  amount?: number
  quantity?: number
  rate?: number
  date?: string
}

export type ExpenseFormData = (Expense | ExpenseCreateData) & { base_amount?: number }

export interface ExpenseParams {
  search?: string
  type?: string
  currency_id?: number
  date_from?: string
  date_to?: string
}

/** Every matching expense; also used by the print report. */
export const getExpenses = async (
  params?: ExpenseParams,
): Promise<Expense[]> => {
  try {
    const response = await api.get('/expenses', { params: cleanListParams({ ...params }) })

    // Handle nested data structure where actual data is in response.data.data
    return Array.isArray(response.data.data) ? response.data.data : []
  }
  catch (error) {
    console.error('Error fetching expenses:', error)
    throw error
  }
}

/** One page of the expense ledger. */
export const getExpensesPage = async (
  params?: ExpenseParams & ListPageParams,
): Promise<ListPage<Expense>> => {
  const response = await api.get('/expenses', {
    params: cleanListParams({ ...params, page: params?.page, per_page: params?.perPage }),
  })

  return readPage<Expense>(response.data)
}

export const getExpense = async (id: number): Promise<Expense> => {
  try {
    const response = await api.get(`/expenses/${id}`)

    // Handle nested data structure
    return response.data.data
  }
  catch (error) {
    console.error(`Error fetching expense ${id}:`, error)
    throw error
  }
}

export const createExpense = async (
  data: ExpenseCreateData,
): Promise<Expense> => {
  try {
    const response = await api.post('/expenses', data)

    // Handle nested data structure
    return response.data.data
  }
  catch (error) {
    console.error('Error creating expense:', error)
    throw error
  }
}

export const updateExpense = async (
  id: number,
  data: ExpenseUpdateData,
): Promise<Expense> => {
  try {
    const response = await api.put(`/expenses/${id}`, data)

    // Handle nested data structure
    return response.data.data
  }
  catch (error) {
    console.error(`Error updating expense ${id}:`, error)
    throw error
  }
}

export const deleteExpense = async (id: number): Promise<void> => {
  try {
    await api.delete(`/expenses/${id}`)
  }
  catch (error) {
    console.error(`Error deleting expense ${id}:`, error)
    throw error
  }
}

export const uploadExpenseDocument = async (id: number, document: File): Promise<Expense> => {
  const body = new FormData()
  body.append('document', document)
  return (await api.post<{ data: Expense }>(`/expenses/${id}/document`, body, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })).data.data
}

export const removeExpenseDocument = async (id: number): Promise<Expense> =>
  (await api.delete<{ data: Expense }>(`/expenses/${id}/document`)).data.data
