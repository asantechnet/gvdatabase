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
const labelsFor = (notification: AppNotification) => notificationText(notification, t).labels
const senderFor = (notification: AppNotification) => notificationText(notification, t).sender

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
        class="notification-filter"
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
      :lines="false"
      class="notification-list"
    >
      <template
        v-for="(notification, index) in visibleNotifications"
        :key="notification.id"
      >
        <VDivider v-if="index > 0" />
        <VListItem
          class="notification-row"
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
          <VListItemTitle class="notification-title">
            {{ titleFor(notification) }}
            <VChip
              v-if="notification.is_read !== true"
              size="x-small"
              color="primary"
            >
              {{ $t('pages.settings.notifications.notifications.new') }}
            </VChip>
          </VListItemTitle>
          <VListItemSubtitle class="notification-body">
            <span
              v-if="senderFor(notification)"
              class="notification-sender"
            >
              {{ $t('notifications.announcement.fromSender', { sender: senderFor(notification) }) }}
            </span>
            <span class="notification-message">{{ messageFor(notification) }}</span>
            <div
              v-if="labelsFor(notification).length"
              class="notification-labels"
            >
              <VChip
                v-for="label in labelsFor(notification)"
                :key="label"
                size="x-small"
                color="primary"
                variant="tonal"
                label
              >
                {{ label }}
              </VChip>
            </div>
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
      </template>
      <VListItem
        v-if="!loading && !visibleNotifications.length"
        :title="$t('pages.settings.notifications.notifications.noNotificationsFound')"
        :subtitle="$t('notificationInbox.allCaughtUp')"
        class="notification-row"
      />
    </VList>
  </VCard>
</template>

<style scoped>
.notification-inbox {
  margin-inline: auto;
  max-inline-size: 960px;
}

.notification-unread {
  background: rgba(var(--v-theme-primary), 6%);
}

/* Vuetify's .v-btn sets `flex: 1 0 auto`, stretching every option to the width
   of the widest label. "All" is much shorter than "Unread", so opting out of
   the grow lets each button size to its own text and keeps padding even. */
.notification-filter :deep(.v-btn) {
  flex: 0 0 auto;
  min-inline-size: max-content;
  padding-inline: 1rem;
  white-space: nowrap;
}

.notification-list {
  padding-block: 0;
}

/* Rows carry a title, a wrapping body and a chip row, so the default
   list-item padding is too tight and consecutive entries merge together. */
.notification-row {
  padding-block: 0.875rem;
}

.notification-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  font-weight: 500;
  gap: 0.5rem;
  line-height: 1.4;
}

.notification-body,
.notification-message {
  display: block;
  overflow-wrap: anywhere;
  white-space: normal;
}

.notification-body {
  margin-block-start: 0.25rem;
  opacity: 1;
}

.notification-message {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  line-height: 1.5;
}

.notification-sender {
  display: block;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 500;
  margin-block-end: 0.125rem;
}

.notification-labels {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-block-start: 0.625rem;
}
</style>
