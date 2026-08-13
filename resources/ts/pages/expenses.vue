<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { VDialog } from 'vuetify/components'
import CurrencyApi, { type Currency } from '@/api/currency'
import { createExpense, deleteExpense, getExpenses, updateExpense, type Expense, type ExpenseCreateData, type ExpenseUpdateData } from '@/api/expenses'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'

const expenses = ref<Expense[]>([])
const currencies = ref<Currency[]>([])
const selectedExpense = ref<Expense | ExpenseCreateData | null>(null)
const isAddExpenseDialogVisible = ref(false)
const isEditExpenseDialogVisible = ref(false)
const isDeleteExpenseDialogVisible = ref(false)
const searchQuery = ref('')
const filterType = ref<string | null>(null)
const filterCurrency = ref<number | null>(null)
const isLoading = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string>>({})

const typeOptions = ['Utilities', 'Software/SaaS', 'Marketing', 'Office Supplies', 'Travel & Dining', 'Salaries']
const currencyOptions = computed(() => currencies.value.map(currency => ({
  title: `${currency.code} — ${currency.name}`,
  value: currency.id,
})))
const totalSpent = computed(() => expenses.value.reduce((total, expense) => total + Number(expense.total_amount || 0), 0))
const averageExpense = computed(() => expenses.value.length ? totalSpent.value / expenses.value.length : 0)
const currentMonthSpent = computed(() => {
  const now = new Date()
  return expenses.value.reduce((total, expense) => {
    const date = new Date(`${expense.date}T00:00:00`)
    return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear()
      ? total + Number(expense.total_amount || 0)
      : total
  }, 0)
})
const largestExpense = computed(() => Math.max(0, ...expenses.value.map(expense => Number(expense.total_amount || 0))))

const fetchCurrencies = async () => {
  const response = await CurrencyApi.getAll()
  currencies.value = response.data.filter(currency => currency.isActive)
}

const fetchExpenses = async () => {
  try {
    isLoading.value = true
    const params: { search?: string; type?: string; currency_id?: number } = {}
    if (searchQuery.value) params.search = searchQuery.value
    if (filterType.value) params.type = filterType.value
    if (filterCurrency.value) params.currency_id = filterCurrency.value
    expenses.value = await getExpenses(params)
  } catch (error) {
    console.error('Error fetching expenses:', error)
    expenses.value = []
  } finally {
    isLoading.value = false
  }
}

const setDefaultCurrency = () => {
  if (!selectedExpense.value || selectedExpense.value.currency_id) return
  const currency = currencies.value.find(item => item.isBase) ?? currencies.value[0]
  if (currency) {
    selectedExpense.value.currency_id = currency.id
    selectedExpense.value.rate_to_base = currency.rateToBase
  }
}

const openAddExpenseDialog = async () => {
  formError.value = ''
  fieldErrors.value = {}
  selectedExpense.value = {
    customer_id: null,
    currency_id: 0,
    type: '',
    description: '',
    amount: 0,
    quantity: 1,
    rate_to_base: 1,
    date: new Date().toISOString().slice(0, 10),
  }
  await fetchCurrencies()
  setDefaultCurrency()
  isAddExpenseDialogVisible.value = true
}

const openEditExpenseDialog = async (expense: Expense) => {
  formError.value = ''
  fieldErrors.value = {}
  selectedExpense.value = { ...expense }
  await fetchCurrencies()
  isEditExpenseDialogVisible.value = true
}

const openDeleteExpenseDialog = (expense: Expense) => {
  selectedExpense.value = expense
  isDeleteExpenseDialogVisible.value = true
}

const handleCurrencyChange = (currencyId: number) => {
  if (!selectedExpense.value) return
  const currency = currencies.value.find(item => item.id === currencyId)
  if (currency) {
    selectedExpense.value.rate_to_base = currency.rateToBase
    calculateTotal()
  }
}

const calculateTotal = () => {
  if (selectedExpense.value)
    selectedExpense.value.total_amount = Number(selectedExpense.value.amount) * Number(selectedExpense.value.quantity) * Number(selectedExpense.value.rate_to_base)
}

