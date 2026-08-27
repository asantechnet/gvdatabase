<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { VDialog } from 'vuetify/components'
import { useToast } from 'vue-toastification'
import StockApi, { type StockCondition, type StockItem, type StockParams } from '@/api/stock'
import EmployeeApi, { type Employee } from '@/api/employee'
import OfficeApi, { type Office } from '@/api/office'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'

const toast = useToast()

const stocks = ref<StockItem[]>([])
const employees = ref<Employee[]>([])
const isAddStockDialogVisible = ref(false)
const isEditStockDialogVisible = ref(false)
const isDeleteStockDialogVisible = ref(false)
const selectedStock = ref<StockItem | null>(null)
const search = ref('')
const filterModel = ref<string | null>(null)
const filterOffice = ref<number | null>(null)
const filterCondition = ref<StockCondition | null>(null)
const isLoading = ref(false)
const employeesLoading = ref(false)
const officeDirectory = ref<Office[]>([])

const models = computed(() => [...new Set(stocks.value.map(stock => stock.model).filter(Boolean))].sort())
const officeOptions = computed(() => {
  const byId = new Map<number, { title: string; value: number; active: boolean }>()
  officeDirectory.value.forEach(office => byId.set(office.id, {
    title: `${office.name} (${office.code})`,
    value: office.id,
    active: office.status === 'active',
  }))
  stocks.value.forEach(stock => {
    if (stock.office && !byId.has(stock.office.id))
      byId.set(stock.office.id, { title: `${stock.office.name} (${stock.office.code})`, value: stock.office.id, active: stock.office.status === 'active' })
  })
  return [...byId.values()].sort((left, right) => left.title.localeCompare(right.title))
})
const activeOfficeOptions = computed(() => officeOptions.value.filter(office => office.active || office.value === selectedStock.value?.office_id))
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
    const matchesOffice = !filterOffice.value || s.office_id === filterOffice.value
    const matchesCondition = !filterCondition.value || s.condition === filterCondition.value
    return matchesSearch && matchesModel && matchesOffice && matchesCondition
  })
})

const headers = [
  { title: translate('pages.finance.stock.stock.serialNumber'), key: 'serial_number', sortable: true },
  { title: translate('pages.finance.stock.stock.item'), key: 'item', sortable: true },
  { title: translate('pages.finance.stock.stock.quantity'), key: 'quantity', sortable: true },
  { title: 'Price', key: 'price', sortable: true },
  { title: translate('pages.finance.stock.stock.model'), key: 'model', sortable: true },
  { title: translate('pages.finance.stock.stock.condition'), key: 'condition', sortable: true },
  { title: translate('common.fields.office'), key: 'office.name', sortable: true },
  { title: translate('pages.finance.stock.stock.assignedTo'), key: 'staff.name', sortable: true },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false },
]

const fetchStocks = async () => {
  isLoading.value = true
  try {
    const params: StockParams = {}
    if (search.value) params.search = search.value
    if (filterModel.value) params.model = filterModel.value
    if (filterOffice.value) params.office_id = filterOffice.value
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
  void printDocument({ bodyClass: 'printing-table-stock', pageStyle: 'size: A4 landscape; margin: 12mm;' })
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
    office_id: officeDirectory.value.find(office => office.status === 'active')?.id ?? null,
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
      const { id, staff, office, ...stockData } = selectedStock.value
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
      const { id, staff, office, ...stockData } = selectedStock.value
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

const debouncedFetchStocks = useDebounceFn(fetchStocks, 300)

watch([search, filterModel, filterOffice, filterCondition], () => {
  void debouncedFetchStocks()
})

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
    <DataTablePageKpis :items="filtered" title="Stock inventory" description="Keep inventory levels, condition, and assignments visible in one place." icon="tabler-packages" />
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="7">
            <VTextField
              v-model="search"
              class="data-table-search" prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.finance.stock.stock.searchStock')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>
          <VCol cols="12" md="5" class="text-end d-flex gap-2 justify-end">
            <DataTableFilterMenu :active="Boolean(filterModel || filterOffice || filterCondition)"
              @reset="filterModel = null; filterOffice = null; filterCondition = null">
              <VSelect v-model="filterModel" :items="models" :label="$t('pages.finance.stock.stock.filterByModel')" density="compact" clearable hide-details />
              <VSelect v-model="filterOffice" :items="officeOptions" :label="$t('pages.finance.stock.stock.filterByOffice')" density="compact" clearable hide-details />
              <VSelect v-model="filterCondition" :items="conditions" :label="$t('pages.finance.stock.stock.filterByCondition')" density="compact" clearable hide-details />
            </DataTableFilterMenu>
            <VBtn icon variant="text" @click="printTable">
              <VIcon icon="tabler-printer" />
              <VTooltip activator="parent" location="top">{{ $t('pages.finance.stock.stock.printStockRecords') }}</VTooltip>
            </VBtn>
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
        <ResponsiveDataTable
          :headers="headers"
          :items="filtered"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
          sticky-last-column
        >
          <template #item.staff.name="{ item }">
            {{ item.staff?.name || '-' }}
          </template>

          <template #item.office.name="{ item }">
            {{ item.office ? `${item.office.name} (${item.office.code})` : '—' }}
          </template>

          <template #item.condition="{ item }">
            <VChip :color="conditionColor(item.condition)" size="small" variant="tonal" label>
              {{ conditions.find(condition => condition.value === item.condition)?.title || item.condition }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <FullDetailsButton :item="item" :title="item.item_name || item.name || item.serial_number" />
              <VBtn icon variant="text"
                size="small"
                color="medium-emphasis"
                @click="openEditStockDialog(item)"
              >
                <VIcon icon="tabler-edit" />
                <VTooltip activator="parent" location="top">{{ $t('pages.finance.stock.stock.editStockRecord') }}</VTooltip>
              </VBtn>
              <VBtn icon variant="text"
                size="small"
                color="error"
                @click="openDeleteStockDialog(item)"
              >
                <VIcon icon="tabler-trash" />
                <VTooltip activator="parent" location="top">{{ $t('pages.finance.stock.stock.deleteStockRecord') }}</VTooltip>
              </VBtn>
            </div>
          </template>
        </ResponsiveDataTable>
      </div>
    </VCard>

    <PrintableListReport title="Stock Report" subtitle="Filtered stock records" :columns="['Serial number', 'Item', 'Quantity', 'Price', 'Model', 'Condition', 'Office', 'Assigned to']" :rows="filtered.map(stock => [stock.serial_number, stock.item, stock.quantity, stock.price, stock.model, stock.condition, stock.office?.name, stock.staff?.name])" />

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
          <VForm class="app-form">
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
                  v-model="selectedStock.office_id"
                  :items="activeOfficeOptions"
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
          <VForm class="app-form">
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
                  v-model="selectedStock.office_id"
                  :items="activeOfficeOptions"
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
  min-inline-size: 0;
}
</style>
