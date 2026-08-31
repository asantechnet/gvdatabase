<script setup lang="ts">
import CompanyApi, { type Company } from '@/api/company'
import ContractTypeApi, { type ContractType } from '@/api/contractType'
import type { ContractStatus } from '@/api/contracts'
import CustomerApi, { type Customer } from '@/api/customer'
import EmployeeApi, { type Employee } from '@/api/employee'
import { computed, onMounted, ref, watch } from 'vue'
import CounterpartyCard, { type CounterpartyDetails } from '@/components/contract/CounterpartyCard.vue'

export interface ContractFormPayload {
  template_id: number | null
  contract_type_id: number | null
  employee_id: number | null
  customer_id: number | null
  company_id: number | null
  is_custom: boolean
  name: string
  cover_text: string | null
  content?: string
  status: ContractStatus
  start_date: string
  end_date: string | null
  duration_months: number | null
  company_signatory_name: string | null
}

interface TemplateOption {
  id: number
  name: string
  type_slug?: string | null
  cover_text?: string | null
}

const props = defineProps<{
  contract?: {
    id: number
    contract: string
    status: ContractStatus
    start_date: string | null
    end_date?: string | null
    duration_months?: number | null
    company_signatory_name?: string | null
    template_id: number | null
    contract_type_id?: number | null
    employee_id?: number | null
    customer_id?: number | null
    company_id?: number | null
    is_custom?: boolean
    type_slug?: string | null
    cover_text?: string | null
    content?: string | null
  }
  templates: TemplateOption[]
  audience?: 'customers' | 'employees' | 'companies' | 'other'
}>()

const emit = defineEmits<{
  (e: 'submit', payload: ContractFormPayload): void
  (e: 'cancel'): void
}>()

const formRef = ref()
const customers = ref<Customer[]>([])
const employees = ref<Employee[]>([])
const companies = ref<Company[]>([])
const contractTypes = ref<ContractType[]>([])
const loadingEntities = ref(true)

const form = ref({
  template_id: (props.contract?.template_id ?? null) as number | null,
  contract_type_id: (props.contract?.contract_type_id ?? null) as number | null,
  employee_id: (props.contract?.employee_id ?? null) as number | null,
  customer_id: (props.contract?.customer_id ?? null) as number | null,
  company_id: (props.contract?.company_id ?? null) as number | null,
  is_custom: Boolean(props.contract?.is_custom),
  name: props.contract?.contract || '',
  cover_text: (props.contract?.cover_text || null) as string | null,
  content: props.contract?.content || '',
  status: (props.contract?.status || 'active') as ContractStatus,
  start_date: props.contract?.start_date || new Date().toISOString().slice(0, 10),
  end_date: (props.contract?.end_date || null) as string | null,
  duration_months: props.contract?.duration_months ?? null,
  company_signatory_name: props.contract?.company_signatory_name ?? null,
})

// ---------------------------------------------------------------------
// Context metadata drives the whole form; no scattered if/else chains.
// ---------------------------------------------------------------------
const CONTEXT_BY_SLUG: Record<string, 'employee' | 'customer' | 'company' | null> = {
  employment: 'employee',
  customer: 'customer',
  company: 'company',
}

const selectedTemplate = computed(() =>
  props.templates.find(template => template.id === form.value.template_id) ?? null)

const selectedType = computed(() =>
  contractTypes.value.find(type => type.id === form.value.contract_type_id) ?? null)

const activeSlug = computed<string | null>(() => {
  if (!form.value.is_custom)
    return selectedTemplate.value?.type_slug ?? null

  return selectedType.value?.slug ?? null
})

const context = computed<'employee' | 'customer' | 'company' | null>(() => {
  const slug = activeSlug.value
  return slug ? CONTEXT_BY_SLUG[slug] ?? null : null
})

const statusOptions = [
  { title: 'Draft', value: 'draft' },
  { title: 'Active', value: 'active' },
  { title: 'Expired', value: 'expired' },
  { title: 'Terminated', value: 'terminated' },
]

const visibleTemplates = computed(() => {
  if (props.audience === 'employees')
    return props.templates.filter(template => template.type_slug === 'employment')

  if (props.audience === 'customers')
    return props.templates.filter(template => template.type_slug === 'customer')

  if (props.audience === 'companies')
    return props.templates.filter(template => template.type_slug === 'company')

  return props.templates
})

const customerOptions = computed(() => customers.value.map(customer => ({
  title: customer.company ? `${customer.name} — ${customer.company}` : customer.name,
  value: customer.id,
})))

const employeeOptions = computed(() => employees.value.map(employee => ({
  title: employee.position?.name ? `${employee.name} — ${employee.position.name}` : employee.name,
  value: employee.id,
})))

const companyOptions = computed(() => companies.value.map(company => ({
  title: company.trade_name ? `${company.legal_name} (${company.trade_name})` : company.legal_name,
  value: company.id,
})))

