<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { VDialog } from 'vuetify/components'
import { useToast } from 'vue-toastification'
import { type FinanceType, getFinanceTypes } from '@/api/financeTypes'
import { financeTypeLabel } from '@/utils/financeTypes'
import PendingTransactionApi from '@/api/pendingTransactions'
import { type Income, type IncomeCreateData, type ApplicantContractPaymentOption, createIncome, deleteIncome, getIncomes, getApplicantContractPayments, updateIncome } from '@/api/incomes'
import ReceiptApi from '@/api/receipts'
import ApplicantApi, { type Applicant } from '@/api/applicants'
import CurrencyApi, { type Currency } from '@/api/currency'
import { apiErrorMessage } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import ApplicantSelect from '@/components/income/ApplicantSelect.vue'
import ReceiptPrinter from '@/components/income/ReceiptPrinter.vue'
import AttachmentsDialog from '@/components/attachments/AttachmentsDialog.vue'
import PaymentOverridesDialog from '@/components/income/PaymentOverridesDialog.vue'
import ServicePaymentsDialog from '@/components/income/ServicePaymentsDialog.vue'
import { canAccess } from '@/utils/authorization'
import { changedIncomeFields, paymentEntryLimit } from '@/utils/incomePaymentBalance'

const incomes = ref<Income[]>([])
const isAddIncomeDialogVisible = ref(false)
const isEditIncomeDialogVisible = ref(false)
const isDeleteIncomeDialogVisible = ref(false)
type IncomeForm = Omit<IncomeCreateData, 'applicant_contract_id'> & {
  id: number
  applicant_contract_id: number | null
  base_amount: number
  receipt_number?: string
  contract_service?: Income['contract_service']
  currency?: Income['currency']
  created_at?: string
  updated_at?: string
}

const emptyIncomeForm = (): IncomeForm => ({
  id: 0,
  applicant_id: null,
  applicant_contract_id: null,
  type: '',
  description: '',
  amount: 0,
  quantity: 1,
  currency_id: 0,
  rate: 1,
  base_amount: 0,
  date: new Date().toISOString().split('T')[0],
})

const selectedIncome = ref<IncomeForm>(emptyIncomeForm())
const searchQuery = ref('')
const filterType = ref<string | null>(null)
const filterCurrency = ref<number | null>(null)
const dateFrom = ref('')
const dateTo = ref('')
const isLoading = ref(false)
const applicantContracts = ref<ApplicantContractPaymentOption[]>([])
const currencies = ref<Currency[]>([])
const applicants = ref<Applicant[]>([])
const formError = ref('')
const isReceiptPreviewOpen = ref(false)
const activeReceiptIncomeId = ref<number | null>(null)
const saveMode = ref<'direct' | 'loan'>('direct')
const attachmentTarget = ref<Income | null>(null)
const isAttachmentsOpen = ref(false)
const isPaymentOverridesOpen = ref(false)
const isServicePaymentsOpen = ref(false)

function openAttachments(income: Income) {
  attachmentTarget.value = income
  isAttachmentsOpen.value = true
}
const toast = useToast()

const financeTypes = ref<FinanceType[]>([])
const typeOptions = computed(() => financeTypes.value.map(type => ({ title: financeTypeLabel(type), value: type.value })))
const filterTypeOptions = typeOptions
const typeLabel = (value: string) => {
  const type = financeTypes.value.find(type => type.value === value)
  return type ? financeTypeLabel(type) : translateBackendValue(value)
}

async function fetchTypes() {
  try { financeTypes.value = await getFinanceTypes('income') }
  catch (error) { toast.error(apiErrorMessage(error, translate('pages.finance.types.loadFailed'))) }
}

const currencyOptions = computed(() => currencies.value
  .filter(currency => currency.isActive)
  .map(currency => ({ title: `${currency.code} — ${currency.name}`, value: currency.id })))

const selectedServiceLine = computed(() => {
  const lineId = selectedIncome.value?.applicant_contract_id

  return lineId ? applicantContracts.value.find(line => line.id === lineId) : undefined
})

