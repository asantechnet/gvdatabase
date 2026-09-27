<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import type { ContractStatus } from '@/api/contracts'
import CustomerApi, { type Customer } from '@/api/customer'
import EmployeeApi, { type Employee } from '@/api/employee'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import PositionApi, { type Position } from '@/api/position'
import ServiceApi, { type Service } from '@/api/services'
import CounterpartyCard, { type CounterpartyDetails } from '@/components/contract/CounterpartyCard.vue'

const { t } = useI18n()
const tr = (key: string) => t(`pages.contracts.contractform.contractform.${key}`)

// Signatory is the editable account Full Name (users.name). Staff are locked
// to their own account name; super-admin is auto-filled but remains editable.
const userData = useCookie<{ name?: string; roles?: string[] } | null>('userData')
const isSuperAdmin = computed(() => userData.value?.roles?.includes('super_admin') ?? false)
const currentUserName = computed(() => String(userData.value?.name ?? '').trim() || null)

export interface ContractFormPayload {
  template_id: number | null
  contract_template_version_id: number | null
  country_id: number | null
  language_id: number | null
  employee_id: number | null
  customer_id: number | null
  service_id: number | null
  service_ids?: number[] | null
  applicant_id?: number | null
  company_id: number | null
  name: string
  cover_text: string | null
  content?: string
  status: ContractStatus
  start_date: string
  counterparty?: string | null
  counterparty_company?: string | null
  counterparty_email?: string | null
  counterparty_contact?: string | null
  counterparty_address?: string | null
  company_signatory_name: string | null
}

interface TemplateOption {
  id: number
  name: string
  type_slug?: string | null
  service_id?: number | null
  position_id?: number | null
  cover_text?: string | null
  versions?: Array<{ id: number; name: string; country_id: number; language_id: number; version_number?: number; content?: string; status: string; is_current: boolean; country?: { id: number; name: string } | null; language?: { id: number; name: string; code?: string } | null }>
}

const props = defineProps<{
  contract?: {
    id: number
    contract: string
    status: ContractStatus
    start_date: string | null
    company_signatory_name?: string | null
    template_id: number | null
    contract_template_version_id?: number | null
    contract_country_id?: number | null
    contract_language_id?: number | null
    contract_type_id?: number | null
    employee_id?: number | null
    customer_id?: number | null
    service_ids?: number[] | null
    service_id?: number | null
    company_id?: number | null
    counterparty?: string | null
    counterparty_company?: string | null
    counterparty_email?: string | null
    counterparty_contact?: string | null
    counterparty_address?: string | null
    is_custom?: boolean
    type_slug?: string | null
    cover_text?: string | null
    content?: string | null
  }
  templates: TemplateOption[]
  audience?: 'customers' | 'employees' | 'other'
  applicantId?: number | null
  applicantCountryId?: number | null
  customerId?: number | null
  defaultServiceId?: number | null
}>()

const emit = defineEmits<{
  (e: 'submit', payload: ContractFormPayload): void
  (e: 'cancel'): void
}>()

const formRef = ref()
const customers = ref<Customer[]>([])
const employees = ref<Employee[]>([])
const countries = ref<MasterDataRecord[]>([])
const languages = ref<MasterDataRecord[]>([])
const services = ref<Service[]>([])
const positions = ref<Position[]>([])
const loadingEntities = ref(true)

const initialServiceId = (): number | null => {
  if (props.contract?.service_ids?.length)
    return props.contract.service_ids[0] ?? null
  if (props.contract?.service_id)
    return props.contract.service_id
  return props.defaultServiceId ?? null
}

const form = ref({
  template_id: (props.contract?.template_id ?? null) as number | null,
  contract_template_version_id: (props.contract?.contract_template_version_id ?? null) as number | null,
  country_id: (props.applicantCountryId ?? props.contract?.contract_country_id ?? null) as number | null,
  language_id: (props.contract?.contract_language_id ?? null) as number | null,
  position_id: null as number | null,
  employee_id: (props.contract?.employee_id ?? null) as number | null,
  customer_id: (props.contract?.customer_id ?? props.customerId ?? null) as number | null,
  service_id: initialServiceId(),
  company_id: (props.contract?.company_id ?? null) as number | null,
  name: props.contract?.contract || '',
  cover_text: (props.contract?.cover_text || null) as string | null,
  content: props.contract?.content || '',
  status: (props.contract?.status || 'active') as ContractStatus,
  start_date: props.contract?.start_date || new Date().toISOString().slice(0, 10),
  counterparty: (props.contract?.counterparty || '') as string,
  counterparty_company: (props.contract?.counterparty_company || null) as string | null,
  counterparty_email: (props.contract?.counterparty_email || null) as string | null,
  counterparty_contact: (props.contract?.counterparty_contact || null) as string | null,
  counterparty_address: (props.contract?.counterparty_address || null) as string | null,
  company_signatory_name: (props.contract?.company_signatory_name ?? currentUserName.value ?? null) as string | null,
})

