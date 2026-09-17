<script setup lang="ts">
import CurrencyApi, { type Currency } from '@/api/currency'
import CompanyApi, { type Company } from '@/api/company'
import CustomerApi, { type ApiResponse, type Customer } from '@/api/customer'
import EmployeeApi, { type Employee } from '@/api/employee'
import ProjectApi from '@/api/project'
import type { Service } from '@/api/services'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import { canAccess } from '@/utils/authorization'
import { printDocument } from '@/utils/printDocument'
import moment from 'moment'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { VCheckbox, VSelect, VTextField } from 'vuetify/components'

// Types
interface ServiceForm {
  id: number
  service_id: number
  employee_id: number | null
  employee_ids: number[]
  marketer_id: number | null
  outsourcing_company_id: number | null
  outsourcing_percentage: number | null
  fee: number | null
  currency_id: number | null
  deadline: string | null
  status: 'active' | 'paused' | 'completed'
  notes: string | null
}

// State
const toast = useToast()
const { t } = useI18n()
const router = useRouter()
const customers = ref<Customer[]>([])
const allServices = ref<Service[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const servicesLoading = ref(false)
const currencies = ref<Currency[]>([])
const marketers = ref<Employee[]>([])
const deliveryEmployees = ref<Employee[]>([])
const outsourcingCompanies = ref<Company[]>([])
const userData = useCookie<{ is_marketer?: boolean; employee?: { id: number; is_marketer?: boolean } | null } | null>('userData')

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
  marketer_id: null,
  outsourcing_company_id: null,
  outsourcing_percentage: null,
  marketer_employee: null,
  date: new Date().toISOString().split('T')[0],
  services: [],
  created_at: '',
  updated_at: '',
})

const currentService = ref<ServiceForm>({
  id: 0,
  service_id: 0,
  employee_id: null,
  employee_ids: [],
  marketer_id: null,
  outsourcing_company_id: null,
  outsourcing_percentage: null,
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

const selectedProjectServiceIds = ref<number[]>([])
const projectDialog = ref(false)
const projectLoading = ref(false)
const projectDraft = ref({ name: '', description: '', due_date: null as string | null })

// Search and filters
const search = ref('')
const filterCurrency = ref<string | null>(null)

// Table headers
const headers = [
  { title: translate('common.fields.name'), key: 'name', sortable: true },
  { title: translate('common.fields.company'), key: 'company', sortable: true },
  { title: translate('common.messages.contact'), key: 'contact', sortable: true },
  { title: translate('common.fields.email'), key: 'email', sortable: true },
  {
    title: translate('common.messages.services'),
    key: 'services',
    sortable: false,
    headerProps: { class: 'd-none d-md-table-cell' },
    cellProps: { class: 'd-none d-md-table-cell' },
  },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false },
]

const serviceHeaders = [
  ...(canAccess('create_projects') ? [{ title: '', key: 'projectSelect', sortable: false, width: '44px' }] : []),
  { title: translate('common.fields.type'), key: 'type' },
  { title: translate('pages.contracts.allCustomers.allCustomers.deliveryOwner'), key: 'employee', sortable: false },
  { title: translate('pages.contracts.allCustomers.allCustomers.acquisition'), key: 'marketer', sortable: false },
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
  if (!deadline)
    return { color: 'secondary', title: translate('pages.contracts.allCustomers.allCustomers.noDeadline') }
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
    const matchesSearch = c.name.toLowerCase().includes(search.value.toLowerCase())
      || c.company.toLowerCase().includes(search.value.toLowerCase())
      || c.contact.toLowerCase().includes(search.value.toLowerCase())
      || (c.email || '').toLowerCase().includes(search.value.toLowerCase())
      || (c.marketer || '').toLowerCase().includes(search.value.toLowerCase())

    const matchesCurrency = !filterCurrency.value
      || c.services.some(s => s.currency.code === filterCurrency.value)

    return matchesSearch && matchesCurrency
  })
})

// Computed available services (excluding already attached ones)
const availableServices = computed(() => {
  if (!currentCustomer.value.id)
    return allServices.value

  const attachedServiceIds = currentCustomer.value.services.map(s => s.service_id)

  return allServices.value.filter(s => !attachedServiceIds.includes(s.id))
})

const isCurrentUserMarketer = computed(() => Boolean(
  userData.value?.is_marketer || userData.value?.employee?.is_marketer,
))

const currentUserEmployeeId = computed(() => userData.value?.employee?.id ?? null)

