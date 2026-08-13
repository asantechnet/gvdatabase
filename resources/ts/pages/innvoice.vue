<script setup lang="ts">
import CustomerApi, { type Customer, type CustomerService } from '@/api/customer'
import CurrencyApi, { type Currency } from '@/api/currency'
import InvoiceApi, { type Invoice } from '@/api/invoice'
import SettingApi, { type Setting } from '@/api/setting'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import InvoiceTemplate from './templates/invoice_template.vue'

const route = useRoute()
const toast = useToast()

const invoices = ref<Invoice[]>([])
const customers = ref<Customer[]>([])
const currencies = ref<Currency[]>([])
const companySetting = ref<Setting | null>(null)
const isLoading = ref(false)
const searchQuery = ref('')
const selectedTypeFilter = ref<Invoice['class-type'] | null>(null)
const isDialogActive = ref(false)
const voidDialog = ref(false)
const invoiceToVoid = ref<Invoice | null>(null)
const voidReason = ref('')
const activePrintInvoice = ref<Invoice | null>(null)
const invoiceTemplateRef = ref<InstanceType<typeof InvoiceTemplate> | null>(null)

const invoiceTypes: Invoice['class-type'][] = ['Standard', 'Proforma', 'Credit Note', 'Debit Note']
const paymentMethods = ['Bank Transfer', 'Cash', 'Card', 'Cheque', 'Mobile Payment']

const defaultForm = () => ({
  customer_id: 0,
  currency_id: 0,
  class_type: 'Standard' as Invoice['class-type'],
  issue_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  service_ids: [] as number[],
  tax_percent: 0,
  discount: 0,
  notes: '',
  payment_method: '',
  payment_instructions: '',
})

const invoiceForm = ref(defaultForm())

const headers = [
  { title: translate('pages.settings.innvoice.innvoice.invoiceNumber'), key: 'invoice_number' },
  { title: translate('pages.settings.innvoice.innvoice.customer'), key: 'deptor' },
  { title: translate('common.fields.type'), key: 'class-type' },
  { title: translate('common.fields.currency'), key: 'currency.shortname' },
  { title: translate('common.messages.total'), key: 'total_amount' },
  { title: translate('common.messages.balance'), key: 'balance' },
  { title: translate('common.fields.status'), key: 'status' },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
]

const serviceHeaders = [
  { title: translate('pages.settings.innvoice.innvoice.include'), key: 'selected', sortable: false },
  { title: translate('common.messages.service'), key: 'type' },
  { title: translate('pages.settings.innvoice.innvoice.originalFee'), key: 'fee' },
  { title: translate('pages.settings.innvoice.innvoice.invoiceAmount'), key: 'converted' },
  { title: translate('common.messages.deadline'), key: 'deadline' },
]

const selectedCustomer = computed(() =>
  customers.value.find(customer => customer.id === invoiceForm.value.customer_id) ?? null,
)

const invoiceCurrency = computed(() =>
  currencies.value.find(currency => Number(currency.id) === Number(invoiceForm.value.currency_id)) ?? null,
)

const assignedServices = computed(() => selectedCustomer.value?.services ?? [])

const selectedServices = computed(() =>
  assignedServices.value.filter(service => invoiceForm.value.service_ids.includes(service.service_id)),
)

const convertServiceFee = (service: CustomerService) => {
  const sourceRate = Number(service.currency.rateToBase)
  const targetRate = Number(invoiceCurrency.value?.rateToBase)

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
  const matchesType = !selectedTypeFilter.value || invoice['class-type'] === selectedTypeFilter.value

  return matchesSearch && matchesType
}))

const getStatusColor = (status: Invoice['status']) => ({
  Issued: 'primary',
  'Partially Paid': 'warning',
  Paid: 'success',
  Overdue: 'error',
  Void: 'secondary',
}[status])

const formatMoney = (amount: number, currency?: { symbol?: string; shortname?: string }) =>
  `${currency?.symbol || ''}${Number(amount || 0).toFixed(2)} ${currency?.shortname || ''}`.trim()

const fetchInvoices = async () => {
  invoices.value = await InvoiceApi.getAll()
}

const fetchFormOptions = async () => {
  const [customerResponse, currencyResponse] = await Promise.all([
    CustomerApi.getAll(),
    CurrencyApi.getAll(),
  ])

  if (!customerResponse.success)
    throw new Error(customerResponse.message || 'Failed to load customers')

  customers.value = customerResponse.data
  currencies.value = currencyResponse.data
}

const selectCustomer = (customerId: number) => {
  invoiceForm.value.customer_id = Number(customerId)
  invoiceForm.value.service_ids = []
}

