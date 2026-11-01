import api from './axios'
import type { Applicant, ApplicantPayload, BuiltInService, ConsultantReview } from './applicants'
import { cleanListParams, readPage, type ListPage, type ListPageParams } from './pagination'

export interface VisitorPerson {
  id: number
  name: string
}

export interface Visitor {
  id: number
  name: string
  contact: string
  email: string | null
  /** Canonical built-in key, or an untouched legacy free-text value. */
  service: string
  service_key: BuiltInService | null
  service_label: string
  service_label_key: string | null
  education_level_id: number | null
  education_level: VisitorPerson | null
  age: number | null
  grade_score: number | string | null
  province_id: number | null
  province: VisitorPerson | null
  english_level_id: number | null
  english_level: VisitorPerson | null
  acquisition_route_id: number | null
  acquisition_route: VisitorPerson | null
  consultant_employee_id: number | null
  consultant: VisitorPerson | null
  /** Only holders of `review_visitors` may edit this counselor's review. */
  consultant_review: ConsultantReview | null
  notes: string | null
  /** Marked as worthy of follow-up: shown in the Follow-up list, not in Visitors. */
  follow_up: boolean
  date: string
  created_at?: string
  updated_at?: string
}

export interface VisitorParams {
  page?: number
  perPage?: number
  search?: string
  consultant_employee_id?: number | null
  education_level_id?: number | null
  province_id?: number | null
  english_level_id?: number | null
  acquisition_route_id?: number | null
  date_from?: string
  date_to?: string
  /** 1 = only follow-up worthy visitors, 0 = only the rest. Omit for every visitor. */
  follow_up?: 0 | 1
}

export interface CreateVisitorData {
  name: string
  contact: string
  email?: string | null
  service: BuiltInService
  education_level_id?: number | null
  age?: number | null
  grade_score?: number | string | null
  province_id?: number | null
  english_level_id?: number | null
  acquisition_route_id?: number | null
  consultant_employee_id?: number | null
  consultant_review?: ConsultantReview | null
  date: string
  notes?: string | null
}

export type UpdateVisitorData = Partial<CreateVisitorData>

/** The editable visitor dialog shape (the visit date is set on create). */
export interface VisitorFormData {
  name: string
  contact: string
  email: string
  service: BuiltInService | ''
  education_level_id: number | null
  age: number | string | null
  grade_score: number | string | null
  province_id: number | null
  english_level_id: number | null
  acquisition_route_id: number | null
  consultant_employee_id: number | null
  consultant_review: ConsultantReview | ''
  notes: string
}

/**
 * The visitor fields that also exist on an applicant/contract. Only present
 * values are copied so selecting a visitor never clears what the operator
 * already typed.
 */
export const visitorApplicantFields = (visitor: Pick<Visitor, 'name' | 'contact' | 'email' | 'education_level_id' | 'grade_score'>): ApplicantPayload => {
  const payload: ApplicantPayload = {
    name: visitor.name,
    contact: visitor.contact,
  }
  if (visitor.email)
    payload.email = visitor.email
  if (visitor.education_level_id != null)
    payload.education_level_id = visitor.education_level_id
  if (visitor.grade_score != null && visitor.grade_score !== '')
    payload.grade_score = visitor.grade_score

  return payload
}

// Visitor API endpoints
const VisitorApi = {
  convertToApplicant: async (id: number): Promise<Applicant> => (await api.post<{ data: Applicant }>(`/visitors/${id}/convert-to-applicant`)).data.data,
  updateConsultantReview: async (id: number, consultant_review: ConsultantReview | null): Promise<Visitor> => (await api.put<{ data: Visitor }>(`/visitors/${id}/consultant-review`, { consultant_review })).data.data,

  // Every matching visitor; also used by the print report, which covers the
  // whole filtered set rather than the visible page.
  getAll: async (params?: VisitorParams) => {
    try {
      const response = await api.get<{ data: Visitor[] }>('/visitors', { params: cleanListParams({ ...params }) })

      return response.data.data
    }
    catch (error) {
      console.error('API getAll error:', error)
      throw error
    }
  },

  // One page of the visitors / follow-up list
  listPage: async (params?: VisitorParams & ListPageParams) => {
    try {
      const response = await api.get('/visitors', {
        params: cleanListParams({ ...params, page: params?.page, per_page: params?.perPage }),
      })

      return readPage<Visitor>(response.data)
    }
    catch (error) {
      console.error('API listPage error:', error)
      throw error
    }
  },

  // Get single visitor
  getById: async (id: number) => {
    try {
      const response = await api.get<{ data: Visitor }>(`/visitors/${id}`)

      return response.data.data
    }
    catch (error) {
      console.error('API getById error:', error)
      throw error
    }
  },

  // Create visitor
  create: async (data: CreateVisitorData) => {
    try {
      const response = await api.post<{ data: Visitor }>('/visitors', data)

      return response.data.data
    }
    catch (error) {
      console.error('API create error:', error)
      throw error
    }
  },

  // Update visitor
  update: async (id: number, data: UpdateVisitorData) => {
    try {
      const response = await api.put<{ data: Visitor }>(`/visitors/${id}`, data)

      return response.data.data
    }
    catch (error) {
      console.error('API update error:', error)
      throw error
    }
  },

  // Mark or unmark a visitor as worthy of follow-up
  setFollowUp: async (id: number, followUp: boolean) => {
    try {
      const response = await api.put<{ data: Visitor }>(`/visitors/${id}/follow-up`, { follow_up: followUp })

      return response.data.data
    }
    catch (error) {
      console.error('API setFollowUp error:', error)
      throw error
    }
  },

  // Delete visitor
  delete: async (id: number) => {
    try {
      await api.delete(`/visitors/${id}`)
    }
    catch (error) {
      console.error('API delete error:', error)
      throw error
    }
  },
}

export default VisitorApi
