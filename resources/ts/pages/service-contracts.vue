<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAbility } from '@casl/vue'
import { useToast } from 'vue-toastification'
import ContractApi, { type Contract, type ServiceContractOptions, serviceContractApplicantFields } from '@/api/contracts'
import { setContractPaymentOverride } from '@/api/incomes'
import ApplicantPortalApi, { type ApplicantPortalCredentials } from '@/api/applicantPortal'
import type { ApplicantPayload, BuiltInService } from '@/api/applicants'
import VisitorApi, { visitorApplicantFields, type Visitor } from '@/api/visitor'
import ApplicantServiceFields from '@/components/ApplicantServiceFields.vue'
import ContractDocumentPrinter from '@/components/contract/ContractDocumentPrinter.vue'
import AcademySlipPrinter from '@/components/academy/AcademySlipPrinter.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import FullDetailsButton from '@/components/FullDetailsButton.vue'
import { printDocument } from '@/utils/printDocument'
import { apiErrorMessage } from '@/utils/apiErrors'
import { moveRowIdFirst } from '@/utils/createdFirst'
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
const contractVersionId = ref<number | null>(null)
const filterMenuOpen = ref(false)
const filterDraft = reactive({
  dateFrom: '',
  dateTo: '',
  status: null as string | null,
  contractVersionId: null as number | null,
})
const versionFilterOptions = ref<Array<{ id: number; name: string; version_number: number }>>([])
// The list asks the server for one page at a time.
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const sortByKey = ref<'contract_number' | 'counterparty' | 'start_date' | 'status' | 'created_at'>('start_date')
// Newest contracts should be the first rows an operator sees.
const sortDirection = ref<'asc' | 'desc'>('desc')
// Filled from every matching contract right before printing.
const reportRows = ref<(string | number | null | undefined)[][]>([])
// Typing in the search box is a filter change, so it starts from the first page.
const debouncedSearch = useDebounceFn(() => { page.value = 1; void load() }, 300)
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
// Private contracts use two direct fee inputs. They are clearer than a range
// slider and remain easy to review when exact amounts are needed.
const applicationFeeFrom = ref<number | string>(0)
const applicationFeeTo = ref<number | string>(0)
const paidFee = ref<number | string>(0)
// A portal login issued with a new contract. The credentials are generated and
// shown before saving, then created with exactly these values.
const createPortalUser = ref(false)
const portalCredentials = ref<ApplicantPortalCredentials | null>(null)
const generatingCredentials = ref(false)
const createdCredentials = ref<ApplicantPortalCredentials | null>(null)
const credentialsDialog = ref(false)
const chargeFields = computed(() => service.value === 'private' ? ['visa_fee'] : service.value === 'scholarship' ? ['application_fee'] : service.value === 'test_offer' ? ['booking_fee', 'service_fee', 'remote_fee'] : ['service_fee'])
const chargeLabel = (field: string) => t(`erp.${field.replace(/_([a-z])/g, (_, letter: string) => letter.toUpperCase())}`)
const fee = computed(() => service.value === 'private'
  ? Number(applicationFeeTo.value || 0) + Number(charges.value.visa_fee || 0)
  : chargeFields.value.reduce((sum, field) => sum + Number(charges.value[field] || 0), 0))
const feeMin = computed(() => service.value === 'private'
  ? Number(applicationFeeFrom.value || 0) + Number(charges.value.visa_fee || 0)
  : fee.value)
const canRecordIncome = computed(() => ability.can('read', 'create_incomes'))
const formRef = ref()
const wizardStep = ref<1 | 2 | 3>(1)
const discount = ref<number | string>(0)
const currencyId = ref<number | null>(null)
const rate = ref<number | string>(1)
const languageId = ref<number | null>(null)
const selectedVersionId = ref<number | null>(null)
const parent = ref<Contract | null>(null)
const editing = ref<Contract | null>(null)
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
  if (version.status !== 'active' || (languageId.value !== null && version.language_id !== languageId.value))
    return false
  if (service.value === 'test_offer')
    return version.test_id === (form.value.test_id ?? parent.value?.applicant?.details?.test_id ?? null)

  const countryId = ['scholarship', 'private'].includes(service.value)
    ? (parent.value?.applicant?.details?.country_id ?? form.value.country_id ?? null)
    : null

  return version.country_id === countryId
}) ?? [])
const versionItems = computed(() => {
  const items = compatibleVersions.value.map(version => ({
  title: `${version.name} · v${version.version_number}${version.is_current ? ` · ${t('serviceContracts.currentVersion')}` : ''}`,
  value: version.id,
  }))
  if (editing.value && selectedVersionId.value && !items.some(item => item.value === selectedVersionId.value))
    items.unshift({ title: t('serviceContracts.issuedVersion'), value: selectedVersionId.value })

  return items
})
const selectedVersion = computed(() => compatibleVersions.value.find(version => version.id === selectedVersionId.value) ?? null)
const wizardStepCount = computed(() => service.value === 'test_offer' ? 2 : 3)
const wizardSteps = computed(() => {
  const titles: Record<BuiltInService, string[]> = {
    private: ['wizard.applicantContact', 'wizard.academicDetails', 'wizard.financialAccess'],
    scholarship: ['wizard.applicantContact', 'wizard.academicDetails', 'wizard.financialAccess'],
    academy: ['wizard.studentContact', 'wizard.classProgram', 'wizard.financialAccess'],
    test_offer: ['wizard.applicantTest', 'wizard.financialAccess'],
  }

  return titles[service.value].map((title, index) => ({
    number: index + 1 as 1 | 2 | 3,
    title: t(`serviceContracts.${title}`),
  }))
})
const contractReportRow = (row: Contract) => [row.contract_number, row.counterparty, row.counterparty_contact,
  row.snapshot_data?.template_version ? `${row.snapshot_data.template_version.name} · v${row.snapshot_data.template_version.version_number}` : '—', row.services?.map(line => linePrice(line)).join(', '), row.start_date, row.status]