// Non-super-admin staff always sign with their own account name.
watch([isSuperAdmin, currentUserName], ([superAdmin, accountName]) => {
  if (!superAdmin && accountName)
    form.value.company_signatory_name = accountName
}, { immediate: true })

// ---------------------------------------------------------------------
// Context metadata drives the whole form; no scattered if/else chains.
// ---------------------------------------------------------------------
const CONTEXT_BY_SLUG: Record<string, 'employee' | 'customer' | 'other' | null> = {
  employment: 'employee',
  customer: 'customer',
  other: 'other',
}

const selectedTemplate = computed(() =>
  props.templates.find(template => template.id === form.value.template_id) ?? null)

const activeSlug = computed<string | null>(() => {
  return selectedTemplate.value?.type_slug ?? null
})

const activeTemplateVersions = computed(() => selectedTemplate.value?.versions?.filter(version => version.is_current && version.status === 'active') ?? [])
const requiresVersionSelection = computed(() => activeTemplateVersions.value.length > 0)
const hasTemplateVersions = computed(() => (selectedTemplate.value?.versions?.length ?? 0) > 0)
const countryOptions = computed(() => {
  const versionCountryIds = [...new Set(activeTemplateVersions.value.map(version => version.country_id ?? null))]

  return versionCountryIds.map(countryId => ({
    title: countryId === null
      ? tr('allCountries')
      : countries.value.find(country => country.id === countryId)?.name
        ?? activeTemplateVersions.value.find(version => version.country_id === countryId)?.country?.name
        ?? tr('country'),
    value: countryId,
  }))
})
const countryScopedVersions = computed(() => activeTemplateVersions.value.filter(version => version.country_id === form.value.country_id))
const languageOptions = computed(() => languages.value
  .filter(language => language.is_active && countryScopedVersions.value.some(version => version.language_id === language.id))
  .map(language => ({ title: `${language.name}${language.code ? ` (${language.code})` : ''}`, value: language.id })))
const selectedTemplateVersion = computed(() => countryScopedVersions.value.find(version => version.language_id === form.value.language_id) ?? null)

const context = computed<'employee' | 'customer' | 'other' | null>(() => {
  const slug = activeSlug.value

  return slug ? CONTEXT_BY_SLUG[slug] ?? null : null
})

const statusOptions = [
  { title: t('common.statuses.draft'), value: 'draft' },
  { title: t('common.statuses.active'), value: 'active' },
  { title: t('common.statuses.completed'), value: 'completed' },
  { title: t('common.statuses.terminated'), value: 'terminated' },
]

const audienceTemplates = computed(() => {
  if (props.audience === 'employees')
    return props.templates.filter(template => template.type_slug === 'employment')

  if (props.audience === 'customers')
    return props.templates.filter(template => template.type_slug === 'customer')

  if (props.audience === 'other')
    return props.templates.filter(template => template.type_slug === 'other')

  return props.templates
})

// Print/create-page rule: when a service is selected, only templates related
// to that service (plus generic templates with no linked service) show up.
const visibleTemplates = computed(() => {
  if ((context.value === 'customer' || context.value === 'other') && form.value.service_id) {
    return audienceTemplates.value.filter(template =>
      template.service_id === null || template.service_id === undefined
      || template.service_id === form.value.service_id,
    )
  }

  return audienceTemplates.value
})

const customerOptions = computed(() => customers.value.map(customer => ({
  title: customer.company ? `${customer.name} — ${customer.company}` : customer.name,
  value: customer.id,
})))

const positionOptions = computed(() => positions.value.map(position => ({
  title: position.title,
  value: position.id,
})))

const employeeOptions = computed(() => employees.value
  .filter(employee => context.value !== 'employee' || !form.value.position_id || employee.position_id === form.value.position_id)
  .map(employee => ({
    title: employee.position ? `${employee.name} — ${employee.position}` : employee.name,
    value: employee.id,
  })))

