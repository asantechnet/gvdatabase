import api from './axios'

export interface ContractTemplate {
  id: number
  name: string
  type: string | null
  contract_type_id: number | null
  type_slug: string | null
  position_id: number | null
  cover_text: string | null
  content: string | null
  policies?: Array<{
    id: number
    label: string
    type: string
    header: string | null
    content: string
  }>
  contracts_count?: number
  created_at: string
  updated_at: string
  versions?: ContractTemplateVersion[]
}
export interface ContractTemplateVersion { id: number; language_id: number; version_number: number; content?: string; status: 'draft' | 'active' | 'archived'; is_current: boolean }

export interface ContractTemplateCreateData {
  name: string
  contract_type_id: number
  position_id?: number | null
  policy_ids?: number[]
  cover_text?: string | null

  /** Sanitized HTML produced by the TipTap editor. */
  content: string
}

export interface ContractTemplateUpdateData extends Partial<
  Omit<ContractTemplateCreateData, 'contract_type_id'>
> {
  contract_type_id?: number
}

const ContractTemplateApi = {
  async getAll(): Promise<ContractTemplate[]> {
    const response = await api.get<{
      success: boolean
      data: ContractTemplate[]
    }>('/contract-templates')

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  async getById(id: number): Promise<ContractTemplate> {
    const response = await api.get<{
      success: boolean
      data: ContractTemplate
    }>(`/contract-templates/${id}`)

    return response.data.data
  },

  async create(data: ContractTemplateCreateData): Promise<ContractTemplate> {
    console.log('Creating contract template with data:', data)

    const response = await api.post<{
      success: boolean
      data: ContractTemplate
    }>('/contract-templates', data)

    return response.data.data
  },

  async update(
    id: number,
    data: ContractTemplateUpdateData,
  ): Promise<ContractTemplate> {
    const response = await api.put<{
      success: boolean
      data: ContractTemplate
    }>(`/contract-templates/${id}`, data)

    return response.data.data
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/contract-templates/${id}`)
  },
  async versions(id: number): Promise<ContractTemplateVersion[]> { return (await api.get(`/contract-templates/${id}/versions`)).data.data },
  async createVersion(id: number, data: Pick<ContractTemplateVersion, 'language_id' | 'content'> & { status?: string }): Promise<ContractTemplateVersion> { return (await api.post(`/contract-templates/${id}/versions`, data)).data.data },
  async activateVersion(templateId: number, versionId: number): Promise<ContractTemplateVersion> { return (await api.post(`/contract-templates/${templateId}/versions/${versionId}/activate`)).data.data },
}

export default ContractTemplateApi