// Print is global: the report walks the filtered pages first.
const printList = async () => {
  try {
    const collected: Contract[] = []
    let nextPage = 1
    let lastPage = 1

    do {
      const result = await ContractApi.listPage({ ...listParams(), page: nextPage, perPage: 100 })

      collected.push(...result.rows)
      lastPage = result.lastPage
      nextPage += 1
    } while (nextPage <= lastPage)

    reportRows.value = collected.map(contractReportRow)
    await printDocument({ bodyClass: 'printing-table-service-contracts', pageStyle: 'size: A4 landscape; margin: 12mm;' })

    // Leave the table showing the page the operator was on.
    await load()
  }
  catch { /* API failure feedback belongs to the global notifier (Task-018). */ }
}
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
  catch { /* API failure feedback belongs to the global notifier (Task-018). */ }
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
  catch { /* API failure feedback belongs to the global notifier (Task-018). */ }
  finally { cancelling.value = false; cancelTarget.value = null }
}
const hasActiveFilters = computed(() => Boolean(status.value || dateFrom.value || dateTo.value || contractVersionId.value))
const syncFilterDraft = () => {
  filterDraft.dateFrom = dateFrom.value
  filterDraft.dateTo = dateTo.value
  filterDraft.status = status.value
  filterDraft.contractVersionId = contractVersionId.value
}
const applyFilters = () => {
  if (filterDraft.dateFrom && filterDraft.dateTo && filterDraft.dateFrom > filterDraft.dateTo)
    return

  dateFrom.value = filterDraft.dateFrom
  dateTo.value = filterDraft.dateTo
  status.value = filterDraft.status
  contractVersionId.value = filterDraft.contractVersionId
  filterMenuOpen.value = false
  page.value = 1
  void load()
}
const resetFilters = () => {
  status.value = null
  dateFrom.value = ''
  dateTo.value = ''
  contractVersionId.value = null
  syncFilterDraft()
  filterMenuOpen.value = false
  page.value = 1
  void load()
}
function updateTableOptions(options: { page: number; itemsPerPage: number; sortBy?: Array<{ key: string; order?: 'asc' | 'desc' }> }) {
  const sort = options.sortBy?.[0]
  const sortableKeys = ['contract_number', 'counterparty', 'start_date', 'status', 'created_at'] as const
  const nextSortBy = sortableKeys.includes(sort?.key as typeof sortableKeys[number]) ? sort?.key as typeof sortableKeys[number] : 'start_date'
  const nextSortDirection = sort?.order === 'desc' ? 'desc' : 'asc'
  if (page.value === options.page && perPage.value === options.itemsPerPage && sortByKey.value === nextSortBy && sortDirection.value === nextSortDirection)
    return

  page.value = options.page
  perPage.value = options.itemsPerPage
  sortByKey.value = nextSortBy
  sortDirection.value = nextSortDirection
  void load()
}
const statuses = computed(() => ['active', 'completed', 'terminated', 'cancelled'].map(value => ({ value, title: t(`serviceContracts.${value}`) })))
const headers = computed(() => [
  { title: t('serviceContracts.number'), key: 'contract_number' },
  { title: t('serviceContracts.applicant'), key: 'counterparty' },
  { title: t('erp.phone'), key: 'counterparty_contact', sortable: false },
  { title: t('agencyStaff.contractVersion'), key: 'version', sortable: false },
  { title: t('erp.totalFee'), key: 'charges', sortable: false },
  { title: t('serviceContracts.startDate'), key: 'start_date' },
  { title: t('serviceContracts.status'), key: 'status' },
  { title: t('serviceContracts.actions'), key: 'actions', sortable: false },
])
// Keep the dense contract record in the detail viewer, where every value has
// a label and relationships can be read in their own tab.
const contractDetailFields = computed(() => [
  { key: 'contract_number', label: t('serviceContracts.number') },
  { key: 'contract', label: t('common.messages.contract') },
  { key: 'service', label: t('educational.service') },
  { key: 'contract_role', label: t('serviceContracts.contractRole') },
  { key: 'type', label: t('educational.contractType') },
  { key: 'status', label: t('serviceContracts.status') },
  { key: 'start_date', label: t('serviceContracts.startDate') },
  { key: 'end_date', label: t('serviceContracts.endDate') },
  { key: 'counterparty', label: t('serviceContracts.applicant') },
  { key: 'counterparty_company', label: t('common.fields.company') },
  { key: 'counterparty_email', label: t('serviceContracts.email') },
  { key: 'counterparty_contact', label: t('serviceContracts.contact') },
  { key: 'counterparty_address', label: t('serviceContracts.address') },
  { key: 'company_signatory_name', label: t('serviceContracts.companySignatory') },
  { key: 'is_cancelled', label: t('serviceContracts.isCancelled') },
  { key: 'cancelled_at', label: t('serviceContracts.cancelledAt') },
  { key: 'applicant', label: t('serviceContracts.applicantDetails') },
  { key: 'contract_type', label: t('educational.contractType') },
  { key: 'services', label: t('educational.service') },
  { key: 'notes', label: t('erp.admissionNotes') },
  { key: 'created_at', label: t('serviceContracts.createdAt') },
  { key: 'updated_at', label: t('serviceContracts.updatedAt') },
])
const valid = computed(() => !!(editing.value ? selectedVersionId.value : selectedVersion.value) && !preparing.value && (parent.value || form.value.name?.trim()) && currencyId.value
  && chargeFields.value.every(field => Number.isFinite(Number(charges.value[field])) && Number(charges.value[field]) >= 0)
  && (service.value !== 'private' || (!applicationFeeFrom.value || Number(applicationFeeFrom.value) >= 0)
    && (!applicationFeeTo.value || Number(applicationFeeTo.value) >= 0)
    && (!applicationFeeFrom.value || !applicationFeeTo.value || Number(applicationFeeTo.value) >= Number(applicationFeeFrom.value)))
  && Number.isFinite(Number(fee.value)) && Number(fee.value) >= 0
  && Number.isFinite(Number(discount.value)) && Number(discount.value) >= 0 && Number(discount.value) <= Number(fee.value)
  && (service.value !== 'academy' || !canRecordIncome.value || !Number(paidFee.value) || (Number(paidFee.value) >= 0 && Number(paidFee.value) <= Number(fee.value)))
  && Number.isFinite(Number(rate.value)) && Number(rate.value) > 0
  && (!createPortalUser.value || !!portalCredentials.value))

const listParams = () => {
  const applicant = Number(route.query.applicant)

  return {
    service: service.value,
    status: (status.value || undefined) as Contract['status'] | undefined,
    search: search.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    contract_template_version_id: contractVersionId.value || undefined,
    sort_by: sortByKey.value,
    sort_direction: sortDirection.value,
    ...(Number.isSafeInteger(applicant) && applicant > 0 ? { applicant_id: applicant } : {}),
  }
}

async function loadVersionOptions() {
  try {
    versionFilterOptions.value = await ContractApi.versionOptions(service.value)
  }
  catch (cause) {
    // The table still works without this optional filter if an account can
    // read the register but cannot access a retired template record.
    console.error('Unable to load contract version filter options:', cause)
    versionFilterOptions.value = []
  }
}

async function load() {
  const current = ++generation
  loading.value = true
  error.value = ''
  try {
    const result = await ContractApi.listPage({ ...listParams(), page: page.value, perPage: perPage.value })

    if (current !== generation)
      return

    rows.value = result.rows
    total.value = result.total

    if (route.query.contractId) {
      // The deep-linked contract may live on another page, so it is fetched
      // directly when it is not in the visible page.
      const id = Number(route.query.contractId)
      const contract = result.rows.find(row => row.id === id) ?? await ContractApi.getById(id)

      if (current === generation && contract)
        search.value = contract.contract_number || contract.counterparty || ''
    }
  }
  catch (cause) { if (current === generation) error.value = apiErrorMessage(cause) }
  finally { if (current === generation) loading.value = false }
}

const emptyApplicantForm = (): ApplicantPayload => ({
  service: service.value,
  name: '',
  father_name: '',
  father_phone: '',
  contact: '',
  email: '',
  address: '',
  notes: '',
  responsible_employee_id: null,
  admission_employee_id: null,
  grade_score: null,
  country_id: null,
  education_level_id: null,
  semester_id: null,
  contract_type_id: null,
  university_fee: null,
  favorite_subject_ids: [],
})

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

