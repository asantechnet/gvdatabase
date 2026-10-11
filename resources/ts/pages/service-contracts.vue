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
import type { ApplicantPayload, BuiltInService } from '@/api/applicants'
import VisitorApi, { visitorApplicantFields, type Visitor } from '@/api/visitor'
import ApplicantServiceFields from '@/components/ApplicantServiceFields.vue'
import ContractDocumentPrinter from '@/components/contract/ContractDocumentPrinter.vue'
import AcademySlipPrinter from '@/components/academy/AcademySlipPrinter.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
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
const chargeFields = computed(() => service.value === 'private' ? ['application_fee', 'visa_fee'] : service.value === 'scholarship' ? ['application_fee'] : service.value === 'test_offer' ? ['booking_fee', 'service_fee', 'remote_fee'] : ['service_fee'])
const chargeLabel = (field: string) => t(`erp.${field.replace(/_([a-z])/g, (_, letter: string) => letter.toUpperCase())}`)
const fee = computed(() => chargeFields.value.reduce((sum, field) => sum + Number(charges.value[field] || 0), 0))
const formRef = ref()
const discount = ref<number | string>(0)
const currencyId = ref<number | null>(null)
const rate = ref<number | string>(1)
const languageId = ref<number | null>(null)
const startDate = ref('')
const parent = ref<Contract | null>(null)
const printing = ref(false)
const printable = ref<Contract | null>(null)
const slipOpen = ref(false)
const slipContractId = ref<number | null>(null)
let generation = 0
let formGeneration = 0
// Test-offer terms vary by the applicant's test; every other service version is
// scoped by its destination country (or none at all for academy).
const matchingVersion = computed(() => selectedTemplate.value?.versions?.find(version => {
  if (version.status !== 'active' || !version.is_current || version.language_id !== languageId.value)
    return false
  if (service.value === 'test_offer')
    return version.test_id === (form.value.test_id ?? null)

  const countryId = ['scholarship', 'private'].includes(service.value)
    ? (parent.value?.applicant?.details?.country_id ?? form.value.country_id ?? null)
    : null

  return version.country_id === countryId
}) ?? null)
const reportRows = computed(() => visible.value.map(row => [row.contract_number, row.counterparty, row.counterparty_contact,
  row.snapshot_data?.template_version ? `${row.snapshot_data.template_version.name} · v${row.snapshot_data.template_version.version_number}` : '—', row.services?.map(line => `${line.fee} ${line.currency?.code ?? ''}`).join(', '), row.start_date, row.status]))
const printList = () => printDocument({ bodyClass: 'printing-table-service-contracts', pageStyle: 'size: A4 landscape; margin: 12mm;' })
const openSlip = (row: Contract) => { slipContractId.value = row.id; slipOpen.value = true }
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
const valid = computed(() => !!matchingVersion.value && !preparing.value && (parent.value || form.value.name?.trim()) && currencyId.value && languageId.value
  && chargeFields.value.every(field => Number.isFinite(Number(charges.value[field])) && Number(charges.value[field]) >= 0)
  && Number.isFinite(Number(fee.value)) && Number(fee.value) >= 0
  && Number.isFinite(Number(discount.value)) && Number(discount.value) >= 0 && Number(discount.value) <= Number(fee.value)
  && Number.isFinite(Number(rate.value)) && Number(rate.value) > 0 && startDate.value)

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

