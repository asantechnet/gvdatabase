import api from './axios'

export interface ApplicantPortalProfile {
  name: string | null
  email: string | null
  phone: string | null
  address: string | null
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
    application_date: string | null
    destination_country: string | null
    primary_university: string | null
    education_level: string | null
    language: string | null
    current_stage: { key: string; name: string; is_terminal: boolean } | null
    progress: { current: number | null; total: number }
    timeline: Array<{ key: string | null; name: string | null; reached_at: string | null }>
    university_applications: Array<{ university: string | null; status: string; intake: string | null }>
  }
}

export type ApplicantPortalProfileInput = Partial<Pick<ApplicantPortalProfile, 'name' | 'email' | 'phone' | 'address'>> & {
  profile?: Partial<Omit<ApplicantPortalProfile, 'name' | 'email' | 'phone' | 'address' | 'completed_at'>>
}

const get = async () => (await api.get('/applicant-portal')).data.data as ApplicantPortalData
const updateProfile = async (data: ApplicantPortalProfileInput) => (await api.put('/applicant-portal/profile', data)).data.data as ApplicantPortalData
const invite = async (applicantId: number, data: { name: string; email: string; phone?: string | null; address?: string | null }) =>
  (await api.post(`/applicants/${applicantId}/portal-invitation`, data)).data.data as { user_id: number; email: string; message: string }

export default { get, updateProfile, invite }
