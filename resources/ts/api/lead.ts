import api from './axios'

export type LeadStage = 'new' | 'contacted' | 'qualified' | 'proposal' | 'won' | 'lost'
export interface CrmInteraction { id: number; channel: string; title: string; notes: string; contacted_at: string; follow_up_at: string | null; creator?: { id: number; name: string } }
export interface CrmMarketer {
  id: number
  name: string
}

export interface Lead {
  id: number
  visitor_id: number | null
  customer_id: number | null
  name: string
  company: string | null
  contact: string
  email: string | null
  service_interest: string | null

  // Keep the legacy label for readable lists and older records. New writes use
  // marketer_id, which is the authoritative employee relation.
  marketer: string | null
  marketer_id: number | null
  marketer_employee: CrmMarketer | null
  stage: LeadStage
  probability: number
  estimated_value: string | null
  next_follow_up_at: string | null
  notes: string | null
  interactions: CrmInteraction[]
}

export interface LeadListParams {
  search?: string
  stage?: LeadStage | null
  marketer_id?: number | null
}

export interface CreateLeadData {
  name: string
  company?: string | null
  contact: string
  email?: string | null
  service_interest?: string | null
  marketer_id?: number | null
  stage?: LeadStage
  probability?: number
  estimated_value?: number | null
  next_follow_up_at?: string | null
  notes?: string | null
}

export type UpdateLeadData = Partial<CreateLeadData>

export default {
  async list(params?: LeadListParams) { return (await api.get('/leads', { params })).data },
  async create(data: CreateLeadData) { return (await api.post('/leads', data)).data.data as Lead },
  async update(id: number, data: UpdateLeadData) { return (await api.put(`/leads/${id}`, data)).data.data as Lead },
  async convert(id: number) { return (await api.post(`/leads/${id}/convert-to-customer`)).data.data },
  async fromVisitor(id: number) { return (await api.post(`/visitors/${id}/convert-to-lead`)).data.data as Lead },
}