async function create(extension: Contract | null = null, editedContract: Contract | null = null) {
  const current = ++formGeneration
  parent.value = extension
  editing.value = editedContract
  wizardStep.value = 1
  const source = extension ?? editedContract
  const extensionLine = source?.services?.[0] ?? null
  const extendingAcademy = extension !== null && service.value === 'academy'
  form.value = (extendingAcademy && extension) || editedContract
    ? parentApplicantPayload(source!)
    : emptyApplicantForm()
  visitorId.value = null
  visitors.value = []
  charges.value = Object.fromEntries(chargeFields.value.map(field => [field, Number((extensionLine as Record<string, unknown> | null)?.[field] ?? 0)]))
  const lineMin = Number(extensionLine?.application_fee_min ?? 0)
  const lineMax = Number(extensionLine?.application_fee_max ?? extensionLine?.application_fee ?? 0)
  applicationFeeFrom.value = lineMin
  applicationFeeTo.value = Math.max(lineMin, lineMax)
  paidFee.value = 0
  createPortalUser.value = false
  portalCredentials.value = null
  discount.value = Number(extensionLine?.discount ?? 0)
  currencyId.value = extensionLine?.currency_id ?? null
  rate.value = Number(extensionLine?.currency_rate ?? 1)
  selectedTemplate.value = null
  languageId.value = source?.contract_language_id ?? null
  selectedVersionId.value = source?.contract_template_version_id ?? null
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
    if (source)
      rate.value = Number(extensionLine?.currency_rate ?? currencies.value.find(row => row.id === currencyId.value)?.rate ?? 1)
    // A counselor creating a contract owns its case: preselect themselves when
    // they are in the counselor list so the field is not left blank.
    // Academy enrolments have no associated employee, so they are never
    // pre-filled with the signed-in counselor.
    if (!source && service.value !== 'academy') {
      const userData = useCookie<{ roles?: string[]; employee?: { id?: number } | null }>('userData').value
      const employeeId = userData?.employee?.id
      const isCounselor = (userData?.roles ?? []).some(role => role === 'counselor' || role === 'senior_counselor')
      if (isCounselor && employeeId && counselorEmployees.value.some(row => row.id === employeeId))
        form.value.responsible_employee_id = employeeId
    }
    if (!source && ability.can('read', 'view_visitors')) {
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

async function edit(contract: Contract) {
  const current = ++formGeneration
  try {
    const fullContract = await ContractApi.getById(contract.id)
    if (current === formGeneration)
      await create(null, fullContract)
  }
  catch (cause) { error.value = apiErrorMessage(cause) }
}

watch(visitorId, id => {
  const visitor = visitors.value.find(row => row.id === id)
  if (visitor)
    form.value = { ...emptyApplicantForm(), ...visitorApplicantFields(visitor) }
  else if (!parent.value && !editing.value) {
    // Clearing the visitor must clear every application value that selection
    // supplied, including the matching version derived from its destination.
    form.value = emptyApplicantForm()
    languageId.value = null
    selectedVersionId.value = null
  }
})
watch(currencyId, id => { rate.value = currencies.value.find(row => row.id === id)?.rate ?? 1 })
// A form-level API error describes the payload that was just submitted. Once
// the operator edits any field it is stale, so it must not stay on screen.
watch([form, charges, applicationFeeFrom, applicationFeeTo, paidFee, discount, currencyId, rate, languageId, selectedVersionId, createPortalUser], () => {
  if (open.value)
    error.value = ''
}, { deep: true })
watch(compatibleVersions, versions => {
  if (editing.value)
    return
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
  status.value = null
  contractVersionId.value = null
  syncFilterDraft()
  await Promise.all([load(), loadVersionOptions()])
  if (route.query.visitor && ability.can('read', 'create_contracts')) await create()
}, { immediate: true })

const credentialText = (credentials: ApplicantPortalCredentials) => `Email: ${credentials.email}\nPassword: ${credentials.password}`
const copyCredentials = async (credentials: ApplicantPortalCredentials | null) => {
  if (!credentials)
    return
  try {
    await navigator.clipboard.writeText(credentialText(credentials))
    toast.success(t('serviceContracts.credentialsCopied'))
  }
  catch {
    toast.error(t('serviceContracts.credentialsCopyFailed'))
  }
}

/** Generate the login the new contract will issue; nothing is created yet. */
async function togglePortalUser(enabled: boolean | null) {
  createPortalUser.value = enabled === true
  portalCredentials.value = null
  if (!enabled || parent.value || editing.value)
    return
  generatingCredentials.value = true
  error.value = ''
  try {
    portalCredentials.value = await ApplicantPortalApi.credentials({
      service: service.value,
      name: form.value.name?.trim() ?? '',
      email: form.value.email ?? null,
      education_level_id: form.value.education_level_id ?? null,
      program_id: form.value.program_id ?? null,
      test_id: form.value.test_id ?? null,
    })
  }
  catch (cause) {
    error.value = apiErrorMessage(cause)
    createPortalUser.value = false
  }
  finally { generatingCredentials.value = false }
}

const hasValue = (value: unknown) => Array.isArray(value) ? value.length > 0 : value !== null && value !== undefined && String(value).trim() !== ''
const currentStepHasRequiredValues = () => {
  if (preparing.value)
    return false

  if (wizardStep.value === 1) {
    const hasContact = !!parent.value || (hasValue(form.value.name) && hasValue(form.value.contact)
      && (service.value !== 'test_offer' || hasValue(form.value.backup_phone)))

    if (service.value === 'academy')
      return hasContact && !!selectedVersionId.value && hasValue(form.value.student_id)

    return service.value !== 'test_offer' || (hasContact && !!selectedVersionId.value
      && hasValue(form.value.test_id) && hasValue(form.value.skill_ids))
  }

  if (wizardStep.value === 2) {
    if (service.value === 'test_offer')
      return true
    if (!selectedVersionId.value)
      return false
    if (service.value === 'academy')
      return hasValue(form.value.student_id) && hasValue(form.value.time_id) && hasValue(form.value.teacher_ids)
        && hasValue(form.value.duration_id) && hasValue(form.value.program_id) && hasValue(form.value.class_id)

    return hasValue(form.value.grade_score) && hasValue(form.value.country_id)
      && hasValue(form.value.education_level_id) && (service.value !== 'private' || hasValue(form.value.semester_id))
  }

  return true
}
async function validateWizardStep() {
  const result = await formRef.value?.validate()

  return result?.valid !== false && currentStepHasRequiredValues()
}
async function nextWizardStep() {
  if (wizardStep.value === wizardStepCount.value || !(await validateWizardStep()))
    return

  wizardStep.value = (wizardStep.value + 1) as 1 | 2 | 3
}
const previousWizardStep = () => { wizardStep.value = (wizardStep.value - 1) as 1 | 2 | 3 }
async function handleWizardSubmit() {
  if (wizardStep.value < wizardStepCount.value) {
    await nextWizardStep()
    return
  }

  await save()
}

async function save() {
  if (saving.value || !(await formRef.value?.validate())?.valid || !valid.value) return
  const selectedService = service.value
  saving.value = true
  error.value = ''
  try {
    // An academy extension keeps its applicant editable, so the edited identity
    // and programme fields travel with the parent reference.
    const payload = {
      ...(parent.value
        ? {
            parent_contract_id: parent.value.id,
            ...(selectedService === 'academy' ? serviceContractApplicantFields(selectedService, form.value, ability.can('read', 'assign_admission_employee')) : {}),
          }
        : serviceContractApplicantFields(selectedService, form.value, ability.can('read', 'assign_admission_employee'))),
      ...Object.fromEntries(chargeFields.value.map(field => [field, Number(charges.value[field])])),
      ...(selectedService === 'private' ? {
        application_fee_min: Number(applicationFeeFrom.value),
        application_fee_max: Number(applicationFeeTo.value),
        application_fee: Number(applicationFeeTo.value),
      } : {}),
      ...(!editing.value && selectedService === 'academy' && canRecordIncome.value && Number(paidFee.value) > 0 ? { paid_fee: Number(paidFee.value) } : {}),
      ...(createPortalUser.value && portalCredentials.value && !editing.value && !parent.value
        ? { create_portal_user: true, portal_user_email: portalCredentials.value.email, portal_user_password: portalCredentials.value.password }
        : {}),
      fee: Number(fee.value), discount: Number(discount.value), currency_id: currencyId.value!,
      currency_rate: Number(rate.value), language_id: languageId.value!, contract_template_version_id: selectedVersionId.value!,
    }
    const result = editing.value
      ? await ContractApi.updateService(selectedService, editing.value.id, payload)
      : await ContractApi.createService(selectedService, payload)
    if (service.value === selectedService) {
      // A created contract belongs on the first page; an edited one stays where
      // it was, so the filter plus the current page decide in both cases.
      const created = !editing.value
      page.value = created ? 1 : page.value
      await load()
      if (created)
        rows.value = moveRowIdFirst(rows.value, result.id)
      open.value = false
      // The password cannot be read back once the account exists, so the
      // generated pair stays on screen until the operator dismisses it.
      if (createPortalUser.value && portalCredentials.value && !editing.value && !parent.value) {
        createdCredentials.value = portalCredentials.value
        credentialsDialog.value = true
      }
    }
    toast.success(t(editing.value ? 'serviceContracts.updated' : 'serviceContracts.created'))
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
        <VRow align="center" class="table-toolbar">
          <VCol cols="12" md="6" lg="5">
            <ErpSearchField v-model="search" :placeholder="t('serviceContracts.search')" @input="debouncedSearch" />
          </VCol>
          <VCol cols="12" md="6" lg="7" class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2">
            <DataTableFilterMenu v-model="filterMenuOpen" :active="hasActiveFilters" :show-reset="false">
              <ErpDateRange v-model:from="filterDraft.dateFrom" v-model:to="filterDraft.dateTo" :show-apply="false" />
              <ErpField v-model="filterDraft.status" kind="select" :items="statuses" :label="t('serviceContracts.status')" :placeholder="t('serviceContracts.placeholders.status')" clearable />
              <ErpField v-model="filterDraft.contractVersionId" kind="select" :items="versionFilterOptions" item-title="name" item-value="id" :label="t('agencyStaff.contractVersion')" :placeholder="t('serviceContracts.placeholders.version')" clearable>
                <template #item="{ props: itemProps, item }">
                  <VListItem v-bind="itemProps" :title="`${item.raw.name} · v${item.raw.version_number}`" />
                </template>
                <template #selection="{ item }">{{ item.raw.name }} · v{{ item.raw.version_number }}</template>
              </ErpField>
              <template #actions>
                <VBtn class="service-contract-filter-reset" variant="flat" @click="resetFilters">{{ t('common.messages.reset') }}</VBtn>
                <VBtn color="primary" variant="flat" class="service-contract-filter-apply" @click="applyFilters">{{ t('educational.apply') }}</VBtn>
              </template>
            </DataTableFilterMenu>
            <VBtn icon="tabler-printer" variant="tonal" class="service-contract-toolbar-icon flex-shrink-0" :aria-label="t('serviceContracts.print')" @click="printList"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('serviceContracts.print') }}</VTooltip></VBtn>
            <VBtn v-if="!route.query.applicant && ability.can('read', 'create_contracts')" color="primary" class="flex-shrink-0" prepend-icon="tabler-plus" @click="create()">{{ t('serviceContracts.create') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <ResponsiveDataTableServer
        :headers="headers"
        :items="rows"
        :items-length="total"
        :page="page"
        :items-per-page="perPage"
        :sort-by="[{ key: sortByKey, order: sortDirection }]"
        :items-per-page-options="[10, 25, 50, 100]"
        item-value="id"
        mobile-breakpoint="sm"
        mobile-cards
        mobile-expand-all
        mobile-actions-as-menu
        mobile-title-key="contract_number"
        mobile-action-key="actions"
        :sticky-key-column="false"
        :sticky-action-column="false"
        :skeleton-rows="5"
        :show-scroll-affordance="false"
        :loading="loading"
        pagination
        class="service-contract-table text-no-wrap"
        @update:options="updateTableOptions"
      >
      <template #item.version="{ item }">{{ item.snapshot_data?.template_version?.name ?? '—' }}<span v-if="item.snapshot_data?.template_version?.version_number"> · v{{ item.snapshot_data.template_version.version_number }}</span></template>
      <template #item.charges="{ item }"><span v-for="line in item.services" :key="line.id">{{ linePrice(line) }}</span></template>
      <template #item.status="{ item }"><VChip size="small" :color="item.is_cancelled ? 'error' : 'success'">{{ t(`serviceContracts.${item.status}`) }}</VChip></template>
      <template #item.counterparty_contact="{ item }">
        <div dir="ltr" class="service-contract-contact-value">
          <ContactActionMenu
            v-if="item.counterparty_contact"
            kind="phone"
            :value="item.counterparty_contact"
            contactable-type="applicant"
            :contactable-id="item.applicant_id || undefined"
            audience="applicant"
            :context="item.applicant ?? item"
          />
          <span v-else class="text-medium-emphasis">—</span>
        </div>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex align-center ga-1 justify-end">
          <VBtn v-if="!item.is_cancelled" icon="tabler-printer" size="small" variant="text" @click="printable = item; printing = true"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('serviceContracts.print') }}</VTooltip></VBtn>
          <VBtn v-if="!item.is_cancelled && service === 'academy'" icon="tabler-school" size="small" variant="text" @click="openSlip(item)"><VIcon icon="tabler-school" /><VTooltip activator="parent">{{ t('documents.academySlip.print') }}</VTooltip></VBtn>
          <VMenu v-if="!item.is_cancelled" location="bottom end">
            <template #activator="{ props: menuProps }">
              <VBtn v-bind="menuProps" icon="tabler-dots-vertical" variant="tonal" size="small" class="service-contract-actions-trigger" :aria-label="t('common.messages.actions')" />
            </template>
            <VList class="service-contract-actions-menu" density="compact" min-width="220">
              <template v-if="!item.is_cancelled">
                <FullDetailsButton :item="item" :fields="contractDetailFields" :title="item.contract_number || item.counterparty || ''" menu-action />
                <VListItem v-if="ability.can('read', 'edit_contracts')" prepend-icon="tabler-edit" :title="t('serviceContracts.edit')" @click="edit(item)" />
                <VListItem v-if="service === 'academy' && ability.can('read', 'create_contracts') && item.contract_role === 'primary' && ['active', 'completed'].includes(item.status)" prepend-icon="tabler-file-plus" :title="t('serviceContracts.extend')" @click="create(item)" />
                <VListItem v-if="canMarkPaid(item)" prepend-icon="tabler-checks" :title="t('serviceContracts.markPaid')" :disabled="markingPaid === item.id" @click="markPaid(item)" />
                <VDivider v-if="canCancel(item)" class="my-1" />
                <VListItem v-if="canCancel(item)" prepend-icon="tabler-ban" class="service-contract-actions-menu__cancel" :title="t('serviceContracts.cancelContract')" @click="requestCancel(item)" />
              </template>
            </VList>
          </VMenu>
        </div>
      </template>
      <template #no-data>
        <div class="service-contract-empty-state">
          <TableEmptyState icon="tabler-file-description" :min-height="0" :title="t('serviceContracts.empty')" :description="t('serviceContracts.emptyDescription')" />
          <VBtn v-if="!route.query.applicant && ability.can('read', 'create_contracts')" color="primary" variant="tonal" class="service-contract-empty-state__action" @click="create()">
            <VIcon icon="tabler-plus" class="me-1" />
            {{ t('serviceContracts.create') }}
          </VBtn>
        </div>
      </template>
      </ResponsiveDataTableServer>
    </VCard>
    <VDialog v-model="open" :persistent="saving" scrollable scroll-strategy="block" max-width="980" content-class="service-contract-form-dialog">
      <VCard class="service-contract-form-card">
        <VCardTitle class="service-contract-dialog-title px-5 py-5">
          <span class="text-h5 service-contract-dialog-heading service-contract-dialog-heading--primary">{{ t(labelKey) }}</span>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="error" class="service-contract-dialog-close" :disabled="saving" :aria-label="t('serviceContracts.close')" @click="open = false" />
        </VCardTitle>
        <div v-if="wizardStepCount > 1" class="service-contract-wizard-progress" :class="{ 'service-contract-wizard-progress--two-steps': wizardStepCount === 2 }" role="list" :aria-label="t('serviceContracts.wizard.progress')">
          <button
            v-for="step in wizardSteps"
            :key="step.number"
            type="button"
            class="service-contract-wizard-progress__step"
            :class="{ 'service-contract-wizard-progress__step--active': wizardStep === step.number, 'service-contract-wizard-progress__step--complete': wizardStep > step.number }"
            :disabled="step.number > wizardStep || saving"
            :aria-current="wizardStep === step.number ? 'step' : undefined"
            @click="step.number < wizardStep && (wizardStep = step.number)"
          >
            <span class="service-contract-wizard-progress__indicator"><VIcon v-if="wizardStep > step.number" icon="tabler-check" size="15" /><span v-else>{{ step.number }}</span></span>
            <span class="service-contract-wizard-progress__label">{{ step.title }}</span>
          </button>
        </div>
        <VCardText class="service-contract-dialog-body pa-5 pa-sm-6">
          <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
          <div v-if="parent && wizardStep === 1" class="service-contract-parent-context">{{ parent.contract_number }}: {{ parent.counterparty }}</div>
          <VForm ref="formRef" :class="['app-form', 'service-contract-wizard', { 'service-contract-wizard--two-column': ['private', 'scholarship'].includes(service) }]" @submit.prevent="handleWizardSubmit">
            <Transition name="service-contract-wizard-step" mode="out-in">
              <div :key="wizardStep" class="service-contract-wizard-step">
            <template v-if="wizardStep === 1 && (!parent || service === 'academy')">
              <template v-if="service === 'private'">
                <VRow class="service-contract-field-grid">
                   <VCol v-if="!editing && ability.can('read', 'view_visitors')" cols="12" sm="6" md="6"><ErpField v-model="visitorId" kind="autocomplete" :items="visitors" item-title="name" item-value="id" :label="t('serviceContracts.visitor')" :placeholder="t('serviceContracts.placeholders.visitor')" :disabled="preparing" clearable /></VCol>
                   <VCol cols="12" sm="6" md="6"><ErpField v-model="form.name" :label="t('serviceContracts.applicant')" :placeholder="t('serviceContracts.placeholders.name')" maxlength="255" required /></VCol>
                   <VCol cols="12" sm="6" md="6"><ErpField v-model="form.contact" dir="ltr" inputmode="tel" :label="t('erp.phone')" :placeholder="t('serviceContracts.placeholders.phone')" maxlength="255" required /></VCol>
                   <VCol cols="12" sm="6" md="6"><ErpField v-model="form.email" :label="t('serviceContracts.email')" :placeholder="t('serviceContracts.placeholders.email')" type="email" maxlength="255" /></VCol>
                   <VCol cols="12" sm="6" md="6"><ErpField v-model="form.father_name" :label="t('serviceContracts.fatherName')" :placeholder="t('serviceContracts.placeholders.fatherName')" maxlength="255" /></VCol>
                   <VCol cols="12" sm="6" md="6"><ErpField v-model="form.father_phone" dir="ltr" inputmode="tel" :label="t('serviceContracts.fatherPhone')" :placeholder="t('serviceContracts.placeholders.fatherPhone')" maxlength="50" /></VCol>
                   <VCol cols="12" sm="6" md="4" class="service-contract-third-row"><ErpField v-model="form.address" :label="t('serviceContracts.address')" :placeholder="t('serviceContracts.placeholders.address')" maxlength="1000" /></VCol>
                   <VCol cols="12" sm="6" md="4" class="service-contract-third-row"><ErpField v-model="form.responsible_employee_id" kind="select" :items="counselorEmployees" item-title="name" item-value="id" :label="t('serviceContracts.consultant')" :placeholder="t('serviceContracts.placeholders.consultant')" clearable /></VCol>
                   <VCol cols="12" sm="6" md="4" class="service-contract-third-row"><ErpField v-model="form.admission_employee_id" kind="select" :items="admissionEmployees" item-title="name" item-value="id" :label="t('serviceContracts.admissionEmployeeOptional')" :placeholder="t('serviceContracts.placeholders.admissionEmployee')" :disabled="!ability.can('read', 'assign_admission_employee')" clearable /></VCol>
                </VRow>
              </template>
              <VRow v-if="service !== 'academy' && service !== 'private'" class="service-contract-field-grid">
                <VCol cols="12" sm="6" md="6"><ErpField v-model="form.name" :label="t('serviceContracts.applicant')" :placeholder="t('serviceContracts.placeholders.name')" maxlength="255" required /></VCol>
                <VCol v-if="['scholarship', 'private'].includes(service)" cols="12" sm="6" md="6"><ErpField v-model="form.father_name" :label="t('serviceContracts.fatherName')" :placeholder="t('serviceContracts.placeholders.fatherName')" maxlength="255" /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="form.contact" dir="ltr" inputmode="tel" :label="t('erp.phone')" :placeholder="t('serviceContracts.placeholders.phone')" maxlength="255" required /></VCol>
                <VCol v-if="['scholarship', 'private'].includes(service)" cols="12" sm="6" md="6"><ErpField v-model="form.father_phone" dir="ltr" inputmode="tel" :label="t('serviceContracts.fatherPhone')" :placeholder="t('serviceContracts.placeholders.fatherPhone')" maxlength="50" /></VCol>
                <VCol v-if="service === 'test_offer'" cols="12" sm="6" md="6"><ErpField v-model="form.backup_phone" dir="ltr" inputmode="tel" :label="t('erp.backupPhone')" :placeholder="t('serviceContracts.placeholders.phone')" maxlength="50" required /></VCol>
                <VCol v-if="!parent && service !== 'scholarship'" cols="12" sm="6" md="6"><ErpField v-model="form.responsible_employee_id" kind="select" :items="counselorEmployees" item-title="name" item-value="id" :label="t('serviceContracts.consultant')" :placeholder="t('serviceContracts.placeholders.consultant')" clearable /></VCol>
              </VRow>
            </template>
            <ApplicantServiceFields v-if="!parent || service === 'academy'" v-model="form" v-model:language-id="languageId" fixed-service show-language contract-layout :contract-step="wizardStep" :languages="languages">
              <template v-if="parent" #private-prefix>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="form.responsible_employee_id" kind="select" :items="counselorEmployees" item-title="name" item-value="id" :label="t('serviceContracts.consultant')" :placeholder="t('serviceContracts.placeholders.consultant')" clearable /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="form.admission_employee_id" kind="select" :items="admissionEmployees" item-title="name" item-value="id" :label="t('serviceContracts.admissionEmployeeOptional')" :placeholder="t('serviceContracts.placeholders.admissionEmployee')" :disabled="!ability.can('read', 'assign_admission_employee')" clearable /></VCol>
              </template>
              <template #private-version>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="selectedVersionId" kind="select" :items="versionItems" :label="versionItems.length ? t('serviceContracts.matchingVersion') : t('agencyStaff.noMatchingVersion')" :placeholder="t('serviceContracts.placeholders.version')" :disabled="preparing || !versionItems.length || !!editing" required /></VCol>
              </template>
              <template #private-fees>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="charges.visa_fee" type="number" min="0" step="0.01" :label="chargeLabel('visa_fee')" :placeholder="t('serviceContracts.placeholders.amount')" /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="applicationFeeFrom" type="number" min="0" step="0.01" :label="t('serviceContracts.applicationFeeMinimum')" :placeholder="t('serviceContracts.placeholders.amount')" /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="applicationFeeTo" type="number" min="0" step="0.01" :label="t('serviceContracts.applicationFeeMaximum')" :placeholder="t('serviceContracts.placeholders.amount')" /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="currencyId" kind="select" :items="currencies" item-title="code" item-value="id" :label="t('serviceContracts.currency')" :placeholder="t('serviceContracts.placeholders.currency')" required /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="rate" type="number" min="0.00000001" step="0.00000001" :label="t('serviceContracts.rate')" :placeholder="t('serviceContracts.placeholders.rate')" required /></VCol>
                <VCol cols="12" class="contract-wizard-full-row"><ErpField v-model="form.notes" :label="t('erp.admissionNotes')" :placeholder="t('serviceContracts.placeholders.admissionNotes')" maxlength="10000" /></VCol>
              </template>
              <template #scholarship-staff>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="form.responsible_employee_id" kind="select" :items="counselorEmployees" item-title="name" item-value="id" :label="t('serviceContracts.consultant')" :placeholder="t('serviceContracts.placeholders.consultant')" clearable /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="form.admission_employee_id" kind="select" :items="admissionEmployees" item-title="name" item-value="id" :label="t('serviceContracts.admissionEmployeeOptional')" :placeholder="t('serviceContracts.placeholders.admissionEmployee')" :disabled="!ability.can('read', 'assign_admission_employee')" clearable /></VCol>
              </template>
              <template #scholarship-version>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="selectedVersionId" kind="select" :items="versionItems" :label="versionItems.length ? t('serviceContracts.matchingVersion') : t('agencyStaff.noMatchingVersion')" :placeholder="t('serviceContracts.placeholders.version')" :disabled="preparing || !versionItems.length || !!editing" required /></VCol>
              </template>
              <template #test-offer-version>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="selectedVersionId" kind="select" :items="versionItems" :label="versionItems.length ? t('serviceContracts.matchingVersion') : t('agencyStaff.noMatchingVersion')" :placeholder="t('serviceContracts.placeholders.version')" :disabled="preparing || !versionItems.length || !!editing" required /></VCol>
              </template>
              <template #academy-student-basics>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="form.name" :label="t('serviceContracts.studentFullName')" :placeholder="t('serviceContracts.placeholders.name')" maxlength="255" required /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="form.contact" dir="ltr" inputmode="tel" :label="t('erp.phone')" :placeholder="t('serviceContracts.placeholders.phone')" maxlength="255" required /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="form.responsible_employee_id" kind="select" :items="counselorEmployees" item-title="name" item-value="id" :label="t('serviceContracts.consultant')" :placeholder="t('serviceContracts.placeholders.consultant')" clearable /></VCol>
              </template>
              <template #academy-version>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="selectedVersionId" kind="select" :items="versionItems" :label="versionItems.length ? t('serviceContracts.matchingVersion') : t('agencyStaff.noMatchingVersion')" :placeholder="t('serviceContracts.placeholders.version')" :disabled="preparing || !versionItems.length || !!editing" required /></VCol>
              </template>
              <template #academy-payment>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="charges.service_fee" type="number" min="0" step="0.01" :label="chargeLabel('service_fee')" :placeholder="t('serviceContracts.placeholders.amount')" required /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="currencyId" kind="select" :items="currencies" item-title="code" item-value="id" :label="t('serviceContracts.currency')" :placeholder="t('serviceContracts.placeholders.currency')" required /></VCol>
                <VCol cols="12" sm="6" md="6"><ErpField v-model="rate" type="number" min="0.00000001" step="0.00000001" :label="t('serviceContracts.rate')" :placeholder="t('serviceContracts.placeholders.rate')" required /></VCol>
                <VCol v-if="canRecordIncome && !editing" cols="12" sm="6" md="6"><ErpField v-model="paidFee" type="number" min="0" :max="fee" step="0.01" :label="t('serviceContracts.paidFee')" :placeholder="t('serviceContracts.placeholders.amount')" /></VCol>
              </template>
            </ApplicantServiceFields>
            <section v-if="wizardStep === wizardStepCount && service !== 'academy'" class="service-contract-section">
              <VRow>
                <template v-if="service === 'private' && parent">
                  <VCol cols="12" sm="6" md="6"><ErpField v-model="form.university_fee" type="number" min="0" step="0.01" :label="t('educational.serviceFields.universityFee')" :placeholder="t('serviceContracts.placeholders.universityFee')" clearable /></VCol>
                  <VCol cols="12" sm="6" md="6"><ErpField v-model="charges.visa_fee" type="number" min="0" step="0.01" :label="chargeLabel('visa_fee')" :placeholder="t('serviceContracts.placeholders.amount')" /></VCol>
                  <VCol cols="12" sm="6" md="6"><ErpField v-model="applicationFeeFrom" type="number" min="0" step="0.01" :label="t('serviceContracts.applicationFeeMinimum')" :placeholder="t('serviceContracts.placeholders.amount')" /></VCol>
                  <VCol cols="12" sm="6" md="6"><ErpField v-model="applicationFeeTo" type="number" min="0" step="0.01" :label="t('serviceContracts.applicationFeeMaximum')" :placeholder="t('serviceContracts.placeholders.amount')" /></VCol>
                  <VCol cols="12" sm="6" md="6"><ErpField v-model="currencyId" kind="select" :items="currencies" item-title="code" item-value="id" :label="t('serviceContracts.currency')" :placeholder="t('serviceContracts.placeholders.currency')" required /></VCol>
                  <VCol cols="12" sm="6" md="6"><ErpField v-model="rate" type="number" min="0.00000001" step="0.00000001" :label="t('serviceContracts.rate')" :placeholder="t('serviceContracts.placeholders.rate')" required /></VCol>
                  <VCol cols="12" class="contract-wizard-full-row"><ErpField v-model="form.notes" :label="t('erp.admissionNotes')" :placeholder="t('serviceContracts.placeholders.admissionNotes')" maxlength="10000" /></VCol>
                </template>
                <template v-else-if="service === 'scholarship'">
                  <VCol cols="12" sm="6" md="4" class="service-contract-third-row"><ErpField v-model="charges.application_fee" type="number" min="0" step="0.01" :label="chargeLabel('application_fee')" :placeholder="t('serviceContracts.placeholders.amount')" required /></VCol>
                  <VCol cols="12" sm="6" md="4" class="service-contract-third-row"><ErpField v-model="currencyId" kind="select" :items="currencies" item-title="code" item-value="id" :label="t('serviceContracts.currency')" :placeholder="t('serviceContracts.placeholders.currency')" required /></VCol>
                  <VCol cols="12" sm="6" md="4" class="service-contract-third-row"><ErpField v-model="rate" type="number" min="0.00000001" step="0.00000001" :label="t('serviceContracts.rate')" :placeholder="t('serviceContracts.placeholders.rate')" required /></VCol>
                  <VCol cols="12" class="contract-wizard-full-row"><ErpField v-model="form.notes" :label="t('erp.admissionNotes')" :placeholder="t('serviceContracts.placeholders.admissionNotes')" maxlength="10000" /></VCol>
                </template>
                <template v-else-if="service === 'test_offer'">
                  <VCol cols="12" sm="6" md="4" class="service-contract-third-row"><ErpField v-model="charges.booking_fee" type="number" min="0" step="0.01" :label="chargeLabel('booking_fee')" :placeholder="t('serviceContracts.placeholders.amount')" required /></VCol>
                  <VCol cols="12" sm="6" md="4" class="service-contract-third-row"><ErpField v-model="charges.service_fee" type="number" min="0" step="0.01" :label="chargeLabel('service_fee')" :placeholder="t('serviceContracts.placeholders.amount')" required /></VCol>
                  <VCol cols="12" sm="6" md="4" class="service-contract-third-row"><ErpField v-model="charges.remote_fee" type="number" min="0" step="0.01" :label="chargeLabel('remote_fee')" :placeholder="t('serviceContracts.placeholders.amount')" required /></VCol>
                  <VCol cols="12" sm="6" md="6"><ErpField v-model="currencyId" kind="select" :items="currencies" item-title="code" item-value="id" :label="t('serviceContracts.currency')" :placeholder="t('serviceContracts.placeholders.currency')" required /></VCol>
                  <VCol cols="12" sm="6" md="6"><ErpField v-model="rate" type="number" min="0.00000001" step="0.00000001" :label="t('serviceContracts.rate')" :placeholder="t('serviceContracts.placeholders.rate')" required /></VCol>
                </template>
              </VRow>
            </section>
            <div v-if="wizardStep === wizardStepCount && !parent && !editing" class="service-contract-portal">
              <div class="service-contract-portal__copy">
                <strong>{{ t('serviceContracts.createPortalUser') }}</strong>
                <span>{{ t('serviceContracts.createPortalUserHint') }}</span>
              </div>
              <div class="service-contract-portal__control">
                <VSwitch
                  :model-value="createPortalUser"
                  :disabled="preparing || generatingCredentials || !form.name?.trim()"
                  density="compact"
                  hide-details
                  @update:model-value="togglePortalUser"
                />
                <div v-if="portalCredentials" class="portal-credentials mt-2">
                  <div><span class="text-medium-emphasis">{{ t('serviceContracts.portalEmail') }}</span> <strong>{{ portalCredentials.email }}</strong></div>
                  <div><span class="text-medium-emphasis">{{ t('serviceContracts.portalPassword') }}</span> <strong class="portal-password">{{ portalCredentials.password }}</strong></div>
                  <VBtn size="small" variant="tonal" prepend-icon="tabler-copy" class="mt-2" @click="copyCredentials(portalCredentials)">{{ t('serviceContracts.copyCredentials') }}</VBtn>
                </div>
              </div>
            </div>
            <div v-if="wizardStep === wizardStepCount" class="service-contract-summary" aria-live="polite">
              <strong>{{ t('erp.totalFee') }}: {{ feeMin.toFixed(2) }}<template v-if="service === 'private'"> – {{ fee.toFixed(2) }}</template> {{ currencies.find(row => row.id === currencyId)?.code }}</strong>
              <span>{{ t('serviceContracts.discount') }}: {{ discount }}</span>
            </div>
              </div>
            </Transition>
            <div class="service-contract-footer service-contract-dialog-actions px-5 py-4">
              <VBtn
                v-if="wizardStep === 1"
                color="error"
                variant="flat"
                class="service-contract-dialog-cancel"
                :disabled="saving"
                @click="open = false"
              >{{ t('erp.cancel') }}</VBtn>
              <VBtn
                v-else
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-arrow-left"
                :disabled="saving"
                @click="previousWizardStep"
              >{{ t('serviceContracts.wizard.back') }}</VBtn>
              <VSpacer />
              <VBtn
                v-if="wizardStep < wizardStepCount"
                type="submit"
                color="primary"
                variant="flat"
                class="service-contract-dialog-submit"
                append-icon="tabler-arrow-right"
                :disabled="preparing || saving"
              >{{ t('serviceContracts.wizard.next') }}</VBtn>
              <VBtn
                v-else
                type="submit"
                color="primary"
                variant="flat"
                class="service-contract-dialog-submit"
                prepend-icon="tabler-check"
                :loading="saving"
                :disabled="saving"
              >{{ t(editing ? 'serviceContracts.wizard.update' : 'serviceContracts.create') }}</VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
    <PrintableListReport :title="t(labelKey)" :subtitle="[search, status, dateFrom, dateTo].filter(Boolean).join(' · ')" :columns="[t('serviceContracts.number'), t('serviceContracts.applicant'), t('erp.phone'), t('agencyStaff.contractVersion'), t('erp.totalFee'), t('serviceContracts.startDate'), t('serviceContracts.status')]" :rows="reportRows" />
    <VDialog v-model="credentialsDialog" max-width="480" content-class="portal-credentials-dialog">
      <VCard class="portal-credentials-dialog__card">
        <VCardText class="portal-credentials-dialog__content">
          <div class="portal-credentials-dialog__icon" aria-hidden="true"><VIcon icon="tabler-circle-check" size="32" /></div>
          <h2 class="portal-credentials-dialog__title">{{ t('serviceContracts.portalCredentialsTitle') }}</h2>
          <p class="portal-credentials-dialog__description">{{ t('serviceContracts.portalCredentialsSubtitle') }}</p>
          <div v-if="createdCredentials" class="portal-credentials portal-credentials-dialog__details">
            <div><span>{{ t('serviceContracts.portalEmail') }}</span><strong dir="ltr">{{ createdCredentials.email }}</strong></div>
            <div><span>{{ t('serviceContracts.portalPassword') }}</span><strong class="portal-password" dir="ltr">{{ createdCredentials.password }}</strong></div>
          </div>
        </VCardText>
        <VCardActions class="portal-credentials-dialog__actions">
          <VBtn class="portal-credentials-dialog__close" variant="flat" color="secondary" @click="credentialsDialog = false">{{ t('common.actions.close') }}</VBtn>
          <VBtn class="portal-credentials-dialog__copy" color="primary" variant="flat" prepend-icon="tabler-copy" @click="copyCredentials(createdCredentials)">{{ t('serviceContracts.copyCredentials') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
    <ContractDocumentPrinter v-model="printing" :contract="printable" />
    <AcademySlipPrinter v-model="slipOpen" :contract-id="slipContractId" />
    <VDialog v-model="cancelOpen" max-width="460" content-class="service-contract-cancel-overlay">
      <VCard class="service-contract-cancel-dialog">
        <VCardText class="service-contract-cancel-dialog__content">
          <div class="service-contract-cancel-dialog__icon" aria-hidden="true"><VIcon icon="tabler-ban" size="32" /></div>
          <h2 class="service-contract-cancel-dialog__title">{{ t('serviceContracts.cancelContract') }}</h2>
          <p class="service-contract-cancel-dialog__description">{{ t('serviceContracts.cancelConfirmation', { contract: cancelTarget?.contract_number || cancelTarget?.counterparty || '' }) }}</p>
        </VCardText>
        <VCardActions class="service-contract-cancel-dialog__actions">
          <VBtn class="service-contract-cancel-dialog__button service-contract-cancel-dialog__button--cancel" variant="flat" :disabled="cancelling" @click="cancelOpen = false">{{ t('serviceContracts.no') }}</VBtn>
          <VBtn class="service-contract-cancel-dialog__button service-contract-cancel-dialog__button--delete" color="error" variant="flat" :loading="cancelling" @click="confirmCancel">{{ t('serviceContracts.cancelContract') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </section>
</template>

<style scoped>
.service-contract-form-card {
  max-block-size: calc(100vh - 32px);
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), .32);
  background: rgb(var(--v-theme-surface));
}

:global(.service-contract-form-dialog .service-contract-dialog-title),
:global(.service-contract-form-dialog .service-contract-dialog-actions) {
  display: flex;
  align-items: center;
  background-color: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%);
  border-color: rgba(var(--v-theme-on-surface), 0.035);
  border-style: solid;
  border-width: 0;
}

:global(.service-contract-form-dialog .service-contract-dialog-title) { border-block-end-width: 1px; }
:global(.service-contract-form-dialog .service-contract-dialog-actions) { border-block-start-width: 1px; }
:global(.service-contract-form-dialog .service-contract-dialog-body) {
  flex: 1 1 auto;
  min-block-size: 0;
  max-block-size: none;
  padding-block-end: 0 !important;
  overflow-y: auto;
  overscroll-behavior: contain;
  background-color: rgb(var(--v-theme-surface));
}
:global(.service-contract-form-dialog) { max-block-size: calc(100dvh - 2rem); }
:global(.service-contract-form-dialog .service-contract-form-card) { display: flex; flex-direction: column; max-block-size: calc(100dvh - 2rem); }
:global(.service-contract-form-dialog .service-contract-dialog-heading) { font-size: 1.625rem; font-weight: 700; }
:global(.service-contract-form-dialog .service-contract-dialog-heading--primary) { font-size: 2rem; }
:global(.service-contract-form-dialog .service-contract-dialog-close) {
  inline-size: 36px;
  block-size: 36px;
  border: 1px solid rgba(var(--v-theme-error), 0.38);
  border-radius: 50%;
  color: rgb(var(--v-theme-error));
}
:global(.service-contract-form-dialog .service-contract-dialog-close:hover) { background-color: rgba(var(--v-theme-error), 0.18) !important; }
:global(.v-overlay:has(.service-contract-form-dialog) .v-overlay__scrim) {
  background-color: rgba(var(--v-theme-on-surface), 0.24);
  backdrop-filter: blur(4px);
}

.service-contract-wizard-progress {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  padding: 14px 20px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), .06);
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-primary)) 4%);
}
.service-contract-wizard-progress--two-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); }

