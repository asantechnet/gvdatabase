import api from './axios'
import type { ApplicantStage, BuiltInService } from './applicants'

/**
 * One contracted service line is one case. The read payload contains only this
 * line's stage, history, delivery tasks and receipts; sibling lines on the same
 * applicant are never part of it.
 */
export interface ServiceCaseHistory {
  id: number
  applicant_contract_id: number | null
  old_stage: { id: number; key: string; name: string } | null
  new_stage: { id: number; key: string; name: string } | null
  note?: string | null
  changed_at: string
  changer?: { id: number; name: string } | null
}

export interface ServiceCaseTask {
  id: number
  applicant_contract_id: number | null
  title: string
  progress: number
  priority?: string | null
  due_date?: string | null
  completed_at?: string | null
}

export interface ServiceCaseIncome {
  id: number
  applicant_contract_id: number | null
  receipt_number?: string | null
  amount: number | string
  date?: string | null
  description?: string | null
}

export interface ServiceCase {
  id: number
  contract_id: number
  built_in_service: BuiltInService | null
  service_label: string
  service_label_key?: string | null
  applicant_id: number
  applicant: {
    id: number
    name: string
    contact?: string | null
    email?: string | null
    father_name?: string | null
    father_phone?: string | null
  } | null
  fee: number | string
  discount: number | string
  payable_total: number
  amount_paid: number | string
  remaining_total: number
  payment_status: string
  payment_status_label: string
  currency_id: number
  currency?: { id: number; code: string } | null
  current_stage_id?: number | null
  current_stage: { id: number; key: string; name: string; is_terminal?: boolean; allowed_next_keys?: string[] } | null
  allowed_next_stages: ApplicantStage[]
  stage_history: ServiceCaseHistory[]
  tasks: ServiceCaseTask[]
  incomes: ServiceCaseIncome[]
  contract_number?: string | null
  status?: string | null
  contract_role?: string | null
  parent_contract_number?: string | null
  is_extension?: boolean
  created_at?: string | null
  updated_at?: string | null
}

export default {
  get: async (line: number) => (await api.get(`/applicant-contracts/${line}`)).data.data as ServiceCase,
  changeStage: async (line: number, stage_id: number, note?: string) => {
    await api.post(`/applicant-contracts/${line}/change-stage`, { stage_id, note })
  },
}
