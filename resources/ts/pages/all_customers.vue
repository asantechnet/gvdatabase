<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { VCheckbox, VDataTable, VSelect, VTextField } from 'vuetify/components'
import { useRouter } from 'vue-router'
import moment from 'moment'
import CustomerApi, { type Customer, type CustomerService, type ApiResponse } from '@/api/customer'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import type { Service } from '@/api/services'
import CurrencyApi, { type Currency } from '@/api/currency'
import MarketerApi, { type Marketer } from '@/api/marketer'

// Types
interface ServiceForm {
  id: number
  service_id: number
  fee: number | null
  currency_id: number | null
  deadline: string | null
  status: 'active' | 'paused' | 'completed'
  notes: string | null
}

// State
const toast = useToast()
const router = useRouter()
const customers = ref<Customer[]>([])
const allServices = ref<Service[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const servicesLoading = ref(false)
const currencies = ref<Currency[]>([])
const marketers = ref<Marketer[]>([])

// Form state
const dialog = ref(false)
const isEdit = ref(false)
const currentCustomer = ref<Customer>({
  id: 0,
  name: '',
  company: '',
  contact: '',
  email: null,
  address: null,
  tax_number: null,
  marketer: '',
  date: new Date().toISOString().split('T')[0],
  services: [],
  created_at: '',
  updated_at: ''
})
const currentService = ref<ServiceForm>({
  id: 0,
  service_id: 0,
  fee: null,
  currency_id: null,
  deadline: null,
  status: 'active',
  notes: null,
})
const serviceDialog = ref(false)
const attachServiceDialog = ref(false)
const deleteDialog = ref(false)
const customerToDelete = ref<number | null>(null)
const serviceToEdit = ref<number | null>(null)
const serviceLoading = ref(false)
const selectedServices = ref<{
  service_id: number
}[]>([])

// Search and filters
const search = ref('')
const filterCurrency = ref<string | null>(null)

// Table headers
const headers = [
  { title: translate('common.fields.name'), key: 'name', sortable: true },
  { title: translate('common.fields.company'), key: 'company', sortable: true },
  { title: translate('common.messages.contact'), key: 'contact', sortable: true },
  { title: translate('common.messages.marketer'), key: 'marketer', sortable: true },
  { title: translate('pages.contracts.allCustomers.allCustomers.registered'), key: 'date', sortable: true },
  { title: translate('common.messages.services'), key: 'services', sortable: false },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false },
]

const serviceHeaders = [
  { title: translate('common.fields.type'), key: 'type' },
  { title: translate('common.messages.fee'), key: 'fee' },
  { title: translate('common.messages.deadline'), key: 'deadline' },
  { title: translate('common.fields.currency'), key: 'currency.code' },
  { title: translate('common.messages.actions'), key: 'serviceActions', sortable: false },
]

const availableServiceHeaders = [
  { title: translate('pages.contracts.allCustomers.allCustomers.select'), key: 'select', sortable: false },
  { title: translate('common.fields.type'), key: 'type' },
  { title: translate('common.messages.deadline'), key: 'duration' },
  { title: translate('common.messages.fee'), key: 'fee', sortable: false },
  { title: translate('common.fields.currency'), key: 'currency.code', sortable: false },
  { title: translate('common.messages.actions'), key: 'details', sortable: false, align: 'end' as const },
]

const deadlineMeta = (deadline: string | null) => {
  if (!deadline) return { color: 'secondary', title: translate('pages.contracts.allCustomers.allCustomers.noDeadline') }
  const today = moment().startOf('day')
  const dueDate = moment(deadline).startOf('day')
  const monthDifference = Math.ceil(Math.abs(dueDate.diff(today, 'months', true)))
  const unit = monthDifference === 1 ? 'month' : 'months'

  if (dueDate.isBefore(today))
    return { color: 'error', title: `${monthDifference} ${unit} overdue` }

  if (dueDate.isSame(today, 'day'))
    return { color: 'warning', title: translate('pages.contracts.allCustomers.allCustomers.dueToday0Months') }

  if (dueDate.diff(today, 'months') === 0)
    return { color: 'warning', title: translate('pages.contracts.allCustomers.allCustomers.dueWithin1Month') }

  return { color: 'success', title: `${monthDifference} ${unit} remaining` }
}

