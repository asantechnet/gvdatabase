import type { AppNotification } from '@/api/notification'
import { translateBackendValue } from '@/utils/localization'

type Translate = (key: string, values?: Record<string, string>) => string

export function notificationText(notification: AppNotification, translate: Translate) {
  const key = notification.notification_key
  const values = {
    table: String(notification.data?.table ?? ''),
    row: String(notification.data?.row_id ?? ''),
    columns: Array.isArray(notification.data?.columns) ? notification.data.columns.map(column => translateBackendValue(column, 'backend.values')).join(', ') : '',
    requester: String(notification.data?.requester ?? ''),
    reviewer: String(notification.data?.reviewer ?? ''),
  }

  const normalizedKey = key?.startsWith('bonus.review.') ? 'bonus.review' : key
  const baseKey = normalizedKey ? `notifications.${normalizedKey}` : `notifications.types.${notification.type}`
  const title = translate(`${baseKey}.title`, values)
  const message = translate(`${baseKey}.message`, values)

  return {
    title: title && !title.startsWith('[missing:') ? title : (notification.title ?? translateBackendValue(notification.type)),
    message: message && !message.startsWith('[missing:') ? message : (notification.message ?? ''),
  }
}
