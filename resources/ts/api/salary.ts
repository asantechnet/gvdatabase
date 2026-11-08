import api from './axios'
import type { WorthyEmployee } from './bonuslist'
import type { Currency } from './currency'
import type { Employee } from './employee'
import type { ListPage } from './pagination'

export interface SalaryDeduction {
  id?: number
  description: string
  amount: number
  sortOrder?: number
  isAutomatic?: boolean
  code?: string | null
}

export interface AttendanceBreakdown {
  workedDays: number
  totalDays: number
  offDays: number
  absentDays: number
}

export interface GenerateSalariesResult {
  success: boolean
  created: number
  skipped: number
  errors: Record<number, string>
}

export interface SalaryAdvanceLine {
  id: number
  advanceNumber: string | null
  date: string | null
  /** The original advance amount. */
  amount: number
  /** What this payslip actually withheld from that advance. */
  deductedAmount?: number
  remarks: string | null
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
  amountDue: number
  amountOutstanding: number
  paymentStatus: 'open' | 'partially_paid' | 'paid'
  payments?: Array<{ id: number; paymentNumber: string | null; amount: number; date: string; remarks: string | null; expenseId: number | null }>
  bonus: number
  workedDays: number
  offDays: number
  advanceRecovery: number
  advances?: SalaryAdvanceLine[]
  absentDays: number
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
  currentBaseAmount: number | null
  currentBaseNetAmount: number | null
  currentBaseCurrency: Pick<Currency, 'id' | 'code' | 'symbol' | 'decimalPlaces'> | null
  expenseId: number | null
  recordedBy?: { id: number; name: string } | null
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
  initialPayment?: number
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
  remarks?: string
  date?: string
  deductions?: SalaryDeduction[]
}

/** One register row: an active employee with this month's payslip, if any. */
export interface PayrollRegisterRow {
  employee: Employee
  salary: Salary | null
}

/** Month-wide figures of the register, computed by the API. */
export interface PayrollRegisterTotals {
  net: number
  paid: number
  outstanding: number
  generated: number
  missing: number
}

export interface PayrollRegisterPage extends ListPage<PayrollRegisterRow> {
  totals: PayrollRegisterTotals
}

// Salary API endpoints
const SalaryApi = {
  /** One page of the payroll register (employee-driven, so it is not `/salaries`). */
  register: async (params: { month: string; search?: string; page?: number; perPage?: number }): Promise<PayrollRegisterPage> => {
    const response = await api.get('/salaries/register', {
      params: {
        month: params.month,
        search: params.search || undefined,
        page: params.page,
        per_page: params.perPage,
      },
    })
    const payload = response.data

    return {
      rows: Array.isArray(payload.data) ? payload.data : [],
      total: Number(payload.total ?? 0),
      page: Number(payload.current_page ?? 1),
      lastPage: Number(payload.last_page ?? 1),
      totals: payload.totals ?? { net: 0, paid: 0, outstanding: 0, generated: 0, missing: 0 },
    }
  },

  // Get all salaries
  getAll: async (params?: SalaryParams) => {
    try {
      const response = await api.get<{ data: Salary[] }>('/salaries', { params })

      return response.data
    }
    catch (error) {
      console.error('API getAll error:', error)
      throw error
    }
  },

  // Get single salary
  getById: async (id: number) => {
    try {
      const response = await api.get<{ data: Salary }>(`/salaries/${id}`)

      return response.data
    }
    catch (error) {
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

  // Attendance-derived working days used to prefill and verify the payslip.
  getAttendanceBreakdown: async (employeeId: number, salaryMonth: string) => {
    const response = await api.get<{ data: AttendanceBreakdown }>('/salaries/attendance-breakdown', {
      params: { employee_id: employeeId, salaryMonth },
    })

    return response.data
  },

  // Create the payslips that are still missing for a month.
  generate: async (salaryMonth: string, employeeIds?: number[], date?: string) => {
    const response = await api.post<GenerateSalariesResult>('/salaries/generate', {
      salaryMonth,
      employee_ids: employeeIds,
      date,
    })

    return response.data
  },

  getDeductions: async (salaryId: number) => {
    const response = await api.get<{ data: SalaryDeduction[] }>(`/salaries/${salaryId}/deductions`)

    return response.data
  },

  addPayment: async (salaryId: number, payment: { amount: number; date: string; remarks?: string }) => {
    const response = await api.post<{ data: Salary }>(`/salaries/${salaryId}/payments`, payment)
    return response.data
  },

  deletePayment: async (salaryId: number, paymentId: number) => {
    const response = await api.delete<{ data: Salary }>(`/salaries/${salaryId}/payments/${paymentId}`)
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

  /** Opt this payslip in or out of advance recovery (unpaid payslips only). */
  setAdvanceRecovery: async (salaryId: number, applied: boolean) => {
    const response = await api.patch<{ data: Salary }>(`/salaries/${salaryId}/advance-recovery`, { applied })

    return response.data
  },

  // Create salary
  create: async (data: CreateSalaryData) => {
    try {
      const response = await api.post<{ data: Salary }>('/salaries', data)

      return response.data
    }
    catch (error) {
      console.error('API create error:', error)
      throw error
    }
  },

  // Update salary
  update: async (id: number, data: UpdateSalaryData) => {
    try {
      const response = await api.put<{ data: Salary }>(`/salaries/${id}`, data)

      return response.data
    }
    catch (error) {
      console.error('API update error:', error)
      throw error
    }
  },

  // Delete salary
  delete: async (id: number) => {
    try {
      const response = await api.delete<{ success: boolean; message: string }>(`/salaries/${id}`)

      return response.data
    }
    catch (error) {
      console.error('API delete error:', error)
      throw error
    }
  },
}

export default SalaryApi
