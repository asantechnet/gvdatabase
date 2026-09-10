import api from './axios'

// Types matching the frontend requirements
export type BonusType = 'Performance' | 'Customer Appreciation' | 'Task Completion' | 'Work Anniversary' | 'Manager Recognition'

export interface SalaryConversionSnapshot {
  sourceAmount: number
  sourceCurrencyCode: string
  sourceRateToBase: number
  baseAmount: number
  salaryCurrencyCode: string
  salaryRateToBase: number
  convertedAmount: number
  formula: string
  snapshotSource: string
}

export interface WorthyEmployee {
  id: string
  employeeId: string
  employeeName: string
  employeeEmail: string
  department: string | null
  achievementDetails: string
  suggestedAmount: number
  selectedAmount: number
  selectedType: BonusType
  isGranted: boolean
  grantedAt: string | null
  grantedBy: number | null
  grantedAmount: number | null
  currencyId: number | null
  baseCurrencyId: number | null
  currencyCode: string | null
  currencyName: string | null
  currencySymbol: string | null
  currencyDecimalPlaces: number | null
  currency: { id: number | null; code: string | null; name: string | null; symbol: string | null; decimalPlaces: number | null; rate: number | null }
  baseCurrencyCode: string | null
  rate: number | null
  baseAmount: number | null
  currentBaseAmount: number | null
  currentBaseCurrency: { id: number; code: string; symbol: string; decimalPlaces: number } | null
  isAutomatic: boolean
  calculationKey: string | null
  calculationScore: number | null
  calculationInputs: { tasks?: Array<{ task_id: string | number; task_title: string; completed_at?: string | null }>; formula?: string } | null
  ruleSnapshot: { id: number; version: number; rules: Record<string, unknown> } | null
  calculatedAt: string | null
  salaryConversion?: SalaryConversionSnapshot
  bonusMonth: string // Format: YYYY-MM
}

export interface CreateNominationData {
  employeeId: number
  achievementDetails: string
  amount: number
  selectedType: BonusType
  bonusMonth?: string // Format: YYYY-MM
}

export interface UpdateEmployeeData {
  amount?: number
  selectedAmount?: number
  selectedType?: BonusType
  isGranted?: boolean
}

// Bonus Eligibility API endpoints
const BonusListApi = {
  // Get all worthy employees
  getAll: async () => {
    try {
      return await api.get<{ data: WorthyEmployee[] }>('/worthy-employees')
    }
    catch (error) {
      console.error('API getAll error:', error)
      throw error
    }
  },

  // Create a new nomination
  create: async (data: CreateNominationData) => {
    try {
      return await api.post<{ data: WorthyEmployee }>('/worthy-employees', data)
    }
    catch (error) {
      console.error('API create error:', error)
      throw error
    }
  },

  // Update an employee's record
  update: async (id: string, data: UpdateEmployeeData) => {
    try {
      return await api.patch<{ data: WorthyEmployee }>(`/worthy-employees/${id}`, data)
    }
    catch (error) {
      console.error('API update error:', error)
      throw error
    }
  },

  // Delete (dismiss) an employee
  delete: async (id: string) => {
    try {
      return await api.delete<{ message: string }>(`/worthy-employees/${id}`)
    }
    catch (error) {
      console.error('API delete error:', error)
      throw error
    }
  },
}

export default BonusListApi
