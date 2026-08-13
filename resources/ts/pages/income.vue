<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { VDialog } from 'vuetify/components'
import { getIncomes, createIncome, updateIncome, deleteIncome, type Income, type IncomeCreateData, type IncomeUpdateData } from '@/api/incomes'
import InvoiceApi, { type Invoice } from '@/api/invoice'
import CurrencyApi, { type Currency } from '@/api/currency'
import { apiErrorMessage } from '@/utils/apiErrors'

const incomes = ref<Income[]>([])
const isAddIncomeDialogVisible = ref(false)
const isEditIncomeDialogVisible = ref(false)
const isDeleteIncomeDialogVisible = ref(false)
type IncomeForm = IncomeCreateData & {
  id: number
  total_amount: number
  receipt_number?: string
  invoice_number?: string | null
  currency?: Income['currency']
  created_at?: string
  updated_at?: string
}

const emptyIncomeForm = (): IncomeForm => ({
  id: 0,
  customer_id: null,
  invoice_id: null,
  type: '',
  description: '',
  amount: 0,
  quantity: 1,
  currency_id: 0,
  rate_to_base: 1,
  total_amount: 0,
  date: new Date().toISOString().split('T')[0],
})

const selectedIncome = ref<IncomeForm>(emptyIncomeForm())
const searchQuery = ref('')
const filterType = ref<string | null>(null)
const filterCurrency = ref<number | null>(null)
const isLoading = ref(false)
const invoices = ref<Invoice[]>([])
const currencies = ref<Currency[]>([])
const formError = ref('')

const typeOptions = ['Service', 'Product', 'Investment', 'Other']
const currencyOptions = computed(() => currencies.value
  .filter(currency => currency.isActive)
  .map(currency => ({ title: `${currency.code} — ${currency.name}`, value: currency.id })))

const selectedInvoice = computed(() => {
  const invoiceId = selectedIncome.value?.invoice_id
  return invoiceId ? invoices.value.find(invoice => invoice.id === invoiceId) : undefined
})

const selectedInvoiceBalance = computed(() => selectedInvoice.value?.balance ?? 0)

