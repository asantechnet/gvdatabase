import api from './axios'

export type StockCondition = 'new' | 'good' | 'fair' | 'poor' | 'damaged'

export interface StockItem {
  id: number
  serial_number: string
  item: string
  quantity: number
  price: number
  model: string
  condition: StockCondition
  office_id: number | null
  office?: {
    id: number
    name: string
    code: string
    status: 'active' | 'inactive'
  }
  staff_id: number | null
  staff?: {
    id: number
    name: string
  }
}

export interface StockParams {
  search?: string
  model?: string
  office_id?: number
  condition?: StockCondition
}

export type StockPayload = Omit<StockItem, 'id' | 'staff' | 'office'>

const unwrapData = <T>(response: unknown): T => {
  let payload = response

  while (payload !== null && typeof payload === 'object' && 'data' in payload)
    payload = (payload as { data: unknown }).data

  return payload as T
}

const StockApi = {
  async getAll(params?: StockParams): Promise<StockItem[]> {
    const response = await api.get('/stocks', { params })
    const stocks = unwrapData<StockItem[]>(response)

    return Array.isArray(stocks) ? stocks : []
  },

  async getById(id: number): Promise<StockItem> {
    return unwrapData<StockItem>(await api.get(`/stocks/${id}`))
  },

  async create(data: StockPayload): Promise<StockItem> {
    return unwrapData<StockItem>(await api.post('/stocks', data))
  },

  async update(id: number, data: Partial<StockPayload>): Promise<StockItem> {
    return unwrapData<StockItem>(await api.put(`/stocks/${id}`, data))
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/stocks/${id}`)
  },
}

export default StockApi