const validateExpense = () => {
  if (!selectedExpense.value) return false

  const expense = selectedExpense.value
  fieldErrors.value = {}
  if (!expense.type?.trim()) fieldErrors.value.type = translate('pages.finance.expenses.expenses.selectAnExpenseType')
  if (!Number.isInteger(Number(expense.currency_id)) || Number(expense.currency_id) < 1) fieldErrors.value.currency_id = translate('common.messages.selectACurrency')
  if (!expense.description?.trim()) fieldErrors.value.description = translate('pages.finance.expenses.expenses.enterADescription')
  if (!Number.isFinite(Number(expense.amount)) || Number(expense.amount) < 0) fieldErrors.value.amount = translate('pages.finance.expenses.expenses.enterAnAmountOfZeroOrMore')
  if (!Number.isInteger(Number(expense.quantity)) || Number(expense.quantity) < 1) fieldErrors.value.quantity = translate('pages.finance.expenses.expenses.enterAWholeQuantityOfAtLeast1')
  if (!expense.date || Number.isNaN(Date.parse(expense.date))) fieldErrors.value.date = translate('pages.finance.expenses.expenses.chooseAValidExpenseDate')

  return Object.keys(fieldErrors.value).length === 0
}

const saveExpense = async (isEditing = false) => {
  if (!selectedExpense.value) return
  if (!validateExpense()) {
    formError.value = translate('pages.finance.expenses.expenses.pleaseCorrectTheHighlightedFields')
    return
  }

  try {
    formError.value = ''
    fieldErrors.value = {}
    const payload = {
      customer_id: selectedExpense.value.customer_id,
      currency_id: selectedExpense.value.currency_id,
      type: selectedExpense.value.type,
      description: selectedExpense.value.description,
      amount: selectedExpense.value.amount,
      quantity: selectedExpense.value.quantity,
      rate_to_base: selectedExpense.value.rate_to_base,
      date: selectedExpense.value.date,
    }
    const savedExpense = isEditing && 'id' in selectedExpense.value
      ? await updateExpense(selectedExpense.value.id, payload as ExpenseUpdateData)
      : await createExpense(payload as ExpenseCreateData)
    const index = expenses.value.findIndex(expense => expense.id === savedExpense.id)
    if (index >= 0) expenses.value[index] = savedExpense
    else expenses.value.unshift(savedExpense)
    isAddExpenseDialogVisible.value = false
    isEditExpenseDialogVisible.value = false
  } catch (error) {
    console.error('Error saving expense:', error)
    fieldErrors.value = apiFieldErrors(error)
    formError.value = apiErrorMessage(error, 'Unable to save the expense record.')
  }
}

const deleteExpenseRecord = async () => {
  if (!selectedExpense.value || !('id' in selectedExpense.value)) return
  try {
    await deleteExpense(selectedExpense.value.id)
    expenses.value = expenses.value.filter(expense => expense.id !== selectedExpense.value?.id)
    isDeleteExpenseDialogVisible.value = false
  } catch (error) {
    console.error('Error deleting expense:', error)
    formError.value = apiErrorMessage(error, 'Unable to delete the expense record.')
  }
}

const formatBaseCurrency = (value: number) => `$${Number(value || 0).toFixed(2)}`
const formatExpenseCurrency = (value: number, currency: Expense['currency']) => {
  const amount = Number(value || 0).toFixed(currency?.decimalPlaces ?? 2)
  return currency?.symbol ? `${currency.symbol}${amount}` : `${amount} ${currency?.code ?? ''}`.trim()
}
const formatDate = (date: string) => formatLocalizedDate(`${date}T00:00:00`, { day: '2-digit', month: 'short', year: 'numeric' })
const expenseTypeColor = (type: string) => ({ Utilities: 'info', 'Software/SaaS': 'primary', Marketing: 'warning', 'Office Supplies': 'secondary', 'Travel & Dining': 'success', Salaries: 'error' }[type] ?? 'secondary')
const printTable = () => window.print()

onMounted(() => {
  fetchExpenses()
  fetchCurrencies()
})
</script>

