<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { VDialog } from 'vuetify/components'
import { type Income, type IncomeCreateData, type IncomeUpdateData, createIncome, deleteIncome, getIncomes, updateIncome } from '@/api/incomes'
import InvoiceApi, { type Invoice } from '@/api/invoice'
import CurrencyApi, { type Currency } from '@/api/currency'
import CompanyApi, { type Company } from '@/api/company'
import { apiErrorMessage } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import InvoicePaymentContext from '@/components/income/InvoicePaymentContext.vue'
import InvoiceSelect from '@/components/income/InvoiceSelect.vue'

const incomes = ref<Income[]>([])
const isAddIncomeDialogVisible = ref(false)
const isEditIncomeDialogVisible = ref(false)
const isDeleteIncomeDialogVisible = ref(false)
type IncomeForm = IncomeCreateData & {
  id: number
  base_amount: number
  receipt_number?: string
  invoice_number?: string | null
  currency?: Income['currency']
  created_at?: string
  updated_at?: string
}

const emptyIncomeForm = (): IncomeForm => ({
  id: 0,
  customer_id: null,
  company_id: null,
  invoice_id: null,
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
const isLoading = ref(false)
const invoices = ref<Invoice[]>([])
const currencies = ref<Currency[]>([])
const companies = ref<Company[]>([])
const formError = ref('')

const typeOptions = ['Service', 'Product', 'Investment', 'Other']

const filterTypeOptions = computed(() => typeOptions.map(value => ({
  title: translateBackendValue(value),
  value,
})))

const currencyOptions = computed(() => currencies.value
  .filter(currency => currency.isActive)
  .map(currency => ({ title: `${currency.code} — ${currency.name}`, value: currency.id })))

const selectedInvoice = computed(() => {
  const invoiceId = selectedIncome.value?.invoice_id

  return invoiceId ? invoices.value.find(invoice => invoice.id === invoiceId) : undefined
})

const companyOptions = computed(() => companies.value
  .filter(company => company.is_active)
  .map(company => ({ title: company.trade_name || company.legal_name, value: company.id })))

const selectedInvoiceBalance = computed(() => selectedInvoice.value?.balance ?? 0)
const selectedCurrency = computed(() => currencies.value.find(currency => currency.id === selectedIncome.value.currency_id) ?? null)
const selectedCurrencyDecimals = computed(() => selectedInvoice.value?.currency.decimalPlaces ?? selectedCurrency.value?.decimalPlaces ?? 2)
const selectedCurrencyStep = computed(() => 10 ** -selectedCurrencyDecimals.value)
const currentBaseCurrency = computed(() => currencies.value.find(currency => currency.isBase) ?? null)

const totalReceived = computed(() => incomes.value.reduce((total, income) => total + Number(income.currentBaseAmount ?? income.base_amount ?? 0), 0))
const linkedIncomeCount = computed(() => incomes.value.filter(income => income.invoice_id).length)
const averageIncome = computed(() => incomes.value.length ? totalReceived.value / incomes.value.length : 0)

const currentMonthIncome = computed(() => {
  const now = new Date()

  return incomes.value.reduce((total, income) => {
    const date = new Date(income.date)

    return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear()
      ? total + Number(income.currentBaseAmount ?? income.base_amount ?? 0)
      : total
  }, 0)
})

const fetchUnpaidInvoices = async (includeInvoiceId?: number | null) => {
  invoices.value = await InvoiceApi.getAll({ unpaid: true })

  if (includeInvoiceId && !invoices.value.some(invoice => invoice.id === includeInvoiceId))
    invoices.value.push(await InvoiceApi.getById(includeInvoiceId))
}

const fetchCurrencies = async () => {
  const response = await CurrencyApi.getAll()

  currencies.value = response.data.filter(currency => currency.isActive)
}

const fetchCompanies = async () => {
  companies.value = await CompanyApi.getAll()
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
  selectedIncome.value = emptyIncomeForm()
  Promise.all([fetchUnpaidInvoices(), fetchCurrencies(), fetchCompanies()]).then(() => {
    setDefaultCurrency()
    isAddIncomeDialogVisible.value = true
  }).catch(error => {
    console.error('Error fetching unpaid invoices:', error)
    formError.value = translate('common.messages.unableToLoadInvoices')
    isAddIncomeDialogVisible.value = true
  })
}

const openEditIncomeDialog = (income: Income) => {
  formError.value = ''
  selectedIncome.value = { ...income }
  Promise.all([fetchUnpaidInvoices(income.invoice_id), fetchCurrencies(), fetchCompanies()]).then(() => {
    isEditIncomeDialogVisible.value = true
  }).catch(error => {
    console.error('Error fetching unpaid invoices:', error)
    formError.value = translate('common.messages.unableToLoadInvoices')
    isEditIncomeDialogVisible.value = true
  })
}

const handleInvoiceChange = (invoiceId: number | null) => {
  if (!invoiceId) {
    selectedIncome.value.customer_id = null

    return
  }

  const invoice = invoices.value.find(item => item.id === invoiceId)
  if (!invoice)
    return

  selectedIncome.value.customer_id = invoice.customer_id
  selectedIncome.value.type = 'Service'
  selectedIncome.value.description = `Payment for ${invoice.invoice_number} — ${invoice.customer.name}`
  selectedIncome.value.currency_id = invoice.currency.id
  selectedIncome.value.rate = invoice.currency.rate
  selectedIncome.value.quantity = 1
  selectedIncome.value.amount = invoice.balance
  calculateTotal()
}

const handleCurrencyChange = (currencyId: number) => {
  const currency = currencies.value.find(item => item.id === currencyId)
  if (currency) {
    selectedIncome.value.rate = currency.rate
    calculateTotal()
  }
}

const formatSelectedInvoiceAmount = (amount: number) => amount.toFixed(selectedCurrencyDecimals.value)

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

  if (selectedInvoice.value && selectedIncome.value.amount * selectedIncome.value.quantity > selectedInvoiceBalance.value + selectedCurrencyStep.value / 2)
    return `Payment cannot exceed the remaining invoice balance of ${formatSelectedInvoiceAmount(selectedInvoiceBalance.value)} ${selectedInvoice.value.currency.shortname}.`

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

      const incomeData = { ...selectedIncome.value } as IncomeUpdateData
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

const markSelectedInvoicePaid = async () => {
  if (!selectedInvoice.value?.can_mark_paid)
    return

  try {
    const updated = await InvoiceApi.markPaid(selectedInvoice.value.id)

    invoices.value = invoices.value.map(invoice => invoice.id === updated.id ? updated : invoice)
  }
  catch (error) {
    formError.value = messageFromError(error)
  }
}

const formatCurrency = (value: number | string | undefined | null): string => {
  const numericValue = Number(value ?? 0)
  const currency = currentBaseCurrency.value
  const decimals = currency?.decimalPlaces ?? 2
  const formatted = Number.isFinite(numericValue) ? numericValue.toFixed(decimals) : (0).toFixed(decimals)

  return `${currency?.symbol ?? ''}${formatted}${currency?.code ? ` ${currency.code}` : ''}`.trim()
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
  fetchIncomes()
  fetchCurrencies()
  fetchCompanies()
})
</script>

<template>
  <div class="income-page">
    <VCard
      class="income-command-deck mb-6"
      elevation="0"
    >
      <VCardText class="pa-6 pa-md-8">
        <div class="hero-topline d-flex flex-wrap align-center justify-space-between gap-5">
          <div class="hero-copy">
            <div class="eyebrow mb-2">
              <VIcon
                icon="tabler-sparkles"
                size="15"
              /> {{ $t('pages.finance.income.income.cashflowCOMMANDCENTER') }}
            </div>
            <h2 class="text-h4 font-weight-black mb-1">
              {{ $t('pages.finance.income.income.incomeLedger') }}
            </h2>
            <p class="text-body-2 opacity-80 mb-0">
              {{ $t('pages.finance.income.income.liveReceiptActivityInvoiceCollectionsAndCurrencyTotalsInOneView') }}
            </p>
          </div>
          <div class="hero-actions d-flex align-center gap-2">
            <VBtn
              class="hero-print"
              icon
              variant="text"
              color="white"
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
            <VBtn
              color="white"
              class="hero-primary-action income-primary-action font-weight-bold"
              @click="openAddIncomeDialog"
            >
              <VIcon
                icon="tabler-plus"
                class="me-1"
              /> {{ $t('pages.finance.income.income.recordIncome') }}
            </VBtn>
          </div>
        </div>
        <VRow class="mt-4">
          <VCol
            cols="12"
            sm="6"
            lg="3"
          >
            <div class="metric-tile metric-tile--success">
              <VIcon
                icon="tabler-wallet"
                color="success"
              /><div>
                <div class="metric-label">
                  {{ $t('pages.finance.income.income.recordedValue') }}
                </div><div class="metric-value">
                  {{ formatCurrency(totalReceived) }}
                </div>
              </div>
            </div>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            lg="3"
          >
            <div class="metric-tile metric-tile--info">
              <VIcon
                icon="tabler-calendar-month"
                color="info"
              /><div>
                <div class="metric-label">
                  {{ $t('common.messages.thisMonth') }}
                </div><div class="metric-value">
                  {{ formatCurrency(currentMonthIncome) }}
                </div>
              </div>
            </div>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            lg="3"
          >
            <div class="metric-tile metric-tile--warning">
              <VIcon
                icon="tabler-link"
                color="warning"
              /><div>
                <div class="metric-label">
                  {{ $t('pages.finance.income.income.invoicePayments') }}
                </div><div class="metric-value">
                  {{ linkedIncomeCount }} <span>{{ $t('pages.finance.income.income.linked') }}</span>
                </div>
              </div>
            </div>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            lg="3"
          >
            <div class="metric-tile metric-tile--secondary">
              <VIcon
                icon="tabler-chart-bar"
                color="secondary"
              /><div>
                <div class="metric-label">
                  {{ $t('pages.finance.income.income.averageReceipt') }}
                </div><div class="metric-value">
                  {{ formatCurrency(averageIncome) }}
                </div>
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

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
              :active="Boolean(filterType || filterCurrency)"
              @reset="filterType = null; filterCurrency = null; fetchIncomes()"
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
            { title: translate('pages.finance.income.income.invoice'), key: 'invoice_number', align: 'start' },
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
          <template #item.receipt_number="{ item }">
            <div class="font-weight-bold">
              {{ item.receipt_number || `RCP-${String(item.id).padStart(6, '0')}` }}
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
              {{ translateBackendValue(item.type) }}
            </VChip>
          </template>

          <template #item.description="{ item }">
            <div class="detail-copy">
              {{ item.description || 'No description added' }}
            </div>
          </template>

          <template #item.invoice_number="{ item }">
            <VChip
              v-if="item.invoice_number"
              size="small"
              color="info"
              variant="outlined"
              prepend-icon="tabler-file-invoice"
            >
              {{ item.invoice_number }}
            </VChip>
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
              <FullDetailsButton :item="item" />
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

    <PrintableListReport
      :title="$t('common.crmUi.printReports.income.title')"
      :subtitle="$t('common.crmUi.printReports.income.subtitle')"
      :columns="[$t('common.crmUi.printReports.columns.receipt'), $t('common.crmUi.printReports.columns.type'), $t('common.crmUi.printReports.columns.details'), $t('common.crmUi.printReports.columns.invoice'), $t('common.crmUi.printReports.columns.amount'), $t('common.crmUi.printReports.columns.date')]"
      :rows="incomes.map(income => [income.receipt_number, income.type, income.description, income.invoice_number, formatIncomeCurrency(income.amount * income.quantity, income.currency), formatDate(income.date)])"
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
                {{ $t('pages.finance.income.income.captureAStandaloneReceiptOrApplyPaymentToAnInvoice') }}
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
              <VCol cols="12">
                <InvoiceSelect
                  v-model="selectedIncome.invoice_id"
                  :invoices="invoices"
                  @change="handleInvoiceChange"
                />
                <InvoicePaymentContext
                  v-if="selectedInvoice"
                  :invoice="selectedInvoice"
                />
                <VAlert
                  v-if="selectedInvoice"
                  :type="selectedInvoice.can_mark_paid ? 'success' : 'info'"
                  variant="tonal"
                  density="compact"
                  class="mt-3"
                >
                  <div class="d-flex align-center justify-space-between gap-3">
                    <span v-if="selectedInvoice.can_mark_paid">{{ $t('pages.finance.income.income.smallBalanceCanMarkPaid') }}</span>
                    <span v-else>{{ $t('pages.finance.income.income.markPaidThresholdHint') }}</span>
                    <VBtn
                      size="small"
                      color="success"
                      variant="flat"
                      :disabled="!selectedInvoice.can_mark_paid"
                      @click="markSelectedInvoicePaid"
                    >
                      {{ $t('pages.finance.income.income.markPaid') }}
                    </VBtn>
                  </div>
                </VAlert>
                <VAlert
                  v-if="selectedInvoice"
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mt-3"
                >
                  {{ $t('pages.finance.income.income.customerTypeQuantityCurrencyAndExchangeRateComeFromTheInvoiceEnterOnly') }}
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
                  :disabled="Boolean(selectedInvoice)"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedIncome.company_id"
                  :items="companyOptions"
                  label="Company (optional)"
                  :disabled="Boolean(selectedInvoice)"
                  clearable
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="selectedIncome.description"
                  :label="$t('common.messages.descriptionPaymentNoteOptional')"
                  :placeholder="$t('common.messages.addAReferencePaymentNoteOrReceiptDetail')"
                  rows="2"
                  auto-grow
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedIncome.amount"
                  :label="$t('common.fields.amount')"
                  type="number"
                  :step="selectedCurrencyStep"
                  :max="selectedInvoice ? selectedInvoiceBalance : undefined"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedIncome.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  :disabled="Boolean(selectedInvoice)"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedIncome.currency_id"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :disabled="Boolean(selectedInvoice)"
                  required
                  @update:model-value="handleCurrencyChange"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
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
                md="6"
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
                md="6"
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
                {{ $t('pages.finance.income.income.updateReceiptDetailsOrItsInvoiceAllocation') }}
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
              <VCol cols="12">
                <InvoiceSelect
                  v-model="selectedIncome.invoice_id"
                  :invoices="invoices"
                  @change="handleInvoiceChange"
                />
                <InvoicePaymentContext
                  v-if="selectedInvoice"
                  :invoice="selectedInvoice"
                />
                <VAlert
                  v-if="selectedInvoice"
                  :type="selectedInvoice.can_mark_paid ? 'success' : 'info'"
                  variant="tonal"
                  density="compact"
                  class="mt-3"
                >
                  <div class="d-flex align-center justify-space-between gap-3">
                    <span v-if="selectedInvoice.can_mark_paid">{{ $t('pages.finance.income.income.smallBalanceCanMarkPaid') }}</span>
                    <span v-else>{{ $t('pages.finance.income.income.markPaidThresholdHint') }}</span>
                    <VBtn
                      size="small"
                      color="success"
                      variant="flat"
                      :disabled="!selectedInvoice.can_mark_paid"
                      @click="markSelectedInvoicePaid"
                    >
                      {{ $t('pages.finance.income.income.markPaid') }}
                    </VBtn>
                  </div>
                </VAlert>
                <VAlert
                  v-if="selectedInvoice"
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mt-3"
                >
                  {{ $t('pages.finance.income.income.customerTypeQuantityCurrencyAndExchangeRateAreControlledByTheLinkedInv') }}
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
                  :disabled="Boolean(selectedInvoice)"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedIncome.company_id"
                  :items="companyOptions"
                  label="Company (optional)"
                  :disabled="Boolean(selectedInvoice)"
                  clearable
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="selectedIncome.description"
                  :label="$t('common.messages.descriptionPaymentNoteOptional')"
                  :placeholder="$t('common.messages.addAReferencePaymentNoteOrReceiptDetail')"
                  rows="2"
                  auto-grow
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedIncome.amount"
                  :label="$t('common.fields.amount')"
                  type="number"
                  :step="selectedCurrencyStep"
                  :max="selectedInvoice ? selectedInvoiceBalance : undefined"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedIncome.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  :disabled="Boolean(selectedInvoice)"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedIncome.currency_id"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :disabled="Boolean(selectedInvoice)"
                  required
                  @update:model-value="handleCurrencyChange"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
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
                md="6"
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
                md="6"
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
.income-command-deck {
  color: white;
  border: 1px solid rgb(52 211 153 / .18);
  border-radius: 20px;
  box-shadow: 0 18px 45px rgb(23 43 77 / .2);
  background: linear-gradient(115deg, #064e3b, #047857 55%, #14532d);
}
/* Do not use :global(.v-theme--light) here: Vue reduces that mixed scoped
   selector to bare .v-theme--light and paints every Vuetify overlay. */
.income-command-deck.v-theme--light { color: #16312a; border-color: #a7f3d0; box-shadow: 0 16px 34px rgb(15 118 110 / .12); background: #e9fbf3; }
.income-command-deck.v-theme--light .metric-tile { border-color: #c9ecdc; background: #fffefa; box-shadow: inset 0 1px 0 #fff; }
.income-command-deck.v-theme--light .hero-print { color: #166534 !important; border-color: #86d9b5; }
.hero-copy { max-width: 660px; }
.hero-copy h2 { letter-spacing: -.035em; }
.hero-actions { flex-shrink: 0; }
.hero-print { border: 1px solid rgb(255 255 255 / .22); }
.hero-primary-action { min-height: 42px; padding-inline: 18px !important; border-radius: 10px; box-shadow: 0 8px 22px rgb(8 20 48 / .22); }
.income-primary-action { color: #86efac !important; }
.income-primary-action :deep(.v-icon) { color: #86efac !important; }
.income-command-deck.v-theme--light .income-primary-action,
.income-command-deck.v-theme--light .income-primary-action :deep(.v-icon) { color: #166534 !important; }
.eyebrow { display: flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 800; letter-spacing: .12em; opacity: .78; }
.metric-tile { min-height: 92px; display: flex; align-items: center; gap: 13px; padding: 16px; border: 1px solid rgb(255 255 255 / .15); border-radius: 15px; background: linear-gradient(135deg, rgb(255 255 255 / .14), rgb(255 255 255 / .055)); box-shadow: inset 0 1px 0 rgb(255 255 255 / .1); transition: transform .2s ease, background .2s ease; }
.metric-tile:hover { transform: translateY(-3px); background: rgb(255 255 255 / .16); }
.metric-tile :deep(.v-icon) { padding: 10px; border-radius: 11px; background: rgb(255 255 255 / .94); box-shadow: 0 5px 12px rgb(8 20 48 / .16); }
.metric-label { font-size: .72rem; letter-spacing: .04em; opacity: .72; text-transform: uppercase; }
.metric-value { font-size: 1.15rem; font-weight: 800; line-height: 1.4; }
.metric-value span { font-size: .7rem; font-weight: 500; opacity: .75; }
.filter-deck, .ledger-card { overflow: hidden; border: 0; border-radius: 16px; box-shadow: 0 10px 28px rgb(15 23 42 / .08); }
.filter-deck { background: rgb(var(--v-theme-surface)); }
.filter-deck__content { padding: 14px 18px !important; }
.filter-deck :deep(.v-field) { border-radius: 10px; background: rgb(var(--v-theme-on-surface) / .025); }
.ledger-card__header { min-height: 68px; padding-inline: 20px; }
.ledger-card__title { display: flex; align-items: center; gap: 9px; }
.detail-copy { max-width: 250px; overflow: hidden; color: rgb(var(--v-theme-on-surface) / .76); text-overflow: ellipsis; }
.invoice-payment-panel { display: flex; align-items: center; gap: 18px; padding: 13px 16px; border: 1px solid rgb(var(--v-theme-primary) / .22); border-radius: 12px; background: rgb(var(--v-theme-primary) / .06); }
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
@media (max-width: 600px) { .invoice-payment-panel { align-items: flex-start; flex-direction: column; gap: 7px; } .invoice-payment-panel :deep(.v-divider) { display: none; } }
@media (max-width: 600px) { .ledger-empty { align-items: flex-start; flex-direction: column; padding: 24px; } .ledger-empty :deep(.v-btn) { margin-inline-start: 0; } }
</style>
