import api from './axios'
import type { ContractType } from './contractType'
import { applicantServicePayload, type Applicant, type ApplicantPayload, type BuiltInService } from './applicants'

export type ContractStatus = 'draft' | 'active' | 'completed' | 'terminated' | 'cancelled'

export interface ContractPreviewInput {
  template_id: number
  contract_template_version_id: number | null
  country_id: number | null
  language_id: number | null
  employee_id: number | null
  applicant_id: number | null
  start_date: string
  end_date: string | null
  company_signatory_name: string | null
}

export async function previewContract(input: ContractPreviewInput): Promise<string> {
  const response = await api.post<{ data: { content: string } }>('/contracts/preview', input, {
    headers: { 'X-Suppress-Error-Notification': 'true' },
  })

  return response.data.data.content
}

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
    email?: string | null
    phone?: string | null
    address?: string | null
  }
  [key: string]: unknown
}

export interface ApplicantContractLine {
  id: number
  contract_id: number
  built_in_service?: BuiltInService | null
  service_label?: string
  discount?: number | string | null
  application_fee?: number | string | null
  visa_fee?: number | string | null
  booking_fee?: number | string | null
  service_fee?: number | string | null
  remote_fee?: number | string | null
  currency_rate?: number | string | null
  fee?: number | string | null
  currency_id?: number | null
  currency?: { id?: number; code?: string | null; symbol?: string | null } | null
  applicant_id?: number | null
  notes?: string | null
}

export interface Contract {
  id: number
  service?: BuiltInService | null
  contract_role?: 'primary' | 'extension'
  parent_contract_id?: number | null
  contract_number: string | null
  contract: string
  status: ContractStatus
  start_date: string | null
  end_date?: string | null
  company_signatory_name?: string | null
  services?: ApplicantContractLine[]
  counterparty: string | null
  counterparty_company?: string | null
  counterparty_email?: string | null
  counterparty_contact?: string | null
  counterparty_address?: string | null
  is_cancelled: boolean
  cancelled_at?: string | null
  template_id: number | null
  contract_template_version_id?: number | null
  contract_country_id?: number | null
  contract_language_id?: number | null
  contract_type_id?: number | null
  employee_id?: number | null
  sold_by_employee_id?: number | null
  applicant_id?: number | null
  type_slug?: string | null
  type?: string | null
  cover_text?: string | null
  content?: string | null
  is_custom?: boolean
  position_id?: number | null
  snapshot_data?: {
    parties?: Record<string, unknown>
    entity?: Record<string, unknown>
    template_version?: { id: number; name?: string | null; version_number: number; country_id?: number | null; country_name?: string | null; language_id?: number | null; language_name?: string | null; language_code?: string | null } | null
    document_design?: { letterhead_url?: string | null; cover_design_url?: string | null; safe_x?: number; safe_y?: number; safe_width?: number; safe_height?: number } | null
    renewal?: { source_contract_id: number; source_contract_number?: string | null }
  } | null
  parties?: ContractPartySnapshot | null
  employee?: Record<string, unknown> | null
  applicant?: Applicant | null
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
  contract_template_version_id?: number | null
  /** Set when the new contract is an extension of an existing agreement. */
  parent_contract_id?: number | null
  country_id?: number | null
  language_id?: number | null
  employee_id?: number | null
  applicant_id?: number | null
  name?: string
  cover_text?: string | null
  content?: string
  status: ContractStatus
  start_date: string
  end_date?: string | null
  company_signatory_name?: string | null
}

export interface ContractUpdateData extends Partial<Omit<ContractCreateData, 'status'>> {
  status?: ContractStatus
}

export interface ContractParams {
  applicant_id?: number
  service?: BuiltInService
  audience?: 'customers' | 'employees' | 'other'
  type_slug?: string
  status?: ContractStatus
  date_from?: string
  date_to?: string
  search?: string
  only_cancelled?: boolean
}

const normalizeList = (payload: unknown): Contract[] =>
  Array.isArray(payload) ? (payload as Contract[]) : []

const ContractApi = {
  async serviceOptions(service: BuiltInService): Promise<ServiceContractOptions> {
    const response = await api.get<{ success: boolean; data: ServiceContractOptions }>(`/contracts/${service}/options`)

    return response.data.data
  },
  async createService(service: BuiltInService, data: ServiceContractCreateData): Promise<Contract> {
    const response = await api.post<{ success: boolean; data: Contract }>(`/contracts/${service}`, data)

    return response.data.data
  },
  async getAll(params?: ContractParams): Promise<Contract[]> {
    const response = await api.get<{ success: boolean; data: Contract[] }>('/contracts', { params: { ...params, only_cancelled: params?.only_cancelled === undefined ? undefined : Number(params.only_cancelled) } })

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

export interface ServiceContractOptions {
  template: import('./contractTemplate').ContractTemplate | null
  admission_employees: Array<{ id: number; name: string }>
  counselor_employees: Array<{ id: number; name: string }>

  currencies: Array<{ id: number; code: string; rate: number | string }>
  languages: Array<{ id: number; name: string }>
  employees: Array<{ id: number; name: string }>
}

export interface ServiceContractCreateData {
  parent_contract_id?: number | null
  name?: string
  father_name?: string | null
  father_phone?: string | null
  backup_phone?: string | null
  contact?: string | null
  email?: string | null
  address?: string | null
  tax_number?: string | null
  admission_employee_id?: number | null
  responsible_employee_id?: number | null
  application_fee?: number
  visa_fee?: number
  booking_fee?: number
  service_fee?: number
  remote_fee?: number
  scholarship_id?: number | null
  class_id?: number | null
  fee: number
  discount: number
  currency_id: number
  currency_rate: number
  language_id: number
  start_date: string
  notes?: string | null
  company_signatory_name?: string | null
  passport_id?: string | null
  grade_score?: number | string | null
  country_id?: number | null
  university_id?: number | null
  education_level_id?: number | null
  semester_id?: number | null
  contract_type_id?: number | null
  university_fee?: number | string | null
  favorite_subject_ids?: number[]
  test_id?: number | null
  skill_ids?: number[]
  program_id?: number | null
  time_id?: number | null
  duration_id?: number | null
  teacher_ids?: number[]
}

export function serviceContractApplicantFields(service: BuiltInService, applicant: ApplicantPayload): Partial<ServiceContractCreateData> {
  const { service: omitted, ...details } = applicantServicePayload({ ...applicant, service })
  const identity: Partial<ServiceContractCreateData> = {}
  const fields = ['name', 'father_name', 'father_phone', 'backup_phone', 'notes', 'contact', 'email', 'address', 'tax_number', 'admission_employee_id', 'responsible_employee_id'] as const
  for (const field of fields) {
    const value = applicant[field]
    if (value !== undefined)
      Object.assign(identity, { [field]: value === '' ? null : value })
  }

  return { ...identity, ...details }
}
