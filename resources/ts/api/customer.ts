import api from './axios'
import type { Service, ApiResponse as ServiceApiResponse } from './services'

export type ApiResponse<T> = ServiceApiResponse<T>

export interface CustomerService {
  id: number
  contract_id: number
  service_id: number
  customer_id?: number | null
  applicant_id?: number | null
  type?: string | null
  service?: { id: number; name?: string | null; type?: string | null; fee?: number | string | null; currency?: { id: number | string; code: string; symbol: string } | null } | null
  fee: number | string | null
  employee_id?: number | null
  employee?: { id: number; name: string } | null
  marketer_id?: number | null
  marketer?: { id: number; name: string } | null
  outsourcing_company_id?: number | null
  outsourcing_percentage?: number | string | null
  outsourcing_company?: { id: number; name: string } | null
  currency_id?: number | null
  currency?: {
    id: number | string
    name?: string | null
    code: string
    symbol: string
    rate: number
  } | null
  status?: string | null
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
  marketer_id: number | null
  outsourcing_company_id: number | null
  outsourcing_percentage: number | null
  marketer_employee: { id: number; name: string } | null
  date: string
  services: CustomerService[]
  created_at: string
  updated_at: string
}

export interface CustomerParams {
  search?: string
  company?: string
  marketer?: string
  marketer_id?: number
  compact?: boolean
}

export interface CreateCustomerData {
  name: string
  company: string
  contact: string
  email?: string | null
  address?: string | null
  tax_number?: string | null

  /** Legacy display label retained for older integrations. */
  marketer?: string | null
  marketer_id?: number | null
  outsourcing_company_id?: number | null
  outsourcing_percentage?: number | null
  date: string
}

export type UpdateCustomerData = Partial<CreateCustomerData>

const emptyCustomer = (): Customer => ({
  id: 0,
  name: '',
  company: '',
  contact: '',
  email: null,
  address: null,
  tax_number: null,
  marketer: '',
  marketer_id: null,
  outsourcing_company_id: null,
  outsourcing_percentage: null,
  marketer_employee: null,
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
    }
    catch (error) {
      return { success: false, data: [], message: errorMessage(error, 'Failed to fetch customers') }
    }
  },

  async getById(id: number): Promise<ApiResponse<Customer>> {
    try {
      const response = await api.get<{ data: Customer }>(`/customers/${id}`)

      return { success: true, data: response.data.data }
    }
    catch (error) {
      return { success: false, data: emptyCustomer(), message: errorMessage(error, 'Failed to fetch customer') }
    }
  },

  async getAllServices(): Promise<ApiResponse<Service[]>> {
    try {
      const response = await api.get<{ data: Service[] }>('/services')

      return { success: true, data: response.data.data }
    }
    catch (error) {
      return { success: false, data: [], message: errorMessage(error, 'Failed to fetch services') }
    }
  },

  async create(data: CreateCustomerData): Promise<ApiResponse<Customer>> {
    try {
      const response = await api.post<{ data: Customer }>('/customers', data)

      return { success: true, data: response.data.data }
    }
    catch (error) {
      return { success: false, data: emptyCustomer(), message: errorMessage(error, 'Failed to create customer') }
    }
  },

  async update(id: number, data: UpdateCustomerData): Promise<ApiResponse<Customer>> {
    try {
      const response = await api.put<{ data: Customer }>(`/customers/${id}`, data)

      return { success: true, data: response.data.data }
    }
    catch (error) {
      return { success: false, data: emptyCustomer(), message: errorMessage(error, 'Failed to update customer') }
    }
  },

  async delete(id: number): Promise<ApiResponse<null>> {
    try {
      await api.delete(`/customers/${id}`)

      return { success: true, data: null }
    }
    catch (error) {
      return { success: false, data: null, message: errorMessage(error, 'Failed to delete customer') }
    }
  },
}

export default CustomerApi
