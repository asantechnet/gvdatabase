import api from './axios'
import type { Applicant } from './applicants'

export interface Counselor {
  id: number
  name: string
}

export interface Visitor {
  id: number
  name: string
  contact: string
  email: string | null
  service: string

  counselor_employee_id: number | null
  counselor: Counselor | null
  notes: string | null
  date: string
  created_at?: string
  updated_at?: string
}

export interface VisitorParams {
  page?: number
  perPage?: number
  search?: string
  counselor_employee_id?: number | null
  date_from?: string
  date_to?: string
}

export interface CreateVisitorData {
  name: string
  contact: string
  email?: string | null
  service: string
  counselor_employee_id?: number | null
  date: string
  notes?: string | null
}

export interface UpdateVisitorData {
  name?: string
  contact?: string
  email?: string | null
  service?: string
  counselor_employee_id?: number | null
  date?: string
  notes?: string | null
}

export const visitorApplicantFields = (visitor: Pick<Visitor, 'name' | 'contact' | 'email'>) => ({
  name: visitor.name,
  contact: visitor.contact,
  email: visitor.email,
})

// Visitor API endpoints
const VisitorApi = {
  convertToApplicant: async (id: number): Promise<Applicant> => (await api.post<{ data: Applicant }>(`/visitors/${id}/convert-to-applicant`)).data.data,

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
