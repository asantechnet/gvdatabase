import api from './axios'

export interface Service {
  id: number
  name: string
  type: string
  code?: string | null
  category: 'general' | 'scholarship' | 'test_offer'
  description?: string | null
  fee: number
  status: 'active' | 'inactive'
  currency: {
    id: number
    name: string
    code: string
    symbol: string
  }
  /** The contract template that represents this service when it is assigned. */
  contract_template?: ServiceContractTemplate | null
  has_contract_template?: boolean
  created_at: string
  updated_at: string
  variants?: ServiceVariant[]
}
export interface ServiceVariant { id: number; service_id: number; name: string; code: string; fee?: number | null }

export interface ServiceContractTemplate {
  id: number
  name: string
  is_active: boolean
  contract_type_id: number
  service_id: number | null
}

export interface BindServiceContractTemplateData {
  contract_template_id?: number | null
  name?: string | null
  cover_text?: string | null
  content?: string | null
}

export interface CreateServiceData {
  type: string
  code?: string | null
  category?: Service['category']
  description?: string | null
  fee: number
  status: 'active' | 'inactive'
  currency_id: number
}

export interface UpdateServiceData {
  type?: string
  code?: string | null
  category?: Service['category']
  description?: string | null
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

  // Bind (or auto-create) the service's contract template
  bindContractTemplate: async (id: number, data: BindServiceContractTemplateData = {}): Promise<ApiResponse<Service>> => {
    const response = await api.post<{ data: Service }>(`/services/${id}/contract-template`, data)

    return {
      success: true,
      data: response.data.data,
    }
  },
  createVariant: async (serviceId: number, data: Omit<ServiceVariant, 'id' | 'service_id'>) => (await api.post(`/services/${serviceId}/variants`, data)).data.data as ServiceVariant,
  updateVariant: async (serviceId: number, variantId: number, data: Partial<Omit<ServiceVariant, 'id' | 'service_id'>>) => (await api.put(`/services/${serviceId}/variants/${variantId}`, data)).data.data as ServiceVariant,
  deleteVariant: async (serviceId: number, variantId: number) => api.delete(`/services/${serviceId}/variants/${variantId}`),
}

export default ServiceApi