// Computed filtered customers
const filtered = computed(() => {
  return customers.value.filter(c => {
    const matchesSearch = c.name.toLowerCase().includes(search.value.toLowerCase()) ||
      c.company.toLowerCase().includes(search.value.toLowerCase()) ||
      c.contact.toLowerCase().includes(search.value.toLowerCase()) ||
      (c.marketer || '').toLowerCase().includes(search.value.toLowerCase())

    const matchesCurrency = !filterCurrency.value ||
      c.services.some(s => s.currency.code === filterCurrency.value)

    return matchesSearch && matchesCurrency
  })
})

// Computed available services (excluding already attached ones)
const availableServices = computed(() => {
  if (!currentCustomer.value.id) return allServices.value

  const attachedServiceIds = currentCustomer.value.services.map(s => s.service_id)
  return allServices.value.filter(s => !attachedServiceIds.includes(s.id))
})

// API methods
const fetchCustomers = async () => {
  try {
    loading.value = true
    const response: ApiResponse<Customer[]> = await CustomerApi.getAll({
      search: search.value,
      company: undefined,
      marketer: undefined
    })
    if (response.success) {
      customers.value = response.data
    } else {
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToFetchCustomers') + (response.message || 'Unknown error')
    }
  } catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToFetchCustomers2')
    console.error(err)
  } finally {
    loading.value = false
  }
}

const fetchAllServices = async () => {
  try {
    servicesLoading.value = true
    const response = await CustomerApi.getAllServices()
    if (response.success) {
      allServices.value = response.data
    } else {
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToFetchServices') + (response.message || 'Unknown error')
    }
  } catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToFetchServices2')
    console.error(err)
  } finally {
    servicesLoading.value = false
  }
}

const fetchCurrencies = async () => {
  try {
    const response = await CurrencyApi.getAll()
    currencies.value = response.data
  } catch (err) {
    error.value = translate('common.messages.failedToFetchCurrencies')
    console.error(err)
  }
}

const createCustomer = async () => {
  try {
    const response: ApiResponse<Customer> = await CustomerApi.create({
      name: currentCustomer.value.name,
      company: currentCustomer.value.company,
      contact: currentCustomer.value.contact,
      email: currentCustomer.value.email,
      address: currentCustomer.value.address,
      tax_number: currentCustomer.value.tax_number,
      marketer: currentCustomer.value.marketer,
      date: currentCustomer.value.date,
    })
    if (response.success) {
      customers.value.push(response.data)
      dialog.value = false
    } else {
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToCreateCustomer') + (response.message || 'Unknown error')
    }
  } catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToCreateCustomer2')
    console.error(err)
  }
}

const updateCustomer = async () => {
  if (!currentCustomer.value.id) return

  try {
    const response: ApiResponse<Customer> = await CustomerApi.update(currentCustomer.value.id, {
      name: currentCustomer.value.name,
      company: currentCustomer.value.company,
      contact: currentCustomer.value.contact,
      email: currentCustomer.value.email,
      address: currentCustomer.value.address,
      tax_number: currentCustomer.value.tax_number,
      marketer: currentCustomer.value.marketer,
      date: currentCustomer.value.date,
    })
    if (response.success) {
      const index = customers.value.findIndex(c => c.id === currentCustomer.value.id)
      if (index !== -1) {
        customers.value[index] = response.data
      }
      dialog.value = false
    } else {
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToUpdateCustomer') + (response.message || 'Unknown error')
    }
  } catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToUpdateCustomer2')
    console.error(err)
  }
}

