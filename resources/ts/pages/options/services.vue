<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import ServiceForm from '@/components/forms/ServiceForm.vue'
import ServiceApi, { type CreateServiceData, type ServiceContractTemplate, type ServiceVariant, type UpdateServiceData } from '@/api/services'
import CurrencyApi from '@/api/currency'
import { apiErrorMessage } from '@/utils/apiErrors'

// --- Interfaces & Types ---
interface ServiceItem {
  id: number
  type: string
  code?: string | null
  description?: string | null
  fee: number
  currency: string
  currency_id: number
  status: 'active' | 'inactive'
  contractTemplate?: ServiceContractTemplate | null
  variants?: ServiceVariant[]
}

interface CurrencyOption {
  title: string
  value: number
}

// --- Headers matching the table layout ---
const headers = [
  { title: 'Service', key: 'type', align: 'start' }, { title: translate('pages.contracts.services.services.serviceCode'), key: 'code', align: 'start' },
  { title: translate('pages.contracts.services.services.fee'), key: 'fee', align: 'start' },
  { title: translate('common.fields.currency2'), key: 'currency', align: 'start' },
  { title: translate('common.fields.status2'), key: 'status', align: 'start' },
  { title: translate('pages.contracts.services.services.contractTemplate'), key: 'contract_template', align: 'start' },
  { title: translate('pages.contracts.services.services.actions'), key: 'actions', align: 'end', sortable: false },
] as const

// --- State ---
const searchQuery = ref('')
const isDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const editedItemId = ref<number | null>(null)
const selectedItemForDelete = ref<number | null>(null)
const isLoading = ref(false)
const toast = useToast()
const router = useRouter()

// Default form state
const defaultItem: Omit<ServiceItem, 'id'> = {
  type: '',
  code: '',
  description: '',
  fee: 0,
  currency: 'USD',
  currency_id: 1,
  status: 'active',
}

const editedItem = ref<Omit<ServiceItem, 'id'>>({ ...defaultItem })
const services = ref<ServiceItem[]>([])
const currencyOptions = ref<CurrencyOption[]>([])
const variantDialog = ref(false)
const variantService = ref<ServiceItem | null>(null)
const variantForm = ref({ id: null as number | null, name: '', code: '', fee: null as number | null })

// --- Computed ---
const formTitle = computed(() => (editedItemId.value === null
  ? translate('pages.contracts.services.services.addNewService')
  : translate('pages.contracts.services.services.editService')))
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
      type: service.name || service.type,
      code: service.code,
      description: service.description,
      fee: Number(service.fee),
      status: service.status as 'active' | 'inactive',
      currency: service.currency.code,
      currency_id: service.currency.id,
      contractTemplate: service.contract_template ?? null,
      variants: service.variants ?? [],
    }))
  }
  catch (error) {
    console.error('Error fetching services:', error)
    toast.error(`${translate('pages.contracts.services.services.failedToLoadServices')} ${apiErrorMessage(error)}`)
  }
  finally {
    isLoading.value = false
  }
}

const fetchCurrencies = async () => {
  try {
    isLoading.value = true

    const response = await CurrencyApi.getAll()

    // Handle the response structure based on currency.ts API
    const currencies = response.data

    currencyOptions.value = currencies.map((currency: { id: number; code: string }) => ({
      title: currency.code,
      value: currency.id,
    }))
  }
  catch (error) {
    console.error('Error fetching currencies:', error)
    toast.error(`${translate('pages.contracts.services.services.failedToLoadCurrencies')} ${apiErrorMessage(error)}`)
  }
  finally {
    isLoading.value = false
  }
}

const createService = async () => {
  try {
    isLoading.value = true

    const payload: CreateServiceData = {
      type: editedItem.value.type,
      code: editedItem.value.code,
      description: editedItem.value.description,
      fee: editedItem.value.fee,
      status: editedItem.value.status,
      currency_id: editedItem.value.currency_id,
    }

    const response = await ServiceApi.create(payload)

    toast.success(translate('pages.contracts.services.services.serviceCreatedSuccessfully'))
    await fetchServices()

    return response
  }
  catch (error) {
    console.error('Error creating service:', error)

    const errorMessage = apiErrorMessage(error)

    toast.error(translate('pages.contracts.services.services.failedToCreateService') + errorMessage)
    throw error
  }
  finally {
    isLoading.value = false
  }
}

