<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ApplicantSummary from '@/components/erp/ApplicantSummary.vue'
import ErpField from '@/components/erp/ErpField.vue'
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAbility } from '@casl/vue'
import { useToast } from 'vue-toastification'
import ContractApi, { type Contract, type ServiceContractOptions, serviceContractApplicantFields } from '@/api/contracts'
import { setContractPaymentOverride } from '@/api/incomes'
import type { ApplicantPayload, BuiltInService } from '@/api/applicants'
import VisitorApi, { visitorApplicantFields, type Visitor } from '@/api/visitor'
import ApplicantServiceFields from '@/components/ApplicantServiceFields.vue'
import ContractDocumentPrinter from '@/components/contract/ContractDocumentPrinter.vue'
import AcademySlipPrinter from '@/components/academy/AcademySlipPrinter.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { printDocument } from '@/utils/printDocument'
import { apiErrorMessage } from '@/utils/apiErrors'
import { useCookie } from '@core/composable/useCookie'

const route = useRoute()
const { t } = useI18n()
const ability = useAbility()
const toast = useToast()
const service = computed(() => route.meta.builtInService as BuiltInService)
const labelKey = computed(() => `educational.builtInServices.${service.value === 'test_offer' ? 'testOffer' : service.value}`)
const rows = ref<Contract[]>([])
const loading = ref(false)
const error = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const search = ref('')
const status = ref<string | null>(null)
const open = ref(false)
const saving = ref(false)
const preparing = ref(false)
const form = ref<ApplicantPayload>({})
const currencies = ref<ServiceContractOptions['currencies']>([])
const languages = ref<ServiceContractOptions['languages']>([])
const selectedTemplate = ref<ServiceContractOptions['template']>(null)
const admissionEmployees = ref<ServiceContractOptions['admission_employees']>([])
const counselorEmployees = ref<ServiceContractOptions['counselor_employees']>([])
const visitors = ref<Visitor[]>([])
const visitorId = ref<number | null>(null)
const charges = ref<Record<string, number | string>>({})
// Private contracts price the application fee as a range; every other service
// keeps a single itemised charge.
const applicationFeeRange = ref<[number, number]>([0, 0])
const paidFee = ref<number | string>(0)
const chargeFields = computed(() => service.value === 'private' ? ['visa_fee'] : service.value === 'scholarship' ? ['application_fee'] : service.value === 'test_offer' ? ['booking_fee', 'service_fee', 'remote_fee'] : ['service_fee'])
const chargeLabel = (field: string) => t(`erp.${field.replace(/_([a-z])/g, (_, letter: string) => letter.toUpperCase())}`)
const fee = computed(() => service.value === 'private'
  ? Number(applicationFeeRange.value[1] || 0) + Number(charges.value.visa_fee || 0)
  : chargeFields.value.reduce((sum, field) => sum + Number(charges.value[field] || 0), 0))
const feeMin = computed(() => service.value === 'private'
  ? Number(applicationFeeRange.value[0] || 0) + Number(charges.value.visa_fee || 0)
  : fee.value)
const applicationFeeCeiling = computed(() => Math.max(10000, Math.ceil(Number(applicationFeeRange.value[1] || 0) / 1000) * 1000 + 1000))
const canRecordIncome = computed(() => ability.can('read', 'create_incomes'))
const formRef = ref()
const discount = ref<number | string>(0)
const currencyId = ref<number | null>(null)
const rate = ref<number | string>(1)
const languageId = ref<number | null>(null)
const selectedVersionId = ref<number | null>(null)
const parent = ref<Contract | null>(null)
const printing = ref(false)
const printable = ref<Contract | null>(null)
const slipOpen = ref(false)
const slipContractId = ref<number | null>(null)
// Cancelling never deletes a contract: the record stays in this list with a
// cancelled label and stays available on the cancelled contracts page.
const cancelTarget = ref<Contract | null>(null)
const cancelOpen = ref(false)
const cancelling = ref(false)
let generation = 0
let formGeneration = 0
// Test-offer terms vary by the applicant's test; every other service version is
// scoped by its destination country (or none at all for academy).
const compatibleVersions = computed(() => selectedTemplate.value?.versions?.filter(version => {
  if (version.status !== 'active' || version.language_id !== languageId.value)
    return false
  if (service.value === 'test_offer')
    return version.test_id === (form.value.test_id ?? parent.value?.applicant?.details?.test_id ?? null)

  const countryId = ['scholarship', 'private'].includes(service.value)
    ? (parent.value?.applicant?.details?.country_id ?? form.value.country_id ?? null)
    : null

  return version.country_id === countryId
}) ?? [])
const versionItems = computed(() => compatibleVersions.value.map(version => ({
  title: `${version.name} · v${version.version_number}${version.is_current ? ` · ${t('serviceContracts.currentVersion')}` : ''}`,
  value: version.id,
})))
const selectedVersion = computed(() => compatibleVersions.value.find(version => version.id === selectedVersionId.value) ?? null)
const reportRows = computed(() => visible.value.map(row => [row.contract_number, row.counterparty, row.counterparty_contact,
  row.snapshot_data?.template_version ? `${row.snapshot_data.template_version.name} · v${row.snapshot_data.template_version.version_number}` : '—', row.services?.map(line => linePrice(line)).join(', '), row.start_date, row.status]))
