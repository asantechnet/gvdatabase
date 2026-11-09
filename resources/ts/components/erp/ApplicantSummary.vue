<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Applicant } from '@/api/applicants'
import type { ApplicantContractLine } from '@/api/contracts'
import { useAcademyEnabled } from '@/composables/useAcademyEnabled'
const props = defineProps<{ applicant: Applicant; lines?: ApplicantContractLine[] }>()
const { t } = useI18n()
const { enabled: academyEnabled } = useAcademyEnabled()
const open = ref(false)
const entries = computed(() => {
  const a = props.applicant
  const d = a.details
  const list: Array<{ label: string; value: string }> = []
  const add = (key: string, value: unknown) => {
    if (value !== null && value !== undefined && value !== '') list.push({ label: t(key), value: String(value) })
  }
  if (a.service === 'test_offer') add('erp.backupPhone', a.backup_phone)
  if (['scholarship', 'private'].includes(a.service)) {
    add('serviceContracts.address', a.address)
    add('serviceContracts.fatherName', a.father_name)
    add('serviceContracts.fatherPhone', a.father_phone)
    add('erp.percentage', d?.grade_score == null ? null : `${d.grade_score}%`)
    add('educational.country', d?.country?.name)
    add('educational.educationLevel', d?.education_level?.name)
    if (a.service === 'private') {
      add('educational.semester', d?.semester?.name)
      add('educational.contractType', (d as { contract_type?: { name?: string } | null })?.contract_type?.name)
      add('educational.serviceFields.universityFee', d?.university_fee)
    }
    add('erp.requestedFields', a.favorite_subjects?.map(row => row.name).join(', '))
  }
  if (a.service === 'test_offer') {
    add('educational.masterData.tests', d?.test?.name)
    add('educational.masterData.skills', a.skills?.map(row => row.name).join(', '))
  }
  if (a.service === 'academy' && academyEnabled.value) {
    add('serviceContracts.studentId', d?.student_id)
    add('educational.masterData.teachers', a.teachers?.map(row => row.name).join(', '))
    add('educational.masterData.programs', d?.program?.name)
    add('erp.class', d?.academy_class?.name)
  }
  add('erp.admissionNotes', a.notes)
  for (const line of props.lines ?? a.services ?? []) {
    if (line.application_fee_min != null && line.application_fee_max != null)
      add('serviceContracts.applicationFeeRange', `${line.application_fee_min} – ${line.application_fee_max} ${line.currency?.code ?? ''}`)
    else
      add('erp.applicationFee', line.application_fee == null ? null : `${line.application_fee} ${line.currency?.code ?? ''}`)
    for (const [field, key] of [['visa_fee', 'visaFee'], ['booking_fee', 'bookingFee'], ['service_fee', 'serviceFee'], ['remote_fee', 'remoteFee']] as const)
      add(`erp.${key}`, line[field] == null ? null : `${line[field]} ${line.currency?.code ?? ''}`)
    add('erp.totalFee', `${line.fee} ${line.currency?.code ?? ''}`)
    add('serviceContracts.rate', line.currency_rate)
    add('serviceContracts.paidFee', line.amount_paid == null ? null : `${line.amount_paid} ${line.currency?.code ?? ''}`)
  }
  return list
})
const brief = computed(() => {
  const d = props.applicant.details
  if (props.applicant.service === 'academy' && academyEnabled.value)
    return [d?.program?.name, d?.academy_class?.name].filter(Boolean).join(' · ')
  if (props.applicant.service === 'test_offer') return [d?.test?.name, props.applicant.skills?.map(row => row.name).join(', ')].filter(Boolean).join(' · ')
  return [d?.education_level?.name, d?.scholarship?.name].filter(Boolean).join(' · ')
})
</script>

<template>
  <div class="d-flex align-center gap-1" @click.stop>
    <span class="text-caption" style="max-inline-size: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ brief || '—' }}</span>
    <VBtn size="small" variant="text" color="primary" @click="open = true">{{ t('erp.details') }}</VBtn>
    <VDialog v-model="open" max-width="760" scrollable content-class="applicant-summary-dialog">
      <VCard class="applicant-summary-dialog__card">
        <VCardTitle class="applicant-summary-dialog__title px-5 py-4"><div><p>{{ t('common.crmDetails.profileTitle') }}</p><span>{{ applicant.name }}</span></div><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="applicant-summary-dialog__close" :aria-label="t('common.actions.close')" @click="open = false" /></VCardTitle>
        <VCardText class="applicant-summary-dialog__body pa-5"><div class="applicant-summary-grid"><div v-for="(entry, index) in entries" :key="index" class="applicant-summary-tile"><span>{{ entry.label }}</span><strong>{{ entry.value }}</strong></div></div></VCardText>
        <VCardActions class="applicant-summary-dialog__actions px-5 py-4"><VSpacer /><VBtn class="applicant-summary-dialog__button" color="primary" variant="flat" @click="open = false">{{ t('serviceContracts.close') }}</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss">
.v-overlay:has(.applicant-summary-dialog) .v-overlay__scrim { background: rgba(var(--v-theme-on-surface), .26) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
.applicant-summary-dialog__card { overflow: hidden; }
.applicant-summary-dialog__title, .applicant-summary-dialog__actions { display: flex; align-items: center; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); border-color: rgba(var(--v-theme-on-surface), .05); border-style: solid; border-width: 0; }
.applicant-summary-dialog__title { border-block-end-width: 1px; }
.applicant-summary-dialog__title p { margin: 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
.applicant-summary-dialog__title span { display: block; margin-block-start: .2rem; font-size: 1.25rem; font-weight: 700; }
.applicant-summary-dialog__close { border: 1px solid rgba(var(--v-theme-error), .38) !important; border-radius: 50% !important; }
.applicant-summary-dialog__body { background: rgb(var(--v-theme-surface)); }
.applicant-summary-dialog__actions { border-block-start-width: 1px; }
.applicant-summary-dialog__button { min-inline-size: 6.5rem; }
.applicant-summary-grid { display: grid; gap: .875rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.applicant-summary-tile { display: grid; gap: .3rem; min-inline-size: 0; min-block-size: 5.25rem; padding: 1rem; border: 1px solid rgba(var(--v-border-color), .42); border-radius: 12px; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%); }
.applicant-summary-tile span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; font-weight: 600; }
.applicant-summary-tile strong { overflow-wrap: anywhere; font-size: .9375rem; line-height: 1.45; white-space: pre-wrap; }
@media (max-width: 599px) { .applicant-summary-grid { grid-template-columns: 1fr; } }
</style>
