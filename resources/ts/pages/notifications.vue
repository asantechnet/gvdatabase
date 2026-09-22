<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import { type AppNotification, deleteNotification, getNotifications, markAllNotificationsRead, updateNotification } from '@/api/notification'
import { notificationText } from '@/utils/notificationText'
import { openAuditNotification } from '@/utils/auditNotification'

const router = useRouter()
const toast = useToast()
const { t } = useI18n()
const notifications = ref<AppNotification[]>([])
const loading = ref(true)
const filter = ref<'all' | 'unread'>('all')

const visibleNotifications = computed(() => filter.value === 'unread' ? notifications.value.filter(notification => notification.is_read !== true) : notifications.value)
const unreadCount = computed(() => notifications.value.filter(notification => notification.is_read !== true).length)
const iconFor = (type: string) => ({ success: 'tabler-circle-check', warning: 'tabler-alert-triangle', error: 'tabler-alert-circle', task: 'tabler-checkbox', contract: 'tabler-file-description', finance: 'tabler-cash' }[type] ?? 'tabler-info-circle')
const colorFor = (type: string) => ({ success: 'success', warning: 'warning', error: 'error', task: 'primary', contract: 'secondary', finance: 'success' }[type] ?? 'info')
const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
const titleFor = (notification: AppNotification) => notificationText(notification, t).title
const messageFor = (notification: AppNotification) => notificationText(notification, t).message

async function load() {
  loading.value = true
  try { notifications.value = await getNotifications({ limit: 100 }) }
  catch (error: any) { toast.error(error.message || t('notificationInbox.loadFailed')) }
  finally { loading.value = false }
}

async function open(notification: AppNotification) {
  if (notification.is_read !== true) {
    notification.is_read = true
    try { await updateNotification(notification.id, { is_read: true }) }
    catch {
      await load(); toast.error(translate('pages.settings.notifications.notifications.couldNotMarkNotificationAsRead'))

      return
    }
  }
  if (openAuditNotification(notification))
    return
  if (notification.data?.action === 'execute_recurring_event' && notification.data.recurring_event_id) {
    await router.push({ name: 'recurring-events', params: { id: String(notification.data.recurring_event_id) } })

    return
  }
  if (notification.go_to_url)
    await router.push(notification.go_to_url)
}

async function reviewRecurring(notification: AppNotification) {
  await open(notification)
}

async function markAllRead() {
  try { await markAllNotificationsRead(); notifications.value.forEach(notification => { notification.is_read = true }); toast.success(translate('pages.settings.notifications.notifications.allNotificationsMarkedAsRead')) }
  catch (error: any) { toast.error(error.message || t('notificationInbox.updateFailed')) }
}

async function remove(notification: AppNotification) {
  try { await deleteNotification(notification.id); notifications.value = notifications.value.filter(item => item.id !== notification.id); toast.success(translate('common.messages.notificationDeleted')) }
  catch (error: any) { toast.error(error.message || t('notificationInbox.deleteFailed')) }
}

onMounted(load)
</script>

<template>
  <VCard
    class="notification-inbox"
    :loading="loading"
  >
    <VCardItem>
      <VCardTitle>{{ $t('common.messages.notifications') }}</VCardTitle>
      <VCardSubtitle>{{ $t('pages.settings.notifications.unreadCount', { count: unreadCount }) }}</VCardSubtitle>
      <template #append>
        <VBtn
          size="small"
          variant="tonal"
          :disabled="!unreadCount"
          @click="markAllRead"
        >
          {{ $t('pages.settings.notifications.notifications.markAllRead') }}
        </VBtn>
      </template>
    </VCardItem>
    <VCardText class="pt-0">
      <VBtnToggle
        v-model="filter"
        mandatory
        density="compact"
        color="primary"
      >
        <VBtn value="all">
          {{ $t('pages.settings.notifications.notifications.all') }}
        </VBtn><VBtn value="unread">
          {{ $t('pages.settings.notifications.notifications.unread') }}
        </VBtn>
      </VBtnToggle>
    </VCardText>
    <VDivider />
    <VList
      lines="two"
      class="py-0"
    >
      <VListItem
        v-for="notification in visibleNotifications"
        :key="notification.id"
        :class="{ 'notification-unread': notification.is_read !== true }"
        @click="open(notification)"
      >
        <template #prepend>
          <VAvatar
            :color="colorFor(notification.type)"
            variant="tonal"
          >
            <VIcon :icon="iconFor(notification.type)" />
          </VAvatar>
        </template>
        <VListItemTitle class="d-flex align-center flex-wrap gap-2">
          {{ titleFor(notification) }}<VChip
            v-if="notification.is_read !== true"
            size="x-small"
            color="primary"
          >
            {{ $t('pages.settings.notifications.notifications.new') }}
          </VChip>
        </VListItemTitle>
        <VListItemSubtitle class="notification-message">
          {{ messageFor(notification) }}
        </VListItemSubtitle>
        <template #append>
          <div class="d-flex align-center ga-2">
            <VBtn
              v-if="notification.data?.action === 'execute_recurring_event'"
              size="small"
              @click.stop="reviewRecurring(notification)"
            >
              {{ $t('notificationInbox.review') }}
            </VBtn><span class="text-caption text-disabled d-none d-sm-inline">{{ formatDate(notification.date_time) }}</span><IconBtn
              :aria-label="$t('pages.settings.notifications.notifications.deleteNotification')"
              @click.stop="remove(notification)"
            >
              <VIcon icon="tabler-x" />
            </IconBtn>
          </div>
        </template>
      </VListItem>
      <VListItem
        v-if="!loading && !visibleNotifications.length"
        :title="$t('pages.settings.notifications.notifications.noNotificationsFound')"
        :subtitle="$t('notificationInbox.allCaughtUp')"
      />
    </VList>
  </VCard>
</template>

<style scoped>
.notification-inbox { max-inline-size: 960px; margin-inline: auto; }
.notification-unread { background: rgba(var(--v-theme-primary), 0.06); }
.notification-message { white-space: normal; }
</style>
