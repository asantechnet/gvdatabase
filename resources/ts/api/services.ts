import api from './axios'

export interface Service {
  id: number
  type: string
  duration: number
  fee: number
  status: 'active' | 'inactive'
  currency: {
    id: number
    name: string
    code: string
    symbol: string
  }
  created_at: string
  updated_at: string
}

export interface CreateServiceData {
  type: string
  duration: number
  fee: number
  status: 'active' | 'inactive'
  currency_id: number
}

export interface UpdateServiceData {
  type?: string
  duration?: number
  fee?: number
  status?: 'active' | 'inactive'
  currency_id?: number
}

export interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
}

// Service API endpoints
const ServiceApi = {
  // Get all services
  getAll: async (): Promise<ApiResponse<Service[]>> => {
    const response = await api.get<{ data: Service[] }>('/services')

    return {
      success: true,
      data: response.data.data,
    }
  },

  // Get single service
  getById: async (id: number): Promise<ApiResponse<Service>> => {
    const response = await api.get<{ data: Service }>(`/services/${id}`)

    return {
      success: true,
      data: response.data.data,
    }
  },

  // Create service
  create: async (data: CreateServiceData): Promise<ApiResponse<Service>> => {
    const response = await api.post<{ data: Service }>('/services', data)

    return {
      success: true,
      data: response.data.data,
    }
  },

  // Update service
  update: async (id: number, data: UpdateServiceData): Promise<ApiResponse<Service>> => {
    const response = await api.put<{ data: Service }>(`/services/${id}`, data)

    return {
      success: true,
      data: response.data.data,
    }
  },

  // Delete service
  delete: async (id: number): Promise<ApiResponse<null>> => {
    const response = await api.delete<{ success: boolean; message?: string }>(`/services/${id}`)

    return {
      success: response.data.success,
      data: null,
      message: response.data.message,
    }
  },
}

export default ServiceApi