<template>
  <div class="expense-page">
    <VCard class="expense-command-deck mb-6" elevation="0">
      <VCardText class="pa-6 pa-md-8">
        <div class="d-flex flex-wrap align-center justify-space-between gap-5">
          <div>
            <div class="eyebrow mb-2"><VIcon icon="tabler-activity-heartbeat" size="15" /> {{ $t('pages.finance.expenses.expenses.spendCONTROLCENTER') }}</div>
            <h2 class="text-h4 font-weight-black mb-1">{{ $t('pages.finance.expenses.expenses.expenseLedger') }}</h2>
            <p class="text-body-2 opacity-80 mb-0">{{ $t('pages.finance.expenses.expenses.trackOutgoingCashRecurringCostsAndSpendPatternsInOneFocusedView') }}</p>
          </div>
          <div class="d-flex align-center gap-2">
            <VBtn icon="tabler-printer" variant="text" color="white" @click="printTable" :title="$t('pages.finance.expenses.expenses.printExpenseRecords')" />
            <VBtn color="white" class="text-error font-weight-bold" @click="openAddExpenseDialog"><VIcon icon="tabler-plus" class="me-1" /> {{ $t('pages.finance.expenses.expenses.recordExpense') }}</VBtn>
          </div>
        </div>
        <VRow class="mt-4">
          <VCol cols="12" sm="6" lg="3"><div class="metric-tile"><VIcon icon="tabler-credit-card-pay" color="error" /><div><div class="metric-label">{{ $t('pages.finance.expenses.expenses.recordedBaseValue') }}</div><div class="metric-value">{{ formatBaseCurrency(totalSpent) }}</div></div></div></VCol>
          <VCol cols="12" sm="6" lg="3"><div class="metric-tile"><VIcon icon="tabler-calendar-month" color="warning" /><div><div class="metric-label">{{ $t('common.messages.thisMonth') }}</div><div class="metric-value">{{ formatBaseCurrency(currentMonthSpent) }}</div></div></div></VCol>
          <VCol cols="12" sm="6" lg="3"><div class="metric-tile"><VIcon icon="tabler-chart-arrows-vertical" color="info" /><div><div class="metric-label">{{ $t('pages.finance.expenses.expenses.largestRecord') }}</div><div class="metric-value">{{ formatBaseCurrency(largestExpense) }}</div></div></div></VCol>
          <VCol cols="12" sm="6" lg="3"><div class="metric-tile"><VIcon icon="tabler-chart-bar" color="secondary" /><div><div class="metric-label">{{ $t('pages.finance.expenses.expenses.averageExpense') }}</div><div class="metric-value">{{ formatBaseCurrency(averageExpense) }}</div></div></div></VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard class="mb-6 filter-deck" elevation="0"><VCardText><VRow align="center">
      <VCol cols="12" md="5"><VTextField v-model="searchQuery" prepend-inner-icon="tabler-search" :placeholder="$t('pages.finance.expenses.expenses.searchExpenseDescriptionsOrTypes')" density="compact" clearable hide-details @keyup.enter="fetchExpenses" /></VCol>
      <VCol cols="12" sm="6" md="2"><VSelect v-model="filterType" :items="typeOptions" :label="$t('pages.finance.expenses.expenses.filterByType')" density="compact" clearable hide-details @update:model-value="fetchExpenses" /></VCol>
      <VCol cols="12" sm="6" md="2"><VSelect v-model="filterCurrency" :items="currencyOptions" :label="$t('common.messages.filterByCurrency')" density="compact" clearable hide-details @update:model-value="fetchExpenses" /></VCol>
      <VCol cols="12" md="3" class="d-flex align-center justify-md-end"><div class="text-caption text-medium-emphasis"><strong class="text-high-emphasis">{{ expenses.length }}</strong> {{ $t('pages.finance.expenses.expenses.expenseRecordsShown') }}</div></VCol>
    </VRow></VCardText></VCard>

    <VCard class="ledger-card" elevation="0">
      <VCardItem><VCardTitle class="font-weight-bold">{{ $t('pages.finance.expenses.expenses.outgoingStream') }}</VCardTitle><template #append><VChip size="small" color="error" variant="tonal">{{ $t('pages.finance.expenses.expenses.spendLedger') }}</VChip></template></VCardItem>
      <div class="table-container"><VDataTable :items="expenses" :headers="[
        { title: translate('pages.finance.expenses.expenses.expense'), key: 'id', align: 'start' }, { title: translate('common.fields.type'), key: 'type', align: 'start' }, { title: translate('common.messages.details'), key: 'description', align: 'start' }, { title: translate('common.fields.currency'), key: 'currency', align: 'start' }, { title: translate('common.fields.amount'), key: 'total_amount', align: 'end' }, { title: translate('common.fields.date'), key: 'date', align: 'center' }, { title: translate('common.messages.actions'), key: 'actions', align: 'center', sortable: false }
      ]" item-value="id" class="text-no-wrap" :loading="isLoading" mobile-breakpoint="sm" density="compact">
        <template #item.id="{ item }"><div class="font-weight-bold"><bdi>{{ `EXP-${String(item.id).padStart(6, '0')}` }}</bdi></div><div class="text-caption text-medium-emphasis">{{ $t('common.messages.quantityValue', { value: item.quantity }) }}</div></template>
        <template #item.type="{ item }"><VChip size="small" :color="expenseTypeColor(item.type)" variant="tonal">{{ translateBackendValue(item.type) }}</VChip></template>
        <template #item.description="{ item }"><div class="detail-copy">{{ item.description }}</div></template>
        <template #item.currency="{ item }"><VChip v-if="item.currency" size="small" variant="outlined">{{ item.currency.code }}</VChip><span v-else>—</span></template>
        <template #item.total_amount="{ item }"><div class="font-weight-bold text-error">{{ formatExpenseCurrency(item.amount * item.quantity, item.currency) }}</div><div class="text-caption text-medium-emphasis">{{ $t('common.messages.rateValue', { value: item.rate_to_base }) }}</div></template>
        <template #item.date="{ item }">{{ formatDate(item.date) }}</template>
        <template #item.actions="{ item }"><div class="d-flex gap-1"><VBtn icon="tabler-edit" variant="text" size="small" color="medium-emphasis" @click="openEditExpenseDialog(item)" /><VBtn icon="tabler-trash" variant="text" size="small" color="error" @click="openDeleteExpenseDialog(item)" /></div></template>
      </VDataTable></div>
    </VCard>

    <VDialog v-model="isAddExpenseDialogVisible" max-width="900px"><VCard class="expense-dialog"><VCardTitle class="pa-6 pb-2"><div class="d-flex align-center gap-3"><VAvatar color="error" variant="tonal" icon="tabler-receipt-2" /><div><div class="text-h5 font-weight-bold">{{ $t('pages.finance.expenses.expenses.recordExpense2') }}</div><div class="text-caption text-medium-emphasis">{{ $t('pages.finance.expenses.expenses.captureOutgoingCashWithItsLiveCurrencyRelationship') }}</div></div></div></VCardTitle>
      <VCardText><VAlert v-if="formError" type="error" variant="tonal" class="mb-4">{{ formError }}</VAlert><VForm><VRow>
        <VCol cols="12" md="6"><VSelect v-model="selectedExpense.type" :items="typeOptions" :label="$t('common.messages.expenseType')" :error-messages="fieldErrors.type" required /></VCol>
        <VCol cols="12" md="6"><VSelect v-model="selectedExpense.currency_id" :items="currencyOptions" :label="$t('common.fields.currency')" :error-messages="fieldErrors.currency_id" required @update:model-value="handleCurrencyChange" /></VCol>
        <VCol cols="12"><VTextarea v-model="selectedExpense.description" :label="$t('common.fields.description')" :error-messages="fieldErrors.description" :placeholder="$t('common.messages.whatWasThisExpenseFor')" rows="2" auto-grow required /></VCol>
        <VCol cols="12" md="4"><VTextField v-model.number="selectedExpense.amount" :label="$t('common.fields.amount')" :error-messages="fieldErrors.amount" type="number" min="0" step="0.01" required @input="calculateTotal" /></VCol>
        <VCol cols="12" md="4"><VTextField v-model.number="selectedExpense.quantity" :label="$t('common.messages.quantity')" :error-messages="fieldErrors.quantity" type="number" min="1" required @input="calculateTotal" /></VCol>
        <VCol cols="12" md="4"><VTextField v-model.number="selectedExpense.rate_to_base" :label="$t('common.messages.exchangeRateSnapshot')" type="number" readonly /></VCol>
        <VCol cols="12" md="6"><VTextField :model-value="selectedExpense.total_amount" :label="$t('common.messages.baseCurrencyTotal')" type="number" readonly /></VCol>
        <VCol cols="12" md="6"><AppDateTimePicker v-model="selectedExpense.date" :label="$t('common.messages.expenseDate')" :error-messages="fieldErrors.date" :config="{ dateFormat: 'Y-m-d' }" required /></VCol>
      </VRow></VForm></VCardText>
      <VCardActions class="pa-5"><VSpacer /><VBtn color="secondary" variant="tonal" @click="isAddExpenseDialogVisible = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="error" @click="saveExpense()">{{ $t('pages.finance.expenses.expenses.saveExpense') }}</VBtn></VCardActions>
    </VCard></VDialog>

    <VDialog v-model="isEditExpenseDialogVisible" max-width="900px"><VCard class="expense-dialog"><VCardTitle class="pa-6 pb-2"><div class="d-flex align-center gap-3"><VAvatar color="error" variant="tonal" icon="tabler-pencil" /><div><div class="text-h5 font-weight-bold">{{ $t('pages.finance.expenses.expenses.editExpense') }}</div><div class="text-caption text-medium-emphasis">{{ $t('pages.finance.expenses.expenses.updateTheRecordedSpendAndItsCurrency') }}</div></div></div></VCardTitle>
      <VCardText><VAlert v-if="formError" type="error" variant="tonal" class="mb-4">{{ formError }}</VAlert><VForm><VRow>
        <VCol cols="12" md="6"><VSelect v-model="selectedExpense.type" :items="typeOptions" :label="$t('common.messages.expenseType')" :error-messages="fieldErrors.type" required /></VCol>
        <VCol cols="12" md="6"><VSelect v-model="selectedExpense.currency_id" :items="currencyOptions" :label="$t('common.fields.currency')" :error-messages="fieldErrors.currency_id" required @update:model-value="handleCurrencyChange" /></VCol>
        <VCol cols="12"><VTextarea v-model="selectedExpense.description" :label="$t('common.fields.description')" :error-messages="fieldErrors.description" :placeholder="$t('common.messages.whatWasThisExpenseFor')" rows="2" auto-grow required /></VCol>
        <VCol cols="12" md="4"><VTextField v-model.number="selectedExpense.amount" :label="$t('common.fields.amount')" :error-messages="fieldErrors.amount" type="number" min="0" step="0.01" required @input="calculateTotal" /></VCol>
        <VCol cols="12" md="4"><VTextField v-model.number="selectedExpense.quantity" :label="$t('common.messages.quantity')" :error-messages="fieldErrors.quantity" type="number" min="1" required @input="calculateTotal" /></VCol>
        <VCol cols="12" md="4"><VTextField v-model.number="selectedExpense.rate_to_base" :label="$t('common.messages.exchangeRateSnapshot')" type="number" readonly /></VCol>
        <VCol cols="12" md="6"><VTextField :model-value="selectedExpense.total_amount" :label="$t('common.messages.baseCurrencyTotal')" type="number" readonly /></VCol>
        <VCol cols="12" md="6"><AppDateTimePicker v-model="selectedExpense.date" :label="$t('common.messages.expenseDate')" :error-messages="fieldErrors.date" :config="{ dateFormat: 'Y-m-d' }" required /></VCol>
      </VRow></VForm></VCardText>
      <VCardActions class="pa-5"><VSpacer /><VBtn color="secondary" variant="tonal" @click="isEditExpenseDialogVisible = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="error" @click="saveExpense(true)">{{ $t('common.messages.saveChanges4') }}</VBtn></VCardActions>
    </VCard></VDialog>

    <VDialog v-model="isDeleteExpenseDialogVisible" max-width="500px"><VCard><VCardTitle class="pa-5">{{ $t('pages.finance.expenses.expenses.deleteExpense') }}</VCardTitle><VCardText>{{ $t('pages.finance.expenses.expenses.thisRemovesTheSelectedExpenseRecordPermanently') }}</VCardText><VCardActions class="pa-5"><VSpacer /><VBtn color="secondary" variant="tonal" @click="isDeleteExpenseDialogVisible = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="error" @click="deleteExpenseRecord">{{ $t('common.actions.delete') }}</VBtn></VCardActions></VCard></VDialog>
  </div>
