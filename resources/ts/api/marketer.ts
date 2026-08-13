import api from './axios'

export interface Marketer {
  id: number
  name: string
  email: string | null
  phone: string | null
  status: 'active' | 'inactive'
  notes: string | null
  customer_count: number
  visitor_count: number
  created_at: string | null
  updated_at: string | null
}

export type MarketerPayload = Pick<Marketer, 'name' | 'email' | 'phone' | 'status' | 'notes'>

const MarketerApi = {
  async getAll(): Promise<Marketer[]> {
    const response = await api.get('/marketers')
    return response.data.data
  },
  async create(data: MarketerPayload): Promise<Marketer> {
    const response = await api.post('/marketers', data)
    return response.data.data
  },
  async update(id: number, data: MarketerPayload): Promise<Marketer> {
    const response = await api.put(`/marketers/${id}`, data)
    return response.data.data
  },
  async delete(id: number): Promise<void> {
    await api.delete(`/marketers/${id}`)
  },
}

export default MarketerApi
