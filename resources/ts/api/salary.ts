import api from "./axios";
import type { WorthyEmployee } from './bonuslist'
import type { Currency } from './currency'

export interface SalaryDeduction {
  id?: number
  description: string
  amount: number
  sortOrder?: number
}

// Types matching the frontend requirements
export interface Salary {
  id: number
  payslip_number: string
  employee_id: number
  employeeName: string
  employeeAvatar: string | null
  salaryMonth: string
  currencyId: number
  currency: Pick<Currency, 'id' | 'code' | 'name' | 'symbol' | 'decimalPlaces' | 'rate'> | null
  currencySnapshotSource: string
  currencySnapshotNote: string | null
  amountPaid: number
  bonus: number
  workedDays: number
  paidOffDays: number
  absentDays: number
  unpaidOffDays: number
  totalDays: number
  totalAmount: number
  amountPaidBase: number
  bonusBase: number
  totalAmountBase: number
  remarks: string
  date: string
  bonuses: WorthyEmployee[]
  deductions: SalaryDeduction[]
  totalDeductions: number
  netAmount: number
  expenseId: number | null
}

export interface SalaryParams {
  page?: number
  perPage?: number
  salaryMonthFilter?: string | null
  selectedSalaryAmountRange?: 'low' | 'mid' | 'high' | null
  searchQuery?: string
}

export interface CreateSalaryData {
  employee_id: number
  salaryMonth: string
  currency_id: number
  rate: number
  amountPaid: number
  workedDays: number
  totalDays?: number
  remarks?: string
  date: string
  deductions: SalaryDeduction[]
}

export interface UpdateSalaryData {
  employee_id?: number
  salaryMonth?: string
  currency_id?: number
  rate?: number
  amountPaid?: number
  workedDays?: number
  totalDays?: number
  remarks?: string
  date?: string
  deductions?: SalaryDeduction[]
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

  getDeductions: async (salaryId: number) => {
    const response = await api.get<{ data: SalaryDeduction[] }>(`/salaries/${salaryId}/deductions`)
    return response.data
  },

  createDeduction: async (salaryId: number, deduction: Omit<SalaryDeduction, 'id'>) => {
    const response = await api.post<{ data: SalaryDeduction }>(`/salaries/${salaryId}/deductions`, deduction)
    return response.data
  },

  updateDeduction: async (salaryId: number, deductionId: number, deduction: Omit<SalaryDeduction, 'id'>) => {
    const response = await api.patch<{ data: SalaryDeduction }>(`/salaries/${salaryId}/deductions/${deductionId}`, deduction)
    return response.data
  },

  deleteDeduction: async (salaryId: number, deductionId: number) => {
    return api.delete(`/salaries/${salaryId}/deductions/${deductionId}`)
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
