<script setup lang="ts">
import ErpField from '@/components/erp/ErpField.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { previewContract, type ContractStatus } from '@/api/contracts'
import ApplicantApi, { type Applicant } from '@/api/applicants'
import AuthApi from '@/api/auth'
import EmployeeApi, { type Employee } from '@/api/employee'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import PositionApi, { type Position } from '@/api/position'
import CounterpartyCard, { type CounterpartyDetails } from '@/components/contract/CounterpartyCard.vue'

const { t } = useI18n()
const tr = (key: string, values: Record<string, unknown> = {}) => t(`pages.contracts.contractform.contractform.${key}`, values)

// Staff contracts must be signed with the logged-in employee's name.
// Super-admins may choose another authorized signer.
const userData = useCookie<{ id?: number; roles?: Array<string | { name?: string }>; employee?: { id: number; name?: string } | null } | null>('userData')
const isSuperAdmin = computed(() => userData.value?.roles?.some(role =>
  (typeof role === 'string' ? role : role.name) === 'super_admin') ?? false)

export interface ContractFormPayload {
  template_id: number | null
  contract_template_version_id: number | null
  parent_contract_id?: number | null
  country_id: number | null
  language_id: number | null
  employee_id: number | null
  applicant_id?: number | null
  name: string
  cover_text: string | null
  content?: string
  status: ContractStatus
  start_date: string
  end_date?: string | null
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
  position_id?: number | null
  cover_text?: string | null
  versions?: Array<{ id: number; name: string; country_id: number | null; language_id: number | null; version_number?: number; content?: string; status: string; is_current: boolean; country?: { id: number; name: string } | null; language?: { id: number; name: string; code?: string } | null }>
}

// Shared shape for the record being edited and for the parent of an extension,
// so both prefill the form through the same code path.
interface ContractFormSource {
  id: number
  contract: string
  status: ContractStatus
  start_date: string | null
  end_date?: string | null
  company_signatory_name?: string | null
  template_id: number | null
  contract_template_version_id?: number | null
  contract_country_id?: number | null
  contract_language_id?: number | null
  contract_type_id?: number | null
  employee_id?: number | null
  applicant_id?: number | null
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

const props = defineProps<{
  contract?: ContractFormSource
  /** When set, the new contract is a draft extension of this agreement. */
  parentContract?: ContractFormSource | null
  extensionMode?: boolean
  templates: TemplateOption[]
  audience?: 'employees' | 'other'
  applicantId?: number | null
  applicantCountryId?: number | null
}>()

const emit = defineEmits<{
  (e: 'submit', payload: ContractFormPayload): void
  (e: 'cancel'): void
}>()

const formRef = ref()
const applicants = ref<Applicant[]>([])
const employees = ref<Employee[]>([])
const currentEmployeeName = computed(() => {
  const employeeId = userData.value?.employee?.id
  const userId = userData.value?.id
  const match = employees.value.find(item => (employeeId != null && item.id === employeeId) || (userId != null && item.user_id === userId)) ?? null
  const cookieName = String(userData.value?.employee?.name ?? '').trim() || null

  return (match?.name?.trim() || cookieName || null) as string | null
})
const dockCalendar = (_dates: Date[], _value: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.transform = 'translateY(0) scale(0.86)'
    instance.calendarContainer.style.transformOrigin = 'top left'
    instance.calendarContainer.style.zIndex = '2400'
  })
}
const isApplicantLocked = computed(() => props.applicantId != null)
// Other contracts are blank templates the office completes by hand: they carry
// no counterparty identity, only the optional record that feeds placeholders.
const isOtherAudience = computed(() => props.audience === 'other')
const isEmployeeAudience = computed(() => props.audience === 'employees')
const usesOtherContractUi = computed(() => isOtherAudience.value || isEmployeeAudience.value)
const countries = ref<MasterDataRecord[]>([])
const languages = ref<MasterDataRecord[]>([])
const positions = ref<Position[]>([])
const loadingEntities = ref(true)

// The extension prefill comes from the parent agreement; an edit still starts
// from the record itself.
const formSource = props.contract ?? props.parentContract ?? null

