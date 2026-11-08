import api from './axios'

export type AnnouncementScopeType = 'all_employees' | 'position' | 'role' | 'department' | 'applicants'

export interface AnnouncementScope {
  type: AnnouncementScopeType
  id?: number
}

export interface Announcement {
  id: number
  sender_id: number
  sender: { id: number; name: string } | null
  title: string | null
  message: string
  labels: string[]
  audience: AnnouncementScope[]
  created_at: string
}

export interface AnnouncementAudienceOptions {
  counts: Partial<Record<AnnouncementScopeType, number>>
  positions: Array<{ id: number; title: string }>
  roles: Array<{ id: number; name: string }>
  departments: Array<{ id: number; name: string }>
}

export interface AnnouncementCreateData {
  title?: string | null
  message: string
  labels?: string[]
  audience: AnnouncementScope[]
}

const request = async <T>(operation: () => Promise<any>): Promise<T> => {
  const response = await operation()

  return response.data.data as T
}

export const getAnnouncements = (): Promise<Announcement[]> => request(() => api.get('/announcements'))
export const getAnnouncement = (id: number): Promise<Announcement> => request(() => api.get(`/announcements/${id}`))
export const createAnnouncement = (data: AnnouncementCreateData): Promise<Announcement> => request(() => api.post('/announcements', data))
export const updateAnnouncement = (id: number, data: AnnouncementCreateData): Promise<Announcement> => request(() => api.put(`/announcements/${id}`, data))
export const deleteAnnouncement = (id: number): Promise<void> => request(() => api.delete(`/announcements/${id}`))
export const getAnnouncementAudienceOptions = (): Promise<AnnouncementAudienceOptions> => request(() => api.get('/announcements/audience-options'))

export default { getAnnouncements, getAnnouncement, createAnnouncement, updateAnnouncement, deleteAnnouncement, getAnnouncementAudienceOptions }
