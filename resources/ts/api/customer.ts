import api from './axios'
import type { ApiResponse as ServiceApiResponse, Service } from './services'

export type ApiResponse<T> = ServiceApiResponse<T>

export interface CustomerService {
  id: number
  service_id: number
  type: string
  fee: number
  currency_id: number | null
  currency: {
    id: number | string
    name: string
    code: string
    symbol: string
    rate: number
  }
  deadline: string | null
  status: string
  notes?: string | null
  created_at: string
  updated_at: string
}

export interface Customer {
  id: number
  name: string
  company: string
  contact: string
  email: string | null
  address: string | null
  tax_number: string | null
  marketer: string | null
  date: string
  services: CustomerService[]
  created_at: string
  updated_at: string
}

export interface CustomerParams {
  search?: string
  company?: string
  marketer?: string
  compact?: boolean
}

export interface CreateCustomerData {
  name: string
  company: string
  contact: string
  email?: string | null
  address?: string | null
  tax_number?: string | null
  marketer?: string | null
  date: string
}

export type UpdateCustomerData = Partial<CreateCustomerData>

export interface CreateCustomerServiceData {
  service_id: number
  deadline?: string | null
}

export interface UpdateCustomerServiceData {
  service_id: number
  fee?: number | null
  currency_id?: number | null
  deadline: string | null
  status: 'active' | 'paused' | 'completed'
  notes?: string | null
}

const emptyCustomer = (): Customer => ({
  id: 0,
  name: '',
  company: '',
  contact: '',
  email: null,
  address: null,
  tax_number: null,
  marketer: '',
  date: '',
  services: [],
  created_at: '',
  updated_at: '',
})

const errorMessage = (error: any, fallback: string) =>
  error.response?.data?.message || error.message || fallback

const CustomerApi = {
  async getAll(params?: CustomerParams): Promise<ApiResponse<Customer[]>> {
    try {
      const response = await api.get<{ data: Customer[] }>('/customers', { params })
      return { success: true, data: response.data.data }
    } catch (error) {
      return { success: false, data: [], message: errorMessage(error, 'Failed to fetch customers') }
    }
  },

  async getById(id: number): Promise<ApiResponse<Customer>> {
    try {
      const response = await api.get<{ data: Customer }>(`/customers/${id}`)
      return { success: true, data: response.data.data }
    } catch (error) {
      return { success: false, data: emptyCustomer(), message: errorMessage(error, 'Failed to fetch customer') }
    }
  },

  async getAllServices(): Promise<ApiResponse<Service[]>> {
    try {
      const response = await api.get<{ data: Service[] }>('/services')
      return { success: true, data: response.data.data }
    } catch (error) {
      return { success: false, data: [], message: errorMessage(error, 'Failed to fetch services') }
    }
  },

  async create(data: CreateCustomerData): Promise<ApiResponse<Customer>> {
    try {
      const response = await api.post<{ data: Customer }>('/customers', data)
      return { success: true, data: response.data.data }
    } catch (error) {
      return { success: false, data: emptyCustomer(), message: errorMessage(error, 'Failed to create customer') }
    }
  },

  async update(id: number, data: UpdateCustomerData): Promise<ApiResponse<Customer>> {
    try {
      const response = await api.put<{ data: Customer }>(`/customers/${id}`, data)
      return { success: true, data: response.data.data }
    } catch (error) {
      return { success: false, data: emptyCustomer(), message: errorMessage(error, 'Failed to update customer') }
    }
  },

  async attachServices(customerId: number, services: CreateCustomerServiceData[]): Promise<ApiResponse<CustomerService[]>> {
    try {
      const response = await api.post<{ data: CustomerService[] }>(`/customers/${customerId}/services`, { services })
      return { success: true, data: response.data.data }
    } catch (error) {
      return { success: false, data: [], message: errorMessage(error, 'Failed to attach services') }
    }
  },

  async updateService(customerId: number, pivotId: number, data: UpdateCustomerServiceData): Promise<ApiResponse<CustomerService>> {
    try {
      const response = await api.put<{ data: CustomerService }>(`/customers/${customerId}/services/${pivotId}`, data)
      return { success: true, data: response.data.data }
    } catch (error) {
      return { success: false, data: {} as CustomerService, message: errorMessage(error, 'Failed to update customer service') }
    }
  },

  async detachService(customerId: number, pivotId: number): Promise<ApiResponse<null>> {
    try {
      await api.delete(`/customers/${customerId}/services/${pivotId}`)
      return { success: true, data: null }
    } catch (error) {
      return { success: false, data: null, message: errorMessage(error, 'Failed to detach service') }
    }
  },

  async delete(id: number): Promise<ApiResponse<null>> {
    try {
      await api.delete(`/customers/${id}`)
      return { success: true, data: null }
    } catch (error) {
      return { success: false, data: null, message: errorMessage(error, 'Failed to delete customer') }
    }
  },
}

export default CustomerApi
