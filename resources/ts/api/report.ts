import api from './axios'

export type ReportType = 'financial' | 'payroll' | 'attendance' | 'operations'

export interface ReportFilters {
  start_date: string
  end_date: string
  employee_id?: number | null
  office_id?: number | null
  applicant_id?: number | null
  currency_id?: number | null
  board_id?: number | null
  status?: string | null
  contract_type?: string | null
  attendance_state?: 'present' | 'absent' | 'paid-leave' | 'unpaid-leave' | 'incomplete' | 'complete' | null
}

export interface ReportSection {
  key: string
  title: string
  columns: string[]
  rows: Array<Record<string, unknown>>
  totals: Record<string, unknown>
}

export interface GeneratedReport {
  type: ReportType
  category: string
  title: string
  filters: ReportFilters
  sections: ReportSection[]
  meta: {
    generated_at: string
    timezone: string
    currency_basis: string
    company: { name: string | null; short_name: string | null; logo: string | null; address: string | null; email: string | null; phone: string | null }
  }
}

export interface ReportCatalog {
  types: Array<{ value: ReportType; category: string; title: string }>
  employees: Array<{ id: number; name: string; office_id: number | null }>
  offices: Array<{ id: number; name: string; code: string }>
  applicants: Array<{ id: number; name: string }>
  currencies: Array<{ id: number; code: string; symbol: string; decimal_places: number; is_base: boolean }>
  boards: Array<{ id: number; title: string }>
  contract_types: string[]
  timezone: string
}

const ReportApi = {
  async catalog(): Promise<ReportCatalog> {
    const response = await api.get<{ data: ReportCatalog }>('/reports/catalog')

    return response.data.data
  },
  async generate(type: ReportType, params: ReportFilters): Promise<GeneratedReport> {
    const response = await api.get<{ data: GeneratedReport }>(`/reports/generate/${type}`, { params })

    return response.data.data
  },
  async export(type: ReportType, params: ReportFilters, format: 'csv' | 'print' = 'csv'): Promise<Blob> {
    const response = await api.get(`/reports/${type}/export`, { params: { ...params, format }, responseType: 'blob' })

    return response.data
  },
  generateExpenseReport: (params: Partial<ReportFilters>) => api.get('/reports/expenses', { params }),
  generateExpenceReport: (params: Partial<ReportFilters>) => api.get('/reports/expenses', { params }),
  generateSalaryReport: (params: Partial<ReportFilters>) => api.get('/reports/salaries', { params }),
  generateIncomeReport: (params: Partial<ReportFilters>) => api.get('/reports/incomes', { params }),
  generateVisitorReport: (params: Partial<ReportFilters>) => api.get('/reports/visitors', { params }),
}

export default ReportApi
