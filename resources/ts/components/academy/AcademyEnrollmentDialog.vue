<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { AcademyEnrollmentsApi, type AcademyEnrollmentRecord, type AcademyEnrollmentStatus } from '@/api/academyEnrollments'
import { apiFieldErrors } from '@/utils/apiErrors'
import { formatActiveDate, formatActiveMoney } from '@/utils/localization'

const props = defineProps<{
  modelValue: boolean
  record: AcademyEnrollmentRecord | null
  canManage: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: []
}>()

const { t } = useI18n()
const toast = useToast()
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const status = ref<Exclude<AcademyEnrollmentStatus, 'active'>>('completed')
const notes = ref('')
const completedOn = ref<string | null>(null)

const open = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

watch(() => props.modelValue, value => {
  if (!value || !props.record)
    return
  errors.value = {}
  status.value = props.record.status === 'active' ? 'completed' : props.record.status as Exclude<AcademyEnrollmentStatus, 'active'>
  notes.value = props.record.notes ?? ''
  completedOn.value = props.record.completed_on
})

const studentName = computed(() => props.record?.student?.name ?? props.record?.applicant?.name ?? '—')
const currency = computed(() => props.record?.line?.currency ?? null)

async function save() {
  if (!props.record || saving.value)
    return

  saving.value = true
  errors.value = {}
  try {
    await AcademyEnrollmentsApi.update(props.record.id, {
      status: status.value,
      notes: notes.value || null,
      completed_on: completedOn.value || null,
    })
    toast.success(t('academy.students.saved'))
    open.value = false
    emit('saved')
  }
  catch (error) {
    errors.value = apiFieldErrors(error)
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog v-model="open" max-width="620">
    <VCard v-if="record">
      <VCardTitle>{{ t('academy.students.detailTitle') }}</VCardTitle>
      <VCardText>
        <div class="d-flex flex-wrap ga-3 mb-4">
          <VChip color="primary" variant="tonal">{{ studentName }}</VChip>
          <VChip variant="outlined">{{ record.student?.student_number ?? record.student_number ?? '—' }}</VChip>
          <VChip variant="outlined">{{ record.class?.name ?? '—' }}</VChip>
          <VChip :color="record.status === 'active' ? 'success' : 'secondary'">{{ t(`academy.status.${record.status}`) }}</VChip>
        </div>
        <VRow>
          <VCol cols="6" sm="3">
            <div class="text-caption text-medium-emphasis">{{ t('academy.fields.startsOn') }}</div>
            <div>{{ formatActiveDate(record.started_on) }}</div>
          </VCol>
          <VCol cols="6" sm="3">
            <div class="text-caption text-medium-emphasis">{{ t('academy.fields.endsOn') }}</div>
            <div>{{ formatActiveDate(record.ends_on) }}</div>
          </VCol>
          <VCol cols="6" sm="3">
            <div class="text-caption text-medium-emphasis">{{ t('academy.fields.contract') }}</div>
            <div>{{ record.contract?.contract_number ?? '—' }}</div>
          </VCol>
          <VCol cols="6" sm="3">
            <div class="text-caption text-medium-emphasis">{{ t('academy.fields.balance') }}</div>
            <div>{{ record.line ? formatActiveMoney(record.line.balance, currency) : '—' }}</div>
          </VCol>
        </VRow>
        <VDivider class="my-4" />
        <VSelect
          v-model="status"
          :items="['completed', 'withdrawn', 'cancelled'].map(value => ({ title: t(`academy.status.${value}`), value }))"
          :label="t('academy.fields.status')"
          :disabled="!canManage || record.status !== 'active'"
          :error-messages="errors.status"
        />
        <VTextField
          v-model="completedOn"
          type="date"
          :label="t('academy.fields.completedOn')"
          :disabled="!canManage || record.status !== 'active'"
          :error-messages="errors.completed_on"
        />
        <VTextarea
          v-model="notes"
          :label="t('academy.fields.notes')"
          rows="3"
          :disabled="!canManage || record.status !== 'active'"
          :error-messages="errors.notes"
        />
        <VAlert v-if="record.status !== 'active'" type="info" variant="tonal" class="mt-4">
          {{ t('academy.students.terminalHint') }}
        </VAlert>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="open = false">{{ canManage && record.status === 'active' ? t('common.actions.cancel') : t('common.actions.close') }}</VBtn>
        <VBtn v-if="canManage && record.status === 'active'" color="primary" :loading="saving" @click="save">{{ t('common.actions.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