const selectedLineRemaining = computed(() => {
  const line = selectedServiceLine.value
  if (!line)
    return 0

  const original = incomes.value.find(income => income.id === selectedIncome.value.id)
  return paymentEntryLimit(line, selectedIncome.value.rate, original)
})
const selectedCurrency = computed(() => currencies.value.find(currency => currency.id === selectedIncome.value.currency_id) ?? null)
const selectedCurrencyDecimals = computed(() => selectedServiceLine.value?.currency?.decimalPlaces ?? selectedCurrency.value?.decimalPlaces ?? 2)
const selectedCurrencyStep = computed(() => 10 ** -selectedCurrencyDecimals.value)

// The applicant's contracted services are the payment subjects: one receipt
// covers one service.
const serviceLineOptions = computed(() => applicantContracts.value.map(line => ({
  title: [line.service_label, line.contract_number].filter(Boolean).join(' — '),
  value: line.id,
  props: { disabled: line.is_cancelled || (line.remaining_total <= 0 && !incomes.value.some(income => income.id === selectedIncome.value.id && income.applicant_contract_id === line.id)) },
})))
const currentBaseCurrency = computed(() => currencies.value.find(currency => currency.isBase) ?? null)

const fetchApplicantContracts = async (applicantId?: number | null) => {
  if (!applicantId) {
    applicantContracts.value = []

    return
  }

  applicantContracts.value = await getApplicantContractPayments(applicantId)
}

const fetchCurrencies = async () => {
  const response = await CurrencyApi.getAll()

  currencies.value = response.data.filter(currency => currency.isActive)
}

const fetchApplicants = async () => {
  const response = await ApplicantApi.list({ per_page: 100 })

  applicants.value = response.data
}

const openReceiptPreview = (income: Income) => {
  activeReceiptIncomeId.value = income.id
  isReceiptPreviewOpen.value = true
}

const setDefaultCurrency = () => {
  if (selectedIncome.value.currency_id)
    return

  const currency = currencies.value.find(item => item.isBase) ?? currencies.value[0]
  if (currency) {
    selectedIncome.value.currency_id = currency.id
    selectedIncome.value.rate = currency.rate
  }
}

const fetchIncomes = async () => {
  try {
    isLoading.value = true

    const params: any = {}

    if (searchQuery.value)
      params.search = searchQuery.value
    if (filterType.value)
      params.type = filterType.value
    if (filterCurrency.value)
      params.currency_id = filterCurrency.value
    if (dateFrom.value)
      params.date_from = dateFrom.value
    if (dateTo.value)
      params.date_to = dateTo.value

    const response = await getIncomes(params)

    incomes.value = Array.isArray(response) ? response : []
  }
  catch (error) {
    console.error('Error fetching incomes:', error)
    incomes.value = []
  }
  finally {
    isLoading.value = false
  }
}

const openAddIncomeDialog = () => {
  formError.value = ''
  saveMode.value = 'direct'
  selectedIncome.value = emptyIncomeForm()
  applicantContracts.value = []
  Promise.all([fetchCurrencies(), fetchApplicants(), fetchTypes()]).then(() => {
    setDefaultCurrency()
    isAddIncomeDialogVisible.value = true
  }).catch(error => {
    console.error('Error loading income form options:', error)
    formError.value = translate('common.messages.unableToLoadOptions')
    isAddIncomeDialogVisible.value = true
  })
}

const openEditIncomeDialog = (income: Income) => {
  formError.value = ''
  selectedIncome.value = { ...income }
  Promise.all([
    income.applicant_id ? fetchApplicantContracts(income.applicant_id) : Promise.resolve(),
    fetchCurrencies(),
    fetchTypes(),
    fetchApplicants(),
  ]).then(() => {
    isEditIncomeDialogVisible.value = true
  }).catch(error => {
    console.error('Error loading income form options:', error)
    formError.value = translate('common.messages.unableToLoadOptions')
    isEditIncomeDialogVisible.value = true
  })
}

const handleApplicantChange = async (applicantId: number | null) => {
  selectedIncome.value.applicant_contract_id = null
  await fetchApplicantContracts(applicantId)
  setDefaultCurrency()
  calculateTotal()
}

const handleServiceLineChange = (lineId: number | null) => {
  if (!lineId)
    return

  const line = applicantContracts.value.find(item => item.id === lineId)
  if (!line)
    return

  selectedIncome.value.type = 'Service'
  selectedIncome.value.description = [line.service_label, line.contract_number].filter(Boolean).join(' — ')
  if (line.currency) {
    selectedIncome.value.currency_id = line.currency.id
    selectedIncome.value.rate = line.currency_rate
  }
  selectedIncome.value.quantity = 1
  selectedIncome.value.amount = selectedLineRemaining.value
  calculateTotal()
}