const updateService = async (customerId: number, pivotId: number) => {
  try {
    serviceLoading.value = true

    const response = await CustomerApi.updateService(customerId, pivotId, {
      service_id: currentService.value.service_id,
      fee: currentService.value.fee,
      currency_id: currentService.value.currency_id,
      deadline: currentService.value.deadline,
      status: currentService.value.status,
      notes: currentService.value.notes,
    })

    if (!response.success) {
      toast.error(response.message || 'Failed to update customer service')
      return
    }

    // Refresh the customer data
    const customerResponse = await CustomerApi.getById(customerId)
    if (customerResponse.success) {
      const index = customers.value.findIndex(c => c.id === customerId)
      if (index !== -1) {
        customers.value[index] = customerResponse.data
      }

      // Also update the current customer in the dialog if it's the same customer
      if (currentCustomer.value.id === customerId) {
        currentCustomer.value = customerResponse.data
      }
    }

    serviceDialog.value = false
    toast.success(translate('pages.contracts.allCustomers.allCustomers.customerServiceUpdated'))
  } catch (err) {
    toast.error(translate('pages.contracts.allCustomers.allCustomers.failedToUpdateCustomerService'))
    console.error(err)
  } finally {
    serviceLoading.value = false
  }
}

const attachSelectedServices = async () => {
  if (!currentCustomer.value.id || selectedServices.value.length === 0) return

  try {
    serviceLoading.value = true
    const response = await CustomerApi.attachServices(currentCustomer.value.id, selectedServices.value)

    if (!response.success) {
      toast.error(response.message || 'Failed to attach services')
      return
    }

    // Refresh the customer data
    const customerResponse = await CustomerApi.getById(currentCustomer.value.id)
    if (customerResponse.success) {
      const index = customers.value.findIndex(c => c.id === currentCustomer.value.id)
      if (index !== -1) {
        customers.value[index] = customerResponse.data
      }

      // Update the current customer in the dialog
      currentCustomer.value = customerResponse.data

      // Reset selected services and close dialog
      selectedServices.value = []
      attachServiceDialog.value = false
      toast.success(translate('pages.contracts.allCustomers.allCustomers.servicesAttached'))
    }
  } catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToAttachServices')
    console.error(err)
  } finally {
    serviceLoading.value = false
  }
}

const deleteCustomer = async () => {
  if (!customerToDelete.value) return

  try {
    const response = await CustomerApi.delete(customerToDelete.value)
    if (response.success) {
      customers.value = customers.value.filter(c => c.id !== customerToDelete.value)
      deleteDialog.value = false
    } else {
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToDeleteCustomer') + (response.message || 'Unknown error')
    }
  } catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToDeleteCustomer2')
    console.error(err)
  }
}

const removeService = async (customerId: number, pivotId: number) => {
  try {
    const response = await CustomerApi.detachService(customerId, pivotId)
    if (!response.success) {
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToRemoveService') + (response.message || 'Unknown error')
      return
    }

    // Refresh the customer data
    const customerResponse = await CustomerApi.getById(customerId)
    if (customerResponse.success) {
      const index = customers.value.findIndex(c => c.id === customerId)
      if (index !== -1) {
        customers.value[index] = customerResponse.data
      }

      // Also update the current customer in the dialog if it's the same customer
      if (currentCustomer.value.id === customerId) {
        currentCustomer.value = customerResponse.data
      }
    }
  } catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToRemoveService2')
    console.error(err)
  }
}

const confirmDeleteCustomer = (customerId: number) => {
  customerToDelete.value = customerId
  deleteDialog.value = true
}

const printTable = () => {
  void printDocument({ bodyClass: 'printing-table-customers', pageStyle: 'size: A4 landscape; margin: 12mm;' })
}