const selectedEmployee = computed(() => employees.value.find(item => item.id === form.value.employee_id) ?? null)
const selectedCustomer = computed(() => customers.value.find(item => item.id === form.value.customer_id) ?? null)
const selectedCompany = computed(() => companies.value.find(item => item.id === form.value.company_id) ?? null)

const counterpartyDetails = computed<CounterpartyDetails | null>(() => {
  switch (context.value) {
    case 'employee':
      return selectedEmployee.value
        ? {
            kind: 'employee',
            name: selectedEmployee.value.name,
            subtitle: selectedEmployee.value.position?.name || null,
            phone: selectedEmployee.value.contact,
            email: selectedEmployee.value.email,
            address: selectedEmployee.value.addr,
          }
        : null
    case 'customer':
      return selectedCustomer.value
        ? {
            kind: 'customer',
            name: selectedCustomer.value.name,
            subtitle: selectedCustomer.value.company || null,
            taxNumber: selectedCustomer.value.tax_number,
            phone: selectedCustomer.value.contact,
            email: selectedCustomer.value.email,
            address: selectedCustomer.value.address,
          }
        : null
    case 'company':
      return selectedCompany.value
        ? {
            kind: 'company',
            name: selectedCompany.value.legal_name,
            subtitle: [selectedCompany.value.legal_form, selectedCompany.value.industry].filter(Boolean).join(' · ') || null,
            registrationNumber: selectedCompany.value.registration_number,
            taxNumber: selectedCompany.value.tax_number,
            phone: selectedCompany.value.representative_phone || selectedCompany.value.phone,
            email: selectedCompany.value.representative_email || selectedCompany.value.email,
            address: [
              selectedCompany.value.address_line, selectedCompany.value.city,
              selectedCompany.value.province, selectedCompany.value.country,
            ].filter(Boolean).join(', ') || null,
            representativeName: selectedCompany.value.representative_name,
            representativeTitle: selectedCompany.value.representative_title,
          }
        : null
    default:
      return null
  }
})

onMounted(async () => {
  try {
    const [customerResult, employeeResult, companyResult, typeList] = await Promise.allSettled([
      CustomerApi.getAll(),
      EmployeeApi.getOptions(),
      CompanyApi.getAll(),
      ContractTypeApi.getAll({ include_inactive: true }),
    ])
    if (customerResult.status === 'fulfilled' && customerResult.value.success)
      customers.value = customerResult.value.data
    if (employeeResult.status === 'fulfilled')
      employees.value = employeeResult.value
    if (companyResult.status === 'fulfilled')
      companies.value = companyResult.value.filter(company => company.is_active !== false)
    if (typeList.status === 'fulfilled')
      contractTypes.value = typeList.value
  } finally {
    loadingEntities.value = false
  }
})

watch(() => props.templates, () => {
  if (!form.value.template_id && !form.value.is_custom && visibleTemplates.value[0])
    form.value.template_id = visibleTemplates.value[0].id
}, { immediate: true })

watch(() => form.value.is_custom, isCustom => {
  if (isCustom) {
    form.value.template_id = null
    if (!form.value.contract_type_id) {
      const fallback = contractTypes.value.find(type => type.slug === 'customer')
        ?? contractTypes.value[0]
      form.value.contract_type_id = fallback?.id ?? null
    }
  } else {
    form.value.contract_type_id = null
    if (!form.value.template_id && visibleTemplates.value[0])
      form.value.template_id = visibleTemplates.value[0].id
  }
})

// Changing context clears entity-specific selections so employment-only
// data never silently rides along with another contract type.
watch(context, currentContext => {
  // Preserve the appropriate counterparty for an existing contract or one
  // opened from a company's action button; clear incompatible IDs.
  form.value.employee_id = currentContext === 'employee' ? (props.contract?.employee_id ?? form.value.employee_id) : null
  form.value.customer_id = currentContext === 'customer' ? (props.contract?.customer_id ?? form.value.customer_id) : null
  form.value.company_id = currentContext === 'company' ? (props.contract?.company_id ?? form.value.company_id) : null
})

watch([() => form.value.start_date, () => form.value.duration_months], ([startDate, duration]) => {
  if (!startDate || !duration) {
    form.value.end_date = null
    return
  }

  const [year, month, day] = startDate.split('-').map(Number)
  const lastDay = new Date(Date.UTC(year, month - 1 + Number(duration) + 1, 0)).getUTCDate()
  form.value.end_date = new Date(Date.UTC(year, month - 1 + Number(duration), Math.min(day, lastDay))).toISOString().slice(0, 10)
}, { immediate: true })

const onTypesChanged = (types: ContractType[]) => {
  contractTypes.value = types
}

const submit = async () => {
  const validation = await formRef.value?.validate()
  if (validation?.valid === false) return

  emit('submit', {
    ...form.value,
    // Template contracts inherit their display name from the template. Keep
    // it in the payload as well so older client validators cannot reject an
    // otherwise valid template-based contract because name is blank.
    name: form.value.is_custom ? form.value.name : (selectedTemplate.value?.name || form.value.name),
    // The backend derives the type from the template for template contracts.
    contract_type_id: form.value.is_custom ? form.value.contract_type_id : null,
    // Template contracts take their content from the selected template. Do
    // not send an empty string here: Laravel converts it to null and then
    // rejects it against the string rule before the template can be applied.
    content: form.value.is_custom ? form.value.content : undefined,
  })
}