const selectedEmployee = computed(() => employees.value.find(item => item.id === form.value.employee_id) ?? null)
const selectedCustomer = computed(() => customers.value.find(item => item.id === form.value.customer_id) ?? null)
const activeServices = computed(() => services.value.filter(service => service.status === 'active'))
const serviceOptions = computed(() => activeServices.value.map(service => ({ title: service.name || service.type, value: service.id })))
const selectedService = computed(() => activeServices.value.find(service => service.id === form.value.service_id) ?? null)

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
    case 'other':
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
    default:
      return null
  }
})

onMounted(async () => {
  try {
    const [customerResult, employeeResult, countryResult, languageResult, serviceResult, positionResult] = await Promise.allSettled([
      CustomerApi.getAll(),
      EmployeeApi.getOptions(),
      MasterDataApi.list('countries'),
      MasterDataApi.list('languages'),
      ServiceApi.getAll(),
      PositionApi.getAll(),
    ])

    if (customerResult.status === 'fulfilled' && customerResult.value.success)
      customers.value = customerResult.value.data
    if (employeeResult.status === 'fulfilled')
      employees.value = employeeResult.value
    if (countryResult.status === 'fulfilled')
      countries.value = countryResult.value.filter(country => country.is_active)
    if (languageResult.status === 'fulfilled')
      languages.value = languageResult.value.filter(language => language.is_active)
    if (serviceResult.status === 'fulfilled')
      services.value = serviceResult.value.data
    if (positionResult.status === 'fulfilled')
      positions.value = positionResult.value.data?.data ?? positionResult.value.data ?? []
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
  form.value.customer_id = currentContext === 'customer' || currentContext === 'other' ? (props.contract?.customer_id ?? form.value.customer_id) : null
  if (currentContext !== 'customer' && currentContext !== 'other')
    form.value.service_id = null
  if (currentContext !== 'employee')
    form.value.position_id = null
})

// If the selected service narrows the template list so the current template
// is no longer related, fall back to the first related template.
watch(() => [form.value.service_id, visibleTemplates.value] as const, () => {
  if (form.value.template_id && !visibleTemplates.value.some(template => template.id === form.value.template_id) && visibleTemplates.value[0])
    form.value.template_id = visibleTemplates.value[0].id
})

watch(() => form.value.template_id, () => {
  const existingVersion = activeTemplateVersions.value.find(version => version.id === form.value.contract_template_version_id)

  form.value.country_id = props.applicantCountryId ?? existingVersion?.country_id ?? countryOptions.value[0]?.value ?? null
  form.value.language_id = existingVersion?.language_id ?? null
  form.value.contract_template_version_id = existingVersion?.id ?? null
}, { immediate: true })

watch(() => form.value.country_id, () => {
  if (!countryScopedVersions.value.some(version => version.language_id === form.value.language_id))
    form.value.language_id = null
  form.value.contract_template_version_id = null
})

watch(() => form.value.language_id, () => {
  form.value.contract_template_version_id = selectedTemplateVersion.value?.id ?? null
})

// Employment position narrows the employee list; clearing it keeps the
// employee only when they actually hold that position.
watch(() => form.value.position_id, positionId => {
  if (positionId && form.value.employee_id) {
    const employee = employees.value.find(item => item.id === form.value.employee_id)
    if (employee && employee.position_id !== positionId)
      form.value.employee_id = null
  }
})

const submit = async () => {
  const validation = await formRef.value?.validate()
  if (validation?.valid === false)
    return

  emit('submit', {
    ...form.value,
    company_signatory_name: !isSuperAdmin.value && currentUserName.value
      ? currentUserName.value
      : (form.value.company_signatory_name?.trim() || currentUserName.value),
    contract_template_version_id: selectedTemplateVersion.value?.id ?? null,
    employee_id: context.value === 'employee' ? form.value.employee_id : null,
    customer_id: context.value === 'customer' || context.value === 'other' ? form.value.customer_id : null,
    service_id: context.value === 'customer' || context.value === 'other' ? form.value.service_id : null,
    service_ids: context.value === 'customer' || context.value === 'other' ? (form.value.service_id ? [form.value.service_id] : []) : [],
    applicant_id: context.value === 'customer' ? props.applicantId ?? null : null,
    company_id: null,

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
      <VCol
        cols="12"
        :md="selectedTemplate ? 6 : 12"
      >
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

      <VCol
        v-if="selectedTemplate"
        cols="12"
        md="3"
      >
        <VSelect
          v-model="form.country_id"
          :items="countryOptions"
          :label="tr('country')"
          :disabled="!!applicantCountryId || !requiresVersionSelection"
          :hint="applicantCountryId ? tr('countryFromApplicant') : tr('countryVersionHint')"
          persistent-hint
        />
      </VCol>
      <VCol
        v-if="selectedTemplate"
        cols="12"
        md="3"
      >
        <VSelect
          v-model="form.language_id"
          :items="languageOptions"
          :label="tr('language')"
          :disabled="!requiresVersionSelection"
          :rules="[value => !requiresVersionSelection || !!value || tr('languageRequired')]"
          :hint="tr('languageVersionHint')"
          persistent-hint
          required
        />
      </VCol>
      <VCol
        v-if="selectedTemplate && !requiresVersionSelection"
        cols="12"
      >
        <VAlert
          :color="hasTemplateVersions ? 'warning' : 'info'"
          variant="tonal"
          density="compact"
          icon="tabler-language"
        >
          {{ hasTemplateVersions ? tr('noActiveVersions') : tr('noVersions') }}
        </VAlert>
      </VCol>
      <VCol
        v-if="selectedTemplateVersion"
        cols="12"
      >
        <VAlert color="success" variant="tonal" density="compact" icon="tabler-versions">
          {{ tr('selectedVersion', { name: selectedTemplateVersion.name, version: selectedTemplateVersion.version_number, country: selectedTemplateVersion.country?.name || tr('country'), language: selectedTemplateVersion.language?.name || tr('language') }) }}
        </VAlert>
      </VCol>

      <!-- Context-driven counterparty selection -->
      <VCol
        v-if="context === 'employee'"
        cols="12"
      >
        <VSelect
          v-model="form.position_id"
          :items="positionOptions"
          :label="tr('position')"
          :placeholder="tr('selectPosition')"
          prepend-inner-icon="tabler-briefcase"
          :loading="loadingEntities"
          :rules="[v => !!v || tr('positionRequired')]"
          :no-data-text="tr('noPositions')"
          required
        />
      </VCol>
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
        v-else-if="context === 'customer' || context === 'other'"
        cols="12"
      >
        <VAutocomplete
          v-model="form.customer_id"
          :items="customerOptions"
          :label="tr('customer')"
          :placeholder="tr('selectCustomer')"
          prepend-inner-icon="tabler-users"
          :loading="loadingEntities"
          :rules="context === 'customer' ? [v => !!v || tr('customerRequired')] : []"
          :no-data-text="tr('noCustomers')"
          :required="context === 'customer'"
          :clearable="context === 'other'"
        />
      </VCol>

      <VCol
        v-if="context === 'customer' || context === 'other'"
        cols="12"
      >
        <VSelect
          v-model="form.service_id"
          :items="serviceOptions"
          :label="tr('service')"
          :placeholder="tr('selectService')"
          prepend-inner-icon="tabler-briefcase"
          :loading="loadingEntities"
          :rules="context === 'customer' ? [v => !!v || tr('servicesRequired')] : []"
          :no-data-text="tr('noServices')"
          :required="context === 'customer'"
          :clearable="context === 'other'"
        />
        <p
          v-if="selectedService"
          class="text-caption text-medium-emphasis mt-1"
        >
          {{ tr('selectedServicesHint') }}: {{ selectedService.name || selectedService.type }}
        </p>
      </VCol>

      <VCol
        v-if="context === 'other'"
        cols="12"
      >
        <VTextField
          v-model="form.counterparty"
          :label="tr('counterparty')"
          :placeholder="tr('counterpartyPlaceholder')"
          prepend-inner-icon="tabler-id"
          :rules="[v => !!form.customer_id || !!String(v || '').trim() || tr('counterpartyRequired')]"
        />
      </VCol>
      <VCol
        v-if="context === 'other'"
        cols="12"
        md="6"
      >
        <VTextField
          v-model="form.counterparty_email"
          :label="tr('counterpartyEmail')"
          type="email"
          prepend-inner-icon="tabler-mail"
        />
      </VCol>
      <VCol
        v-if="context === 'other'"
        cols="12"
        md="6"
      >
        <VTextField
          v-model="form.counterparty_contact"
          :label="tr('counterpartyContact')"
          prepend-inner-icon="tabler-phone"
        />
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
          v-model="form.company_signatory_name"
          :label="tr('signatory')"
          :hint="tr('signatoryHint')"
          persistent-hint
          :readonly="!isSuperAdmin"
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
