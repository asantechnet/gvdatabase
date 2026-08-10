<script setup lang="ts">
import ServiceApi, { type CreateServiceData, type UpdateServiceData } from '@/api/services'
import CurrencyApi from '@/api/currency'
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'

// --- Interfaces & Types ---
interface ServiceItem {
  id: number
  type: string
  fee: number
  duration: number
  currency: string
  currency_id: number
  status: 'active' | 'inactive'
}

interface CurrencyOption {
  title: string
  value: number
}

// --- Headers matching the table layout ---
const headers = [
  { title: 'TYPE', key: 'type', align: 'start' },
  { title: 'FEE', key: 'fee', align: 'start' },
  { title: 'DURATION (MONTHS)', key: 'duration', align: 'start' },
  { title: 'CURRENCY', key: 'currency', align: 'start' },
  { title: 'STATUS', key: 'status', align: 'start' },
  { title: 'ACTIONS', key: 'actions', align: 'end', sortable: false },
] as const

// --- State ---
const searchQuery = ref('')
const isDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const editedItemId = ref<number | null>(null)
const selectedItemForDelete = ref<number | null>(null)
const isLoading = ref(false)
const toast = useToast()

// Default form state
const defaultItem: Omit<ServiceItem, 'id'> = {
  type: '',
  fee: 0,
  duration: 1,
  currency: 'USD',
  currency_id: 1,
  status: 'active',
}

const editedItem = ref<Omit<ServiceItem, 'id'>>({ ...defaultItem })
const services = ref<ServiceItem[]>([])
const currencyOptions = ref<CurrencyOption[]>([])

// --- Computed ---
const formTitle = computed(() => (editedItemId.value === null ? 'Add New Service' : 'Edit Service'))
const statusOptions: Array<ServiceItem['status']> = ['active', 'inactive']

// --- Lifecycle Hooks ---
onMounted(async () => {
  await fetchServices()
  await fetchCurrencies()
})

// --- API Methods ---
const fetchServices = async () => {
  try {
    isLoading.value = true
    const response = await ServiceApi.getAll()
    services.value = response.data.map(service => ({
      id: service.id,
      type: service.type,
      duration: service.duration,
      fee: Number(service.fee),
      status: service.status as 'active' | 'inactive',
      currency: service.currency.code,
      currency_id: service.currency.id,
    }))
  } catch (error) {
    console.error('Error fetching services:', error)
    toast.error('Failed to load services: ' + (error.message || 'Unknown error'))
  } finally {
    isLoading.value = false
  }
}

const fetchCurrencies = async () => {
  try {
    isLoading.value = true
    const response = await CurrencyApi.getAll()

    // Handle the response structure based on currency.ts API
    const currencies = response.data.data || response.data

    currencyOptions.value = currencies.map((currency: { id: number; code: string }) => ({
      title: currency.code,
      value: currency.id
    }))
  } catch (error) {
    console.error('Error fetching currencies:', error)
    toast.error('Failed to load currencies: ' + (error.message || 'Unknown error'))
  } finally {
    isLoading.value = false
  }
}

const createService = async () => {
  try {
    isLoading.value = true
    const payload: CreateServiceData = {
      type: editedItem.value.type,
      duration: editedItem.value.duration,
      fee: editedItem.value.fee,
      status: editedItem.value.status,
      currency_id: editedItem.value.currency_id
    }
    const response = await ServiceApi.create(payload)
    toast.success('Service created successfully')
    await fetchServices()
    return response
  } catch (error) {
    console.error('Error creating service:', error)
    const errorMessage = error.message || error.errors?.join(', ') || 'Unknown error'
    toast.error('Failed to create service: ' + errorMessage)
    throw error
  } finally {
    isLoading.value = false
  }
}

const updateService = async () => {
  if (editedItemId.value === null) return

  try {
    isLoading.value = true
    const payload: UpdateServiceData = {
      type: editedItem.value.type,
      duration: editedItem.value.duration,
      fee: editedItem.value.fee,
      status: editedItem.value.status,
      currency_id: editedItem.value.currency_id
    }
    const response = await ServiceApi.update(editedItemId.value, payload)

    // Find and update the service in the list with the server response
    const index = services.value.findIndex(s => s.id === editedItemId.value)
    if (index !== -1) {
      services.value[index] = {
        id: response.data.id,
        type: response.data.type,
        duration: response.data.duration,
        fee: Number(response.data.fee),
        status: response.data.status as 'active' | 'inactive',
        currency: response.data.currency.code,
        currency_id: response.data.currency.id,
      }
    }
    toast.success('Service updated successfully')
    return response
  } catch (error) {
    console.error('Error updating service:', error)
    const errorMessage = error.message || error.errors?.join(', ') || 'Unknown error'
    toast.error('Failed to update service: ' + errorMessage)
    throw error
  } finally {
    isLoading.value = false
  }
}

