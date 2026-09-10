import api from './axios'

export interface PaidTimeOff {
  id: number
  employee_id: number
  date: string
  year: number
  duration_days: 0.5 | 1
  reason: string
  is_granted: boolean
  is_paid: boolean | null
  paid_days: number | null
  paid_override: boolean
  approval_note: string | null
  granted_at: string | null
  approved_by?: number | null
  approvedBy?: { id: number; name: string } | null
  employee?: { id: number; name: string; position: string | null }
}

export interface PaidTimeOffLedger {
  data: PaidTimeOff[]
  allowance: number
  used: number
  remaining: number
  pending: number
  year: number
}

export type PaidTimeOffPayload = Pick<PaidTimeOff, 'date' | 'duration_days' | 'reason'>

const PaidTimeOffApi = {
  async getAll(employeeId: number, year: number): Promise<PaidTimeOffLedger> {
    const response = await api.get(`/employees/${employeeId}/leaves`, { params: { year } })

    return response.data as PaidTimeOffLedger
  },
  async create(employeeId: number, data: PaidTimeOffPayload): Promise<PaidTimeOff> {
    const response = await api.post(`/employees/${employeeId}/leaves`, data)

    return response.data.data as PaidTimeOff
  },
  async update(employeeId: number, id: number, data: PaidTimeOffPayload): Promise<PaidTimeOff> {
    const response = await api.put(`/employees/${employeeId}/leaves/${id}`, data)

    return response.data.data as PaidTimeOff
  },
  async delete(employeeId: number, id: number): Promise<void> {
    await api.delete(`/employees/${employeeId}/leaves/${id}`)
  },
  async mine(year: number): Promise<PaidTimeOffLedger> {
    const response = await api.get('/my-leaves', { params: { year } })

    return response.data
  },
  async requestRange(data: { start_date: string; end_date: string; duration_days?: 0.5 | 1; reason: string }): Promise<PaidTimeOff[]> {
    const response = await api.post('/my-leaves', data)

    return response.data.data
  },
  async getAllRequests(year: number): Promise<{ data: PaidTimeOff[]; allowance: number }> {
    const response = await api.get('/leaves', { params: { year } })

    return { data: response.data.data, allowance: Number(response.data.allowance ?? 0) }
  },
  async grant(ids: number[], forcePaid = false, approvalNote?: string): Promise<{ data: PaidTimeOff[]; exceeded: number }> {
    const response = await api.patch('/leaves/grant', { ids, force_paid: forcePaid, approval_note: approvalNote })

    return response.data
  },
}

export default PaidTimeOffApi
