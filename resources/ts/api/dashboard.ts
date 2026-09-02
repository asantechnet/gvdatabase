import api from './axios'

export interface DashboardTask {
  id: string
  title: string
  board: string
  progress: number
  status: 'not_started' | 'in_progress' | 'nearing_completion' | 'overdue'
  color: 'secondary' | 'primary' | 'success' | 'error'
}

export interface DashboardData {
  capabilities: {
    finance: boolean
    attendance: boolean
    customers: boolean
    invoices: boolean
    asset: boolean
    employees: boolean
    contracts: boolean
    payroll: boolean
    visitors: boolean
    services: boolean
  }
  currency: {
    code: string
    symbol: string
    decimal_places: number
  }
  summary: {
    monthly_income: number | null
    previous_month_income: number | null
    finance_bases?: {
      display_base: string
      monthly_income: Record<string, number>
      previous_month_income: Record<string, number>
    }
    active_tasks: number
    nearing_completion_tasks: number
    completed_tasks_this_month: number
    overdue_tasks: number
    average_task_progress: number
    task_completion_rate: number
    attendance_rate: number | null
    attendance_present: number | null
    attendance_marked: number | null
    monthly_expenses: number | null
    monthly_net_cash: number | null
    customer_count: number | null
    new_customers_this_month: number | null
    open_invoice_count: number | null
    open_invoice_value: number | null
    employee_count: number | null
    asset_asset_value: number | null
  }
  sparklines: {
    income: number[]
    active_tasks: number[]
    attendance: number[]
  }
  weekly_activity: {
    labels: string[]
    created: number[]
    completed: number[]
  }
  secondary_chart: {
    labels: string[]
    rates: number[]
  }
  tertiary_chart: {
    labels: string[]
    values: number[]
    current_value: number
  }
  active_tasks: DashboardTask[]
  financial_chart: {
    labels: string[]
    income: number[]
    expenses: number[]
  } | null
  expense_breakdown: Array<{ label: string; value: number }>
  top_customers: Array<{ id: string; name: string; value: number }>
  receivables: Array<{
    id: string
    number: string
    customer: string
    balance: number
    due_date: string | null
    is_overdue: boolean
  }>
  asset_health: {
    total_items: number
    assigned_items: number
    available_items: number
    attention_items: number
    by_condition: Array<{ label: string; value: number }>
  } | null
  team_health: {
    total_employees: number
    new_this_month: number
    attendance_rate: number | null
    present: number | null
    marked: number | null
  } | null
  attention_items: Array<{
    type: string
    color: 'error' | 'warning'
    icon: string
    count: number
    title: string
  }>
  commercial_overview: {
    customers: number | null
    new_customers_this_month: number | null
    invoice_total: number | null
    collected_total: number | null
    collection_rate: number
    active_services: number | null
    service_deadlines_30_days: number | null
    active_contracts: number | null
    expiring_contracts_30_days: number | null
    draft_contracts: number | null
    visitors_this_month: number | null
  }
  people_overview: {
    employees: number | null
    present_today: number | null
    attendance_marked_today: number | null
    unmarked_today: number | null
    late_minutes_this_month: number | null
    overtime_minutes_this_month: number | null
    pending_leave_requests: number | null
    latest_payroll_period: string | null
    latest_payroll_total: number | null
  }
  kpi_trends: {
    labels: string[]
    invoiceValue: number[]
    customers: number[]
    payroll: number[]
    visitors: number[]
    completedTasks: number[]
  }
  business_trend: {
    labels: string[]
    billed: number[]
    collected: number[]
    spent: number[]
  } | null
  invoice_aging: { labels: string[]; values: number[] } | null
  service_portfolio: Array<{ label: string; value: number }>
  task_mix: Array<{ label: string; value: number }>
  executive_actions: Array<{
    areaKey: string
    titleKey: string
    detailKey: string
    params: Record<string, string | number>
    severity: 'high' | 'medium' | 'low'
    icon: string
    route: string
  }>
}

const DashboardApi = {
  // Compatibility for the legacy API usage example. New application code
  // should use getData so response unwrapping remains inside this contract.
  getStats: () => api.get('/dashboard/stats'),

  async getData(): Promise<DashboardData> {
    const response = await api.get('/dashboard/stats')

    return response.data.data
  },
}

export default DashboardApi