.service-contract-wizard-progress__step {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  min-inline-size: 0;
  padding: 4px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  cursor: default;
  font: inherit;
  text-align: start;
}

.service-contract-wizard-progress__step:not(:last-child)::after {
  position: absolute;
  inset-inline-start: calc(100% - 4px);
  inline-size: 8px;
  block-size: 1px;
  background: rgba(var(--v-theme-on-surface), .14);
  content: '';
}

.service-contract-wizard-progress__step--complete { cursor: pointer; }
.service-contract-wizard-progress__step:disabled { opacity: 1; }
.service-contract-wizard-progress__indicator {
  display: grid;
  flex: 0 0 auto;
  inline-size: 25px;
  block-size: 25px;
  border: 1px solid rgba(var(--v-theme-on-surface), .18);
  border-radius: 50%;
  place-items: center;
  font-size: .75rem;
  font-weight: 700;
}
.service-contract-wizard-progress__label { overflow: hidden; font-size: .8rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.service-contract-wizard-progress__step--active { color: rgb(var(--v-theme-primary)); }
.service-contract-wizard-progress__step--active .service-contract-wizard-progress__indicator { border-color: rgba(var(--v-theme-primary), .3); background: rgba(var(--v-theme-primary), .12); }
.service-contract-wizard-progress__step--complete { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); }
.service-contract-wizard-progress__step--complete .service-contract-wizard-progress__indicator { border-color: transparent; background: rgba(var(--v-theme-on-surface), .08); }
.service-contract-wizard-progress__step--complete::after { background: rgba(var(--v-theme-on-surface), .24); }