const updateService = async () => {
  if (editedItemId.value === null)
    return

  try {
    isLoading.value = true

    const payload: UpdateServiceData = {
      type: editedItem.value.type,
      code: editedItem.value.code,
      description: editedItem.value.description,
      fee: editedItem.value.fee,
      status: editedItem.value.status,
      currency_id: editedItem.value.currency_id,
    }

    const response = await ServiceApi.update(editedItemId.value, payload)

    // Find and update the service in the list with the server response
    const index = services.value.findIndex(s => s.id === editedItemId.value)
    if (index !== -1) {
      services.value[index] = {
        id: response.data.id,
        type: response.data.type,
        code: response.data.code,
        description: response.data.description,
        fee: Number(response.data.fee),
        status: response.data.status as 'active' | 'inactive',
        currency: response.data.currency.code,
        currency_id: response.data.currency.id,
      }
    }
    toast.success(translate('pages.contracts.services.services.serviceUpdatedSuccessfully'))

    return response
  }
  catch (error) {
    console.error('Error updating service:', error)

    const errorMessage = apiErrorMessage(error)

    toast.error(translate('pages.contracts.services.services.failedToUpdateService') + errorMessage)
    throw error
  }
  finally {
    isLoading.value = false
  }
}

const deleteService = async () => {
  if (selectedItemForDelete.value === null)
    return

  try {
    isLoading.value = true
    await ServiceApi.delete(selectedItemForDelete.value)
    toast.success(translate('pages.contracts.services.services.serviceDeletedSuccessfully'))
    await fetchServices()
  }
  catch (error) {
    console.error('Error deleting service:', error)

    const errorMessage = apiErrorMessage(error)

    toast.error(translate('pages.contracts.services.services.failedToDeleteService') + errorMessage)
  }
  finally {
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
    code: item.code,
    description: item.description,
    fee: item.fee,
    currency: item.currency,
    currency_id: item.currency_id,
    status: item.status,
  }
  isDialogVisible.value = true
}

const saveService = async (formData?: Omit<ServiceItem, 'id' | 'currency'>) => {
  if (formData)
    editedItem.value = { ...editedItem.value, ...formData }

  try {
    if (editedItemId.value === null)
      await createService()
    else
      await updateService()

    isDialogVisible.value = false
  }
  catch (error) {
    // Error already handled in the individual methods
  }
}

const confirmDelete = (id: number) => {
  selectedItemForDelete.value = id
  isDeleteDialogVisible.value = true
}

/**
 * Open the contract-documents templates tab pre-scoped to this service. The
 * templates tab owns creation/versioning; the catalogue only links to it.
 */
