import api from './axios'
import type { Currency } from './currency'
import { cleanListParams, readPage, type ListPage, type ListPageParams } from './pagination'

/** How much of the advance has been paid back. Derived from its settlements. */
export type EmployeeAdvanceStatus = 'outstanding' | 'partial' | 'settled'

/** One reduction of the debt: a payroll withholding or cash returned. */
export interface EmployeeAdvanceSettlement {
  id: number
  kind: 'payroll' | 'cash'
  amount: number
  date: string | null
  salaryId: number | null
  remarks: string | null
}

/** A salary advance paid to an employee ahead of the payroll period. */
export interface EmployeeAdvance {
  id: number
  advanceNumber: string | null
  employee_id: number
  employeeName: string
  currencyId: number
  currency: (Pick<Currency, 'id' | 'code' | 'symbol' | 'decimalPlaces'> & { rate: number }) | null
  amount: number
  rate: number
  baseAmount: number
  date: string
  remarks: string
  expenseId: number | null
  status: EmployeeAdvanceStatus
  /** Total paid back so far, by payroll or in cash. */
  settledAmount: number
  /** What is still owed. */
  remainingAmount: number
  salaryId?: number | null
  recoveredAt?: string | null
  settlements?: EmployeeAdvanceSettlement[]
  recordedBy?: { id: number; name: string } | null
}

export interface EmployeeAdvanceParams {
  employee_id?: number | null
  search?: string
  date_from?: string
  date_to?: string
}

export interface CreateEmployeeAdvanceData {
  employee_id: number
  amount: number
  date: string
  remarks?: string
}

export interface UpdateEmployeeAdvanceData {
  amount: number
  date: string
  remarks?: string
}

export interface SettleEmployeeAdvanceData {
  amount: number
  date: string
  remarks?: string
}

const EmployeeAdvanceApi = {
  /** Every matching advance, for pickers and reports. */
  list: async (params?: EmployeeAdvanceParams): Promise<EmployeeAdvance[]> => {
    const response = await api.get('/employee-advances', { params: cleanListParams({ ...params }) })

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  /** One page of the advances ledger. */
  listPage: async (params?: EmployeeAdvanceParams & ListPageParams): Promise<ListPage<EmployeeAdvance>> => {
    const response = await api.get('/employee-advances', {
      params: cleanListParams({ ...params, page: params?.page, per_page: params?.perPage }),
    })

    return readPage<EmployeeAdvance>(response.data)
  },

  create: async (data: CreateEmployeeAdvanceData): Promise<EmployeeAdvance> => {
    const response = await api.post('/employee-advances', data)

    return response.data.data
  },

  update: async (id: number, data: UpdateEmployeeAdvanceData): Promise<EmployeeAdvance> => {
    const response = await api.patch(`/employee-advances/${id}`, data)

    return response.data.data
  },

  /** Record money the employee handed back against the advance. */
  settle: async (id: number, data: SettleEmployeeAdvanceData): Promise<EmployeeAdvance> => {
    const response = await api.post(`/employee-advances/${id}/settlements`, data)

    return response.data.data
  },

  remove: async (id: number): Promise<void> => {
    await api.delete(`/employee-advances/${id}`)
  },
}

export default EmployeeAdvanceApi
