<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { VDialog } from 'vuetify/components'
import { useToast } from 'vue-toastification'
import { type FinanceType, getFinanceTypes } from '@/api/financeTypes'
import { financeTypeLabel } from '@/utils/financeTypes'
import PendingTransactionApi from '@/api/pendingTransactions'
import CurrencyApi, { type Currency } from '@/api/currency'
import { type Expense, type ExpenseCreateData, type ExpenseFormData, type ExpenseUpdateData, createExpense, deleteExpense, getExpenses, updateExpense } from '@/api/expenses'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import ExpenseFormDialog from '@/components/forms/ExpenseFormDialog.vue'
import AttachmentsDialog from '@/components/attachments/AttachmentsDialog.vue'

const expenses = ref<Expense[]>([])
const currencies = ref<Currency[]>([])
const selectedExpense = ref<ExpenseFormData | null>(null)
const isAddExpenseDialogVisible = ref(false)
const isEditExpenseDialogVisible = ref(false)
const isDeleteExpenseDialogVisible = ref(false)
const searchQuery = ref('')
const filterType = ref<string | null>(null)
const filterCurrency = ref<number | null>(null)
const dateFrom = ref('')
const dateTo = ref('')
const isLoading = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string>>({})
const attachmentTarget = ref<Expense | null>(null)
const isAttachmentsOpen = ref(false)
const toast = useToast()

const financeTypes = ref<FinanceType[]>([])
const typeOptions = computed(() => financeTypes.value.map(type => ({ title: financeTypeLabel(type), value: type.value })))
const filterTypeOptions = typeOptions
const typeLabel = (value: string) => {
  const type = financeTypes.value.find(type => type.value === value)
  return type ? financeTypeLabel(type) : translateBackendValue(value)
}

async function fetchTypes() {
  try { financeTypes.value = await getFinanceTypes('expense') }
  catch (error) { toast.error(apiErrorMessage(error, translate('pages.finance.types.loadFailed'))) }
}

const currencyOptions = computed(() => currencies.value.map(currency => ({
  title: `${currency.code} — ${currency.name}`,
  value: currency.id,
  rate: currency.rate,
})))

const fetchCurrencies = async () => {
  const response = await CurrencyApi.getAll()

  currencies.value = response.data.filter(currency => currency.isActive)
}

const fetchExpenses = async () => {
  try {
    isLoading.value = true

    const params: { search?: string; type?: string; currency_id?: number; date_from?: string; date_to?: string } = {}
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
    applicant_id: null,
    currency_id: 0,
    type: '',
    description: '',
    amount: 0,
    quantity: 1,
    rate: 1,
    date: new Date().toISOString().slice(0, 10),
  }
  await Promise.all([fetchCurrencies(), fetchTypes()])
  setDefaultCurrency()
  isAddExpenseDialogVisible.value = true
}

const openEditExpenseDialog = async (expense: Expense) => {
  formError.value = ''
  fieldErrors.value = {}
  selectedExpense.value = { ...expense }
  await Promise.all([fetchCurrencies(), fetchTypes()])
  isEditExpenseDialogVisible.value = true
}

const openDeleteExpenseDialog = (expense: Expense) => {
  selectedExpense.value = expense
  isDeleteExpenseDialogVisible.value = true
}