// A renewal starts the day after the agreement it extends ends. Without an end
// date there is nothing to continue from, so it starts today.
const initialStartDate = () => {
  const parentEnd = props.parentContract?.end_date
  if (props.extensionMode && parentEnd) {
    const next = new Date(`${parentEnd}T00:00:00`)
    if (!Number.isNaN(next.getTime())) {
      next.setDate(next.getDate() + 1)

      return next.toISOString().slice(0, 10)
    }
  }

  return props.contract?.start_date || new Date().toISOString().slice(0, 10)
}

const form = ref({
  template_id: (formSource?.template_id ?? null) as number | null,
  contract_template_version_id: (formSource?.contract_template_version_id ?? null) as number | null,
  country_id: (props.applicantCountryId ?? formSource?.contract_country_id ?? null) as number | null,
  language_id: (formSource?.contract_language_id ?? null) as number | null,
  employee_id: (formSource?.employee_id ?? null) as number | null,
  applicant_id: (formSource?.applicant_id ?? props.applicantId ?? null) as number | null,
  name: formSource?.contract || '',
  cover_text: (formSource?.cover_text || null) as string | null,
  content: formSource?.content || '',
  status: (props.extensionMode ? 'draft' : (formSource?.status || 'active')) as ContractStatus,
  start_date: initialStartDate(),
  end_date: (props.extensionMode ? '' : (formSource?.end_date ?? '')) as string,
  counterparty: (formSource?.counterparty || '') as string,
  counterparty_company: (formSource?.counterparty_company || null) as string | null,
  counterparty_email: (formSource?.counterparty_email || null) as string | null,
  counterparty_contact: (formSource?.counterparty_contact || null) as string | null,
  counterparty_address: (formSource?.counterparty_address || null) as string | null,
  company_signatory_name: (formSource?.company_signatory_name ?? null) as string | null,
})

const applySignatoryDefault = (employeeName: string | null) => {
  if (!employeeName)
    return

  if (!isSuperAdmin.value || !form.value.company_signatory_name)
    form.value.company_signatory_name = employeeName
}
watch([isSuperAdmin, currentEmployeeName], ([, employeeName]) => applySignatoryDefault(employeeName), { immediate: true })

// ---------------------------------------------------------------------
// Context metadata drives the whole form; no scattered if/else chains.
// ---------------------------------------------------------------------
const CONTEXT_BY_SLUG: Record<string, 'employee' | 'other' | null> = {
  employment: 'employee',
  other: 'other',
}

const selectedTemplate = computed(() =>
  props.templates.find(template => template.id === form.value.template_id) ?? null)

const activeSlug = computed<string | null>(() => {
  return selectedTemplate.value?.type_slug ?? null
})

// Employment templates are not versioned per country/language: they carry a
// single current version identified by its name, so it is selected for you.
const isEmploymentTemplate = computed(() => activeSlug.value === 'employment')
const activeTemplateVersions = computed(() => selectedTemplate.value?.versions?.filter(version => version.is_current && version.status === 'active') ?? [])
const requiresVersionSelection = computed(() => activeTemplateVersions.value.length > 0 && !isEmploymentTemplate.value)
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
const selectedTemplateVersion = computed(() => {
  if (isEmploymentTemplate.value)
    return activeTemplateVersions.value.find(version => version.id === form.value.contract_template_version_id) ?? activeTemplateVersions.value[0] ?? null

  return countryScopedVersions.value.find(version => version.language_id === form.value.language_id) ?? null
})

const context = computed<'employee' | 'other' | null>(() => {
  const slug = activeSlug.value

  return slug ? CONTEXT_BY_SLUG[slug] ?? 'other' : null
})

const statusOptions = computed(() => [
  { title: t('common.statuses.draft'), value: 'draft' },
  { title: t('common.statuses.active'), value: 'active' },
  { title: t('common.statuses.completed'), value: 'completed' },
  { title: t('common.statuses.terminated'), value: 'terminated' },
])

const audienceTemplates = computed(() => {
  if (props.audience === 'employees')
    return props.templates.filter(template => template.type_slug === 'employment')

  if (props.audience === 'other')
    return props.templates.filter(template => template.type_slug !== 'employment' && template.type_slug !== 'customer')

  return props.templates
})

const visibleTemplates = computed(() => audienceTemplates.value.filter(template => template.type_slug !== 'customer'))

