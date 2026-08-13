import api from './axios'

export interface Position {
  id: number
  title: string
  description: string | null
  salary: number
  status: 'active' | 'inactive'
  employee_count: number
  user_count: number
  created_at: string | null
  updated_at: string | null
}

export interface PositionPayload {
  title: string
  description?: string | null
  salary: number
  status: 'active' | 'inactive'
}

const PositionApi = {
  // Get all positions
  getAll: () => api.get('/positions'),

  // Get single position
  getById: (id: number) => api.get(`/positions/${id}`),

  // Create position
  create: (data: PositionPayload) => api.post('/positions', data),

  // Update position
  update: (id: number, data: Partial<PositionPayload>) => api.put(`/positions/${id}`, data),

  // Delete position
  delete: (id: number) => api.delete(`/positions/${id}`),

}

export default PositionApi
