import api from './axios'

export interface ContractDocumentDesign {
  id: number
  contract_type_id: number
  service_id: number | null
  letterhead_url: string | null
  cover_design_url: string | null
  safe_x: number
  safe_y: number
  safe_width: number
  safe_height: number
}

const ContractDocumentDesignApi = {
  async getAll(): Promise<ContractDocumentDesign[]> {
    const response = await api.get<{ data: ContractDocumentDesign[] }>('/contract-document-designs')
    return response.data.data
  },
  async save(id: number | null, data: FormData): Promise<ContractDocumentDesign> {
    if (id) {
      data.append('_method', 'PUT')
      const response = await api.post<{ data: ContractDocumentDesign }>(`/contract-document-designs/${id}`, data)
      return response.data.data
    }
    const response = await api.post<{ data: ContractDocumentDesign }>('/contract-document-designs', data)
    return response.data.data
  },
}

export default ContractDocumentDesignApi
