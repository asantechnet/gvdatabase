import api from './axios'

export type AcademyCertificateStatus = 'issued' | 'revoked'

export interface AcademyCertificateRecord {
  id: number
  certificate_number: string
  academy_enrollment_id: number
  student: { id: string | null; name: string | null; father_name: string | null }
  program: string | null
  class: string | null
  teachers: string[]
  started_on: string | null
  ends_on: string | null
  completed_on: string | null
  status: AcademyCertificateStatus
  issued_at: string | null
  issued_by: { id: number; name: string } | null
  revoked_at: string | null
  revoked_by: { id: number; name: string } | null
  revocation_reason: string | null
  snapshot: {
    award: {
      student_name: string
      father_name: string | null
      student_id: string | null
      program: string | null
      class: string | null
      teachers: string[]
      start_date: string | null
      end_date: string | null
      completion_date: string | null
    }
    certificate_number: string
    issue_date: string | null
    signatory: { name: string | null; title: string | null }
    template: { id: number; name: string; title: string; body: string; signatory_title: string | null; version: string }
    rendered_body: string
  }
}

export interface AcademyCertificateTemplate {
  id: number
  name: string
  title: string
  body: string
  signatory_title: string | null
  is_active: boolean
  updated_at: string | null
  tokens: string[]
}

const data = <T>(response: { data: { data: T } }): T => response.data.data

export const AcademyCertificatesApi = {
  async list(params: { status?: AcademyCertificateStatus; class_id?: number; search?: string; page?: number } = {}): Promise<{ data: AcademyCertificateRecord[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
    const response = await api.get('/academy/certificates', { params })

    return {
      data: Array.isArray(response.data.data) ? response.data.data : [],
      meta: response.data.meta,
    }
  },

  async issue(payload: { academy_enrollment_id?: number; class_id?: number; enrollment_ids?: number[]; template_id?: number | null }): Promise<AcademyCertificateRecord | { created: number; skipped: number; certificates: AcademyCertificateRecord[] }> {
    return data(await api.post('/academy/certificates', payload))
  },

  async revoke(id: number, reason: string): Promise<AcademyCertificateRecord> {
    return data(await api.patch(`/academy/certificates/${id}/revoke`, { reason }))
  },

  async templates(): Promise<AcademyCertificateTemplate[]> {
    const response = await api.get('/academy/certificate-templates')

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  async updateTemplate(id: number, payload: Partial<Pick<AcademyCertificateTemplate, 'name' | 'title' | 'body' | 'signatory_title' | 'is_active'>>): Promise<AcademyCertificateTemplate> {
    return data(await api.patch(`/academy/certificate-templates/${id}`, payload))
  },
}

export default AcademyCertificatesApi
