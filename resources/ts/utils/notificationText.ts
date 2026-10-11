import type { AppNotification } from '@/api/notification'
import { translateBackendValue } from '@/utils/localization'

type Translate = (key: string, values?: Record<string, string>) => string

export function notificationText(notification: AppNotification, translate: Translate) {
  const key = notification.notification_key
  const resourceKey = String(notification.data?.resource ?? notification.data?.table ?? 'other')
  const localizedResource = translate(`pages.audits.resources.${resourceKey}`)

  const service = String(notification.data?.service ?? '')
  const serviceKey = ({ scholarship: 'scholarship', test_offer: 'testOffer', academy: 'academy', private: 'private' } as Record<string, string>)[service]

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
    employee: String(notification.data?.employee ?? ''),
    contract_number: String(notification.data?.contract_number ?? ''),
    service: serviceKey ? translate(`educational.builtInServices.${serviceKey}`) : service,
    parent_contract_number: String(notification.data?.parent_contract_number ?? ''),
    template: String(notification.data?.template ?? ''),
    version: String(notification.data?.version ?? ''),
    version_number: String(notification.data?.version_number ?? ''),
    country: String(notification.data?.country ?? ''),
    language: String(notification.data?.language ?? ''),
    label: String(notification.data?.label ?? ''),
    counterparty: String(notification.data?.counterparty ?? ''),
    end_date: String(notification.data?.end_date ?? ''),
    days_remaining: String(notification.data?.days_remaining ?? ''),
  }

  const normalizedKey = String(notification.data?.translation_key || (key?.startsWith('bonus.review.') ? 'bonus.review' : key))

  // Announcements carry their own content; only the surrounding labels are
  // localized so the author's words are never replaced by a template.
  // Labels are returned as a list so the UI can render them as tags instead of
  // flattening them into the message body.
  if (normalizedKey.startsWith('announcement.')) {
    // The sender is surfaced on its own so it stays visible when the
    // announcement has a title, instead of only acting as a title fallback.
    const sender = String(notification.data?.announcement_sender ?? '').trim()

    return {
      title: notification.title || sender || translate('notifications.announcement.fallbackTitle'),
      message: notification.message ?? '',
      sender,
      labels: Array.isArray(notification.data?.announcement_labels)
        ? (notification.data?.announcement_labels as unknown[])
          .filter((label): label is string => typeof label === 'string')
          .map(label => label.trim())
          .filter(label => label !== '')
        : [],
    }
  }

  const baseKey = normalizedKey ? `notifications.${normalizedKey}` : `notifications.types.${notification.type}`
  const title = translate(`${baseKey}.title`, values)
  const message = translate(`${baseKey}.message`, values)

  return {
    title: title && !title.startsWith('[missing:') ? title : (notification.title ?? translateBackendValue(notification.type)),
    message: message && !message.startsWith('[missing:') ? message : (notification.message ?? ''),
    sender: '',
    labels: [],
  }
}