const manageContractTemplate = (item: ServiceItem) => {
  router.push({ name: 'contract-documents', query: { tab: 'templates', serviceId: String(item.id) } })
}
const flattenedVariants = computed(() => variantService.value?.variants ?? [])
const openVariants = (item: ServiceItem) => { variantService.value = item; variantForm.value = { id: null, name: '', code: '', fee: null }; variantDialog.value = true }
const editVariant = (variant: ServiceVariant) => { variantForm.value = { id: variant.id, name: variant.name, code: variant.code, fee: variant.fee ?? null } }
const saveVariant = async () => {
  if (!variantService.value || !variantForm.value.name.trim() || !variantForm.value.code.trim()) return
  const { id, ...payload } = variantForm.value
  if (id) await ServiceApi.updateVariant(variantService.value.id, id, payload)
  else await ServiceApi.createVariant(variantService.value.id, payload)
  await fetchServices(); variantService.value = services.value.find(item => item.id === variantService.value?.id) ?? null
  variantForm.value = { id: null, name: '', code: '', fee: null }
}
const deleteVariant = async (variant: ServiceVariant) => { if (variantService.value && confirm(`Delete ${variant.name}?`)) { await ServiceApi.deleteVariant(variantService.value.id, variant.id); await fetchServices(); variantService.value = services.value.find(item => item.id === variantService.value?.id) ?? null } }

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
  <DataTablePageKpis
    :items="services"
    title="Services"
    description="Manage your service catalogue and quickly review its current availability."
    icon="tabler-tools"
  />
  <VCard>
    <!-- Card Header & Actions -->
    <VCardText class="d-flex flex-wrap gap-4 align-center justify-space-between">
      <div class="d-flex align-center gap-4 flex-wrap">
        <VTextField
          v-model="searchQuery"
          :placeholder="$t('pages.contracts.services.services.searchService')"
          density="compact"
          hide-details
          class="data-table-search"
          prepend-inner-icon="tabler-search"
          style="max-inline-size: 280px; min-inline-size: 220px;"
        />
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="openCreateModal"
      >
        {{ $t('pages.contracts.services.services.addService') }}
      </VBtn>
    </VCardText>

    <VDivider />

    <!-- Data Table -->
    <ResponsiveDataTable
      :headers="headers"
      :items="services"
      :search="searchQuery"
      :loading="isLoading"
      item-value="id"
      class="text-no-wrap"
    >
      <!-- FEE Slot -->
      <template #item.fee="{ item }">
        <span class="font-weight-medium">{{ formatLocalizedNumber(item.fee) }}</span>
      </template>

      <!-- STATUS Slot -->
      <template #item.status="{ item }">
        <VChip
          :color="resolveStatusColor(item.status)"
          size="small"
          label
          class="text-capitalize"
        >
          {{ translateBackendValue(item.status) }}
        </VChip>
      </template>

      <!-- CONTRACT TEMPLATE Slot -->
      <template #item.contract_template="{ item }">
        <VChip
          v-if="item.contractTemplate"
          color="success"
          size="small"
          variant="tonal"
        >
          {{ item.contractTemplate.name }}
        </VChip>
        <VChip
          v-else
          color="warning"
          size="small"
          variant="tonal"
        >
          {{ $t('pages.contracts.services.services.templateMissing') }}
        </VChip>
      </template>

      <!-- ACTIONS Slot -->
      <template #item.actions="{ item }">
        <div class="d-flex justify-end gap-2">
          <VBtn
            icon
            variant="text"
            size="small"
            color="primary"
            :title="$t('pages.contracts.services.services.manageContractTemplate')"
            @click="manageContractTemplate(item)"
          >
            <VIcon :icon="item.contractTemplate ? 'tabler-file-text' : 'tabler-file-plus'" />
          </VBtn>
          <VBtn icon variant="text" size="small" color="secondary" :title="$t('pages.contracts.services.services.serviceVariants')" @click="openVariants(item)"><VIcon icon="tabler-category-2" /></VBtn>
          <FullDetailsButton
            :item="item"
            :title="item.type"
          />
          <VBtn
            icon
            variant="text"
            size="small"
            color="default"
            @click="openEditModal(item)"
          >
            <VIcon icon="tabler-edit" />
          </VBtn>

          <VBtn
            icon
            variant="text"
            size="small"
            color="error"
            @click="confirmDelete(item.id)"
          >
            <VIcon icon="tabler-trash" />
          </VBtn>
        </div>
      </template>

      <!-- Loading -->
      <template #loading>
        <div class="d-flex justify-center align-center py-4">
          <VProgressCircular
            indeterminate
            color="primary"
          />
        </div>
      </template>
    </ResponsiveDataTable>
  </VCard>

  <ServiceForm
    v-model="isDialogVisible"
    :initial-item="editedItem"
    :currency-options="currencyOptions"
    :loading="isLoading"
    :title="formTitle"
    @submit="saveService"
  />
  <VDialog v-model="variantDialog" max-width="760"><VCard :title="`${variantService?.type ?? ''} — ${$t('pages.contracts.services.services.serviceVariants')}`"><VCardText>
    <VAlert density="compact" variant="tonal" color="info" class="mb-4">{{ $t('pages.contracts.services.services.variantFeeHint') }}</VAlert>
    <ResponsiveDataTable :headers="[{ title: $t('common.fields.name'), key: 'name' }, { title: $t('common.fields.code'), key: 'code' }, { title: $t('pages.contracts.services.services.fee'), key: 'fee' }, { title: $t('pages.contracts.services.services.actions'), key: 'actions', sortable: false }]" :items="flattenedVariants" item-value="id"><template #item.actions="{ item }"><IconBtn @click="editVariant(item)"><VIcon icon="tabler-pencil" /></IconBtn><IconBtn @click="deleteVariant(item)"><VIcon icon="tabler-trash" /></IconBtn></template></ResponsiveDataTable>
    <VRow class="mt-3"><VCol cols="12" md="4"><VTextField v-model="variantForm.name" :label="$t('pages.contracts.services.services.variantName')" /></VCol><VCol cols="12" md="4"><VTextField v-model="variantForm.code" :label="$t('common.fields.code')" /></VCol><VCol cols="12" md="4"><VTextField v-model.number="variantForm.fee" :label="$t('pages.contracts.services.services.fee')" type="number" clearable /></VCol></VRow>
  </VCardText><VCardActions><VSpacer /><VBtn @click="variantDialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" @click="saveVariant">{{ variantForm.id ? $t('common.actions.save') : $t('common.actions.add') }}</VBtn></VCardActions></VCard></VDialog>

  <!-- Delete Confirmation Dialog -->
  <VDialog
    v-model="isDeleteDialogVisible"
    max-width="400"
  >
    <VCard :title="$t('common.messages.confirmDeletion')">
      <VCardText>
        {{ $t('common.confirmations.areYouSureYouWantToDeleteThisServiceRecordThisActionCannotBeUndone') }}
      </VCardText>

      <VCardActions class="justify-end gap-2 pa-4">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isLoading"
          @click="isDeleteDialogVisible = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="error"
          :loading="isLoading"
          @click="deleteService"
        >
          {{ $t('common.actions.delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
