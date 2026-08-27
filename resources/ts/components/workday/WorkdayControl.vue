<script setup lang="ts">
import WorkdayApi, { type WorkdayStatus } from '@/api/workday'
import { canAccess } from '@/utils/authorization'

const { t } = useI18n()
const emit = defineEmits<{
  openStartDialog: []
  openEndDialog: []
}>()

const status = ref<WorkdayStatus | null>(null)
const loading = ref(false)

const canUseWorkday = computed(() => canAccess('manage_own_workday'))

const loadStatus = async () => {
  if (!canUseWorkday.value) return
  try {
    status.value = await WorkdayApi.status()
  }
  catch { /* Global API error handling presents the failure. */ }
}

onMounted(() => {
  loadStatus()
  window.addEventListener('workday-status-changed', loadStatus)
})
onBeforeUnmount(() => window.removeEventListener('workday-status-changed', loadStatus))
</script>

<template>
  <template v-if="status?.eligible">
    <VDivider class="my-2" />
    <VListItem v-if="status.state === 'active' || status.state === 'awaiting_report'" prepend-icon="tabler-clock-stop" @click="emit('openEndDialog')">
      <VListItemTitle>{{ t('components.account.workday.endMyDay') }}</VListItemTitle>
      <VListItemSubtitle v-if="status.attendance?.checkInTime">
        {{ t('components.account.workday.startedAt', { time: status.attendance.checkInTime }) }}
      </VListItemSubtitle>
    </VListItem>
    <VListItem v-else-if="status.state === 'completed'" prepend-icon="tabler-circle-check" disabled>
      <VListItemTitle>{{ t('components.account.workday.dayCompleted') }}</VListItemTitle>
    </VListItem>
    <VListItem v-else-if="status.state === 'blocked'" prepend-icon="tabler-calendar-off" disabled>
      <VListItemTitle>{{ t('components.account.workday.attendanceUnavailable') }}</VListItemTitle>
    </VListItem>
    <VListItem v-else-if="status.state === 'not_started'" prepend-icon="tabler-player-play" @click="emit('openStartDialog')">
      <VListItemTitle>{{ t('components.account.workday.startMyDay') }}</VListItemTitle>
    </VListItem>
  </template>
</template>
