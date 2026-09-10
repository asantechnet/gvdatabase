<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { VDialog } from 'vuetify/components'
import CurrencyApi, { type Currency } from '@/api/currency'
import CompanyApi, { type Company } from '@/api/company'
import ProjectApi, { type Project } from '@/api/project'
import { type Expense, type ExpenseCreateData, type ExpenseFormData, type ExpenseUpdateData, createExpense, deleteExpense, getExpenses, updateExpense } from '@/api/expenses'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import ExpenseFormDialog from '@/components/forms/ExpenseFormDialog.vue'

const expenses = ref<Expense[]>([])
const currencies = ref<Currency[]>([])
const companies = ref<Company[]>([])
const projects = ref<Project[]>([])
const selectedExpense = ref<ExpenseFormData | null>(null)
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

const filterTypeOptions = computed(() => typeOptions.map(value => ({
  title: translateBackendValue(value),
  value,
})))

const currencyOptions = computed(() => currencies.value.map(currency => ({
  title: `${currency.code} — ${currency.name}`,
  value: currency.id,
  rate: currency.rate,
})))

const currentBaseCurrency = computed(() => currencies.value.find(currency => currency.isBase) ?? null)

const companyOptions = computed(() => companies.value
  .filter(company => company.is_active)
  .map(company => ({ title: company.trade_name || company.legal_name, value: company.id })))

const projectOptions = computed(() => projects.value
  .filter(project => project.status !== 'archived')
  .map(project => ({ title: project.customer ? `${project.name} - ${project.customer.name}` : project.name, value: project.id })))

const totalSpent = computed(() => expenses.value.reduce((total, expense) => total + Number(expense.currentBaseAmount ?? expense.base_amount ?? 0), 0))
const averageExpense = computed(() => expenses.value.length ? totalSpent.value / expenses.value.length : 0)

const currentMonthSpent = computed(() => {
  const now = new Date()

  return expenses.value.reduce((total, expense) => {
    const date = new Date(`${expense.date}T00:00:00`)

    return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear()
      ? total + Number(expense.currentBaseAmount ?? expense.base_amount ?? 0)
      : total
  }, 0)
})

const largestExpense = computed(() => Math.max(0, ...expenses.value.map(expense => Number(expense.currentBaseAmount ?? expense.base_amount ?? 0))))

const fetchCurrencies = async () => {
  const response = await CurrencyApi.getAll()

  currencies.value = response.data.filter(currency => currency.isActive)
}

const fetchLinkOptions = async () => {
  const [companyRecords, projectRecords] = await Promise.all([CompanyApi.getAll(), ProjectApi.getAll()])

  companies.value = companyRecords
  projects.value = projectRecords
}

const fetchExpenses = async () => {
  try {
    isLoading.value = true

    const params: { search?: string; type?: string; currency_id?: number } = {}
    if (searchQuery.value)
      params.search = searchQuery.value
    if (filterType.value)
      params.type = filterType.value
    if (filterCurrency.value)
      params.currency_id = filterCurrency.value
    expenses.value = await getExpenses(params)
  }
  catch (error) {
    console.error('Error fetching expenses:', error)
    expenses.value = []
  }
  finally {
    isLoading.value = false
  }
}

const setDefaultCurrency = () => {
  if (!selectedExpense.value || selectedExpense.value.currency_id)
    return
  const currency = currencies.value.find(item => item.isBase) ?? currencies.value[0]
  if (currency) {
    selectedExpense.value.currency_id = currency.id
    selectedExpense.value.rate = currency.rate
  }
}

const openAddExpenseDialog = async () => {
  formError.value = ''
  fieldErrors.value = {}
  selectedExpense.value = {
    customer_id: null,
    company_id: null,
    project_id: null,
    currency_id: 0,
    type: '',
    description: '',
    amount: 0,
    quantity: 1,
    rate: 1,
    date: new Date().toISOString().slice(0, 10),
  }
  await Promise.all([fetchCurrencies(), fetchLinkOptions()])
  setDefaultCurrency()
  isAddExpenseDialogVisible.value = true
}

const openEditExpenseDialog = async (expense: Expense) => {
  formError.value = ''
  fieldErrors.value = {}
  selectedExpense.value = { ...expense }
  await Promise.all([fetchCurrencies(), fetchLinkOptions()])
  isEditExpenseDialogVisible.value = true
}

const openDeleteExpenseDialog = (expense: Expense) => {
  selectedExpense.value = expense
  isDeleteExpenseDialogVisible.value = true
}