const deleteService = async () => {
  if (selectedItemForDelete.value === null) return

  try {
    isLoading.value = true
    await ServiceApi.delete(selectedItemForDelete.value)
    toast.success('Service deleted successfully')
    await fetchServices()
  } catch (error) {
    console.error('Error deleting service:', error)
    const errorMessage = error.message || error.errors?.join(', ') || 'Unknown error'
    toast.error('Failed to delete service: ' + errorMessage)
  } finally {
    isLoading.value = false
    isDeleteDialogVisible.value = false
  }
}

// --- CRUD Actions ---
const openCreateModal = () => {
  editedItemId.value = null
  editedItem.value = { ...defaultItem }
  isDialogVisible.value = true
}

const openEditModal = (item: ServiceItem) => {
  editedItemId.value = item.id
  editedItem.value = {
    type: item.type,
    fee: item.fee,
    duration: item.duration,
    currency: item.currency,
    currency_id: item.currency_id,
    status: item.status,
  }
  isDialogVisible.value = true
}

const saveService = async () => {
  try {
    if (editedItemId.value === null) {
      await createService()
    } else {
      await updateService()
    }
    isDialogVisible.value = false
  } catch (error) {
    // Error already handled in the individual methods
  }
}

const confirmDelete = (id: number) => {
  selectedItemForDelete.value = id
  isDeleteDialogVisible.value = true
}

// --- Helpers ---
const resolveStatusColor = (status: ServiceItem['status']) => {
  switch (status) {
    case 'active':
      return 'success'
    case 'inactive':
      return 'secondary'
    default:
      return 'primary'
  }
}
</script>

<template>
  <VCard>
    <!-- Card Header & Actions -->
    <VCardText class="d-flex flex-wrap gap-4 align-center justify-space-between">
      <div class="d-flex align-center gap-4 flex-wrap">
        <VTextField v-model="searchQuery" placeholder="Search service..." density="compact" hide-details
          prepend-inner-icon="tabler-search" style="max-inline-size: 280px; min-inline-size: 220px;" />
      </div>

      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreateModal">
        Add Service
      </VBtn>
    </VCardText>

    <VDivider />

    <!-- Data Table -->
    <VDataTable :headers="headers" :items="services" :search="searchQuery" :loading="isLoading" item-value="id"
      class="text-no-wrap">
      <!-- FEE Slot -->
      <template #item.fee="{ item }">
        <span class="font-weight-medium">{{ item.fee.toLocaleString() }}</span>
      </template>

      <!-- DURATION Slot -->
      <template #item.duration="{ item }">
        <span class="font-weight-medium">{{ item.duration }} month{{ item.duration !== 1 ? 's' : '' }}</span>
      </template>

      <!-- STATUS Slot -->
      <template #item.status="{ item }">
        <VChip :color="resolveStatusColor(item.status)" size="small" label class="text-capitalize">
          {{ item.status }}
        </VChip>
      </template>

      <!-- ACTIONS Slot -->
      <template #item.actions="{ item }">
        <div class="d-flex justify-end gap-2">
          <VBtn icon variant="text" size="small" color="default" @click="openEditModal(item)">
            <VIcon icon="tabler-edit" />
          </VBtn>

          <VBtn icon variant="text" size="small" color="error" @click="confirmDelete(item.id)">
            <VIcon icon="tabler-trash" />
          </VBtn>
        </div>
      </template>

      <!-- Loading -->
      <template #loading>
        <div class="d-flex justify-center align-center py-4">
          <VProgressCircular indeterminate color="primary" />
        </div>
      </template>
    </VDataTable>
  </VCard>

  <!-- Create / Edit Dialog -->
  <VDialog v-model="isDialogVisible" max-width="600" persistent>
    <VCard :title="formTitle">
      <VCardText>
        <VRow>
          <VCol cols="12" md="8">
            <VTextField v-model="editedItem.type" label="Service Type" placeholder="e.g. Website Maintenance"
              required />
          </VCol>

          <VCol cols="12" md="4">
            <VSelect v-model="editedItem.currency_id" :items="currencyOptions" label="Currency" item-title="title"
              item-value="value" />
          </VCol>

          <VCol cols="12" md="6">
            <VTextField v-model.number="editedItem.fee" label="Fee" type="number" min="0" step="0.01" />
          </VCol>

          <VCol cols="12" md="6">
            <VTextField v-model.number="editedItem.duration" label="Duration (months)" type="number" min="1" />
          </VCol>

          <VCol cols="12" md="6">
            <VSelect v-model="editedItem.status" :items="statusOptions" label="Status" />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end gap-2 pa-4">
        <VBtn variant="tonal" color="secondary" @click="isDialogVisible = false" :disabled="isLoading">
          Cancel
        </VBtn>
        <VBtn color="primary" @click="saveService" :loading="isLoading">
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Delete Confirmation Dialog -->
  <VDialog v-model="isDeleteDialogVisible" max-width="400">
    <VCard title="Confirm Deletion">
      <VCardText>
        Are you sure you want to delete this service record? This action cannot be undone.
      </VCardText>

      <VCardActions class="justify-end gap-2 pa-4">
        <VBtn variant="tonal" color="secondary" @click="isDeleteDialogVisible = false" :disabled="isLoading">
          Cancel
        </VBtn>
        <VBtn color="error" @click="deleteService" :loading="isLoading">
          Delete
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