.service-contract-parent-context {
  margin-block-end: 16px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-theme-primary), .18);
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), .06);
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: .875rem;
}

.service-contract-wizard :deep(.v-row) {
  /* Vuetify spaces fields with the row's negative margin plus v-col padding.
     Its gutter variables do not control this version, so set the actual values. */
  margin: -3px !important;
  row-gap: 0 !important;
}
.service-contract-wizard--two-column .service-contract-portal { margin-block-start: 1.5rem; }
.service-contract-wizard :deep(.v-row > .v-col) {
  flex: 0 0 50% !important;
  max-inline-size: 50% !important;
  padding: 3px !important;
  animation: service-contract-step-in .22s ease-out both;
}
.service-contract-wizard :deep(.v-row > .v-col.contract-wizard-full-row) {
  flex-basis: 100% !important;
  max-inline-size: 100% !important;
}
.service-contract-wizard :deep(.v-row > .v-col.service-contract-quarter-row) {
  flex-basis: 25% !important;
  max-inline-size: 25% !important;
}
.service-contract-wizard :deep(.v-row > .v-col.service-contract-third-row) {
  flex-basis: 33.333333% !important;
  max-inline-size: 33.333333% !important;
}

@keyframes service-contract-step-in {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

.service-contract-wizard-step-enter-active,
.service-contract-wizard-step-leave-active {
  transition: opacity .22s ease, transform .22s ease;
}

.service-contract-wizard-step-enter-from {
  opacity: 0;
  transform: translateY(10px);
}

.service-contract-wizard-step-leave-to {
  opacity: 0;
  transform: translateY(-7px);
}

.service-contract-section {
  margin-block-end: 12px;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
}

.service-contract-portal {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-block: 1rem;
  padding: .875rem 1rem;
  border: 1px solid rgba(var(--v-border-color), .34);
  border-radius: .75rem;
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-primary)) 6%);
}
.service-contract-portal__copy { display: grid; gap: .2rem; max-inline-size: 45rem; }
.service-contract-portal__copy strong { font-size: .9rem; }
.service-contract-portal__copy span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .78rem; line-height: 1.45; }
.service-contract-portal__control { min-inline-size: 3rem; }