const printList = () => printDocument({ bodyClass: 'printing-table-service-contracts', pageStyle: 'size: A4 landscape; margin: 12mm;' })
const openSlip = (row: Contract) => { slipContractId.value = row.id; slipOpen.value = true }
const canCancel = (row: Contract) => ability.can('read', 'cancel_contracts')
  && !row.is_cancelled && ['draft', 'active'].includes(row.status)
const requestCancel = (row: Contract) => { cancelTarget.value = row; cancelOpen.value = true }

// A private application fee is a range. The displayed price is the whole
// service (range + visa fee); the payable ceiling is the range maximum.
const linePrice = (line: NonNullable<Contract['services']>[number]) => {
  const currency = line.currency?.code ?? ''
  if (line.application_fee_min != null && line.application_fee_max != null) {
    const min = Number(line.application_fee_min) + Number(line.visa_fee ?? 0)
    const max = Number(line.application_fee_max) + Number(line.visa_fee ?? 0)

    return `${min}–${max} ${currency}`.trim()
  }

  return `${line.fee} ${currency}`.trim()
}
const lineRangePaid = (line: NonNullable<Contract['services']>[number]) => {
  if (line.application_fee_min == null)
    return false
  const discount = Number(line.discount ?? 0)
  const min = Math.max(0, Number(line.fee ?? 0) - Number(line.application_fee_max ?? line.fee ?? 0) + Number(line.application_fee_min) - discount)
  const max = Math.max(0, Number(line.fee ?? 0) - discount)
  const paid = Number(line.amount_paid ?? 0)

  return paid > 0 && paid >= min - 0.00001 && paid <= max + 0.00001
}
const canMarkPaid = (row: Contract) => service.value === 'private'
  && ability.can('read', 'override_contract_payments')
  && !row.is_cancelled
  && (row.services ?? []).some(line => line.payment_status !== 'paid' && lineRangePaid(line))
const markingPaid = ref<number | null>(null)
async function markPaid(row: Contract) {
  const line = (row.services ?? []).find(item => item.payment_status !== 'paid' && lineRangePaid(item))
  if (!line)
    return
  markingPaid.value = row.id
  try {
    await setContractPaymentOverride(line.id, { amount_paid: null, payment_status: 'paid' })
    await load()
    toast.success(t('serviceContracts.markedPaid'))
  }
  catch (cause) { toast.error(apiErrorMessage(cause)) }
  finally { markingPaid.value = null }
}

async function confirmCancel() {
  const target = cancelTarget.value
  if (!target)
    return

  cancelOpen.value = false
  cancelling.value = true
  try {
    await ContractApi.cancel(target.id)
    await load()
    toast.success(t('serviceContracts.cancelledDone'))
  }
  catch (cause) { toast.error(apiErrorMessage(cause)) }
  finally { cancelling.value = false; cancelTarget.value = null }
}
const resetFilters = () => { search.value = ''; status.value = null; dateFrom.value = ''; dateTo.value = ''; void load() }
const visible = computed(() => rows.value.filter(row => (!status.value || row.status === status.value)
  && `${row.contract_number} ${row.counterparty}`.toLocaleLowerCase().includes(search.value.toLocaleLowerCase())))