function openAttachments(expense: Expense) {
  attachmentTarget.value = expense
  isAttachmentsOpen.value = true
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

const saveExpense = async (isEditing = false, submittedExpense?: ExpenseFormData, mode: 'direct' | 'loan' = 'direct') => {
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

    // Payment requests route the expense through the approval workflow instead
    // of writing the ledger row immediately.
    if (!isEditing && mode === 'loan') {
      await PendingTransactionApi.create({
        transaction_type: 'expense',
        reason: selectedExpense.value.description?.trim() || selectedExpense.value.type || 'Expense',
        amount: Number(selectedExpense.value.amount) * Number(selectedExpense.value.quantity || 1),
        currency_id: selectedExpense.value.currency_id,
        applicant_id: selectedExpense.value.applicant_id ?? null,
        employee_id: null,
      })
      isAddExpenseDialogVisible.value = false
      toast.success(translate('pages.finance.expenses.expenses.loanSubmitted'))

      return
    }

    const payload = {
      applicant_id: selectedExpense.value.applicant_id,
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

const formatExpenseCurrency = (value: number, currency: Expense['currency']) => {
  const amount = Number(value || 0).toFixed(currency?.decimalPlaces ?? 2)

  return currency?.symbol ? `${currency.symbol}${amount}` : `${amount} ${currency?.code ?? ''}`.trim()
}

const formatDate = (date: string) => formatLocalizedDate(`${date}T00:00:00`, { day: '2-digit', month: 'short', year: 'numeric' })
const expenseTypeColor = (type: string) => ({ 'Utilities': 'info', 'Software/SaaS': 'primary', 'Marketing': 'warning', 'Office Supplies': 'secondary', 'Travel & Dining': 'success', 'Salaries': 'error' }[type] ?? 'secondary')
const printTable = () => void printDocument({ bodyClass: 'printing-table-expenses', pageStyle: 'size: A4 landscape; margin: 12mm;' })

onMounted(() => {
  fetchTypes()
  fetchExpenses()
  fetchCurrencies()
})
</script>

<template>
  <div class="expense-page">
    <div class="expense-page-head d-flex flex-wrap align-center justify-space-between gap-3 mb-6">
      <div class="d-flex align-center ga-3">
        <VAvatar
          icon="tabler-receipt-2"
          color="error"
          variant="tonal"
          rounded="lg"
        />
        <div>
          <h1 class="text-h5 font-weight-bold mb-0">
            {{ $t('pages.finance.expenses.expenses.expenseLedger') }}
          </h1>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ $t('pages.finance.expenses.expenses.trackOutgoingCashRecurringCostsAndSpendPatternsInOneFocusedView') }}
          </p>
        </div>
      </div>
      <div class="d-flex align-center gap-2">
        <VBtn
          icon="tabler-printer"
          variant="text"
          :aria-label="$t('pages.finance.expenses.expenses.printExpenseRecords')"
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
          color="primary"
          prepend-icon="tabler-plus"
          @click="openAddExpenseDialog"
        >
          {{ $t('pages.finance.expenses.expenses.recordExpense') }}
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
              :active="Boolean(filterType || filterCurrency || dateFrom || dateTo)"
              @reset="filterType = null; filterCurrency = null; dateFrom = ''; dateTo = ''; fetchExpenses()"
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
              <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" @apply="fetchExpenses" />
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
            { title: translate('pages.finance.expenses.expenses.expense'), key: 'id', align: 'start' }, { title: translate('common.fields.type'), key: 'type', align: 'start' }, { title: translate('common.fields.currency'), key: 'currency', align: 'start' }, { title: translate('common.fields.amount'), key: 'base_amount', align: 'end' }, { title: translate('common.fields.date'), key: 'date', align: 'center' }, { title: translate('common.messages.actions'), key: 'actions', align: 'center', sortable: false },
          ]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
        >
          <template #loading>
            <TableLoadingSkeleton :columns="6" :label="$t('common.messages.loading')" />
          </template>
          <template #no-data>
            <TableEmptyState icon="tabler-receipt-off" :description="$t('common.messages.noMatchingRecords')" />
          </template>
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
              {{ typeLabel(item.type) }}
            </VChip>
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
              {{ item.base_amount }} {{ item.base_curr_shrt }} · {{ $t('serviceContracts.rate') }} {{ item.rate }}
            </div>
          </template>
          <template #item.date="{ item }">
            {{ formatDate(item.date) }}
          </template>
          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-paperclip"
                variant="text"
                size="small"
                :aria-label="$t('common.attachments.title')"
                :title="$t('common.attachments.title')"
                @click="openAttachments(item)"
              />
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
        </ResponsiveDataTable>
      </div>
    </VCard>

    <PrintableListReport
      :title="$t('common.crmUi.printReports.expenses.title')"
      :subtitle="$t('common.crmUi.printReports.expenses.subtitle')"
      :columns="[$t('common.crmUi.printReports.columns.expense'), $t('common.crmUi.printReports.columns.type'), $t('common.crmUi.printReports.columns.details'), $t('common.crmUi.printReports.columns.currency'), $t('common.crmUi.printReports.columns.amount'), $t('common.crmUi.printReports.columns.date')]"
      :rows="expenses.map(expense => [`EXP-${String(expense.id).padStart(6, '0')}`, expense.type, expense.description, expense.currency?.code, formatExpenseCurrency(expense.amount * expense.quantity, expense.currency), formatDate(expense.date)])"
    />

    <ExpenseFormDialog
      v-model="isAddExpenseDialogVisible"
      :initial-expense="selectedExpense"
      :currency-options="currencyOptions"
      :type-options="typeOptions"
      mode="create"
      @submit="(value, mode) => saveExpense(false, value, mode)"
    />
    <ExpenseFormDialog
      v-model="isEditExpenseDialogVisible"
      :initial-expense="selectedExpense"
      :currency-options="currencyOptions"
      :type-options="typeOptions"
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

    <AttachmentsDialog
      v-model="isAttachmentsOpen"
      type="expenses"
      :record-id="attachmentTarget?.id ?? null"
      :title="attachmentTarget?.description || $t('common.attachments.title')"
    />
  </div>
</template>

<style scoped>
.expense-page { padding-bottom: 32px; }
.filter-deck, .ledger-card { overflow: hidden; border: 0; border-radius: 16px; box-shadow: 0 10px 28px rgb(36 20 31 / .08); }.filter-deck { background: rgb(var(--v-theme-surface)); }.filter-deck__content { padding: 14px 18px !important; }.filter-deck :deep(.v-field) { border-radius: 10px; background: rgb(var(--v-theme-on-surface) / .025); }.ledger-card__header { min-height: 68px; padding-inline: 20px; }.ledger-card__title { display: flex; align-items: center; gap: 9px; }.expense-dialog { overflow: visible; }.table-container { overflow-x: auto; }
.ledger-card :deep(thead th) { height: 48px !important; color: rgb(var(--v-theme-on-surface) / .58) !important; font-size: .68rem !important; font-weight: 800 !important; letter-spacing: .075em; text-transform: uppercase; background: rgb(var(--v-theme-on-surface) / .018); }.ledger-card :deep(tbody td) { height: 66px !important; border-bottom-color: rgb(var(--v-border-color) / .7) !important; }.ledger-card :deep(tbody tr) { transition: background .16s ease; }.ledger-card :deep(tbody tr:hover) { background: rgb(var(--v-theme-error) / .035) !important; }
.ledger-empty { display: flex; align-items: center; gap: 14px; min-height: 176px; padding: 28px 32px; text-align: start; background: linear-gradient(90deg, rgb(var(--v-theme-error) / .07), transparent 58%); }.ledger-empty :deep(.v-btn) { margin-inline-start: auto; }
@media (max-width: 600px) { .ledger-empty { align-items: flex-start; flex-direction: column; padding: 24px; }.ledger-empty :deep(.v-btn) { margin-inline-start: 0; } }
</style>
