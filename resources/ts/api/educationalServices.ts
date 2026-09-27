import api from './axios'

export type EducationalServiceType = 'scholarships' | 'test-offers'
export type BonusSourceType = 'contract' | 'service'
export type BonusStatus = 'pending' | 'approved' | 'paid' | 'void'

export interface NamedRecord { id: number; name: string }
export interface ServiceApplicant extends NamedRecord {}
export interface EducationalBonusSource {
  id: number
  source_type: string
  source_id: number
  source_amount: number | string
  bonus_amount: number | string
  rule_source: string | null
  status: BonusStatus
  employee?: NamedRecord
  applicant?: ServiceApplicant | null
}
export interface EducationalService {
  id: number
  service_id: number
  applicant_id: number
  country_id?: number | null
  education_level_id?: number | null
  language_id?: number | null
  employee_id: number
  currency_id: number
  package_type_id?: number | null
  date: string
  fee: number | string
  status: 'active' | 'completed' | 'cancelled'
  applicant?: ServiceApplicant | null
  employee?: NamedRecord
  currency?: NamedRecord & { code: string; symbol: string | null }
  package_type?: NamedRecord | null
  country?: NamedRecord | null
  education_level?: NamedRecord | null
  language?: NamedRecord | null
  bonus_source?: EducationalBonusSource | null
  /** The contract created for this sale (1 service ↔ 1 contract). */
  contract_id?: number | null
  contract_number?: string | null
  contract_role?: 'primary' | 'extension'
  parent_contract_id?: number | null
  parent_contract_number?: string | null
}
export interface EducationalServiceInput {
  service_id: number | null
  applicant_id: number | null
  country_id: number | null
  education_level_id: number | null
  language_id: number | null
  employee_id: number | null
  currency_id: number | null
  package_type_id: number | null
  date: string
  fee: number | null
  status: EducationalService['status']
  bonus_amount?: number | null
  bonus_rule_source?: string | null
  /** Allow a repeat sale to become an extension of the existing contract. */
  extend?: boolean
}
export interface EducationalServiceOptions {
  employees: NamedRecord[]
  applicants: ServiceApplicant[]
  currencies: Array<NamedRecord & { code: string; symbol: string | null; is_base: boolean }>
  package_types: Array<NamedRecord & { services: Array<{ id: number; type: string; category: 'scholarship' | 'test_offer' | 'general' }> }>
  services: Array<{ id: number; type: string; category: 'scholarship' | 'test_offer'; fee: number | string; currency_id: number; contract_template?: NamedRecord | null }>
  countries: NamedRecord[]
  education_levels: NamedRecord[]
  languages: NamedRecord[]
  contracts: Array<{ id: number; contract_number: string | null; name: string | null; employee_id: number; applicant_id: number | null }>
}
export interface EducationalServicePage {
  data: EducationalService[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const unwrap = <T>(response: { data: { data: T } }) => response.data.data

export default {
  list: async (type: EducationalServiceType, page = 1, perPage = 25) => unwrap<EducationalServicePage>(await api.get(`/educational-services/${type}`, { params: { page, per_page: perPage } })),
  create: async (type: EducationalServiceType, data: EducationalServiceInput) => unwrap<EducationalService>(await api.post(`/educational-services/${type}`, data)),
  update: async (type: EducationalServiceType, id: number, data: Partial<EducationalServiceInput>) => unwrap<EducationalService>(await api.put(`/educational-services/${type}/${id}`, data)),
  remove: async (type: EducationalServiceType, id: number) => api.delete(`/educational-services/${type}/${id}`),
  options: async () => unwrap<EducationalServiceOptions>(await api.get('/educational-services/options')),
  listBonusSources: async () => unwrap<EducationalBonusSource[]>(await api.get('/educational-bonus-sources')),
  createContractBonus: async (data: { source_type: 'contract'; source_id: number; source_amount: number; bonus_amount?: number; rule_source?: string }) => unwrap<EducationalBonusSource>(await api.post('/educational-bonus-sources', data)),
  updateBonus: async (id: number, data: Partial<Pick<EducationalBonusSource, 'bonus_amount' | 'rule_source' | 'status'>>) => unwrap<EducationalBonusSource>(await api.put(`/educational-bonus-sources/${id}`, data)),
}