// Form methods
const openAddCustomer = () => {
  currentCustomer.value = {
    id: 0,
    name: '',
    company: '',
    contact: '',
    email: null,
    address: null,
    tax_number: null,
    marketer: '',
    date: new Date().toISOString().split('T')[0],
    services: [],
    created_at: '',
    updated_at: ''
  }
  isEdit.value = false
  dialog.value = true
}

const openEditCustomer = (customer: Customer) => {
  currentCustomer.value = { ...customer }
  isEdit.value = true
  dialog.value = true
}

const saveCustomer = () => {
  if (isEdit.value) {
    updateCustomer()
  } else {
    createCustomer()
  }
}

const openEditService = (customerId: number, pivotId: number) => {
  const customer = customers.value.find(c => c.id === customerId)
  if (customer) {
    const service = customer.services.find(s => s.id === pivotId)
    if (service) {
      currentCustomer.value = { ...customer }
      currentService.value = {
        id: service.id,
        service_id: service.service_id,
        fee: service.fee,
        currency_id: service.currency_id ?? Number(service.currency.id),
        deadline: service.deadline,
        status: service.status as ServiceForm['status'],
        notes: service.notes ?? null,
      }
      serviceToEdit.value = pivotId
      serviceDialog.value = true
    }
  }
}

const openAttachServices = async (customerId: number) => {
  const customer = customers.value.find(c => c.id === customerId)
  if (customer) {
    currentCustomer.value = { ...customer }
    selectedServices.value = []
    attachServiceDialog.value = true
    await fetchAllServices()
  }
}

const saveService = async () => {
  if (currentCustomer.value.id && serviceToEdit.value)
    await updateService(currentCustomer.value.id, serviceToEdit.value)
}

const fetchMarketers = async () => {
  try {
    marketers.value = await MarketerApi.getAll()
  } catch (err) {
    console.error('Failed to fetch marketers:', err)
  }
}

const issueInvoice = (customerId: number) => {
  router.push({ name: 'innvoice', query: { customer: customerId } })
}

// Lifecycle hooks
onMounted(() => {
  fetchCustomers()
  fetchAllServices()
  fetchCurrencies()
  fetchMarketers()
})
</script>

