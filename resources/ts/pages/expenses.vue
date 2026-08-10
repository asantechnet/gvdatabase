<script setup lang="ts">
import { createExpense, deleteExpense, getExpenses, updateExpense, type Expense, type ExpenseCreateData } from '@/api/expenses'
import { onMounted, ref } from 'vue'
import { VDialog } from 'vuetify/components'

const expenses = ref<Expense[]>([])
const isAddExpenseDialogVisible = ref(false)
const isEditExpenseDialogVisible = ref(false)
const isDeleteExpenseDialogVisible = ref(false)
const selectedExpense = ref<Expense | ExpenseCreateData | null>(null)
const searchQuery = ref('')
const filterType = ref<string | null>(null)
const filterCurrency = ref<string | null>(null)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

const typeOptions = ['Utilities', 'Software/SaaS', 'Marketing', 'Office Supplies', 'Travel & Dining', 'Salaries']
const currencyOptions = ['USD', 'EUR', 'GBP', 'JPY']

const fetchExpenses = async () => {
  try {
    isLoading.value = true
    errorMessage.value = null
    const params: any = {}

    if (searchQuery.value) params.search = searchQuery.value
    if (filterType.value) params.type = filterType.value
    if (filterCurrency.value) params.currency = filterCurrency.value

    const response = await getExpenses(params)
    expenses.value = response
  } catch (error) {
    console.error('Error fetching expenses:', error)
    errorMessage.value = 'Failed to load expenses. Please try again.'
    expenses.value = []
  } finally {
    isLoading.value = false
  }
}

const printTable = () => {
  window.print()
}

const openAddExpenseDialog = () => {
  selectedExpense.value = {
    id: 0,
    customer_id: null,
    type: '',
    description: '',
    amount: 0,
    quantity: 1,
    currency: 'USD',
    rate_to_base: 1.0,
    date: new Date().toISOString().split('T')[0]
  }
  isAddExpenseDialogVisible.value = true
}

const openEditExpenseDialog = (expense: Expense) => {
  selectedExpense.value = { ...expense }
  isEditExpenseDialogVisible.value = true
}

const openDeleteExpenseDialog = (expense: Expense) => {
  selectedExpense.value = { ...expense }
  isDeleteExpenseDialogVisible.value = true
}

const addExpense = async () => {
  if (!selectedExpense.value) return

  try {
    const expenseData = {
      customer_id: selectedExpense.value.customer_id,
      type: selectedExpense.value.type,
      description: selectedExpense.value.description,
      amount: selectedExpense.value.amount,
      quantity: selectedExpense.value.quantity,
      currency: selectedExpense.value.currency,
      rate_to_base: selectedExpense.value.rate_to_base,
      date: selectedExpense.value.date
    }

    const createdExpense = await createExpense(expenseData)
    expenses.value.unshift(createdExpense)
    isAddExpenseDialogVisible.value = false
  } catch (error) {
    console.error('Error creating expense:', error)
    errorMessage.value = 'Failed to create expense. Please try again.'
  }
}

const handleUpdateExpense = async () => {
  if (!selectedExpense.value || !('id' in selectedExpense.value)) return

  try {
    const expenseData = {
      customer_id: selectedExpense.value.customer_id,
      type: selectedExpense.value.type,
      description: selectedExpense.value.description,
      amount: selectedExpense.value.amount,
      quantity: selectedExpense.value.quantity,
      currency: selectedExpense.value.currency,
      rate_to_base: selectedExpense.value.rate_to_base,
      date: selectedExpense.value.date
    }

    const updatedExpense = await updateExpense(selectedExpense.value.id, expenseData)

    // Find and update the expense in the array
    const index = expenses.value.findIndex(e => e.id === updatedExpense.id)
    if (index !== -1) {
      expenses.value[index] = updatedExpense
    } else {
      // If not found, add it to the beginning of the array
      expenses.value.unshift(updatedExpense)
    }

    isEditExpenseDialogVisible.value = false
  } catch (error) {
    console.error('Error updating expense:', error)
    errorMessage.value = 'Failed to update expense. Please try again.'
  }
}

const handleDeleteExpense = async () => {
  if (!selectedExpense.value || !('id' in selectedExpense.value)) return

  try {
    await deleteExpense(selectedExpense.value.id)
    // Remove the expense from the array
    expenses.value = expenses.value.filter(e => e.id !== selectedExpense.value?.id)
    isDeleteExpenseDialogVisible.value = false
  } catch (error) {
    console.error('Error deleting expense:', error)
    errorMessage.value = 'Failed to delete expense. Please try again.'
  }
}

const calculateTotal = () => {
  if (selectedExpense.value) {
    selectedExpense.value.total_amount = selectedExpense.value.amount * selectedExpense.value.quantity * selectedExpense.value.rate_to_base
  }
}

const formatCurrency = (value: number | string | undefined | null): string => {
  if (value === undefined || value === null || value === '') return '$0.00'

  // Convert string to number if needed
  const numericValue = typeof value === 'string' ? parseFloat(value) : value

  // Check if the conversion resulted in a valid number
  if (isNaN(numericValue)) return '$0.00'

  return `$${numericValue.toFixed(2)}`
}

const formatDate = (dateString: string | undefined | null): string => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString()
}

onMounted(() => {
  fetchExpenses()
})
</script>

