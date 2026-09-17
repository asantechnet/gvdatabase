import api from './axios'

export type EducationalServiceType = 'scholarships' | 'test-offers'
export type BonusSourceType = 'contract' | 'scholarship' | 'test_offer'
export type BonusStatus = 'pending' | 'approved' | 'paid' | 'void'

export interface NamedRecord { id: number; name: string }
export interface ServiceApplicant { id: number; customer_id: number; customer?: NamedRecord }
export interface EducationalBonusSource {
  id: number
  source_type: string
  source_id: number
  source_amount: number | string
  bonus_amount: number | string
  rule_source: string | null
  status: BonusStatus
  employee?: NamedRecord
  customer?: NamedRecord
  applicant?: ServiceApplicant | null
}
export interface EducationalService {
  id: number
  customer_id: number
  applicant_id?: number | null
  employee_id: number
  currency_id: number
  package_type_id?: number | null
  date: string
  fee: number | string
  status: 'active' | 'completed' | 'cancelled'
  customer?: NamedRecord
  applicant?: ServiceApplicant | null
  employee?: NamedRecord
  currency?: NamedRecord & { code: string; symbol: string | null }
  package_type?: NamedRecord | null
  bonus_source?: EducationalBonusSource | null
}
export interface EducationalServiceInput {
  customer_id: number | null
  applicant_id: number | null
  employee_id: number | null
  currency_id: number | null
  package_type_id: number | null
  date: string
  fee: number | null
  status: EducationalService['status']
  bonus_amount?: number | null
  bonus_rule_source?: string | null
}
export interface EducationalServiceOptions {
  customers: NamedRecord[]
  employees: NamedRecord[]
  applicants: ServiceApplicant[]
  currencies: Array<NamedRecord & { code: string; symbol: string | null; is_base: boolean }>
  package_types: NamedRecord[]
  contracts: Array<{ id: number; contract_number: string | null; name: string | null; employee_id: number; customer_id: number; applicant_id: number | null }>
}

const unwrap = <T>(response: { data: { data: T } }) => response.data.data

export default {
  list: async (type: EducationalServiceType) => unwrap<EducationalService[]>(await api.get(`/educational-services/${type}`)),
  create: async (type: EducationalServiceType, data: EducationalServiceInput) => unwrap<EducationalService>(await api.post(`/educational-services/${type}`, data)),
  update: async (type: EducationalServiceType, id: number, data: Partial<EducationalServiceInput>) => unwrap<EducationalService>(await api.put(`/educational-services/${type}/${id}`, data)),
  remove: async (type: EducationalServiceType, id: number) => api.delete(`/educational-services/${type}/${id}`),
  options: async () => unwrap<EducationalServiceOptions>(await api.get('/educational-services/options')),
  listBonusSources: async () => unwrap<EducationalBonusSource[]>(await api.get('/educational-bonus-sources')),
  createContractBonus: async (data: { source_type: 'contract'; source_id: number; source_amount: number; bonus_amount?: number; rule_source?: string }) => unwrap<EducationalBonusSource>(await api.post('/educational-bonus-sources', data)),
  updateBonus: async (id: number, data: Partial<Pick<EducationalBonusSource, 'bonus_amount' | 'rule_source' | 'status'>>) => unwrap<EducationalBonusSource>(await api.put(`/educational-bonus-sources/${id}`, data)),
}
