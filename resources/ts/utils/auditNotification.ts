import type { AppNotification } from '@/api/notification'

export const auditLogIdFromNotification = (notification: AppNotification): number | null => {
  const dataId = Number(notification.data?.audit_log_id)
  if (Number.isInteger(dataId) && dataId > 0)
    return dataId

  const legacyMatch = notification.go_to_url?.match(/^\/audit-logs\/(\d+)$/)
  if (legacyMatch) return Number(legacyMatch[1])

  const pageMatch = notification.go_to_url?.match(/^\/audits\?(?:[^#]*&)?audit=(\d+)(?:&|$)/)
  return pageMatch ? Number(pageMatch[1]) : null
}

export const openAuditNotification = (notification: AppNotification): boolean => {
  const isAudit = notification.type === 'audit'
    || notification.notification_key?.startsWith('audit.')
    || notification.go_to_url?.startsWith('/audit-logs/')
    || notification.go_to_url?.startsWith('/audits?')

  if (!isAudit)
    return false

  const auditLogId = auditLogIdFromNotification(notification)
  if (!auditLogId)
    return false

  window.dispatchEvent(new CustomEvent('open-audit-log', {
    detail: { auditLogId },
  }))

  return true
}
