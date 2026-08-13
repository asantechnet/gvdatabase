<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'

// 1. Interfaces & Types Schema Definitions
interface Invoice {
  id: string
  clientName: string
  projectType: string
  amount: number
  status: 'Paid' | 'Pending' | 'Overdue'
  dueDate: string
}

interface Expense {
  id: number
  description: string
  category: 'Software' | 'Salaries' | 'Marketing' | 'Supplies' | 'Infrastructure'
  amount: number
  date: string
}

// 2. Initial Mock Datasets
const initialInvoices: Invoice[] = [
  { id: 'INV-2026-001', clientName: 'Kabul Dental Complex', projectType: 'Clinic Management System', amount: 3500.00, status: 'Paid', dueDate: '2026-06-15' },
  { id: 'INV-2026-002', clientName: 'Omran Construction Group', projectType: 'ERP Module Customization', amount: 8200.00, status: 'Pending', dueDate: '2026-07-20' },
  { id: 'INV-2026-003', clientName: 'Ariana Real Estate', projectType: 'Mobile CRM App', amount: 4500.00, status: 'Overdue', dueDate: '2026-07-01' }
]

const initialExpenses: Expense[] = [
  { id: 1, description: translate('pages.finance.finance.finance.productionServerNginxHostingCloud'), category: 'Infrastructure', amount: 240.00, date: '2026-07-02' },
  { id: 2, description: translate('pages.finance.finance.finance.uiUXVectorAssetsSpriteLicenses'), category: 'Software', amount: 150.00, date: '2026-07-05' },
  { id: 3, description: translate('pages.finance.finance.finance.fullStackDeveloperContractRetainer'), category: 'Salaries', amount: 2100.00, date: '2026-07-07' }
]

// 3. Reactive States
const invoices = ref<Invoice[]>(initialInvoices)
const expenses = ref<Expense[]>(initialExpenses)

// Search & Filter Bindings
const invoiceSearch = ref('')
const selectedStatusFilter = ref<string | null>(null)
const selectedInvoiceAmountRange = ref<string | null>(null)
const expenseSearch = ref('')
const expenseCategoryFilter = ref<string | null>(null)
const selectedExpenseAmountRange = ref<string | null>(null)

// Dialog Control Toggles
const isInvoiceDialogActive = ref(false)
const isExpenseDialogActive = ref(false)

// Edit Mode Flags
const isInvoiceEditMode = ref(false)
const isExpenseEditMode = ref(false)

// Form State Bindings
const invoiceForm = ref({ id: '', clientName: '', projectType: '', amount: 0, status: 'Pending' as 'Paid' | 'Pending' | 'Overdue', dueDate: '' })
const expenseForm = ref({ id: null as number | null, description: '', category: 'Software' as Expense['category'], amount: 0, date: '' })

// 4. Computed Metrics Calculations
const totalRevenueCollected = computed(() => {
  return invoices.value.filter(i => i.status === 'Paid').reduce((sum, i) => sum + i.amount, 0)
})

const totalOutstandingReceivables = computed(() => {
  return invoices.value.filter(i => i.status !== 'Paid').reduce((sum, i) => sum + i.amount, 0)
})

const totalExpensesLogged = computed(() => {
  return expenses.value.reduce((sum, e) => sum + e.amount, 0)
})

// Filtered Pipelines
const filteredInvoices = computed(() => {
  return invoices.value.filter(invoice => {
    const matchesSearch = invoice.clientName.toLowerCase().includes(invoiceSearch.value.toLowerCase()) ||
      invoice.id.toLowerCase().includes(invoiceSearch.value.toLowerCase()) ||
      invoice.projectType.toLowerCase().includes(invoiceSearch.value.toLowerCase())
    const matchesStatus = !selectedStatusFilter.value || invoice.status === selectedStatusFilter.value
    let matchesAmount = true
    if (selectedInvoiceAmountRange.value === 'low') matchesAmount = invoice.amount < 3000
    if (selectedInvoiceAmountRange.value === 'mid') matchesAmount = invoice.amount >= 3000 && invoice.amount <= 6000
    if (selectedInvoiceAmountRange.value === 'high') matchesAmount = invoice.amount > 6000
    return matchesSearch && matchesStatus && matchesAmount
  })
})

