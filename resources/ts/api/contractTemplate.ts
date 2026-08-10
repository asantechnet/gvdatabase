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
  // Get all contract templates
  getAll: () => api.get<{ success: boolean; data: ContractTemplate[] }>('/contract-templates'),

  // Get single contract template
  getById: (id: number) => api.get<{ success: boolean; data: ContractTemplate }>(`/contract-templates/${id}`),

  // Create contract template
  create: (data: ContractTemplateCreateData) =>
    api.post<{ success: boolean; data: ContractTemplate }>('/contract-templates', data),

  // Update contract template
  update: (id: number, data: ContractTemplateUpdateData) =>
    api.put<{ success: boolean; data: ContractTemplate }>(`/contract-templates/${id}`, data),

  // Delete contract template
  delete: (id: number) => api.delete<{ success: boolean; message: string }>(`/contract-templates/${id}`),
}

export default ContractTemplateApi
