import api from './axios'

export interface ContractTemplate {
  id: number
  name: string
  type: 'Employment' | 'Service' | 'Partnership' | 'Lease' | 'NDA'
  cover_text: string | null
  content: any
  created_at: string
  updated_at: string
}

export interface ContractTemplateCreateData {
  name: string
  type: 'Employment' | 'Service' | 'Partnership' | 'Lease' | 'NDA'
  cover_text?: string
  content: any // Quill editor content (Delta format)
}

export interface ContractTemplateUpdateData {
  name?: string
  type?: 'Employment' | 'Service' | 'Partnership' | 'Lease' | 'NDA'
  cover_text?: string
  content?: any
}

const ContractTemplateApi = {
  async getAll(): Promise<ContractTemplate[]> {
    const response = await api.get<{ success: boolean; data: ContractTemplate[] }>('/contract-templates')
    return response.data.data
  },

  async getById(id: number): Promise<ContractTemplate> {
    const response = await api.get<{ success: boolean; data: ContractTemplate }>(`/contract-templates/${id}`)
    return response.data.data
  },

  async create(data: ContractTemplateCreateData): Promise<ContractTemplate> {
    const response = await api.post<{ success: boolean; data: ContractTemplate }>('/contract-templates', data)
    return response.data.data
  },

  async update(id: number, data: ContractTemplateUpdateData): Promise<ContractTemplate> {
    const response = await api.put<{ success: boolean; data: ContractTemplate }>(`/contract-templates/${id}`, data)
    return response.data.data
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/contract-templates/${id}`)
  },
}

export default ContractTemplateApi