const statuses = computed(() => ['active', 'completed', 'terminated', 'cancelled'].map(value => ({ value, title: t(`serviceContracts.${value}`) })))
const headers = computed(() => [
  { title: t('serviceContracts.number'), key: 'contract_number' },
  { title: t('serviceContracts.applicant'), key: 'counterparty' },
  { title: t('erp.phone'), key: 'counterparty_contact' },
  { title: t('agencyStaff.contractVersion'), key: 'version', sortable: false },
  { title: t('erp.details'), key: 'details', sortable: false },
  { title: t('erp.totalFee'), key: 'charges', sortable: false },
  { title: t('serviceContracts.startDate'), key: 'start_date' },
  { title: t('serviceContracts.status'), key: 'status' },
  { title: t('serviceContracts.actions'), key: 'actions', sortable: false },
])
const valid = computed(() => !!selectedVersion.value && !preparing.value && (parent.value || form.value.name?.trim()) && currencyId.value && languageId.value
  && chargeFields.value.every(field => Number.isFinite(Number(charges.value[field])) && Number(charges.value[field]) >= 0)
  && (service.value !== 'private' || (Number.isFinite(applicationFeeRange.value[0]) && Number.isFinite(applicationFeeRange.value[1]) && applicationFeeRange.value[0] >= 0 && applicationFeeRange.value[1] >= applicationFeeRange.value[0]))
  && Number.isFinite(Number(fee.value)) && Number(fee.value) >= 0
  && Number.isFinite(Number(discount.value)) && Number(discount.value) >= 0 && Number(discount.value) <= Number(fee.value)
  && (service.value !== 'academy' || !canRecordIncome.value || !Number(paidFee.value) || (Number(paidFee.value) >= 0 && Number(paidFee.value) <= Number(fee.value)))
  && Number.isFinite(Number(rate.value)) && Number(rate.value) > 0)

async function load() {
  const current = ++generation
  loading.value = true
  error.value = ''
  try {
    const applicant = Number(route.query.applicant)
    const result = await ContractApi.getAll({ service: service.value, date_from: dateFrom.value || undefined, date_to: dateTo.value || undefined, ...(Number.isSafeInteger(applicant) && applicant > 0 ? { applicant_id: applicant } : {}) })
    if (current === generation) rows.value = result
    if (current === generation && route.query.contractId) {
      const contract = result.find(row => row.id === Number(route.query.contractId))
      if (contract) search.value = contract.contract_number || contract.counterparty || ''
    }
  }
  catch (cause) { if (current === generation) error.value = apiErrorMessage(cause) }
  finally { if (current === generation) loading.value = false }
}

// An academy extension is issued to the same applicant, so the form is filled
// in from the parent contract and every field stays editable.
const parentApplicantPayload = (contract: Contract): ApplicantPayload => {
  const applicant = contract.applicant
  const details = applicant?.details ?? {}
  const identity: Record<string, unknown> = { service: service.value }
  for (const field of ['name', 'father_name', 'father_phone', 'backup_phone', 'contact', 'email', 'address', 'tax_number', 'notes', 'admission_employee_id', 'responsible_employee_id'] as const)
    identity[field] = applicant?.[field] ?? null

  return {
    ...identity,
    student_id: details.student_id ?? null,
    program_id: details.program_id ?? null,
    time_id: details.time_id ?? null,
    duration_id: details.duration_id ?? null,
    class_id: details.class_id ?? null,
    test_id: details.test_id ?? null,
    country_id: details.country_id ?? null,
    university_id: details.university_id ?? null,
    education_level_id: details.education_level_id ?? null,
    semester_id: details.semester_id ?? null,
    scholarship_id: details.scholarship_id ?? null,
    passport_id: details.passport_id ?? null,
    grade_score: details.grade_score ?? null,
    university_fee: details.university_fee ?? null,
    favorite_subject_ids: applicant?.favorite_subject_ids ?? [],
    skill_ids: applicant?.skill_ids ?? [],
    teacher_ids: applicant?.teacher_ids ?? [],
  } as ApplicantPayload
}

