<script setup lang="ts">
import WorkdayApi, { type EndWorkdayData, type WorkdayStatus } from '@/api/workday'
import { canAccess } from '@/utils/authorization'
import { apiFieldErrors } from '@/utils/apiErrors'

const { t } = useI18n()
const status = ref<WorkdayStatus | null>(null)
const loading = ref(false)
const startDialog = ref(false)
const endDialog = ref(false)
const errors = ref<Record<string, string>>({})
const report = reactive<EndWorkdayData>({
  summary: '',
  achievements: '',
  challenges: '',
  nextDayPlan: '',
  supportNeeded: '',
})

const canUseWorkday = computed(() => canAccess('manage_own_workday'))

const loadStatus = async () => {
  if (!canUseWorkday.value) return
  try {
    status.value = await WorkdayApi.status()
    startDialog.value = status.value.eligible && status.value.state === 'not_started'
  }
  catch { /* Global API error handling presents the failure. */ }
}

const startDay = async () => {
  loading.value = true
  try {
    status.value = await WorkdayApi.start()
    startDialog.value = false
  }
  finally {
    loading.value = false
  }
}

const endDay = async () => {
  errors.value = {}
  if (!report.summary.trim()) {
    errors.value.summary = t('components.account.workday.summaryRequired')
    return
  }

  loading.value = true
  try {
    status.value = await WorkdayApi.end(report)
    endDialog.value = false
    Object.assign(report, { summary: '', achievements: '', challenges: '', nextDayPlan: '', supportNeeded: '' })
  }
  catch (error) {
    errors.value = apiFieldErrors(error)
  }
  finally {
    loading.value = false
  }
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
    <VListItem v-if="status.state === 'active' || status.state === 'awaiting_report'" prepend-icon="tabler-clock-stop" @click="endDialog = true">
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
    <VListItem v-else-if="status.state === 'not_started'" prepend-icon="tabler-player-play" @click="startDialog = true">
      <VListItemTitle>{{ t('components.account.workday.startMyDay') }}</VListItemTitle>
    </VListItem>
  </template>

  <VDialog v-model="startDialog" persistent max-width="560">
    <VCard>
      <VCardText class="pa-8 text-center">
        <VAvatar color="primary" variant="tonal" size="72" class="mb-5">
          <VIcon icon="tabler-sunrise" size="38" />
        </VAvatar>
        <h2 class="text-h4 mb-2">{{ t('components.account.workday.startTitle') }}</h2>
        <p class="text-body-1 text-medium-emphasis mb-6">{{ t('components.account.workday.startDescription') }}</p>
        <VBtn size="large" color="primary" prepend-icon="tabler-player-play" :loading="loading" @click="startDay">
          {{ t('components.account.workday.startMyDay') }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>

  <VDialog v-model="endDialog" persistent max-width="700">
    <VCard>
      <VCardTitle class="d-flex align-center ga-2 pa-5">
        <VIcon icon="tabler-notes" color="primary" />
        {{ t('components.account.workday.reportTitle') }}
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-5">
        <p class="text-body-2 text-medium-emphasis mb-5">{{ t('components.account.workday.reportDescription') }}</p>
        <VTextarea v-model="report.summary" :label="t('components.account.workday.summary')" rows="3" auto-grow counter="5000"
          :error-messages="errors.summary" class="mb-3" required />
        <VTextarea v-model="report.achievements" :label="t('components.account.workday.achievements')" rows="2" auto-grow counter="5000" />
        <VTextarea v-model="report.challenges" :label="t('components.account.workday.challenges')" rows="2" auto-grow counter="5000" />
        <VTextarea v-model="report.nextDayPlan" :label="t('components.account.workday.nextDayPlan')" rows="2" auto-grow counter="5000" />
        <VTextarea v-model="report.supportNeeded" :label="t('components.account.workday.supportNeeded')" rows="2" auto-grow counter="5000" />
      </VCardText>
      <VCardActions class="pa-5 pt-0">
        <VSpacer />
        <VBtn variant="tonal" :disabled="loading" @click="endDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" prepend-icon="tabler-check" :loading="loading" @click="endDay">
          {{ t('components.account.workday.submitAndEnd') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
