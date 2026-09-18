import api from './axios'

export interface ApplicantStage {
  id: number
  name: string
  key: string
  sort_order?: number
  is_terminal?: boolean
  allowed_next_keys?: string[]
}

export interface Applicant {
  id: number
  customer_id: number
  responsible_employee_id?: number | null
  country_id?: number | null
  university_id?: number | null
  education_level_id?: number | null
  language_id?: number | null
  current_stage_id?: number | null
  application_date?: string | null
  status: string
  notes?: string | null
  customer?: { id?: number; name: string; contact?: string | null }
  responsible_employee?: { id: number; name: string } | null
  country?: { id: number; name: string } | null
  university?: { id: number; name: string } | null
  education_level?: { id: number; name: string } | null
  language?: { id: number; name: string } | null
  current_stage?: { id: number; name: string; key: string }
  tasks?: Array<{ id: number; title: string; progress: number; priority?: string | null; due_date?: string | null; attachments?: Array<{ id: number; original_name: string; mime_type?: string | null; size?: number | null }> }>
  contracts?: Array<{ id: number; contract_number: string; status: string }>
  stage_history?: Array<{ id: number; changed_at: string; note?: string; new_stage?: { name: string }; old_stage?: { name: string } }>
  pending_transactions?: Array<{ id: number; transaction_type: string; reason: string; amount: number | string; approval_status: string; rejection_reason?: string | null }>
  incomes?: Array<{ id: number; amount: number | string; date?: string | null; description?: string | null }>
  expenses?: Array<{ id: number; amount: number | string; date?: string | null; description?: string | null }>
  scholarships?: Array<{ id: number; fee: number | string; date?: string | null; status?: string | null; currency?: { id: number; code: string } | null }>
  test_offers?: Array<{ id: number; fee: number | string; date?: string | null; status?: string | null; currency?: { id: number; code: string } | null }>
  universities?: Array<{ id: number; university_id: number; status: string; intake?: string | null; notes?: string | null; university?: { id: number; name: string } | null }>
}

export type ApplicantBoard = 'operation' | 'final' | 'cancelled'

export interface ApplicantListParams {
  stage?: string
  status?: 'active' | 'closed'
  board?: ApplicantBoard | ''
  customer_id?: number | null
  responsible_employee_id?: number | null
  country_id?: number | null
  university_id?: number | null
  education_level_id?: number | null
  language_id?: number | null
  search?: string
  date_from?: string
  date_to?: string
  sort?: 'application_date' | 'created_at' | 'id'
  dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface PaginatedApplicants {
  data: Applicant[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PipelineColumn extends ApplicantStage {
  count: number
}

export interface PipelineResponse {
  stages: PipelineColumn[]
  totals: { active: number; closed: number; all: number }
}

const clean = (params: ApplicantListParams = {}) => {
  const out: Record<string, unknown> = {}

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '')
      out[key] = value
  }

  return out
}

export default {
  list: async (params: ApplicantListParams = {}) => {
    const response = await api.get('/applicants', { params: clean(params) })
    const payload = response.data.data

    // Laravel paginator shape: { data, current_page, ... }
    if (payload && Array.isArray(payload.data)) {
      return {
        data: payload.data as Applicant[],
        current_page: payload.current_page ?? 1,
        last_page: payload.last_page ?? 1,
        per_page: payload.per_page ?? 25,
        total: payload.total ?? payload.data.length,
      } as PaginatedApplicants
    }

    const rows = (Array.isArray(payload) ? payload : []) as Applicant[]

    return { data: rows, current_page: 1, last_page: 1, per_page: rows.length, total: rows.length } as PaginatedApplicants
  },
  pipeline: async (params: ApplicantListParams = {}) => (await api.get('/applicants-pipeline', { params: clean(params) })).data.data as PipelineResponse,
  board: async (board: ApplicantBoard | '', params: ApplicantListParams = {}) => {
    const response = await api.get('/applicants', { params: clean({ ...params, board: board || undefined }) })
    const payload = response.data.data

    if (payload && Array.isArray(payload.data)) {
      return {
        data: payload.data as Applicant[],
        current_page: payload.current_page ?? 1,
        last_page: payload.last_page ?? 1,
        per_page: payload.per_page ?? 25,
        total: payload.total ?? payload.data.length,
      } as PaginatedApplicants
    }

    const rows = (Array.isArray(payload) ? payload : []) as Applicant[]

    return { data: rows, current_page: 1, last_page: 1, per_page: rows.length, total: rows.length } as PaginatedApplicants
  },
  get: async (id: number) => (await api.get(`/applicants/${id}`)).data.data as Applicant,
  create: async (data: Partial<Applicant>) => (await api.post('/applicants', data)).data.data as Applicant,
  update: async (id: number, data: Partial<Applicant>) => (await api.put(`/applicants/${id}`, data)).data.data as Applicant,
  stages: async () => (await api.get('/applicant-stages')).data.data as ApplicantStage[],
  changeStage: async (id: number, stage_id: number, note?: string) => (await api.post(`/applicants/${id}/change-stage`, { stage_id, note })).data.data as Applicant,
}

export interface ApplicantUniversity {
  id: number
  applicant_id: number
  university_id: number
  status: string
  intake?: string | null
  notes?: string | null
  university?: { id: number; name: string } | null
}

export const ApplicantUniversityApi = {
  list: async (applicantId: number) => (await api.get(`/applicants/${applicantId}/universities`)).data.data as ApplicantUniversity[],
  create: async (applicantId: number, data: Partial<ApplicantUniversity>) => (await api.post(`/applicants/${applicantId}/universities`, data)).data.data as ApplicantUniversity,
  update: async (applicantId: number, id: number, data: Partial<ApplicantUniversity>) => (await api.put(`/applicants/${applicantId}/universities/${id}`, data)).data.data as ApplicantUniversity,
  remove: async (applicantId: number, id: number) => api.delete(`/applicants/${applicantId}/universities/${id}`),
}
