import api from './axios'

export interface ApplicantStage {
  id: number
  name: string
  key: string
  country_id?: number | null
  university_id?: number | null
  sort_order?: number
  is_terminal?: boolean
  allowed_next_keys?: string[]
}

export interface Applicant {
  id: number
  user_id?: number | null
  name: string
  father_name?: string | null
  father_phone?: string | null
  contact?: string | null
  email?: string | null
  address?: string | null
  tax_number?: string | null
  attachments_count?: number
  marketer_id?: number | null
  marketer?: string | null
  responsible_employee_id?: number | null
  country_id?: number | null
  university_id?: number | null
  education_level_id?: number | null
  /**
   * Educational grade metric, a percentage of the maximum mark (0-100).
   * The applicant endpoints serialize the model directly, so the DB decimal
   * cast arrives as a numeric string (like `services[].fee`).
   */
  percentage?: number | string | null
  language_id?: number | null
  semester_id?: number | null
  current_stage_id?: number | null
  application_date?: string | null
  status: string
  notes?: string | null
  responsible_employee?: { id: number; name: string } | null
  country?: { id: number; name: string } | null
  university?: { id: number; name: string } | null
  education_level?: { id: number; name: string } | null
  language?: { id: number; name: string } | null
  semester?: { id: number; name: string; code?: string } | null
  current_stage?: { id: number; name: string; key: string }
  tasks?: Array<{ id: number; title: string; progress: number; priority?: string | null; due_date?: string | null; attachments?: Array<{ id: number; original_name: string; mime_type?: string | null; size?: number | null }> }>
  contracts?: Array<{ id: number; contract_number: string; status: string }>
  stage_history?: Array<{ id: number; changed_at: string; note?: string; new_stage?: { name: string }; old_stage?: { name: string } }>
  pending_transactions?: Array<{ id: number; transaction_type: string; reason: string; amount: number | string; approval_status: string; rejection_reason?: string | null }>
  incomes?: Array<{ id: number; amount: number | string; date?: string | null; description?: string | null }>
  expenses?: Array<{ id: number; amount: number | string; date?: string | null; description?: string | null }>
  services?: Array<{ id: number; service_id: number; type: string; category?: string; employee_id?: number | null; contract_id?: number | null; contract_number?: string | null; currency_id: number; package_type_id?: number | null; country_id?: number | null; education_level_id?: number | null; language_id?: number | null; fee: number | string; date?: string | null; status?: 'active' | 'paused' | 'completed' | 'cancelled'; currency?: { id: number; code: string } | null; bonus_source?: { bonus_amount: number | string; rule_source?: string | null } | null }>
  universities?: Array<{ id: number; university_id: number; semester_id?: number | null; status: string; intake?: string | null; notes?: string | null; university?: { id: number; name: string } | null; semester?: { id: number; name: string; code?: string } | null }>
}

export type ApplicantBoard = 'operation' | 'final' | 'cancelled'

export interface ApplicantListParams {
  stage?: string
  status?: 'active' | 'closed'
  board?: ApplicantBoard | ''
  responsible_employee_id?: number | null
  country_id?: number | null
  university_id?: number | null
  education_level_id?: number | null
  language_id?: number | null
  semester_id?: number | null
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
  delete: async (id: number) => api.delete(`/applicants/${id}`),
  stages: async (params?: Pick<ApplicantListParams, 'country_id' | 'university_id'>) => (await api.get('/applicant-stages', { params: clean(params) })).data.data as ApplicantStage[],
  // Laravel's boolean validation accepts 1/0 query values reliably; URL query
  // serialization of `true` can otherwise arrive as the invalid string "true".
  stageDefinitions: async () => (await api.get('/applicant-stages', { params: { all: 1 } })).data.data as ApplicantStage[],
  createStage: async (data: Partial<ApplicantStage>) => (await api.post('/applicant-stages', data)).data.data as ApplicantStage,
  updateStageDefinition: async (id: number, data: Partial<ApplicantStage>) => (await api.put(`/applicant-stages/${id}`, data)).data.data as ApplicantStage,
  removeStageDefinition: async (id: number) => api.delete(`/applicant-stages/${id}`),
  changeStage: async (id: number, stage_id: number, note?: string) => (await api.post(`/applicants/${id}/change-stage`, { stage_id, note })).data.data as Applicant,
}

export interface ApplicantAttachment { id: number; label: string; category?: string | null; name: string; mime_type?: string | null; size: number; uploaded_at?: string; download_url: string }
export const ApplicantAttachmentApi = {
  list: async (id: number) => (await api.get(`/applicants/${id}/attachments`)).data.data as ApplicantAttachment[],
  upload: async (id: number, file: File, label: string, category?: string) => { const form = new FormData(); form.append('file', file); form.append('label', label); if (category) form.append('category', category); return (await api.post(`/applicants/${id}/attachments`, form, { headers: { 'Content-Type': 'multipart/form-data' } })).data.data as ApplicantAttachment },
  update: async (applicantId: number, id: number, label: string, category?: string) => (await api.put(`/applicants/${applicantId}/attachments/${id}`, { label, category })).data.data as ApplicantAttachment,
  remove: async (applicantId: number, id: number) => api.delete(`/applicants/${applicantId}/attachments/${id}`),
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
