import api from './axios'
import { cleanListParams, readPage, type ListPage, type ListPageParams } from './pagination'

export type AssetCondition = 'new' | 'good' | 'fair' | 'poor' | 'damaged'

export interface AssetItem {
  id: number
  serial_number: string
  item: string
  quantity: number
  price: number
  model: string
  condition: AssetCondition
  office_id: number | null
  office?: {
    id: number
    name: string
    code: string
    status: 'active' | 'inactive'
  }
  allocated_quantity?: number
  available_quantity?: number
  allocations: AssetAllocation[]
}

export interface AssetAllocation {
  id?: number
  employee_id: number
  employee?: { id: number; name: string } | null
  quantity: number
}

export interface AssetParams {
  search?: string
  model?: string
  office_id?: number
  condition?: AssetCondition
}

export type AssetPayload = Omit<AssetItem, 'id' | 'office' | 'allocated_quantity' | 'available_quantity'>

const unwrapData = <T>(response: unknown): T => {
  let payload = response

  while (payload !== null && typeof payload === 'object' && 'data' in payload)
    payload = (payload as { data: unknown }).data

  return payload as T
}

const AssetApi = {
  // Every matching row; used by the print report, which covers the whole
  // filtered set rather than the visible page.
  async getAll(params?: AssetParams): Promise<AssetItem[]> {
    const response = await api.get('/assets', { params })
    const assets = unwrapData<AssetItem[]>(response)

    return Array.isArray(assets) ? assets : []
  },

  /** One page of the inventory table. */
  async listPage(params?: AssetParams & ListPageParams): Promise<ListPage<AssetItem>> {
    const response = await api.get('/assets', {
      params: cleanListParams({ ...params, page: params?.page, per_page: params?.perPage }),
    })

    return readPage<AssetItem>(response.data)
  },

  /** The models already in use, for the filter select. */
  async models(): Promise<string[]> {
    const models = unwrapData<string[]>(await api.get('/assets/models'))

    return Array.isArray(models) ? models : []
  },

  async getById(id: number): Promise<AssetItem> {
    return unwrapData<AssetItem>(await api.get(`/assets/${id}`))
  },

  async create(data: AssetPayload): Promise<AssetItem> {
    return unwrapData<AssetItem>(await api.post('/assets', data))
  },

  async update(id: number, data: Partial<AssetPayload>): Promise<AssetItem> {
    return unwrapData<AssetItem>(await api.put(`/assets/${id}`, data))
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/assets/${id}`)
  },
}

export default AssetApi