const handleCurrencyChange = (currencyId: number) => {
  if (!selectedExpense.value)
    return
  const currency = currencies.value.find(item => item.id === currencyId)
  if (currency) {
    selectedExpense.value.rate = currency.rate
    calculateTotal()
  }
}

const calculateTotal = () => {
  if (selectedExpense.value)
    selectedExpense.value.base_amount = Number(selectedExpense.value.amount) * Number(selectedExpense.value.quantity) / Number(selectedExpense.value.rate || 1)
}

const validateExpense = () => {
  if (!selectedExpense.value)
    return false

  const expense = selectedExpense.value

  fieldErrors.value = {}
  if (!expense.type?.trim())
    fieldErrors.value.type = translate('pages.finance.expenses.expenses.selectAnExpenseType')
  if (!Number.isInteger(Number(expense.currency_id)) || Number(expense.currency_id) < 1)
    fieldErrors.value.currency_id = translate('common.messages.selectACurrency')
  if (!expense.description?.trim())
    fieldErrors.value.description = translate('pages.finance.expenses.expenses.enterADescription')
  if (!Number.isFinite(Number(expense.amount)) || Number(expense.amount) < 0)
    fieldErrors.value.amount = translate('pages.finance.expenses.expenses.enterAnAmountOfZeroOrMore')
  if (!Number.isInteger(Number(expense.quantity)) || Number(expense.quantity) < 1)
    fieldErrors.value.quantity = translate('pages.finance.expenses.expenses.enterAWholeQuantityOfAtLeast1')
  if (!Number.isFinite(Number(expense.rate)) || Number(expense.rate) <= 0)
    fieldErrors.value.rate = translate('common.messages.exchangeRate')
  if (!expense.date || Number.isNaN(Date.parse(expense.date)))
    fieldErrors.value.date = translate('pages.finance.expenses.expenses.chooseAValidExpenseDate')

  return Object.keys(fieldErrors.value).length === 0
}

const saveExpense = async (isEditing = false, submittedExpense?: ExpenseFormData) => {
  if (submittedExpense)
    selectedExpense.value = submittedExpense

  if (!selectedExpense.value)
    return
  if (!validateExpense()) {
    formError.value = translate('pages.finance.expenses.expenses.pleaseCorrectTheHighlightedFields')

    return
  }

  try {
    formError.value = ''
    fieldErrors.value = {}

    const payload = {
      customer_id: selectedExpense.value.customer_id,
      company_id: selectedExpense.value.company_id,
      project_id: selectedExpense.value.project_id,
      currency_id: selectedExpense.value.currency_id,
      type: selectedExpense.value.type,
      description: selectedExpense.value.description,
      amount: selectedExpense.value.amount,
      quantity: selectedExpense.value.quantity,
      rate: selectedExpense.value.rate,
      date: selectedExpense.value.date,
    }

    const savedExpense = isEditing && 'id' in selectedExpense.value
      ? await updateExpense(selectedExpense.value.id, payload as ExpenseUpdateData)
      : await createExpense(payload as ExpenseCreateData)

    const index = expenses.value.findIndex(expense => expense.id === savedExpense.id)
    if (index >= 0)
      expenses.value[index] = savedExpense
    else expenses.value.unshift(savedExpense)
    isAddExpenseDialogVisible.value = false
    isEditExpenseDialogVisible.value = false
  }
  catch (error) {
    console.error('Error saving expense:', error)
    fieldErrors.value = apiFieldErrors(error)
    formError.value = apiErrorMessage(error, 'Unable to save the expense record.')
  }
}

const deleteExpenseRecord = async () => {
  if (!selectedExpense.value || !('id' in selectedExpense.value))
    return
  const deletedId = selectedExpense.value.id
  try {
    await deleteExpense(deletedId)
    expenses.value = expenses.value.filter(expense => expense.id !== deletedId)
    isDeleteExpenseDialogVisible.value = false
  }
  catch (error) {
    console.error('Error deleting expense:', error)
    formError.value = apiErrorMessage(error, 'Unable to delete the expense record.')
  }
}

const formatBaseCurrency = (value: number) => {
  const currency = currentBaseCurrency.value
  const decimals = currency?.decimalPlaces ?? 2
  const formatted = Number(value || 0).toFixed(decimals)

  return `${currency?.symbol ?? ''}${formatted}${currency?.code ? ` ${currency.code}` : ''}`.trim()
}

const formatExpenseCurrency = (value: number, currency: Expense['currency']) => {
  const amount = Number(value || 0).toFixed(currency?.decimalPlaces ?? 2)

  return currency?.symbol ? `${currency.symbol}${amount}` : `${amount} ${currency?.code ?? ''}`.trim()
}

