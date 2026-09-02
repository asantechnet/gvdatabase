import api from './axios'

export interface Office {
  id: number
  name: string
  code: string
  email: string | null
  phone: string | null
  address: string | null
  status: 'active' | 'inactive'
  latitude: number | null
  longitude: number | null
  geofence_radius_meters: number
  geofence_enabled: boolean
  asset_count: number
  created_at: string | null
  updated_at: string | null
}

export type OfficePayload = Pick<Office, 'name' | 'code' | 'email' | 'phone' | 'address' | 'status' | 'latitude' | 'longitude' | 'geofence_radius_meters' | 'geofence_enabled'>

const OfficeApi = {
  async getAll(): Promise<Office[]> {
    const response = await api.get('/offices')
    return response.data.data
  },
  async create(data: OfficePayload): Promise<Office> {
    const response = await api.post('/offices', data)
    return response.data.data
  },
  async update(id: number, data: OfficePayload): Promise<Office> {
    const response = await api.put(`/offices/${id}`, data)
    return response.data.data
  },
  async delete(id: number): Promise<void> {
    await api.delete(`/offices/${id}`)
  },
}

export default OfficeApi
