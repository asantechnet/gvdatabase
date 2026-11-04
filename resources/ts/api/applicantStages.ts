import api from './axios'
import type { ApplicantStage as SharedApplicantStage, BuiltInService } from './applicants'

/**
 * A stage row extends the shared applicant-stage contract with the test scope
 * used by test-offer workflows. `null` is the shared baseline, not a missing
 * configuration.
 */
export interface ApplicantStage extends SharedApplicantStage {
  test_id?: number | null
  test?: { id: number; name: string } | null
}

export interface ApplicantStageQuery {
  service?: BuiltInService | ''
  country_id?: number | null
  university_id?: number | null
  test_id?: number | null
  line_id?: number | null
  applicant_id?: number | null
  active?: boolean
  all?: boolean
}

const clean = (params: ApplicantStageQuery = {}) => {
  const out: Record<string, unknown> = {}

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '')
      continue
    // Laravel's boolean validation accepts 1/0 reliably; URL query
    // serialization of `true` can otherwise arrive as the invalid string.
    out[key] = typeof value === 'boolean' ? (value ? 1 : 0) : value
  }

  return out
}

export default {
  /** Picker read. Pass `line_id` or `applicant_id` for a case's own stages. */
  list: async (params: ApplicantStageQuery = {}) => (await api.get('/applicant-stages', { params: clean(params) })).data.data as ApplicantStage[],
  /** Editor read: every row of the service, including inactive and test-scoped. */
  catalogue: async (service?: BuiltInService) => (await api.get('/applicant-stages', { params: { all: 1, ...(service ? { service } : {}) } })).data.data as ApplicantStage[],
  create: async (data: Partial<ApplicantStage>) => (await api.post('/applicant-stages', data)).data.data as ApplicantStage,
  update: async (id: number, data: Partial<ApplicantStage>) => (await api.put(`/applicant-stages/${id}`, data)).data.data as ApplicantStage,
  remove: async (id: number) => api.delete(`/applicant-stages/${id}`),
}