<template>
  <div>
    <VAlert v-if="errorMessage" type="error" dismissible @update:modelValue="errorMessage = null">
      {{ errorMessage }}
    </VAlert>

    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="4">
            <VTextField v-model="searchQuery" prepend-inner-icon="tabler-search" placeholder="Search expenses..."
              density="compact" clearable hide-details @keyup.enter="fetchExpenses" />
          </VCol>

          <VCol cols="12" sm="6" md="2">
            <VSelect v-model="filterType" :items="typeOptions" label="Filter by Type" density="compact" clearable
              hide-details @update:modelValue="fetchExpenses" />
          </VCol>

          <VCol cols="12" sm="6" md="2">
            <VSelect v-model="filterCurrency" :items="currencyOptions" label="Filter by Currency" density="compact"
              clearable hide-details @update:modelValue="fetchExpenses" />
          </VCol>

          <VCol cols="12" md="4" class="d-flex gap-2 justify-end">
            <VBtn icon="tabler-printer" variant="text" @click="printTable" title="Print expense records" />
            <VBtn color="primary" @click="openAddExpenseDialog">
              <VIcon icon="tabler-plus" class="me-1" />
              Add Expense
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <VDataTable :items="expenses" :headers="[
          { title: 'ID', key: 'id', align: 'start' },
          { title: 'Type', key: 'type', align: 'start' },
          { title: 'Description', key: 'description', align: 'start' },
          { title: 'Amount', key: 'amount', align: 'end' },
          { title: 'Currency', key: 'currency', align: 'center' },
          { title: 'Total', key: 'total_amount', align: 'end' },
          { title: 'Date', key: 'date', align: 'center' },
          { title: 'Actions', key: 'actions', align: 'center', sortable: false }
        ]" item-value="id" class="text-no-wrap" :loading="isLoading" mobile-breakpoint="sm" density="compact">
          <template #item.id="{ item }">
            <span>{{ item.id }}</span>
          </template>

          <template #item.type="{ item }">
            <span>{{ item.type }}</span>
          </template>

          <template #item.description="{ item }">
            <span>{{ item.description }}</span>
          </template>

          <template #item.amount="{ item }">
            <span>{{ formatCurrency(item.amount) }}</span>
          </template>

          <template #item.currency="{ item }">
            <span>{{ item.currency }}</span>
          </template>

          <template #item.total_amount="{ item }">
            <span>{{ formatCurrency(item.total_amount) }}</span>
          </template>

          <template #item.date="{ item }">
            <span>{{ formatDate(item.date) }}</span>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn icon="tabler-edit" variant="text" size="small" color="medium-emphasis"
                @click="openEditExpenseDialog(item)" title="Edit expense record" />
              <VBtn icon="tabler-trash" variant="text" size="small" color="error" @click="openDeleteExpenseDialog(item)"
                title="Delete expense record" />
            </div>
          </template>

          <template #no-data>
            <div class="pa-4 text-center">
              <div v-if="isLoading">Loading expenses...</div>
              <div v-else-if="errorMessage">Error loading expenses</div>
              <div v-else>No expenses found</div>
            </div>
          </template>
        </VDataTable>
      </div>
    </VCard>

    <!-- Add Expense Dialog -->
    <VDialog v-model="isAddExpenseDialogVisible" max-width="800px">
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">Add New Expense</span>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12" md="6">
                <VSelect v-model="selectedExpense.type" :items="typeOptions" label="Type" required />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedExpense.description" label="Description" required />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model.number="selectedExpense.amount" label="Amount" type="number" step="0.01"
                  @input="calculateTotal" required />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model.number="selectedExpense.quantity" label="Quantity" type="number"
                  @input="calculateTotal" required />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect v-model="selectedExpense.currency" :items="currencyOptions" label="Currency"
                  @update:modelValue="calculateTotal" required />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model.number="selectedExpense.rate_to_base" label="Exchange Rate" type="number"
                  step="0.01" @input="calculateTotal" required />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedExpense.total_amount" label="Total Amount" type="number" readonly />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedExpense.date" label="Date" type="date" required />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="isAddExpenseDialogVisible = false">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="addExpense">
            Save
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Expense Dialog -->
    <VDialog v-model="isEditExpenseDialogVisible" max-width="800px">
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">Edit Expense</span>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12" md="6">
                <VSelect v-model="selectedExpense.type" :items="typeOptions" label="Type" required />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedExpense.description" label="Description" required />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model.number="selectedExpense.amount" label="Amount" type="number" step="0.01"
                  @input="calculateTotal" required />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model.number="selectedExpense.quantity" label="Quantity" type="number"
                  @input="calculateTotal" required />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect v-model="selectedExpense.currency" :items="currencyOptions" label="Currency"
                  @update:modelValue="calculateTotal" required />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model.number="selectedExpense.rate_to_base" label="Exchange Rate" type="number"
                  step="0.01" @input="calculateTotal" required />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedExpense.total_amount" label="Total Amount" type="number" readonly />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedExpense.date" label="Date" type="date" required />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="isEditExpenseDialogVisible = false">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="handleUpdateExpense">
            Save
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Expense Dialog -->
    <VDialog v-model="isDeleteExpenseDialogVisible" max-width="500px">
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">Confirm Delete</span>
        </VCardTitle>

        <VCardText>
          Are you sure you want to delete this expense record?
          <br>
          This action cannot be undone.
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="isDeleteExpenseDialogVisible = false">
            Cancel
          </VBtn>
          <VBtn color="error" @click="handleDeleteExpense">
            Delete
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.table-container {
  overflow-x: auto;
}
</style>
