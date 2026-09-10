import api from './axios'

export interface Company {
  id: number
  legal_name: string
  trade_name: string | null
  registration_number: string | null
  tax_number: string | null
  national_id: string | null
  legal_form: string | null
  email: string | null
  phone: string | null
  website: string | null
  country: string | null
  province: string | null
  city: string | null
  postal_code: string | null
  address_line: string | null
  industry: string | null
  description: string | null
  representative_name: string | null
  representative_title: string | null
  representative_phone: string | null
  representative_email: string | null
  is_active: boolean
  contracts_count?: number
  created_at?: string
  updated_at?: string
}

export type CompanyCreateData = Partial<Omit<Company, 'id' | 'created_at' | 'updated_at' | 'contracts_count'>> & {
  legal_name: string
}

export type CompanyUpdateData = Partial<Omit<CompanyCreateData, 'legal_name'>>

export interface CompanyParams {
  search?: string
  include_inactive?: boolean
}

const CompanyApi = {
  async getAll(params?: CompanyParams): Promise<Company[]> {
    const response = await api.get<{ success: boolean; data: Company[] }>('/companies', { params })

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  async getById(id: number): Promise<Company> {
    const response = await api.get<{ success: boolean; data: Company }>(`/companies/${id}`)

    return response.data.data
  },

  async create(data: CompanyCreateData): Promise<Company> {
    const response = await api.post<{ success: boolean; data: Company }>('/companies', data)

    return response.data.data
  },

  async update(id: number, data: CompanyUpdateData): Promise<Company> {
    const response = await api.put<{ success: boolean; data: Company }>(`/companies/${id}`, data)

    return response.data.data
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/companies/${id}`)
  },
}

export default CompanyApi