const applicantOptions = computed(() => applicants.value.map(applicant => ({
  title: applicant.name,
  value: applicant.id,
})))
// The selected employment template owns the position; the employee list only
// offers people who actually hold it.
const templatePositionId = computed(() => selectedTemplate.value?.position_id ?? null)
const templatePositionTitle = computed(() => positions.value.find(position => position.id === templatePositionId.value)?.title ?? '')

const employeeOptions = computed(() => employees.value
  .filter(employee => context.value !== 'employee' || !templatePositionId.value || employee.position_id === templatePositionId.value)
  .map(employee => ({
    title: employee.position ? `${employee.name} — ${employee.position}` : employee.name,
    value: employee.id,
  })))

const selectedEmployee = computed(() => employees.value.find(item => item.id === form.value.employee_id) ?? null)
const selectedApplicant = computed(() => applicants.value.find(item => item.id === form.value.applicant_id) ?? null)

watch(selectedApplicant, applicant => {
  // Other contracts have no counterparty: the selected record only supplies
  // template placeholders, so the counterparty fields are always cleared.
  if (isOtherAudience.value) {
    form.value.counterparty = ''
    form.value.counterparty_company = null
    form.value.counterparty_email = null
    form.value.counterparty_contact = null
    form.value.counterparty_address = null

    return
  }

  form.value.counterparty = applicant?.name ?? ''
  form.value.counterparty_company = null
  form.value.counterparty_email = applicant?.email ?? null
  form.value.counterparty_contact = applicant?.contact ?? null
  form.value.counterparty_address = applicant?.address ?? null
})

const previewHtml = ref('')
const previewLoading = ref(false)
const previewError = ref(false)
let previewRequest = 0
watch(() => [form.value.template_id, form.value.contract_template_version_id, form.value.country_id,
  form.value.language_id, form.value.employee_id, form.value.applicant_id, form.value.start_date,
  form.value.end_date, form.value.company_signatory_name], async () => {
  const request = ++previewRequest
  previewHtml.value = ''
  previewError.value = false
  previewLoading.value = false
  if (!form.value.template_id || !(form.value.employee_id || form.value.applicant_id)
    || (requiresVersionSelection.value && !selectedTemplateVersion.value))
    return

  previewLoading.value = true
  try {
    const html = await previewContract({
      template_id: form.value.template_id,
      contract_template_version_id: selectedTemplateVersion.value?.id ?? null,
      country_id: form.value.country_id, language_id: form.value.language_id,
      employee_id: context.value === 'employee' ? form.value.employee_id : null,
      applicant_id: context.value === 'employee' ? null : form.value.applicant_id,
      start_date: form.value.start_date, end_date: form.value.end_date || null,
      company_signatory_name: form.value.company_signatory_name,
    })
    if (request === previewRequest)
      previewHtml.value = html
  }
  catch {
    if (request === previewRequest)
      previewError.value = true
  }
  finally {
    if (request === previewRequest)
      previewLoading.value = false
  }
}, { immediate: true })

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
    case 'other':
      return selectedApplicant.value
        ? {
          kind: 'customer',
          name: selectedApplicant.value.name,
          subtitle: null,
          phone: selectedApplicant.value.contact,
          email: selectedApplicant.value.email,
          address: selectedApplicant.value.address,
        }
        : null
    default:
      return null
  }
})

