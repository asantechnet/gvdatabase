<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { VDialog } from 'vuetify/components'
import { getIncomes, createIncome, updateIncome, deleteIncome, type Income, type IncomeCreateData, type IncomeUpdateData } from '@/api/incomes'

const incomes = ref<Income[]>([])
const isAddIncomeDialogVisible = ref(false)
const isEditIncomeDialogVisible = ref(false)
const isDeleteIncomeDialogVisible = ref(false)
const selectedIncome = ref<Income | IncomeCreateData | null>(null)
const searchQuery = ref('')
const filterType = ref<string | null>(null)
const filterCurrency = ref<string | null>(null)
const isLoading = ref(false)

const typeOptions = ['Service', 'Product', 'Investment', 'Other']
const currencyOptions = ['USD', 'EUR', 'GBP', 'JPY']

const fetchIncomes = async () => {
  try {
    isLoading.value = true
    const params: any = {}

    if (searchQuery.value) params.search = searchQuery.value
    if (filterType.value) params.type = filterType.value
    if (filterCurrency.value) params.currency = filterCurrency.value

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
  selectedIncome.value = {
    id: 0,
    customer_id: null,
    type: '',
    description: '',
    amount: 0,
    quantity: 1,
    currency: 'USD',
    rate_to_base: 1.0,
    total_amount: 0,
    date: new Date().toISOString().split('T')[0]
  }
  isAddIncomeDialogVisible.value = true
}

const openEditIncomeDialog = (income: Income) => {
  selectedIncome.value = {...income}
  isEditIncomeDialogVisible.value = true
}

const openDeleteIncomeDialog = (income: Income) => {
  selectedIncome.value = income
  isDeleteIncomeDialogVisible.value = true
}

const addIncome = async () => {
  if (selectedIncome.value) {
    try {
      const incomeData = {...selectedIncome.value} as IncomeCreateData
      const createdIncome = await createIncome(incomeData)
      incomes.value.unshift(createdIncome)
      isAddIncomeDialogVisible.value = false
    } catch (error) {
      console.error('Error creating income:', error)
    }
  }
}

const updateIncomeRecord = async () => {
  if (selectedIncome.value && 'id' in selectedIncome.value) {
    try {
      const incomeData = {...selectedIncome.value} as IncomeUpdateData
      const updatedIncome = await updateIncome(selectedIncome.value.id, incomeData)
      const index = incomes.value.findIndex(i => i.id === updatedIncome.id)
      if (index !== -1) {
        incomes.value[index] = updatedIncome
      }
      isEditIncomeDialogVisible.value = false
    } catch (error) {
      console.error('Error updating income:', error)
    }
  }
}

const deleteIncomeRecord = async () => {
  if (selectedIncome.value && 'id' in selectedIncome.value) {
    try {
      await deleteIncome(selectedIncome.value.id)
      incomes.value = incomes.value.filter(i => i.id !== selectedIncome.value?.id)
      isDeleteIncomeDialogVisible.value = false
    } catch (error) {
      console.error('Error deleting income:', error)
    }
  }
}

const calculateTotal = () => {
  if (selectedIncome.value) {
    selectedIncome.value.total_amount = selectedIncome.value.amount * selectedIncome.value.quantity * selectedIncome.value.rate_to_base
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

const printTable = () => {
  window.print()
}

onMounted(() => {
  fetchIncomes()
})
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="4">
            <VTextField
              v-model="searchQuery"
              prepend-inner-icon="tabler-search"
              placeholder="Search income..."
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
              label="Filter by Type"
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
              label="Filter by Currency"
              density="compact"
              clearable
              hide-details
              @update:modelValue="fetchIncomes"
            />
          </VCol>

          <VCol cols="12" md="4" class="d-flex gap-2 justify-end">
            <VBtn
              icon="tabler-printer"
              variant="text"
              @click="printTable"
              title="Print income records"
            />
            <VBtn
              color="primary"
              @click="openAddIncomeDialog"
            >
              <VIcon icon="tabler-plus" class="me-1" />
              Add Income
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <VDataTable
          :items="incomes"
          :headers="[
            { title: 'ID', key: 'id', align: 'start' },
            { title: 'Type', key: 'type', align: 'start' },
            { title: 'Description', key: 'description', align: 'start' },
            { title: 'Amount', key: 'amount', align: 'end' },
            { title: 'Currency', key: 'currency', align: 'center' },
            { title: 'Total', key: 'total_amount', align: 'end' },
            { title: 'Date', key: 'date', align: 'center' },
            { title: 'Actions', key: 'actions', align: 'center', sortable: false }
          ]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
        >
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
            <span class="text-success">{{ formatCurrency(item.amount) }}</span>
          </template>

          <template #item.currency="{ item }">
            <span>{{ item.currency }}</span>
          </template>

          <template #item.total_amount="{ item }">
            <span class="text-success">{{ formatCurrency(item.total_amount) }}</span>
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
                title="Edit income record"
              />
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="openDeleteIncomeDialog(item)"
                title="Delete income record"
              />
            </div>
          </template>
        </VDataTable>
      </div>
    </VCard>

    <!-- Add Income Dialog -->
    <VDialog
      v-model="isAddIncomeDialogVisible"
      max-width="800px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">Add New Income</span>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.type"
                  :items="typeOptions"
                  label="Type"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedIncome.description"
                  label="Description"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.amount"
                  label="Amount"
                  type="number"
                  step="0.01"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.quantity"
                  label="Quantity"
                  type="number"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.currency"
                  :items="currencyOptions"
                  label="Currency"
                  @update:modelValue="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.rate_to_base"
                  label="Exchange Rate"
                  type="number"
                  step="0.01"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedIncome.total_amount"
                  label="Total Amount"
                  type="number"
                  readonly
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedIncome.date"
                  label="Date"
                  type="date"
                  required
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
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="addIncome"
          >
            Save
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Income Dialog -->
    <VDialog
      v-model="isEditIncomeDialogVisible"
      max-width="800px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">Edit Income</span>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.type"
                  :items="typeOptions"
                  label="Type"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedIncome.description"
                  label="Description"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.amount"
                  label="Amount"
                  type="number"
                  step="0.01"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.quantity"
                  label="Quantity"
                  type="number"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.currency"
                  :items="currencyOptions"
                  label="Currency"
                  @update:modelValue="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedIncome.rate_to_base"
                  label="Exchange Rate"
                  type="number"
                  step="0.01"
                  @input="calculateTotal"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedIncome.total_amount"
                  label="Total Amount"
                  type="number"
                  readonly
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedIncome.date"
                  label="Date"
                  type="date"
                  required
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
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="updateIncomeRecord"
          >
            Save
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
          <span class="headline">Confirm Delete</span>
        </VCardTitle>

        <VCardText>
          Are you sure you want to delete this income record?
          <br>
          This action cannot be undone.
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteIncomeDialogVisible = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            @click="deleteIncomeRecord"
          >
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