const totalReceived = computed(() => incomes.value.reduce((total, income) => total + Number(income.total_amount || 0), 0))
const linkedIncomeCount = computed(() => incomes.value.filter(income => income.invoice_id).length)
const averageIncome = computed(() => incomes.value.length ? totalReceived.value / incomes.value.length : 0)
const currentMonthIncome = computed(() => {
  const now = new Date()
  return incomes.value.reduce((total, income) => {
    const date = new Date(income.date)
    return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear()
      ? total + Number(income.total_amount || 0)
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

const setDefaultCurrency = () => {
  if (selectedIncome.value.currency_id) return

  const currency = currencies.value.find(item => item.isBase) ?? currencies.value[0]
  if (currency) {
    selectedIncome.value.currency_id = currency.id
    selectedIncome.value.rate_to_base = currency.rateToBase
  }
}

const fetchIncomes = async () => {
  try {
    isLoading.value = true
    const params: any = {}

    if (searchQuery.value) params.search = searchQuery.value
    if (filterType.value) params.type = filterType.value
    if (filterCurrency.value) params.currency_id = filterCurrency.value

    const response = await getIncomes(params)
    incomes.value = Array.isArray(response) ? response : []
  } catch (error) {
    console.error('Error fetching incomes:', error)
    incomes.value = []
  } finally {
    isLoading.value = false
  }
}

const openAddIncomeDialog = () => {
  formError.value = ''
  selectedIncome.value = emptyIncomeForm()
  Promise.all([fetchUnpaidInvoices(), fetchCurrencies()]).then(() => {
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
  selectedIncome.value = {...income}
  Promise.all([fetchUnpaidInvoices(income.invoice_id), fetchCurrencies()]).then(() => {
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
  if (!invoice) return

  selectedIncome.value.customer_id = invoice.customer_id
  selectedIncome.value.type = 'Service'
  selectedIncome.value.description = `Payment for ${invoice.invoice_number} — ${invoice.customer.name}`
  selectedIncome.value.currency_id = invoice.currency.id
  selectedIncome.value.rate_to_base = invoice.currency.rate
  selectedIncome.value.quantity = 1
  selectedIncome.value.amount = invoice.balance
  calculateTotal()
}

const handleCurrencyChange = (currencyId: number) => {
  const currency = currencies.value.find(item => item.id === currencyId)
  if (currency) {
    selectedIncome.value.rate_to_base = currency.rateToBase
    calculateTotal()
  }
}

const messageFromError = (error: unknown): string => apiErrorMessage(error, 'Unable to save the income record.')

const validateIncomeForm = () => {
  if (!selectedIncome.value.type) return translate('pages.finance.income.income.selectAnIncomeType')
  if (!selectedIncome.value.currency_id) return translate('common.messages.selectACurrency')
  if (!Number.isFinite(Number(selectedIncome.value.amount)) || selectedIncome.value.amount <= 0)
    return translate('pages.finance.income.income.amountMustBeGreaterThanZero')
  if (!Number.isInteger(Number(selectedIncome.value.quantity)) || selectedIncome.value.quantity < 1)
    return translate('pages.finance.income.income.quantityMustBeAWholeNumberOfAtLeastOne')
  if (!Number.isFinite(Number(selectedIncome.value.rate_to_base)) || selectedIncome.value.rate_to_base <= 0)
    return translate('pages.finance.income.income.exchangeRateMustBeGreaterThanZero')
  if (!/^\d{4}-\d{2}-\d{2}$/.test(selectedIncome.value.date)) return translate('pages.finance.income.income.selectAValidIncomeDate')

  if (selectedInvoice.value && selectedIncome.value.amount * selectedIncome.value.quantity > selectedInvoiceBalance.value + 0.00001)
    return `Payment cannot exceed the remaining invoice balance of ${selectedInvoiceBalance.value.toFixed(2)} ${selectedInvoice.value.currency.code}.`

  return ''
}

const openDeleteIncomeDialog = (income: Income) => {
  selectedIncome.value = income
  isDeleteIncomeDialogVisible.value = true
}

const addIncome = async () => {
  formError.value = validateIncomeForm()
  if (formError.value) return

  try {
    formError.value = ''
    const incomeData = {...selectedIncome.value} as IncomeCreateData
    const createdIncome = await createIncome(incomeData)
    incomes.value.unshift(createdIncome)
    isAddIncomeDialogVisible.value = false
  } catch (error) {
    console.error('Error creating income:', error)
    formError.value = messageFromError(error)
  }
}

const updateIncomeRecord = async () => {
  if (selectedIncome.value.id) {
    formError.value = validateIncomeForm()
    if (formError.value) return

    try {
      formError.value = ''
      const incomeData = {...selectedIncome.value} as IncomeUpdateData
      const updatedIncome = await updateIncome(selectedIncome.value.id, incomeData)
      const index = incomes.value.findIndex(i => i.id === updatedIncome.id)
      if (index !== -1) {
        incomes.value[index] = updatedIncome
      }
      isEditIncomeDialogVisible.value = false
    } catch (error) {
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
    } catch (error) {
      console.error('Error deleting income:', error)
    }
  }
}

const calculateTotal = () => {
  selectedIncome.value.total_amount = selectedIncome.value.amount * selectedIncome.value.quantity * selectedIncome.value.rate_to_base
}

const formatCurrency = (value: number | string | undefined | null): string => {
  if (value === undefined || value === null || value === '') return '$0.00'

  // Convert string to number if needed
  const numericValue = typeof value === 'string' ? parseFloat(value) : value

  // Check if the conversion resulted in a valid number
  if (isNaN(numericValue)) return '$0.00'

  return `$${numericValue.toFixed(2)}`
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
  if (!dateString) return ''
  return formatLocalizedDate(`${dateString}T00:00:00`, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

const printTable = () => {
  window.print()
}

onMounted(() => {
  fetchIncomes()
  fetchCurrencies()
})
</script>

<template>
  <div class="income-page">
    <VCard class="income-command-deck mb-6" elevation="0">
      <VCardText class="pa-6 pa-md-8">
        <div class="d-flex flex-wrap align-center justify-space-between gap-5">
          <div>
            <div class="eyebrow mb-2"><VIcon icon="tabler-sparkles" size="15" /> {{ $t('pages.finance.income.income.cashflowCOMMANDCENTER') }}</div>
            <h2 class="text-h4 font-weight-black mb-1">{{ $t('pages.finance.income.income.incomeLedger') }}</h2>
            <p class="text-body-2 opacity-80 mb-0">{{ $t('pages.finance.income.income.liveReceiptActivityInvoiceCollectionsAndCurrencyTotalsInOneView') }}</p>
          </div>
          <div class="d-flex align-center gap-2">
            <VBtn icon="tabler-printer" variant="text" color="white" @click="printTable" :title="$t('pages.finance.income.income.printIncomeRecords')" />
            <VBtn color="white" class="text-primary font-weight-bold" @click="openAddIncomeDialog">
              <VIcon icon="tabler-plus" class="me-1" /> {{ $t('pages.finance.income.income.recordIncome') }}
            </VBtn>
          </div>
        </div>
        <VRow class="mt-4">
          <VCol cols="12" sm="6" lg="3"><div class="metric-tile"><VIcon icon="tabler-wallet" color="success" /><div><div class="metric-label">{{ $t('pages.finance.income.income.recordedValue') }}</div><div class="metric-value">{{ formatCurrency(totalReceived) }}</div></div></div></VCol>
          <VCol cols="12" sm="6" lg="3"><div class="metric-tile"><VIcon icon="tabler-calendar-month" color="info" /><div><div class="metric-label">{{ $t('common.messages.thisMonth') }}</div><div class="metric-value">{{ formatCurrency(currentMonthIncome) }}</div></div></div></VCol>
          <VCol cols="12" sm="6" lg="3"><div class="metric-tile"><VIcon icon="tabler-link" color="warning" /><div><div class="metric-label">{{ $t('pages.finance.income.income.invoicePayments') }}</div><div class="metric-value">{{ linkedIncomeCount }} <span>{{ $t('pages.finance.income.income.linked') }}</span></div></div></div></VCol>
          <VCol cols="12" sm="6" lg="3"><div class="metric-tile"><VIcon icon="tabler-chart-bar" color="secondary" /><div><div class="metric-label">{{ $t('pages.finance.income.income.averageReceipt') }}</div><div class="metric-value">{{ formatCurrency(averageIncome) }}</div></div></div></VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard class="mb-6 filter-deck" elevation="0">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="5">
            <VTextField
              v-model="searchQuery"
              prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.finance.income.income.searchIncome')"
              density="compact"
              clearable
              hide-details
              @keyup.enter="fetchIncomes"
            />
          </VCol>

          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filterType"
              :items="typeOptions"
              :label="$t('common.messages.filterByType')"
              density="compact"
              clearable
              hide-details
              @update:modelValue="fetchIncomes"
            />
          </VCol>

          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filterCurrency"
              :items="currencyOptions"
              :label="$t('common.messages.filterByCurrency')"
              density="compact"
              clearable
              hide-details
              @update:modelValue="fetchIncomes"
            />
          </VCol>

          <VCol cols="12" md="3" class="d-flex align-center justify-md-end">
            <div class="text-caption text-medium-emphasis"><strong class="text-high-emphasis">{{ incomes.length }}</strong> {{ $t('pages.finance.income.income.recordsCurrentlyShown') }}</div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard class="ledger-card" elevation="0">
      <VCardItem>
        <VCardTitle class="font-weight-bold">{{ $t('pages.finance.income.income.receiptStream') }}</VCardTitle>
        <template #append><VChip size="small" color="primary" variant="tonal">{{ $t('pages.finance.income.income.liveLedger') }}</VChip></template>
      </VCardItem>
      <div class="table-container">
        <VDataTable
          :items="incomes"
          :headers="[
            { title: translate('pages.finance.income.income.receipt'), key: 'receipt_number', align: 'start' },
            { title: translate('common.fields.type'), key: 'type', align: 'start' },
            { title: translate('common.messages.details'), key: 'description', align: 'start' },
            { title: translate('pages.finance.income.income.invoice'), key: 'invoice_number', align: 'start' },
            { title: translate('common.fields.amount'), key: 'total_amount', align: 'end' },
            { title: translate('common.fields.date'), key: 'date', align: 'center' },
            { title: translate('common.messages.actions'), key: 'actions', align: 'center', sortable: false }
          ]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
        >
          <template #item.receipt_number="{ item }">
            <div class="font-weight-bold">{{ item.receipt_number || `RCP-${String(item.id).padStart(6, '0')}` }}</div>
            <div class="text-caption text-medium-emphasis">{{ $t('common.messages.currencyQuantity', { currency: item.currency?.code ?? '—', quantity: item.quantity }) }}</div>
          </template>

          <template #item.type="{ item }">
            <VChip size="small" :color="incomeTypeColor(item.type)" variant="tonal">{{ translateBackendValue(item.type) }}</VChip>
          </template>

          <template #item.description="{ item }">
            <div class="detail-copy">{{ item.description || 'No description added' }}</div>
          </template>

          <template #item.invoice_number="{ item }">
            <VChip v-if="item.invoice_number" size="small" color="info" variant="outlined" prepend-icon="tabler-file-invoice">{{ item.invoice_number }}</VChip>
            <span v-else class="text-caption text-medium-emphasis">{{ $t('pages.finance.income.income.standalone') }}</span>
          </template>

          <template #item.total_amount="{ item }">
            <div class="font-weight-bold text-success">{{ formatIncomeCurrency(item.amount * item.quantity, item.currency) }}</div>
            <div class="text-caption text-medium-emphasis">{{ $t('common.messages.quantityExchangeRate', { quantity: item.quantity, rate: formatRate(item.rate_to_base) }) }}</div>
          </template>

          <template #item.date="{ item }">
            <span>{{ formatDate(item.date) }}</span>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                @click="openEditIncomeDialog(item)"
                :title="$t('pages.finance.income.income.editIncomeRecord')"
              />
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="openDeleteIncomeDialog(item)"
                :title="$t('pages.finance.income.income.deleteIncomeRecord')"
              />
            </div>
          </template>
        </VDataTable>
      </div>
    </VCard>

    <!-- Add Income Dialog -->
    <VDialog
      v-model="isAddIncomeDialogVisible"
      max-width="900px"
    >
      <VCard class="income-dialog">
        <VCardTitle class="pa-6 pb-2">
          <div class="d-flex align-center gap-3"><VAvatar color="primary" variant="tonal" icon="tabler-receipt" /><div><div class="text-h5 font-weight-bold">{{ $t('pages.finance.income.income.recordIncome2') }}</div><div class="text-caption text-medium-emphasis">{{ $t('pages.finance.income.income.captureAStandaloneReceiptOrApplyPaymentToAnInvoice') }}</div></div></div>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
              {{ formError }}
            </VAlert>
            <VRow>
              <VCol cols="12">
                <VSelect
                  v-model="selectedIncome.invoice_id"
                  :items="invoices"
                  item-title="invoice_number"
                  item-value="id"
                  :label="$t('common.messages.associatedInvoiceOptional')"
                  clearable
                  @update:modelValue="handleInvoiceChange"
                />
                <div v-if="selectedInvoice" class="invoice-payment-panel mt-2">
                  <div><div class="text-caption text-medium-emphasis">{{ $t('common.messages.selectedInvoice') }}</div><div class="font-weight-bold">{{ selectedInvoice.invoice_number }}</div></div>
                  <VDivider vertical />
                  <div><div class="text-caption text-medium-emphasis">{{ $t('common.messages.remainingBalance') }}</div><div class="text-h6 font-weight-black text-primary">{{ selectedInvoice.currency.symbol }}{{ selectedInvoiceBalance.toFixed(2) }}</div></div>
                  <div class="ms-auto text-right"><div class="text-caption text-medium-emphasis">{{ $t('common.messages.alreadyPaid') }}</div><div class="font-weight-bold">{{ selectedInvoice.currency.symbol }}{{ selectedInvoice.paid_amount.toFixed(2) }}</div></div>
                </div>
                <VAlert v-if="selectedInvoice" type="info" variant="tonal" density="compact" class="mt-3">
                  {{ $t('pages.finance.income.income.customerTypeQuantityCurrencyAndExchangeRateComeFromTheInvoiceEnterOnly') }}
                </VAlert>
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.type"
                  :items="typeOptions"
                  :label="$t('common.fields.type')"
                  :disabled="Boolean(selectedInvoice)"
                  required
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
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.amount"
                  :label="$t('common.fields.amount')"
                  type="number"
                  step="0.01"
                  :max="selectedInvoice ? selectedInvoiceBalance : undefined"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  :disabled="Boolean(selectedInvoice)"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.currency_id"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :disabled="Boolean(selectedInvoice)"
                  @update:modelValue="handleCurrencyChange"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.rate_to_base"
                  :label="$t('common.messages.exchangeRate')"
                  type="number"
                  step="0.01"
                  :disabled="Boolean(selectedInvoice)"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedIncome.total_amount"
                  :label="$t('common.messages.totalAmount')"
                  type="number"
                  readonly
                />
              </VCol>
              <VCol cols="12" md="6">
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
          <div class="d-flex align-center gap-3"><VAvatar color="primary" variant="tonal" icon="tabler-pencil" /><div><div class="text-h5 font-weight-bold">{{ $t('pages.finance.income.income.editIncome') }}</div><div class="text-caption text-medium-emphasis">{{ $t('pages.finance.income.income.updateReceiptDetailsOrItsInvoiceAllocation') }}</div></div></div>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
              {{ formError }}
            </VAlert>
            <VRow>
              <VCol cols="12">
                <VSelect
                  v-model="selectedIncome.invoice_id"
                  :items="invoices"
                  item-title="invoice_number"
                  item-value="id"
                  :label="$t('common.messages.associatedInvoiceOptional')"
                  clearable
                  @update:modelValue="handleInvoiceChange"
                />
                <div v-if="selectedInvoice" class="invoice-payment-panel mt-2">
                  <div><div class="text-caption text-medium-emphasis">{{ $t('common.messages.selectedInvoice') }}</div><div class="font-weight-bold">{{ selectedInvoice.invoice_number }}</div></div>
                  <VDivider vertical />
                  <div><div class="text-caption text-medium-emphasis">{{ $t('common.messages.remainingBalance') }}</div><div class="text-h6 font-weight-black text-primary">{{ selectedInvoice.currency.symbol }}{{ selectedInvoiceBalance.toFixed(2) }}</div></div>
                  <div class="ms-auto text-right"><div class="text-caption text-medium-emphasis">{{ $t('common.messages.alreadyPaid') }}</div><div class="font-weight-bold">{{ selectedInvoice.currency.symbol }}{{ selectedInvoice.paid_amount.toFixed(2) }}</div></div>
                </div>
                <VAlert v-if="selectedInvoice" type="info" variant="tonal" density="compact" class="mt-3">
                  {{ $t('pages.finance.income.income.customerTypeQuantityCurrencyAndExchangeRateAreControlledByTheLinkedInv') }}
                </VAlert>
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.type"
                  :items="typeOptions"
                  :label="$t('common.fields.type')"
                  :disabled="Boolean(selectedInvoice)"
                  required
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
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.amount"
                  :label="$t('common.fields.amount')"
                  type="number"
                  step="0.01"
                  :max="selectedInvoice ? selectedInvoiceBalance : undefined"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  :disabled="Boolean(selectedInvoice)"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.currency_id"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :disabled="Boolean(selectedInvoice)"
                  @update:modelValue="handleCurrencyChange"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.rate_to_base"
                  :label="$t('common.messages.exchangeRate')"
                  type="number"
                  step="0.01"
                  :disabled="Boolean(selectedInvoice)"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedIncome.total_amount"
                  :label="$t('common.messages.totalAmount')"
                  type="number"
                  readonly
                />
              </VCol>
              <VCol cols="12" md="6">
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
.income-page { padding-bottom: 24px; }
.income-command-deck {
  color: white;
  overflow: hidden;
  background:
    radial-gradient(circle at 90% 5%, rgb(var(--v-theme-primary) / .65), transparent 27%),
    radial-gradient(circle at 6% 100%, #0ea5e9aa, transparent 35%),
    linear-gradient(115deg, #18233f, #1e2f58 58%, #172b4d);
}
.eyebrow { display: flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 800; letter-spacing: .12em; opacity: .78; }
.metric-tile { min-height: 86px; display: flex; align-items: center; gap: 13px; padding: 15px; border: 1px solid rgb(255 255 255 / .14); border-radius: 14px; background: rgb(255 255 255 / .09); backdrop-filter: blur(12px); }
.metric-tile :deep(.v-icon) { padding: 10px; border-radius: 10px; background: rgb(255 255 255 / .94); }
.metric-label { font-size: .72rem; letter-spacing: .04em; opacity: .72; text-transform: uppercase; }
.metric-value { font-size: 1.15rem; font-weight: 800; line-height: 1.4; }
.metric-value span { font-size: .7rem; font-weight: 500; opacity: .75; }
.filter-deck, .ledger-card { border: 1px solid rgb(var(--v-border-color)); }
.detail-copy { max-width: 250px; overflow: hidden; text-overflow: ellipsis; }
.invoice-payment-panel { display: flex; align-items: center; gap: 18px; padding: 13px 16px; border: 1px solid rgb(var(--v-theme-primary) / .22); border-radius: 12px; background: rgb(var(--v-theme-primary) / .06); }
.income-dialog { overflow: hidden; }
.table-container {
  overflow-x: auto;
}
@media (max-width: 600px) { .invoice-payment-panel { align-items: flex-start; flex-direction: column; gap: 7px; } .invoice-payment-panel :deep(.v-divider) { display: none; } }
</style>
