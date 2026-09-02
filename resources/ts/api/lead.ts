import api from './axios'

export type LeadStage = 'new' | 'contacted' | 'qualified' | 'proposal' | 'won' | 'lost'
export interface CrmInteraction { id: number; channel: string; title: string; notes: string; contacted_at: string; follow_up_at: string | null; creator?: { id: number; name: string } }
export interface Lead { id: number; name: string; company: string | null; contact: string; email: string | null; service_interest: string | null; stage: LeadStage; probability: number; estimated_value: string | null; next_follow_up_at: string | null; notes: string | null; interactions: CrmInteraction[]; customer_id: number | null }

export default {
  async list(params?: { search?: string; stage?: LeadStage | null }) { return (await api.get('/leads', { params })).data },
  async create(data: Partial<Lead>) { return (await api.post('/leads', data)).data.data as Lead },
  async update(id: number, data: Partial<Lead>) { return (await api.put(`/leads/${id}`, data)).data.data as Lead },
  async convert(id: number) { return (await api.post(`/leads/${id}/convert-to-customer`)).data.data },
  async fromVisitor(id: number) { return (await api.post(`/visitors/${id}/convert-to-lead`)).data.data as Lead },
}
