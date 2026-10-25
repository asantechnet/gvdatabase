import api from './axios'

export interface DashboardStage { id: number; service: string | null; key: string; name: string; is_terminal: boolean; count: number }
export interface StaffRow { id: number; name: string; active_applicants: number; sales_recorded: number; contracts_sold: number; bonuses_generated: number }
export interface AttentionHit { id: number; title: string; subtitle: string; route: { name: string; params?: Record<string, number | string> } }
export interface CountryRow { id: number; name: string; active: number; total: number }
export interface UniversityRow { id: number; name: string; applications: number; in_progress: number; admitted: number }
export interface CurrencySummary { currency: string | null; records: number; amount: number }

export interface WorkloadSummary {
  tasks: { open: number; due_today: number; overdue: number; unassigned: number; completed: number }
  contracts: { total: number; draft: number; active: number; completed: number; terminated: number; cancelled: number; new_in_period: number; service_lines: number }
}

export interface EducationalDashboard {
  operations: {
    active_cases: number
    unassigned_cases: number
    outstanding: CurrencySummary[]
    admissions: Record<'pending' | 'applied' | 'admitted' | 'rejected' | 'withdrawn', number>
    service_stages: Array<{ service: string; key: string | null; name: string | null; is_terminal: boolean; count: number }>
  }
  pipeline: { stages: DashboardStage[]; totals: { active: number; closed: number; all: number } }
  financial: { income: CurrencySummary[]; expenses: CurrencySummary[]; salaries: CurrencySummary[]; pending_by_status: Record<string, number> }
  sales: Record<'contracts' | 'scholarships' | 'test_offers' | 'academy' | 'private', { sold: number; by_currency: Array<{ currency: string | null; sold: number; revenue: number }> }>
  staff: StaffRow[]
  attention: { counts: Record<'stuck_applicants' | 'overdue_tasks' | 'missing_documents' | 'contracts_requiring_attention', number>; stuck_applicants: AttentionHit[]; overdue_tasks: AttentionHit[]; pending_approvals: number; missing_documents: AttentionHit[]; contracts_requiring_attention: AttentionHit[] }
  work: WorkloadSummary
  countries: CountryRow[]
  universities: UniversityRow[]
}

export interface EducationalDashboardParams {
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