const currentMarketerName = computed(() => {
  const employeeId = currentUserEmployeeId.value

  return marketers.value.find(marketer => marketer.id === employeeId)?.name ?? 'You'
})

const eligibleDeliveryEmployees = computed(() => deliveryEmployees.value.filter(employee =>
  employee.commissions.some(commission => commission.service_id === currentService.value.service_id),
))

// Use the application's locale-aware helper here rather than Vuetify's
// no-data prop resolving a template expression; this keeps the message
// reactive and avoids a literal missing-key marker in the service editor.
const noDeliveryEmployeeMessage = computed(() => translate(
  'pages.contracts.allCustomers.allCustomers.noCommissionEmployeeForService',
))

const deliveryEmployeeLabel = (employee: Employee) => {
  const capability = employee.commissions.find(commission => commission.service_id === currentService.value.service_id)

  return capability ? `${employee.name} · ${capability.percentage}% commission` : employee.name
}

const selectedProjectServices = computed(() => currentCustomer.value.services.filter(service =>
  selectedProjectServiceIds.value.includes(service.service_id),
))

const canCreateProject = computed(() => Boolean(
  currentCustomer.value.id && selectedProjectServices.value.length && projectDraft.value.name.trim(),
))

watch(() => currentService.value.service_id, serviceId => {
  currentService.value.employee_ids = currentService.value.employee_ids.filter(employeeId =>
    deliveryEmployees.value.some(employee => employee.id === employeeId
      && employee.commissions.some(commission => commission.service_id === serviceId)),
  )
})

// API methods
const fetchCustomers = async () => {
  try {
    loading.value = true

    const response: ApiResponse<Customer[]> = await CustomerApi.getAll({
      search: search.value,
      company: undefined,
      marketer: undefined,
    })

    if (response.success)
      customers.value = response.data
    else
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToFetchCustomers') + (response.message || 'Unknown error')
  }
  catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToFetchCustomers2')
    console.error(err)
  }
  finally {
    loading.value = false
  }
}

const fetchAllServices = async () => {
  try {
    servicesLoading.value = true

    const response = await CustomerApi.getAllServices()
    if (response.success)
      allServices.value = response.data
    else
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToFetchServices') + (response.message || 'Unknown error')
  }
  catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToFetchServices2')
    console.error(err)
  }
  finally {
    servicesLoading.value = false
  }
}

const fetchCurrencies = async () => {
  try {
    const response = await CurrencyApi.getAll()

    currencies.value = response.data
  }
  catch (err) {
    error.value = translate('common.messages.failedToFetchCurrencies')
    console.error(err)
  }
}

const fetchOutsourcingCompanies = async () => {
  try { outsourcingCompanies.value = await CompanyApi.getAll() }
  catch (err) { console.error('Failed to fetch outsourcing companies:', err) }
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
      date: currentCustomer.value.date,
      ...(isCurrentUserMarketer.value ? {} : { marketer_id: currentCustomer.value.marketer_id }),
    })

    if (response.success) {
      // Keep the newly created record on the first page of the paginated table.
      customers.value.unshift(response.data)
      dialog.value = false
    }
    else {
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToCreateCustomer') + (response.message || 'Unknown error')
    }
  }
  catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToCreateCustomer2')
    console.error(err)
  }
}

const updateCustomer = async () => {
  if (!currentCustomer.value.id)
    return

  try {
    const response: ApiResponse<Customer> = await CustomerApi.update(currentCustomer.value.id, {
      name: currentCustomer.value.name,
      company: currentCustomer.value.company,
      contact: currentCustomer.value.contact,
      email: currentCustomer.value.email,
      address: currentCustomer.value.address,
      tax_number: currentCustomer.value.tax_number,
      date: currentCustomer.value.date,

      // Marketer users never submit an alternative owner. On creation the
      // server assigns them automatically; on ordinary edits it preserves the
      // existing relationship owner.
      ...(isCurrentUserMarketer.value ? {} : { marketer_id: currentCustomer.value.marketer_id }),
    })

    if (response.success) {
      const index = customers.value.findIndex(c => c.id === currentCustomer.value.id)
      if (index !== -1)
        customers.value[index] = response.data

      dialog.value = false
    }
    else {
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToUpdateCustomer') + (response.message || 'Unknown error')
    }
  }
  catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToUpdateCustomer2')
    console.error(err)
  }
}

