import api from './axios'

/**
 * An employee's request for a day (or half day) away from work.
 *
 * `off_day_days` is the part covered by the employee's annual allowance (or an
 * explicit exception). Once granted, the rest of `duration_days` is an absence.
 */
export interface LeaveRequest {
  id: number
  employee_id: number
  date: string
  year: number
  duration_days: 0.5 | 1
  reason: string
  is_granted: boolean
  off_day_days: number
  absent_days: number
  off_day_override: boolean
  treatment: 'pending' | 'off_day' | 'partial' | 'absent'
  approval_note: string | null
  granted_at: string | null
  approved_by?: number | null
  approvedBy?: { id: number; name: string } | null
  employee?: { id: number; name: string; position: string | null }
}

export interface LeaveRequestLedger {
  data: LeaveRequest[]
  allowance: number
  allowances?: Record<number, number>
  used: number
  remaining: number
  pending: number
  year: number
}

export type LeaveRequestPayload = Pick<LeaveRequest, 'date' | 'duration_days' | 'reason'>

const LeaveRequestApi = {
  async getAll(employeeId: number, year: number): Promise<LeaveRequestLedger> {
    const response = await api.get(`/employees/${employeeId}/leave-requests`, { params: { year } })

    return response.data as LeaveRequestLedger
  },
  async create(employeeId: number, data: LeaveRequestPayload): Promise<LeaveRequest> {
    const response = await api.post(`/employees/${employeeId}/leave-requests`, data)

    return response.data.data as LeaveRequest
  },
  async createRange(employeeId: number, data: { start_date: string; end_date: string; reason: string; duration_days?: 0.5 | 1 }): Promise<LeaveRequest[]> {
    const response = await api.post(`/employees/${employeeId}/leave-requests`, data)

    return response.data.data as LeaveRequest[]
  },
  async update(employeeId: number, id: number, data: LeaveRequestPayload): Promise<LeaveRequest> {
    const response = await api.put(`/employees/${employeeId}/leave-requests/${id}`, data)

    return response.data.data as LeaveRequest
  },
  async delete(employeeId: number, id: number): Promise<void> {
    await api.delete(`/employees/${employeeId}/leave-requests/${id}`)
  },
  async mine(year: number): Promise<LeaveRequestLedger> {
    const response = await api.get('/my-leave-requests', { params: { year } })

    return response.data
  },
  async requestRange(data: { start_date: string; end_date: string; duration_days?: 0.5 | 1; reason: string }): Promise<LeaveRequest[]> {
    const response = await api.post('/my-leave-requests', data)

    return response.data.data
  },
  async getAllRequests(year: number): Promise<{ data: LeaveRequest[]; allowance: number; allowances: Record<number, number> }> {
    const response = await api.get('/leave-requests', { params: { year } })

    return {
      data: response.data.data,
      allowance: Number(response.data.allowance ?? 0),
      allowances: response.data.allowances ?? {},
    }
  },
  async grant(ids: number[], forceOffDay = false, approvalNote?: string): Promise<{ data: LeaveRequest[]; exceeded: number }> {
    const response = await api.patch('/leave-requests/grant', { ids, force_off_day: forceOffDay, approval_note: approvalNote })

    return response.data
  },
}

export default LeaveRequestApi
