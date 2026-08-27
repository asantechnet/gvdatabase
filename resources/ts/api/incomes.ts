import api from './axios'
import type { Currency } from './currency'

export interface Income {
  id: number
  receipt_number: string
  customer_id: number | null
  invoice_id: number | null
  currency_id: number
  invoice_number?: string | null
  type: string
  description?: string
  amount: number
  quantity: number
  currency: Pick<Currency, 'id' | 'code' | 'symbol' | 'rate' | 'decimalPlaces'> | null
  cnv_curr_shrt: string
  base_curr_shrt: string
  rate: number
  base_amount: number
  date: string
  created_at?: string
  updated_at?: string
}

export interface IncomeCreateData {
  customer_id?: number | null
  invoice_id?: number | null
  currency_id: number
  type: string
  description?: string
  amount: number
  quantity: number
  rate: number
  date: string
}

export interface IncomeUpdateData {
  customer_id?: number | null
  invoice_id?: number | null
  currency_id?: number
  type?: string
  description?: string | null
  amount?: number
  quantity?: number
  rate?: number
  date?: string
}

export interface IncomeParams {
  search?: string
  type?: string
  currency_id?: number
}

export const getIncomes = async (params?: IncomeParams): Promise<Income[]> => {
  try {
    const response = await api.get('/incomes', { params })
    // Handle nested data structure where actual data is in response.data.data
    return Array.isArray(response.data.data) ? response.data.data : []
  } catch (error) {
    console.error('Error fetching incomes:', error)
    throw error
  }
}

export const getIncome = async (id: number): Promise<Income> => {
  try {
    const response = await api.get(`/incomes/${id}`)
    // Handle nested data structure
    return response.data.data
  } catch (error) {
    console.error(`Error fetching income ${id}:`, error)
    throw error
  }
}

export const createIncome = async (data: IncomeCreateData): Promise<Income> => {
  try {
    const response = await api.post('/incomes', data)
    // Handle nested data structure
    return response.data.data
  } catch (error) {
    console.error('Error creating income:', error)
    throw error
  }
}

export const updateIncome = async (id: number, data: IncomeUpdateData): Promise<Income> => {
  try {
    const response = await api.put(`/incomes/${id}`, data)
    // Handle nested data structure
    return response.data.data
  } catch (error) {
    console.error(`Error updating income ${id}:`, error)
    throw error
  }
}

export const deleteIncome = async (id: number): Promise<void> => {
  try {
    await api.delete(`/incomes/${id}`)
  } catch (error) {
    console.error(`Error deleting income ${id}:`, error)
    throw error
  }
}
