import api from './axios'

export type BuiltInService = 'scholarship' | 'test_offer' | 'academy' | 'private'

export interface ApplicantServiceDetails {
  scholarship_id?: number | null
  class_id?: number | null
  scholarship?: { id: number; name: string } | null
  academy_class?: { id: number; name: string } | null
  passport_id?: string | null
  grade_score?: number | string | null
  country_id?: number | null
  university_id?: number | null
  education_level_id?: number | null
  semester_id?: number | null
  contract_type_id?: number | null
  university_fee?: number | string | null
  test_id?: number | null
  program_id?: number | null
  time_id?: number | null
  duration_id?: number | null
  country?: { id: number; name: string } | null
  university?: { id: number; name: string } | null
  education_level?: { id: number; name: string } | null
  semester?: { id: number; name: string } | null
  test?: { id: number; name: string } | null
  program?: { id: number; name: string } | null
  time?: { id: number; name: string } | null
  duration?: { id: number; name: string } | null
}

/**
 * The terminal stage that finishes a case. Reaching it closes the case and
 * releases the commission earned on its contracted services.
 */
export const COMPLETED_APPLICANT_STAGE = 'completed'

export interface ApplicantStage {
  id: number
  name: string
  key: string
  // Stages are scoped to one built-in service (scholarship/test_offer/academy/private).
  service?: BuiltInService | null
  country_id?: number | null
  university_id?: number | null
  sort_order?: number
  is_terminal?: boolean
  allowed_next_keys?: string[]
  is_active?: boolean
}

export interface Applicant {
  id: number
  created_at?: string
  service: BuiltInService
  details?: ApplicantServiceDetails | null
  favorite_subjects?: Array<{ id: number; name: string }>
  skills?: Array<{ id: number; name: string }>
  teachers?: Array<{ id: number; name: string }>
  favorite_subject_ids?: number[]
  skill_ids?: number[]
  teacher_ids?: number[]
  user_id?: number | null
  portal_access_status?: 'active' | 'inactive' | null
  name: string
  father_name?: string | null
  father_phone?: string | null
  backup_phone?: string | null
  contact?: string | null
  email?: string | null
  address?: string | null
  tax_number?: string | null
  attachments_count?: number
  admission_employee_id?: number | null
  admission_employee?: { id: number; name: string } | null
  responsible_employee_id?: number | null
  current_stage_id?: number | null
  status: string
  payment_status?: 'paid' | 'unpaid' | 'partially_paid' | null
  notes?: string | null
  responsible_employee?: { id: number; name: string } | null
  // A case's stage decides the workflow: terminal stages close the case, and
  // `completed` is the terminal stage that also releases commission.
  current_stage?: { id: number; name: string; key: string; is_terminal?: boolean; allowed_next_keys?: string[] }
  tasks?: Array<{ id: number; title: string; progress: number; priority?: string | null; due_date?: string | null; attachments?: Array<{ id: number; original_name: string; mime_type?: string | null; size?: number | null }> }>
  contracts?: Array<{ id: number; contract_number: string; status: string }>
  stage_history?: Array<{ id: number; changed_at: string; note?: string; new_stage?: { name: string }; old_stage?: { name: string } }>
  pending_transactions?: Array<{ id: number; transaction_type: string; reason: string; amount: number | string; approval_status: string; rejection_reason?: string | null }>
  incomes?: Array<{ id: number; amount: number | string; date?: string | null; description?: string | null }>
  expenses?: Array<{ id: number; amount: number | string; date?: string | null; description?: string | null }>
  services?: Array<{ application_fee?: number | string | null; visa_fee?: number | string | null; booking_fee?: number | string | null; service_fee?: number | string | null; remote_fee?: number | string | null; currency_rate?: number | string | null; current_stage_id?: number | null; current_stage?: { id: number; key: string; name: string; is_terminal?: boolean; allowed_next_keys?: string[] } | null; id: number; service?: BuiltInService | null; type: string; category?: string; employee_id?: number | null; contract_id?: number | null; contract_number?: string | null; currency_id: number; country_id?: number | null; education_level_id?: number | null; language_id?: number | null; fee: number | string; date?: string | null; status?: 'active' | 'paused' | 'completed' | 'cancelled'; currency?: { id: number; code: string } | null; bonus_source?: { bonus_amount: number | string; rule_source?: string | null } | null }>
  universities?: Array<{ id: number; university_id: number; semester_id?: number | null; status: string; intake?: string | null; notes?: string | null; university?: { id: number; name: string } | null; semester?: { id: number; name: string; code?: string } | null }>
}

