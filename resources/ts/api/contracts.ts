import api from './axios'
import { cleanListParams, readPage, type ListPage, type ListPageParams } from './pagination'
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

export interface ContractPrintDetailsSnapshot {
  name?: string | null
  father_name?: string | null
  phone?: string | null
  email?: string | null
  details?: Record<string, string | number | null>
  subjects?: string | null
  skills?: string | null
  teachers?: string | null
}

export interface ContractEntitySnapshot {
  service?: BuiltInService | null
  applicant_id?: number | null
  applicant?: Record<string, unknown>
  print_details?: ContractPrintDetailsSnapshot
  [key: string]: unknown
}

export interface ApplicantContractLine {
  id: number
  contract_id: number
  built_in_service?: BuiltInService | null
  service_label?: string
  discount?: number | string | null
  application_fee?: number | string | null
  application_fee_min?: number | string | null
  application_fee_max?: number | string | null
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
  payable_total?: number | string | null
  amount_paid?: number | string | null
  remaining_total?: number | string | null
  payment_status?: string | null
  payment_status_overridden?: boolean
  amount_paid_overridden?: boolean
}

export interface Contract {
  id: number
  service?: BuiltInService | null
  contract_role?: 'primary' | 'extension'
  parent_contract_id?: number | null
  parent_contract_number?: string | null
  /** Derived presentation state: a future-dated extension is `scheduled`. */
  display_status?: string | null
  is_scheduled?: boolean
  /** Negative once the end date has passed. */
  days_remaining?: number | null
  expiry_state?: 'none' | 'expiring' | 'expired'
  payment?: {
    applicable: boolean
    status: string | null
    outstanding: number | string | null
    currency: { code?: string | null; symbol?: string | null } | null
    lines: number
  } | null
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
    entity?: ContractEntitySnapshot
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
  contract_template_version_id?: number
  search?: string
  counterparty?: string
  only_cancelled?: boolean
  sort_by?: 'contract_number' | 'counterparty' | 'start_date' | 'status' | 'created_at'
  sort_direction?: 'asc' | 'desc'
  /** Active contracts ending on or before 30 days from today. */
  expiring?: boolean
}

const normalizeList = (payload: unknown): Contract[] =>
  Array.isArray(payload) ? (payload as Contract[]) : []

const ContractApi = {
  /** Versions available for the current service's register filter. */
  async versionOptions(service: BuiltInService): Promise<Array<{ id: number; name: string; version_number: number }>> {
    const response = await api.get<{ success: boolean; data: Array<{ id: number; name: string; version_number: number }> }>(`/contracts/${service}/version-options`)

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  async serviceOptions(service: BuiltInService): Promise<ServiceContractOptions> {
    const response = await api.get<{ success: boolean; data: ServiceContractOptions }>(`/contracts/${service}/options`)

    return response.data.data
  },
  async createService(service: BuiltInService, data: ServiceContractCreateData): Promise<Contract> {
    const response = await api.post<{ success: boolean; data: Contract }>(`/contracts/${service}`, data)

    return response.data.data
  },
  async updateService(service: BuiltInService, id: number, data: ServiceContractCreateData): Promise<Contract> {
    const response = await api.put<{ success: boolean; data: Contract }>(`/contracts/${service}/${id}`, data)

    return response.data.data
  },
  async getAll(params?: ContractParams): Promise<Contract[]> {
    const response = await api.get<{ success: boolean; data: Contract[] }>('/contracts', { params: { ...params, only_cancelled: params?.only_cancelled === undefined ? undefined : Number(params.only_cancelled) } })

    return normalizeList(response.data.data)
  },

  /** One page of a contract list. */
  async listPage(params?: ContractParams & ListPageParams): Promise<ListPage<Contract>> {
    const response = await api.get('/contracts', {
      params: cleanListParams({
        ...params,
        page: params?.page,
        per_page: params?.perPage,
        only_cancelled: params?.only_cancelled === undefined ? undefined : Number(params.only_cancelled),
        expiring: params?.expiring === undefined ? undefined : Number(params.expiring),
      }),
    })

    return readPage<Contract>(response.data)
  },

  /** The counterparties already on record, for the list filter. */
  async counterparties(): Promise<string[]> {
    const response = await api.get<{ success: boolean; data: string[] }>('/contracts/counterparties')

    return Array.isArray(response.data.data) ? response.data.data : []
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
  responsible_employees: Array<{ id: number; name: string }>
  counselor_employees: Array<{ id: number; name: string }>

  currencies: Array<{ id: number; code: string; rate: number | string }>
  languages: Array<{ id: number; name: string }>
  employees: Array<{ id: number; name: string }>
}

export interface ServiceContractCreateData {
  /** Academy resolves its hidden template version on the server. */
  contract_template_version_id?: number | null
  parent_contract_id?: number | null
  name?: string
  father_name?: string | null
  father_phone?: string | null
  backup_phone?: string | null
  contact?: string | null
  email?: string | null
  address?: string | null
  tax_number?: string | null
  responsible_employee_ids?: number[]
  responsible_employee_id?: number | null
  application_fee?: number
  application_fee_min?: number
  application_fee_max?: number
  visa_fee?: number
  paid_fee?: number
  // A portal login issued with the contract, using the credentials the form
  // generated and displayed before saving.
  create_portal_user?: boolean
  portal_user_email?: string | null
  portal_user_password?: string | null
  student_id?: string | null
  booking_fee?: number
  service_fee?: number
  remote_fee?: number
  scholarship_id?: number | null
  class_id?: number | null
  fee: number
  discount: number
  currency_id: number
  currency_rate: number
  /** Academy omits the language; other services send it. */
  language_id?: number | null
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
  teacher_ids?: number[]
}

export function serviceContractApplicantFields(service: BuiltInService, applicant: ApplicantPayload, canAssignResponsible = false): Partial<ServiceContractCreateData> {
  const { service: omitted, ...details } = applicantServicePayload({ ...applicant, service })
  const identity: Partial<ServiceContractCreateData> = {}
  const fields: Array<keyof ApplicantPayload> = service === 'private'
    ? ['name', 'father_name', 'father_phone', 'contact', 'email', 'address', 'notes', 'responsible_employee_id']
    : service === 'scholarship'
      ? ['name', 'father_name', 'father_phone', 'contact', 'notes', 'responsible_employee_id']
      : service === 'test_offer'
        ? ['name', 'backup_phone', 'contact', 'responsible_employee_id']
        : ['name', 'contact', 'responsible_employee_id']
  for (const field of fields) {
    const value = applicant[field]
    if (value !== undefined)
      Object.assign(identity, { [field]: value === '' ? null : value })
  }
  if (canAssignResponsible && ['private', 'scholarship'].includes(service) && applicant.responsible_employee_ids !== undefined)
    identity.responsible_employee_ids = applicant.responsible_employee_ids

  return { ...identity, ...details }
}
