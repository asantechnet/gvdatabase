<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import CompanyApi, { type Company } from '@/api/company'
import type { ContractStatus } from '@/api/contracts'
import CustomerApi, { type Customer } from '@/api/customer'
import EmployeeApi, { type Employee } from '@/api/employee'
import CounterpartyCard, { type CounterpartyDetails } from '@/components/contract/CounterpartyCard.vue'

const { t } = useI18n()
const tr = (key: string) => t(`pages.contractform.contractform.${key}`)

export interface ContractFormPayload {
  template_id: number | null
  employee_id: number | null
  customer_id: number | null
  company_id: number | null
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
  audience?: 'customers' | 'employees' | 'companies'
}>()

const emit = defineEmits<{
  (e: 'submit', payload: ContractFormPayload): void
  (e: 'cancel'): void
}>()

const formRef = ref()
const customers = ref<Customer[]>([])
const employees = ref<Employee[]>([])
const companies = ref<Company[]>([])
const loadingEntities = ref(true)

const form = ref({
  template_id: (props.contract?.template_id ?? null) as number | null,
  employee_id: (props.contract?.employee_id ?? null) as number | null,
  customer_id: (props.contract?.customer_id ?? null) as number | null,
  company_id: (props.contract?.company_id ?? null) as number | null,
  name: props.contract?.contract || '',
  cover_text: (props.contract?.cover_text || null) as string | null,
  content: props.contract?.content || '',
  status: (props.contract?.status || 'active') as ContractStatus,
  start_date: props.contract?.start_date || new Date().toISOString().slice(0, 10),
  end_date: (props.contract?.end_date || null) as string | null,
  duration_months: props.contract?.duration_months ?? null,
  company_signatory_name: props.contract?.company_signatory_name ?? null,
})

const normalizeWholeNumber = (value: unknown): number | null => {
  if (typeof value === 'number')
    return Number.isSafeInteger(value) && value > 0 ? value : null

  if (typeof value !== 'string')
    return null

  const normalized = value
    .trim()
    .replace(/[\u06F0-\u06F9]/g, digit => String(digit.charCodeAt(0) - 0x06F0))
    .replace(/[\u0660-\u0669]/g, digit => String(digit.charCodeAt(0) - 0x0660))

  return /^\d+$/.test(normalized) && Number(normalized) > 0 && Number.isSafeInteger(Number(normalized))
    ? Number(normalized)
    : null
}

const isPositiveWholeNumber = (value: unknown) =>
  value === null || value === '' || normalizeWholeNumber(value) !== null || tr('wholeMonths')

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

const activeSlug = computed<string | null>(() => {
  return selectedTemplate.value?.type_slug ?? null
})

const context = computed<'employee' | 'customer' | 'company' | null>(() => {
  const slug = activeSlug.value

  return slug ? CONTEXT_BY_SLUG[slug] ?? null : null
})

const statusOptions = [
  { title: t('common.statuses.draft'), value: 'draft' },
  { title: t('common.statuses.active'), value: 'active' },
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
  title: employee.position ? `${employee.name} — ${employee.position}` : employee.name,
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
            subtitle: selectedEmployee.value.position || null,
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
              selectedCompany.value.address_line,
          selectedCompany.value.city,
              selectedCompany.value.province,
          selectedCompany.value.country,
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
    const [customerResult, employeeResult, companyResult] = await Promise.allSettled([
      CustomerApi.getAll(),
      EmployeeApi.getOptions(),
      CompanyApi.getAll(),
    ])

    if (customerResult.status === 'fulfilled' && customerResult.value.success)
      customers.value = customerResult.value.data
    if (employeeResult.status === 'fulfilled')
      employees.value = employeeResult.value
    if (companyResult.status === 'fulfilled')
      companies.value = companyResult.value.filter(company => company.is_active !== false)
  }
  finally {
    loadingEntities.value = false
  }
})

watch(() => props.templates, () => {
  if (!form.value.template_id && visibleTemplates.value[0])
    form.value.template_id = visibleTemplates.value[0].id
}, { immediate: true })

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
  const durationMonths = normalizeWholeNumber(duration)
  if (!startDate || durationMonths === null) {
    form.value.end_date = null

    return
  }

  const [year, month, day] = startDate.split('-').map(Number)
  const lastDay = new Date(Date.UTC(year, month - 1 + durationMonths + 1, 0)).getUTCDate()

  form.value.end_date = new Date(Date.UTC(year, month - 1 + durationMonths, Math.min(day, lastDay))).toISOString().slice(0, 10)
}, { immediate: true })

