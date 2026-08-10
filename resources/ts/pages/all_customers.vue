<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { VCheckbox, VDataTable, VSelect, VTextField } from 'vuetify/components'
import { useRouter } from 'vue-router'
import moment from 'moment'
import CustomerApi, { type Customer, type CustomerService, type ApiResponse } from '@/api/customer'
import type { Service } from '@/api/services'
import CurrencyApi, { type Currency } from '@/api/currency'

// Types
interface ServiceForm {
  id: number
  service_id: number
  type: string
  deadline: string
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

// Form state
const dialog = ref(false)
const isEdit = ref(false)
const currentCustomer = ref<Customer>({
  id: 0,
  name: '',
  company: '',
  contact: '',
  marketer: '',
  date: new Date().toISOString().split('T')[0],
  services: [],
  created_at: '',
  updated_at: ''
})
const currentService = ref<ServiceForm>({
  id: 0,
  service_id: 0,
  type: '',
  deadline: '',
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
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Company', key: 'company', sortable: true },
  { title: 'Contact', key: 'contact', sortable: true },
  { title: 'Marketer', key: 'marketer', sortable: true },
  { title: 'Services', key: 'services', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false },
]

const serviceHeaders = [
  { title: 'Type', key: 'type' },
  { title: 'Fee', key: 'fee' },
  { title: 'Deadline', key: 'deadline' },
  { title: 'Currency', key: 'currency.code' },
  { title: 'Actions', key: 'serviceActions', sortable: false },
]

const availableServiceHeaders = [
  { title: 'Select', key: 'select', sortable: false },
  { title: 'Type', key: 'type' },
  { title: 'Deadline', key: 'duration' },
  { title: 'Fee', key: 'fee', sortable: false },
  { title: 'Currency', key: 'currency.code', sortable: false },
]

const deadlineMeta = (deadline: string) => {
  const today = moment().startOf('day')
  const dueDate = moment(deadline).startOf('day')
  const monthDifference = Math.ceil(Math.abs(dueDate.diff(today, 'months', true)))
  const unit = monthDifference === 1 ? 'month' : 'months'

  if (dueDate.isBefore(today))
    return { color: 'error', title: `${monthDifference} ${unit} overdue` }

  if (dueDate.isSame(today, 'day'))
    return { color: 'warning', title: 'Due today (0 months)' }

  if (dueDate.diff(today, 'months') === 0)
    return { color: 'warning', title: 'Due within 1 month' }

  return { color: 'success', title: `${monthDifference} ${unit} remaining` }
}

// Computed filtered customers
const filtered = computed(() => {
  return customers.value.filter(c => {
    const matchesSearch = c.name.toLowerCase().includes(search.value.toLowerCase()) ||
      c.company.toLowerCase().includes(search.value.toLowerCase()) ||
      c.contact.toLowerCase().includes(search.value.toLowerCase()) ||
      c.marketer.toLowerCase().includes(search.value.toLowerCase())

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
      error.value = 'Failed to fetch customers: ' + (response.message || 'Unknown error')
    }
  } catch (err) {
    error.value = 'Failed to fetch customers'
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
      error.value = 'Failed to fetch services: ' + (response.message || 'Unknown error')
    }
  } catch (err) {
    error.value = 'Failed to fetch services'
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
    error.value = 'Failed to fetch currencies'
    console.error(err)
  }
}

const createCustomer = async () => {
  try {
    const response: ApiResponse<Customer> = await CustomerApi.create({
      name: currentCustomer.value.name,
      company: currentCustomer.value.company,
      contact: currentCustomer.value.contact,
      marketer: currentCustomer.value.marketer,
      date: currentCustomer.value.date,
    })
    if (response.success) {
      customers.value.push(response.data)
      dialog.value = false
    } else {
      error.value = 'Failed to create customer: ' + (response.message || 'Unknown error')
    }
  } catch (err) {
    error.value = 'Failed to create customer'
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
      error.value = 'Failed to update customer: ' + (response.message || 'Unknown error')
    }
  } catch (err) {
    error.value = 'Failed to update customer'
    console.error(err)
  }
}

const updateService = async (customerId: number, pivotId: number) => {
  try {
    serviceLoading.value = true

    const response = await CustomerApi.updateService(customerId, pivotId, {
      deadline: currentService.value.deadline,
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
    toast.success('Customer service updated')
  } catch (err) {
    toast.error('Failed to update customer service')
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
      toast.success('Services attached')
    }
  } catch (err) {
    error.value = 'Failed to attach services'
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
      error.value = 'Failed to delete customer: ' + (response.message || 'Unknown error')
    }
  } catch (err) {
    error.value = 'Failed to delete customer'
    console.error(err)
  }
}

const removeService = async (customerId: number, pivotId: number) => {
  try {
    const response = await CustomerApi.detachService(customerId, pivotId)
    if (!response.success) {
      error.value = 'Failed to remove service: ' + (response.message || 'Unknown error')
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
    error.value = 'Failed to remove service'
    console.error(err)
  }
}

const confirmDeleteCustomer = (customerId: number) => {
  customerToDelete.value = customerId
  deleteDialog.value = true
}

const printTable = () => {
  window.print()
}

// Form methods
const openAddCustomer = () => {
  currentCustomer.value = {
    id: 0,
    name: '',
    company: '',
    contact: '',
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
        type: service.type,
        deadline: service.deadline,
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

const issueInvoice = (customerId: number) => {
  router.push({ name: 'innvoice', query: { customer: customerId } })
}

// Lifecycle hooks
onMounted(() => {
  fetchCustomers()
  fetchCurrencies()
})
</script>

<template>
  <VCard>
    <VCardText>
      <VRow align="center">
        <VCol cols="12" md="4">
          <VTextField
            v-model="search"
            prepend-inner-icon="tabler-search"
            placeholder="Search customers..."
            density="compact"
            clearable
            hide-details
            @input="fetchCustomers"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            v-model="filterCurrency"
            :items="currencies"
            item-title="code"
            item-value="code"
            label="Filter by Currency"
            density="compact"
            clearable
            hide-details
          />
        </VCol>
        <VCol cols="12" md="5" class="text-end d-flex gap-2 justify-end">
          <VBtn
            icon="tabler-printer"
            variant="text"
            @click="printTable"
            title="Print customer list"
          />
          <VBtn
            color="primary"
            @click="openAddCustomer"
          >
            <VIcon icon="tabler-plus" class="me-1" />
            Add New Customer
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VDataTable
      :headers="headers"
      :items="filtered"
      item-value="id"
      class="text-no-wrap"
      mobile-breakpoint="sm"
      :loading="loading"
      style="overflow-x: auto;"
    >
      <template #item.services="{ item }">
        <div v-if="item.services.length === 0" class="text-disabled">
          No services
        </div>
        <VDataTable
          v-else
          :headers="serviceHeaders"
          :items="item.services"
          density="compact"
          hide-default-footer
        >
          <template #item.deadline="{ item }">
            <VChip
              :color="deadlineMeta(item.deadline).color"
              size="small"
              label
              variant="tonal"
            >
              {{ moment(item.deadline).format('YYYY-MM-DD') }}
              <VTooltip activator="parent" location="top">
                {{ deadlineMeta(item.deadline).title }}
              </VTooltip>
            </VChip>
          </template>
          <template #item.currency.code="{ item }">
            {{ item.currency.code }}
          </template>
          <template #item.serviceActions="{ item: service }">
            <VBtn
              icon="tabler-edit"
              variant="text"
              size="x-small"
              color="primary"
              @click="openEditService(item.id, service.id)"
              title="Edit service"
            >
              <VIcon icon="tabler-edit" />
              <VTooltip activator="parent" location="top">Edit Service</VTooltip>
            </VBtn>
            <VBtn
              icon="tabler-trash"
              variant="text"
              size="x-small"
              color="error"
              @click="removeService(item.id, service.id)"
              title="Remove service"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent" location="top">Remove Service</VTooltip>
            </VBtn>
          </template>
          <template #bottom></template>
        </VDataTable>
      </template>

      <template #item.actions="{ item }">
        <VBtn
          icon="tabler-edit"
          variant="text"
          size="small"
          color="primary"
          @click="openEditCustomer(item)"
          title="Edit customer"
        >
          <VIcon icon="tabler-edit" />
          <VTooltip activator="parent" location="top">Edit Customer</VTooltip>
        </VBtn>
        <VBtn
          icon="tabler-plus"
          variant="text"
          size="small"
          color="success"
          @click="openAttachServices(item.id)"
          title="Attach services"
        >
          <VIcon icon="tabler-plus" />
          <VTooltip activator="parent" location="top">Attach Services</VTooltip>
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
          title="Delete customer"
        >
          <VIcon icon="tabler-trash" />
          <VTooltip activator="parent" location="top">Delete Customer</VTooltip>
        </VBtn>
      </template>
    </VDataTable>
  </VCard>

  <!-- Customer Form Dialog -->
  <VDialog v-model="dialog" max-width="800px">
    <VCard>
      <VCardTitle>
        <span class="text-h5">{{ isEdit ? 'Edit Customer' : 'Add New Customer' }}</span>
      </VCardTitle>

      <VCardText>
        <VForm>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentCustomer.name"
                label="Full Name"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentCustomer.company"
                label="Company"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentCustomer.contact"
                label="Contact (Email/Phone)"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentCustomer.marketer"
                label="Marketer"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentCustomer.date"
                label="Date"
                type="date"
                required
              />
            </VCol>
          </VRow>

          <VRow v-if="isEdit">
            <VCol cols="12">
              <VCard>
                <VCardTitle class="d-flex justify-space-between align-center">
                  <span>Services</span>
                  <div v-if="currentCustomer.id">
                    <VBtn
                      color="primary"
                      size="small"
                      class="me-2"
                      @click="openAttachServices(currentCustomer.id)"
                    >
                      <VIcon icon="tabler-plus" class="me-1" />
                      Attach Services
                    </VBtn>
                  </div>
                </VCardTitle>

                <VDataTable
                  :headers="serviceHeaders"
                  :items="currentCustomer.services"
                  density="compact"
                  hide-default-footer
                >
                  <template #item.deadline="{ item }">
                    <VChip
                      :color="deadlineMeta(item.deadline).color"
                      size="small"
                      label
                      variant="tonal"
                    >
                      {{ moment(item.deadline).format('YYYY-MM-DD') }}
                      <VTooltip activator="parent" location="top">
                        {{ deadlineMeta(item.deadline).title }}
                      </VTooltip>
                    </VChip>
                  </template>
                  <template #item.currency.code="{ item }">
                    {{ item.currency.code }}
                  </template>
                  <template #item.serviceActions="{ item }">
                    <VBtn
                      icon="tabler-edit"
                      variant="text"
                      size="x-small"
                      color="primary"
                      @click="openEditService(currentCustomer.id, item.id)"
                      title="Edit service"
                    >
                      <VIcon icon="tabler-edit" />
                      <VTooltip activator="parent" location="top">Edit Service</VTooltip>
                    </VBtn>
                    <VBtn
                      icon="tabler-trash"
                      variant="text"
                      size="x-small"
                      color="error"
                      @click="removeService(currentCustomer.id, item.id)"
                      title="Remove service"
                    >
                      <VIcon icon="tabler-trash" />
                      <VTooltip activator="parent" location="top">Remove Service</VTooltip>
                    </VBtn>
                  </template>
                </VDataTable>
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
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          @click="saveCustomer"
        >
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Service Form Dialog -->
  <VDialog v-model="serviceDialog" max-width="600px">
    <VCard>
      <VCardTitle>
        <span class="text-h5">Edit Customer Service</span>
      </VCardTitle>

      <VCardText>
        <VForm>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="currentService.type"
                label="Service Type"
                readonly
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="currentService.deadline"
                label="Deadline"
                type="date"
                required
              />
            </VCol>
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
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          @click="saveService"
          :loading="serviceLoading"
        >
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Attach Services Dialog -->
  <VDialog v-model="attachServiceDialog" max-width="800px">
    <VCard>
      <VCardTitle>
        <span class="text-h5">Attach Services to {{ currentCustomer.name }}</span>
      </VCardTitle>

      <VCardText>
        <VRow>
          <VCol cols="12">
            <VDataTable
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
                {{ moment().add(item.duration, 'months').format('YYYY-MM-DD') }}
              </template>

              <template #item.fee="{ item }">
                {{ item.currency.symbol }}{{ Number(item.fee).toFixed(2) }}
              </template>

              <template #item.currency.code="{ item }">
                {{ item.currency.code }}
              </template>
            </VDataTable>
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
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          @click="attachSelectedServices"
          :loading="serviceLoading"
          :disabled="selectedServices.length === 0"
        >
          Attach Selected Services
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Delete Confirmation Dialog -->
  <VDialog v-model="deleteDialog" max-width="500px">
    <VCard>
      <VCardTitle class="text-h5">
        Are you sure you want to delete this customer?
      </VCardTitle>
      <VCardText>
        This action cannot be undone. All customer data and associated services will be permanently removed.
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          @click="deleteDialog = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="error"
          @click="deleteCustomer"
        >
          Delete Permanently
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
