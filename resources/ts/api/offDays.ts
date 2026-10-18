import api from './axios'

export type OffDayStatus = 'pending' | 'approved' | 'rejected'

/** A company-wide off day (public holiday) announced for a contiguous range. */
export interface OffDay {
  id: number
  start_date: string
  end_date: string
  days: number
  reason: string
  status: OffDayStatus
  approved_by: number | null
  approvedBy: { id: number; name: string } | null
  approved_at: string | null
  createdBy: { id: number; name: string } | null
  created_at: string | null
  updated_at: string | null
}

export interface OffDayParams {
  year?: number
  from?: string
  to?: string
  status?: OffDayStatus
}

export interface OffDayPayload {
  start_date: string
  end_date: string
  reason: string
}

const OffDayApi = {
  list: async (params?: OffDayParams): Promise<OffDay[]> => {
    const response = await api.get<{ data: OffDay[] }>('/off-days', { params })

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  create: async (data: OffDayPayload): Promise<OffDay> => {
    const response = await api.post<{ data: OffDay }>('/off-days', data)

    return response.data.data
  },

  update: async (id: number, data: OffDayPayload): Promise<OffDay> => {
    const response = await api.put<{ data: OffDay }>(`/off-days/${id}`, data)

    return response.data.data
  },

  approve: async (id: number): Promise<OffDay> => {
    const response = await api.patch<{ data: OffDay }>(`/off-days/${id}/approve`)

    return response.data.data
  },

  reject: async (id: number): Promise<OffDay> => {
    const response = await api.patch<{ data: OffDay }>(`/off-days/${id}/reject`)

    return response.data.data
  },

  remove: async (id: number): Promise<void> => {
    await api.delete(`/off-days/${id}`)
  },
}

export default OffDayApi
