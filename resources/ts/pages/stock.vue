<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { VDialog } from 'vuetify/components'
import { useToast } from 'vue-toastification'
import StockApi, { type StockCondition, type StockItem, type StockParams } from '@/api/stock'
import EmployeeApi, { type Employee } from '@/api/employee'
import OfficeApi, { type Office } from '@/api/office'

const toast = useToast()

const stocks = ref<StockItem[]>([])
const employees = ref<Employee[]>([])
const isAddStockDialogVisible = ref(false)
const isEditStockDialogVisible = ref(false)
const isDeleteStockDialogVisible = ref(false)
const selectedStock = ref<StockItem | null>(null)
const search = ref('')
const filterModel = ref<string | null>(null)
const filterOffice = ref<string | null>(null)
const filterCondition = ref<StockCondition | null>(null)
const isLoading = ref(false)
const employeesLoading = ref(false)
const officeDirectory = ref<Office[]>([])

const models = computed(() => [...new Set(stocks.value.map(stock => stock.model).filter(Boolean))].sort())
const offices = computed(() => [...new Set([
  ...officeDirectory.value.filter(office => office.status === 'active').map(office => office.name),
  ...stocks.value.map(stock => stock.office),
])].filter(Boolean).sort())
const conditions: Array<{ title: string; value: StockCondition }> = [
  { title: translate('pages.finance.stock.stock.new'), value: 'new' },
  { title: translate('pages.finance.stock.stock.good'), value: 'good' },
  { title: translate('pages.finance.stock.stock.fair'), value: 'fair' },
  { title: translate('pages.finance.stock.stock.poor'), value: 'poor' },
  { title: translate('pages.finance.stock.stock.damaged'), value: 'damaged' },
]

const conditionColor = (condition: StockCondition) => ({
  new: 'info',
  good: 'success',
  fair: 'warning',
  poor: 'orange-darken-2',
  damaged: 'error',
}[condition])

const filtered = computed(() => {
  return stocks.value.filter(s => {
    const matchesSearch = s.serial_number.toLowerCase().includes(search.value.toLowerCase()) ||
      s.item.toLowerCase().includes(search.value.toLowerCase())
    const matchesModel = !filterModel.value || s.model === filterModel.value
    const matchesOffice = !filterOffice.value || s.office === filterOffice.value
    const matchesCondition = !filterCondition.value || s.condition === filterCondition.value
    return matchesSearch && matchesModel && matchesOffice && matchesCondition
  })
})

const headers = [
  { title: translate('common.messages.id'), key: 'id' },
  { title: translate('pages.finance.stock.stock.serialNumber'), key: 'serial_number', sortable: true },
  { title: translate('pages.finance.stock.stock.item'), key: 'item', sortable: true },
  { title: translate('pages.finance.stock.stock.quantity'), key: 'quantity', sortable: true },
  { title: 'Price', key: 'price', sortable: true },
  { title: translate('pages.finance.stock.stock.model'), key: 'model', sortable: true },
  { title: translate('pages.finance.stock.stock.condition'), key: 'condition', sortable: true },
  { title: translate('common.fields.office'), key: 'office', sortable: true },
  { title: translate('pages.finance.stock.stock.assignedTo'), key: 'staff.name', sortable: true },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false },
]

const fetchStocks = async () => {
  isLoading.value = true
  try {
    const params: StockParams = {}
    if (search.value) params.search = search.value
    if (filterModel.value) params.model = filterModel.value
    if (filterOffice.value) params.office = filterOffice.value
    if (filterCondition.value) params.condition = filterCondition.value

    stocks.value = await StockApi.getAll(params)
  } catch (error) {
    console.error('Error fetching stocks:', error)
    toast.error(translate('pages.finance.stock.stock.failedToFetchStockRecords'))
  } finally {
    isLoading.value = false
  }
}

const fetchEmployees = async () => {
  try {
    employeesLoading.value = true
    employees.value = await EmployeeApi.getOptions()
  } catch (error) {
    console.error('Error fetching employees:', error)
    toast.error(translate('pages.finance.stock.stock.failedToFetchEmployees'))
  } finally {
    employeesLoading.value = false
  }
}

const printTable = () => {
  window.print()
}

const openAddStockDialog = () => {
  selectedStock.value = {
    id: 0,
    serial_number: '',
    item: '',
    quantity: 1,
    price: 0,
    model: '',
    condition: 'good',
    office: '',
    staff_id: null
  }
  isAddStockDialogVisible.value = true
}

const openEditStockDialog = (stock: StockItem) => {
  selectedStock.value = {...stock}
  isEditStockDialogVisible.value = true
}

const openDeleteStockDialog = (stock: StockItem) => {
  selectedStock.value = stock
  isDeleteStockDialogVisible.value = true
}

const addStock = async () => {
  if (selectedStock.value) {
    try {
      const { id, staff, ...stockData } = selectedStock.value
      const stock = await StockApi.create(stockData)
      stocks.value.unshift(stock)
      isAddStockDialogVisible.value = false
      toast.success(translate('pages.finance.stock.stock.stockItemAdded'))
    } catch (error) {
      console.error('Error adding stock:', error)
      toast.error(translate('pages.finance.stock.stock.failedToAddStockItem'))
    }
  }
}

const updateStock = async () => {
  if (selectedStock.value) {
    try {
      const { id, staff, ...stockData } = selectedStock.value
      const stock = await StockApi.update(id, stockData)
      const index = stocks.value.findIndex(s => s.id === selectedStock.value?.id)
      if (index !== -1) {
        stocks.value[index] = stock
      }
      isEditStockDialogVisible.value = false
      toast.success(translate('pages.finance.stock.stock.stockItemUpdated'))
    } catch (error) {
      console.error('Error updating stock:', error)
      toast.error(translate('pages.finance.stock.stock.failedToUpdateStockItem'))
    }
  }
}

