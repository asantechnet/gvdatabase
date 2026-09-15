<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import InvoiceTemplate from './templates/invoice_template.vue'
import CustomerApi, { type Customer, type CustomerService } from '@/api/customer'
import CurrencyApi, { type Currency } from '@/api/currency'
import InvoiceApi, { type Invoice } from '@/api/invoice'
import PaymentDetailApi, { type PaymentDetail } from '@/api/paymentDetails'
import SettingApi, { type Setting } from '@/api/setting'

const route = useRoute()
const toast = useToast()

const invoices = ref<Invoice[]>([])
const customers = ref<Customer[]>([])
const currencies = ref<Currency[]>([])
const paymentDetails = ref<PaymentDetail[]>([])
const companySetting = ref<Setting | null>(null)
const isLoading = ref(false)
const searchQuery = ref('')
const selectedStatusFilter = ref<Invoice['status'] | null>(null)
const isDialogActive = ref(false)
const voidDialog = ref(false)
const invoiceToVoid = ref<Invoice | null>(null)
const voidReason = ref('')
const activePrintInvoice = ref<Invoice | null>(null)
const isInvoicePreviewOpen = ref(false)
const invoiceTemplateRef = ref<InstanceType<typeof InvoiceTemplate> | null>(null)

const invoiceStatuses: Invoice['status'][] = ['Issued', 'Partially Paid', 'Paid', 'Overdue', 'Void']

const defaultForm = () => ({
  customer_id: 0,
  currency_id: 0,
  rate: 1,
  issue_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  service_ids: [] as number[],
  tax_percent: 0,
  discount: 0,
  notes: '',
  payment_detail_id: null as number | null,
})

const invoiceForm = ref(defaultForm())

const headers = [
  { title: translate('pages.settings.innvoice.innvoice.invoiceNumber'), key: 'invoice_number' },
  { title: translate('pages.settings.innvoice.innvoice.customer'), key: 'deptor' },
  { title: translate('common.fields.currency'), key: 'currency.shortname' },
  { title: translate('common.messages.total'), key: 'total_amount' },
  { title: translate('common.fields.amountPaid'), key: 'paid_amount' },
  { title: translate('common.messages.balance'), key: 'balance' },
  { title: translate('common.fields.status'), key: 'status' },
  { title: 'Recorded by', key: 'recordedBy.name' },
  { title: translate('common.messages.services'), key: 'data-table-expand', sortable: false },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
]

const invoiceServiceHeaders = [
  { title: translate('common.messages.service'), key: 'name' },
  { title: translate('common.messages.quantity'), key: 'quantity', align: 'end' as const },
  { title: translate('documents.invoice_template.invoiceTemplate.unitCOST'), key: 'price', align: 'end' as const },
  { title: translate('common.fields.amount'), key: 'line_total', align: 'end' as const },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
]

const serviceHeaders = [
  { title: translate('pages.settings.innvoice.innvoice.include'), key: 'selected', sortable: false },
  { title: translate('common.messages.service'), key: 'type' },
  { title: translate('pages.settings.innvoice.innvoice.originalFee'), key: 'fee' },
  { title: translate('pages.settings.innvoice.innvoice.invoiceAmount'), key: 'converted' },
  { title: 'Delivery owner', key: 'commission_employee', sortable: false },
  { title: translate('common.messages.deadline'), key: 'deadline' },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
]

const selectedCustomer = computed(() =>
  customers.value.find(customer => customer.id === invoiceForm.value.customer_id) ?? null,
)

const invoiceCurrency = computed(() =>
  currencies.value.find(currency => Number(currency.id) === Number(invoiceForm.value.currency_id)) ?? null,
)

const currentBaseCurrency = computed(() => currencies.value.find(currency => currency.isBase) ?? null)

const selectedPaymentDetail = computed(() =>
  paymentDetails.value.find(detail => detail.id === Number(invoiceForm.value.payment_detail_id)) ?? null,
)

const assignedServices = computed(() => selectedCustomer.value?.services ?? [])

const selectedServices = computed(() =>
  assignedServices.value.filter(service => invoiceForm.value.service_ids.includes(service.id)),
)

const convertServiceFee = (service: CustomerService) => {
  const sourceRate = Number(service.currency.rate)
  const targetRate = Number(invoiceForm.value.rate)

  if (sourceRate <= 0 || targetRate <= 0)
    return 0

  return Number(service.fee) / sourceRate * targetRate
}