const filteredExpenses = computed(() => {
  return expenses.value.filter(exp => {
    const matchesSearch = exp.description.toLowerCase().includes(expenseSearch.value.toLowerCase()) ||
      exp.category.toLowerCase().includes(expenseSearch.value.toLowerCase())
    const matchesCategory = !expenseCategoryFilter.value || exp.category === expenseCategoryFilter.value
    let matchesAmount = true
    if (selectedExpenseAmountRange.value === 'low') matchesAmount = exp.amount < 500
    if (selectedExpenseAmountRange.value === 'mid') matchesAmount = exp.amount >= 500 && exp.amount <= 1500
    if (selectedExpenseAmountRange.value === 'high') matchesAmount = exp.amount > 1500
    return matchesSearch && matchesCategory && matchesAmount
  })
})

// 5. Action Handlers & Ledger Operations
const openInvoiceModal = () => {
  isInvoiceEditMode.value = false
  invoiceForm.value = {
    id: `INV-2026-00${invoices.value.length + 1}`,
    clientName: '',
    projectType: '',
    amount: 0,
    status: 'Pending',
    dueDate: new Date().toISOString().split('T')[0]
  }
  isInvoiceDialogActive.value = true
}

const openEditInvoice = (item: Invoice) => {
  isInvoiceEditMode.value = true
  invoiceForm.value = { ...item }
  isInvoiceDialogActive.value = true
}

const saveInvoice = () => {
  if (isInvoiceEditMode.value) {
    const idx = invoices.value.findIndex(i => i.id === invoiceForm.value.id)
    if (idx !== -1) invoices.value[idx] = { ...invoiceForm.value }
  } else {
    invoices.value.unshift({ ...invoiceForm.value })
  }
  isInvoiceDialogActive.value = false
}

const deleteInvoice = (id: string) => {
  if (confirm(translate('pages.finance.finance.finance.deleteThisInvoice'))) {
    invoices.value = invoices.value.filter(i => i.id !== id)
  }
}

const openExpenseModal = () => {
  isExpenseEditMode.value = false
  expenseForm.value = {
    id: null,
    description: '',
    category: 'Software',
    amount: 0,
    date: new Date().toISOString().split('T')[0]
  }
  isExpenseDialogActive.value = true
}

const openEditExpense = (item: Expense) => {
  isExpenseEditMode.value = true
  expenseForm.value = { ...item }
  isExpenseDialogActive.value = true
}

const saveExpense = () => {
  if (isExpenseEditMode.value && expenseForm.value.id !== null) {
    const idx = expenses.value.findIndex(e => e.id === expenseForm.value.id)
    if (idx !== -1) expenses.value[idx] = { ...expenseForm.value }
  } else {
    expenses.value.unshift({
      id: Date.now(),
      description: expenseForm.value.description,
      category: expenseForm.value.category,
      amount: expenseForm.value.amount,
      date: expenseForm.value.date
    })
  }
  isExpenseDialogActive.value = false
}

const deleteExpense = (id: number) => {
  if (confirm(translate('pages.finance.finance.finance.deleteThisExpense'))) {
    expenses.value = expenses.value.filter(e => e.id !== id)
  }
}

const toggleInvoiceStatus = (invoice: Invoice) => {
  const statuses: Invoice['status'][] = ['Pending', 'Paid', 'Overdue']
  const nextIndex = (statuses.indexOf(invoice.status) + 1) % statuses.length
  invoice.status = statuses[nextIndex]
}

const getStatusColor = (status: Invoice['status']) => {
  if (status === 'Paid') return 'success'
  if (status === 'Pending') return 'warning'
  return 'error'
}