defineExpose({ submit })
</script>

<template>
  <VForm ref="formRef" class="app-form" @submit.prevent="submit">
    <VRow>
      <VCol cols="12">
        <p class="app-form__section">Contract setup</p>
        <p class="app-form__section-help">
          Pick a template first — its contract type decides which counterparty record is required and which details
          flow into the document automatically.
        </p>
      </VCol>

      <VCol v-if="audience === 'customers' || audience === 'other'" cols="12">
        <VSwitch v-model="form.is_custom" color="primary" label="Custom contract"
          hint="Write a one-off contract without adding it to the shared templates." persistent-hint />
      </VCol>

      <!-- Template-based -->
      <VCol v-if="!form.is_custom" cols="12">
        <VSelect v-model="form.template_id" :items="visibleTemplates" item-title="name" item-value="id"
          label="Contract Template" :rules="[v => !!v || 'Template is required']" :loading="loadingEntities"
          required />
      </VCol>

      <!-- Custom: pick a contract type -->
      <VCol v-else cols="12">
        <ContractTypeSelector v-model="form.contract_type_id" required @types-changed="onTypesChanged" />
      </VCol>

      <!-- Custom: free-form name + cover -->
      <template v-if="form.is_custom">
        <VCol cols="12" md="7">
          <VTextField v-model="form.name" label="Contract name" :rules="[v => !!v || 'Contract name is required']" />
        </VCol>
        <VCol cols="12">
          <VTextarea v-model="form.cover_text" label="Cover introduction (optional)" rows="2" />
        </VCol>
        <VCol cols="12">
          <VTextarea v-model="form.content" label="Contract content" rows="8"
            :rules="[v => !!v || 'Contract content is required']"
            hint="This content is saved on this contract only." persistent-hint />
        </VCol>
      </template>

      <!-- Context-driven counterparty selection -->
      <VCol v-if="context === 'employee'" cols="12">
        <VAutocomplete v-model="form.employee_id" :items="employeeOptions" label="Employee"
          placeholder="Select employee" prepend-inner-icon="tabler-user" :loading="loadingEntities"
          :rules="[v => !!v || 'Employee is required']" no-data-text="No employees found" required />
      </VCol>

      <VCol v-else-if="context === 'customer'" cols="12">
        <VAutocomplete v-model="form.customer_id" :items="customerOptions" label="Customer"
          placeholder="Select a customer" prepend-inner-icon="tabler-users" :loading="loadingEntities"
          :rules="[v => !!v || 'A customer is required']" no-data-text="No customers found" required />
      </VCol>

      <VCol v-else-if="context === 'company'" cols="12">
        <VAutocomplete v-model="form.company_id" :items="companyOptions" label="Counterparty Company"
          placeholder="Select a company" prepend-inner-icon="tabler-building" :loading="loadingEntities"
          :rules="[v => !!v || 'A counterparty company is required']" no-data-text="No companies found"
          hint="Manage companies from the Companies page in the Contracts menu." persistent-hint required>
          <template #append-item>
            <div class="pa-2">
              <VBtn variant="tonal" color="primary" size="small" block prepend-icon="tabler-plus" to="/companies">
                Manage Companies
              </VBtn>
            </div>
          </template>
        </VAutocomplete>
      </VCol>

      <!-- Read-only identity summary of the selected record -->
      <VCol cols="12">
        <CounterpartyCard :details="counterpartyDetails" />
      </VCol>


      <VCol cols="12" sm="6" md="4">
        <VSelect v-model="form.status" :items="statusOptions" label="Status"
          :rules="[v => !!v || 'Status is required']" required />
      </VCol>
      <VCol cols="12" sm="6" md="4">
        <VTextField v-model="form.start_date" label="Start Date" type="date"
          :rules="[v => !!v || 'Start date is required']" required />
      </VCol>
      <VCol cols="12" md="4">
        <VTextField v-model.number="form.duration_months" label="Duration (months)" type="number" min="1"
          :rules="[v => !!v || 'Duration is required']" hint="The end date is calculated from the start date." persistent-hint required />
      </VCol>
      <VCol cols="12" md="4">
        <VTextField v-model="form.end_date" label="Calculated End Date" type="date" readonly />
      </VCol>
      <VCol cols="12" md="6">
        <VTextField v-model="form.company_signatory_name" label="Our company signatory"
          hint="The authorized person signing on behalf of our company." persistent-hint />
      </VCol>
    </VRow>

    <VCardActions class="app-form__actions">
      <VBtn variant="outlined" color="secondary" @click="emit('cancel')">
        {{ $t('common.actions.cancel') }}
      </VBtn>
      <VBtn type="submit" color="primary">
        {{ $t('common.actions.save') }}
      </VBtn>
    </VCardActions>
  </VForm>
</template>
