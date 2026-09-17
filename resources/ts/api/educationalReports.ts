import api from './axios'

export type EducationalReportType = 'applicants' | 'employee-performance' | 'sales' | 'visa-outcomes'

export interface EducationalReport {
  columns: string[]
  rows: Array<Record<string, string | number>>
  total: number
  current_page?: number
  last_page?: number
}

export type EducationalReportParams = Record<string, string | number | undefined>

export default {
  get: async (type: EducationalReportType, params: EducationalReportParams = {}) => {
    const clean: Record<string, string | number> = {}

    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== '')
        clean[key] = value
    }

    return (await api.get(`/educational-reports/${type}`, { params: clean })).data.data as EducationalReport
  },
  exportCsv: async (type: EducationalReportType, params: EducationalReportParams = {}) => {
    const clean: Record<string, string | number> = {}

    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== '')
        clean[key] = value
    }

    const response = await api.get(`/educational-reports/${type}/export`, { params: clean, responseType: 'blob' })

    return response.data as Blob
  },
}