const formSubtotal = computed(() =>
  selectedServices.value.reduce((total, service) => total + convertServiceFee(service), 0),
)

const formTaxAmount = computed(() => formSubtotal.value * Number(invoiceForm.value.tax_percent || 0) / 100)
const formTotal = computed(() => Math.max(0, formSubtotal.value + formTaxAmount.value - Number(invoiceForm.value.discount || 0)))

const filteredInvoices = computed(() => invoices.value.filter(invoice => {
  const query = searchQuery.value.toLowerCase()

  const matchesSearch = invoice.deptor.toLowerCase().includes(query)
    || invoice.invoice_number.toLowerCase().includes(query)

  const matchesStatus = !selectedStatusFilter.value || invoice.status === selectedStatusFilter.value

  return matchesSearch && matchesStatus
}))

const getStatusColor = (status: Invoice['status']) => ({
  'Issued': 'primary',
  'Partially Paid': 'warning',
  'Paid': 'success',
  'Overdue': 'error',
  'Void': 'secondary',
}[status])

const formatMoney = (amount: number, currency?: { symbol?: string; shortname?: string; decimalPlaces?: number }) => {
  const decimals = currency?.decimalPlaces ?? invoiceCurrency.value?.decimalPlaces ?? 2
  const formatted = Number(amount || 0).toFixed(decimals)

  return `${currency?.symbol || ''}${formatted} ${currency?.shortname || ''}`.trim()
}

const fetchInvoices = async () => {
  invoices.value = await InvoiceApi.getAll()
}

const fetchFormOptions = async () => {
  const [customerResponse, currencyResponse, paymentDetailResponse] = await Promise.all([
    CustomerApi.getAll(),
    CurrencyApi.getAll(),
    PaymentDetailApi.getAll(),
  ])

  if (!customerResponse.success)
    throw new Error(customerResponse.message || 'Failed to load customers')

  customers.value = customerResponse.data
  currencies.value = currencyResponse.data
  paymentDetails.value = paymentDetailResponse
}

const selectCustomer = (customerId: number) => {
  invoiceForm.value.customer_id = Number(customerId)
  invoiceForm.value.service_ids = []
}

const selectInvoiceCurrency = (currencyId: number) => {
  const currency = currencies.value.find(item => Number(item.id) === Number(currencyId))

  invoiceForm.value.rate = Number(currency?.rate || 1)
}

const openCreateDialog = (customerId?: number) => {
  const baseCurrency = currencies.value.find(currency => currency.isBase && currency.isActive)
    ?? currencies.value.find(currency => currency.isActive)

  invoiceForm.value = {
    ...defaultForm(),
    customer_id: Number(customerId || 0),
    currency_id: Number(baseCurrency?.id || 0),
    rate: Number(baseCurrency?.rate || 1),
  }
  isDialogActive.value = true
}

const toggleService = (customerServiceId: number, selected: boolean | null) => {
  if (selected) {
    if (!invoiceForm.value.service_ids.includes(customerServiceId))
      invoiceForm.value.service_ids.push(customerServiceId)
  }
  else {
    invoiceForm.value.service_ids = invoiceForm.value.service_ids.filter(id => id !== customerServiceId)
  }
}

const createInvoice = async () => {
  try {
    isLoading.value = true
    await InvoiceApi.create({
      customer_id: invoiceForm.value.customer_id,
      currency_id: invoiceForm.value.currency_id,
      rate: Number(invoiceForm.value.rate),
      issue_date: invoiceForm.value.issue_date || undefined,
      due_date: invoiceForm.value.due_date || undefined,
      services: invoiceForm.value.service_ids.map(id => ({
        customer_service_id: id,
        quantity: 1,
      })),
      tax_percent: Number(invoiceForm.value.tax_percent),
      discount: Number(invoiceForm.value.discount),
      notes: invoiceForm.value.notes,
      payment_detail_id: invoiceForm.value.payment_detail_id,
    })
    isDialogActive.value = false
    await fetchInvoices()
    toast.success(translate('pages.settings.innvoice.innvoice.invoiceIssued'))
  }
  catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to issue invoice')
  }
  finally {
    isLoading.value = false
  }
}

const openVoidDialog = (invoice: Invoice) => {
  invoiceToVoid.value = invoice
  voidReason.value = ''
  voidDialog.value = true
}

