import api from './axios'

export interface ContractPolicy {
  id: number
  label: string
  type: string
  is_active: boolean
  header: string | null
  content: string
  created_at: string
  updated_at: string
}

export type ContractPolicyPayload = Pick<ContractPolicy, 'label' | 'type' | 'is_active' | 'header' | 'content'>

export default {
  async getAll(includeInactive = false): Promise<ContractPolicy[]> {
    return (await api.get<{ data: ContractPolicy[] }>('/contract-policies', { params: { include_inactive: includeInactive } })).data.data
  },
  async create(data: ContractPolicyPayload): Promise<ContractPolicy> {
    return (await api.post<{ data: ContractPolicy }>('/contract-policies', data)).data.data
  },
  async update(id: number, data: Partial<ContractPolicyPayload>): Promise<ContractPolicy> {
    return (await api.put<{ data: ContractPolicy }>(`/contract-policies/${id}`, data)).data.data
  },
  async delete(id: number): Promise<void> {
    await api.delete(`/contract-policies/${id}`)
  },
}