async function create(extension: Contract | null = null) {
  const current = ++formGeneration
  parent.value = extension
  const extensionLine = extension?.services?.[0] ?? null
  const extendingAcademy = extension !== null && service.value === 'academy'
  form.value = extendingAcademy && extension
    ? parentApplicantPayload(extension)
    : { service: service.value, name: '', contact: '', email: '' }
  visitorId.value = null
  visitors.value = []
  charges.value = Object.fromEntries(chargeFields.value.map(field => [field, Number((extensionLine as Record<string, unknown> | null)?.[field] ?? 0)]))
  const lineMin = Number(extensionLine?.application_fee_min ?? 0)
  const lineMax = Number(extensionLine?.application_fee_max ?? extensionLine?.application_fee ?? 0)
  applicationFeeRange.value = [lineMin, Math.max(lineMin, lineMax)]
  paidFee.value = 0
  discount.value = Number(extensionLine?.discount ?? 0)
  currencyId.value = extensionLine?.currency_id ?? null
  rate.value = Number(extensionLine?.currency_rate ?? 1)
  selectedTemplate.value = null
  languageId.value = extension?.contract_language_id ?? null
  selectedVersionId.value = extension?.contract_template_version_id ?? null
  open.value = true
  preparing.value = true
  error.value = ''
  try {
    const options = await ContractApi.serviceOptions(service.value)
    if (current !== formGeneration) return
    currencies.value = options.currencies
    languages.value = options.languages
    selectedTemplate.value = options.template
    admissionEmployees.value = options.admission_employees
    counselorEmployees.value = options.counselor_employees
    // The parent line's rate wins; fall back to the currency's current rate.
    if (extension)
      rate.value = Number(extensionLine?.currency_rate ?? currencies.value.find(row => row.id === currencyId.value)?.rate ?? 1)
    // A counselor creating a contract owns its case: preselect themselves when
    // they are in the counselor list so the field is not left blank.
    // Academy enrolments have no associated employee, so they are never
    // pre-filled with the signed-in counselor.
    if (!extension && service.value !== 'academy') {
      const userData = useCookie<{ roles?: string[]; employee?: { id?: number } | null }>('userData').value
      const employeeId = userData?.employee?.id
      const isCounselor = (userData?.roles ?? []).some(role => role === 'counselor' || role === 'senior_counselor')
      if (isCounselor && employeeId && counselorEmployees.value.some(row => row.id === employeeId))
        form.value.responsible_employee_id = employeeId
    }
    if (!extension && ability.can('read', 'view_visitors')) {
      const visitorRows = await VisitorApi.getAll()
      const preselected = Number(route.query.visitor)
      let visitor: Visitor | undefined
      if (Number.isSafeInteger(preselected) && preselected > 0)
        visitor = visitorRows.find(row => row.id === preselected) ?? await VisitorApi.getById(preselected)
      if (current !== formGeneration) return
      visitors.value = visitorRows
      if (visitor) {
        if (!visitors.value.some(row => row.id === visitor!.id)) visitors.value.push(visitor)
        visitorId.value = visitor.id
      }
    }
  }
  catch (cause) { if (current === formGeneration) error.value = apiErrorMessage(cause) }
  finally { if (current === formGeneration) preparing.value = false }
}

watch(visitorId, id => {
  const visitor = visitors.value.find(row => row.id === id)
  if (visitor)
    form.value = { ...form.value, ...visitorApplicantFields(visitor) }
})
watch(currencyId, id => { rate.value = currencies.value.find(row => row.id === id)?.rate ?? 1 })
watch(compatibleVersions, versions => {
  if (!versions.some(version => version.id === selectedVersionId.value))
    selectedVersionId.value = versions.find(version => version.is_current)?.id ?? versions[0]?.id ?? null
}, { immediate: true })
watch(() => [service.value, route.query.visitor, route.query.applicant, route.query.contractId], async () => {
  ++formGeneration
  open.value = false
  rows.value = []
  search.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  await load()
  if (route.query.visitor && ability.can('read', 'create_contracts')) await create()
}, { immediate: true })

async function save() {
  if (!valid.value || saving.value || !(await formRef.value?.validate())?.valid) return
  const selectedService = service.value
  saving.value = true
  error.value = ''
  try {
    // An academy extension keeps its applicant editable, so the edited identity
    // and programme fields travel with the parent reference.
    const result = await ContractApi.createService(selectedService, {
      ...(parent.value
        ? {
            parent_contract_id: parent.value.id,
            ...(selectedService === 'academy' ? serviceContractApplicantFields(selectedService, form.value, ability.can('read', 'assign_admission_employee')) : {}),
          }
        : serviceContractApplicantFields(selectedService, form.value, ability.can('read', 'assign_admission_employee'))),
      ...Object.fromEntries(chargeFields.value.map(field => [field, Number(charges.value[field])])),
      ...(selectedService === 'private' ? {
        application_fee_min: Number(applicationFeeRange.value[0]),
        application_fee_max: Number(applicationFeeRange.value[1]),
        application_fee: Number(applicationFeeRange.value[1]),
      } : {}),
      ...(selectedService === 'academy' && canRecordIncome.value && Number(paidFee.value) > 0 ? { paid_fee: Number(paidFee.value) } : {}),
      fee: Number(fee.value), discount: Number(discount.value), currency_id: currencyId.value!,
      currency_rate: Number(rate.value), language_id: languageId.value!, contract_template_version_id: selectedVersionId.value!,
    })
    if (service.value === selectedService) {
      rows.value.unshift(result)
      open.value = false
    }
    toast.success(t('serviceContracts.created'))
  }
  catch (cause) { error.value = apiErrorMessage(cause) }
  finally { saving.value = false }
}
</script>

