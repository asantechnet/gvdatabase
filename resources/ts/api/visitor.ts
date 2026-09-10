import api from './axios'

export interface CrmMarketer {
  id: number
  name: string
}

export interface Visitor {
  id: number
  name: string
  company: string
  contact: string
  email: string | null
  service: string

  // `marketer` remains a display label for legacy records. New requests use
  // the relational marketer_id instead of trying to match a name.
  marketer: string | null
  marketer_id: number | null
  marketer_employee: CrmMarketer | null
  notes: string | null
  date: string
  created_at?: string
  updated_at?: string
}

export interface VisitorParams {
  page?: number
  perPage?: number
  search?: string
  company?: string
  marketer_id?: number | null
}

export interface CreateVisitorData {
  name: string
  company: string
  contact: string
  email?: string | null
  service: string
  marketer_id?: number | null
  date: string
  notes?: string | null
}

export interface UpdateVisitorData {
  name?: string
  company?: string
  contact?: string
  email?: string | null
  service?: string
  marketer_id?: number | null
  date?: string
  notes?: string | null
}

// Visitor API endpoints
const VisitorApi = {
  convertToCustomer: async (id: number) => (await api.post(`/visitors/${id}/convert-to-customer`)).data.data,

  // Get all visitors
  getAll: async (params?: VisitorParams) => {
    try {
      const response = await api.get<{ data: Visitor[] }>('/visitors', { params })

      return response.data.data
    }
    catch (error) {
      console.error('API getAll error:', error)
      throw error
    }
  },

  // Get single visitor
  getById: async (id: number) => {
    try {
      const response = await api.get<{ data: Visitor }>(`/visitors/${id}`)

      return response.data.data
    }
    catch (error) {
      console.error('API getById error:', error)
      throw error
    }
  },

  // Create visitor
  create: async (data: CreateVisitorData) => {
    try {
      const response = await api.post<{ data: Visitor }>('/visitors', data)

      return response.data.data
    }
    catch (error) {
      console.error('API create error:', error)
      throw error
    }
  },

  // Update visitor
  update: async (id: number, data: UpdateVisitorData) => {
    try {
      const response = await api.put<{ data: Visitor }>(`/visitors/${id}`, data)

      return response.data.data
    }
    catch (error) {
      console.error('API update error:', error)
      throw error
    }
  },

  // Delete visitor
  delete: async (id: number) => {
    try {
      await api.delete(`/visitors/${id}`)
    }
    catch (error) {
      console.error('API delete error:', error)
      throw error
    }
  },
}

export default VisitorApi
