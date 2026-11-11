<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AcademyEnrollmentRecord } from '@/api/academyEnrollments'
import { AcademyStudentsApi, type AcademyStudentFinance, type AcademyStudentFinanceCurrency, type AcademyStudentFinanceIncome } from '@/api/academyStudents'
import ReceiptApi from '@/api/receipts'
import ReceiptPrinter from '@/components/income/ReceiptPrinter.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import { canAccess } from '@/utils/authorization'
import { formatActiveDate, formatActiveMoney } from '@/utils/localization'

const props = defineProps<{ modelValue: boolean; enrollments: AcademyEnrollmentRecord[]; loading?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t } = useI18n()
const open = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const tab = ref<'details' | 'classes'>('details')
const student = computed(() => props.enrollments[0]?.student ?? null)
const studentId = computed(() => student.value?.id ?? null)
const canViewFinance = computed(() => canAccess('view_applicant_financials') || canAccess('view_all_finance_records') || canAccess('view_incomes'))
const canPrintReceipts = computed(() => canAccess('view_all_finance_records') || canAccess('view_incomes'))
const finance = ref<AcademyStudentFinance | null>(null)
const financeLoading = ref(false)
const financeFailed = ref(false)
const receiptOpen = ref(false)
const receiptIncomeId = ref<number | null>(null)
let financeRequest = 0

const detailFields = computed(() => [
  { icon: 'tabler-id', label: t('academy.fields.id'), value: student.value?.student_number },
  { icon: 'tabler-phone', label: t('academy.fields.contact'), value: student.value?.contact },
  { icon: 'tabler-mail', label: t('common.fields.email'), value: student.value?.email },
  { icon: 'tabler-user', label: t('educational.fatherName'), value: student.value?.father_name },
  { icon: 'tabler-phone-call', label: t('educational.fatherPhone'), value: student.value?.father_phone },
  { icon: 'tabler-map-pin', label: t('common.fields.address'), value: student.value?.address },
  { icon: 'tabler-notes', label: t('academy.fields.notes'), value: student.value?.notes },
])

function money(amount: number, currency: AcademyStudentFinanceCurrency | null) {
  const decimals = currency?.decimal_places ?? 2
  return `${currency?.symbol ?? ''}${Number(amount || 0).toFixed(decimals)}${currency?.code ? ` ${currency.code}` : ''}`.trim()
}

function statusColor(status: AcademyStudentFinanceIncome['status']) {
  return ({ draft: 'warning', approved: 'success', rejected: 'error' })[status]
}

function openReceipt(income: AcademyStudentFinanceIncome) {
  receiptIncomeId.value = income.id
  receiptOpen.value = true
}

async function loadFinance() {
  const id = studentId.value
  const request = ++financeRequest
  finance.value = null
  financeFailed.value = false
  if (!id || !canViewFinance.value || props.loading) {
    financeLoading.value = false
    return
  }
  financeLoading.value = true
  try {
    const response = await AcademyStudentsApi.getFinance(id)
    if (request === financeRequest)
      finance.value = response
  }
  catch {
    if (request === financeRequest)
      financeFailed.value = true
  }
  finally {
    if (request === financeRequest)
      financeLoading.value = false
  }
}

watch([() => props.modelValue, studentId, () => props.loading], ([isOpen]) => {
  if (!isOpen) {
    ++financeRequest
    financeLoading.value = false
    receiptOpen.value = false
    return
  }
  tab.value = 'details'
  void loadFinance()
}, { immediate: true })
</script>

