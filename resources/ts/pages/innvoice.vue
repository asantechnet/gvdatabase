<script setup lang="ts">
import CustomerApi, { type Customer, type CustomerService } from '@/api/customer'
import CurrencyApi, { type Currency } from '@/api/currency'
import InvoiceApi, { type Invoice } from '@/api/invoice'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import InvoiceTemplate from './templates/invoice_template.vue'

const route = useRoute()
const toast = useToast()

const invoices = ref<Invoice[]>([])
const customers = ref<Customer[]>([])
const currencies = ref<Currency[]>([])
const isLoading = ref(false)
const searchQuery = ref('')
const selectedTypeFilter = ref<string | null>(null)
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
  service_ids: [] as number[],
  tax_percent: 0,
  discount: 0,
  notes: '',
  payment_method: '',
  payment_instructions: '',
})

const invoiceForm = ref(defaultForm())

const headers = [
  { title: 'Invoice Number', key: 'invoice_number' },
  { title: 'Customer', key: 'deptor' },
  { title: 'Type', key: 'class-type' },
  { title: 'Currency', key: 'currency.shortname' },
  { title: 'Total', key: 'total_amount' },
  { title: 'Balance', key: 'balance' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' as const },
]

const serviceHeaders = [
  { title: 'Include', key: 'selected', sortable: false },
  { title: 'Service', key: 'type' },
  { title: 'Original Fee', key: 'fee' },
  { title: 'Invoice Amount', key: 'converted' },
  { title: 'Deadline', key: 'deadline' },
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
    toast.success('Invoice issued')
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
    toast.success('Invoice voided without deleting its record')
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
  dateOfIssue: invoice.created_at ? new Date(invoice.created_at).toLocaleDateString() : '',
  dueDate: '',
  client: {
    name: invoice.deptor,
    addressLines: [invoice.customer.company, invoice.customer.contact].filter(Boolean) as string[],
  },
  sender: {
    brandName: 'Our Company',
    addressLines: [],
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
    await Promise.all([fetchInvoices(), fetchFormOptions()])

    const customerId = Number(route.query.customer)
    if (customerId && customers.value.some(customer => customer.id === customerId))
      openCreateDialog(customerId)
  }
  catch (error) {
    toast.error('Failed to load invoicing data')
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
              placeholder="Search invoice number or customer..." density="compact" hide-details clearable />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <VSelect v-model="selectedTypeFilter" :items="invoiceTypes" label="Invoice type" density="compact"
              hide-details clearable />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VBtn color="primary" block @click="openCreateDialog()">
              <VIcon icon="tabler-file-invoice" class="me-1" /> Issue Invoice
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
          <VChip size="small" variant="tonal" :color="getStatusColor(item.status)">{{ item.status }}</VChip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex justify-end gap-1">
            <VBtn icon variant="text" size="small" color="info" @click="triggerPrint(item)">
              <VIcon icon="tabler-printer" />
              <VTooltip activator="parent">Print</VTooltip>
            </VBtn>
            <VBtn v-if="item.status !== 'Void'" icon variant="text" size="small" color="error"
              @click="openVoidDialog(item)">
              <VIcon icon="tabler-circle-x" />
              <VTooltip activator="parent">Void invoice</VTooltip>
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VDialog v-model="isDialogActive" max-width="900" scrollable>
      <VCard title="Issue Invoice">
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <VSelect :model-value="invoiceForm.customer_id || null" :items="customers" item-title="name"
                item-value="id" label="Customer" @update:model-value="selectCustomer" />
            </VCol>
            <VCol cols="12" md="3">
              <VSelect v-model="invoiceForm.class_type" :items="invoiceTypes" label="Invoice type" />
            </VCol>
            <VCol cols="12" md="3">
              <VSelect v-model="invoiceForm.currency_id" :items="currencies.filter(currency => currency.isActive)"
                :item-title="currency => `${currency.code} - ${currency.name}`" item-value="id"
                label="Invoice currency" />
            </VCol>

            <VCol cols="12">
              <VAlert v-if="selectedCustomer && assignedServices.length === 0" type="warning" variant="tonal">
                This customer has no assigned services. Assign services from the customers page first.
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
              <VTextField v-model.number="invoiceForm.tax_percent" type="number" min="0" max="100" label="Tax (%)" />
            </VCol>
            <VCol cols="12" sm="6" md="4">
              <VTextField v-model.number="invoiceForm.discount" type="number" min="0" label="Discount" />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField :model-value="formatMoney(formTotal, invoiceCurrency ? { symbol: invoiceCurrency.symbol, shortname: invoiceCurrency.code } : undefined)"
                label="Estimated total" readonly />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="invoiceForm.notes" label="Notes" rows="2" />
            </VCol>
            <VCol cols="12" md="6">
              <VCombobox v-model="invoiceForm.payment_method" :items="paymentMethods"
                label="Payment method" clearable />
            </VCol>
            <VCol cols="12" md="6">
              <VTextarea v-model="invoiceForm.payment_instructions"
                label="Payment instructions (one per line)" rows="2" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="justify-end pa-6 pt-0">
          <VBtn variant="tonal" color="secondary" @click="isDialogActive = false">Cancel</VBtn>
          <VBtn color="primary" :loading="isLoading"
            :disabled="!invoiceForm.customer_id || !invoiceForm.currency_id || invoiceForm.service_ids.length === 0"
            @click="createInvoice">Issue Invoice</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="voidDialog" max-width="520">
      <VCard title="Void Invoice">
        <VCardText>
          <VAlert type="warning" variant="tonal" class="mb-4">
            The invoice remains permanently recorded and will be marked Void.
          </VAlert>
          <VTextarea v-model="voidReason" label="Reason" rows="3" autofocus />
        </VCardText>
        <VCardActions class="justify-end pa-6 pt-0">
          <VBtn variant="tonal" @click="voidDialog = false">Cancel</VBtn>
          <VBtn color="error" :disabled="!voidReason.trim()" :loading="isLoading" @click="voidInvoice">Void</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <div style="position: fixed; left: -9999px; top: -9999px; width: 100vw; visibility: hidden; pointer-events: none;">
      <InvoiceTemplate v-if="activePrintInvoice" ref="invoiceTemplateRef"
        v-bind="formatInvoiceForTemplate(activePrintInvoice)" />
    </div>
  </div>
</template>
