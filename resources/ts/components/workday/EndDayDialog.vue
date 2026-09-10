<script setup lang="ts">
import WorkdayApi, { type EndWorkdayData } from '@/api/workday'
import { apiFieldErrors } from '@/utils/apiErrors'

interface Props {
  modelValue: boolean
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()
const loading = ref(false)
const errors = ref<Record<string, string>>({})

const report = reactive<EndWorkdayData>({
  summary: '',
  achievements: '',
  challenges: '',
  nextDayPlan: '',
  supportNeeded: '',
})

async function handleSubmit() {
  errors.value = {}
  if (!report.summary.trim()) {
    errors.value.summary = t('components.account.workday.summaryRequired')

    return
  }

  loading.value = true
  try {
    await WorkdayApi.end(report)
    emit('update:modelValue', false)
    Object.assign(report, { summary: '', achievements: '', challenges: '', nextDayPlan: '', supportNeeded: '' })
    window.dispatchEvent(new CustomEvent('workday-status-changed'))
  }
  catch (error) {
    errors.value = apiFieldErrors(error)
  }
  finally {
    loading.value = false
  }
}

watch(() => props.modelValue, open => {
  if (!open) {
    Object.assign(report, { summary: '', achievements: '', challenges: '', nextDayPlan: '', supportNeeded: '' })
    errors.value = {}
  }
})
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    persistent
    max-width="700"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #default="{ isActive }: { isActive: { value: boolean } }">
      <VCard>
        <VCardTitle class="d-flex align-center ga-2 pa-5">
          <VIcon
            icon="tabler-notes"
            color="primary"
          />
          {{ t('components.account.workday.reportTitle') }}
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <p class="text-body-2 text-medium-emphasis mb-5">
            {{ t('components.account.workday.reportDescription') }}
          </p>
          <VTextarea
            v-model="report.summary"
            :label="t('components.account.workday.summary')"
            rows="3"
            auto-grow
            counter="5000"
            :error-messages="errors.summary"
            class="mb-3"
            required
          />
          <VTextarea
            v-model="report.achievements"
            :label="t('components.account.workday.achievements')"
            rows="2"
            auto-grow
            counter="5000"
          />
          <VTextarea
            v-model="report.challenges"
            :label="t('components.account.workday.challenges')"
            rows="2"
            auto-grow
            counter="5000"
          />
          <VTextarea
            v-model="report.nextDayPlan"
            :label="t('components.account.workday.nextDayPlan')"
            rows="2"
            auto-grow
            counter="5000"
          />
          <VTextarea
            v-model="report.supportNeeded"
            :label="t('components.account.workday.supportNeeded')"
            rows="2"
            auto-grow
            counter="5000"
          />
        </VCardText>
        <VCardActions class="pa-5 pt-0">
          <VSpacer />
          <VBtn
            variant="tonal"
            :disabled="loading"
            @click="emit('update:modelValue', false)"
          >
            {{ t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-check"
            :loading="loading"
            @click="handleSubmit"
          >
            {{ t('components.account.workday.submitAndEnd') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </template>
  </VDialog>
</template>