const handleCurrencyChange = (currencyId: number) => {
  const currency = currencies.value.find(item => item.id === currencyId)
  if (currency) {
    selectedIncome.value.rate = currency.rate
    calculateTotal()
  }
}

const formatSelectedLineAmount = (amount: number) => amount.toFixed(selectedCurrencyDecimals.value)

const messageFromError = (error: unknown): string => apiErrorMessage(error, 'Unable to save the income record.')

const validateIncomeForm = () => {
  if (!selectedIncome.value.type)
    return translate('pages.finance.income.income.selectAnIncomeType')
  if (!selectedIncome.value.currency_id)
    return translate('common.messages.selectACurrency')
  if (!Number.isFinite(Number(selectedIncome.value.amount)) || selectedIncome.value.amount <= 0)
    return translate('pages.finance.income.income.amountMustBeGreaterThanZero')
  if (!Number.isInteger(Number(selectedIncome.value.quantity)) || selectedIncome.value.quantity < 1)
    return translate('pages.finance.income.income.quantityMustBeAWholeNumberOfAtLeastOne')
  if (!Number.isFinite(Number(selectedIncome.value.rate)) || selectedIncome.value.rate <= 0)
    return translate('pages.finance.income.income.exchangeRateMustBeGreaterThanZero')
  if (!/^\d{4}-\d{2}-\d{2}$/.test(selectedIncome.value.date))
    return translate('pages.finance.income.income.selectAValidIncomeDate')

  if (selectedServiceLine.value && selectedIncome.value.amount * selectedIncome.value.quantity > selectedLineRemaining.value + selectedCurrencyStep.value / 2)
    return `${translate('pages.finance.income.income.paymentExceedsRemainingBalance')} ${formatSelectedLineAmount(selectedLineRemaining.value)} ${selectedServiceLine.value.currency?.code ?? ''}`.trim()

  return ''
}

const openDeleteIncomeDialog = (income: Income) => {
  selectedIncome.value = income
  isDeleteIncomeDialogVisible.value = true
}

const addIncome = async () => {
  formError.value = validateIncomeForm()
  if (formError.value)
    return

  try {
    formError.value = ''

    // Payment requests route the receipt through the approval workflow instead
    // of writing the ledger row immediately.
    if (saveMode.value === 'loan') {
      await PendingTransactionApi.create({
        transaction_type: 'income',
        reason: selectedIncome.value.description?.trim() || selectedIncome.value.type || 'Income',
        amount: Number(selectedIncome.value.amount) * Number(selectedIncome.value.quantity || 1),
        currency_id: selectedIncome.value.currency_id,
        applicant_id: selectedIncome.value.applicant_id ?? null,
        employee_id: null,
      })
      isAddIncomeDialogVisible.value = false
      toast.success(translate('pages.finance.income.income.loanSubmitted'))

      return
    }

    const incomeData = { ...selectedIncome.value } as IncomeCreateData
    const createdIncome = await createIncome(incomeData)

    incomes.value.unshift(createdIncome)
    isAddIncomeDialogVisible.value = false
  }
  catch (error) {
    console.error('Error creating income:', error)
    formError.value = messageFromError(error)
  }
}

const updateIncomeRecord = async () => {
  if (selectedIncome.value.id) {
    formError.value = validateIncomeForm()
    if (formError.value)
      return

    try {
      formError.value = ''

      const original = incomes.value.find(income => income.id === selectedIncome.value.id)
      if (!original)
        return
      const incomeData = changedIncomeFields(original, selectedIncome.value)
      const updatedIncome = await updateIncome(selectedIncome.value.id, incomeData)
      const index = incomes.value.findIndex(i => i.id === updatedIncome.id)
      if (index !== -1)
        incomes.value[index] = updatedIncome

      isEditIncomeDialogVisible.value = false
    }
    catch (error) {
      console.error('Error updating income:', error)
      formError.value = messageFromError(error)
    }
  }
}

const deleteIncomeRecord = async () => {
  if (selectedIncome.value.id) {
    try {
      await deleteIncome(selectedIncome.value.id)
      incomes.value = incomes.value.filter(i => i.id !== selectedIncome.value.id)
      isDeleteIncomeDialogVisible.value = false
    }
    catch (error) {
      console.error('Error deleting income:', error)
    }
  }
}

