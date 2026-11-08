import api from './axios'
import type { AcademyClassRecord, AcademyDurationOption } from './academyClasses'

export type AcademyEnrollmentStatus = 'active' | 'completed' | 'withdrawn' | 'cancelled'

export interface AcademyEnrollmentRecord {
  id: number
  student_id: number
  student_number: string | null
  student: {
    id: number
    student_number: string | null
    name: string
    contact: string | null
    email: string | null
    father_phone: string | null
    applicant_id: number | null
  } | null
  applicant: { id: number; name: string; contact: string | null; father_phone: string | null } | null
  contract: { id: number; contract_number: string; status: string; start_date: string | null; end_date: string | null } | null
  line: {
    id: number
    fee: number
    discount: number
    paid: number
    balance: number
    payment_status: string
    currency: { code: string; symbol: string | null; decimal_places: number } | null
  } | null
  class: AcademyClassRecord | null
  duration: AcademyDurationOption | null
  started_on: string | null
  ends_on: string | null
  status: AcademyEnrollmentStatus
  completed_on: string | null
  notes: string | null
  created_at?: string
}

export interface AcademyEnrollmentMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface AcademyEnrollmentParams {
  class_id?: number
  status?: AcademyEnrollmentStatus
  ending?: boolean
  search?: string
  teacher_id?: number
  page?: number
  per_page?: number
}

export interface AcademyEnrollmentUpdate {
  status: Exclude<AcademyEnrollmentStatus, 'active'>
  completed_on?: string | null
  notes?: string | null
}

export const AcademyEnrollmentsApi = {
  async list(params: AcademyEnrollmentParams = {}): Promise<{ data: AcademyEnrollmentRecord[]; meta: AcademyEnrollmentMeta }> {
    const response = await api.get('/academy/enrollments', { params })

    return {
      data: Array.isArray(response.data.data) ? response.data.data : [],
      meta: response.data.meta as AcademyEnrollmentMeta,
    }
  },

  async get(id: number): Promise<AcademyEnrollmentRecord> {
    return (await api.get(`/academy/enrollments/${id}`)).data.data
  },

  async update(id: number, payload: AcademyEnrollmentUpdate): Promise<AcademyEnrollmentRecord> {
    return (await api.patch(`/academy/enrollments/${id}`, payload)).data.data
  },
}