const submit = async () => {
  const validation = await formRef.value?.validate()
  if (validation?.valid === false)
    return

  const durationMonths = normalizeWholeNumber(form.value.duration_months)
  emit('submit', {
    ...form.value,
    employee_id: context.value === 'employee' ? form.value.employee_id : null,
    customer_id: context.value === 'customer' ? form.value.customer_id : null,
    company_id: context.value === 'company' ? form.value.company_id : null,
    duration_months: durationMonths,

    // Template contracts inherit their display name from the template. Keep
    // it in the payload as well so older client validators cannot reject an
    // otherwise valid template-based contract because name is blank.
    name: selectedTemplate.value?.name || form.value.name,

    // Template contracts take their content from the selected template.
    content: undefined,
  })
}

defineExpose({ submit })
</script>

<template>
  <VForm
    ref="formRef"
    class="app-form"
    @submit.prevent="submit"
  >
    <VRow>
      <VCol cols="12">
        <p class="app-form__section">
          {{ tr('setup') }}
        </p>
        <p class="app-form__section-help">
          {{ tr('setupHint') }}
        </p>
      </VCol>

      <VCol cols="12">
        <VSelect
          v-model="form.template_id"
          :items="visibleTemplates"
          item-title="name"
          item-value="id"
          :label="tr('template')"
          :rules="[v => !!v || tr('templateRequired')]"
          :loading="loadingEntities"
          required
        />
      </VCol>

      <!-- Context-driven counterparty selection -->
      <VCol
        v-if="context === 'employee'"
        cols="12"
      >
        <VAutocomplete
          v-model="form.employee_id"
          :items="employeeOptions"
          :label="tr('employee')"
          :placeholder="tr('selectEmployee')"
          prepend-inner-icon="tabler-user"
          :loading="loadingEntities"
          :rules="[v => !!v || tr('employeeRequired')]"
          :no-data-text="tr('noEmployees')"
          required
        />
      </VCol>

      <VCol
        v-else-if="context === 'customer'"
        cols="12"
      >
        <VAutocomplete
          v-model="form.customer_id"
          :items="customerOptions"
          :label="tr('customer')"
          :placeholder="tr('selectCustomer')"
          prepend-inner-icon="tabler-users"
          :loading="loadingEntities"
          :rules="[v => !!v || tr('customerRequired')]"
          :no-data-text="tr('noCustomers')"
          required
        />
      </VCol>

      <VCol
        v-else-if="context === 'company'"
        cols="12"
      >
        <VAutocomplete
          v-model="form.company_id"
          :items="companyOptions"
          :label="tr('company')"
          :placeholder="tr('selectCompany')"
          prepend-inner-icon="tabler-building"
          :loading="loadingEntities"
          :rules="[v => !!v || tr('companyRequired')]"
          :no-data-text="tr('noCompanies')"
          :hint="tr('companyHint')"
          persistent-hint
          required
        >
          <template #append-item>
            <div class="pa-2">
              <VBtn
                variant="tonal"
                color="primary"
                size="small"
                block
                prepend-icon="tabler-plus"
                to="/companies"
              >
                {{ tr('manageCompanies') }}
              </VBtn>
            </div>
          </template>
        </VAutocomplete>
      </VCol>

      <!-- Read-only identity summary of the selected record -->
      <VCol cols="12">
        <CounterpartyCard :details="counterpartyDetails" />
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="4"
      >
        <VSelect
          v-model="form.status"
          :items="statusOptions"
          :label="tr('status')"
          :rules="[v => !!v || tr('statusRequired')]"
          required
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="4"
      >
        <VTextField
          v-model="form.start_date"
          :label="tr('startDate')"
          type="date"
          :rules="[v => !!v || tr('startRequired')]"
          required
        />
      </VCol>
      <VCol
        cols="12"
        md="4"
      >
        <VTextField
          v-model="form.duration_months"
          :label="tr('duration')"
          type="number"
          min="1"
          step="1"
          :rules="[isPositiveWholeNumber]"
          :hint="tr('durationHint')"
          persistent-hint
        />
      </VCol>
      <VCol
        cols="12"
        md="4"
      >
        <VTextField
          v-model="form.end_date"
          :label="form.end_date ? tr('calculatedEndDate') : tr('openContract')"
          type="date"
          readonly
        />
      </VCol>
      <VCol v-if="!form.end_date" cols="12" md="8">
        <VAlert color="info" variant="tonal" density="compact" icon="tabler-lock-open">
          {{ tr('openContractHint') }}
        </VAlert>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VTextField
          v-model="form.company_signatory_name"
          :label="tr('signatory')"
          :hint="tr('signatoryHint')"
          persistent-hint
        />
      </VCol>
    </VRow>

    <VCardActions class="app-form__actions">
      <VBtn
        variant="outlined"
        color="secondary"
        @click="emit('cancel')"
      >
        {{ $t('common.actions.cancel') }}
      </VBtn>
      <VBtn
        type="submit"
        color="primary"
      >
        {{ $t('common.actions.save') }}
      </VBtn>
    </VCardActions>
  </VForm>
</template>
