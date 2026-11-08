import api from './axios'

export type AttendanceStatus = 'present' | 'absent' | 'off_day'

export interface Attendance {
  id: number
  staff_id: number
  name: string
  role: string
  office_id: number | null
  office: string | null
  date: string
  status: AttendanceStatus
  off_day_id: number | null
  leave_request_id: number | null
  source: 'manual' | 'self_service' | 'holiday' | 'leave' | 'missing_policy' | null
  is_off_day: boolean
  off_day_reason: string | null
  leave_days: number
  off_day_days: number
  timestamp: string
  check_in_time: string | null
  check_out_time: string | null
  checked_in_at: string | null
  checked_out_at: string | null
  check_in_state: 'early' | 'on_time' | 'late' | null
  check_out_state: 'early_departure' | 'on_time' | 'overtime' | null
  break_minutes: number
  gross_minutes: number | null
  net_worked_minutes: number | null
  required_minutes: number | null
  late_arrival_minutes: number
  early_departure_minutes: number
  overtime_minutes: number
  missing_punch_state: 'none' | 'check_in' | 'check_out' | 'both' | 'invalid'
  is_overnight_shift: boolean
  daily_report_exists: boolean
  timezone: string
}

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

export interface UnmarkedStaff { id: number; name: string; position: string; role?: string }
export interface AttendanceParams { start_date?: string; end_date?: string; employee_id?: number; office_id?: number; status?: AttendanceStatus | 'incomplete' }
export interface CreateAttendanceData {
  staff_id: number
  date: string
  status: 'present' | 'absent'
  check_in_time?: string | null
  check_out_time?: string | null
  break_minutes?: number | null
}

export interface AttendanceOffDay {
  is_off_day: boolean
  reason: string | null
  off_day_id: number | null
}

export interface UpdateAttendanceData {
  status: 'present' | 'absent'
  check_in_time?: string | null
  check_out_time?: string | null
  break_minutes?: number | null
}

const AttendanceApi = {
  async getAll(params?: AttendanceParams): Promise<Attendance[]> {
    const response = await api.get<{ data: Attendance[] }>('/attendances', { params })

    return response.data.data
  },
  async getMine(params?: Pick<AttendanceParams, 'start_date' | 'end_date'>): Promise<Attendance[]> {
    const response = await api.get<{ data: Attendance[] }>('/my-work-history', { params })

    return response.data.data
  },
  async getOwnDailyReport(attendanceId: number): Promise<DailyReport> {
    const response = await api.get<{ data: DailyReport }>(`/my-work-history/${attendanceId}/daily-report`)

    return response.data.data
  },
  async getUnmarkedStaff(date: string): Promise<UnmarkedStaff[]> {
    const response = await api.get<{ data: UnmarkedStaff[] }>('/attendances/unmarked-staff', { params: { date } })

    return response.data.data
  },
  async getOffDay(date: string): Promise<AttendanceOffDay> {
    const response = await api.get<{ data: AttendanceOffDay }>('/attendances/off-day', { params: { date } })

    return response.data.data
  },
  async getDailyReport(attendanceId: number): Promise<DailyReport> {
    const response = await api.get<{ data: DailyReport }>(`/attendances/${attendanceId}/daily-report`)

    return response.data.data
  },
  async create(data: CreateAttendanceData): Promise<Attendance> {
    const response = await api.post<{ data: Attendance }>('/attendances', data)

    return response.data.data
  },
  async update(id: number, data: UpdateAttendanceData): Promise<Attendance> {
    const response = await api.put<{ data: Attendance }>(`/attendances/${id}`, data)

    return response.data.data
  },
  async correctCheckout(id: number, data: { date: string; time: string }): Promise<Attendance> {
    const response = await api.post<{ data: Attendance }>(`/attendances/${id}/checkout-correction`, data)

    return response.data.data
  },
  async delete(id: number): Promise<void> {
    await api.delete(`/attendances/${id}`)
  },
}

export default AttendanceApi
