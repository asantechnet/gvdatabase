<script lang="ts" setup>
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { deleteNotification, getNotifications, markAllNotificationsRead, updateNotification, type AppNotification } from '@/api/notification'
import type { Notification } from '@layouts/types'

const router = useRouter()
const toast = useToast()
const rawNotifications = ref<AppNotification[]>([])

const visual = {
  success: { icon: 'tabler-circle-check', color: 'success' }, warning: { icon: 'tabler-alert-triangle', color: 'warning' },
  error: { icon: 'tabler-alert-circle', color: 'error' }, task: { icon: 'tabler-checkbox', color: 'primary' },
  contract: { icon: 'tabler-file-description', color: 'secondary' }, finance: { icon: 'tabler-cash', color: 'success' },
  system: { icon: 'tabler-settings', color: 'info' }, info: { icon: 'tabler-info-circle', color: 'info' },
} as const

const notifications = computed<Notification[]>(() => rawNotifications.value.map(item => {
  const style = visual[item.type as keyof typeof visual] ?? visual.info
  return { id: item.id, icon: style.icon, color: style.color, title: item.title, subtitle: item.message, time: new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(item.date_time)), isSeen: item.is_read === true, goToUrl: item.go_to_url }
}))

async function loadNotifications() {
  try { rawNotifications.value = await getNotifications({ limit: 20 }) }
  catch { /* Preserve the visible inbox during a temporary background failure. */ }
}

async function setRead(ids: number[], isRead: boolean) {
  const targets = rawNotifications.value.filter(item => ids.includes(item.id) && item.is_read !== isRead)
  rawNotifications.value.forEach(item => { if (ids.includes(item.id)) item.is_read = isRead })
  try {
    if (isRead && targets.length === rawNotifications.value.length) await markAllNotificationsRead()
    else await Promise.all(targets.map(item => updateNotification(item.id, { is_read: isRead })))
  } catch {
    await loadNotifications(); toast.error('Could not update notification status.')
  }
}

async function removeNotification(id: number) {
  try { await deleteNotification(id); rawNotifications.value = rawNotifications.value.filter(item => item.id !== id); toast.success('Notification deleted.') }
  catch (error: any) { toast.error(error.message || 'You are not allowed to delete this notification.') }
}

async function handleNotificationClick(notification: Notification) {
  if (!notification.isSeen) await setRead([notification.id], true)
  if (notification.goToUrl) await router.push(notification.goToUrl)
}

let refreshTimer: ReturnType<typeof setInterval> | undefined
onMounted(() => { loadNotifications(); refreshTimer = setInterval(loadNotifications, 60_000) })
onUnmounted(() => { if (refreshTimer) clearInterval(refreshTimer) })
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
