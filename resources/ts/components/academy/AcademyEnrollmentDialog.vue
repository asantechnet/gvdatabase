<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { AcademyEnrollmentsApi, type AcademyEnrollmentRecord, type AcademyEnrollmentStatus } from '@/api/academyEnrollments'
import { apiFieldErrors } from '@/utils/apiErrors'
import { formatActiveDate, formatActiveMoney } from '@/utils/localization'

const props = defineProps<{ modelValue: boolean; record: AcademyEnrollmentRecord | null; canManage: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()
const { t } = useI18n()
const toast = useToast()
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const status = ref<Exclude<AcademyEnrollmentStatus, 'active'>>('completed')
const notes = ref('')
const completedOn = ref<string | null>(null)
const open = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })

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
const canEdit = computed(() => props.canManage && props.record?.status === 'active')

async function save() {
  if (!props.record || saving.value)
    return
  saving.value = true
  errors.value = {}
  try {
    await AcademyEnrollmentsApi.update(props.record.id, { status: status.value, notes: notes.value || null, completed_on: completedOn.value || null })
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

const dockDialogPicker = (_selectedDates: Date[], _dateString: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.setProperty('transform', 'translateY(0) scale(0.86)', 'important')
    instance.calendarContainer.style.setProperty('transform-origin', 'top left', 'important')
    instance.calendarContainer.style.setProperty('z-index', '2400', 'important')
  })
}
</script>

<template>
  <VDialog v-model="open" max-width="780" :persistent="saving" scrollable scroll-strategy="block" content-class="academy-enrollment-dialog">
    <VCard v-if="record" class="academy-enrollment-card app-form-dialog">
      <VForm class="app-form" @submit.prevent="save">
        <VCardTitle class="academy-enrollment__header px-5 py-4"><div><div class="academy-enrollment__eyebrow">{{ t('academy.students.detailTitle') }}</div><div class="text-h5 font-weight-bold">{{ studentName }}</div></div><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="academy-enrollment__close" :disabled="saving" :aria-label="t('common.actions.close')" @click="open = false" /></VCardTitle>
        <VCardText class="academy-enrollment__body pa-5 pa-sm-6">
          <div class="academy-enrollment__summary mb-5"><VChip color="primary" variant="tonal">{{ record.student?.student_number ?? record.student_number ?? '—' }}</VChip><VChip variant="outlined">{{ record.class?.name ?? '—' }}</VChip><VChip :color="record.status === 'active' ? 'success' : 'secondary'" variant="tonal">{{ t(`academy.status.${record.status}`) }}</VChip></div>
          <div class="academy-enrollment__facts mb-5"><div><span>{{ t('academy.fields.joiningDate') }}</span><strong>{{ formatActiveDate(record.started_on) }}</strong></div><div><span>{{ t('academy.fields.program') }}</span><strong>{{ record.class?.program?.name ?? '—' }}</strong></div><div><span>{{ t('academy.fields.balance') }}</span><strong>{{ record.line ? formatActiveMoney(record.line.balance, currency) : '—' }}</strong></div></div>
          <VRow class="academy-enrollment__grid">
            <VCol cols="12" md="6"><ErpField v-model="status" kind="select" :items="['completed', 'withdrawn', 'cancelled'].map(value => ({ title: t(`academy.status.${value}`), value }))" :label="t('academy.fields.status')" :placeholder="t('academy.fields.status')" :disabled="!canEdit" :error-messages="errors.status" /></VCol>
            <VCol cols="12" md="6"><div class="erp-field"><label class="erp-field-label">{{ t('academy.fields.completedOn') }}</label><AppDateTimePicker v-model="completedOn" class="erp-field-control" :placeholder="t('academy.placeholders.date')" :disabled="!canEdit" :error-messages="errors.completed_on" :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockDialogPicker }" /></div></VCol>
            <VCol cols="12"><ErpField v-model="notes" :label="t('academy.fields.notes')" :placeholder="t('academy.fields.notes')" :disabled="!canEdit" :error-messages="errors.notes" /></VCol>
          </VRow>
          <VAlert v-if="!canEdit" type="info" variant="tonal" class="mt-4">{{ t('academy.students.terminalHint') }}</VAlert>
        </VCardText>
        <VCardActions class="academy-enrollment__footer px-5 py-4"><VSpacer /><VBtn color="secondary" variant="flat" class="academy-enrollment__cancel" :disabled="saving" @click="open = false">{{ canEdit ? t('common.actions.cancel') : t('common.actions.close') }}</VBtn><VBtn v-if="canEdit" color="primary" variant="flat" type="submit" :loading="saving">{{ t('common.actions.save') }}</VBtn></VCardActions>
      </VForm>
    </VCard>
  </VDialog>
</template>

<style scoped>
.academy-enrollment-card { overflow: hidden; }
.academy-enrollment__header, .academy-enrollment__footer { display: flex; align-items: center; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.academy-enrollment__header { border-block-end: 1px solid rgba(var(--v-theme-on-surface), .07); }
.academy-enrollment__footer { gap: .5rem; border-block-start: 1px solid rgba(var(--v-theme-on-surface), .07); }
.academy-enrollment__body { background: rgb(var(--v-theme-surface)); }
.academy-enrollment__eyebrow { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .8125rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.academy-enrollment__close { border: 1px solid rgba(var(--v-theme-error), .38) !important; border-radius: 999px !important; color: rgb(var(--v-theme-error)) !important; }
.academy-enrollment__close:hover { background: rgba(var(--v-theme-error), .18) !important; }
.academy-enrollment__summary { display: flex; flex-wrap: wrap; gap: .5rem; }
.academy-enrollment__facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
.academy-enrollment__facts > div { display: grid; gap: .2rem; padding: .8rem; border: 1px solid rgba(var(--v-border-color), .42); border-radius: 10px; }
.academy-enrollment__facts span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; }
.academy-enrollment__cancel { border: 1px solid rgba(var(--v-theme-secondary), .45) !important; background: rgba(var(--v-theme-secondary), .12) !important; color: rgb(var(--v-theme-secondary)) !important; }
.academy-enrollment__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
:global(.v-overlay:has(.academy-enrollment-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .28) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
:global(.academy-enrollment-dialog) { max-block-size: calc(100dvh - 2rem); }
@media (max-width: 599px) { .academy-enrollment__header, .academy-enrollment__footer, .academy-enrollment__body { padding-inline: 1rem !important; } .academy-enrollment__facts { grid-template-columns: 1fr; } }
</style>
