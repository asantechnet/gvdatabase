<script setup lang="ts">
import CustomerApi, { type Customer } from '@/api/customer'
import { computed, onMounted, ref, watch } from 'vue'

interface ContractFormData {
  template_id: number
  status: 'Active' | 'Expired' | 'Pending'
  start_date: string
  end_date: string | null
  counterparty: string
  counterparty_company: string | null
  counterparty_email: string | null
  counterparty_contact: string | null
  counterparty_address: string | null
}

interface ContractTemplate {
  id: number
  name: string
  type: string
}

const props = defineProps<{
  contract?: {
    id: number
    contract: string
    status: 'Active' | 'Expired' | 'Pending'
    start_date: string
    end_date?: string | null
    counterparty: string
    counterparty_company?: string | null
    counterparty_email?: string | null
    counterparty_contact?: string | null
    counterparty_address?: string | null
    template_id: number
  }
  templates: ContractTemplate[]
}>()

const emit = defineEmits(['submit', 'cancel'])
const customers = ref<Customer[]>([])
const loadingCustomers = ref(false)
const customerOptions = computed(() => customers.value.map(customer => ({
  title: customer.company ? `${customer.name} — ${customer.company}` : customer.name,
  value: customer.name,
})))

const form = ref<ContractFormData>({
  template_id: props.templates[0]?.id || 0,
  status: props.contract?.status || 'Active',
  start_date: props.contract?.start_date || new Date().toISOString().substr(0, 10),
  end_date: props.contract?.end_date || null,
  counterparty: props.contract?.counterparty || '',
  counterparty_company: props.contract?.counterparty_company || null,
  counterparty_email: props.contract?.counterparty_email || null,
  counterparty_contact: props.contract?.counterparty_contact || null,
  counterparty_address: props.contract?.counterparty_address || null,
})

const statusOptions = [
  { title: translate('common.statuses.active'), value: 'Active' },
  { title: translate('common.statuses.expired'), value: 'Expired' },
  { title: translate('common.statuses.pending'), value: 'Pending' }
]

onMounted(async () => {
  loadingCustomers.value = true
  try {
    const response = await CustomerApi.getAll()
    if (response.success)
      customers.value = response.data
  } finally {
    loadingCustomers.value = false
  }
})

watch(() => props.contract, (newVal) => {
  if (newVal) {
    form.value = {
      template_id: newVal.template_id || props.templates[0]?.id || 0,
      status: newVal.status,
      start_date: newVal.start_date,
      end_date: newVal.end_date || null,
      counterparty: newVal.counterparty,
      counterparty_company: newVal.counterparty_company || null,
      counterparty_email: newVal.counterparty_email || null,
      counterparty_contact: newVal.counterparty_contact || null,
      counterparty_address: newVal.counterparty_address || null,
    }
  }
}, { immediate: true })

watch(() => props.templates, templates => {
  if (!form.value.template_id && templates[0])
    form.value.template_id = templates[0].id
}, { deep: true })

watch(() => form.value.counterparty, name => {
  const customer = customers.value.find(item => item.name === name)
  if (!customer) return
  form.value.counterparty_company = customer.company || null
  form.value.counterparty_email = customer.email || null
  form.value.counterparty_contact = customer.contact || null
  form.value.counterparty_address = customer.address || null
})

const submit = () => {
  emit('submit', form.value)
}

const cancel = () => {
  emit('cancel')
}
</script>

<template>
  <VForm @submit.prevent="submit">
    <VRow>
      <VCol cols="12">
        <VSelect
          v-model="form.template_id"
          :items="templates"
          item-title="name"
          item-value="id"
          :label="$t('pages.contracts.contractform.contractform.contractTemplate')"
          :rules="[v => !!v || 'Template is required']"
          required
        />
      </VCol>

      <VCol cols="12">
        <VAutocomplete
          v-model="form.counterparty"
          :items="customerOptions"
          :label="$t('pages.contracts.contractform.contractform.counterparty')"
          :placeholder="$t('pages.contracts.contractform.contractform.selectACustomer')"
          prepend-inner-icon="tabler-user"
          :loading="loadingCustomers"
          :rules="[v => !!v || 'Counterparty is required']"
          :no-data-text="$t('pages.contracts.contractform.contractform.noCustomersFound')"
          required
        />
      </VCol>

      <VCol cols="12" md="6">
        <VSelect
          v-model="form.status"
          :items="statusOptions"
          :label="$t('common.fields.status')"
          :rules="[v => !!v || 'Status is required']"
          required
        />
      </VCol>
      <VCol cols="12" md="6"><VTextField v-model="form.end_date" label="End date" type="date" /></VCol>
      <VCol cols="12" md="6"><VTextField v-model="form.counterparty_company" label="Counterparty company" /></VCol>
      <VCol cols="12" md="6"><VTextField v-model="form.counterparty_email" label="Counterparty email" type="email" /></VCol>
      <VCol cols="12" md="6"><VTextField v-model="form.counterparty_contact" label="Counterparty contact" /></VCol>
      <VCol cols="12"><VTextarea v-model="form.counterparty_address" label="Counterparty address" rows="2" /></VCol>

      <VCol cols="12" md="6">
        <VTextField
          v-model="form.start_date"
          :label="$t('common.fields.startDate2')"
          type="date"
          :rules="[v => !!v || 'Start date is required']"
          required
        />
      </VCol>
    </VRow>

    <VCardActions class="justify-end pt-4">
      <VBtn variant="outlined" color="secondary" @click="cancel">
        {{ $t('common.actions.cancel') }}
      </VBtn>
      <VBtn type="submit" color="primary">
        {{ $t('common.actions.save') }}
      </VBtn>
    </VCardActions>
  </VForm>
</template>