<template>
  <VDialog v-model="open" max-width="980" scrollable content-class="academy-student-details-dialog">
    <VCard class="academy-student-details-card">
      <VCardTitle class="academy-student-details__header px-5 py-4">
        <div><div class="academy-student-details__eyebrow">{{ t('common.actions.viewFullDetails') }}</div><div class="text-h5 font-weight-bold">{{ student?.name ?? enrollments[0]?.applicant?.name ?? t('academy.students.cardTitle') }}</div></div>
        <VSpacer />
        <VBtn icon="tabler-x" variant="tonal" color="error" class="academy-student-details__close" :aria-label="t('common.actions.close')" @click="open = false" />
      </VCardTitle>
      <VCardText class="academy-student-details__body pa-5 pa-sm-6">
        <div v-if="loading" class="academy-student-details__loading" aria-busy="true"><VSkeletonLoader type="heading, text" class="mb-5" /><div class="academy-student-details__fields"><VSkeletonLoader v-for="index in 6" :key="index" type="list-item-two-line" class="academy-student-details__skeleton" /></div><VSkeletonLoader type="heading, table-tbody" class="mt-6" /></div>
        <template v-else>
          <div class="academy-student-details__identity"><VAvatar color="primary" variant="tonal" size="52"><span class="text-h6 font-weight-bold">{{ (student?.name ?? enrollments[0]?.applicant?.name ?? '—').slice(0, 1) }}</span></VAvatar><div><strong>{{ student?.name ?? enrollments[0]?.applicant?.name ?? '—' }}</strong><span>{{ student?.student_number ?? enrollments[0]?.student_number ?? '—' }}</span></div></div>
          <VTabs v-model="tab" density="compact" class="academy-student-details__tabs"><VTab value="details">{{ t('academy.students.detailTitle') }}</VTab><VTab value="classes">{{ t('academy.fields.class') }}</VTab></VTabs>
          <VWindow v-model="tab" class="mt-5">
            <VWindowItem value="details">
              <div class="academy-student-details__fields"><div v-for="field in detailFields" :key="field.label" class="academy-student-details__field"><VIcon :icon="field.icon" color="primary" size="20" /><div><span>{{ field.label }}</span><strong>{{ field.value || '—' }}</strong></div></div></div>
              <template v-if="canViewFinance"><VDivider class="my-6" /><div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3"><h3 class="text-subtitle-1 font-weight-bold mb-0">{{ t('academy.students.financeTitle') }}</h3><div v-if="finance?.totals.length" class="d-flex flex-wrap ga-2"><VChip v-for="(total, index) in finance.totals" :key="index" color="success" variant="tonal" size="small">{{ t('academy.students.financeTotalReceived') }}: {{ money(total.amount, total.currency) }}</VChip></div></div><VSkeletonLoader v-if="financeLoading" type="list-item-two-line, list-item-two-line" /><VAlert v-else-if="financeFailed" type="error" variant="tonal">{{ t('academy.students.financeLoadFailed') }}</VAlert><p v-else-if="!finance?.incomes.length" class="text-body-2 text-medium-emphasis mb-0">{{ t('academy.students.financeEmpty') }}</p><VList v-else density="compact" lines="two"><VListItem v-for="income in finance.incomes" :key="income.id" :title="income.receipt_number || `#${income.id}`" :subtitle="[formatActiveDate(income.date), income.description].filter(Boolean).join(' · ')"><template #prepend><VIcon icon="tabler-receipt" color="success" /></template><template #append><div class="d-flex flex-wrap align-center justify-end ga-2"><strong>{{ money(income.line_total, income.currency) }}</strong><VChip size="x-small" :color="statusColor(income.status)" variant="tonal">{{ t(`common.statuses.${income.status}`) }}</VChip><VBtn v-if="income.status === 'approved' && canPrintReceipts" icon="tabler-printer" size="small" variant="text" color="primary" :aria-label="t('documents.receipt.printReceipt')" @click="openReceipt(income)"><VIcon icon="tabler-printer" /></VBtn></div></template></VListItem></VList></template>
            </VWindowItem>
            <VWindowItem value="classes"><TableEmptyState v-if="enrollments.length === 0" icon="tabler-school-off" :min-height="180" :title="t('academy.students.emptyCourses')" /><div v-else class="academy-student-details__classes"><article v-for="enrollment in enrollments" :key="enrollment.id" class="academy-student-details__class"><div><strong>{{ enrollment.class?.name ?? '—' }}</strong><span>{{ enrollment.class?.program?.name ?? '—' }}</span></div><VChip size="small" :color="enrollment.status === 'active' ? 'success' : 'secondary'" variant="tonal">{{ t(`academy.status.${enrollment.status}`) }}</VChip><div class="academy-student-details__class-meta"><span>{{ t('academy.fields.joiningDate') }}</span><strong>{{ formatActiveDate(enrollment.started_on) }}</strong></div><div v-if="enrollment.line" class="academy-student-details__class-meta"><span>{{ t('academy.fields.balance') }}</span><strong>{{ formatActiveMoney(enrollment.line.balance, enrollment.line.currency) }}</strong></div></article></div></VWindowItem>
          </VWindow>
        </template>
      </VCardText>
    </VCard>
  </VDialog>
  <ReceiptPrinter v-model="receiptOpen" :record-id="receiptIncomeId" :loader="ReceiptApi.getForIncome" />
</template>

<style scoped>
.academy-student-details-card { overflow: hidden; }
.academy-student-details__header { display: flex; align-items: center; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .07); background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.academy-student-details__body { background: rgb(var(--v-theme-surface)); }
.academy-student-details__eyebrow { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .8125rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.academy-student-details__close { border: 1px solid rgba(var(--v-theme-error), .38) !important; border-radius: 999px !important; color: rgb(var(--v-theme-error)) !important; }
.academy-student-details__close:hover { background: rgba(var(--v-theme-error), .18) !important; }
.academy-student-details__identity { display: flex; align-items: center; gap: .875rem; margin-block-end: 1rem; }
.academy-student-details__identity > div { display: grid; gap: .15rem; }
.academy-student-details__identity span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .875rem; }
.academy-student-details__tabs { border-block-end: 1px solid rgba(var(--v-border-color), .48); }
.academy-student-details__fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
.academy-student-details__field { display: flex; align-items: flex-start; gap: .7rem; min-block-size: 5rem; padding: .875rem; border: 1px solid rgba(var(--v-border-color), .42); border-radius: 10px; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%); }
.academy-student-details__field > div, .academy-student-details__class > div { display: grid; gap: .2rem; min-inline-size: 0; }
.academy-student-details__field span, .academy-student-details__class span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; }
.academy-student-details__field strong { overflow-wrap: anywhere; }
.academy-student-details__skeleton { min-block-size: 5rem; border: 1px solid rgba(var(--v-border-color), .36); border-radius: 10px; }
.academy-student-details__classes { display: grid; gap: .75rem; }
.academy-student-details__class { display: grid; grid-template-columns: minmax(12rem, 1.5fr) auto minmax(7rem, .75fr) minmax(7rem, .75fr); align-items: center; gap: 1rem; padding: .875rem 1rem; border: 1px solid rgba(var(--v-border-color), .42); border-radius: 12px; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%); }
.academy-student-details__class-meta { text-align: end; }
:global(.v-overlay:has(.academy-student-details-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .24) !important; backdrop-filter: blur(5px); opacity: 1 !important; }
@media (max-width: 799px) { .academy-student-details__fields { grid-template-columns: repeat(2, minmax(0, 1fr)); } .academy-student-details__class { grid-template-columns: minmax(0, 1fr) auto; } .academy-student-details__class-meta { text-align: start; } }
@media (max-width: 599px) { .academy-student-details__header, .academy-student-details__body { padding-inline: 1rem !important; } .academy-student-details__fields { grid-template-columns: 1fr; } .academy-student-details__class { gap: .75rem; } }
</style>
