import api from './axios'
import { type ApiReadOptions, backgroundReadConfig } from './backgroundRead'

export type NotificationType = 'info' | 'success' | 'warning' | 'error' | 'task' | 'contract' | 'finance' | 'system' | string

export interface AppNotification {
  id: number
  type: NotificationType
  notification_key: string | null
  title: string | null
  message: string | null
  data: Record<string, unknown> | null
  date_time: string
  is_read: boolean | null
  employee_id: number | null
  go_to_url: string | null
  created_at: string
  updated_at: string
}

export interface CreateNotificationData {
  type: NotificationType
  notification_key: string
  data?: Record<string, unknown>
  date_time?: string
  employee_id?: number | null
  user_ids?: number[]
  go_to_url?: string | null
}

export class NotificationApiError extends Error {
  public readonly validationErrors: Record<string, string[]> | undefined

  constructor(error: any) {
    super(error?.message || 'Notification request failed.')
    this.name = 'NotificationApiError'
    this.validationErrors = error?.errors
  }
}

const request = async <T>(operation: () => Promise<any>): Promise<T> => {
  try {
    const response = await operation()

    return response.data.data as T
  }
  catch (error) {
    throw new NotificationApiError(error)
  }
}

/**
 * Real-time delivery channel. Returns the user's unread notifications that were
 * queued since the last poll and clears the server-side flag for that batch.
 * An empty array is the normal "nothing new" response.
 */
export const fetchNotificationHeartbeat = (): Promise<AppNotification[]> =>
  request(() => api.get('/notifications/heartbeat', backgroundReadConfig({ background: true })))

export const getNotifications = (params?: { limit?: number; is_read?: boolean }, options?: ApiReadOptions): Promise<AppNotification[]> => request(() => api.get('/notifications', { ...backgroundReadConfig(options), params }))
export const getNotification = (id: number): Promise<AppNotification> => request(() => api.get(`/notifications/${id}`))
export const createNotification = (data: CreateNotificationData): Promise<AppNotification> => request(() => api.post('/notifications', data))
export const updateNotification = (id: number, data: Partial<CreateNotificationData> & { is_read?: boolean | null }): Promise<AppNotification> => request(() => api.put(`/notifications/${id}`, data))
export const markAllNotificationsRead = (): Promise<void> => request(() => api.patch('/notifications/mark-all-read'))
export const deleteNotification = (id: number): Promise<void> => request(() => api.delete(`/notifications/${id}`))

export default { getNotifications, getNotification, fetchNotificationHeartbeat, createNotification, updateNotification, markAllNotificationsRead, deleteNotification }
