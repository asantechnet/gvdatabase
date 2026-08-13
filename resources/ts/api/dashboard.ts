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
  }
  currency: {
    code: string
    symbol: string
    decimal_places: number
  }
  summary: {
    monthly_income: number | null
    previous_month_income: number | null
    active_tasks: number
    nearing_completion_tasks: number
    completed_tasks_this_month: number
    overdue_tasks: number
    average_task_progress: number
    task_completion_rate: number
    attendance_rate: number | null
    attendance_present: number | null
    attendance_marked: number | null
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