const voidInvoice = async () => {
  if (!invoiceToVoid.value || !voidReason.value.trim())
    return

  try {
    isLoading.value = true
    await InvoiceApi.void(invoiceToVoid.value.id, voidReason.value.trim())
    voidDialog.value = false
    await fetchInvoices()
    toast.success(translate('pages.settings.innvoice.innvoice.invoiceVoidedWithoutDeletingItsRecord'))
  }
  catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to void invoice')
  }
  finally {
    isLoading.value = false
  }
}

const formatInvoiceForTemplate = (invoice: Invoice) => ({
  invoiceNumber: invoice.invoice_number,
  dateOfIssue: invoice.issue_date ? formatLocalizedDate(invoice.issue_date) : '',
  dueDate: invoice.due_date ? formatLocalizedDate(invoice.due_date) : '',
  client: {
    name: invoice.deptor,
    addressLines: [invoice.customer.company, invoice.customer.address, invoice.customer.contact, invoice.customer.email, invoice.customer.tax_number].filter(Boolean) as string[],
  },
  sender: {
    brandName: companySetting.value?.original_company_name || companySetting.value?.short_company_name || companySetting.value?.website_title || 'Our Company',
    logo: companySetting.value?.website_logo_url || undefined,
    signature: companySetting.value?.company_signature_url || undefined,
    addressLines: [companySetting.value?.physical_address].filter(Boolean) as string[],
    phone: companySetting.value?.phone || undefined,
    email: companySetting.value?.company_email || undefined,
    website: companySetting.value?.website_address || undefined,
  },
  services: invoice.services.map(service => ({
    description: service.name,
    subDescription: service.subDescription || '',
    price: service.price,
    quantity: service.quantity,
    currency: invoice.currency.symbol,
    exchangeRate: service.source_currency?.rate || invoice.currency.rate,
  })),
  discount: invoice.discount,
  taxRate: invoice.tax_percent,
  notes: invoice.notes,
  paymentMethod: invoice.payment_method || '',
  paymentInstructions: invoice.payment_instructions,
})

const triggerPrint = async (invoice: Invoice) => {
  activePrintInvoice.value = invoice
  isInvoicePreviewOpen.value = true
  await nextTick()
}

const printPreviewedInvoice = () => invoiceTemplateRef.value?.print()

