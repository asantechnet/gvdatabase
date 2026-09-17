import api from './axios'

export interface DashboardStage { key: string; name: string; is_terminal: boolean; count: number }
export interface StaffRow { id: number; name: string; active_applicants: number; sales_recorded: number; sales_source_total: number }
export interface AttentionHit { id: number; title: string; subtitle: string; route: { name: string; params?: Record<string, number | string> } }
export interface CountryRow { id: number; name: string; active: number; total: number }

export interface EducationalDashboard {
  pipeline: { stages: DashboardStage[]; totals: { active: number; closed: number; all: number } }
  sales: Record<'scholarships' | 'test_offers', { sold: number; by_currency: Array<{ currency: string | null; sold: number; revenue: number }> }>
  staff: StaffRow[]
  attention: { stuck_applicants: AttentionHit[]; overdue_tasks: AttentionHit[]; pending_approvals: number }
  countries: CountryRow[]
}

export interface EducationalDashboardParams {
  responsible_employee_id?: number | null
  country_id?: number | null
  university_id?: number | null
  date_from?: string
  date_to?: string
  stale_days?: number
}

export default {
  get: async (params: EducationalDashboardParams = {}) => {
    const clean: Record<string, unknown> = {}

    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '')
        clean[key] = value
    }

    return (await api.get('/educational-dashboard', { params: clean })).data.data as EducationalDashboard
  },
}
