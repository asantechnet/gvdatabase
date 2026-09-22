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

export const SYSTEM_TYPE_SLUGS = ['employment', 'customer', 'other'] as const

const ContractTypeApi = {
  async getAll(params?: { include_inactive?: boolean }): Promise<ContractType[]> {
    const response = await api.get<{ success: boolean; data: ContractType[] }>('/contract-types', { params })

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  async getById(id: number): Promise<ContractType> {
    const response = await api.get<{ success: boolean; data: ContractType }>(`/contract-types/${id}`)

    return response.data.data
  },

}

export default ContractTypeApi