<template>
  <section class="erp-workspace">
    <ErpPageHeader :title="t(labelKey)" section="contracts" />
    <VAlert v-if="error && !open" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
    <VCard>
      <VCardText>
        <!-- Same toolbar shape as the other contract lists: the filter menu,
             print action and primary action live inside the data table's
             card rather than a detached row above it. -->
        <VRow align="center" class="table-toolbar">
          <VCol cols="12" class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2 ms-auto">
            <DataTableFilterMenu :active="Boolean(search || status || dateFrom || dateTo)" @reset="resetFilters">
              <VTextField v-model="search" class="data-table-search" prepend-inner-icon="tabler-search" :label="t('serviceContracts.search')" clearable @click:clear="search = ''" />
              <VSelect v-model="status" :items="statuses" :label="t('serviceContracts.status')" clearable />
              <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" @apply="load" />
            </DataTableFilterMenu>
            <VBtn icon="tabler-printer" variant="text" class="flex-shrink-0" :aria-label="t('serviceContracts.print')" @click="printList"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('serviceContracts.print') }}</VTooltip></VBtn>
            <VBtn v-if="!route.query.applicant && ability.can('read', 'create_contracts')" color="primary" class="flex-shrink-0" prepend-icon="tabler-plus" @click="create()">{{ t('serviceContracts.create') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <VDataTable :headers="headers" :items="visible" :loading="loading">
      <template #loading><TableLoadingSkeleton :columns="headers.length" :label="t('common.messages.loading')" /></template>
      <template #no-data><TableEmptyState icon="tabler-file-description" :description="t('serviceContracts.empty')" /></template>
      <template #item.version="{ item }">{{ item.snapshot_data?.template_version?.name ?? '—' }}<span v-if="item.snapshot_data?.template_version?.version_number"> · v{{ item.snapshot_data.template_version.version_number }}</span></template>
      <template #item.details="{ item }"><ApplicantSummary v-if="item.applicant" :applicant="item.applicant" :lines="item.services" /></template>
      <template #item.charges="{ item }"><span v-for="line in item.services" :key="line.id">{{ linePrice(line) }}</span></template>
      <template #item.status="{ item }"><VChip size="small" :color="item.is_cancelled ? 'error' : 'success'">{{ t(`serviceContracts.${item.status}`) }}</VChip></template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <FullDetailsButton :item="item" :title="item.contract_number || item.counterparty || ''" />
          <!-- A cancelled contract keeps its history in this list: it stays
               viewable but loses every other action, in particular printing. -->
          <template v-if="!item.is_cancelled">
            <VBtn v-if="service !== 'academy'" icon="tabler-printer" size="small" variant="text" @click="printable = item; printing = true"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('serviceContracts.print') }}</VTooltip></VBtn>
            <VBtn v-if="service === 'academy'" icon="tabler-school" size="small" variant="text" @click="openSlip(item)"><VIcon icon="tabler-school" /><VTooltip activator="parent">{{ t('documents.academySlip.print') }}</VTooltip></VBtn>
            <VBtn v-if="service === 'academy' && ability.can('read', 'create_contracts') && item.contract_role === 'primary' && ['active', 'completed'].includes(item.status)" icon="tabler-file-plus" size="small" variant="text" @click="create(item)"><VIcon icon="tabler-file-plus" /><VTooltip activator="parent">{{ t('serviceContracts.extend') }}</VTooltip></VBtn>
            <VBtn v-if="canMarkPaid(item)" icon="tabler-checks" size="small" variant="text" color="success" :loading="markingPaid === item.id" @click="markPaid(item)"><VIcon icon="tabler-checks" /><VTooltip activator="parent">{{ t('serviceContracts.markPaid') }}</VTooltip></VBtn>
            <VBtn v-if="canCancel(item)" icon="tabler-ban" size="small" variant="text" color="error" @click="requestCancel(item)"><VIcon icon="tabler-ban" /><VTooltip activator="parent">{{ t('serviceContracts.cancelContract') }}</VTooltip></VBtn>
          </template>
        </div>
      </template>
      </VDataTable>
    </VCard>
    <VDialog v-model="open" :persistent="saving" max-width="1240" scrollable class="erp-form service-contract-dialog">
      <VCard>
        <VCardTitle class="service-contract-header">
          <div class="min-w-0"><div class="text-h6">{{ t(labelKey) }}</div><div class="text-caption text-medium-emphasis text-truncate">{{ selectedTemplate?.name ?? t('agencyStaff.templateNotReady') }}</div></div>
          <VSpacer />
          <VSelect v-model="selectedVersionId" :items="versionItems" :label="versionItems.length ? t('agencyStaff.contractVersion') : t('agencyStaff.noMatchingVersion')" density="compact" variant="outlined" hide-details :disabled="preparing || !versionItems.length" class="contract-version-select" />
          <VBtn icon="tabler-x" variant="text" :disabled="saving" :aria-label="t('serviceContracts.close')" @click="open = false"><VIcon icon="tabler-x" /><VTooltip activator="parent">{{ t('serviceContracts.close') }}</VTooltip></VBtn>
        </VCardTitle>
        <VCardText class="service-contract-body">
          <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
          <div v-if="parent" class="mb-4">{{ parent.contract_number }}: {{ parent.counterparty }}</div>
          <VForm ref="formRef" @submit.prevent="save">
            <template v-if="!parent || service === 'academy'">
              <VRow v-if="!parent && ability.can('read', 'view_visitors')" dense><VCol cols="12" sm="6" md="4"><VAutocomplete v-model="visitorId" :items="visitors" item-title="name" item-value="id" :label="t('serviceContracts.visitor')" :disabled="preparing" density="compact" clearable /></VCol></VRow>
              <VRow dense class="mb-3">
                <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="form.name" :label="service === 'academy' ? t('serviceContracts.studentFullName') : t('serviceContracts.applicant')" maxlength="255" required /></VCol>
                <VCol v-if="['scholarship', 'private'].includes(service)" cols="12" sm="6" md="3"><ErpField v-model="form.father_name" :label="t('serviceContracts.fatherName')" maxlength="255" required /></VCol>
                <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="form.contact" :label="t('erp.phone')" maxlength="255" required /></VCol>
                <VCol v-if="['scholarship', 'private'].includes(service)" cols="12" sm="6" md="3"><ErpField v-model="form.father_phone" :label="t('serviceContracts.fatherPhone')" maxlength="50" required /></VCol>
                <VCol v-if="service === 'test_offer'" cols="12" sm="6" md="4"><ErpField v-model="form.backup_phone" :label="t('erp.backupPhone')" maxlength="50" required /></VCol>
                <VCol v-if="!parent && service === 'private'" cols="12" sm="6" md="3"><ErpField v-model="form.email" :label="t('serviceContracts.email')" type="email" maxlength="255" /></VCol>
                <VCol v-if="!parent && service === 'private'" cols="12" sm="6" md="3"><ErpField v-model="form.address" :label="t('serviceContracts.address')" maxlength="1000" /></VCol>
                <VCol v-if="!parent" cols="12" sm="6" md="3"><ErpField v-model="form.responsible_employee_id" kind="select" :items="counselorEmployees" item-title="name" item-value="id" :label="t('serviceContracts.consultant')" clearable /></VCol>
                <VCol v-if="!parent && ['scholarship', 'private'].includes(service)" cols="12" sm="6" md="3"><ErpField v-model="form.admission_employee_id" kind="select" :items="admissionEmployees" item-title="name" item-value="id" :label="t('agencyStaff.admission')" :disabled="!ability.can('read', 'assign_admission_employee')" clearable /></VCol>
              </VRow>
              <ApplicantServiceFields v-model="form" v-model:language-id="languageId" fixed-service show-language :languages="languages" />
            </template>
            <VRow dense>
              <VCol v-if="service === 'private'" cols="12" md="6">
                <label class="erp-field-label">{{ t('serviceContracts.applicationFeeRange') }} <span class="text-error" aria-hidden="true">*</span></label>
                <VRangeSlider
                  v-model="applicationFeeRange"
                  class="mt-2"
                  :min="0"
                  :max="applicationFeeCeiling"
                  :step="10"
                  thumb-label="always"
                  color="primary"
                />
                <div class="text-caption text-medium-emphasis">
                  {{ applicationFeeRange[0] }} – {{ applicationFeeRange[1] }} {{ currencies.find(row => row.id === currencyId)?.code }}
                </div>
              </VCol>
              <VCol v-for="field in chargeFields" :key="field" cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="charges[field]" type="number" min="0" step="0.01" :label="chargeLabel(field)" required /></VCol>
              <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="currencyId" kind="select" :items="currencies" item-title="code" item-value="id" :label="t('serviceContracts.currency')" required /></VCol>
              <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="rate" type="number" min="0.00000001" step="0.00000001" :label="t('serviceContracts.rate')" required /></VCol>
              <VCol class="service-contract-discount" cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="discount" type="number" min="0" :max="fee" step="0.01" :label="t('serviceContracts.discount')" /></VCol>
              <VCol v-if="service === 'academy' && canRecordIncome" cols="12" sm="6" md="3"><ErpField v-model="paidFee" type="number" min="0" :max="fee" step="0.01" :label="t('serviceContracts.paidFee')" :hint="t('serviceContracts.paidFeeHint')" persistent-hint /></VCol>
              <VCol v-if="['scholarship', 'private'].includes(service)" cols="12" md="6"><ErpField v-model="form.notes" kind="textarea" rows="1" auto-grow :label="t('erp.admissionNotes')" maxlength="10000" /></VCol>
            </VRow>
            <div class="service-contract-footer">
              <div class="d-flex flex-wrap align-center ga-4"><strong>{{ t('erp.totalFee') }}: {{ feeMin.toFixed(2) }}<template v-if="service === 'private'"> – {{ fee.toFixed(2) }}</template> {{ currencies.find(row => row.id === currencyId)?.code }}</strong><span class="text-medium-emphasis">{{ t('serviceContracts.discount') }}: {{ discount }}</span></div>
              <div class="d-flex ga-2"><VBtn color="secondary" variant="tonal" :disabled="saving" @click="open = false">{{ t('erp.cancel') }}</VBtn><VBtn type="submit" color="primary" prepend-icon="tabler-check" :disabled="!valid || saving">{{ t('erp.save') }}</VBtn></div>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
    <PrintableListReport :title="t(labelKey)" :subtitle="[search, status, dateFrom, dateTo].filter(Boolean).join(' · ')" :columns="[t('serviceContracts.number'), t('serviceContracts.applicant'), t('erp.phone'), t('agencyStaff.contractVersion'), t('erp.totalFee'), t('serviceContracts.startDate'), t('serviceContracts.status')]" :rows="reportRows" />
    <ContractDocumentPrinter v-model="printing" :contract="printable" />
    <AcademySlipPrinter v-model="slipOpen" :contract-id="slipContractId" />
    <VDialog v-model="cancelOpen" max-width="460">
      <VCard>
        <VCardTitle>{{ t('serviceContracts.cancelContract') }}</VCardTitle>
        <VCardText>{{ t('serviceContracts.cancelConfirmation', { contract: cancelTarget?.contract_number || cancelTarget?.counterparty || '' }) }}</VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="cancelOpen = false">{{ t('common.actions.cancel') }}</VBtn>
          <VBtn color="error" :disabled="cancelling" @click="confirmCancel">{{ t('serviceContracts.cancelContract') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </section>
</template>

<style scoped>
.service-contract-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-block: 10px;
}

.contract-version-select {
  flex: 0 1 360px;
  min-inline-size: 240px;
}

.service-contract-body {
  padding-block: 10px 12px;
}

.service-contract-discount {
  display: none;
}

.service-contract-body :deep(.v-row) {
  margin-block: -3px;
}

.service-contract-body :deep(.v-col) {
  padding-block: 3px;
}

.service-contract-footer {
  position: sticky;
  z-index: 2;
  bottom: -12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-block-start: 8px;
  padding-block: 10px;
  background: rgb(var(--v-theme-surface));
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

@media (max-width: 600px) {
  .service-contract-header,
  .service-contract-footer {
    align-items: stretch;
    flex-direction: column;
  }

  .contract-version-select {
    flex-basis: auto;
    inline-size: 100%;
    min-inline-size: 0;
  }
}
</style>
