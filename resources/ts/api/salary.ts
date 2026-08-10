import api from "./axios";
import type { WorthyEmployee } from './bonuslist'
import type { Currency } from './currency'

// Types matching the frontend requirements
export interface Salary {
  id: number
  payslip_number: string
  employee_id: number
  employeeName: string
  salaryMonth: string
  currencyId: number
  currency: Pick<Currency, 'id' | 'code' | 'symbol' | 'decimalPlaces'> | null
  amountPaid: number
  bonus: number
  workedDays: number
  totalDays: number
  totalAmount: number
  remarks: string
  date: string
  bonuses: WorthyEmployee[]
}

export interface SalaryParams {
  page?: number
  perPage?: number
  salaryMonthFilter?: string
  selectedSalaryAmountRange?: 'low' | 'mid' | 'high'
  searchQuery?: string
}

export interface CreateSalaryData {
  employee_id: number
  salaryMonth: string
  currency_id: number
  amountPaid: number
  workedDays: number
  totalDays?: number
  remarks?: string
  date: string
}

export interface UpdateSalaryData {
  employee_id?: number
  salaryMonth?: string
  currency_id?: number
  amountPaid?: number
  workedDays?: number
  totalDays?: number
  remarks?: string
  date?: string
}

// Salary API endpoints
const SalaryApi = {
  // Get all salaries
  getAll: async (params?: SalaryParams) => {
    try {
      const response = await api.get<{ data: Salary[] }>("/salaries", { params })
      return response.data
    } catch (error) {
      console.error('API getAll error:', error)
      throw error
    }
  },

  // Get single salary
  getById: async (id: number) => {
    try {
      const response = await api.get<{ data: Salary }>(`/salaries/${id}`)
      return response.data
    } catch (error) {
      console.error('API getById error:', error)
      throw error
    }
  },

  // Get only the granted bonus records applicable to an employee's salary month.
  getGrantedBonuses: async (employeeId: number, salaryMonth: string) => {
    const response = await api.get<{ data: WorthyEmployee[] }>('/salaries/granted-bonuses', {
      params: { employee_id: employeeId, salaryMonth },
    })

    return response.data
  },

  // Create salary
  create: async (data: CreateSalaryData) => {
    try {
      const response = await api.post<{ data: Salary }>("/salaries", data)
      return response.data
    } catch (error) {
      console.error('API create error:', error)
      throw error
    }
  },

  // Update salary
  update: async (id: number, data: UpdateSalaryData) => {
    try {
      const response = await api.put<{ data: Salary }>(`/salaries/${id}`, data)
      return response.data
    } catch (error) {
      console.error('API update error:', error)
      throw error
    }
  },

  // Delete salary
  delete: async (id: number) => {
    try {
      const response = await api.delete<{ success: boolean; message: string }>(`/salaries/${id}`)
      return response.data
    } catch (error) {
      console.error('API delete error:', error)
      throw error
    }
  },
}

export default SalaryApi
