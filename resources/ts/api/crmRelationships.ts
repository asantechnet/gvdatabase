import api from './axios'

export type EntityType = 'visitor' | 'applicant' | 'university' | 'country'
export type Channel = 'call' | 'message' | 'email' | 'meeting' | 'note'

export interface Interaction {
  id: number
  applicant_id?: number | null
  applicant?: { id: number; name: string } | null
  channel: Channel
  title: string
  notes: string | null
  contacted_at: string
  follow_up_at: string | null
  creator?: { id: number; name: string } | null
}

export interface Relationship {
  id: number
  name: string
  type?: EntityType
  contact?: string | null
  email?: string | null
  code?: string | null
  is_active?: boolean | null
  status?: string | null
  address?: string | null
  tax_number?: string | null
  service?: string | null
  service_interest?: string | null
  country?: { id: number; name: string; code?: string } | null
  education_level?: { id: number; name: string } | null
  province?: { id: number; name: string } | null
  english_level?: { id: number; name: string } | null
  acquisition_route?: { id: number; name: string } | null
  consultant?: { id: number; name: string } | null
  age?: number | null
  grade_score?: number | string | null
  consultant_review?: string | null
  stage?: string
  probability?: number | null
  estimated_value?: number | string | null
  next_follow_up_at?: string | null
  date?: string | null
  owner?: { id: number; name: string } | null
  responsible_employee?: { id: number; name: string } | null
  responsible_employees?: Array<{ id: number; name: string }>
  current_stage?: { name: string; key?: string } | null
  services?: Array<{ id: number; type?: string | null; status?: string | null }>
  notes?: string | null
  interactions?: Interaction[]
}

export interface ApplicantRelationshipRecord {
  id: number
  target_type: 'country' | 'university'
  target_id: number
  target_name: string | null
  channel: Channel
  title: string
  notes: string | null
  contacted_at: string
  follow_up_at: string | null
  creator: { id: number; name: string } | null
}

export interface ApplicantRelationships {
  applicant_id: number
  country: { id: number; name: string; code?: string | null; contact?: string | null; email?: string | null } | null
  universities: Array<{
    id: number
    university_id: number
    status?: string | null
    intake?: string | null
    university: { id: number; name: string; contact?: string | null; email?: string | null } | null
  }>
  records: ApplicantRelationshipRecord[]
}

export type InteractionPayload = Pick<Interaction, 'channel' | 'title' | 'notes'> & Partial<Pick<Interaction, 'contacted_at' | 'follow_up_at'>>

export default {
  async get(type: EntityType, id: number, caseApplicantId?: number | null): Promise<Relationship> {
    if (type === 'visitor' || type === 'applicant')
      return (await api.get<{ data: Relationship }>(`/${type === 'applicant' ? 'applicants' : 'visitors'}/${id}`)).data.data

    return (await api.get<{ data: Relationship }>(`/crm-relationships/${type}/${id}`, {
      params: caseApplicantId ? { applicant_id: caseApplicantId } : undefined,
    })).data.data
  },
  async saveNotes(type: EntityType, id: number, notes: string | null): Promise<string | null> {
    return (await api.patch<{ data: { notes: string | null } }>(`/crm-relationships/${type}/${id}/notes`, { notes })).data.data.notes
  },
  async createInteraction(type: EntityType, id: number, payload: InteractionPayload, applicantId?: number | null): Promise<void> {
    // Existing visitor/applicant callers keep their original endpoint. Only
    // university/country interactions use the additive relationship route.
    if (type === 'visitor' || type === 'applicant') {
      await api.post(`/crm/${type}/${id}/interactions`, payload)

      return
    }

    await api.post(`/crm-relationships/${type}/${id}/interactions`, {
      ...payload,
      ...(applicantId ? { applicant_id: applicantId } : {}),
    })
  },
  async getApplicantRelationships(applicantId: number): Promise<ApplicantRelationships> {
    return (await api.get<{ data: ApplicantRelationships }>(`/applicants/${applicantId}/relationships`)).data.data
  },
  async updateInteraction(id: number, payload: InteractionPayload): Promise<void> {
    await api.put(`/crm/interactions/${id}`, payload)
  },
  async removeInteraction(id: number): Promise<void> {
    await api.delete(`/crm/interactions/${id}`)
  },
}