.service-contract-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-block-start: 16px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-theme-primary), .16);
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), .06);
  color: rgb(var(--v-theme-on-surface));
  font-size: .875rem;
}
.service-contract-summary span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); }

.service-contract-dialog-actions {
  margin-inline: -1.5rem;
  margin-block-end: 0;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), .035);
  background-color: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%) !important;
}
.service-contract-dialog-cancel {
  border: 1px solid rgba(var(--v-theme-error), 0.62) !important;
  background-color: rgba(var(--v-theme-error), 0.12) !important;
  color: rgb(var(--v-theme-error)) !important;
}
.service-contract-dialog-cancel:hover,
.service-contract-dialog-cancel:focus-visible,
.service-contract-dialog-cancel.v-btn--active,
.service-contract-dialog-cancel:active { border-color: rgb(var(--v-theme-error)); background-color: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.service-contract-dialog-submit { background-color: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.service-contract-dialog-submit:hover { filter: brightness(.9); }

.service-contract-filter-reset { background-color: rgba(var(--v-theme-primary), .12) !important; color: rgb(var(--v-theme-primary)) !important; }
.service-contract-filter-reset:hover,
.service-contract-filter-reset:focus-visible,
.service-contract-filter-reset.v-btn--active,
.service-contract-filter-reset:active { background-color: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.service-contract-filter-apply,
.service-contract-filter-apply:hover,
.service-contract-filter-apply:focus-visible { background-color: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.service-contract-toolbar-icon { flex: 0 0 auto; }

.service-contract-contact-value { direction: ltr; unicode-bidi: plaintext; }
.service-contract-actions-trigger { border-radius: 999px !important; background-color: rgba(var(--v-theme-on-surface), .04) !important; color: rgba(var(--v-theme-on-surface), .72) !important; }
.service-contract-actions-trigger:hover { background-color: rgba(var(--v-theme-on-surface), .08) !important; }
.service-contract-actions-menu__cancel { color: rgb(var(--v-theme-error)) !important; }

.service-contract-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; min-block-size: 17rem; padding: 1.25rem 1.25rem 2rem; }
.service-contract-empty-state :deep(.table-empty-state) { min-block-size: 0 !important; padding-block: 1rem; }
.service-contract-empty-state__action { margin-block-start: -.25rem; background-color: rgba(var(--v-theme-primary), .12) !important; color: rgb(var(--v-theme-primary)) !important; }
.service-contract-empty-state__action:hover { background-color: rgba(var(--v-theme-primary), .18) !important; }

.service-contract-table :deep(.v-table__wrapper > table > tbody > tr:hover > td) { background-color: rgba(var(--v-theme-on-surface), .035) !important; }
.service-contract-table :deep(.v-table__wrapper:has(.service-contract-empty-state)) { overflow-x: hidden; }
.service-contract-table :deep(.v-table__wrapper > table:has(.service-contract-empty-state)) { min-inline-size: 100%; table-layout: fixed; }
.service-contract-table :deep(.v-table__wrapper > table:has(.service-contract-empty-state) > thead > tr > th),
.service-contract-table :deep(.v-table__wrapper > table:has(.service-contract-empty-state) > tbody > tr > td) { min-inline-size: 0 !important; white-space: normal; }
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr:has(.service-contract-empty-state):hover > td) { background-color: transparent !important; }
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr:has(.service-contract-empty-state) > td) { padding: 0 !important; }
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:hover > td) { background-color: transparent !important; }
.service-contract-table :deep(.v-data-table__wrapper > table > tbody > tr:has(.service-contract-empty-state):hover > td),
.service-contract-table :deep(.v-data-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:hover > td) { background-color: transparent !important; }

