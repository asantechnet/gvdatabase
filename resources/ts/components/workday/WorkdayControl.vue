<script setup lang="ts">
import WorkdayApi, { type WorkdayStatus } from '@/api/workday'
import { canAccess } from '@/utils/authorization'
import { useVisiblePolling } from '@/composables/useVisiblePolling'

const emit = defineEmits<{
  openStartDialog: []
  openEndDialog: []
}>()

const { t } = useI18n()
const status = ref<WorkdayStatus | null>(null)

let disposed = false
let requestPending = false
const canUseWorkday = computed(() => canAccess('manage_own_workday'))

const loadStatus = async () => {
  if (disposed || requestPending || !canUseWorkday.value)
    return
  requestPending = true
  try {
    const result = await WorkdayApi.status(true)
    if (!disposed) status.value = result
  }
  catch { /* Retry on visibility/online changes without repetitive background toasts. */ }
  finally { requestPending = false }
}

const { start: startStatusRefresh } = useVisiblePolling(loadStatus, 60_000)

onMounted(() => {
  loadStatus()
  window.addEventListener('workday-status-changed', loadStatus)
  window.addEventListener('online', loadStatus)
  startStatusRefresh()
})
onBeforeUnmount(() => {
  disposed = true
  window.removeEventListener('workday-status-changed', loadStatus)
  window.removeEventListener('online', loadStatus)
})
</script>

<template>
  <template v-if="status?.eligible">
    <VDivider class="my-2" />
    <VListItem v-if="status.needsCheckoutCorrection" prepend-icon="tabler-alert-circle" disabled>
      <VListItemTitle>{{ t('components.account.workday.attendanceUnavailable') }}</VListItemTitle>
      <VListItemSubtitle class="text-wrap">{{ t('backend.errors.business.workday_checkout_needs_correction') }}</VListItemSubtitle>
    </VListItem>
    <VListItem
      v-else-if="(status.state === 'active' || status.state === 'awaiting_report') && canAccess('submit_own_daily_report')"
      prepend-icon="tabler-clock-stop"
      @click="emit('openEndDialog')"
    >
      <VListItemTitle>{{ t('components.account.workday.endMyDay') }}</VListItemTitle>
      <VListItemSubtitle v-if="status.attendance?.checkInTime">
        {{ t('components.account.workday.startedAt', { time: status.attendance.checkInTime }) }}
      </VListItemSubtitle>
    </VListItem>
    <VListItem
      v-else-if="status.state === 'completed'"
      prepend-icon="tabler-circle-check"
      disabled
    >
      <VListItemTitle>{{ t('components.account.workday.dayCompleted') }}</VListItemTitle>
    </VListItem>
    <VListItem
      v-else-if="status.state === 'blocked'"
      prepend-icon="tabler-calendar-off"
      disabled
    >
      <VListItemTitle>{{ t('components.account.workday.attendanceUnavailable') }}</VListItemTitle>
    </VListItem>
    <VListItem
      v-else-if="status.state === 'not_started'"
      prepend-icon="tabler-player-play"
      @click="emit('openStartDialog')"
    >
      <VListItemTitle>{{ t('components.account.workday.startMyDay') }}</VListItemTitle>
    </VListItem>
  </template>
</template>
