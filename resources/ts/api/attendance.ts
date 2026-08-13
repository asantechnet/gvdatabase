import api from './axios'

export interface Attendance {
  id: number
  staff_id: number
  name: string
  role: string
  office_id: number | null
  office: string | null
  date: string
  status: 'present' | 'absent' | 'paid-off-day' | 'unpaid-off-day'
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
  missing_punch_state: 'none' | 'check_in' | 'check_out' | 'both'
  is_overnight_shift: boolean
  timezone: string
}

export interface UnmarkedStaff { id: number; name: string; position: string; role?: string }
export interface AttendanceParams { start_date?: string; end_date?: string; employee_id?: number; office_id?: number; status?: Attendance['status'] | 'incomplete' }
export interface CreateAttendanceData {
  staff_id: number
  date: string
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
  async getUnmarkedStaff(date: string): Promise<UnmarkedStaff[]> {
    const response = await api.get<{ data: UnmarkedStaff[] }>('/attendances/unmarked-staff', { params: { date } })
    return response.data.data
  },
  async create(data: CreateAttendanceData): Promise<Attendance> {
    const response = await api.post<{ data: Attendance }>('/attendances', data)
    return response.data.data
  },
  async delete(id: number): Promise<void> {
    await api.delete(`/attendances/${id}`)
  },
}

export default AttendanceApi