const updateService = async (customerId: number, pivotId: number) => {
  try {
    serviceLoading.value = true

    const response = await CustomerApi.updateService(customerId, pivotId, {
      service_id: currentService.value.service_id,
      employee_id: currentService.value.employee_id,
      employee_ids: currentService.value.employee_ids,
      marketer_id: currentService.value.marketer_id,
      outsourcing_company_id: currentService.value.outsourcing_company_id,
      outsourcing_percentage: currentService.value.outsourcing_percentage,
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
      if (index !== -1)
        customers.value[index] = customerResponse.data

      // Also update the current customer in the dialog if it's the same customer
      if (currentCustomer.value.id === customerId) {
        currentCustomer.value = customerResponse.data
        selectedProjectServiceIds.value = selectedProjectServiceIds.value.filter(serviceId =>
          customerResponse.data.services.some(service => service.service_id === serviceId),
        )
      }
    }

    serviceDialog.value = false
    toast.success(translate('pages.contracts.allCustomers.allCustomers.customerServiceUpdated'))
  }
  catch (err) {
    toast.error(translate('pages.contracts.allCustomers.allCustomers.failedToUpdateCustomerService'))
    console.error(err)
  }
  finally {
    serviceLoading.value = false
  }
}

const attachSelectedServices = async () => {
  if (!currentCustomer.value.id || selectedServices.value.length === 0)
    return

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
      if (index !== -1)
        customers.value[index] = customerResponse.data

      // Update the current customer in the dialog
      currentCustomer.value = customerResponse.data

      // Reset selected services and close dialog
      selectedServices.value = []
      selectedProjectServiceIds.value = []
      attachServiceDialog.value = false
      toast.success(translate('pages.contracts.allCustomers.allCustomers.servicesAttached'))
    }
  }
  catch (err) {
    error.value = translate('pages.contracts.allCustomers.allCustomers.failedToAttachServices')
    console.error(err)
  }
  finally {
    serviceLoading.value = false
  }
}

const deleteCustomer = async () => {
  if (!customerToDelete.value)
    return

  try {
    const response = await CustomerApi.delete(customerToDelete.value)
    if (response.success) {
      customers.value = customers.value.filter(c => c.id !== customerToDelete.value)
      deleteDialog.value = false
    }
    else {
      error.value = translate('pages.contracts.allCustomers.allCustomers.failedToDeleteCustomer') + (response.message || 'Unknown error')
    }
  }
  catch (err) {
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
      if (index !== -1)
        customers.value[index] = customerResponse.data

      // Also update the current customer in the dialog if it's the same customer
      if (currentCustomer.value.id === customerId) {
        currentCustomer.value = customerResponse.data
        selectedProjectServiceIds.value = selectedProjectServiceIds.value.filter(serviceId =>
          customerResponse.data.services.some(service => service.service_id === serviceId),
        )
      }
    }
  }
  catch (err) {
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
    marketer_id: isCurrentUserMarketer.value ? currentUserEmployeeId.value : null,
    outsourcing_company_id: null,
    outsourcing_percentage: null,
    marketer_employee: null,
    date: new Date().toISOString().split('T')[0],
    services: [],
    created_at: '',
    updated_at: '',
  }
  isEdit.value = false
  dialog.value = true
}

const openEditCustomer = async (customer: Customer) => {
  const response = await CustomerApi.getById(customer.id)

  currentCustomer.value = response.success ? response.data : { ...customer }
  selectedProjectServiceIds.value = []
  isEdit.value = true
  dialog.value = true
}

const saveCustomer = () => {
  if (isEdit.value)
    updateCustomer()
  else
    createCustomer()
}

