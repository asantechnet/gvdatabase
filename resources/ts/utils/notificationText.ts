import type { AppNotification } from '@/api/notification'
import { translateBackendValue } from '@/utils/localization'

type Translate = (key: string, values?: Record<string, string>) => string

export function notificationText(notification: AppNotification, translate: Translate) {
  const key = notification.notification_key
  const resourceKey = String(notification.data?.resource ?? notification.data?.table ?? 'other')
  const localizedResource = translate(`pages.audits.resources.${resourceKey}`)
  const values = {
    resource: localizedResource && !localizedResource.startsWith('[missing:') ? localizedResource : translate('pages.audits.unknownRecord'),
    record: String(notification.data?.record ?? ''),
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
