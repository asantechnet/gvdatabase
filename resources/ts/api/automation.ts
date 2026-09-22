import api from './axios'

export interface BonusRules {
  minimum_completed_tasks: number
  amount_per_completed_task: number
  minimum_cases_won: number
  amount_per_case_won: number
  task_quality_weighting_enabled: boolean
  maximum_monthly_bonus: number
  excluded_employee_ids: number[]
  excluded_office_ids: number[]
}

export interface BonusRuleSet { id: number; name: string; version: number; is_active: boolean; rules: BonusRules; updated_at: string }
export interface AutomationRun { id: number; job: string; period_key: string; status: 'running' | 'completed' | 'failed'; attempt: number; started_at: string; finished_at: string | null; parameters: Record<string, unknown>; result: Record<string, unknown> | null; error_message: string | null }

const AutomationApi = {
  async getBonusRules(): Promise<BonusRuleSet> {
    const response = await api.get<{ data: BonusRuleSet }>('/automation/bonus-rules')

    return response.data.data
  },
  async updateBonusRules(rules: BonusRules): Promise<BonusRuleSet> {
    const response = await api.put<{ data: BonusRuleSet }>('/automation/bonus-rules', rules)

    return response.data.data
  },
  async getRuns(params?: { job?: string; status?: string }): Promise<AutomationRun[]> {
    const response = await api.get<{ data: AutomationRun[] }>('/automation/runs', { params })

    return response.data.data
  },
}

export default AutomationApi
