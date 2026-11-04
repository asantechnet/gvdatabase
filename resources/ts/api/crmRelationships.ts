import api from './axios'

export type EntityType = 'visitor' | 'applicant'
export type Channel = 'call' | 'message' | 'email' | 'meeting' | 'note'

export interface Interaction {
  id: number
  channel: Channel
  title: string
  notes: string
  contacted_at: string
  follow_up_at: string | null
  creator?: { id: number; name: string } | null
}

export interface Relationship {
  id: number
  name: string
  contact?: string | null
  email?: string | null
  address?: string | null
  tax_number?: string | null
  service?: string | null
  service_interest?: string | null
  country?: { id: number; name: string; code?: string } | null
  stage?: string
  probability?: number | null
  estimated_value?: number | string | null
  next_follow_up_at?: string | null
  date?: string | null
  owner?: { id: number; name: string } | null
  responsible_employee?: { id: number; name: string } | null
  current_stage?: { name: string } | null
  services?: Array<{ id: number; type?: string | null; status?: string | null }>
  notes?: string | null
  interactions?: Interaction[]
}

export type InteractionPayload = Pick<Interaction, 'channel' | 'title' | 'notes'> & Partial<Pick<Interaction, 'contacted_at' | 'follow_up_at'>>

export default {
  async get(type: EntityType, id: number): Promise<Relationship> {
    return (await api.get<{ data: Relationship }>(`/${type === 'applicant' ? 'applicants' : 'visitors'}/${id}`)).data.data
  },
  async saveNotes(type: EntityType, id: number, notes: string | null): Promise<string | null> {
    return (await api.patch<{ data: { notes: string | null } }>(`/crm/${type}/${id}/notes`, { notes })).data.data.notes
  },
  async createInteraction(type: EntityType, id: number, payload: InteractionPayload): Promise<void> {
    await api.post(`/crm/${type}/${id}/interactions`, payload)
  },
  async updateInteraction(id: number, payload: InteractionPayload): Promise<void> {
    await api.put(`/crm/interactions/${id}`, payload)
  },
  async removeInteraction(id: number): Promise<void> {
    await api.delete(`/crm/interactions/${id}`)
  },
}
