import api from './axios'

export interface AcademyTeacher {
  id: number
  name: string
  contact: string | null
  avatar: string | null
  position: string | null
  is_commission_based: boolean
}

export const AcademyTeachersApi = {
  async list(): Promise<AcademyTeacher[]> {
    const response = await api.get('/academy/teachers')

    return Array.isArray(response.data.data) ? response.data.data : []
  },
}
