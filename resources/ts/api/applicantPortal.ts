import type { BuiltInService } from './applicants'
import api from './axios'

export interface ApplicantPortalProfile {
  name: string | null
  email: string | null
  phone: string | null
  address: string | null
  father_name?: string | null
  father_phone?: string | null
  date_of_birth?: string | null
  passport_number?: string | null
  nationality?: string | null
  education_background?: string | null
  intended_intake?: string | null
  english_test?: string | null
  preferred_contact_method?: 'email' | 'phone' | 'whatsapp' | null
  completed_at?: string | null
}

export interface ApplicantPortalData {
  profile: ApplicantPortalProfile
  case: {
    id: number
    status: string
    created_at: string | null
    service: BuiltInService
    service_details: { test?: string | null; program?: string | null; time?: string | null; duration?: string | null }
    destination_country: string | null
    primary_university: string | null
    education_level: string | null
    grade_score: number | null
    advisor: { name: string; phone: string | null; email: string | null } | null
    current_stage: { key: string; name: string; is_terminal: boolean } | null
    progress: { current: number | null; total: number }
    timeline: Array<{ key: string | null; name: string | null; reached_at: string | null }>
    university_applications: Array<{ university: string | null; status: string; intake: string | null }>
  }
}

export interface ApplicantPortalAttachment {
  id: number
  label: string
  name: string
  mime_type: string | null
  size: number
  uploaded_at: string | null
}

export type ApplicantPortalProfileInput = Partial<Pick<ApplicantPortalProfile, 'name' | 'email' | 'phone' | 'address' | 'father_name' | 'father_phone'>> & {
  profile?: Partial<Omit<ApplicantPortalProfile, 'name' | 'email' | 'phone' | 'address' | 'father_name' | 'father_phone' | 'completed_at'>>
}

const get = async () => (await api.get('/applicant-portal')).data.data as ApplicantPortalData
const updateProfile = async (data: ApplicantPortalProfileInput) => (await api.put('/applicant-portal/profile', data)).data.data as ApplicantPortalData
const listAttachments = async () => (await api.get('/applicant-portal/attachments')).data.data as ApplicantPortalAttachment[]
const uploadAttachment = async (file: File, label?: string) => {
  const data = new FormData()
  data.append('file', file)
  if (label?.trim())
    data.append('label', label.trim())

  return (await api.post('/applicant-portal/attachments', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })).data.data as ApplicantPortalAttachment
}
const invite = async (applicantId: number, data: { name: string; email: string; phone?: string | null; address?: string | null }) =>
  (await api.post(`/applicants/${applicantId}/portal-invitation`, data)).data.data as { user_id: number; email: string; message: string }

const setAccess = async (applicantId: number, enabled: boolean) =>
  (await api.put(`/applicants/${applicantId}/portal-access`, { enabled })).data.data as { user_id: number; status: 'active' | 'inactive' }

export default { get, updateProfile, listAttachments, uploadAttachment, invite, setAccess }
