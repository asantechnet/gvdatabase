import api from './axios'
import type { Currency } from './currency'
import { cleanListParams, readPage, type ListPage, type ListPageParams } from './pagination'

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
  salaryId?: number | null
  recoveredAt?: string | null
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

  remove: async (id: number): Promise<void> => {
    await api.delete(`/employee-advances/${id}`)
  },
}

export default EmployeeAdvanceApi