export type ApplicantBoard = 'operation' | 'final' | 'cancelled'

export interface ApplicantListParams {
  focus?: 'outstanding' | 'admitted' | 'awaiting_decision' | 'unassigned' | ''
  service?: BuiltInService | ''
  stage?: string
  status?: 'active' | 'closed'
  board?: ApplicantBoard | ''
  responsible_employee_id?: number | null
  country_id?: number | null
  university_id?: number | null
  education_level_id?: number | null
  semester_id?: number | null
  search?: string
  date_from?: string
  date_to?: string
  sort?: 'created_at' | 'id'
  dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export type ApplicantPayload = Partial<Applicant> & ApplicantServiceDetails

export function applicantServicePayload(form: ApplicantPayload): ApplicantPayload {
  const fields = form.service === 'academy'
    ? ['program_id', 'time_id', 'duration_id', 'teacher_ids', 'class_id']
    : form.service === 'test_offer'
      ? ['passport_id', 'test_id', 'skill_ids']
      : ['passport_id', 'grade_score', 'country_id', 'university_id', 'education_level_id', 'semester_id', 'contract_type_id', 'university_fee', 'favorite_subject_ids', ...(form.service === 'scholarship' ? ['scholarship_id'] : [])]
  const payload: Record<string, unknown> = { service: form.service ?? 'scholarship' }
  for (const field of fields) {
    const value = form[field as keyof ApplicantPayload]
    payload[field] = field.endsWith('_ids') ? value ?? [] : value === '' || value === undefined ? null : value
  }
  return payload as ApplicantPayload
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
  create: async (data: ApplicantPayload) => (await api.post('/applicants', data)).data.data as Applicant,
  update: async (id: number, data: ApplicantPayload) => (await api.put(`/applicants/${id}`, data)).data.data as Applicant,
  delete: async (id: number) => api.delete(`/applicants/${id}`),
  stages: async (params?: Pick<ApplicantListParams, 'country_id' | 'university_id' | 'service'>) => (await api.get('/applicant-stages', { params: clean(params) })).data.data as ApplicantStage[],
  // Laravel's boolean validation accepts 1/0 query values reliably; URL query
  // serialization of `true` can otherwise arrive as the invalid string "true".
  stageDefinitions: async (service?: BuiltInService) => (await api.get('/applicant-stages', { params: { all: 1, ...(service ? { service } : {}) } })).data.data as ApplicantStage[],
  createStage: async (data: Partial<ApplicantStage>) => (await api.post('/applicant-stages', data)).data.data as ApplicantStage,
  updateStageDefinition: async (id: number, data: Partial<ApplicantStage>) => (await api.put(`/applicant-stages/${id}`, data)).data.data as ApplicantStage,
  removeStageDefinition: async (id: number) => api.delete(`/applicant-stages/${id}`),
  changeStage: async (id: number, stage_id: number, note?: string) => (await api.post(`/applicants/${id}/change-stage`, { stage_id, note })).data.data as Applicant,
  // Per-service workflow: move one contracted line through its own stage list.
  changeLineStage: async (lineId: number, stage_id: number, note?: string) => (await api.post(`/applicant-contracts/${lineId}/change-stage`, { stage_id, note })).data.data,
}

export interface ApplicantAttachment { id: number; label: string; category?: string | null; name: string; mime_type?: string | null; size: number; uploaded_at?: string; download_url: string }
export const ApplicantAttachmentApi = {
  list: async (id: number) => (await api.get(`/applicants/${id}/attachments`)).data.data as ApplicantAttachment[],
  upload: async (id: number, file: File, label: string, category?: string) => { const form = new FormData(); form.append('file', file); form.append('label', label); if (category) form.append('category', category); return (await api.post(`/applicants/${id}/attachments`, form, { headers: { 'Content-Type': 'multipart/form-data' } })).data.data as ApplicantAttachment },
  update: async (applicantId: number, id: number, label: string, category?: string) => (await api.put(`/applicants/${applicantId}/attachments/${id}`, { label, category })).data.data as ApplicantAttachment,
  remove: async (applicantId: number, id: number) => api.delete(`/applicants/${applicantId}/attachments/${id}`),
}

export interface ApplicantUniversity {
  semester_id?: number | null
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