.service-contract-discount {
  display: none;
}

.portal-credentials {
  display: grid;
  gap: 4px;
  font-size: 0.875rem;
}

.portal-password {
  font-family: var(--erp-mono-font, monospace);
  overflow-wrap: anywhere;
}

.portal-credentials-dialog__card {
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-primary), .2);
  border-radius: 18px;
  background: linear-gradient(150deg, rgba(var(--v-theme-primary), .09), rgb(var(--v-theme-surface)) 50%);
}

.portal-credentials-dialog__content {
  display: grid;
  justify-items: center;
  padding: 2rem 2rem 1.5rem !important;
  text-align: center;
}

.portal-credentials-dialog__icon {
  display: grid;
  inline-size: 4rem;
  block-size: 4rem;
  margin-block-end: 1rem;
  border-radius: 50%;
  background: rgba(var(--v-theme-primary), .14);
  color: rgb(var(--v-theme-primary));
  place-items: center;
  box-shadow: 0 10px 28px rgba(var(--v-theme-primary), .18);
}

.portal-credentials-dialog__title { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1.35rem; font-weight: 750; line-height: 1.25; }
.portal-credentials-dialog__description { max-inline-size: 23rem; margin: .65rem 0 1.25rem; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .9rem; line-height: 1.55; }
.portal-credentials-dialog__details { inline-size: 100%; padding: .85rem 1rem; border: 1px solid rgba(var(--v-theme-secondary), .22); border-radius: .75rem; background: rgba(var(--v-theme-secondary), .07); text-align: start; }
.portal-credentials-dialog__details > div { display: grid; padding-block: .35rem; gap: .2rem; }.portal-credentials-dialog__details span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; }.portal-credentials-dialog__details strong { color: rgb(var(--v-theme-on-surface)); overflow-wrap: anywhere; }
.portal-credentials-dialog__actions { display: grid; padding: 1rem 1.25rem 1.25rem !important; gap: .75rem; grid-template-columns: 1fr 1fr; }.portal-credentials-dialog__close { background: rgba(var(--v-theme-secondary), .14) !important; color: rgb(var(--v-theme-secondary)) !important; }.portal-credentials-dialog__copy { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
:global(.v-overlay:has(.portal-credentials-dialog) .v-overlay__scrim) { background-color: rgba(var(--v-theme-on-surface), .25); backdrop-filter: blur(4px); }

@media (max-width: 460px) { .portal-credentials-dialog__content { padding: 1.5rem 1rem 1.25rem !important; }.portal-credentials-dialog__actions { padding-inline: 1rem !important; grid-template-columns: 1fr; } }

.service-contract-table :deep(.v-table__wrapper),
.service-contract-table :deep(.v-data-table__wrapper) {
  overflow-x: auto;
  scrollbar-width: none;
}

.service-contract-table :deep(.v-table__wrapper::-webkit-scrollbar),
.service-contract-table :deep(.v-data-table__wrapper::-webkit-scrollbar) {
  display: none;
}

.service-contract-table :deep(.v-table__wrapper > table),
.service-contract-table :deep(.v-data-table__wrapper > table) {
  min-inline-size: 1500px;
}

.service-contract-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(1)),
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(1)) { min-inline-size: 9rem; }
.service-contract-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(2)),
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(2)) { min-inline-size: 13rem; }
.service-contract-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(3)),
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(3)) { min-inline-size: 10rem; }
.service-contract-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(4)),
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(4)) { min-inline-size: 15rem; }
.service-contract-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(5)),
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(5)) { min-inline-size: 11rem; }
.service-contract-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(6)),
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(6)) { min-inline-size: 9rem; }
.service-contract-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(7)),
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(7)) { min-inline-size: 8rem; }
.service-contract-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(8)),
.service-contract-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(8)) { min-inline-size: 8rem; }