onMounted(async () => {
  // Employment contracts are drawn for an employee, never an applicant, so
  // fetching the applicant page here only produced a spurious pagination
  // validation error (the API caps `per_page` at 100).
  const loadsApplicants = props.audience !== 'employees'
  try {
    const [applicantResult, employeeResult, countryResult, languageResult, positionResult, authResult] = await Promise.allSettled([
      loadsApplicants ? ApplicantApi.list({ per_page: 100 }) : Promise.resolve(null),
      EmployeeApi.getOptions(),
      MasterDataApi.list('countries'),
      MasterDataApi.list('languages'),
      PositionApi.getAll(),
      AuthApi.me(),
    ])

    if (applicantResult.status === 'fulfilled' && applicantResult.value)
      applicants.value = applicantResult.value.data
    if (employeeResult.status === 'fulfilled')
      employees.value = employeeResult.value
    if (countryResult.status === 'fulfilled')
      countries.value = countryResult.value.filter(country => country.is_active)
    if (languageResult.status === 'fulfilled')
      languages.value = languageResult.value.filter(language => language.is_active)
    if (positionResult.status === 'fulfilled')
      positions.value = positionResult.value.data?.data ?? positionResult.value.data ?? []
    if (authResult.status === 'fulfilled')
      userData.value = authResult.value.data.data

    // A visa-case dialog passes its applicant explicitly; make sure that
    // record is selectable even when it falls outside the first page.
    if (props.applicantId && !applicants.value.some(item => item.id === props.applicantId)) {
      try {
        const single = await ApplicantApi.get(props.applicantId)
        if (single)
          applicants.value = [single, ...applicants.value]
      }
      catch {
        // The locked select still shows the id; creation stays possible.
      }
    }
    applySignatoryDefault(currentEmployeeName.value)
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
  // opened from a visa-case action button; clear incompatible IDs.
  form.value.employee_id = currentContext === 'employee' ? (props.contract?.employee_id ?? form.value.employee_id) : null
  if (currentContext === 'other') {
    if (props.applicantId)
      form.value.applicant_id = props.applicantId
    else if (props.contract?.applicant_id)
      form.value.applicant_id = props.contract.applicant_id
  }
  else {
    form.value.applicant_id = null
  }
})

// Keep the visa-case applicant locked even if the dialog is reused.
watch(() => props.applicantId, applicantId => {
  if (applicantId)
    form.value.applicant_id = applicantId
})

watch(() => form.value.template_id, () => {
  const existingVersion = activeTemplateVersions.value.find(version => version.id === form.value.contract_template_version_id)

  if (isEmploymentTemplate.value) {
    form.value.country_id = null
    form.value.language_id = null
    form.value.contract_template_version_id = existingVersion?.id ?? activeTemplateVersions.value[0]?.id ?? null

    return
  }

  form.value.country_id = props.applicantCountryId ?? existingVersion?.country_id ?? countryOptions.value[0]?.value ?? null
  form.value.language_id = existingVersion?.language_id ?? null
  form.value.contract_template_version_id = existingVersion?.id ?? null
}, { immediate: true })

watch(() => form.value.country_id, () => {
  if (isEmploymentTemplate.value)
    return
  if (!countryScopedVersions.value.some(version => version.language_id === form.value.language_id))
    form.value.language_id = null
  form.value.contract_template_version_id = null
})

watch(() => form.value.language_id, () => {
  if (isEmploymentTemplate.value)
    return
  form.value.contract_template_version_id = selectedTemplateVersion.value?.id ?? null
})

// A template's position drives the employee list; clearing keeps the employee
// only when they actually hold that position.
watch(templatePositionId, positionId => {
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

  const applicantId = context.value === 'other'
    ? (form.value.applicant_id ?? props.applicantId ?? null)
    : null

  emit('submit', {
    ...form.value,
    parent_contract_id: props.extensionMode ? (props.parentContract?.id ?? null) : null,
    company_signatory_name: !isSuperAdmin.value && currentEmployeeName.value
      ? currentEmployeeName.value
      : (form.value.company_signatory_name?.trim() || currentEmployeeName.value),
    contract_template_version_id: selectedTemplateVersion.value?.id ?? null,
    employee_id: context.value === 'employee' ? form.value.employee_id : null,
    applicant_id: applicantId,

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
    <VRow class="contract-form-grid">
      <VCol cols="12" :md="isOtherAudience ? 6 : undefined">
        <ErpField kind="select"
          v-model="form.template_id"
          :items="visibleTemplates"
          item-title="name"
          item-value="id"
          :label="tr('template')"
          :rules="[v => !!v || tr('templateRequired')]"
          required
        />
      </VCol>

      <VCol
        v-if="selectedTemplate && !isEmploymentTemplate"
        cols="12"
        :md="isOtherAudience ? 6 : 3"
      >
        <ErpField kind="select"
          v-model="form.country_id"
          :items="countryOptions"
          :label="tr('country')"
          :disabled="!!applicantCountryId || !requiresVersionSelection"
          :hint="applicantCountryId ? tr('countryFromApplicant') : tr('countryVersionHint')"
          persistent-hint
        />
      </VCol>
      <VCol
        v-if="selectedTemplate && !isEmploymentTemplate"
        cols="12"
        :md="isOtherAudience ? 6 : 3"
      >
        <ErpField kind="select"
          v-model="form.language_id"
          :items="languageOptions"
          :label="tr('language')"
          :disabled="!requiresVersionSelection"
          :rules="[value => !requiresVersionSelection || !!value || tr('languageRequired')]"
          :hint="tr('languageVersionHint')"
          persistent-hint
          :required="requiresVersionSelection"
        />
      </VCol>
      <VCol
        v-if="selectedTemplate && !requiresVersionSelection && !isEmploymentTemplate"
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
        v-if="isEmploymentTemplate && hasTemplateVersions && !selectedTemplateVersion"
        cols="12"
      >
        <VAlert color="warning" variant="tonal" density="compact" icon="tabler-versions">
          {{ tr('noActiveEmploymentVersion') }}
        </VAlert>
      </VCol>
      <VCol
        v-if="selectedTemplateVersion && !isEmployeeAudience"
        cols="12"
      >
        <VAlert color="success" variant="tonal" density="compact" icon="tabler-versions">
          {{ isEmploymentTemplate
            ? tr('selectedVersionName', { name: selectedTemplateVersion.name, version: selectedTemplateVersion.version_number })
            : tr('selectedVersion', { name: selectedTemplateVersion.name, version: selectedTemplateVersion.version_number, country: selectedTemplateVersion.country?.name || tr('country'), language: selectedTemplateVersion.language?.name || tr('language') }) }}
        </VAlert>
      </VCol>


      <!-- Context-driven counterparty selection -->
      <VCol
        v-if="context === 'employee' && selectedTemplate"
        cols="12"
        md="6"
      >
        <ErpField
          :model-value="templatePositionTitle"
          :label="tr('position')"
          prepend-inner-icon="tabler-briefcase"
          readonly
          variant="outlined"
          density="compact"
        />
      </VCol>
      <VCol
        v-if="context === 'employee'"
        cols="12"
        md="6"
      >
        <ErpField
          kind="autocomplete"
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
        v-if="context === 'other'"
        cols="12"
        :md="isOtherAudience ? 6 : undefined"
      >
        <ErpField
          kind="autocomplete"
          v-model="form.applicant_id"
          :items="applicantOptions"
          :label="tr('applicant')"
          :placeholder="tr('selectApplicant')"
          prepend-inner-icon="tabler-users"
          :loading="loadingEntities"
          :no-data-text="tr('noApplicants')"
          :clearable="context === 'other' && !isApplicantLocked"
          :disabled="isApplicantLocked"
          :hint="isApplicantLocked ? tr('applicantFromCase') : undefined"
          persistent-hint
        />
      </VCol>

      <!-- Other contracts carry no counterparty at all (blank template), so
           these identity fields exist only for employee-derived parties. -->
      <template v-if="context === 'other' && !isOtherAudience">
        <VCol cols="12">
          <ErpField
            v-model="form.counterparty"
            :label="tr('counterparty')"
            :placeholder="tr('counterpartyPlaceholder')"
            prepend-inner-icon="tabler-id"
            :rules="[v => !!form.applicant_id || !!String(v || '').trim() || tr('counterpartyRequired')]"
          />
        </VCol>
        <VCol cols="12" md="6">
          <ErpField
            v-model="form.counterparty_email"
            :label="tr('counterpartyEmail')"
            type="email"
            prepend-inner-icon="tabler-mail"
          />
        </VCol>
        <VCol cols="12" md="6">
          <ErpField
            v-model="form.counterparty_contact"
            :label="tr('counterpartyContact')"
            prepend-inner-icon="tabler-phone"
          />
        </VCol>
      </template>

      <!-- Other contracts may optionally link an applicant for template
           placeholders, but they have no counterparty to summarise. -->
      <VCol v-if="!isOtherAudience && (context !== 'employee' || selectedEmployee)" cols="12">
        <CounterpartyCard
          :details="counterpartyDetails"
          :title-key="isOtherAudience ? 'components.contractCounterpartyCard.applicantCardTitle' : null"
        />
      </VCol>

      <!-- Employment terms own their dates. Keeping them outside the
           counterparty summary makes them visible and editable even before a
           staff member is selected. -->
      <template v-if="isEmployeeAudience">
        <VCol cols="12" sm="6">
          <div class="erp-field">
            <label class="erp-field-label">{{ tr('startDate') }} <span class="erp-field-required">*</span></label>
            <AppDateTimePicker v-model="form.start_date" class="erp-field-control" :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockCalendar }" :rules="[(value: unknown) => !!value || tr('startDateRequired')]" required />
          </div>
        </VCol>
        <VCol cols="12" sm="6">
          <div class="erp-field">
            <label class="erp-field-label">{{ tr('endDate') }}</label>
            <AppDateTimePicker v-model="form.end_date" clearable class="erp-field-control" :config="{ dateFormat: 'Y-m-d', static: false, minDate: form.start_date || undefined, onOpen: dockCalendar }" />
          </div>
        </VCol>
      </template>

      <template v-if="isOtherAudience">
        <VCol cols="12" md="6">
          <div class="erp-field">
            <label class="erp-field-label">{{ tr('startDate') }} <span class="erp-field-required">*</span></label>
            <AppDateTimePicker v-model="form.start_date" class="erp-field-control" :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockCalendar }" :rules="[(value: unknown) => !!value || tr('startDateRequired')]" required />
          </div>
        </VCol>
        <VCol cols="12" md="6">
          <div class="erp-field">
            <label class="erp-field-label">{{ tr('endDate') }}</label>
            <AppDateTimePicker v-model="form.end_date" clearable class="erp-field-control" :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockCalendar }" />
          </div>
        </VCol>
      </template>

      <VCol
        cols="12"
        md="6"
      >
        <ErpField kind="select"
          v-model="form.status"
          :items="statusOptions"
          :label="tr('status')"
          :rules="[v => !!v || tr('statusRequired')]"
          :disabled="extensionMode"
          :hint="extensionMode ? tr('extensionStatusHint') : undefined"
          persistent-hint
          required
        />
      </VCol>
      <VCol
        cols="12"
        :md="isOtherAudience ? 12 : 6"
      >
        <ErpField
          v-model="form.company_signatory_name"
          :label="tr('signatory')"
          :disabled="!isSuperAdmin"
        />
      </VCol>
      <VCol v-if="previewLoading || previewHtml || previewError" cols="12">
        <VProgressLinear v-if="previewLoading" indeterminate />
        <VAlert v-else-if="previewError" type="warning" variant="tonal">{{ tr('previewFailed') }}</VAlert>
        <div v-else class="contract-form-preview" v-html="previewHtml" />
      </VCol>
    </VRow>

    <VCardActions :class="['app-form__actions', { 'other-contract-form-actions': usesOtherContractUi }]">
      <VBtn
        :variant="usesOtherContractUi ? 'flat' : 'outlined'"
        :color="usesOtherContractUi ? 'error' : 'secondary'"
        :class="{ 'other-contract-form-actions__cancel': usesOtherContractUi }"
        @click="emit('cancel')"
      >
        {{ $t('common.actions.cancel') }}
      </VBtn>
      <VSpacer v-if="usesOtherContractUi" />
      <VBtn
        type="submit"
        color="primary"
        :variant="usesOtherContractUi ? 'flat' : undefined"
        :class="{ 'other-contract-form-actions__save': usesOtherContractUi }"
      >
        {{ $t('common.actions.save') }}
      </VBtn>
    </VCardActions>
  </VForm>
</template>

<style scoped>
.contract-form-grid {
  /* Vuetify applies its default 24px gutter through v-col padding. */
  margin: -3px !important;
  row-gap: 0 !important;
}
.contract-form-grid > :deep(.v-col) {
  padding: 3px !important;
}

.contract-form-preview {
  max-block-size: 360px;
  overflow: auto;
  padding: 16px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  overflow-wrap: anywhere;
}
.contract-form-preview :deep(table) { inline-size: 100%; border-collapse: collapse; }
.contract-form-preview :deep(td), .contract-form-preview :deep(th) {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding: 6px 8px;
}

@media (max-width: 600px) {
  .contract-form-grid {
    margin: -2px !important;
  }
  .contract-form-grid > :deep(.v-col) {
    padding: 2px !important;
  }
}

@media (min-width: 960px) {
  /* Match a full field to the combined visible width of two md-6 fields. */
  .contract-form-grid > :deep(.v-col-12:not([class*="v-col-md-"])),
  .contract-form-grid > :deep(.v-col-md-12) {
    padding-inline: 6px !important;
  }
}
</style>