</template>

<style scoped>
.expense-page { padding-bottom: 24px; }
.expense-command-deck { color: white; overflow: hidden; background: radial-gradient(circle at 90% 5%, #ef4444aa, transparent 28%), radial-gradient(circle at 8% 100%, #f97316aa, transparent 38%), linear-gradient(115deg, #31202c, #522537 58%, #42213a); }
.eyebrow { display: flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 800; letter-spacing: .12em; opacity: .78; }
.metric-tile { min-height: 86px; display: flex; align-items: center; gap: 13px; padding: 15px; border: 1px solid rgb(255 255 255 / .14); border-radius: 14px; background: rgb(255 255 255 / .09); backdrop-filter: blur(12px); }
.metric-tile :deep(.v-icon) { padding: 10px; border-radius: 10px; background: rgb(255 255 255 / .94); }
.metric-label { font-size: .72rem; letter-spacing: .04em; opacity: .72; text-transform: uppercase; }.metric-value { font-size: 1.15rem; font-weight: 800; line-height: 1.4; }
.filter-deck, .ledger-card { border: 1px solid rgb(var(--v-border-color)); }.detail-copy { max-width: 290px; overflow: hidden; text-overflow: ellipsis; }.expense-dialog { overflow: hidden; }.table-container { overflow-x: auto; }
</style>
