import api, { mutationHeaders, type ApiMutationOptions } from './axios'
import type { BuiltInService } from './applicants'

export interface ContractDocumentDesign {
  id: number
  contract_type_id: number
  service: BuiltInService | null
  letterhead: string | null
  cover_design: string | null
  letterhead_url: string | null
  cover_design_url: string | null
  safe_x: number
  safe_y: number
  safe_width: number
  safe_height: number
  contract_type?: { id: number; name: string; slug: string } | null
}

export interface ContractDocumentDesignSafeArea {
  x: number
  y: number
  width: number
  height: number
}

export interface ContractDocumentDesignPayload {
  contractTypeId: number
  /** Only customer contracts can scope artwork to a service. */
  service?: BuiltInService | null
  letterhead?: File | null
  coverDesign?: File | null
  removeLetterhead?: boolean
  removeCoverDesign?: boolean
  safeArea?: ContractDocumentDesignSafeArea
}

const toFormData = (payload: ContractDocumentDesignPayload): FormData => {
  const data = new FormData()

  data.append('contract_type_id', String(payload.contractTypeId))

  if (payload.service)
    data.append('service', payload.service)

  if (payload.letterhead)
    data.append('letterhead', payload.letterhead)

  if (payload.coverDesign)
    data.append('cover_design', payload.coverDesign)

  if (payload.removeLetterhead && !payload.letterhead)
    data.append('remove_letterhead', '1')

  if (payload.removeCoverDesign && !payload.coverDesign)
    data.append('remove_cover_design', '1')

  if (payload.safeArea) {
    data.append('safe_x', String(payload.safeArea.x))
    data.append('safe_y', String(payload.safeArea.y))
    data.append('safe_width', String(payload.safeArea.width))
    data.append('safe_height', String(payload.safeArea.height))
  }

  return data
}

const ContractDocumentDesignApi = {
  async getAll(params?: { contract_type_id?: number; service?: BuiltInService }): Promise<ContractDocumentDesign[]> {
    const response = await api.get<{ data: ContractDocumentDesign[] }>('/contract-document-designs', { params })

    return Array.isArray(response.data.data) ? response.data.data : []
  },
  async save(id: number | null, payload: ContractDocumentDesignPayload, options?: ApiMutationOptions): Promise<ContractDocumentDesign> {
    const data = toFormData(payload)

    if (id) {
      // PHP only reliably populates uploaded files for multipart POST requests.
      // Laravel honours this override and still dispatches to the PUT route.
      data.append('_method', 'PUT')

      const response = await api.post<{ data: ContractDocumentDesign }>(`/contract-document-designs/${id}`, data, {
        headers: { 'Content-Type': 'multipart/form-data', ...mutationHeaders(options) },
      })

      return response.data.data
    }

    const response = await api.post<{ data: ContractDocumentDesign }>('/contract-document-designs', data, {
      headers: { 'Content-Type': 'multipart/form-data', ...mutationHeaders(options) },
    })

    return response.data.data
  },
}

export default ContractDocumentDesignApi