async function create(extension: Contract | null = null) {
  const current = ++formGeneration
  parent.value = extension
  form.value = { service: service.value, name: '', contact: '', email: '' }
  visitorId.value = null
  visitors.value = []
  charges.value = Object.fromEntries(chargeFields.value.map(field => [field, 0]))
  discount.value = 0
  currencyId.value = null
  rate.value = 1
  selectedTemplate.value = null
  languageId.value = null
  const today = new Date()
  startDate.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
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
    // A counselor creating a contract owns its case: preselect themselves when
    // they are in the counselor list so the field is not left blank.
    if (!extension) {
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
    const result = await ContractApi.createService(selectedService, {
      ...(parent.value ? { parent_contract_id: parent.value.id } : serviceContractApplicantFields(selectedService, form.value)),
      ...Object.fromEntries(chargeFields.value.map(field => [field, Number(charges.value[field])])),
      fee: Number(fee.value), discount: Number(discount.value), currency_id: currencyId.value!,
      currency_rate: Number(rate.value), language_id: languageId.value!, start_date: startDate.value,
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
    <ErpPageHeader :title="t(labelKey)" section="contracts">
      <template #actions><VBtn v-if="!route.query.applicant && ability.can('read', 'create_contracts')" color="primary" prepend-icon="tabler-plus" @click="create()">{{ t('serviceContracts.create') }}</VBtn></template>
    </ErpPageHeader>
    <VAlert v-if="error && !open" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
    <div class="d-flex align-center ga-3 mb-4">
      <VSpacer />
      <DataTableFilterMenu :active="Boolean(search || status || dateFrom || dateTo)" @reset="resetFilters">
        <VTextField v-model="search" prepend-inner-icon="tabler-search" :label="t('serviceContracts.search')" clearable @click:clear="search = ''" />
        <VSelect v-model="status" :items="statuses" :label="t('serviceContracts.status')" clearable />
        <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" @apply="load" />
      </DataTableFilterMenu>
      <VBtn icon="tabler-printer" variant="text" :aria-label="t('serviceContracts.print')" @click="printList"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('serviceContracts.print') }}</VTooltip></VBtn>
    </div>
    <VDataTable :headers="headers" :items="visible" :loading="loading" :no-data-text="t('serviceContracts.empty')">
      <template #item.version="{ item }">{{ item.snapshot_data?.template_version?.name ?? '—' }}<span v-if="item.snapshot_data?.template_version?.version_number"> · v{{ item.snapshot_data.template_version.version_number }}</span></template>
      <template #item.details="{ item }"><ApplicantSummary v-if="item.applicant" :applicant="item.applicant" :lines="item.services" /></template>
      <template #item.charges="{ item }"><span v-for="line in item.services" :key="line.id">{{ line.fee }} {{ line.currency?.code }}</span></template>
      <template #item.status="{ item }"><VChip size="small" :color="item.is_cancelled ? 'error' : 'success'">{{ t(`serviceContracts.${item.status}`) }}</VChip></template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <VBtn icon="tabler-printer" size="small" variant="text" @click="printable = item; printing = true"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('serviceContracts.print') }}</VTooltip></VBtn>
          <VBtn v-if="service === 'academy'" icon="tabler-school" size="small" variant="text" @click="openSlip(item)"><VIcon icon="tabler-school" /><VTooltip activator="parent">{{ t('documents.academySlip.print') }}</VTooltip></VBtn>
          <VBtn v-if="ability.can('read', 'create_contracts') && item.contract_role === 'primary' && !item.is_cancelled && ['active', 'completed'].includes(item.status)" icon="tabler-file-plus" size="small" variant="text" @click="create(item)"><VIcon icon="tabler-file-plus" /><VTooltip activator="parent">{{ t('serviceContracts.extend') }}</VTooltip></VBtn>
        </div>
      </template>
    </VDataTable>
    <VDialog v-model="open" :persistent="saving" max-width="1100" scrollable class="erp-form">
      <VCard>
        <VCardTitle class="d-flex align-center flex-wrap gap-2">{{ t(labelKey) }}<VSpacer /><VBtn icon="tabler-x" variant="text" :disabled="saving" @click="open = false"><VIcon icon="tabler-x" /><VTooltip activator="parent">{{ t('serviceContracts.close') }}</VTooltip></VBtn></VCardTitle>
        <VCardText>
          <VProgressLinear v-if="preparing" indeterminate class="mb-4" />
          <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
          <div v-if="parent" class="mb-4">{{ parent.contract_number }}: {{ parent.counterparty }}</div>
          <VForm ref="formRef" @submit.prevent="save">
            <template v-if="!parent">
              <VAutocomplete v-if="ability.can('read', 'view_visitors')" v-model="visitorId" :items="visitors" item-title="name" item-value="id" :label="t('serviceContracts.visitor')" :disabled="preparing" clearable class="mb-4" />
              <VRow dense class="mb-3">
                <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="form.name" :label="t('serviceContracts.applicant')" maxlength="255" required /></VCol>
                <VCol cols="12" sm="6" md="3"><ErpField v-model="form.father_name" :label="t('serviceContracts.fatherName')" maxlength="255" /></VCol>
                <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="form.contact" :label="t('erp.phone')" maxlength="255" /></VCol>
                <VCol cols="12" sm="6" md="3"><ErpField v-model="form.father_phone" :label="t('serviceContracts.fatherPhone')" maxlength="50" /></VCol>
                <VCol v-if="service === 'test_offer'" cols="12" sm="6" md="4"><ErpField v-model="form.backup_phone" :label="t('erp.backupPhone')" maxlength="50" /></VCol>
              </VRow>
              <ApplicantServiceFields v-model="form" fixed-service />
            </template>
            <template v-if="service !== 'academy'">
              <VTextField :model-value="selectedTemplate?.name ?? t('agencyStaff.templateNotReady')" :label="t('common.messages.contract')" readonly class="mt-4" />
              <VTextField :model-value="matchingVersion ? `${matchingVersion.name} · v${matchingVersion.version_number}` : t('agencyStaff.noMatchingVersion')" :label="t('agencyStaff.contractVersion')" readonly class="mt-4" />
              <VAlert v-if="!preparing && !matchingVersion" type="warning" variant="tonal" class="mt-3">{{ t('agencyStaff.noMatchingVersion') }}</VAlert>
              <VCol v-if="!parent" cols="12"><ErpField v-model="form.admission_employee_id" kind="select" :items="admissionEmployees" item-title="name" item-value="id" :label="t('agencyStaff.admission')" clearable /></VCol>
              <VDivider class="my-4" />
            </template>
            <template v-if="service === 'academy'">
              <VDivider class="my-4" />
            </template>
            <VRow dense>
              <VCol v-for="field in chargeFields" :key="field" cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="charges[field]" type="number" min="0" step="0.01" :label="chargeLabel(field)" required /></VCol>
              <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="currencyId" kind="select" :items="currencies" item-title="code" item-value="id" :label="t('serviceContracts.currency')" required /></VCol>
              <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="rate" type="number" min="0.00000001" step="0.00000001" :label="t('serviceContracts.rate')" required /></VCol>
              <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="languageId" kind="select" :items="languages" item-title="name" item-value="id" :label="t('serviceContracts.language')" required /></VCol>
              <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="startDate" type="date" :label="t('serviceContracts.startDate')" required /></VCol>
              <VCol cols="12" sm="6" :md="['scholarship', 'private'].includes(service) ? 3 : 4"><ErpField v-model="discount" type="number" min="0" :max="fee" step="0.01" :label="t('serviceContracts.discount')" /></VCol>
              <VCol cols="12"><ErpField v-model="form.notes" kind="textarea" rows="2" auto-grow :label="t('erp.admissionNotes')" maxlength="10000" /></VCol>
            </VRow>
            <VExpansionPanels v-if="!parent" variant="accordion" elevation="0" class="mt-3">
              <VExpansionPanel :title="t('erp.optionalDetails')"><VExpansionPanelText><VRow dense>
                <VCol cols="12" sm="6" md="4"><ErpField v-model="form.email" :label="t('serviceContracts.email')" type="email" maxlength="255" /></VCol>
                <VCol cols="12" sm="6" md="4"><ErpField v-model="form.address" :label="t('serviceContracts.address')" maxlength="1000" /></VCol>
                <VCol cols="12" sm="6" md="4"><ErpField v-model="form.responsible_employee_id" kind="select" :items="counselorEmployees" item-title="name" item-value="id" :label="t('serviceContracts.responsibleEmployee')" clearable /></VCol>
              </VRow></VExpansionPanelText></VExpansionPanel>
            </VExpansionPanels>
            <div class="d-flex align-center justify-space-between mt-4 mb-4"><strong>{{ t('erp.totalFee') }}: {{ fee.toFixed(2) }} {{ currencies.find(row => row.id === currencyId)?.code }}</strong><span>{{ t('serviceContracts.discount') }}: {{ discount }}</span></div>
            <div class="erp-form-actions"><VBtn color="secondary" variant="tonal" :disabled="saving" @click="open = false">{{ t('erp.cancel') }}</VBtn><VBtn type="submit" color="primary" prepend-icon="tabler-check" :disabled="!valid" :loading="saving">{{ t('erp.save') }}</VBtn></div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
    <PrintableListReport :title="t(labelKey)" :subtitle="[search, status, dateFrom, dateTo].filter(Boolean).join(' · ')" :columns="[t('serviceContracts.number'), t('serviceContracts.applicant'), t('erp.phone'), t('agencyStaff.contractVersion'), t('erp.totalFee'), t('serviceContracts.startDate'), t('serviceContracts.status')]" :rows="reportRows" />
    <ContractDocumentPrinter v-model="printing" :contract="printable" />
    <AcademySlipPrinter v-model="slipOpen" :contract-id="slipContractId" />
  </section>
</template>
