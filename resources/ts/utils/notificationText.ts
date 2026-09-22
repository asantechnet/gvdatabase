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
    applicant: String(notification.data?.applicant ?? ''),
    stage: String(notification.data?.stage ?? ''),
    days: String(notification.data?.days ?? ''),
    status: translateBackendValue(String(notification.data?.status ?? '')),
    type: translateBackendValue(String(notification.data?.transaction_type ?? '')),
    amount: String(notification.data?.amount ?? ''),
    reason: String(notification.data?.reason ?? ''),
    package: String(notification.data?.package ?? ''),
    employee: String(notification.data?.employee ?? ''),
  }

  const normalizedKey = String(notification.data?.translation_key || (key?.startsWith('bonus.review.') ? 'bonus.review' : key))
  const baseKey = normalizedKey ? `notifications.${normalizedKey}` : `notifications.types.${notification.type}`
  const title = translate(`${baseKey}.title`, values)
  const message = translate(`${baseKey}.message`, values)

  return {
    title: title && !title.startsWith('[missing:') ? title : (notification.title ?? translateBackendValue(notification.type)),
    message: message && !message.startsWith('[missing:') ? message : (notification.message ?? ''),
  }
}