const openCreateDialog = (customerId?: number) => {
  const baseCurrency = currencies.value.find(currency => currency.isBase && currency.isActive)
    ?? currencies.value.find(currency => currency.isActive)

  invoiceForm.value = {
    ...defaultForm(),
    customer_id: Number(customerId || 0),
    currency_id: Number(baseCurrency?.id || 0),
  }
  isDialogActive.value = true
}

const toggleService = (serviceId: number, selected: boolean | null) => {
  if (selected) {
    if (!invoiceForm.value.service_ids.includes(serviceId))
      invoiceForm.value.service_ids.push(serviceId)
  }
  else {
    invoiceForm.value.service_ids = invoiceForm.value.service_ids.filter(id => id !== serviceId)
  }
}

const createInvoice = async () => {
  try {
    isLoading.value = true
    await InvoiceApi.create({
      customer_id: invoiceForm.value.customer_id,
      currency_id: invoiceForm.value.currency_id,
      class_type: invoiceForm.value.class_type,
      issue_date: invoiceForm.value.issue_date || undefined,
      due_date: invoiceForm.value.due_date || undefined,
      services: invoiceForm.value.service_ids.map(id => ({ id, quantity: 1 })),
      tax_percent: Number(invoiceForm.value.tax_percent),
      discount: Number(invoiceForm.value.discount),
      notes: invoiceForm.value.notes,
      payment_method: invoiceForm.value.payment_method || undefined,
      payment_instructions: invoiceForm.value.payment_instructions
        .split('\n')
        .map(line => line.trim())
        .filter(Boolean),
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
    brandName: companySetting.value?.original_company_name || companySetting.value?.website_title || '',
    logo: companySetting.value?.website_logo_url || undefined,
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
  })),
  discount: invoice.discount,
  taxRate: invoice.tax_percent,
  notes: invoice.notes,
  paymentMethod: invoice.payment_method || '',
  paymentInstructions: invoice.payment_instructions,
})

