<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { deleteNotification, getNotifications, markAllNotificationsRead, updateNotification, type AppNotification } from '@/api/notification'

const router = useRouter()
const toast = useToast()
const notifications = ref<AppNotification[]>([])
const loading = ref(true)
const filter = ref<'all' | 'unread'>('all')

const visibleNotifications = computed(() => filter.value === 'unread' ? notifications.value.filter(notification => notification.is_read !== true) : notifications.value)
const unreadCount = computed(() => notifications.value.filter(notification => notification.is_read !== true).length)
const iconFor = (type: string) => ({ success: 'tabler-circle-check', warning: 'tabler-alert-triangle', error: 'tabler-alert-circle', task: 'tabler-checkbox', contract: 'tabler-file-description', finance: 'tabler-cash' }[type] ?? 'tabler-info-circle')
const colorFor = (type: string) => ({ success: 'success', warning: 'warning', error: 'error', task: 'primary', contract: 'secondary', finance: 'success' }[type] ?? 'info')
const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))

async function load() {
  loading.value = true
  try { notifications.value = await getNotifications({ limit: 100 }) }
  catch (error: any) { toast.error(error.message || 'Failed to load notifications.') }
  finally { loading.value = false }
}

async function open(notification: AppNotification) {
  if (notification.is_read !== true) {
    notification.is_read = true
    try { await updateNotification(notification.id, { is_read: true }) }
    catch { await load(); toast.error('Could not mark notification as read.'); return }
  }
  if (notification.go_to_url) await router.push(notification.go_to_url)
}

async function markAllRead() {
  try { await markAllNotificationsRead(); notifications.value.forEach(notification => { notification.is_read = true }); toast.success('All notifications marked as read.') }
  catch (error: any) { toast.error(error.message || 'Could not update notifications.') }
}

async function remove(notification: AppNotification) {
  try { await deleteNotification(notification.id); notifications.value = notifications.value.filter(item => item.id !== notification.id); toast.success('Notification deleted.') }
  catch (error: any) { toast.error(error.message || 'You are not allowed to delete this notification.') }
}

onMounted(load)
</script>

<template>
  <VCard class="notification-inbox" :loading="loading">
    <VCardItem>
      <VCardTitle>Notifications</VCardTitle>
      <VCardSubtitle>{{ unreadCount }} unread</VCardSubtitle>
      <template #append><VBtn size="small" variant="tonal" :disabled="!unreadCount" @click="markAllRead">Mark all read</VBtn></template>
    </VCardItem>
    <VCardText class="pt-0"><VBtnToggle v-model="filter" mandatory density="compact" color="primary"><VBtn value="all">All</VBtn><VBtn value="unread">Unread</VBtn></VBtnToggle></VCardText>
    <VDivider />
    <VList lines="two" class="py-0">
      <VListItem v-for="notification in visibleNotifications" :key="notification.id" :class="{ 'notification-unread': notification.is_read !== true }" @click="open(notification)">
        <template #prepend><VAvatar :color="colorFor(notification.type)" variant="tonal"><VIcon :icon="iconFor(notification.type)" /></VAvatar></template>
        <VListItemTitle class="d-flex align-center flex-wrap gap-2">{{ notification.title }}<VChip v-if="notification.is_read !== true" size="x-small" color="primary">New</VChip></VListItemTitle>
        <VListItemSubtitle class="notification-message">{{ notification.message }}</VListItemSubtitle>
        <template #append><div class="d-flex align-center ga-2"><span class="text-caption text-disabled d-none d-sm-inline">{{ formatDate(notification.date_time) }}</span><IconBtn aria-label="Delete notification" @click.stop="remove(notification)"><VIcon icon="tabler-x" /></IconBtn></div></template>
      </VListItem>
      <VListItem v-if="!loading && !visibleNotifications.length" title="No notifications found" subtitle="You are all caught up." />
    </VList>
  </VCard>
</template>

<style scoped>
.notification-inbox { max-inline-size: 960px; margin-inline: auto; }
.notification-unread { background: rgba(var(--v-theme-primary), 0.06); }
.notification-message { white-space: normal; }
</style>
