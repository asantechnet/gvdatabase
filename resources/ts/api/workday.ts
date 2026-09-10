import api from './axios'

export type WorkdayState = 'unavailable' | 'not_started' | 'active' | 'awaiting_report' | 'completed' | 'blocked'

export interface DailyReport {
  id: number
  attendanceId: number
  reportDate: string
  summary: string
  achievements: string | null
  challenges: string | null
  nextDayPlan: string | null
  supportNeeded: string | null
  submittedAt: string
}

export interface WorkdayStatus {
  eligible: boolean
  state: WorkdayState
  timezone: string
  attendance: null | {
    id: number
    date: string
    checkedInAt: string | null
    checkedOutAt: string | null
    checkInTime: string | null
    checkOutTime: string | null
    workedMinutes: number | null
  }
  report: DailyReport | null
}

export interface EndWorkdayData {
  summary: string
  achievements?: string | null
  challenges?: string | null
  nextDayPlan?: string | null
  supportNeeded?: string | null
}

const WorkdayApi = {
  async status(): Promise<WorkdayStatus> {
    const response = await api.get<{ data: WorkdayStatus }>('/workday/status')

    return response.data.data
  },
  async start(): Promise<WorkdayStatus> {
    const response = await api.post<{ data: WorkdayStatus }>('/workday/start')

    return response.data.data
  },
  async end(data: EndWorkdayData): Promise<WorkdayStatus> {
    const response = await api.post<{ data: WorkdayStatus }>('/workday/end', data)

    return response.data.data
  },
}

export default WorkdayApi