const calculateTotal = () => {
  const decimals = currentBaseCurrency.value?.decimalPlaces ?? 2

  selectedIncome.value.base_amount = Number((selectedIncome.value.amount * selectedIncome.value.quantity / selectedIncome.value.rate).toFixed(decimals))
}

const formatIncomeCurrency = (value: number | string, currency: Income['currency']): string => {
  const amount = Number(value)
  const formatted = Number.isFinite(amount) ? amount.toFixed(currency?.decimalPlaces ?? 2) : '0.00'

  return currency?.symbol ? `${currency.symbol}${formatted}` : `${formatted} ${currency?.code ?? ''}`.trim()
}

const formatRate = (value: number | string): string => formatLocalizedNumber(value, { maximumFractionDigits: 4 })

const incomeTypeColor = (type: string) => ({
  Service: 'primary',
  Product: 'success',
  Investment: 'warning',
  Other: 'secondary',
}[type] ?? 'secondary')

const formatDate = (dateString: string | undefined | null): string => {
  if (!dateString)
    return ''

  return formatLocalizedDate(`${dateString}T00:00:00`, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

const printTable = () => void printDocument({ bodyClass: 'printing-table-income', pageStyle: 'size: A4 landscape; margin: 12mm;' })

onMounted(() => {
  fetchTypes()
  fetchIncomes()
  fetchCurrencies()
  fetchApplicants()
})
</script>

<template>
  <PaymentOverridesDialog v-model="isPaymentOverridesOpen" />
  <ServicePaymentsDialog v-model="isServicePaymentsOpen" @created="incomes.unshift($event)" />
  <div class="income-page">
    <div class="income-page-head d-flex flex-wrap align-center justify-space-between gap-3 mb-6">
      <div class="d-flex align-center ga-3">
        <VAvatar
          icon="tabler-receipt-2"
          color="primary"
          variant="tonal"
          rounded="lg"
        />
        <div>
          <h1 class="text-h5 font-weight-bold mb-0">
            {{ $t('pages.finance.income.income.incomeLedger') }}
          </h1>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ $t('pages.finance.income.income.liveReceiptActivityAndCurrencyTotalsInOneView') }}
          </p>
        </div>
      </div>
      <div class="d-flex flex-wrap align-center gap-2">
        <VBtn
          icon="tabler-printer"
          variant="text"
          :aria-label="$t('pages.finance.income.income.printIncomeRecords')"
          @click="printTable"
        >
          <VIcon icon="tabler-printer" />
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ $t('pages.finance.income.income.printIncomeRecords') }}
          </VTooltip>
        </VBtn>
        <VBtn v-if="canAccess('override_contract_payments')" icon="tabler-settings" variant="text" @click="isPaymentOverridesOpen = true">
          <VIcon icon="tabler-settings" />
          <VTooltip activator="parent">{{ $t('finance.paymentOverrides.title') }}</VTooltip>
        </VBtn>
        <VBtn v-if="canAccess('create_incomes')" prepend-icon="tabler-receipt" variant="tonal" @click="isServicePaymentsOpen = true">
          {{ $t('finance.servicePayments.title') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openAddIncomeDialog"
        >
          {{ $t('pages.finance.income.income.recordIncome') }}
        </VBtn>
      </div>
    </div>

    <VCard
      class="mb-6 filter-deck"
      elevation="0"
    >
      <VCardText class="filter-deck__content">
        <VRow align="center">
          <VCol
            cols="12"
            md="8"
          >
            <VTextField
              v-model="searchQuery"
              class="data-table-search"
              prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.finance.income.income.searchIncome')"
              density="compact"
              clearable
              hide-details
              @keyup.enter="fetchIncomes"
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
            class="d-flex align-center justify-md-end ga-2"
          >
            <DataTableFilterMenu
              :active="Boolean(filterType || filterCurrency || dateFrom || dateTo)"
              @reset="filterType = null; filterCurrency = null; dateFrom = ''; dateTo = ''; fetchIncomes()"
            >
              <VSelect
                v-model="filterType"
                :items="filterTypeOptions"
                :label="$t('common.messages.filterByType')"
                density="compact"
                clearable
                hide-details
                @update:model-value="fetchIncomes"
              />
              <VSelect
                v-model="filterCurrency"
                :items="currencyOptions"
                :label="$t('common.messages.filterByCurrency')"
                density="compact"
                clearable
                hide-details
                @update:model-value="fetchIncomes"
              />
              <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" @apply="fetchIncomes" />
            </DataTableFilterMenu>
            <div class="text-caption text-medium-emphasis">
              <strong class="text-high-emphasis">{{ incomes.length }}</strong> {{ $t('pages.finance.income.income.recordsCurrentlyShown') }}
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      class="ledger-card"
      elevation="0"
    >
      <VCardItem class="ledger-card__header">
        <VCardTitle class="ledger-card__title font-weight-bold">
          <VIcon
            icon="tabler-receipt-2"
            size="20"
          />{{ $t('pages.finance.income.income.receiptStream') }}
        </VCardTitle>
        <template #append>
          <VChip
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ $t('pages.finance.income.income.liveLedger') }}
          </VChip>
        </template>
      </VCardItem>
      <div class="table-container">
        <ResponsiveDataTable
          :items="incomes"
          :headers="[
            { title: translate('pages.finance.income.income.receipt'), key: 'receipt_number', align: 'start' },
            { title: translate('common.fields.type'), key: 'type', align: 'start' },
            { title: translate('common.messages.details'), key: 'description', align: 'start' },
            { title: translate('pages.finance.income.income.service'), key: 'contract_service', align: 'start' },
            { title: translate('common.fields.amount'), key: 'base_amount', align: 'end' },
            { title: translate('common.fields.date'), key: 'date', align: 'center' },
            { title: translate('common.messages.recordedBy'), key: 'recordedBy.name', align: 'start' },
            { title: translate('common.messages.actions'), key: 'actions', align: 'center', sortable: false },
          ]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
        >
          <template #loading>
            <TableLoadingSkeleton :columns="8" :label="$t('common.messages.loading')" />
          </template>
          <template #no-data>
            <TableEmptyState icon="tabler-receipt-off" :description="$t('common.messages.noMatchingRecords')" />
          </template>
          <template #item.receipt_number="{ item }">
            <div class="font-weight-bold">
              {{ item.receipt_number || `RCPT-${String(item.id).padStart(6, '0')}` }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ $t('common.messages.currencyQuantity', { currency: item.currency?.code ?? '—', quantity: item.quantity }) }}
            </div>
          </template>

          <template #item.type="{ item }">
            <VChip
              size="small"
              :color="incomeTypeColor(item.type)"
              variant="tonal"
            >
              {{ typeLabel(item.type) }}
            </VChip>
          </template>

          <template #item.description="{ item }">
            <div class="detail-copy">
              {{ item.description || 'No description added' }}
            </div>
          </template>

          <template #item.contract_service="{ item }">
            <div v-if="item.contract_service">
              <div class="font-weight-medium">
                {{ item.contract_service.service_label }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ $t(`finance.paymentStatus.${item.contract_service.payment_status}`) }}
                <template v-if="item.contract_service.contract_number"> · {{ item.contract_service.contract_number }}</template>
              </div>
            </div>
            <span
              v-else
              class="text-caption text-medium-emphasis"
            >{{ $t('pages.finance.income.income.standalone') }}</span>
          </template>

          <template #item.base_amount="{ item }">
            <div class="font-weight-bold text-success">
              {{ formatIncomeCurrency(item.amount * item.quantity, item.currency) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ $t('common.messages.quantityExchangeRate', { quantity: item.quantity, rate: formatRate(item.rate) }) }}
            </div>
          </template>

          <template #item.date="{ item }">
            <span>{{ formatDate(item.date) }}</span>
          </template>
          <template #item.recordedBy.name="{ item }">
            <span>{{ item.recordedBy?.name || $t('common.messages.systemLegacy') }}</span>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-paperclip"
                variant="text"
                size="small"
                :aria-label="$t('common.attachments.title')"
                @click="openAttachments(item)"
              >
                <VIcon icon="tabler-paperclip" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('common.attachments.title') }}
                </VTooltip>
              </VBtn>
              <FullDetailsButton :item="item" />
              <VBtn
                icon="tabler-receipt"
                variant="text"
                size="small"
                color="primary"
                @click="openReceiptPreview(item)"
              >
                <VIcon icon="tabler-receipt" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('documents.receipt.printReceipt') }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                @click="openEditIncomeDialog(item)"
              >
                <VIcon icon="tabler-edit" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('pages.finance.income.income.editIncomeRecord') }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="openDeleteIncomeDialog(item)"
              >
                <VIcon icon="tabler-trash" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('pages.finance.income.income.deleteIncomeRecord') }}
                </VTooltip>
              </VBtn>
            </div>
          </template>

        </ResponsiveDataTable>
      </div>
    </VCard>

    <ReceiptPrinter
      v-model="isReceiptPreviewOpen"
      :record-id="activeReceiptIncomeId"
      :loader="ReceiptApi.getForIncome"
    />

    <AttachmentsDialog
      v-model="isAttachmentsOpen"
      type="incomes"
      :record-id="attachmentTarget?.id ?? null"
      :title="attachmentTarget?.receipt_number || $t('common.attachments.title')"
    />

    <PrintableListReport
      :title="$t('common.crmUi.printReports.income.title')"
      :subtitle="$t('common.crmUi.printReports.income.subtitle')"
      :columns="[$t('common.crmUi.printReports.columns.receipt'), $t('common.crmUi.printReports.columns.type'), $t('common.crmUi.printReports.columns.details'), $t('common.crmUi.printReports.columns.service'), $t('common.crmUi.printReports.columns.amount'), $t('common.crmUi.printReports.columns.date')]"
      :rows="incomes.map(income => [income.receipt_number, income.type, income.description, income.contract_service?.service_label ?? '', formatIncomeCurrency(income.amount * income.quantity, income.currency), formatDate(income.date)])"
    />

    <!-- Add Income Dialog -->
    <VDialog
      v-model="isAddIncomeDialogVisible"
      max-width="900px"
    >
      <VCard class="income-dialog">
        <VCardTitle class="pa-6 pb-2">
          <div class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              icon="tabler-receipt"
            /><div>
              <div class="text-h5 font-weight-bold">
                {{ $t('pages.finance.income.income.recordIncome2') }}
              </div><div class="text-caption text-medium-emphasis">
                {{ $t('pages.finance.income.income.captureAStandaloneReceiptOrApplyPaymentToAContractLine') }}
              </div>
            </div>
          </div>
        </VCardTitle>

        <VCardText>
          <VForm class="app-form">
            <VAlert
              v-if="formError"
              type="error"
              variant="tonal"
              class="mb-4"
            >
              {{ formError }}
            </VAlert>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <ApplicantSelect
                  v-model="selectedIncome.applicant_id"
                  :applicants="applicants"
                  :disabled="Boolean(selectedServiceLine)"
                  @update:model-value="handleApplicantChange"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedIncome.applicant_contract_id"
                  :items="serviceLineOptions"
                  :label="$t('pages.finance.income.income.service')"
                  :disabled="!selectedIncome.applicant_id"
                  clearable
                  :hint="selectedIncome.applicant_id ? $t('pages.finance.income.income.selectTheContractedServiceThisReceiptPaysFor') : undefined"
                  :persistent-hint="Boolean(selectedIncome.applicant_id)"
                  @update:model-value="handleServiceLineChange"
                />
              </VCol>
              <VCol
                v-if="selectedServiceLine"
                cols="12"
              >
                <div
                  class="d-flex flex-wrap gap-x-6 gap-y-2"
                >
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('common.messages.fee') }}
                    </div>
                    <div class="font-weight-medium">
                      {{ formatIncomeCurrency(selectedServiceLine.fee, selectedServiceLine.currency) }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('pages.finance.income.income.discount') }}
                    </div>
                    <div class="font-weight-medium">
                      {{ formatIncomeCurrency(selectedServiceLine.discount, selectedServiceLine.currency) }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('pages.finance.income.income.paidToDate') }}
                    </div>
                    <div class="font-weight-medium">
                      {{ formatIncomeCurrency(selectedServiceLine.amount_paid, selectedServiceLine.currency) }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('pages.finance.income.income.remaining') }}
                    </div>
                    <div class="font-weight-medium">
                      {{ formatIncomeCurrency(selectedServiceLine.remaining_total, selectedServiceLine.currency) }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('pages.finance.income.income.paymentStatus') }}
                    </div>
                    <VChip
                      size="small"
                      variant="tonal"
                    >{{ $t(`finance.paymentStatus.${selectedServiceLine.payment_status}`) }}</VChip>
                  </div>
                </div>
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mt-3"
                >
                  {{ $t('pages.finance.income.income.customerTypeQuantityCurrencyAndExchangeRateComeFromTheSelectedService') }}
                </VAlert>
              </VCol>
              <VCol cols="12">
                <div class="d-flex flex-wrap gap-4 mb-1">
                  <VBtn
                    :color="saveMode === 'direct' ? 'primary' : 'secondary'"
                    :variant="saveMode === 'direct' ? 'flat' : 'outlined'"
                    prepend-icon="tabler-device-floppy"
                    @click="saveMode = 'direct'"
                  >
                    {{ $t('pages.finance.income.income.saveModeDirect') }}
                  </VBtn>
                  <VBtn
                    :color="saveMode === 'loan' ? 'primary' : 'secondary'"
                    :variant="saveMode === 'loan' ? 'flat' : 'outlined'"
                    prepend-icon="tabler-send"
                    @click="saveMode = 'loan'"
                  >
                    {{ $t('pages.finance.income.income.saveModeLoan') }}
                  </VBtn>
                </div>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{ saveMode === 'loan' ? $t('pages.finance.income.income.saveModeLoanHint') : $t('pages.finance.income.income.saveModeDirectHint') }}
                </p>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedIncome.type"
                  :items="typeOptions"
                  :label="$t('common.fields.type')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedIncome.description"
                  :label="$t('common.messages.descriptionPaymentNoteOptional')"
                  :placeholder="$t('common.messages.addAReferencePaymentNoteOrReceiptDetail')"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model.number="selectedIncome.amount"
                  :label="$t('common.fields.amount')"
                  type="number"
                  :step="selectedCurrencyStep"
                  :max="selectedServiceLine ? selectedLineRemaining : undefined"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model.number="selectedIncome.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VSelect
                  v-model="selectedIncome.currency_id"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  @update:model-value="handleCurrencyChange"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model.number="selectedIncome.rate"
                  :label="$t('common.messages.exchangeRate')"
                  type="number"
                  step="0.00000001"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model="selectedIncome.base_amount"
                  :label="$t('common.messages.totalAmount')"
                  type="number"
                  readonly
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="selectedIncome.date"
                  :label="$t('common.fields.date')"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isAddIncomeDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="addIncome"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Income Dialog -->
    <VDialog
      v-model="isEditIncomeDialogVisible"
      max-width="900px"
    >
      <VCard class="income-dialog">
        <VCardTitle class="pa-6 pb-2">
          <div class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              icon="tabler-pencil"
            /><div>
              <div class="text-h5 font-weight-bold">
                {{ $t('pages.finance.income.income.editIncome') }}
              </div><div class="text-caption text-medium-emphasis">
                {{ $t('pages.finance.income.income.updateReceiptDetailsOrItsContractAllocation') }}
              </div>
            </div>
          </div>
        </VCardTitle>

        <VCardText>
          <VForm class="app-form">
            <VAlert
              v-if="formError"
              type="error"
              variant="tonal"
              class="mb-4"
            >
              {{ formError }}
            </VAlert>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <ApplicantSelect
                  v-model="selectedIncome.applicant_id"
                  :applicants="applicants"
                  :disabled="Boolean(selectedServiceLine)"
                  @update:model-value="handleApplicantChange"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedIncome.applicant_contract_id"
                  :items="serviceLineOptions"
                  :label="$t('pages.finance.income.income.service')"
                  :disabled="!selectedIncome.applicant_id"
                  clearable
                  :hint="selectedIncome.applicant_id ? $t('pages.finance.income.income.selectTheContractedServiceThisReceiptPaysFor') : undefined"
                  :persistent-hint="Boolean(selectedIncome.applicant_id)"
                  @update:model-value="handleServiceLineChange"
                />
              </VCol>
              <VCol
                v-if="selectedServiceLine"
                cols="12"
              >
                <div
                  class="d-flex flex-wrap gap-x-6 gap-y-2"
                >
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('common.messages.fee') }}
                    </div>
                    <div class="font-weight-medium">
                      {{ formatIncomeCurrency(selectedServiceLine.fee, selectedServiceLine.currency) }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('pages.finance.income.income.discount') }}
                    </div>
                    <div class="font-weight-medium">
                      {{ formatIncomeCurrency(selectedServiceLine.discount, selectedServiceLine.currency) }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('pages.finance.income.income.paidToDate') }}
                    </div>
                    <div class="font-weight-medium">
                      {{ formatIncomeCurrency(selectedServiceLine.amount_paid, selectedServiceLine.currency) }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('pages.finance.income.income.remaining') }}
                    </div>
                    <div class="font-weight-medium">
                      {{ formatIncomeCurrency(selectedServiceLine.remaining_total, selectedServiceLine.currency) }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('pages.finance.income.income.paymentStatus') }}
                    </div>
                    <VChip
                      size="small"
                      variant="tonal"
                    >{{ $t(`finance.paymentStatus.${selectedServiceLine.payment_status}`) }}</VChip>
                  </div>
                </div>
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mt-3"
                >
                  {{ $t('pages.finance.income.income.customerTypeQuantityCurrencyAndExchangeRateComeFromTheSelectedService') }}
                </VAlert>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedIncome.type"
                  :items="typeOptions"
                  :label="$t('common.fields.type')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedIncome.description"
                  :label="$t('common.messages.descriptionPaymentNoteOptional')"
                  :placeholder="$t('common.messages.addAReferencePaymentNoteOrReceiptDetail')"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model.number="selectedIncome.amount"
                  :label="$t('common.fields.amount')"
                  type="number"
                  :step="selectedCurrencyStep"
                  :max="selectedServiceLine ? selectedLineRemaining : undefined"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model.number="selectedIncome.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VSelect
                  v-model="selectedIncome.currency_id"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  @update:model-value="handleCurrencyChange"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model.number="selectedIncome.rate"
                  :label="$t('common.messages.exchangeRate')"
                  type="number"
                  step="0.00000001"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model="selectedIncome.base_amount"
                  :label="$t('common.messages.totalAmount')"
                  type="number"
                  readonly
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="selectedIncome.date"
                  :label="$t('common.fields.date')"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isEditIncomeDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="updateIncomeRecord"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Income Dialog -->
    <VDialog
      v-model="isDeleteIncomeDialogVisible"
      max-width="500px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('common.messages.confirmDelete') }}</span>
        </VCardTitle>

        <VCardText>
          {{ $t('common.confirmations.areYouSureYouWantToDeleteThisIncomeRecord') }}
          <br>
          {{ $t('common.confirmations.thisActionCannotBeUndone') }}
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteIncomeDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            @click="deleteIncomeRecord"
          >
            {{ $t('common.actions.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.income-page { padding-bottom: 32px; }
.filter-deck, .ledger-card { overflow: hidden; border: 0; border-radius: 16px; box-shadow: 0 10px 28px rgb(15 23 42 / .08); }
.filter-deck { background: rgb(var(--v-theme-surface)); }
.filter-deck__content { padding: 14px 18px !important; }
.filter-deck :deep(.v-field) { border-radius: 10px; background: rgb(var(--v-theme-on-surface) / .025); }
.ledger-card__header { min-height: 68px; padding-inline: 20px; }
.ledger-card__title { display: flex; align-items: center; gap: 9px; }
.detail-copy { max-width: 250px; overflow: hidden; color: rgb(var(--v-theme-on-surface) / .76); text-overflow: ellipsis; }
.income-dialog :deep(.v-field__field),
.income-dialog :deep(.v-field__input),
.income-dialog :deep(.v-select__selection) { align-items: center; }
.table-container {
  overflow-x: auto;
}
.ledger-card :deep(thead th) { height: 48px !important; color: rgb(var(--v-theme-on-surface) / .58) !important; font-size: .68rem !important; font-weight: 800 !important; letter-spacing: .075em; text-transform: uppercase; background: rgb(var(--v-theme-on-surface) / .018); }
.ledger-card :deep(tbody td) { height: 66px !important; border-bottom-color: rgb(var(--v-border-color) / .7) !important; }
.ledger-card :deep(tbody tr) { transition: background .16s ease; }
.ledger-card :deep(tbody tr:hover) { background: rgb(var(--v-theme-primary) / .035) !important; }
.ledger-empty { display: flex; align-items: center; gap: 14px; min-height: 176px; padding: 28px 32px; text-align: start; background: linear-gradient(90deg, rgb(var(--v-theme-success) / .07), transparent 58%); }
.ledger-empty :deep(.v-btn) { margin-inline-start: auto; }
@media (max-width: 600px) { .ledger-empty { align-items: flex-start; flex-direction: column; padding: 24px; } .ledger-empty :deep(.v-btn) { margin-inline-start: 0; } }
</style>
