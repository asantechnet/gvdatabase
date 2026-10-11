import api from './axios'
import { currentLocale } from '@/utils/localization'

export type EducationalReportType = 'applicants' | 'employee-performance' | 'contracts' | 'services' | 'financial' | 'universities' | 'visa-outcomes'

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

    // The CSV is rendered server-side, so its dates are localized with the
    // locale the user is reading the page in.
    const response = await api.get(`/educational-reports/${type}/export`, { params: { ...clean, locale: currentLocale() }, responseType: 'blob' })

    return response.data as Blob
  },
}
