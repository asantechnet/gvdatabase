<script lang="ts" setup>
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import { type AppNotification, deleteNotification, getNotifications, markAllNotificationsRead, updateNotification } from '@/api/notification'
import { notificationText } from '@/utils/notificationText'
import { openAuditNotification } from '@/utils/auditNotification'
import { playLaunchDingOnce, setNotificationSoundEnabled, useNotificationHeartbeat } from '@/composables/useNotificationHeartbeat'
import { useVisiblePolling } from '@/composables/useVisiblePolling'
import type { Notification } from '@layouts/types'
import { canAccess } from '@/utils/authorization'
import { isBackgroundActive } from '@/utils/backgroundActivity'

const router = useRouter()
const toast = useToast()
const { t } = useI18n()
const rawNotifications = ref<AppNotification[]>([])
const userData = useCookie<{ notification_sound?: boolean } | null>('userData')
let inboxPending = false
let disposed = false
let launchChecked = false
onUnmounted(() => { disposed = true })

const visual = {
  success: { icon: 'tabler-circle-check', color: 'success' },
  warning: { icon: 'tabler-alert-triangle', color: 'warning' },
  error: { icon: 'tabler-alert-circle', color: 'error' },
  task: { icon: 'tabler-checkbox', color: 'primary' },
  contract: { icon: 'tabler-file-description', color: 'secondary' },
  finance: { icon: 'tabler-cash', color: 'success' },
  audit: { icon: 'tabler-history', color: 'warning' },
  system: { icon: 'tabler-settings', color: 'info' },
  info: { icon: 'tabler-info-circle', color: 'info' },
} as const

const notifications = computed<Notification[]>(() => rawNotifications.value.map(item => {
  const style = visual[item.type as keyof typeof visual] ?? visual.info
  const text = notificationText(item, t)

  return { id: item.id, icon: style.icon, color: style.color, title: text.title, subtitle: text.message, labels: text.labels, sender: text.sender, time: new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(item.date_time)), isSeen: item.is_read === true, goToUrl: item.go_to_url }
}))

async function loadNotifications() {
  if (disposed || inboxPending || !isBackgroundActive() || !canAccess('view_own_notifications'))
    return

  inboxPending = true
  try {
    const items = await getNotifications({ limit: 20 }, { background: true })
    if (disposed)
      return
    rawNotifications.value = items
    if (!launchChecked && isBackgroundActive()) {
      launchChecked = true
      playLaunchDingOnce(items.some(item => item.is_read !== true))
    }
  }
  catch { /* Preserve the visible inbox during a temporary background failure. */ }
  finally { inboxPending = false }
}

/** Adds real-time items to the inbox, de-duped against what is already listed. */
function mergeHeartbeatBatch(batch: AppNotification[]) {
  const knownIds = new Set(rawNotifications.value.map(item => item.id))
  const incoming = batch.filter(item => !knownIds.has(item.id))

  if (incoming.length)
    rawNotifications.value = [...incoming, ...rawNotifications.value]
}

const { start: startHeartbeat } = useNotificationHeartbeat(mergeHeartbeatBatch)
const { start: startInboxRefresh } = useVisiblePolling(loadNotifications, 60_000)

async function setRead(ids: number[], isRead: boolean) {
  const targets = rawNotifications.value.filter(item => ids.includes(item.id) && item.is_read !== isRead)

  rawNotifications.value.forEach(item => {
    if (ids.includes(item.id))
      item.is_read = isRead
  })
  try {
    if (isRead && targets.length === rawNotifications.value.length)
      await markAllNotificationsRead()
    else await Promise.all(targets.map(item => updateNotification(item.id, { is_read: isRead })))
  }
  catch {
    await loadNotifications(); toast.error(translate('components.navigation.navbarnotifications.couldNotUpdateNotificationStatus'))
  }
}

async function removeNotification(id: number) {
  try { await deleteNotification(id); rawNotifications.value = rawNotifications.value.filter(item => item.id !== id); toast.success(translate('common.messages.notificationDeleted')) }
  catch (error: any) { toast.error(error.message || 'You are not allowed to delete this notification.') }
}

async function handleNotificationClick(notification: Notification) {
  if (!notification.isSeen)
    await setRead([notification.id], true)
  const rawNotification = rawNotifications.value.find(item => item.id === notification.id)
  if (rawNotification && openAuditNotification(rawNotification))
    return
  if (notification.goToUrl)
    await router.push(notification.goToUrl)
}

onMounted(() => {
  // Hydrate the chime preference before the first inbox/heartbeat fetch can play it.
  setNotificationSoundEnabled(userData.value?.notification_sound === true)
  startInboxRefresh(true)
  startHeartbeat()
})
</script>

<template>
  <Notifications
    :notifications="notifications"
    @remove="removeNotification"
    @read="ids => setRead(ids, true)"
    @unread="ids => setRead(ids, false)"
    @click:notification="handleNotificationClick"
    @view-all="router.push('/notifications')"
  />
</template>