const openEditService = async (customerId: number, pivotId: number) => {
  const response = await CustomerApi.getById(customerId)
  const customer = response.success ? response.data : customers.value.find(c => c.id === customerId)
  if (customer) {
    const service = customer.services.find(s => s.id === pivotId)
    if (service) {
      currentCustomer.value = { ...customer }
      currentService.value = {
        id: service.id,
        service_id: service.service_id,
        employee_id: service.employee_id ?? null,
        employee_ids: service.employee_ids ?? service.employees?.map(employee => employee.id) ?? (service.employee_id ? [service.employee_id] : []),
        marketer_id: service.marketer_id ?? customer.marketer_id ?? null,
        outsourcing_company_id: service.outsourcing_company_id ?? null,
        outsourcing_percentage: service.outsourcing_percentage ?? null,
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
  const response = await CustomerApi.getById(customerId)
  const customer = response.success ? response.data : customers.value.find(c => c.id === customerId)
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
    marketers.value = await EmployeeApi.getMarketerOptions()
  }
  catch (err) {
    console.error('Failed to fetch marketers:', err)
  }
}

const fetchDeliveryEmployees = async () => {
  try {
    deliveryEmployees.value = (await EmployeeApi.getOptions())
      .filter(employee => employee.employment_type === 'commission')
  }
  catch (err) {
    console.error('Failed to fetch commission-capable employees:', err)
  }
}

const toggleProjectService = (serviceId: number, selected: boolean | null) => {
  if (selected && !selectedProjectServiceIds.value.includes(serviceId))
    selectedProjectServiceIds.value.push(serviceId)
  else if (!selected)
    selectedProjectServiceIds.value = selectedProjectServiceIds.value.filter(id => id !== serviceId)
}

const openProjectDialog = () => {
  if (!currentCustomer.value.id || !selectedProjectServices.value.length)
    return

  const serviceNames = selectedProjectServices.value.map(service => service.type).join(' + ')

  const earliestDeadline = selectedProjectServices.value
    .map(service => service.deadline)
    .filter((deadline): deadline is string => Boolean(deadline))
    .sort()[0] ?? null

  projectDraft.value = {
    name: `${currentCustomer.value.name} — ${serviceNames}`.slice(0, 255),
    description: `Delivery workspace for ${serviceNames}.`,
    due_date: earliestDeadline,
  }
  projectDialog.value = true
}

const createProjectFromSelectedServices = async () => {
  if (!canCreateProject.value)
    return

  projectLoading.value = true
  try {
    const project = await ProjectApi.create({
      name: projectDraft.value.name.trim(),
      description: projectDraft.value.description.trim() || null,
      due_date: projectDraft.value.due_date,
      customer_id: currentCustomer.value.id,
      service_ids: selectedProjectServices.value.map(service => service.service_id),
    })

    toast.success(`Project “${project.name}” created with ${selectedProjectServices.value.length} selected service${selectedProjectServices.value.length === 1 ? '' : 's'}.`)
    selectedProjectServiceIds.value = []
    projectDialog.value = false
  }
  catch (err: any) {
    toast.error(err?.response?.data?.errors?.service_ids?.[0] || err?.response?.data?.message || 'Unable to create the project from these services.')
  }
  finally {
    projectLoading.value = false
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
  fetchDeliveryEmployees()
  fetchOutsourcingCompanies()
})
</script>

<template>
  <DataTablePageKpis
    :items="filtered"
    title="Customer directory"
    description="Track customer relationships and their active services at a glance."
    icon="tabler-users-group"
  />
  <VCard>
    <VCardText>
      <VRow align="center">
        <VCol
          cols="12"
          md="7"
        >
          <VTextField
            v-model="search"
            class="data-table-search"
            prepend-inner-icon="tabler-search"
            :placeholder="$t('pages.contracts.allCustomers.allCustomers.searchCustomers')"
            density="compact"
            clearable
            hide-details
            @input="fetchCustomers"
          />
        </VCol>
        <VCol
          cols="12"
          md="5"
          class="text-end d-flex gap-2 justify-end"
        >
          <DataTableFilterMenu
            :active="Boolean(filterCurrency)"
            @reset="filterCurrency = null"
          >
            <VSelect
              v-model="filterCurrency"
              :items="currencies"
              item-title="code"
              item-value="code"
              :label="$t('pages.contracts.allCustomers.allCustomers.filterByCurrency')"
              density="compact"
              clearable
              hide-details
            />
          </DataTableFilterMenu>
          <VBtn
            icon="tabler-printer"
            variant="text"
            @click="printTable"
          >
            <VIcon icon="tabler-printer" /><VTooltip
              activator="parent"
              location="top"
            >
              {{ $t('pages.contracts.allCustomers.allCustomers.printCustomerList') }}
            </VTooltip>
          </VBtn>
          <VBtn
            color="primary"
            @click="openAddCustomer"
          >
            <VIcon
              icon="tabler-plus"
              class="me-1"
            />
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
        <ContactActionMenu
          kind="phone"
          :value="item.contact"
          contactable-type="customer"
          :contactable-id="item.id"
        />
      </template>

      <template #item.email="{ item }">
        <ContactActionMenu
          v-if="item.email"
          kind="email"
          :value="item.email"
          contactable-type="customer"
          :contactable-id="item.id"
        />
        <span
          v-else
          class="text-medium-emphasis"
        >—</span>
      </template>

      <template #item.date="{ item }">
        {{ formatLocalizedDate(`${item.date}T00:00:00`) }}
      </template>

      <template #item.services="{ item }">
        <div class="d-flex align-center flex-wrap ga-1 customer-services-summary">
          <span
            v-if="item.services.length === 0"
            class="text-medium-emphasis text-body-2"
          >No services</span>
          <VChip
            v-for="service in item.services.slice(0, 2)"
            v-else
            :key="service.id"
            size="small"
            variant="tonal"
            color="primary"
          >
            {{ service.type }}
          </VChip>
          <VChip
            v-if="item.services.length > 2"
            size="small"
            variant="outlined"
          >
            +{{ item.services.length - 2 }}
          </VChip>
          <VBtn
            v-if="item.services.length"
            size="x-small"
            variant="text"
            append-icon="tabler-chevron-left"
            class="mt-1 manage-services-button"
            @click="openEditCustomer(item)"
          >
            {{ $t('pages.contracts.allCustomers.allCustomers.manageServices') }}
          </VBtn>
        </div>
      </template>

      <template #item.actions="{ item }">
        <CrmRelationshipDetails
          :id="item.id"
          type="customer"
          :title="item.name"
        />
        <VBtn
          icon="tabler-edit"
          variant="text"
          size="small"
          color="primary"
          @click="openEditCustomer(item)"
        >
          <VIcon icon="tabler-edit" />
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ $t('pages.contracts.allCustomers.allCustomers.editCustomer2') }}
          </VTooltip>
        </VBtn>
        <VBtn
          icon="tabler-plus"
          variant="text"
          size="small"
          color="success"
          @click="openAttachServices(item.id)"
        >
          <VIcon icon="tabler-plus" />
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ $t('common.messages.attachServices') }}
          </VTooltip>
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
          <VTooltip
            activator="parent"
            location="top"
          >
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
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ $t('pages.contracts.allCustomers.allCustomers.deleteCustomer2') }}
          </VTooltip>
        </VBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>
  <PrintableListReport
    :title="$t('common.crmUi.customersReport')"
    :subtitle="$t('common.crmUi.filteredCustomerRecords')"
    :columns="[$t('common.crmUi.printColumns.name'), $t('common.crmUi.printColumns.company'), $t('common.crmUi.printColumns.contact'), $t('common.crmUi.printColumns.marketer'), $t('common.crmUi.printColumns.registered'), $t('common.crmUi.printColumns.services')]"
    :rows="filtered.map(customer => [customer.name, customer.company, customer.contact, customer.marketer, customer.date, customer.services.length ? customer.services.map(service => `${service.type}: ${service.fee} ${service.currency?.code ?? ''}${service.deadline ? ` · ${service.deadline}` : ''}`).join(' | ') : '—'])"
  />

  <!-- Customer Form Dialog -->
  <VDialog
    v-model="dialog"
    max-width="800px"
  >
    <VCard>
      <VCardTitle>
        <span class="text-h5">{{ isEdit ? 'Edit Customer' : 'Add New Customer' }}</span>
      </VCardTitle>

      <VCardText>
        <VForm class="app-form">
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="currentCustomer.name"
                :label="$t('pages.contracts.allCustomers.allCustomers.fullName')"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="currentCustomer.company"
                :label="$t('common.fields.company')"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="currentCustomer.contact"
                :label="$t('common.fields.phone')"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="currentCustomer.email"
                label="Email"
                type="email"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="currentCustomer.address"
                label="Address"
                rows="2"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="currentCustomer.tax_number"
                label="Tax number"
              />
            </VCol>
            <VCol
              v-if="isCurrentUserMarketer"
              cols="12"
              md="6"
            >
              <VTextField
                :model-value="currentMarketerName"
                :label="$t('common.messages.marketer')"
                prepend-inner-icon="tabler-user-check"
                :hint="$t('common.crmUi.customerMarketerAutomatic')"
                persistent-hint
                readonly
              />
            </VCol>
            <VCol
              v-else
              cols="12"
              md="6"
            >
              <VAutocomplete
                v-model="currentCustomer.marketer_id"
                :items="marketers"
                item-title="name"
                item-value="id"
                :label="$t('common.messages.marketer')"
                :placeholder="$t('common.messages.selectAMarketer')"
                :no-data-text="$t('common.crmUi.customerMarketerEmpty')"
                :hint="$t('common.crmUi.customerMarketerHint')"
                persistent-hint
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
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
              <VCard class="customer-services-card">
                <VCardTitle class="d-flex flex-wrap justify-space-between align-center ga-3 py-4">
                  <div>
                    <div class="text-subtitle-1 font-weight-semibold">
                      {{ $t('pages.contracts.allCustomers.allCustomers.services') }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t('pages.contracts.allCustomers.allCustomers.chooseDeliveryOwners') }}
                    </div>
                  </div>
                  <div
                    v-if="currentCustomer.id"
                    class="d-flex flex-wrap align-center ga-2"
                  >
                    <VChip
                      v-if="selectedProjectServices.length"
                      size="small"
                      color="primary"
                      variant="tonal"
                    >
                      {{ selectedProjectServices.length }} selected
                    </VChip>
                    <VBtn
                      v-if="canAccess('create_projects')"
                      color="secondary"
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-briefcase-2"
                      :disabled="!selectedProjectServices.length"
                      @click="openProjectDialog"
                    >
                      Create project
                    </VBtn>
                    <VBtn
                      color="primary"
                      size="small"
                      prepend-icon="tabler-plus"
                      @click="openAttachServices(currentCustomer.id)"
                    >
                      {{ $t('common.messages.attachServices') }}
                    </VBtn>
                  </div>
                </VCardTitle>

                <div class="customer-services-table-wrap">
                <ResponsiveDataTable
                  class="customer-services-table"
                  :headers="serviceHeaders"
                  :items="currentCustomer.services"
                  density="compact"
                  hide-default-footer
                  sticky-last-column
                >
                  <template #item.projectSelect="{ item }">
                    <VCheckbox
                      :model-value="selectedProjectServiceIds.includes(item.service_id)"
                      density="compact"
                      hide-details
                      :aria-label="`Include ${item.type} in a new project`"
                      @update:model-value="toggleProjectService(item.service_id, $event)"
                    />
                  </template>
                  <template #item.employee="{ item }">
                    <div
                      v-if="item.employees?.length || item.employee"
                      class="d-flex align-center ga-2"
                    >
                      <VAvatar
                        size="28"
                        color="primary"
                        variant="tonal"
                      >
                        <VIcon
                          icon="tabler-user-check"
                          size="15"
                        />
                      </VAvatar>
                      <span class="text-body-2">{{ item.employees?.length ? item.employees.map((employee: Employee) => employee.name).join(', ') : item.employee.name }}</span>
                    </div>
                    <span
                      v-else
                      class="text-caption text-warning"
                    >Unassigned</span>
                  </template>
                  <template #item.marketer="{ item }">
                    <span
                      v-if="item.marketer"
                      class="text-body-2"
                    >{{ item.marketer.name }}</span>
                    <span
                      v-else
                      class="text-medium-emphasis"
                    >—</span>
                  </template>
                  <template #item.deadline="{ item }">
                    <VChip
                      v-if="item.deadline"
                      :color="deadlineMeta(item.deadline).color"
                      size="small"
                      label
                      variant="tonal"
                    >
                      {{ formatLocalizedDate(`${item.deadline}T00:00:00`) }}
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ deadlineMeta(item.deadline).title }}
                      </VTooltip>
                    </VChip>
                    <span
                      v-else
                      class="text-medium-emphasis"
                    >{{ $t('common.messages.noDeadline') }}</span>
                  </template>
                  <template #item.currency.code="{ item }">
                    {{ item.currency.code }}
                  </template>
                  <template #item.serviceActions="{ item }">
                    <div class="service-actions">
                      <FullDetailsButton
                        :item="item"
                        :title="item.type"
                      />
                      <VBtn
                        icon="tabler-edit"
                        variant="text"
                        size="x-small"
                        color="primary"
                        @click="openEditService(currentCustomer.id, item.id)"
                      >
                        <VIcon icon="tabler-edit" />
                        <VTooltip activator="parent" location="top">
                          {{ $t('common.messages.editService2') }}
                        </VTooltip>
                      </VBtn>
                      <VBtn
                        icon="tabler-trash"
                        variant="text"
                        size="x-small"
                        color="error"
                        @click="removeService(currentCustomer.id, item.id)"
                      >
                        <VIcon icon="tabler-trash" />
                        <VTooltip activator="parent" location="top">
                          {{ $t('common.messages.removeService2') }}
                        </VTooltip>
                      </VBtn>
                    </div>
                  </template>
                </ResponsiveDataTable>
                </div>
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

  <!-- Create a delivery project from the purchased services selected above. -->
  <VDialog
    v-model="projectDialog"
    max-width="640px"
  >
    <VCard>
      <VCardTitle class="d-flex align-center ga-3 py-5">
        <VAvatar
          color="primary"
          variant="tonal"
          size="40"
        >
          <VIcon icon="tabler-briefcase-2" />
        </VAvatar>
        <div>
          <div class="text-h6">
            Create delivery project
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ currentCustomer.name }} · {{ selectedProjectServices.length }} selected service{{ selectedProjectServices.length === 1 ? '' : 's' }}
          </div>
        </div>
      </VCardTitle>
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          This project will cover only the selected purchased services. You can plan tasks without exposing the customer's other services.
        </VAlert>
        <div class="d-flex flex-wrap ga-2 mb-5">
          <VChip
            v-for="service in selectedProjectServices"
            :key="service.id"
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ service.type }}
          </VChip>
        </div>
        <AppDateTimePicker
          v-model="projectDraft.due_date"
          label="Target date"
          clearable
          class="project-target-date-picker mb-4"
          :config="{ dateFormat: 'Y-m-d', position: 'below' }"
        />
        <VTextField
          v-model="projectDraft.name"
          label="Project name"
          prepend-inner-icon="tabler-folder"
          class="mb-4"
          autofocus
        />
        <VTextarea
          v-model="projectDraft.description"
          label="Delivery brief"
          rows="3"
          auto-grow
          class="mb-4"
        />
      </VCardText>
      <VCardActions class="pa-5">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="projectDialog = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-briefcase-2"
          :loading="projectLoading"
          :disabled="!canCreateProject"
          @click="createProjectFromSelectedServices"
        >
          Create project
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Service Form Dialog -->
  <VDialog
    v-model="serviceDialog"
    max-width="600px"
  >
    <VCard class="customer-service-form-card">
      <VCardTitle>
        <span class="text-h5">{{ $t('pages.contracts.allCustomers.allCustomers.editCustomerService') }}</span>
      </VCardTitle>

      <VCardText>
        <VForm class="app-form">
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="currentService.service_id"
                :items="allServices"
                item-title="type"
                item-value="id"
                :label="$t('common.messages.serviceType')"
                :loading="servicesLoading"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="currentService.currency_id"
                :items="currencies"
                item-title="code"
                item-value="id"
                :label="$t('common.fields.currency')"
              />
            </VCol>
            <VCol cols="12">
              <VAutocomplete
                v-model="currentService.employee_ids"
                :items="eligibleDeliveryEmployees"
                :item-title="deliveryEmployeeLabel"
                item-value="id"
                multiple
                chips
                :label="$t('pages.contracts.allCustomers.allCustomers.deliveryOwnerOptional')"
                prepend-inner-icon="tabler-user-check"
                clearable
                :hint="$t('pages.contracts.allCustomers.allCustomers.onlyConfiguredRateEmployees')"
                persistent-hint
              >
                <template #no-data>
                  <VListItem
                    class="delivery-owner-empty-state"
                    prepend-icon="tabler-user-off"
                    :title="noDeliveryEmployeeMessage"
                  />
                </template>
              </VAutocomplete>
            </VCol>
            <VCol
              v-if="isCurrentUserMarketer"
              cols="12"
              md="6"
            >
              <VTextField
                :model-value="currentMarketerName"
                :label="$t('common.crmUi.serviceMarketer')"
                prepend-inner-icon="tabler-speakerphone"
                hint="Your attribution is applied automatically."
                persistent-hint
                readonly
              />
            </VCol>
            <VCol
              v-else
              cols="12"
              md="6"
            >
              <VAutocomplete
                v-model="currentService.marketer_id"
                :items="marketers"
                item-title="name"
                item-value="id"
                :label="$t('common.crmUi.serviceMarketerOptional')"
                prepend-inner-icon="tabler-speakerphone"
                clearable
                :hint="$t('common.crmUi.serviceMarketerHint')"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VAutocomplete
                v-model="currentService.outsourcing_company_id"
                :items="outsourcingCompanies"
                :item-title="(company: Company) => company.trade_name || company.legal_name"
                item-value="id"
                :label="t('outsourcing.company')"
                prepend-inner-icon="tabler-building-factory-2"
                clearable
                @update:model-value="value => { if (!value) currentService.outsourcing_percentage = null }"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model.number="currentService.outsourcing_percentage"
                :label="t('outsourcing.rate')"
                type="number"
                min="0.01"
                max="100"
                step="0.01"
                suffix="%"
                :disabled="!currentService.outsourcing_company_id"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="currentService.deadline"
                :label="$t('pages.contracts.allCustomers.allCustomers.deadline')"
                type="date"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model.number="currentService.fee"
                :label="$t('common.messages.fee')"
                type="number"
                min="0"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="currentService.status"
                :items="['active', 'paused', 'completed']"
                :label="$t('common.fields.status')"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="currentService.notes"
                :label="$t('common.messages.notes')"
                rows="2"
                auto-grow
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
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="serviceLoading"
          @click="saveService"
        >
          {{ $t('common.actions.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Attach Services Dialog -->
  <VDialog
    v-model="attachServiceDialog"
    max-width="800px"
  >
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
                        service_id: item.id,
                      })
                    }
                    else {
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
              <template #item.details="{ item }">
                <FullDetailsButton
                  :item="item"
                  :title="item.type"
                />
              </template>
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
          :loading="serviceLoading"
          :disabled="selectedServices.length === 0"
          @click="attachSelectedServices"
        >
          {{ $t('pages.contracts.allCustomers.allCustomers.attachSelectedServices') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Delete Confirmation Dialog -->
  <VDialog
    v-model="deleteDialog"
    max-width="500px"
  >
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

<style scoped>
.customer-services-card {
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  background: rgba(var(--v-theme-surface), 0.72);
}

.customer-service-form-card :deep(.v-card-title) {
  padding: 22px 24px 14px;
  color: rgb(var(--v-theme-primary));
  font-weight: 700;
}

.customer-service-form-card :deep(.v-card-text) {
  padding: 10px 24px 22px;
}

.customer-service-form-card :deep(.v-field) {
  border-radius: 10px;
}

.customer-service-form-card :deep(.v-card-actions) {
  padding: 14px 24px 20px;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.project-target-date-picker :deep(.flatpickr-calendar) {
  z-index: 3000 !important;
}

/* Flatpickr portals its calendar to document.body, so a scoped selector
   cannot affect the popup once it leaves this component's DOM subtree. */
:global(.flatpickr-calendar) {
  z-index: 10000 !important;
}

.customer-services-table-wrap {
  overflow-x: auto;
  padding: 0 16px 16px;
}

.customer-services-table {
  min-width: 760px;
}

.customer-services-summary {
  padding: 4px 6px;
}

.manage-services-button :deep(.v-btn__append .v-icon) {
  transform: none !important;
}

.customer-services-table :deep(.v-table__wrapper) {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 12px;
}

.customer-services-table :deep(th) {
  height: 44px !important;
  color: rgba(var(--v-theme-on-surface), 0.72) !important;
  background: rgba(var(--v-theme-primary), 0.08) !important;
  font-size: 0.7rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  white-space: nowrap;
}

.customer-services-table :deep(td) {
  height: 60px !important;
  border-bottom-color: rgba(var(--v-theme-on-surface), 0.08) !important;
  white-space: nowrap;
}

.customer-services-table :deep(tbody tr:hover) {
  background: rgba(var(--v-theme-primary), 0.06) !important;
}

.service-actions {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 2px;
  flex-wrap: nowrap;
  min-width: 96px;
  white-space: nowrap;
}

.customer-services-table :deep(td:last-child) {
  position: sticky;
  inset-inline-end: 0;
  z-index: 2;
  width: 124px;
  min-width: 124px;
  border-inline-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  background: rgb(var(--v-theme-surface));
  white-space: nowrap;
}

.customer-services-table :deep(th:last-child) {
  position: sticky;
  inset-inline-end: 0;
  z-index: 4;
  width: 124px;
  min-width: 124px;
  border-inline-start: 1px solid rgba(var(--v-theme-on-surface), 0.13);
  background: rgb(var(--v-theme-surface)) !important;
}

/* Keep the row actions reachable while horizontally reviewing wide service
   records. Logical insets make the fixed column sit at the correct edge in
   both LTR and RTL layouts. */
.customer-services-table :deep(tbody tr:hover td:last-child) {
  background: rgba(var(--v-theme-primary), 0.06) !important;
}

.service-owner {
  min-width: 145px;
}

@media (max-width: 900px) {
  .customer-services-table-wrap {
    padding-inline: 10px;
  }
}
</style>
