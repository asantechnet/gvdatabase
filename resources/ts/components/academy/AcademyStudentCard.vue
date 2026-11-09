<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AcademyEnrollmentRecord } from '@/api/academyEnrollments'
import { AcademyStudentsApi, type AcademyStudentFinance, type AcademyStudentFinanceCurrency, type AcademyStudentFinanceIncome } from '@/api/academyStudents'
import ReceiptApi from '@/api/receipts'
import ReceiptPrinter from '@/components/income/ReceiptPrinter.vue'
import { canAccess } from '@/utils/authorization'
import { formatActiveDate, formatActiveMoney } from '@/utils/localization'

const props = defineProps<{
  modelValue: boolean
  enrollments: AcademyEnrollmentRecord[]
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t } = useI18n()
const open = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})
const student = computed(() => props.enrollments[0]?.student ?? null)
const studentId = computed(() => student.value?.id ?? null)
const canViewFinance = computed(() => canAccess('view_applicant_financials')
  || canAccess('view_all_finance_records')
  || canAccess('view_incomes'))
const canPrintReceipts = computed(() => canAccess('view_all_finance_records')
  || canAccess('view_incomes'))
const finance = ref<AcademyStudentFinance | null>(null)
const financeLoading = ref(false)
const financeFailed = ref(false)
const receiptOpen = ref(false)
const receiptIncomeId = ref<number | null>(null)
let financeRequest = 0

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
  if (!id || !canViewFinance.value) {
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

watch([() => props.modelValue, studentId], ([isOpen]) => {
  if (!isOpen) {
    ++financeRequest
    financeLoading.value = false
    receiptOpen.value = false

    return
  }

  void loadFinance()
}, { immediate: true })
</script>

<template>
  <VDialog v-model="open" max-width="820" scrollable>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ t('academy.students.cardTitle') }}</span>
        <VBtn icon="tabler-x" variant="text" :aria-label="t('common.actions.close')" @click="open = false" />
      </VCardTitle>
      <VCardText>
        <div class="mb-4">
          <div class="text-h6">{{ student?.name ?? enrollments[0]?.applicant?.name ?? '—' }}</div>
          <div class="text-body-2 text-medium-emphasis">
            {{ student?.student_number ?? enrollments[0]?.student_number ?? '—' }}
            · {{ student?.contact ?? enrollments[0]?.applicant?.contact ?? '—' }}
          </div>
        </div>
        <VAlert v-if="enrollments.length === 0" type="info" variant="tonal">
          {{ t('academy.students.emptyCourses') }}
        </VAlert>
        <VRow>
          <VCol v-for="enrollment in enrollments" :key="enrollment.id" cols="12" md="6">
            <VCard variant="outlined" class="h-100">
              <VCardText>
                <div class="d-flex justify-space-between align-start ga-2 mb-2">
                  <div>
                    <div class="text-subtitle-1 font-weight-bold">{{ enrollment.class?.name ?? '—' }}</div>
                    <div class="text-body-2 text-medium-emphasis">
                      {{ enrollment.class?.program?.name ?? '—' }}
                    </div>
                  </div>
                  <VChip size="small" :color="enrollment.status === 'active' ? 'success' : 'secondary'">
                    {{ t(`academy.status.${enrollment.status}`) }}
                  </VChip>
                </div>
                <div class="text-body-2 mb-1">
                  {{ formatActiveDate(enrollment.started_on) }} — {{ formatActiveDate(enrollment.ends_on) }}
                </div>
                <div class="text-body-2 mb-2">{{ enrollment.contract?.contract_number ?? '—' }}</div>
                <div v-if="enrollment.line" class="d-flex justify-space-between text-body-2">
                  <span>{{ t('academy.fields.balance') }}</span>
                  <strong>{{ formatActiveMoney(enrollment.line.balance, enrollment.line.currency) }}</strong>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <template v-if="canViewFinance">
          <VDivider class="my-5" />
          <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
            <h3 class="text-subtitle-1 font-weight-bold mb-0">{{ t('academy.students.financeTitle') }}</h3>
            <div v-if="finance?.totals.length" class="d-flex flex-wrap ga-2">
              <VChip v-for="(total, index) in finance.totals" :key="index" color="success" variant="tonal" size="small">
                {{ t('academy.students.financeTotalReceived') }}: {{ money(total.amount, total.currency) }}
              </VChip>
            </div>
          </div>
          <VProgressLinear v-if="financeLoading" indeterminate color="primary" />
          <VAlert v-else-if="financeFailed" type="error" variant="tonal">
            {{ t('academy.students.financeLoadFailed') }}
          </VAlert>
          <p v-else-if="!finance?.incomes.length" class="text-body-2 text-medium-emphasis mb-0">
            {{ t('academy.students.financeEmpty') }}
          </p>
          <VList v-else density="compact" lines="two">
            <VListItem
              v-for="income in finance.incomes"
              :key="income.id"
              :title="income.receipt_number || `#${income.id}`"
              :subtitle="[formatActiveDate(income.date), income.description].filter(Boolean).join(' · ')"
            >
              <template #prepend>
                <VIcon icon="tabler-receipt" color="success" />
              </template>
              <template #append>
                <div class="d-flex flex-wrap align-center justify-end ga-2">
                  <strong>{{ money(income.line_total, income.currency) }}</strong>
                  <VChip size="x-small" :color="statusColor(income.status)" variant="tonal">
                    {{ t(`common.statuses.${income.status}`) }}
                  </VChip>
                  <VBtn
                    v-if="income.status === 'approved' && canPrintReceipts"
                    icon="tabler-printer"
                    size="small"
                    variant="text"
                    color="primary"
                    :aria-label="t('documents.receipt.printReceipt')"
                    @click="openReceipt(income)"
                  >
                    <VIcon icon="tabler-printer" />
                  </VBtn>
                </div>
              </template>
            </VListItem>
          </VList>
        </template>
      </VCardText>
    </VCard>
  </VDialog>

  <ReceiptPrinter
    v-model="receiptOpen"
    :record-id="receiptIncomeId"
    :loader="ReceiptApi.getForIncome"
  />
</template>
