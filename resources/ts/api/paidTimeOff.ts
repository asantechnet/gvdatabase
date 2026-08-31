import api from './axios'

export interface PaidTimeOff {
  id: number
  employee_id: number
  date: string
  year: number
  reason: string
  is_granted: boolean
  is_paid: boolean | null
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

export type PaidTimeOffPayload = Pick<PaidTimeOff, 'date' | 'reason'>

const PaidTimeOffApi = {
  async getAll(employeeId: number, year: number): Promise<PaidTimeOffLedger> {
    const response = await api.get(`/employees/${employeeId}/paid-time-offs`, { params: { year } })
    return response.data as PaidTimeOffLedger
  },
  async create(employeeId: number, data: PaidTimeOffPayload): Promise<PaidTimeOff> {
    const response = await api.post(`/employees/${employeeId}/paid-time-offs`, data)
    return response.data.data as PaidTimeOff
  },
  async update(employeeId: number, id: number, data: PaidTimeOffPayload): Promise<PaidTimeOff> {
    const response = await api.put(`/employees/${employeeId}/paid-time-offs/${id}`, data)
    return response.data.data as PaidTimeOff
  },
  async delete(employeeId: number, id: number): Promise<void> {
    await api.delete(`/employees/${employeeId}/paid-time-offs/${id}`)
  },
  async mine(year: number): Promise<PaidTimeOffLedger> {
    const response = await api.get('/my-paid-time-offs', { params: { year } })
    return response.data
  },
  async requestRange(data: { start_date: string; end_date: string; reason: string }): Promise<PaidTimeOff[]> {
    const response = await api.post('/my-paid-time-offs', data)
    return response.data.data
  },
  async getAllRequests(year: number): Promise<PaidTimeOff[]> {
    const response = await api.get('/paid-time-offs', { params: { year } })
    return response.data.data
  },
  async grant(ids: number[]): Promise<{ data: PaidTimeOff[]; exceeded: number }> {
    const response = await api.patch('/paid-time-offs/grant', { ids })
    return response.data
  },
}

export default PaidTimeOffApi
