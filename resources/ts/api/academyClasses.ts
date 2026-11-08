import api from './axios'

export interface AcademyOption {
  id: number
  name: string
}

export interface AcademyDurationOption extends AcademyOption {
  months: number | null
}

export interface AcademyClassRecord {
  id: number
  name: string
  description: string | null
  sort_order: number
  is_active: boolean
  program: AcademyOption | null
  time: AcademyOption | null
  duration: AcademyDurationOption | null
  teachers: AcademyOption[]
  starts_on: string | null
  ends_on: string | null
  weekdays: number[]
  starts_at: string | null
  ends_at: string | null
  capacity: number | null
  seats_taken: number | null
  fee: number | null
  roster?: AcademyEnrollmentSummary[]
}

export interface AcademyEnrollmentSummary {
  id: number
  student_id: number
  student_number: string | null
  student: {
    id: number
    student_number: string | null
    name: string
    contact: string | null
    email: string | null
    father_phone: string | null
    applicant_id: number | null
  } | null
  applicant: { id: number; name: string; contact: string | null; father_phone: string | null } | null
  contract: { id: number; contract_number: string; status: string; start_date: string | null; end_date: string | null } | null
  line: {
    id: number
    fee: number
    discount: number
    paid: number
    balance: number
    payment_status: string
    currency: { code: string; symbol: string | null; decimal_places: number } | null
  } | null
  duration: AcademyDurationOption | null
  started_on: string | null
  ends_on: string | null
  status: string
  completed_on: string | null
  notes: string | null
}

export interface AcademyClassPayload {
  name: string
  description?: string | null
  program_id?: number | null
  time_id?: number | null
  duration_id?: number | null
  teacher_ids: number[]
  weekdays: number[]
  starts_on?: string | null
  ends_on?: string | null
  starts_at?: string | null
  ends_at?: string | null
  capacity?: number | null
  fee?: number | null
  is_active: boolean
  sort_order?: number
}

export interface AcademyDurationRecord extends AcademyDurationOption {
  is_active: boolean
  sort_order: number
}

const data = <T>(response: { data: { data: T } }): T => response.data.data

export const AcademyClassesApi = {
  async list(params: { search?: string; program_id?: number; is_active?: boolean } = {}): Promise<AcademyClassRecord[]> {
    const response = await api.get('/academy/classes', { params })

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  async get(id: number): Promise<AcademyClassRecord> {
    return data(await api.get(`/academy/classes/${id}`))
  },

  async create(payload: AcademyClassPayload): Promise<AcademyClassRecord> {
    return data(await api.post('/academy/classes', payload))
  },

  async update(id: number, payload: Partial<AcademyClassPayload>): Promise<AcademyClassRecord> {
    return data(await api.put(`/academy/classes/${id}`, payload))
  },

  async remove(id: number): Promise<void> {
    await api.delete(`/academy/classes/${id}`)
  },

  async durations(): Promise<AcademyDurationRecord[]> {
    const response = await api.get('/academy/durations')

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  async updateDuration(id: number, months: number | null): Promise<AcademyDurationRecord> {
    return data(await api.patch(`/academy/durations/${id}`, { months }))
  },
}