<template>
  <DataTablePageKpis :items="filtered" title="Customer directory" description="Track customer relationships and their active services at a glance." icon="tabler-users-group" />
  <VCard>
    <VCardText>
      <VRow align="center">
        <VCol cols="12" md="7">
          <VTextField
            v-model="search"
            class="data-table-search" prepend-inner-icon="tabler-search"
            :placeholder="$t('pages.contracts.allCustomers.allCustomers.searchCustomers')"
            density="compact"
            clearable
            hide-details
            @input="fetchCustomers"
          />
        </VCol>
        <VCol cols="12" md="5" class="text-end d-flex gap-2 justify-end">
          <DataTableFilterMenu :active="Boolean(filterCurrency)" @reset="filterCurrency = null">
            <VSelect v-model="filterCurrency" :items="currencies" item-title="code" item-value="code"
              :label="$t('pages.contracts.allCustomers.allCustomers.filterByCurrency')" density="compact" clearable hide-details />
          </DataTableFilterMenu>
          <VBtn
            icon="tabler-printer"
            variant="text"
            @click="printTable"
          ><VTooltip activator="parent" location="top">{{ $t('pages.contracts.allCustomers.allCustomers.printCustomerList') }}</VTooltip></VBtn>
          <VBtn
            color="primary"
            @click="openAddCustomer"
          >
            <VIcon icon="tabler-plus" class="me-1" />
            {{ $t('pages.contracts.allCustomers.allCustomers.addNewCustomer') }}
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <ResponsiveDataTable
      :headers="headers"
      :items="filtered"
      item-value="id"
      class="text-no-wrap"
      mobile-breakpoint="sm"
      :loading="loading"
      style="overflow-x: auto;"
    >
      <template #item.contact="{ item }">
        <div class="d-flex flex-column align-start gap-1">
          <ContactActionMenu kind="phone" :value="item.contact" />
          <ContactActionMenu v-if="item.email" kind="email" :value="item.email" />
        </div>
      </template>

      <template #item.date="{ item }">
        {{ formatLocalizedDate(`${item.date}T00:00:00`) }}
      </template>

      <template #item.services="{ item }">
        <div v-if="item.services.length === 0" class="text-disabled">
          {{ $t('pages.contracts.allCustomers.allCustomers.noServices') }}
        </div>
        <ResponsiveDataTable
          v-else
          :headers="serviceHeaders"
          :items="item.services"
          density="compact"
          hide-default-footer
        >
          <template #item.deadline="{ item }">
            <VChip v-if="item.deadline"
              :color="deadlineMeta(item.deadline).color"
              size="small"
              label
              variant="tonal"
            >
              {{ formatLocalizedDate(`${item.deadline}T00:00:00`) }}
              <VTooltip activator="parent" location="top">
                {{ deadlineMeta(item.deadline).title }}
              </VTooltip>
            </VChip>
            <span v-else class="text-medium-emphasis">{{ $t('common.messages.noDeadline') }}</span>
          </template>
          <template #item.currency.code="{ item }">
            {{ item.currency.code }}
          </template>
          <template #item.serviceActions="{ item: service }">
            <FullDetailsButton :item="service" :title="service.type" />
            <VBtn
              icon="tabler-edit"
              variant="text"
              size="x-small"
              color="primary"
              @click="openEditService(item.id, service.id)"
            >
              <VIcon icon="tabler-edit" />
              <VTooltip activator="parent" location="top">{{ $t('common.messages.editService2') }}</VTooltip>
            </VBtn>
            <VBtn
              icon="tabler-trash"
              variant="text"
              size="x-small"
              color="error"
              @click="removeService(item.id, service.id)"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent" location="top">{{ $t('common.messages.removeService2') }}</VTooltip>
            </VBtn>
          </template>
          <template #bottom></template>
        </ResponsiveDataTable>
      </template>

      <template #item.actions="{ item }">
        <FullDetailsButton :item="item" :title="item.name" />
        <VBtn
          icon="tabler-edit"
          variant="text"
          size="small"
          color="primary"
          @click="openEditCustomer(item)"
        >
          <VIcon icon="tabler-edit" />
          <VTooltip activator="parent" location="top">{{ $t('pages.contracts.allCustomers.allCustomers.editCustomer2') }}</VTooltip>
        </VBtn>
        <VBtn
          icon="tabler-plus"
          variant="text"
          size="small"
          color="success"
          @click="openAttachServices(item.id)"
        >
          <VIcon icon="tabler-plus" />
          <VTooltip activator="parent" location="top">{{ $t('common.messages.attachServices') }}</VTooltip>
        </VBtn>
        <VBtn
          icon
          variant="text"
          size="small"
          color="info"
          :disabled="item.services.length === 0"
          @click="issueInvoice(item.id)"
        >
          <VIcon icon="tabler-file-invoice" />
          <VTooltip activator="parent" location="top">
            {{ item.services.length ? 'Issue Invoice' : 'Assign services before invoicing' }}
          </VTooltip>
        </VBtn>
        <VBtn
          icon="tabler-trash"
          variant="text"
          size="small"
          color="error"
          @click="confirmDeleteCustomer(item.id)"
        >
          <VIcon icon="tabler-trash" />
          <VTooltip activator="parent" location="top">{{ $t('pages.contracts.allCustomers.allCustomers.deleteCustomer2') }}</VTooltip>
        </VBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>
  <PrintableListReport title="Customers Report" subtitle="Filtered customer records" :columns="['Name', 'Company', 'Contact', 'Marketer', 'Registered', 'Services']" :rows="filtered.map(customer => [customer.name, customer.company, customer.contact, customer.marketer, customer.date, customer.services.length ? customer.services.map(service => `${service.type}: ${service.fee} ${service.currency?.code ?? ''}${service.deadline ? ` · ${service.deadline}` : ''}`).join(' | ') : '—'])" />

  <!-- Customer Form Dialog -->
  <VDialog v-model="dialog" max-width="800px">
    <VCard>
      <VCardTitle>
        <span class="text-h5">{{ isEdit ? 'Edit Customer' : 'Add New Customer' }}</span>
      </VCardTitle>

      <VCardText>
        <VForm class="app-form">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentCustomer.name"
                :label="$t('pages.contracts.allCustomers.allCustomers.fullName')"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentCustomer.company"
                :label="$t('common.fields.company')"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentCustomer.contact"
                :label="$t('common.fields.phone')"
                required
              />
            </VCol>
            <VCol cols="12" md="6"><VTextField v-model="currentCustomer.email" label="Email" type="email" /></VCol>
            <VCol cols="12"><VTextarea v-model="currentCustomer.address" label="Address" rows="2" /></VCol>
            <VCol cols="12" md="6"><VTextField v-model="currentCustomer.tax_number" label="Tax number" /></VCol>
            <VCol cols="12" md="6">
              <VAutocomplete
                v-model="currentCustomer.marketer"
                :items="marketers"
                item-title="name"
                item-value="name"
                :label="$t('common.messages.marketer')"
                :placeholder="$t('common.messages.selectAMarketer')"
                :no-data-text="$t('pages.contracts.allCustomers.allCustomers.addMarketersFromSetupMarketers')"
                clearable
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentCustomer.date"
                :label="$t('common.fields.date')"
                type="date"
                required
              />
            </VCol>
          </VRow>

          <VRow v-if="isEdit">
            <VCol cols="12">
              <VCard>
                <VCardTitle class="d-flex justify-space-between align-center">
                  <span>{{ $t('pages.contracts.allCustomers.allCustomers.services') }}</span>
                  <div v-if="currentCustomer.id">
                    <VBtn
                      color="primary"
                      size="small"
                      class="me-2"
                      @click="openAttachServices(currentCustomer.id)"
                    >
                      <VIcon icon="tabler-plus" class="me-1" />
                      {{ $t('common.messages.attachServices') }}
                    </VBtn>
                  </div>
                </VCardTitle>

                <ResponsiveDataTable
                  :headers="serviceHeaders"
                  :items="currentCustomer.services"
                  density="compact"
                  hide-default-footer
                >
                  <template #item.deadline="{ item }">
                    <VChip v-if="item.deadline"
                      :color="deadlineMeta(item.deadline).color"
                      size="small"
                      label
                      variant="tonal"
                    >
                      {{ formatLocalizedDate(`${item.deadline}T00:00:00`) }}
                      <VTooltip activator="parent" location="top">
                        {{ deadlineMeta(item.deadline).title }}
                      </VTooltip>
                    </VChip>
                    <span v-else class="text-medium-emphasis">{{ $t('common.messages.noDeadline') }}</span>
                  </template>
                  <template #item.currency.code="{ item }">
                    {{ item.currency.code }}
                  </template>
                  <template #item.serviceActions="{ item }">
                    <FullDetailsButton :item="item" :title="item.type" />
                    <VBtn
                      icon="tabler-edit"
                      variant="text"
                      size="x-small"
                      color="primary"
                      @click="openEditService(currentCustomer.id, item.id)"
                    >
                      <VIcon icon="tabler-edit" />
                      <VTooltip activator="parent" location="top">{{ $t('common.messages.editService2') }}</VTooltip>
                    </VBtn>
                    <VBtn
                      icon="tabler-trash"
                      variant="text"
                      size="x-small"
                      color="error"
                      @click="removeService(currentCustomer.id, item.id)"
                    >
                      <VIcon icon="tabler-trash" />
                      <VTooltip activator="parent" location="top">{{ $t('common.messages.removeService2') }}</VTooltip>
                    </VBtn>
                  </template>
                </ResponsiveDataTable>
              </VCard>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          @click="dialog = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          @click="saveCustomer"
        >
          {{ $t('common.actions.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Service Form Dialog -->
  <VDialog v-model="serviceDialog" max-width="600px">
    <VCard>
      <VCardTitle>
        <span class="text-h5">{{ $t('pages.contracts.allCustomers.allCustomers.editCustomerService') }}</span>
      </VCardTitle>

      <VCardText>
        <VForm class="app-form">
          <VRow>
            <VCol cols="12" md="6">
              <VSelect
                v-model="currentService.service_id"
                :items="allServices"
                item-title="type"
                item-value="id"
                :label="$t('common.messages.serviceType')"
                :loading="servicesLoading"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="currentService.currency_id"
                :items="currencies"
                item-title="code"
                item-value="id"
                :label="$t('common.fields.currency')"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentService.deadline"
                :label="$t('pages.contracts.allCustomers.allCustomers.deadline')"
                type="date"
                clearable
              />
            </VCol>
            <VCol cols="12" md="6"><VTextField v-model.number="currentService.fee" :label="$t('common.messages.fee')" type="number" min="0" /></VCol>
            <VCol cols="12" md="6"><VSelect v-model="currentService.status" :items="['active', 'paused', 'completed']" :label="$t('common.fields.status')" /></VCol>
            <VCol cols="12"><VTextarea v-model="currentService.notes" :label="$t('common.messages.notes')" rows="2" auto-grow /></VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          @click="serviceDialog = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          @click="saveService"
          :loading="serviceLoading"
        >
          {{ $t('common.actions.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Attach Services Dialog -->
  <VDialog v-model="attachServiceDialog" max-width="800px">
    <VCard>
      <VCardTitle>
        <span class="text-h5">{{ $t('pages.contracts.allCustomers.attachServicesTo', { customer: currentCustomer.name }) }}</span>
      </VCardTitle>

      <VCardText>
        <VRow>
          <VCol cols="12">
            <ResponsiveDataTable
              :headers="availableServiceHeaders"
              :items="availableServices"
              :loading="servicesLoading"
              item-value="id"
              density="compact"
            >
              <template #item.select="{ item }">
                <VCheckbox
                  :model-value="selectedServices.some(s => s.service_id === item.id)"
                  @update:model-value="(value) => {
                    if (value) {
                      selectedServices.push({
                        service_id: item.id
                      })
                    } else {
                      selectedServices = selectedServices.filter(s => s.service_id !== item.id)
                    }
                  }"
                />
              </template>

              <template #item.duration="{ item }">
                {{ formatLocalizedDate(moment().add(item.duration, 'months').toDate()) }}
              </template>

              <template #item.fee="{ item }">
                {{ item.currency.symbol }}{{ Number(item.fee).toFixed(2) }}
              </template>

              <template #item.currency.code="{ item }">
                {{ item.currency.code }}
              </template>
              <template #item.details="{ item }"><FullDetailsButton :item="item" :title="item.type" /></template>
            </ResponsiveDataTable>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          @click="attachServiceDialog = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          @click="attachSelectedServices"
          :loading="serviceLoading"
          :disabled="selectedServices.length === 0"
        >
          {{ $t('pages.contracts.allCustomers.allCustomers.attachSelectedServices') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Delete Confirmation Dialog -->
  <VDialog v-model="deleteDialog" max-width="500px">
    <VCard>
      <VCardTitle class="text-h5">
        {{ $t('common.confirmations.areYouSureYouWantToDeleteThisCustomer') }}
      </VCardTitle>
      <VCardText>
        {{ $t('common.confirmations.thisActionCannotBeUndoneAllCustomerDataAndAssociatedServicesWillBePerm') }}
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          @click="deleteDialog = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="error"
          @click="deleteCustomer"
        >
          {{ $t('pages.contracts.allCustomers.allCustomers.deletePermanently') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