.service-contract-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-block-start: 16px;
  padding-block: 10px;
  background: transparent;
  border-block-start: 0;
}

:global(.service-contract-cancel-overlay .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .3) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
:global(.v-overlay:has(.service-contract-cancel-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .3) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
:global(.service-contract-cancel-dialog) { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .2) !important; border-radius: 16px !important; background: rgb(var(--v-theme-surface)) !important; box-shadow: 0 24px 48px rgba(0, 0, 0, .18) !important; text-align: center; }
.service-contract-cancel-dialog__content { display: flex; flex-direction: column; align-items: center; padding: 2rem 2rem 1.25rem !important; }
.service-contract-cancel-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin-block-end: 1.25rem; border-radius: 50%; place-items: center; background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); }
.service-contract-cancel-dialog__title { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1.2rem; font-weight: 700; line-height: 1.45; }
.service-contract-cancel-dialog__description { max-inline-size: 24rem; margin: .65rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); line-height: 1.65; }
.service-contract-cancel-dialog__actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: 0 1.5rem 1.5rem !important; }
.service-contract-cancel-dialog__button { min-block-size: 42px; font-weight: 700; text-transform: none; }
.service-contract-cancel-dialog__button--cancel {
  border: 1px solid rgba(var(--v-theme-secondary), .52) !important;
  background: rgba(var(--v-theme-secondary), .13) !important;
  color: rgb(var(--v-theme-secondary)) !important;
}
.service-contract-cancel-dialog__button--cancel:hover,
.service-contract-cancel-dialog__button--cancel:focus-visible,
.service-contract-cancel-dialog__button--cancel.v-btn--active,
.service-contract-cancel-dialog__button--cancel:active {
  background: rgb(var(--v-theme-secondary)) !important;
  color: #fff !important;
}

@media (max-width: 600px) {
  .service-contract-wizard :deep(.v-row) {
    margin: -2px !important;
  }
  .service-contract-wizard :deep(.v-row > .v-col) {
    padding: 2px !important;
  }
  .service-contract-wizard :deep(.v-row > .v-col.service-contract-quarter-row) {
    flex-basis: 100% !important;
    max-inline-size: 100% !important;
  }
  .service-contract-wizard :deep(.v-row > .v-col.service-contract-third-row) {
    flex-basis: 100% !important;
    max-inline-size: 100% !important;
  }
  .service-contract-wizard-progress { gap: 2px; padding-inline: 10px; }
  .service-contract-wizard-progress__step { gap: 5px; padding-inline: 2px; }
  .service-contract-wizard-progress__step:not(:last-child)::after { display: none; }
  .service-contract-wizard-progress__indicator { inline-size: 22px; block-size: 22px; }
  .service-contract-wizard-progress__label { font-size: .68rem; }
  .service-contract-wizard :deep(.erp-field-label) { margin-block-end: 4px; font-size: .75rem; }
  .service-contract-summary { align-items: flex-start; flex-direction: column; gap: 3px; margin-block-start: 12px; }
}

@media (min-width: 600px) {
  /* A full field otherwise includes the 6px gap that sits between two half
     fields. Match the visible width of a 6 + 6 row without changing the grid. */
  .service-contract-wizard :deep(.v-row > .v-col.contract-wizard-full-row) {
    padding-inline: 6px !important;
  }
}

@media (max-width: 959px) {
  .service-contract-table.responsive-data-table--mobile-cards :deep(.v-table__wrapper > table > tbody > tr:hover > td),
  .service-contract-table.responsive-data-table--mobile-cards :deep(.v-data-table__wrapper > table > tbody > tr:hover > td),
  .service-contract-table.responsive-data-table--mobile-cards :deep(.responsive-data-table__mobile-row:hover > td) {
    background-color: transparent !important;
  }

  .service-contract-table :deep(.v-table__wrapper),
  .service-contract-table :deep(.v-data-table__wrapper) {
    overflow: clip;
  }

  .service-contract-table :deep(.v-table__wrapper > table),
  .service-contract-table :deep(.v-data-table__wrapper > table) {
    inline-size: 100%;
    min-inline-size: 0;
    table-layout: fixed;
  }

  .service-contract-table :deep(.v-data-table__thead) {
    display: none;
  }

  .service-contract-table :deep(.responsive-data-table__mobile-row > td) {
    padding: .75rem;
  }
}
</style>