const triggerPrint = async (invoice: Invoice) => {
  activePrintInvoice.value = invoice
  await nextTick()
  await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))
  invoiceTemplateRef.value?.print()
}

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
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="5">
            <VTextField v-model="searchQuery" prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.settings.innvoice.innvoice.searchInvoiceNumberOrCustomer')" density="compact" hide-details clearable />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <VSelect v-model="selectedTypeFilter" :items="invoiceTypes" :label="$t('pages.settings.innvoice.innvoice.invoiceType')" density="compact"
              hide-details clearable />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VBtn color="primary" block @click="openCreateDialog()">
              <VIcon icon="tabler-file-invoice" class="me-1" /> {{ $t('common.messages.issueInvoice') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VDataTable :headers="headers" :items="filteredInvoices" :loading="isLoading" class="text-no-wrap">
        <template #[`item.class-type`]="{ item }">
          <VChip size="small" variant="tonal" color="primary">{{ item['class-type'] }}</VChip>
        </template>
        <template #item.currency.shortname="{ item }">{{ item.currency.shortname }}</template>
        <template #item.total_amount="{ item }">{{ formatMoney(item.total_amount, item.currency) }}</template>
        <template #item.balance="{ item }">{{ formatMoney(item.balance, item.currency) }}</template>
        <template #item.status="{ item }">
          <VChip size="small" variant="tonal" :color="getStatusColor(item.status)">{{ translateBackendValue(item.status) }}</VChip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex justify-end gap-1">
            <VBtn icon variant="text" size="small" color="info" @click="triggerPrint(item)">
              <VIcon icon="tabler-printer" />
              <VTooltip activator="parent">{{ $t('common.actions.print') }}</VTooltip>
            </VBtn>
            <VBtn v-if="item.status !== 'Void'" icon variant="text" size="small" color="error"
              @click="openVoidDialog(item)">
              <VIcon icon="tabler-circle-x" />
              <VTooltip activator="parent">{{ $t('pages.settings.innvoice.innvoice.voidInvoice2') }}</VTooltip>
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VDialog v-model="isDialogActive" max-width="900" scrollable>
      <VCard :title="$t('common.messages.issueInvoice')">
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <VSelect :model-value="invoiceForm.customer_id || null" :items="customers" item-title="name"
                item-value="id" :label="$t('pages.settings.innvoice.innvoice.customer')" @update:model-value="selectCustomer" />
            </VCol>
            <VCol cols="12" md="3">
              <VSelect v-model="invoiceForm.class_type" :items="invoiceTypes" :label="$t('pages.settings.innvoice.innvoice.invoiceType2')" />
            </VCol>
            <VCol cols="12" md="3">
              <VSelect v-model="invoiceForm.currency_id" :items="currencies.filter(currency => currency.isActive)"
                :item-title="currency => `${currency.code} - ${currency.name}`" item-value="id"
                :label="$t('pages.settings.innvoice.innvoice.invoiceCurrency')" />
            </VCol>
            <VCol cols="12" md="6"><AppDateTimePicker v-model="invoiceForm.issue_date" label="Issue date" :config="{ dateFormat: 'Y-m-d' }" /></VCol>
            <VCol cols="12" md="6"><AppDateTimePicker v-model="invoiceForm.due_date" label="Due date" :config="{ dateFormat: 'Y-m-d' }" /></VCol>

            <VCol cols="12">
              <VAlert v-if="selectedCustomer && assignedServices.length === 0" type="warning" variant="tonal">
                {{ $t('pages.settings.innvoice.innvoice.thisCustomerHasNoAssignedServicesAssignServicesFromTheCustomersPageFir') }}
              </VAlert>
              <VDataTable v-else-if="selectedCustomer" :headers="serviceHeaders" :items="assignedServices"
                density="compact" hide-default-footer>
                <template #item.selected="{ item }">
                  <VCheckbox :model-value="invoiceForm.service_ids.includes(item.service_id)" hide-details
                    @update:model-value="toggleService(item.service_id, $event)" />
                </template>
                <template #item.fee="{ item }">
                  {{ item.currency.symbol }}{{ Number(item.fee).toFixed(2) }} {{ item.currency.code }}
                </template>
                <template #item.converted="{ item }">
                  {{ formatMoney(convertServiceFee(item), invoiceCurrency ? { symbol: invoiceCurrency.symbol, shortname: invoiceCurrency.code } : undefined) }}
                </template>
              </VDataTable>
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <VTextField v-model.number="invoiceForm.tax_percent" type="number" min="0" max="100" :label="$t('pages.settings.innvoice.innvoice.tax')" />
            </VCol>
            <VCol cols="12" sm="6" md="4">
              <VTextField v-model.number="invoiceForm.discount" type="number" min="0" :label="$t('pages.settings.innvoice.innvoice.discount')" />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField :model-value="formatMoney(formTotal, invoiceCurrency ? { symbol: invoiceCurrency.symbol, shortname: invoiceCurrency.code } : undefined)"
                :label="$t('pages.settings.innvoice.innvoice.estimatedTotal')" readonly />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="invoiceForm.notes" :label="$t('common.fields.notes')" rows="2" />
            </VCol>
            <VCol cols="12" md="6">
              <VCombobox v-model="invoiceForm.payment_method" :items="paymentMethods"
                :label="$t('pages.settings.innvoice.innvoice.paymentMethod')" clearable />
            </VCol>
            <VCol cols="12" md="6">
              <VTextarea v-model="invoiceForm.payment_instructions"
                :label="$t('pages.settings.innvoice.innvoice.paymentInstructionsOnePerLine')" rows="2" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="justify-end pa-6 pt-0">
          <VBtn variant="tonal" color="secondary" @click="isDialogActive = false">{{ $t('common.actions.cancel') }}</VBtn>
          <VBtn color="primary" :loading="isLoading"
            :disabled="!invoiceForm.customer_id || !invoiceForm.currency_id || invoiceForm.service_ids.length === 0"
            @click="createInvoice">{{ $t('common.messages.issueInvoice') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="voidDialog" max-width="520">
      <VCard :title="$t('pages.settings.innvoice.innvoice.voidInvoice')">
        <VCardText>
          <VAlert type="warning" variant="tonal" class="mb-4">
            {{ $t('pages.settings.innvoice.innvoice.theInvoiceRemainsPermanentlyRecordedAndWillBeMarkedVoid') }}
          </VAlert>
          <VTextarea v-model="voidReason" :label="$t('common.fields.reason')" rows="3" autofocus />
        </VCardText>
        <VCardActions class="justify-end pa-6 pt-0">
          <VBtn variant="tonal" @click="voidDialog = false">{{ $t('common.actions.cancel') }}</VBtn>
          <VBtn color="error" :disabled="!voidReason.trim()" :loading="isLoading" @click="voidInvoice">{{ $t('pages.settings.innvoice.innvoice.void') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <div style="position: fixed; left: -9999px; top: -9999px; width: 100vw; visibility: hidden; pointer-events: none;">
      <InvoiceTemplate v-if="activePrintInvoice" ref="invoiceTemplateRef"
        v-bind="formatInvoiceForTemplate(activePrintInvoice)" />
    </div>
  </div>
</template>
