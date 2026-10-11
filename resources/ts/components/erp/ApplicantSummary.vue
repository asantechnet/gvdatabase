<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Applicant } from '@/api/applicants'
import type { ApplicantContractLine } from '@/api/contracts'
const props = defineProps<{ applicant: Applicant; lines?: ApplicantContractLine[] }>()
const { t } = useI18n()
const open = ref(false)
const entries = computed(() => {
  const a = props.applicant
  const d = a.details
  const list: Array<{ label: string; value: string }> = []
  const add = (key: string, value: unknown) => {
    if (value !== null && value !== undefined && value !== '') list.push({ label: t(key), value: String(value) })
  }
  add('erp.phone', a.contact)
  if (a.service === 'test_offer') add('erp.backupPhone', a.backup_phone)
  if (['scholarship', 'private'].includes(a.service)) {
    add('serviceContracts.fatherName', a.father_name)
    add('serviceContracts.fatherPhone', a.father_phone)
    add('erp.percentage', d?.grade_score == null ? null : `${d.grade_score}%`)
    add('erp.scholarship', d?.scholarship?.name)
    add('educational.country', d?.country?.name)
    add('educational.educationLevel', d?.education_level?.name)
    add('educational.semester', d?.semester?.name)
    add('erp.requestedFields', a.favorite_subjects?.map(row => row.name).join(', '))
    add('educational.serviceFields.universityFee', d?.university_fee)
  }
  if (a.service === 'test_offer') {
    add('educational.masterData.tests', d?.test?.name)
    add('educational.masterData.skills', a.skills?.map(row => row.name).join(', '))
  }
  if (a.service === 'academy') {
    add('educational.masterData.times', d?.time?.name)
    add('educational.masterData.teachers', a.teachers?.map(row => row.name).join(', '))
    add('educational.masterData.durations', d?.duration?.name)
    add('educational.masterData.programs', d?.program?.name)
    add('erp.class', d?.academy_class?.name)
  }
  add('erp.admissionNotes', a.notes)
  for (const line of props.lines ?? a.services ?? []) {
    for (const [field, key] of [['application_fee', 'applicationFee'], ['visa_fee', 'visaFee'], ['booking_fee', 'bookingFee'], ['service_fee', 'serviceFee'], ['remote_fee', 'remoteFee']] as const)
      add(`erp.${key}`, line[field] == null ? null : `${line[field]} ${line.currency?.code ?? ''}`)
    add('erp.totalFee', `${line.fee} ${line.currency?.code ?? ''}`)
    add('serviceContracts.rate', line.currency_rate)
  }
  return list
})
const brief = computed(() => {
  const d = props.applicant.details
  if (props.applicant.service === 'academy') return [d?.program?.name, d?.academy_class?.name].filter(Boolean).join(' · ')
  if (props.applicant.service === 'test_offer') return [d?.test?.name, props.applicant.skills?.map(row => row.name).join(', ')].filter(Boolean).join(' · ')
  return [d?.education_level?.name, d?.scholarship?.name].filter(Boolean).join(' · ')
})
</script>

<template>
  <div class="d-flex align-center gap-1" @click.stop>
    <span class="text-caption" style="max-inline-size: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ brief || '—' }}</span>
    <VBtn size="small" variant="text" color="primary" @click="open = true">{{ t('erp.details') }}</VBtn>
    <VDialog v-model="open" max-width="640" scrollable>
      <VCard :title="applicant.name"><VCardText><dl class="applicant-summary-grid">
        <template v-for="(entry, index) in entries" :key="index"><dt>{{ entry.label }}</dt><dd>{{ entry.value }}</dd></template>
      </dl></VCardText><VCardActions><VSpacer /><VBtn @click="open = false">{{ t('serviceContracts.close') }}</VBtn></VCardActions></VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.applicant-summary-grid { display: grid; grid-template-columns: minmax(120px, 1fr) minmax(0, 2fr); gap: 12px; }
dt { font-weight: 600; }
dd { margin: 0; overflow-wrap: anywhere; white-space: pre-wrap; }
</style>