onMounted(async () => {
  try {
    isLoading.value = true

    const [, , profile] = await Promise.all([fetchInvoices(), fetchFormOptions(), SettingApi.getCompanyProfile()])

    companySetting.value = profile.data

    const customerId = Number(route.query.customer)
    if (customerId && customers.value.some(customer => customer.id === customerId))
      openCreateDialog(customerId)
  }
  catch (error) {
    toast.error(translate('pages.settings.innvoice.innvoice.failedToLoadInvoicingData'))
  }
  finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="invoice-management-wrapper">
    <DataTablePageKpis
      :items="filteredInvoices"
      :base-currency="currentBaseCurrency"
      title="Invoices"
      description="Keep billing activity visible, from paid invoices to items that need attention."
      icon="tabler-file-invoice"
    />
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol
            cols="12"
            md="8"
          >
            <VTextField
              v-model="searchQuery"
              class="data-table-search"
              prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.settings.innvoice.innvoice.searchInvoiceNumberOrCustomer')"
              density="compact"
              hide-details
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
            class="d-flex justify-end ga-2"
          >
            <DataTableFilterMenu
              :active="Boolean(selectedStatusFilter)"
              @reset="selectedStatusFilter = null"
            >
              <VSelect
                v-model="selectedStatusFilter"
                :items="invoiceStatuses"
                :label="$t('common.fields.status')"
                density="compact"
                hide-details
                clearable
              />
            </DataTableFilterMenu>
            <VBtn
              color="primary"
              block
              @click="openCreateDialog"
            >
              <VIcon
                icon="tabler-file-invoice"
                class="me-1"
              /> {{ $t('common.messages.issueInvoice') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <ResponsiveDataTable
        :headers="headers"
        :items="filteredInvoices"
        :loading="isLoading"
        class="text-no-wrap"
        show-expand
      >
        <template #item.currency.shortname="{ item }">
          {{ item.currency.shortname }}
        </template>
        <template #item.total_amount="{ item }">
          {{ formatMoney(item.total_amount, item.currency) }}
        </template>
        <template #item.paid_amount="{ item }">
          {{ formatMoney(item.paid_amount, item.currency) }}
        </template>
        <template #item.balance="{ item }">
          {{ formatMoney(item.balance, item.currency) }}
        </template>
        <template #expanded-row="{ columns, item }">
          <tr class="invoice-services-row">
            <td
              :colspan="columns.length"
              class="pa-4"
            >
              <ResponsiveDataTable
                :headers="invoiceServiceHeaders"
                :items="item.services"
                density="compact"
                hide-default-footer
                class="invoice-services-table"
              >
                <template #item.price="{ item: service }">
                  {{ formatMoney(service.price, service.currency) }}
                </template>
                <template #item.line_total="{ item: service }">
                  {{ formatMoney(service.line_total, service.currency) }}
                </template>
                <template #item.actions="{ item: service }">
                  <FullDetailsButton
                    :item="service"
                    :title="service.name"
                  />
                </template>
              </ResponsiveDataTable>
            </td>
          </tr>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            variant="tonal"
            :color="getStatusColor(item.status)"
          >
            {{ translateBackendValue(item.status) }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex justify-end gap-1">
            <FullDetailsButton
              :item="item"
              :title="item.invoice_number"
            />
            <VBtn
              icon
              variant="text"
              size="small"
              color="info"
              @click="triggerPrint(item)"
            >
              <VIcon icon="tabler-printer" />
              <VTooltip activator="parent">
                {{ $t('common.actions.print') }}
              </VTooltip>
            </VBtn>
            <VBtn
              v-if="item.status !== 'Void'"
              icon
              variant="text"
              size="small"
              color="error"
              @click="openVoidDialog(item)"
            >
              <VIcon icon="tabler-circle-x" />
              <VTooltip activator="parent">
                {{ $t('pages.settings.innvoice.innvoice.voidInvoice2') }}
              </VTooltip>
            </VBtn>
          </div>
        </template>
      </ResponsiveDataTable>
    </VCard>

    <VDialog
      v-model="isDialogActive"
      max-width="1080"
    >
      <VCard class="invoice-editor">
        <header class="invoice-editor__header">
          <div class="d-flex align-center ga-3 min-w-0">
            <VAvatar
              color="primary"
              variant="tonal"
              size="42"
              rounded="lg"
            >
              <VIcon
                icon="tabler-file-invoice"
                size="22"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="text-overline text-primary invoice-editor__eyebrow">
                {{ $t('pages.settings.innvoice.innvoice.newInvoice') }}
              </div>
              <h2 class="text-h5 invoice-editor__title">
                {{ $t('common.messages.issueInvoice') }}
              </h2>
            </div>
          </div>
          <IconBtn
            :aria-label="$t('common.actions.close')"
            @click="isDialogActive = false"
          >
            <VIcon icon="tabler-x" />
          </IconBtn>
        </header>

        <VForm
          class="app-form invoice-editor__form"
          @submit.prevent="createInvoice"
        >
          <div class="invoice-editor__body">
            <section class="invoice-form-section">
              <div class="invoice-form-section__heading">
                <span class="invoice-form-section__number">1</span>
                <div><h3>{{ $t('pages.settings.innvoice.innvoice.invoiceSetup') }}</h3><p>{{ $t('pages.settings.innvoice.innvoice.invoiceSetupHint') }}</p></div>
              </div>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VAutocomplete
                    :model-value="invoiceForm.customer_id || null"
                    :items="customers"
                    item-title="name"
                    item-value="id"
                    :label="$t('pages.settings.innvoice.innvoice.customer')"
                    prepend-inner-icon="tabler-user"
                    :placeholder="$t('pages.settings.innvoice.innvoice.selectCustomer')"
                    clearable
                    @update:model-value="selectCustomer"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                  md="3"
                >
                  <VSelect
                    v-model="invoiceForm.currency_id"
                    :items="currencies.filter(currency => currency.isActive)"
                    :item-title="currency => `${currency.code} · ${currency.name}`"
                    item-value="id"
                    :label="$t('pages.settings.innvoice.innvoice.invoiceCurrency')"
                    prepend-inner-icon="tabler-currency-dollar"
                    @update:model-value="selectInvoiceCurrency"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="4"
                >
                  <VTextField
                    v-model.number="invoiceForm.rate"
                    :label="$t('common.fields.exchangeRate')"
                    prepend-inner-icon="tabler-arrows-exchange"
                    type="number"
                    min="0.00000001"
                    step="any"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="4"
                >
                  <AppDateTimePicker
                    v-model="invoiceForm.issue_date"
                    :label="$t('pages.settings.innvoice.innvoice.issueDate')"
                    :config="{ dateFormat: 'Y-m-d' }"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="4"
                >
                  <AppDateTimePicker
                    v-model="invoiceForm.due_date"
                    :label="$t('common.messages.dueDate')"
                    :config="{ dateFormat: 'Y-m-d' }"
                  />
                </VCol>
              </VRow>

              <div
                v-if="selectedCustomer"
                class="invoice-customer-summary"
              >
                <VIcon
                  icon="tabler-building"
                  size="18"
                  color="primary"
                />
                <div><strong>{{ selectedCustomer.name }}</strong><span>{{ selectedCustomer.company || selectedCustomer.contact }}</span></div>
                <VChip
                  size="small"
                  variant="tonal"
                  color="primary"
                >
                  {{ $t('pages.settings.innvoice.innvoice.availableServices', { count: assignedServices.length }) }}
                </VChip>
              </div>
            </section>

            <section class="invoice-form-section">
              <div class="invoice-form-section__heading invoice-form-section__heading--split">
                <div class="d-flex ga-3">
                  <span class="invoice-form-section__number">2</span><div><h3>{{ $t('pages.settings.innvoice.innvoice.serviceSelection') }}</h3><p>{{ $t('pages.settings.innvoice.innvoice.serviceSelectionHint') }}</p></div>
                </div>
                <VChip
                  v-if="invoiceForm.service_ids.length"
                  size="small"
                  color="success"
                  variant="tonal"
                  prepend-icon="tabler-check"
                >
                  {{ $t('pages.settings.innvoice.innvoice.servicesSelected', { count: invoiceForm.service_ids.length }) }}
                </VChip>
              </div>

              <div
                v-if="!selectedCustomer"
                class="invoice-empty-state"
              >
                <VIcon
                  icon="tabler-user-search"
                  size="30"
                /><strong>{{ $t('pages.settings.innvoice.innvoice.chooseCustomerFirst') }}</strong><span>{{ $t('pages.settings.innvoice.innvoice.chooseCustomerFirstHint') }}</span>
              </div>
              <VAlert
                v-else-if="assignedServices.length === 0"
                type="warning"
                variant="tonal"
              >
                {{ $t('pages.settings.innvoice.innvoice.thisCustomerHasNoAssignedServicesAssignServicesFromTheCustomersPageFir') }}
              </VAlert>
              <div
                v-else
                class="invoice-service-picker"
              >
                <ResponsiveDataTable
                  :headers="serviceHeaders"
                  :items="assignedServices"
                  density="comfortable"
                  hide-default-footer
                >
                  <template #item.selected="{ item }">
                    <VCheckbox
                      :model-value="invoiceForm.service_ids.includes(item.id)"
                      hide-details
                      @update:model-value="toggleService(item.id, $event)"
                    />
                  </template>
                  <template #item.type="{ item }">
                    <div class="font-weight-medium">
                      {{ item.type }}
                    </div><div
                      v-if="item.deadline"
                      class="text-caption text-medium-emphasis"
                    >
                      {{ formatLocalizedDate(item.deadline) }}
                    </div>
                  </template>
                  <template #item.fee="{ item }">
                    {{ item.currency.symbol }}{{ Number(item.fee).toFixed(2) }} {{ item.currency.code }}
                  </template>
                  <template #item.converted="{ item }">
                    <span class="font-weight-bold text-primary">{{ formatMoney(convertServiceFee(item), invoiceCurrency ? { symbol: invoiceCurrency.symbol, shortname: invoiceCurrency.code } : undefined) }}</span>
                  </template>
                  <template #item.commission_employee="{ item }">
                    <div
                      v-if="item.employee"
                      class="d-flex align-center ga-2 py-1"
                    >
                      <VAvatar
                        size="28"
                        color="primary"
                        variant="tonal"
                      >
                        <VIcon
                          icon="tabler-user-check"
                          size="16"
                        />
                      </VAvatar>
                      <div>
                        <div class="text-body-2 font-weight-medium">
                          {{ item.employee.name }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          Rate is resolved from this service capability
                        </div>
                      </div>
                    </div>
                    <div
                      v-else
                      class="text-caption text-warning d-flex align-center ga-1"
                    >
                      <VIcon
                        icon="tabler-alert-circle"
                        size="16"
                      /> Assign a delivery owner in the customer service first
                    </div>
                  </template>
                  <template #item.actions="{ item }">
                    <FullDetailsButton
                      :item="item"
                      :title="item.type"
                    />
                  </template>
                </ResponsiveDataTable>
              </div>
            </section>

            <section class="invoice-form-section">
              <div class="invoice-form-section__heading">
                <span class="invoice-form-section__number">3</span>
                <div><h3>{{ $t('pages.settings.innvoice.innvoice.pricingSummary') }}</h3><p>{{ $t('pages.settings.innvoice.innvoice.pricingSummaryHint') }}</p></div>
              </div>
              <VRow align="stretch">
                <VCol
                  cols="12"
                  md="6"
                >
                  <VRow>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <VTextField
                        v-model.number="invoiceForm.tax_percent"
                        type="number"
                        min="0"
                        max="100"
                        :label="$t('pages.settings.innvoice.innvoice.tax')"
                        prepend-inner-icon="tabler-percentage"
                        suffix="%"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <VTextField
                        v-model.number="invoiceForm.discount"
                        type="number"
                        min="0"
                        :label="$t('pages.settings.innvoice.innvoice.discount')"
                        prepend-inner-icon="tabler-discount-2"
                      />
                    </VCol>
                    <VCol cols="12">
                      <VTextarea
                        v-model="invoiceForm.notes"
                        :label="$t('common.fields.notes')"
                        :placeholder="$t('pages.settings.innvoice.innvoice.notesPlaceholder')"
                        prepend-inner-icon="tabler-notes"
                        rows="2"
                        auto-grow
                      />
                    </VCol>
                  </VRow>
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <div class="invoice-total-card">
                    <div><span>{{ $t('pages.settings.innvoice.innvoice.subtotal') }}</span><strong>{{ formatMoney(formSubtotal, invoiceCurrency ? { symbol: invoiceCurrency.symbol, shortname: invoiceCurrency.code } : undefined) }}</strong></div>
                    <div><span>{{ $t('pages.settings.innvoice.innvoice.taxAmount') }}</span><strong>{{ formatMoney(formTaxAmount, invoiceCurrency ? { symbol: invoiceCurrency.symbol, shortname: invoiceCurrency.code } : undefined) }}</strong></div>
                    <div><span>{{ $t('pages.settings.innvoice.innvoice.discount') }}</span><strong>− {{ formatMoney(invoiceForm.discount, invoiceCurrency ? { symbol: invoiceCurrency.symbol, shortname: invoiceCurrency.code } : undefined) }}</strong></div>
                    <VDivider />
                    <div class="invoice-total-card__grand">
                      <span>{{ $t('pages.settings.innvoice.innvoice.estimatedTotal') }}</span><strong>{{ formatMoney(formTotal, invoiceCurrency ? { symbol: invoiceCurrency.symbol, shortname: invoiceCurrency.code } : undefined) }}</strong>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </section>

            <section class="invoice-form-section">
              <div class="invoice-form-section__heading">
                <span class="invoice-form-section__number">4</span>
                <div><h3>{{ $t('pages.settings.innvoice.innvoice.paymentDetails') }}</h3><p>{{ $t('pages.settings.innvoice.innvoice.paymentDetailsHint') }}</p></div>
              </div>
              <VRow align="stretch">
                <VCol
                  cols="12"
                  md="5"
                >
                  <VSelect
                    v-model="invoiceForm.payment_detail_id"
                    :items="paymentDetails"
                    item-title="method"
                    item-value="id"
                    :label="$t('pages.paymentDetails.method')"
                    prepend-inner-icon="tabler-credit-card"
                    clearable
                    :no-data-text="$t('pages.paymentDetails.empty')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="7"
                >
                  <div
                    class="payment-profile-preview"
                    :class="{ 'payment-profile-preview--empty': !selectedPaymentDetail }"
                  >
                    <div class="payment-profile-preview__icon">
                      <VIcon
                        :icon="selectedPaymentDetail ? 'tabler-receipt-2' : 'tabler-info-circle'"
                        size="22"
                      />
                    </div>
                    <div>
                      <span class="payment-profile-preview__label">{{ $t('pages.paymentDetails.instruction') }}</span>
                      <p>{{ selectedPaymentDetail?.instruction || $t('pages.settings.innvoice.innvoice.paymentDetailsHint') }}</p>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </section>
          </div>

          <footer class="invoice-editor__footer">
            <div class="invoice-editor__footer-total">
              <span>{{ $t('pages.settings.innvoice.innvoice.totalDue') }}</span><strong>{{ formatMoney(formTotal, invoiceCurrency ? { symbol: invoiceCurrency.symbol, shortname: invoiceCurrency.code } : undefined) }}</strong>
            </div>
            <div class="d-flex ga-3 invoice-editor__footer-actions">
              <VBtn
                variant="tonal"
                color="secondary"
                @click="isDialogActive = false"
              >
                {{ $t('common.actions.cancel') }}
              </VBtn>
              <VBtn
                color="primary"
                type="submit"
                prepend-icon="tabler-file-check"
                :loading="isLoading"
                :disabled="!invoiceForm.customer_id || !invoiceForm.currency_id || invoiceForm.service_ids.length === 0"
              >
                {{ $t('common.messages.issueInvoice') }}
              </VBtn>
            </div>
          </footer>
        </VForm>
      </VCard>
    </VDialog>

    <VDialog
      v-model="voidDialog"
      max-width="520"
    >
      <VCard :title="$t('pages.settings.innvoice.innvoice.voidInvoice')">
        <VCardText>
          <VAlert
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            {{ $t('pages.settings.innvoice.innvoice.theInvoiceRemainsPermanentlyRecordedAndWillBeMarkedVoid') }}
          </VAlert>
          <VTextarea
            v-model="voidReason"
            :label="$t('common.fields.reason')"
            rows="3"
            autofocus
          />
        </VCardText>
        <VCardActions class="justify-end pa-6 pt-0">
          <VBtn
            variant="tonal"
            @click="voidDialog = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            :disabled="!voidReason.trim()"
            :loading="isLoading"
            @click="voidInvoice"
          >
            {{ $t('pages.settings.innvoice.innvoice.void') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isInvoicePreviewOpen"
      width="80vw"
      max-width="1280"
      content-class="document-preview-modal"
    >
      <VCard
        flat
        class="document-preview-dialog"
      >
        <VCardTitle
          class="document-preview-dialog__header"
          :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'"
        >
          <div class="document-preview-dialog__heading">
            <VAvatar
              color="primary"
              variant="tonal"
              size="38"
            >
              <VIcon icon="tabler-file-invoice" />
            </VAvatar>
            <div>
              <div class="text-h6 font-weight-bold">
                {{ $t('documents.invoice_template.invoiceTemplate.invoice') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ activePrintInvoice?.invoice_number }}
              </div>
            </div>
          </div>
          <div class="document-preview-dialog__actions">
            <VBtn
              variant="tonal"
              color="secondary"
              @click="isInvoicePreviewOpen = false"
            >
              {{ $t('common.actions.cancel') }}
            </VBtn>
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-printer"
              @click="printPreviewedInvoice"
            >
              {{ $t('common.actions.print') }}
            </VBtn>
          </div>
        </VCardTitle>
        <VCardText class="document-preview-dialog__body">
          <InvoiceTemplate
            v-if="activePrintInvoice"
            ref="invoiceTemplateRef"
            v-bind="formatInvoiceForTemplate(activePrintInvoice)"
          />
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped lang="scss">
.document-preview-dialog {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  height: 90vh;
  background: #edf2f7;
  overflow: hidden;
}

.document-preview-dialog__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  min-block-size: 4.5rem;
  padding: 0.875rem 1.25rem;
  background: rgb(var(--v-theme-surface));
}

.document-preview-dialog__heading {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-inline-size: 0;
}

.document-preview-dialog__actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-inline-start: auto;
  flex-shrink: 0;
}

.document-preview-dialog__body {
  display: grid;
  place-items: start center;
  padding: 2rem;
  overflow: auto;
}

.invoice-editor {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  max-block-size: min(94dvh, 920px);
  border-radius: 1rem !important;
  overflow: hidden;
}

.invoice-editor__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-block-size: 76px;
  padding: 0.875rem 1.25rem;
  border-block-end: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.09), rgb(var(--v-theme-surface)) 48%);
}

.invoice-editor__eyebrow {
  margin-block-end: 0.1rem;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  line-height: 1.2;
}

.invoice-editor__title {
  margin: 0;
  font-size: 1.2rem !important;
  font-weight: 650;
  line-height: 1.35;
}

.invoice-editor__form {
  display: grid;
  min-block-size: 0;
  grid-template-rows: minmax(0, 1fr) auto;
}

.invoice-editor__body {
  min-block-size: 0;
  background: rgb(var(--v-theme-surface));
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-gutter: stable;
}

.invoice-form-section {
  padding: 1.35rem 1.5rem 1.1rem;
  border-block-end: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.invoice-form-section:last-child {
  border-block-end: 0;
}

.invoice-form-section__heading {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin-block-end: 1.1rem;
}

.invoice-form-section__heading--split {
  justify-content: space-between;
}

.invoice-form-section__number {
  display: inline-grid;
  flex: 0 0 28px;
  block-size: 28px;
  border-radius: 0.55rem;
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
  font-size: 0.75rem;
  font-weight: 800;
  place-items: center;
}

.invoice-form-section__heading h3 {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.925rem;
  font-weight: 700;
  line-height: 1.35;
}

.invoice-form-section__heading p {
  margin: 0.2rem 0 0;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  line-height: 1.45;
}

.invoice-customer-summary {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-block-start: 0.25rem;
  padding: 0.7rem 0.85rem;
  border-radius: 0.65rem;
  background: rgba(var(--v-theme-primary), 0.055);
}

.invoice-customer-summary > div {
  display: grid;
  min-inline-size: 0;
  flex: 1;
}

.invoice-customer-summary strong {
  font-size: 0.8125rem;
}

.invoice-customer-summary span {
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.72rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.invoice-empty-state {
  display: grid;
  min-block-size: 145px;
  align-content: center;
  justify-items: center;
  border: 1px dashed rgba(var(--v-theme-primary), 0.28);
  border-radius: 0.75rem;
  background: rgba(var(--v-theme-primary), 0.035);
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  gap: 0.35rem;
  text-align: center;
}

.invoice-empty-state strong {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.875rem;
}

.invoice-empty-state span {
  max-inline-size: 440px;
  font-size: 0.75rem;
}

.invoice-service-picker {
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.75rem;
  overflow: hidden;
}

.invoice-total-card {
  display: grid;
  block-size: 100%;
  min-block-size: 190px;
  align-content: center;
  gap: 0.75rem;
  border-radius: 0.85rem;
  background: linear-gradient(145deg, rgb(var(--v-theme-primary)), rgba(var(--v-theme-primary), 0.82));
  box-shadow: 0 10px 28px rgba(var(--v-theme-primary), 0.22);
  color: rgb(var(--v-theme-on-primary));
  padding: 1.15rem 1.25rem;
}

.invoice-total-card > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  font-size: 0.78rem;
}

.invoice-total-card span {
  opacity: 0.78;
}

.invoice-total-card strong {
  font-size: 0.875rem;
  font-variant-numeric: tabular-nums;
  text-align: end;
}

.invoice-total-card :deep(.v-divider) {
  border-color: currentcolor;
  opacity: 0.22;
}

.invoice-total-card__grand strong {
  font-size: 1.2rem;
}

.payment-profile-preview {
  display: flex;
  min-block-size: 96px;
  block-size: 100%;
  align-items: flex-start;
  gap: 0.8rem;
  border: 1px solid rgba(var(--v-theme-primary), 0.2);
  border-radius: 0.75rem;
  background: rgba(var(--v-theme-primary), 0.045);
  padding: 0.9rem 1rem;
}

.payment-profile-preview--empty {
  border-style: dashed;
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.payment-profile-preview__icon {
  display: grid;
  flex: 0 0 38px;
  block-size: 38px;
  border-radius: 0.65rem;
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
  place-items: center;
}

.payment-profile-preview__label {
  display: block;
  margin-block-end: 0.25rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.035em;
  text-transform: uppercase;
}

.payment-profile-preview p {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.8rem;
  line-height: 1.65;
  white-space: pre-line;
}

.invoice-editor__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  min-block-size: 76px;
  padding: 0.85rem 1.25rem;
  border-block-start: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 -8px 24px rgba(20, 18, 50, 0.04);
}

.invoice-editor__footer-total {
  display: grid;
}

.invoice-editor__footer-total span {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.68rem;
  font-weight: 600;
}

.invoice-editor__footer-total strong {
  color: rgb(var(--v-theme-primary));
  font-size: 1.05rem;
  font-variant-numeric: tabular-nums;
}

.min-w-0 {
  min-inline-size: 0;
}

@media (max-width: 599px) {
  .invoice-editor {
    max-block-size: calc(100dvh - 1.5rem);
    border-radius: 0.75rem !important;
  }

  .invoice-editor__header {
    min-block-size: 66px;
    padding: 0.75rem 0.875rem;
  }

  .invoice-form-section {
    padding: 1rem 0.875rem 0.8rem;
  }

  .invoice-form-section__heading--split {
    flex-direction: column;
  }

  .invoice-customer-summary {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .invoice-editor__footer {
    align-items: stretch;
    flex-direction: column;
    padding: 0.75rem 0.875rem;
  }

  .invoice-editor__footer-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .invoice-editor__footer-actions > .v-btn {
    flex: 1 1 0;
  }
}
</style>