const formatDate = (date: string) => formatLocalizedDate(`${date}T00:00:00`, { day: '2-digit', month: 'short', year: 'numeric' })
const expenseTypeColor = (type: string) => ({ 'Utilities': 'info', 'Software/SaaS': 'primary', 'Marketing': 'warning', 'Office Supplies': 'secondary', 'Travel & Dining': 'success', 'Salaries': 'error' }[type] ?? 'secondary')
const printTable = () => void printDocument({ bodyClass: 'printing-table-expenses', pageStyle: 'size: A4 landscape; margin: 12mm;' })

onMounted(() => {
  fetchExpenses()
  fetchCurrencies()
  fetchLinkOptions()
})
</script>

<template>
  <div class="expense-page">
    <VCard
      class="expense-command-deck mb-6"
      elevation="0"
    >
      <VCardText class="pa-6 pa-md-8">
        <div class="hero-topline d-flex flex-wrap align-center justify-space-between gap-5">
          <div class="hero-copy">
            <div class="eyebrow mb-2">
              <VIcon
                icon="tabler-activity-heartbeat"
                size="15"
              /> {{ $t('pages.finance.expenses.expenses.spendCONTROLCENTER') }}
            </div>
            <h2 class="text-h4 font-weight-black mb-1">
              {{ $t('pages.finance.expenses.expenses.expenseLedger') }}
            </h2>
            <p class="text-body-2 opacity-80 mb-0">
              {{ $t('pages.finance.expenses.expenses.trackOutgoingCashRecurringCostsAndSpendPatternsInOneFocusedView') }}
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
                {{ $t('pages.finance.expenses.expenses.printExpenseRecords') }}
              </VTooltip>
            </VBtn>
            <VBtn
              color="white"
              class="hero-primary-action text-error font-weight-bold"
              @click="openAddExpenseDialog"
            >
              <VIcon
                icon="tabler-plus"
                class="me-1"
              /> {{ $t('pages.finance.expenses.expenses.recordExpense') }}
            </VBtn>
          </div>
        </div>
        <VRow class="mt-4">
          <VCol
            cols="12"
            sm="6"
            lg="3"
          >
            <div class="metric-tile metric-tile--error">
              <VIcon
                icon="tabler-credit-card-pay"
                color="error"
              /><div>
                <div class="metric-label">
                  {{ $t('pages.finance.expenses.expenses.recordedBaseValue') }}
                </div><div class="metric-value">
                  {{ formatBaseCurrency(totalSpent) }}
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
                icon="tabler-calendar-month"
                color="warning"
              /><div>
                <div class="metric-label">
                  {{ $t('common.messages.thisMonth') }}
                </div><div class="metric-value">
                  {{ formatBaseCurrency(currentMonthSpent) }}
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
                icon="tabler-chart-arrows-vertical"
                color="info"
              /><div>
                <div class="metric-label">
                  {{ $t('pages.finance.expenses.expenses.largestRecord') }}
                </div><div class="metric-value">
                  {{ formatBaseCurrency(largestExpense) }}
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
                  {{ $t('pages.finance.expenses.expenses.averageExpense') }}
                </div><div class="metric-value">
                  {{ formatBaseCurrency(averageExpense) }}
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
              :placeholder="$t('pages.finance.expenses.expenses.searchExpenseDescriptionsOrTypes')"
              density="compact"
              clearable
              hide-details
              @keyup.enter="fetchExpenses"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
            class="d-flex align-center justify-md-end ga-2"
          >
            <DataTableFilterMenu
              :active="Boolean(filterType || filterCurrency)"
              @reset="filterType = null; filterCurrency = null; fetchExpenses()"
            >
              <VSelect
                v-model="filterType"
                :items="filterTypeOptions"
                :label="$t('pages.finance.expenses.expenses.filterByType')"
                density="compact"
                clearable
                hide-details
                @update:model-value="fetchExpenses"
              /><VSelect
                v-model="filterCurrency"
                :items="currencyOptions"
                :label="$t('common.messages.filterByCurrency')"
                density="compact"
                clearable
                hide-details
                @update:model-value="fetchExpenses"
              />
            </DataTableFilterMenu><div class="text-caption text-medium-emphasis">
              <strong class="text-high-emphasis">{{ expenses.length }}</strong> {{ $t('pages.finance.expenses.expenses.expenseRecordsShown') }}
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
          />{{ $t('pages.finance.expenses.expenses.outgoingStream') }}
        </VCardTitle><template #append>
          <VChip
            size="small"
            color="error"
            variant="tonal"
          >
            {{ $t('pages.finance.expenses.expenses.spendLedger') }}
          </VChip>
        </template>
      </VCardItem>
      <div class="table-container">
        <ResponsiveDataTable
          :items="expenses"
          :headers="[
            { title: translate('pages.finance.expenses.expenses.expense'), key: 'id', align: 'start' }, { title: translate('common.fields.type'), key: 'type', align: 'start' }, { title: translate('common.messages.details'), key: 'description', align: 'start' }, { title: translate('common.fields.currency'), key: 'currency', align: 'start' }, { title: translate('common.fields.amount'), key: 'base_amount', align: 'end' }, { title: translate('common.fields.date'), key: 'date', align: 'center' }, { title: translate('common.messages.recordedBy'), key: 'recordedBy.name', align: 'start' }, { title: translate('common.messages.actions'), key: 'actions', align: 'center', sortable: false },
          ]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
        >
          <template #item.id="{ item }">
            <div class="font-weight-bold">
              <bdi>{{ `EXP-${String(item.id).padStart(6, '0')}` }}</bdi>
            </div><div class="text-caption text-medium-emphasis">
              {{ $t('common.messages.quantityValue', { value: item.quantity }) }}
            </div>
          </template>
          <template #item.type="{ item }">
            <VChip
              size="small"
              :color="expenseTypeColor(item.type)"
              variant="tonal"
            >
              {{ translateBackendValue(item.type) }}
            </VChip>
          </template>
          <template #item.description="{ item }">
            <div class="detail-copy">
              {{ item.description }}
            </div>
          </template>
          <template #item.currency="{ item }">
            <VChip
              v-if="item.currency"
              size="small"
              variant="outlined"
            >
              {{ item.currency.code }}
            </VChip><span v-else>—</span>
          </template>
          <template #item.base_amount="{ item }">
            <div class="font-weight-bold text-error">
              {{ formatExpenseCurrency(item.amount * item.quantity, item.currency) }}
            </div><div class="text-caption text-medium-emphasis">
              {{ item.base_amount }} {{ item.base_curr_shrt }} · rate {{ item.rate }}
            </div>
          </template>
          <template #item.date="{ item }">
            {{ formatDate(item.date) }}
          </template>
          <template #item.recordedBy.name="{ item }">
            <span>{{ item.recordedBy?.name || $t('common.messages.systemLegacy') }}</span>
          </template>
          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <FullDetailsButton :item="item" /><VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                @click="openEditExpenseDialog(item)"
              /><VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="openDeleteExpenseDialog(item)"
              />
            </div>
          </template>
          <template #no-data>
            <div class="ledger-empty">
              <VAvatar
                size="48"
                color="error"
                variant="tonal"
                icon="tabler-receipt"
              /><div>
                <div class="text-subtitle-1 font-weight-bold">
                  Your expense ledger is clear
                </div><p class="text-body-2 text-medium-emphasis mb-0">
                  Record an expense to start building a clear picture of your spending.
                </p>
              </div><VBtn
                color="error"
                variant="tonal"
                prepend-icon="tabler-plus"
                @click="openAddExpenseDialog"
              >
                Record expense
              </VBtn>
            </div>
          </template>
        </ResponsiveDataTable>
      </div>
    </VCard>

    <PrintableListReport
      title="Expenses Report"
      subtitle="Expense records"
      :columns="['Expense', 'Type', 'Details', 'Currency', 'Amount', 'Date']"
      :rows="expenses.map(expense => [`EXP-${String(expense.id).padStart(6, '0')}`, expense.type, expense.description, expense.currency?.code, formatExpenseCurrency(expense.amount * expense.quantity, expense.currency), formatDate(expense.date)])"
    />

    <ExpenseFormDialog
      v-model="isAddExpenseDialogVisible"
      :initial-expense="selectedExpense"
      :currency-options="currencyOptions"
      :company-options="companyOptions"
      :project-options="projectOptions"
      mode="create"
      @submit="saveExpense(false, $event)"
    />
    <ExpenseFormDialog
      v-model="isEditExpenseDialogVisible"
      :initial-expense="selectedExpense"
      :currency-options="currencyOptions"
      :company-options="companyOptions"
      :project-options="projectOptions"
      mode="edit"
      @submit="saveExpense(true, $event)"
    />

    <VDialog
      v-model="isDeleteExpenseDialogVisible"
      max-width="500px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          {{ $t('pages.finance.expenses.expenses.deleteExpense') }}
        </VCardTitle><VCardText>{{ $t('pages.finance.expenses.expenses.thisRemovesTheSelectedExpenseRecordPermanently') }}</VCardText><VCardActions class="pa-5">
          <VSpacer /><VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteExpenseDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn><VBtn
            color="error"
            @click="deleteExpenseRecord"
          >
            {{ $t('common.actions.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.expense-page { padding-bottom: 32px; }
.expense-command-deck { color: white; border: 1px solid rgb(239 68 68 / .18); border-radius: 20px; box-shadow: 0 18px 45px rgb(72 28 51 / .2); background: linear-gradient(115deg, #31202c, #522537 58%, #42213a); }
/* Keep the theme class on this scoped card selector. A mixed :global()
   selector leaks the light background onto full-screen Vuetify overlays. */
.expense-command-deck.v-theme--light { color: #3d2421; border-color: #fed7aa; box-shadow: 0 16px 34px rgb(194 65 12 / .12); background: #fff3e8; }
.expense-command-deck.v-theme--light .metric-tile { border-color: #f4d5bb; background: #fffefa; box-shadow: inset 0 1px 0 #fff; }
.expense-command-deck.v-theme--light .hero-print { color: #9a3412 !important; border-color: #fdba74; }
.hero-copy { max-width: 660px; }.hero-copy h2 { letter-spacing: -.035em; }.hero-actions { flex-shrink: 0; }.hero-print { border: 1px solid rgb(255 255 255 / .22); }.hero-primary-action { min-height: 42px; padding-inline: 18px !important; border-radius: 10px; box-shadow: 0 8px 22px rgb(52 13 28 / .22); }
.eyebrow { display: flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 800; letter-spacing: .12em; opacity: .78; }
.metric-tile { min-height: 92px; display: flex; align-items: center; gap: 13px; padding: 16px; border: 1px solid rgb(255 255 255 / .15); border-radius: 15px; background: linear-gradient(135deg, rgb(255 255 255 / .14), rgb(255 255 255 / .055)); box-shadow: inset 0 1px 0 rgb(255 255 255 / .1); transition: transform .2s ease, background .2s ease; }.metric-tile:hover { transform: translateY(-3px); background: rgb(255 255 255 / .16); }.metric-tile :deep(.v-icon) { padding: 10px; border-radius: 11px; background: rgb(255 255 255 / .94); box-shadow: 0 5px 12px rgb(52 13 28 / .16); }
.metric-label { font-size: .72rem; letter-spacing: .04em; opacity: .72; text-transform: uppercase; }.metric-value { font-size: 1.15rem; font-weight: 800; line-height: 1.4; }
.filter-deck, .ledger-card { overflow: hidden; border: 0; border-radius: 16px; box-shadow: 0 10px 28px rgb(36 20 31 / .08); }.filter-deck { background: rgb(var(--v-theme-surface)); }.filter-deck__content { padding: 14px 18px !important; }.filter-deck :deep(.v-field) { border-radius: 10px; background: rgb(var(--v-theme-on-surface) / .025); }.ledger-card__header { min-height: 68px; padding-inline: 20px; }.ledger-card__title { display: flex; align-items: center; gap: 9px; }.detail-copy { max-width: 290px; overflow: hidden; color: rgb(var(--v-theme-on-surface) / .76); text-overflow: ellipsis; }.expense-dialog { overflow: visible; }.table-container { overflow-x: auto; }
.ledger-card :deep(thead th) { height: 48px !important; color: rgb(var(--v-theme-on-surface) / .58) !important; font-size: .68rem !important; font-weight: 800 !important; letter-spacing: .075em; text-transform: uppercase; background: rgb(var(--v-theme-on-surface) / .018); }.ledger-card :deep(tbody td) { height: 66px !important; border-bottom-color: rgb(var(--v-border-color) / .7) !important; }.ledger-card :deep(tbody tr) { transition: background .16s ease; }.ledger-card :deep(tbody tr:hover) { background: rgb(var(--v-theme-error) / .035) !important; }
.ledger-empty { display: flex; align-items: center; gap: 14px; min-height: 176px; padding: 28px 32px; text-align: start; background: linear-gradient(90deg, rgb(var(--v-theme-error) / .07), transparent 58%); }.ledger-empty :deep(.v-btn) { margin-inline-start: auto; }
@media (max-width: 600px) { .ledger-empty { align-items: flex-start; flex-direction: column; padding: 24px; }.ledger-empty :deep(.v-btn) { margin-inline-start: 0; } }
</style>
