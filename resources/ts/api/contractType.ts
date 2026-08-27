import api from './axios'

export interface ContractType {
  id: number
  name: string
  slug: string
  is_system: boolean
  is_active: boolean
  templates_count?: number
  contracts_count?: number
  created_at?: string
  updated_at?: string
}

export const SYSTEM_TYPE_SLUGS = ['employment', 'customer', 'company'] as const

export interface ContractTypeCreateData {
  name: string
}

const ContractTypeApi = {
  async getAll(params?: { include_inactive?: boolean }): Promise<ContractType[]> {
    const response = await api.get<{ success: boolean; data: ContractType[] }>('/contract-types', { params })
    return Array.isArray(response.data.data) ? response.data.data : []
  },

  async getById(id: number): Promise<ContractType> {
    const response = await api.get<{ success: boolean; data: ContractType }>(`/contract-types/${id}`)
    return response.data.data
  },

  async create(data: ContractTypeCreateData): Promise<ContractType> {
    const response = await api.post<{ success: boolean; data: ContractType }>('/contract-types', data)
    return response.data.data
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/contract-types/${id}`)
  },
}

export default ContractTypeApi
