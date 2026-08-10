<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { VDialog } from 'vuetify/components'
import StockApi, { type StockItem } from '@/api/stock'

const stocks = ref<StockItem[]>([])
const isAddStockDialogVisible = ref(false)
const isEditStockDialogVisible = ref(false)
const isDeleteStockDialogVisible = ref(false)
const selectedStock = ref<StockItem | null>(null)
const search = ref('')
const filterModel = ref<string | null>(null)
const filterOffice = ref<string | null>(null)
const isLoading = ref(false)

const models = ['XPS 15', '27f', 'MX Master']
const offices = ['HQ', 'Branch A', 'Branch B', 'Remote']

const filtered = computed(() => {
  return stocks.value.filter(s => {
    const matchesSearch = s.serial_number.toLowerCase().includes(search.value.toLowerCase()) ||
      s.item.toLowerCase().includes(search.value.toLowerCase())
    const matchesModel = !filterModel.value || s.model === filterModel.value
    const matchesOffice = !filterOffice.value || s.office === filterOffice.value
    return matchesSearch && matchesModel && matchesOffice
  })
})

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Serial Number', key: 'serial_number', sortable: true },
  { title: 'Item', key: 'item', sortable: true },
  { title: 'Quantity', key: 'quantity', sortable: true },
  { title: 'Model', key: 'model', sortable: true },
  { title: 'Office', key: 'office', sortable: true },
  { title: 'Assigned To', key: 'staff.name', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

const fetchStocks = async () => {
  isLoading.value = true
  try {
    const params: Record<string, string> = {}
    if (search.value) params.search = search.value
    if (filterModel.value) params.model = filterModel.value
    if (filterOffice.value) params.office = filterOffice.value

    const response = await StockApi.getAll(params)
    stocks.value = response.data.data
  } catch (error) {
    console.error('Error fetching stocks:', error)
  } finally {
    isLoading.value = false
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
    model: '',
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
      const response = await StockApi.create(stockData)
      stocks.value.unshift(response.data.data)
      isAddStockDialogVisible.value = false
    } catch (error) {
      console.error('Error adding stock:', error)
    }
  }
}

const updateStock = async () => {
  if (selectedStock.value) {
    try {
      const { id, staff, ...stockData } = selectedStock.value
      const response = await StockApi.update(id, stockData)
      const index = stocks.value.findIndex(s => s.id === selectedStock.value?.id)
      if (index !== -1) {
        stocks.value[index] = response.data.data
      }
      isEditStockDialogVisible.value = false
    } catch (error) {
      console.error('Error updating stock:', error)
    }
  }
}

const deleteStock = async () => {
  if (selectedStock.value) {
    try {
      await StockApi.delete(selectedStock.value.id)
      stocks.value = stocks.value.filter(s => s.id !== selectedStock.value?.id)
      isDeleteStockDialogVisible.value = false
    } catch (error) {
      console.error('Error deleting stock:', error)
    }
  }
}

onMounted(() => {
  fetchStocks()
})
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="4">
            <VTextField
              v-model="search"
              prepend-inner-icon="tabler-search"
              placeholder="Search stock..."
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
              label="Filter by Model"
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
              label="Filter by Office"
              density="compact"
              clearable
              hide-details
              @update:modelValue="fetchStocks"
            />
          </VCol>
          <VCol cols="12" md="4" class="text-end d-flex gap-2 justify-end">
            <VBtn
              icon="tabler-printer"
              variant="text"
              @click="printTable"
              title="Print stock records"
            />
            <VBtn
              color="primary"
              @click="openAddStockDialog"
            >
              <VIcon icon="tabler-plus" class="me-1" />
              Add Stock
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

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                @click="openEditStockDialog(item)"
                title="Edit stock record"
              />
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="openDeleteStockDialog(item)"
                title="Delete stock record"
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
          <span class="headline">Add New Stock Item</span>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStock.serial_number"
                  label="Serial Number"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStock.item"
                  label="Item Name"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedStock.quantity"
                  label="Quantity"
                  type="number"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStock.model"
                  :items="models"
                  label="Model"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStock.office"
                  :items="offices"
                  label="Office"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VAutocomplete
                  v-model="selectedStock.staff_id"
                  label="Assign to Staff (Optional)"
                  :items="[]"
                  item-title="name"
                  item-value="id"
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
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="addStock"
          >
            Save
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
          <span class="headline">Edit Stock Item</span>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStock.serial_number"
                  label="Serial Number"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStock.item"
                  label="Item Name"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedStock.quantity"
                  label="Quantity"
                  type="number"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStock.model"
                  :items="models"
                  label="Model"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStock.office"
                  :items="offices"
                  label="Office"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VAutocomplete
                  v-model="selectedStock.staff_id"
                  label="Assign to Staff (Optional)"
                  :items="[]"
                  item-title="name"
                  item-value="id"
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
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="updateStock"
          >
            Save
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
          <span class="headline">Confirm Delete</span>
        </VCardTitle>

        <VCardText>
          Are you sure you want to delete this stock item?
          <br>
          This action cannot be undone.
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteStockDialogVisible = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            @click="deleteStock"
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
