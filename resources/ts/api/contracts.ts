import api from './axios'
import type { ContractType } from './contractType'

export type ContractStatus = 'draft' | 'active' | 'expired' | 'terminated' | 'cancelled'

export interface ContractPartySnapshot {
  our_company?: {
    name?: string | null
    short_name?: string | null
    email?: string | null
    phone?: string | null
    address?: string | null
    business_licence?: string | null
  }
  counterparty: {
    name?: string | null
    company?: string | null
    email?: string | null
    phone?: string | null
    address?: string | null
    representative?: { name?: string | null; title?: string | null } | null
  }
  [key: string]: unknown
}

export interface Contract {
  id: number
  contract_number: string | null
  contract: string
  status: ContractStatus
  start_date: string | null
  end_date: string | null
  counterparty: string | null
  counterparty_company?: string | null
  counterparty_email?: string | null
  counterparty_contact?: string | null
  counterparty_address?: string | null
  is_cancelled: boolean
  cancelled_at?: string | null
  template_id: number | null
  contract_type_id?: number | null
  employee_id?: number | null
  customer_id?: number | null
  company_id?: number | null
  type_slug?: string | null
  type?: string | null
  cover_text?: string | null
  content?: string | null
  is_custom?: boolean
  snapshot_data?: { parties?: Record<string, unknown>; entity?: Record<string, unknown> } | null
  parties?: ContractPartySnapshot | null
  employee?: Record<string, unknown> | null
  customer?: Record<string, unknown> | null
  company?: Record<string, unknown> | null
  contract_type?: ContractType | null
  contract_template?: {
    id: number
    name: string
    type: string | null
    type_slug?: string | null
    cover_text: string | null
    content: string | null
  }
}

export interface ContractCreateData {
  template_id?: number | null
  contract_type_id?: number | null
  employee_id?: number | null
  customer_id?: number | null
  company_id?: number | null
  is_custom?: boolean
  name?: string
  cover_text?: string | null
  content?: string
  status: ContractStatus
  start_date: string
  end_date?: string | null
}

export interface ContractUpdateData extends Partial<Omit<ContractCreateData, 'status'>> {
  status?: ContractStatus
}

export interface ContractParams {
  audience?: 'customers' | 'employees' | 'companies' | 'other'
  type_slug?: string
  status?: ContractStatus
  search?: string
  only_cancelled?: boolean
}

const normalizeList = (payload: unknown): Contract[] =>
  Array.isArray(payload) ? (payload as Contract[]) : []

const ContractApi = {
  async getAll(params?: ContractParams): Promise<Contract[]> {
    const response = await api.get<{ success: boolean; data: Contract[] }>('/contracts', { params })
    return normalizeList(response.data.data)
  },

  async getById(id: number): Promise<Contract> {
    const response = await api.get<{ success: boolean; data: Contract }>(`/contracts/${id}`)
    return response.data.data
  },

  async create(data: ContractCreateData): Promise<Contract> {
    const response = await api.post<{ success: boolean; data: Contract }>('/contracts', data)
    return response.data.data
  },

  async update(id: number, data: ContractUpdateData): Promise<Contract> {
    const response = await api.put<{ success: boolean; data: Contract }>(`/contracts/${id}`, data)
    return response.data.data
  },

  async cancel(id: number): Promise<Contract> {
    const response = await api.put<{ success: boolean; data: Contract }>(`/contracts/${id}/cancel`)
    return response.data.data
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/contracts/${id}`)
  },
}

export default ContractApi