const deleteStock = async () => {
  if (selectedStock.value) {
    try {
      await StockApi.delete(selectedStock.value.id)
      stocks.value = stocks.value.filter(s => s.id !== selectedStock.value?.id)
      isDeleteStockDialogVisible.value = false
      toast.success(translate('pages.finance.stock.stock.stockItemDeleted'))
    } catch (error) {
      console.error('Error deleting stock:', error)
      toast.error(translate('pages.finance.stock.stock.failedToDeleteStockItem'))
    }
  }
}

onMounted(() => {
  fetchStocks()
  fetchEmployees()
  OfficeApi.getAll().then(data => { officeDirectory.value = data }).catch(error => {
    console.error('Error fetching offices:', error)
    toast.error(translate('pages.finance.stock.stock.failedToFetchOffices'))
  })
})
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="3">
            <VTextField
              v-model="search"
              prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.finance.stock.stock.searchStock')"
              density="compact"
              clearable
              hide-details
              @input="fetchStocks"
            />
          </VCol>
          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filterModel"
              :items="models"
              :label="$t('pages.finance.stock.stock.filterByModel')"
              density="compact"
              clearable
              hide-details
              @update:modelValue="fetchStocks"
            />
          </VCol>
          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filterOffice"
              :items="offices"
              :label="$t('pages.finance.stock.stock.filterByOffice')"
              density="compact"
              clearable
              hide-details
              @update:modelValue="fetchStocks"
            />
          </VCol>
          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filterCondition"
              :items="conditions"
              :label="$t('pages.finance.stock.stock.filterByCondition')"
              density="compact"
              clearable
              hide-details
              @update:modelValue="fetchStocks"
            />
          </VCol>
          <VCol cols="12" md="3" class="text-end d-flex gap-2 justify-end">
            <VBtn
              icon="tabler-printer"
              variant="text"
              @click="printTable"
              :title="$t('pages.finance.stock.stock.printStockRecords')"
            />
            <VBtn
              color="primary"
              @click="openAddStockDialog"
            >
              <VIcon icon="tabler-plus" class="me-1" />
              {{ $t('pages.finance.stock.stock.addStock') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <VDataTable
          :headers="headers"
          :items="filtered"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
        >
          <template #item.staff.name="{ item }">
            {{ item.staff?.name || '-' }}
          </template>

          <template #item.condition="{ item }">
            <VChip :color="conditionColor(item.condition)" size="small" variant="tonal" label>
              {{ conditions.find(condition => condition.value === item.condition)?.title || item.condition }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                @click="openEditStockDialog(item)"
                :title="$t('pages.finance.stock.stock.editStockRecord')"
              />
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="openDeleteStockDialog(item)"
                :title="$t('pages.finance.stock.stock.deleteStockRecord')"
              />
            </div>
          </template>
        </VDataTable>
      </div>
    </VCard>

    <!-- Add Stock Dialog -->
    <VDialog
      v-model="isAddStockDialogVisible"
      max-width="800px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('pages.finance.stock.stock.addNewStockItem') }}</span>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStock.serial_number"
                  :label="$t('common.messages.serialNumber')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStock.item"
                  :label="$t('common.messages.itemName')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedStock.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  required
                />
              </VCol>
              <VCol cols="12" md="6"><VTextField v-model.number="selectedStock.price" label="Price" type="number" min="0" required /></VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStock.model"
                  :label="$t('common.messages.model')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStock.office"
                  :items="offices"
                  :label="$t('common.fields.office')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStock.condition"
                  :items="conditions"
                  :label="$t('common.messages.condition')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VAutocomplete
                  v-model="selectedStock.staff_id"
                  :label="$t('common.messages.assignToStaffOptional')"
                  :items="employees"
                  item-title="name"
                  item-value="id"
                  :loading="employeesLoading"
                  clearable
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
            @click="isAddStockDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="addStock"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Stock Dialog -->
    <VDialog
      v-model="isEditStockDialogVisible"
      max-width="800px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('pages.finance.stock.stock.editStockItem') }}</span>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStock.serial_number"
                  :label="$t('common.messages.serialNumber')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStock.item"
                  :label="$t('common.messages.itemName')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedStock.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  required
                />
              </VCol>
              <VCol cols="12" md="6"><VTextField v-model.number="selectedStock.price" label="Price" type="number" min="0" required /></VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStock.model"
                  :label="$t('common.messages.model')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStock.office"
                  :items="offices"
                  :label="$t('common.fields.office')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStock.condition"
                  :items="conditions"
                  :label="$t('common.messages.condition')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VAutocomplete
                  v-model="selectedStock.staff_id"
                  :label="$t('common.messages.assignToStaffOptional')"
                  :items="employees"
                  item-title="name"
                  item-value="id"
                  :loading="employeesLoading"
                  clearable
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
            @click="isEditStockDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="updateStock"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Stock Dialog -->
    <VDialog
      v-model="isDeleteStockDialogVisible"
      max-width="500px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('common.messages.confirmDelete') }}</span>
        </VCardTitle>

        <VCardText>
          {{ $t('common.confirmations.areYouSureYouWantToDeleteThisStockItem') }}
          <br>
          {{ $t('common.confirmations.thisActionCannotBeUndone') }}
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteStockDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            @click="deleteStock"
          >
            {{ $t('common.actions.delete') }}
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