const printTable = (section: 'invoices' | 'expenses') => {
  // Create a new window for printing
  const printWindow = window.open('', '_blank')

  if (printWindow) {
    let html = ''

    if (section === 'invoices') {
      html = `
        <html>
          <head>
            <title>Invoices Report</title>
            <style>
              body { font-family: Arial, sans-serif; margin: 20px; }
              h1 { text-align: center; color: #1976d2; }
              table { width: 100%; border-collapse: collapse; margin: 20px 0; }
              th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
              th { background-color: #f2f2f2; }
              tr:nth-child(even) { background-color: #f9f9f9; }
            </style>
          </head>
          <body>
            <h1>Invoices Report</h1>
            <table>
              <thead>
                <tr>
                  <th>Token ID</th>
                  <th>Client Accounts</th>
                  <th>Project Type</th>
                  <th>Statement Balance</th>
                  <th>Status</th>
                  <th>Due Date</th>
                </tr>
              </thead>
              <tbody>
                ${filteredInvoices.value.map(invoice => `
                  <tr>
                    <td>${invoice.id}</td>
                    <td>${invoice.clientName}</td>
                    <td>${invoice.projectType}</td>
                    <td>$${invoice.amount.toFixed(2)}</td>
                    <td>${invoice.status}</td>
                    <td>${invoice.dueDate}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </body>
        </html>
      `
    } else if (section === 'expenses') {
      html = `
        <html>
          <head>
            <title>Expenses Report</title>
            <style>
              body { font-family: Arial, sans-serif; margin: 20px; }
              h1 { text-align: center; color: #d32f2f; }
              table { width: 100%; border-collapse: collapse; margin: 20px 0; }
              th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
              th { background-color: #f2f2f2; }
              tr:nth-child(even) { background-color: #f9f9f9; }
            </style>
          </head>
          <body>
            <h1>Expenses Report</h1>
            <table>
              <thead>
                <tr>
                  <th>Description</th>
                  <th>Cost Pool</th>
                  <th>Date</th>
                  <th>Charge</th>
                </tr>
              </thead>
              <tbody>
                ${filteredExpenses.value.map(expense => `
                  <tr>
                    <td>${expense.description}</td>
                    <td>${expense.category}</td>
                    <td>${expense.date}</td>
                    <td>$${expense.amount.toFixed(2)}</td>
                  </tr>
                `).join('')}
                <tr>
                  <td colspan="3" style="text-align: right; font-weight: bold;">Total:</td>
                  <td style="font-weight: bold;">$${totalExpensesLogged.value.toFixed(2)}</td>
                </tr>
              </tbody>
            </table>
          </body>
        </html>
      `
    }

    printWindow.document.write(html)
    printWindow.document.close()
    printWindow.focus()
    printWindow.print()
  }
}
</script>

<template>
  <div class="pa-6">

    <VRow class="mb-6" g="4">
      <VCol cols="12" sm="4">
        <VCard class="pa-5 border-start border-success border-xl h-100" elevation="1">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis uppercase font-weight-bold tracking-widest">{{ $t('pages.finance.finance.finance.revenueSettled') }}
              </div>
              <h3 class="text-h4 font-weight-black mt-2 text-success tracking-tight">
                    ${{ formatLocalizedNumber(totalRevenueCollected, { minimumFractionDigits: 2 }) }}
              </h3>
            </div>
            <VAvatar color="success" variant="tonal" rounded="lg" size="48" class="ms-4">
              <VIcon icon="tabler-currency-dollar" size="26" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4">
        <VCard class="pa-5 border-start border-warning border-xl h-100" elevation="1">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis uppercase font-weight-bold tracking-widest">{{ $t('pages.finance.finance.finance.outstandingReceivables') }}</div>
              <h3 class="text-h4 font-weight-black mt-2 text-warning tracking-tight">
                    ${{ formatLocalizedNumber(totalOutstandingReceivables, { minimumFractionDigits: 2 }) }}
              </h3>
            </div>
            <VAvatar color="warning" variant="tonal" rounded="lg" size="48" class="ms-4">
              <VIcon icon="tabler-clock-bolt" size="26" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4">
        <VCard class="pa-5 border-start border-error border-xl h-100" elevation="1">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis uppercase font-weight-bold tracking-widest">{{ $t('pages.finance.finance.finance.operatingExpensesLogged') }}</div>
              <h3 class="text-h4 font-weight-black mt-2 text-error tracking-tight">
                    ${{ formatLocalizedNumber(totalExpensesLogged, { minimumFractionDigits: 2 }) }}
              </h3>
            </div>
            <VAvatar color="error" variant="tonal" rounded="lg" size="48" class="ms-4">
              <VIcon icon="tabler-report-analytics" size="26" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <VRow class="match-height">
      <VCol cols="12" xl="7" lg="12">
        <VCard>
          <VCardItem class="py-4">
            <VCardTitle class="text-h6 font-weight-bold">{{ $t('pages.finance.finance.finance.invoicesLedgerOperations') }}</VCardTitle>
            <template #append>
              <VBtn color="primary" size="medium" prepend-icon="tabler-file-plus" class="px-4 font-weight-bold"
                @click="openInvoiceModal">
                {{ $t('common.messages.issueInvoice') }}
              </VBtn>
            </template>
          </VCardItem>

          <VCardText class="pb-4">
            <VRow>
              <VCol cols="12" sm="4">
                <VTextField v-model="invoiceSearch" :label="$t('pages.finance.finance.finance.searchByClientTokenOrProject')" density="compact"
                  prepend-inner-icon="tabler-search" hide-details clearable />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect v-model="selectedStatusFilter" :items="['Paid', 'Pending', 'Overdue']" :item-title="translateBackendValue"
                  :label="$t('pages.finance.finance.finance.filterStatus')" density="compact" hide-details clearable />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect v-model="selectedInvoiceAmountRange" :items="[
                  { title: translate('pages.finance.finance.finance.under3000'), value: 'low' },
                  { title: '$3,000 - $6,000', value: 'mid' },
                  { title: translate('pages.finance.finance.finance.over6000'), value: 'high' }
                ]" :label="$t('common.messages.filterByAmount')" density="compact" hide-details clearable />
              </VCol>
            </VRow>
          </VCardText>

          <VDataTable :headers="[
            { title: translate('pages.finance.finance.finance.tokenID'), key: 'id', sortable: true },
            { title: translate('pages.finance.finance.finance.clientAccounts'), key: 'clientName', sortable: true },
            { title: translate('pages.finance.finance.finance.projectType'), key: 'projectType', sortable: true },
            { title: translate('pages.finance.finance.finance.statementBalance'), key: 'amount', sortable: true },
            { title: translate('common.fields.status'), key: 'status', sortable: true },
            { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
          ]" :items="filteredInvoices" class="text-no-wrap">
            <template #item.amount="{ item }">
              <span class="font-weight-black text-body-1">${{ item.amount.toFixed(2) }}</span>
            </template>
            <template #item.status="{ item }">
              <VChip :color="getStatusColor(item.status)" size="small" variant="tonal" class="font-weight-bold cursor-pointer"
                @click="toggleInvoiceStatus(item)">
                {{ translateBackendValue(item.status) }}
              </VChip>
            </template>
            <template #item.actions="{ item }">
              <div class="d-flex justify-end gap-1">
                <VBtn icon="tabler-edit" variant="text" size="small" color="medium-emphasis" @click="openEditInvoice(item)" />
                <VBtn icon="tabler-trash" variant="text" size="small" color="error" @click="deleteInvoice(item.id)" />
              </div>
            </template>
          </VDataTable>
        </VCard>
      </VCol>

      <VCol cols="12" xl="5" lg="12">
        <VCard>
          <VCardItem class="py-4">
            <VCardTitle class="text-h6 font-weight-bold">{{ $t('pages.finance.finance.finance.capitalExpendituresLedger') }}</VCardTitle>
            <template #append>
              <VBtn color="error" size="medium" variant="tonal" prepend-icon="tabler-cash-banknote"
                class="px-4 font-weight-bold" @click="openExpenseModal">
                {{ $t('pages.finance.finance.finance.logExpense') }}
              </VBtn>
            </template>
          </VCardItem>

          <VCardText class="pb-4">
            <VRow>
              <VCol cols="12" sm="4">
                <VTextField v-model="expenseSearch" :label="$t('pages.finance.finance.finance.searchDescriptionOrCategory')" density="compact"
                  prepend-inner-icon="tabler-search" hide-details clearable />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect v-model="expenseCategoryFilter"
                  :items="['Software', 'Salaries', 'Marketing', 'Supplies', 'Infrastructure']" :item-title="translateBackendValue"
                  :label="$t('pages.finance.finance.finance.filterCostPool')" density="compact" hide-details clearable />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect v-model="selectedExpenseAmountRange" :items="[
                  { title: translate('pages.finance.finance.finance.under500'), value: 'low' },
                  { title: '$500 - $1,500', value: 'mid' },
                  { title: translate('pages.finance.finance.finance.over1500'), value: 'high' }
                ]" :label="$t('common.messages.filterByAmount')" density="compact" hide-details clearable />
              </VCol>
            </VRow>
          </VCardText>

          <VDataTable :headers="[
            { title: translate('common.fields.description'), key: 'description', sortable: true },
            { title: translate('pages.finance.finance.finance.costPool'), key: 'category', sortable: true },
            { title: translate('common.fields.date'), key: 'date', sortable: true },
            { title: translate('pages.finance.finance.finance.charge'), key: 'amount', sortable: true },
            { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
          ]" :items="filteredExpenses" class="text-no-wrap">
            <template #item.amount="{ item }">
              <span class="font-weight-black text-body-1 text-error">-${{ item.amount.toFixed(2) }}</span>
            </template>
            <template #item.category="{ item }">
              <VChip size="small" variant="flat" class="font-weight-medium">{{ item.category }}</VChip>
            </template>
            <template #item.actions="{ item }">
              <div class="d-flex justify-end gap-1">
                <VBtn icon="tabler-edit" variant="text" size="small" color="medium-emphasis" @click="openEditExpense(item)" />
                <VBtn icon="tabler-trash" variant="text" size="small" color="error" @click="deleteExpense(item.id)" />
                <VBtn icon="tabler-printer" variant="text" size="small" color="primary" @click="printTable('expenses')" />
              </div>
            </template>
          </VDataTable>
        </VCard>
      </VCol>
    </VRow>

    <!-- Invoice Dialog -->
    <VDialog v-model="isInvoiceDialogActive" max-width="520">
      <VCard class="pa-2 rounded-xl">
        <VCardTitle class="text-h6 font-weight-bold px-4 pt-4">
          {{ isInvoiceEditMode ? 'Modify Invoice' : 'Formulate Accounts Receivable Statement' }}
        </VCardTitle>
        <VCardText class="mt-2">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="invoiceForm.clientName" :label="$t('pages.finance.finance.finance.clientCorporateEnterpriseName')" variant="outlined" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="invoiceForm.projectType" :label="$t('pages.finance.finance.finance.operationalExecutionProjectFocus')" variant="outlined" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model.number="invoiceForm.amount" type="number" prefix="$" :label="$t('pages.finance.finance.finance.grossStatementBalance')" variant="outlined" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model="invoiceForm.dueDate" type="date" :label="$t('pages.finance.finance.finance.maturityPaymentDueDate')" variant="outlined" persistent-placeholder />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect v-model="invoiceForm.status" :items="['Pending', 'Paid', 'Overdue']" :item-title="translateBackendValue" :label="$t('common.fields.status')" variant="outlined" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="px-4 pb-4 gap-2">
          <VSpacer />
          <VBtn color="secondary" variant="text" class="px-4 font-weight-bold" @click="isInvoiceDialogActive = false">
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn color="primary" variant="elevated" class="px-5 font-weight-bold"
            :disabled="!invoiceForm.clientName || !invoiceForm.amount" @click="saveInvoice">{{ $t('common.actions.save') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Expense Dialog -->
    <VDialog v-model="isExpenseDialogActive" max-width="480">
      <VCard class="pa-2 rounded-xl">
        <VCardTitle class="text-h6 font-weight-bold px-4 pt-4">
          {{ isExpenseEditMode ? 'Modify Expense' : 'Record Operating Outflow Balance' }}
        </VCardTitle>
        <VCardText class="mt-2">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="expenseForm.description" :label="$t('pages.finance.finance.finance.transactionalOutflowDescription')" variant="outlined" />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect v-model="expenseForm.category"
                :items="['Software', 'Salaries', 'Marketing', 'Supplies', 'Infrastructure']" :item-title="translateBackendValue"
                :label="$t('pages.finance.finance.finance.targetCostAllocationPool')" variant="outlined" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model.number="expenseForm.amount" type="number" prefix="$" :label="$t('pages.finance.finance.finance.settledAmountOutflow')" variant="outlined" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model="expenseForm.date" type="date" :label="$t('common.fields.date')" variant="outlined" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="px-4 pb-4 gap-2">
          <VSpacer />
          <VBtn color="secondary" variant="text" class="px-4 font-weight-bold" @click="isExpenseDialogActive = false">
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn color="error" variant="elevated" class="px-5 font-weight-bold"
            :disabled="!expenseForm.description || !expenseForm.amount" @click="saveExpense">{{ $t('common.actions.save') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.border-xl {
  border-inline-start-width: 5px !important;
}

.tracking-widest {
  letter-spacing: 0.075em !important;
}

.tracking-tight {
  letter-spacing: -0.025em !important;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
