<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { type Applicant, type ApplicantStage, type BuiltInService, type ApplicantPayload, type ApplicantListParams, type ConsultantReview, applicantServicePayload } from '@/api/applicants'
import ApplicantServiceFields from '@/components/ApplicantServiceFields.vue'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'
import { translateApplicantStage, translateBackendValue } from '@/utils/localization'
import CrmRelationshipDetails from '@/components/crm/CrmRelationshipDetails.vue'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import ApplicantPipeline from '@/pages/applicant-pipeline.vue'
import { useAcademyEnabled } from '@/composables/useAcademyEnabled'
import EmployeeAvatar from '@/components/hr/EmployeeAvatar.vue'

const toast = useToast()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()
const { enabled: academyEnabled } = useAcademyEnabled()

const rows = ref<Applicant[]>([])
// Keep the table in its loading state through catalogue bootstrapping so the
// first paint is always the row skeleton, never an empty table flash.
const loading = ref(true)
// The shared A4 report prints the whole filtered set, not the visible page, so
// it is filled from every page right before printing.
const printing = ref(false)
const reportRows = ref<string[][]>([])
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const filterDialog = ref(false)
const displayView = ref<'table' | 'kanban'>(route.query.view === 'kanban' ? 'kanban' : 'table')

function setDisplayView(value: 'table' | 'kanban') {
  displayView.value = value
  const query = { ...route.query }
  if (value === 'kanban') query.view = 'kanban'
  else delete query.view
  void router.replace({ query })
}

const search = ref((route.query.search as string) ?? '')
const focus = ref<ApplicantListParams['focus']>(['outstanding', 'admitted', 'awaiting_decision', 'unassigned'].includes(String(route.query.focus)) ? route.query.focus as ApplicantListParams['focus'] : '')
const focusOptions = computed(() => [
  { title: t('agencyDashboard.toCollect'), value: 'outstanding' },
  { title: t('agencyDashboard.admitted'), value: 'admitted' },
  { title: t('agencyDashboard.awaitingDecision', { count: '' }), value: 'awaiting_decision' },
  { title: t('agencyDashboard.unassigned', { count: '' }), value: 'unassigned' },
])
const service = ref<BuiltInService | ''>(['scholarship', 'private', 'test_offer', 'academy'].includes(String(route.query.service)) ? route.query.service as BuiltInService : '')
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  ...(academyEnabled.value ? [{ title: t('educational.builtInServices.academy'), value: 'academy' }] : []),
  { title: t('educational.builtInServices.private'), value: 'private' },
])
// The stage tab strip drives this same filter, so deep links such as
// `?stage=visa_processing` from the dashboard and the pipeline keep working.
const stage = ref((route.query.stage as string) ?? '')
const status = ref<'active' | 'closed' | ''>((route.query.status as 'active' | 'closed') ?? '')
const paymentStatus = ref<'paid' | 'unpaid' | 'partially_paid' | ''>('')
const queryId = (key: string) => /^\d+$/.test(String(route.query[key] ?? '')) && Number(route.query[key]) > 0 ? Number(route.query[key]) : null
const responsibleEmployeeId = ref<number | null>(queryId('responsible_employee_id'))
const countryId = ref<number | null>(queryId('country_id'))
const universityId = ref<number | null>(queryId('university_id'))
const educationLevelId = ref<number | null>(null)
const semesterId = ref<number | null>(null)
const queryDate = (key: string) => /^\d{4}-\d{2}-\d{2}$/.test(String(route.query[key] ?? '')) ? String(route.query[key]) : ''
const dateFrom = ref(queryDate('date_from'))
const dateTo = ref(queryDate('date_to'))
const invalidDateRange = computed(() => !!dateFrom.value && !!dateTo.value && dateFrom.value > dateTo.value)
const dockFilterCalendar = (_selectedDates: Date[], _dateString: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.setProperty('transform', 'translateY(0) scale(0.86)', 'important')
    instance.calendarContainer.style.setProperty('transform-origin', 'top left', 'important')
    instance.calendarContainer.style.setProperty('z-index', '2400', 'important')
  })
}
const filterDraft = reactive({
  focus: focus.value,
  service: service.value,
  status: status.value,
  paymentStatus: paymentStatus.value,
  responsibleEmployeeId: responsibleEmployeeId.value,
  countryId: countryId.value,
  universityId: universityId.value,
  educationLevelId: educationLevelId.value,
  semesterId: semesterId.value,
  dateFrom: dateFrom.value,
  dateTo: dateTo.value,
})

const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const levels = ref<MasterDataRecord[]>([])
const semesters = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])
const admissionEmployees = ref<Employee[]>([])
const stages = ref<ApplicantStage[]>([])

const canViewPipeline = computed(() => canAccess('view_applicants'))
// Payment state is a finance field: the column is omitted entirely for a
// viewer without the permission rather than rendered as a dash on every row.
const canViewFinancials = computed(() => canAccess('view_applicant_financials'))
const canAssignEmployee = computed(() => canAccess('assign_applicant_employee'))
const canReview = computed(() => canAccess('review_visitors'))
const hasActiveFilters = computed(() => Boolean(
  focus.value
  || service.value
  || status.value
  || paymentStatus.value
  || responsibleEmployeeId.value
  || countryId.value
  || universityId.value
  || educationLevelId.value
  || semesterId.value
  || dateFrom.value
  || dateTo.value,
))
const headers = computed(() => [
  { title: t('educational.id'), key: 'id', sortable: false },
  { title: t('educational.applicant'), key: 'name', sortable: false, width: '20rem' },
  { title: t('educational.employee'), key: 'employee', sortable: false },
  { title: t('educational.service'), key: 'service', sortable: false, width: '21rem' },
  { title: t('educational.country'), key: 'country', sortable: false },
  { title: t('educational.university'), key: 'university', sortable: false },
  { title: t('educational.stage'), key: 'stage', sortable: false },
  { title: t('educational.status'), key: 'status', sortable: false },
  { title: t('pages.finance.income.income.paymentStatus'), key: 'paymentStatus', sortable: false },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
].filter(column => column.key !== 'paymentStatus' || canViewFinancials.value))
const serviceLabel = (row: Applicant) => serviceOptions.value.find(option => option.value === row.service)?.title ?? row.service
const destinationLabel = (row: Applicant) => {
  const details = row.details
  const place = [details?.country?.name, details?.university?.name].filter(Boolean).join(' · ')

  return [serviceLabel(row), place].filter(Boolean).join(' · ')
}
const countryLabel = (row: Applicant) => row.details?.country?.name ?? '—'
const universityLabel = (row: Applicant) => row.details?.university?.name ?? '—'
const paymentStatusLabel = (row: Applicant) => row.payment_status ? t(`finance.paymentStatus.${row.payment_status}`) : t('educational.noServiceLines')
const employeeAssignments = (row: Applicant) => row.responsible_employees?.length
  ? row.responsible_employees
  : row.responsible_employee ? [row.responsible_employee] : []
const employeeLabel = (row: Applicant) => {
  if (row.service === 'academy')
    return '—'
  const assigned = employeeAssignments(row).map(employee => employee.name).filter(Boolean)

  return assigned.length ? assigned.join(', ') : t('educational.unassigned')
}
// The API returns the same stage it filters by: the service line's under a
// service filter, the applicant mirror otherwise.
const stageOf = (row: Applicant) => row.display_stage ?? row.current_stage
const reportRow = (row: Applicant) => [
  `#${row.id}`,
  row.name,
  employeeLabel(row),
  serviceLabel(row),
  countryLabel(row),
  universityLabel(row),
  stageOf(row) ? translateApplicantStage(stageOf(row)!) : '—',
  translateBackendValue(row.status),
  ...(canViewFinancials.value ? [paymentStatusLabel(row)] : []),
]
const setReportRows = (source: Applicant[]) => { reportRows.value = source.map(reportRow) }

const createDialog = ref(false)
const creating = ref(false)
const reviewDialog = ref(false)

// A modal scrim must never outlive the page. Closing the dialogs before the
// route changes prevents the dark overlay from covering the next page when the
// user leaves via browser back/forward or a deep link.
onBeforeRouteLeave(() => {
  filterDialog.value = false
  createDialog.value = false
  reviewDialog.value = false
})
const reviewApplicant = ref<Applicant | null>(null)
const consultantReview = ref<ConsultantReview | ''>('')
const reviewOptions = computed(() => [
  { title: t('common.statuses.good'), value: 'good' },
  { title: t('common.statuses.bad'), value: 'bad' },
  { title: t('common.statuses.great'), value: 'great' },
])
const createForm = ref({ name: '', father_name: '', father_phone: '', backup_phone: '', contact: '', email: '', responsible_employee_ids: [] as number[], responsible_employee_id: null as number | null, notes: '' })
const serviceForm = ref<ApplicantPayload>({ service: 'scholarship' })
// The list is driven by the workflow stages themselves: every stage is a tab,
// and the pipeline keeps its order while the terminal (closing) stages are
// grouped at the end. This replaces the coarse Operation / Final / Cancelled
// boards, which were just pre-grouped sets of these same stages.
const pipelineStages = computed(() => stages.value.filter(item => !item.is_terminal))
const terminalStages = computed(() => stages.value.filter(item => item.is_terminal))

const terminalStageIcons: Record<string, string> = {
  completed: 'tabler-circle-check',
  cancelled: 'tabler-ban',
  rejected: 'tabler-circle-x',
  withdrawn: 'tabler-arrow-back-up',
}

const terminalStageIcon = (key: string) => terminalStageIcons[key] ?? 'tabler-flag'

const terminalStageColor = (key: string) => {
  if (key === 'completed')
    return 'success'

  return key === 'rejected' ? 'error' : 'secondary'
}

const listParams = (): ApplicantListParams => ({
  focus: focus.value || undefined,
  service: service.value || undefined,
  stage: stage.value || undefined,
  status: (status.value || undefined) as 'active' | 'closed' | undefined,
  search: search.value || undefined,
  payment_status: paymentStatus.value || undefined,
  responsible_employee_id: responsibleEmployeeId.value,
  country_id: countryId.value,
  university_id: universityId.value,
  education_level_id: educationLevelId.value,
  semester_id: semesterId.value,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
})

// The Kanban board receives this exact same filter state. Its API needs a
// single stage catalogue, therefore Scholarship is used only until a service
// is chosen in the shared Applications filter.
const pipelineFilters = computed<ApplicantListParams>(() => ({
  ...listParams(),
  service: service.value || 'scholarship',
}))

async function load() {
  if (invalidDateRange.value) return
  loading.value = true
  try {
    const result = await ApplicantApi.list({ ...listParams(), page: page.value, per_page: perPage.value })

    rows.value = result.data
    page.value = result.current_page
    perPage.value = result.per_page
    total.value = result.total
    setReportRows(result.data)
  }
  catch {
    toast.error(t('educational.messages.loadApplicantsFailed'))
  }
  finally {
    loading.value = false
  }
}

// Print is global: walk the filtered pages so the shared report covers the
// whole result set instead of the 25 rows currently on screen.
async function printReport() {
  if (invalidDateRange.value) return
  printing.value = true
  try {
    const all: Applicant[] = []
    let nextPage = 1
    let lastPage = 1
    do {
      const result = await ApplicantApi.list({ ...listParams(), page: nextPage, per_page: 100 })
      all.push(...result.data)
      lastPage = result.last_page
      nextPage += 1
    } while (nextPage <= lastPage)

    setReportRows(all)
    await printDocument({ bodyClass: 'printing-table-applicants', pageStyle: 'size: A4 landscape; margin: 12mm;' })
  }
  catch {
    toast.error(t('educational.messages.loadApplicantsFailed'))
  }
  finally {
    printing.value = false
  }
}

async function loadStages() {
  // Stage tabs follow the service and the destination: scholarship and private
  // stages can be limited to a country or university. Without a service, the
  // API returns the cross-service list deduped by key.
  stages.value = await ApplicantApi.stages({
    service: service.value || undefined,
    country_id: countryId.value,
    university_id: universityId.value,
  })
  resolveDeepLinkedStage()
}

// A stage belongs to exactly one service. A deep link that carries only the
// stage adopts its service, so the tab strip, the table filter and the Stage
// column all describe the same pipeline instead of a cross-service mix.
function resolveDeepLinkedStage() {
  if (!stage.value)
    return

  if (!service.value) {
    const matched = stages.value.find(item => item.key === stage.value && item.service)

    if (matched?.service) {
      service.value = matched.service

      return
    }
  }

  if (!stages.value.some(item => item.key === stage.value))
    stage.value = ''
}

async function loadCatalogs() {
  try {
    const [countryRows, universityRows, levelRows, semesterRows, employeeRows, admissionRows] = await Promise.all([
      MasterDataApi.list('countries'),
      MasterDataApi.list('universities'),
      MasterDataApi.list('education-levels'),
      MasterDataApi.list('semesters'),
      canAccess('view_employees') ? EmployeeApi.getOptions() : Promise.resolve([]),
      EmployeeApi.getAssignmentOptions('admission'),
    ])

    countries.value = countryRows
    universities.value = universityRows
    levels.value = levelRows
    semesters.value = semesterRows
    employees.value = employeeRows
    admissionEmployees.value = admissionRows
    await loadStages()
  }
  catch {
    toast.error(t('educational.messages.loadFiltersFailed'))
  }
}

async function applyFilters() {
  page.value = 1

  try {
    await loadStages()
  }
  catch {
    toast.error(t('educational.messages.loadFiltersFailed'))
  }

  await load()
}

const syncFilterDraft = () => {
  filterDraft.focus = focus.value
  filterDraft.service = service.value
  filterDraft.status = status.value
  filterDraft.paymentStatus = paymentStatus.value
  filterDraft.responsibleEmployeeId = responsibleEmployeeId.value
  filterDraft.countryId = countryId.value
  filterDraft.universityId = universityId.value
  filterDraft.educationLevelId = educationLevelId.value
  filterDraft.semesterId = semesterId.value
  filterDraft.dateFrom = dateFrom.value
  filterDraft.dateTo = dateTo.value
}

const openFilters = () => {
  syncFilterDraft()
  filterDialog.value = true
}

const applyFilterDialog = async () => {
  if (filterDraft.dateFrom && filterDraft.dateTo && filterDraft.dateFrom > filterDraft.dateTo)
    return

  focus.value = filterDraft.focus
  service.value = filterDraft.service
  status.value = filterDraft.status
  paymentStatus.value = filterDraft.paymentStatus
  responsibleEmployeeId.value = filterDraft.responsibleEmployeeId
  countryId.value = filterDraft.countryId
  universityId.value = filterDraft.universityId
  educationLevelId.value = filterDraft.educationLevelId
  semesterId.value = filterDraft.semesterId
  dateFrom.value = filterDraft.dateFrom
  dateTo.value = filterDraft.dateTo
  filterDialog.value = false
  await applyFilters()
}

function onTabChange() {
  page.value = 1
  load()
}

function updateTableOptions(options: { page: number; itemsPerPage: number }) {
  if (page.value === options.page && perPage.value === options.itemsPerPage)
    return

  page.value = options.page
  perPage.value = options.itemsPerPage
  load()
}

async function create() {
  if (!createForm.value.name.trim())
    return

  creating.value = true
  try {
    const payload: Record<string, unknown> = {
      name: createForm.value.name.trim(),
      father_name: createForm.value.father_name.trim() || null,
      father_phone: createForm.value.father_phone.trim() || null,
      backup_phone: createForm.value.backup_phone || null,
      contact: createForm.value.contact || null,
      email: createForm.value.email || null,
    }

    Object.assign(payload, applicantServicePayload(serviceForm.value))
    // Responsibility is plural and only case-bearing services accept it.
    if (['scholarship', 'private'].includes(serviceForm.value.service ?? ''))
      payload.responsible_employee_ids = createForm.value.responsible_employee_ids
    if (createForm.value.responsible_employee_id)
      payload.responsible_employee_id = createForm.value.responsible_employee_id

    if (createForm.value.notes.trim())
      payload.notes = createForm.value.notes.trim()

    const created = await ApplicantApi.create(payload)

    createDialog.value = false
    await load()
    toast.success(t('educational.messages.caseCreated', { id: created.id }))
    router.push({ name: 'applicant-case', params: { id: created.id } })
  }
  catch (error) {
  }
  finally {
    creating.value = false
  }
}

function openConsultantReview(applicant: Applicant) {
  reviewApplicant.value = applicant
  consultantReview.value = applicant.consultant_review ?? ''
  reviewDialog.value = true
}

async function saveConsultantReview() {
  if (!reviewApplicant.value)
    return

  try {
    creating.value = true
    const updated = await ApplicantApi.updateConsultantReview(reviewApplicant.value.id, consultantReview.value || null)
    const index = rows.value.findIndex(row => row.id === updated.id)
    if (index !== -1)
      rows.value[index] = updated
    reviewDialog.value = false
    toast.success(t('pages.contracts.visitor.visitor.consultantReviewSaved'))
  }
  catch (error) {
  }
  finally {
    creating.value = false
  }
}

async function clearFilters() {
  focus.value = ''
  service.value = ''
  stage.value = ''
  status.value = ''
  paymentStatus.value = ''
  responsibleEmployeeId.value = null
  countryId.value = null
  universityId.value = null
  educationLevelId.value = null
  semesterId.value = null
  dateFrom.value = ''
  dateTo.value = ''
  page.value = 1
  syncFilterDraft()
  filterDialog.value = false
  // The destination context was reset too, so the stage strip must return to
  // the unscoped catalogue before the list reloads.
  await applyFilters()
}

const debouncedSearch = useDebounceFn(() => {
  page.value = 1
  void load()
}, 300)

// Pagination controls call load() explicitly; no page watcher, so changing
// pages never fires duplicate requests.
watch(countryId, () => {
  const stillVisible = universities.value
    .filter(u => u.is_active && (!countryId.value || u.country_id === countryId.value))
    .some(u => u.id === universityId.value)

  if (!stillVisible)
    universityId.value = null
})

watch(() => filterDraft.countryId, () => {
  const stillVisible = universities.value
    .filter(university => university.is_active && (!filterDraft.countryId || university.country_id === filterDraft.countryId))
    .some(university => university.id === filterDraft.universityId)

  if (!stillVisible)
    filterDraft.universityId = null
})

// A tab from the previous service's pipeline must not stay selected: the
// server would filter by a stage key that no longer belongs to this service.
watch(service, async () => {
  const previousStage = stage.value

  try {
    await loadStages()
  }
  catch {
    toast.error(t('educational.messages.loadFiltersFailed'))

    return
  }

  // Without a service there is no stage strip, so an old stage must not keep
  // filtering invisibly; and a stage from the previous service does not exist
  // in the newly selected one.
  if (previousStage && (!service.value || !stages.value.some(item => item.key === previousStage))) {
    stage.value = ''
    page.value = 1
    load()
  }
})


onMounted(async () => {
  await loadCatalogs()
  await load()
})
</script>

<template>
  <div class="education-workspace erp-workspace">
    <ErpPageHeader :title="$t('educational.applications')" section="applications" />
  <VCard>
    <VCardText>
      <VRow align="center" class="table-toolbar">
        <VCol cols="12" md="6" lg="5">
          <ErpSearchField
            v-model="search"
            :placeholder="$t('educational.searchApplicant')"
            @input="debouncedSearch"
          />
        </VCol>
        <VCol cols="12" md="6" lg="7" class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2">
          <div class="applications-view-switch" role="group" :aria-label="$t('educational.applications')">
            <VBtn size="small" :variant="displayView === 'table' ? 'flat' : 'tonal'" :color="displayView === 'table' ? 'primary' : undefined" @click="setDisplayView('table')">Table</VBtn>
            <VBtn size="small" :variant="displayView === 'kanban' ? 'flat' : 'tonal'" :color="displayView === 'kanban' ? 'primary' : undefined" @click="setDisplayView('kanban')">Kanban</VBtn>
          </div>
          <VBtn
            icon="tabler-filter"
            :variant="hasActiveFilters ? 'flat' : 'tonal'"
            :color="hasActiveFilters ? 'primary' : undefined"
            size="small"
            class="applications-filter-trigger"
            :aria-label="$t('common.messages.filters')"
            @click="openFilters"
          >
            <VIcon icon="tabler-filter" />
            <VTooltip activator="parent" location="top">{{ $t('common.messages.filters') }}</VTooltip>
          </VBtn>
          <VBtn
            icon="tabler-printer"
            variant="tonal"
            class="flex-shrink-0"
            :aria-label="$t('common.actions.print')"
            :loading="loading || printing"
            @click="printReport"
          >
            <VIcon icon="tabler-printer" />
            <VTooltip activator="parent" location="top">{{ $t('common.actions.print') }}</VTooltip>
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
    <template v-if="displayView === 'table'">
    <VTabs v-if="service" v-model="stage" show-arrows class="px-3" @update:model-value="onTabChange">
      <VTab value="">{{ $t('educational.allApplications') }}</VTab>
      <VTab v-for="item in pipelineStages" :key="item.id" :value="item.key">{{ translateApplicantStage(item) }}</VTab>
      <VDivider v-if="terminalStages.length && pipelineStages.length" vertical class="stage-tabs-divider align-self-center" />
      <VTab v-for="item in terminalStages" :key="item.id" :value="item.key">
        <VIcon start size="small" :icon="terminalStageIcon(item.key)" :color="terminalStageColor(item.key)" />
        {{ translateApplicantStage(item) }}
        <VTooltip activator="parent" location="bottom">{{ $t('educational.terminalStageTab') }}</VTooltip>
      </VTab>
    </VTabs>
    <VDivider />
    <ResponsiveDataTableServer
      :headers="headers"
      :items="rows"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      item-value="id"
      class="applications-responsive-table"
      mobile-breakpoint="sm"
      mobile-cards
      mobile-expand-all
      mobile-actions-as-menu
      mobile-title-key="name"
      mobile-action-key="actions"
      :mobile-fields="['stage', 'employee', 'destination', 'status']"
      :sticky-key-column="false"
      :sticky-action-column="false"
      :skeleton-rows="5"
      :show-scroll-affordance="false"
      pagination
      @update:options="updateTableOptions"
    >
      <template #loading><TableLoadingSkeleton :columns="headers.length" :label="$t('common.messages.loading')" /></template>
      <template #no-data>
        <div class="applications-empty-state">
          <TableEmptyState
            icon="tabler-clipboard-list"
            :min-height="0"
            :title="$t('pages.contracts.visitor.visitor.noApplicants')"
            :description="$t('educational.noApplicants')"
          />
        </div>
      </template>
      <template #item.id="{ item }">#{{ item.id }}</template>
      <template #item.name="{ item }"><span class="font-weight-medium">{{ item.name }}</span></template>
      <template #item.employee="{ item }">
        <div v-if="item.service !== 'academy' && employeeAssignments(item).length" class="d-flex flex-wrap align-center ga-2">
          <span v-for="employee in employeeAssignments(item)" :key="employee.id" class="d-inline-flex align-center ga-1">
            <EmployeeAvatar :name="employee.name" :src="employee.avatar" size="28" />
            <span>{{ employee.name }}</span>
          </span>
        </div>
        <span v-else>{{ item.service === 'academy' ? '—' : $t('educational.unassigned') }}</span>
      </template>
      <template #item.service="{ item }"><VChip size="x-small" variant="tonal">{{ serviceLabel(item) }}</VChip></template>
      <template #item.country="{ item }"><span class="text-medium-emphasis">{{ countryLabel(item) }}</span></template>
      <template #item.university="{ item }"><span class="text-medium-emphasis">{{ universityLabel(item) }}</span></template>
      <template #item.stage="{ item }">{{ stageOf(item) ? translateApplicantStage(stageOf(item)!) : '—' }}</template>
      <template #item.status="{ item }"><VChip size="small" :color="item.status === 'active' ? 'success' : 'secondary'">{{ translateBackendValue(item.status) }}</VChip></template>
      <template #item.paymentStatus="{ item }"><VChip v-if="item.payment_status" size="small" variant="tonal" :color="item.payment_status === 'paid' ? 'success' : item.payment_status === 'partially_paid' ? 'warning' : 'error'">{{ paymentStatusLabel(item) }}</VChip><span v-else class="text-medium-emphasis">{{ $t('educational.noServiceLines') }}</span></template>
      <template #item.actions="{ item }">
        <div class="d-flex justify-end align-center">
          <VMenu location="bottom end" :close-on-content-click="false">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon="tabler-dots-vertical"
                variant="tonal"
                size="small"
                class="applications-actions-trigger"
                :aria-label="$t('common.messages.actions')"
              />
            </template>
            <VList class="applications-actions-menu" density="compact" min-width="220">
              <CrmRelationshipDetails type="applicant" :id="item.id" :title="item.name" menu-action />
              <VListItem
                v-if="canReview"
                prepend-icon="tabler-message"
                :title="$t('pages.contracts.visitor.visitor.consultantReview')"
                :disabled="creating"
                @click="openConsultantReview(item)"
              />
              <VListItem
                prepend-icon="tabler-folder-open"
                :title="$t('educational.openCase')"
                @click="router.push({ name: 'applicant-case', params: { id: item.id } })"
              />
            </VList>
          </VMenu>
        </div>
      </template>
    </ResponsiveDataTableServer>
    </template>
    <ApplicantPipeline v-else embedded :external-filters="pipelineFilters" />
  </VCard>
  <PrintableListReport :title="t('educational.applications')" :subtitle="t('common.crmUi.printReports.applications.subtitle')" :columns="headers.slice(0, -1).map(column => column.title)" :rows="reportRows" />
  </div>
  <VDialog
    v-model="filterDialog"
    max-width="860"
    content-class="applications-filter-dialog"
    scroll-strategy="block"
  >
    <VCard class="applications-filter-card">
      <VCardTitle class="applications-filter-dialog__title px-5 py-4">
        <span class="applications-filter-dialog__heading">{{ $t('common.messages.filters') }}</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="error"
          class="applications-filter-dialog__close"
          :aria-label="$t('common.actions.close')"
          @click="filterDialog = false"
        />
      </VCardTitle>
      <VCardText class="applications-filter-dialog__body pa-5">
        <VRow>
          <VCol cols="12" sm="6" md="4">
            <ErpField v-model="filterDraft.focus" kind="select" :items="focusOptions" :label="$t('educational.needsAttention')" :placeholder="$t('educational.needsAttention')" clearable />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <ErpField v-model="filterDraft.service" kind="select" :items="serviceOptions" :label="$t('educational.service')" :placeholder="$t('educational.service')" clearable />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <ErpField v-model="filterDraft.status" kind="select" :items="[{ title: $t('educational.active'), value: 'active' }, { title: $t('educational.closed'), value: 'closed' }]" :label="$t('educational.status')" :placeholder="$t('educational.status')" clearable />
          </VCol>
          <VCol v-if="canViewFinancials" cols="12" sm="6" md="4">
            <ErpField v-model="filterDraft.paymentStatus" kind="select" :items="[{ title: $t('finance.paymentStatus.paid'), value: 'paid' }, { title: $t('finance.paymentStatus.partially_paid'), value: 'partially_paid' }, { title: $t('finance.paymentStatus.unpaid'), value: 'unpaid' }]" :label="$t('pages.finance.income.income.paymentStatus')" :placeholder="$t('pages.finance.income.income.paymentStatus')" clearable />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <ErpField v-model="filterDraft.responsibleEmployeeId" kind="autocomplete" :items="employees.map(employee => ({ title: employee.name, value: employee.id }))" :label="$t('educational.employee')" :placeholder="$t('educational.employee')" clearable />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <ErpField v-model="filterDraft.countryId" kind="select" :items="countries.filter(country => country.is_active).map(country => ({ title: country.name, value: country.id }))" :label="$t('educational.country')" :placeholder="$t('educational.country')" clearable />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <ErpField v-model="filterDraft.universityId" kind="select" :items="universities.filter(university => university.is_active && (!filterDraft.countryId || university.country_id === filterDraft.countryId)).map(university => ({ title: university.name, value: university.id }))" :label="$t('educational.university')" :placeholder="$t('educational.university')" clearable />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <ErpField v-model="filterDraft.educationLevelId" kind="select" :items="levels.filter(level => level.is_active).map(level => ({ title: level.name, value: level.id }))" :label="$t('educational.educationLevel')" :placeholder="$t('educational.educationLevel')" clearable />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <ErpField v-model="filterDraft.semesterId" kind="select" :items="semesters.filter(semester => semester.is_active).map(semester => ({ title: semester.name, value: semester.id }))" :label="$t('educational.semester')" :placeholder="$t('educational.semester')" clearable />
          </VCol>
          <VCol cols="12" sm="6">
            <div class="erp-field">
              <label class="erp-field-label">{{ $t('common.fields.startDate') }}</label>
              <AppDateTimePicker v-model="filterDraft.dateFrom" class="erp-field-control" :placeholder="$t('common.fields.startDate')" persistent-placeholder :config="{ dateFormat: 'Y-m-d', maxDate: filterDraft.dateTo || undefined, static: false, onOpen: dockFilterCalendar }" />
            </div>
          </VCol>
          <VCol cols="12" sm="6">
            <div class="erp-field">
              <label class="erp-field-label">{{ $t('common.fields.endDate') }}</label>
              <AppDateTimePicker v-model="filterDraft.dateTo" class="erp-field-control" :placeholder="$t('common.fields.endDate')" persistent-placeholder :config="{ dateFormat: 'Y-m-d', minDate: filterDraft.dateFrom || undefined, static: false, onOpen: dockFilterCalendar }" />
            </div>
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="applications-filter-dialog__actions px-5 py-4">
        <VBtn class="applications-filter-dialog__reset" variant="flat" @click="clearFilters">
          {{ $t('common.messages.reset') }}
        </VBtn>
        <VBtn
          class="applications-filter-dialog__apply"
          color="primary"
          variant="flat"
          :loading="loading"
          :disabled="!!filterDraft.dateFrom && !!filterDraft.dateTo && filterDraft.dateFrom > filterDraft.dateTo"
          @click="applyFilterDialog"
        >
          {{ $t('educational.apply') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="createDialog" max-width="1100" scrollable class="erp-form">
    <VCard :title="$t('educational.newApplicantCase')">
      <VCardText>
        <VRow dense>
          <VCol cols="12" sm="6" md="4"><ErpField v-model="createForm.name" :label="$t('common.fields.name')" required /></VCol>
          <VCol cols="12" sm="6" md="4"><ErpField v-model="createForm.contact" :label="$t('common.messages.contact')" /></VCol>
          <VCol cols="12" sm="6" md="4"><ErpField v-model="createForm.email" :label="$t('common.fields.email')" type="email" /></VCol>
          <VCol v-if="['scholarship', 'private'].includes(serviceForm.service || '')" cols="12" sm="6" md="4"><ErpField v-model="createForm.father_name" :label="$t('educational.fatherName')" /></VCol>
          <VCol v-if="['scholarship', 'private'].includes(serviceForm.service || '')" cols="12" sm="6" md="4"><ErpField v-model="createForm.father_phone" :label="$t('educational.fatherPhone')" /></VCol>
          <VCol v-if="['scholarship', 'private'].includes(serviceForm.service || '')" cols="12" sm="6" md="4"><ErpField kind="select" v-model="createForm.responsible_employee_ids" multiple chips closable-chips :items="admissionEmployees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.responsibleEmployees')" clearable /></VCol>
          <VCol v-if="serviceForm.service === 'test_offer'" cols="12" sm="6" md="4"><ErpField v-model="createForm.backup_phone" :label="$t('erp.backupPhone')" /></VCol>
          <VCol cols="12"><ApplicantServiceFields v-model="serviceForm" /></VCol>
          <VCol cols="12"><ErpField kind="textarea" rows="2" v-model="createForm.notes" :label="$t('educational.notes')" /></VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="tonal" @click="createDialog = false">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" variant="flat" :loading="creating" :disabled="!createForm.name.trim()" @click="create">{{ $t('educational.createCase') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="reviewDialog" max-width="460" content-class="visitor-form-dialog visitor-review-dialog">
    <VCard class="visitor-form-card">
      <VCardTitle class="visitor-dialog-title px-5 py-4">
        <span class="visitor-dialog-heading">{{ $t('pages.contracts.visitor.visitor.consultantReview') }}</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="error"
          class="visitor-dialog-close"
          :aria-label="$t('common.actions.close')"
          :disabled="creating"
          @click="reviewDialog = false"
        />
      </VCardTitle>
      <VCardText class="visitor-dialog-body pa-5">
        <ErpField
          v-model="consultantReview"
          kind="select"
          :items="reviewOptions"
          :label="$t('pages.contracts.visitor.visitor.consultantReview')"
          :placeholder="$t('pages.contracts.visitor.visitor.placeholders.consultantReview')"
          clearable
        />
      </VCardText>
      <VCardActions class="visitor-dialog-actions px-5 py-4">
        <VSpacer />
        <VBtn class="visitor-dialog-cancel" variant="flat" color="error" :disabled="creating" @click="reviewDialog = false">
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn class="visitor-dialog-submit" color="primary" variant="flat" :loading="creating" @click="saveConsultantReview">
          {{ $t('common.actions.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
/* Separates the open pipeline stages from the terminal (closing) stages. */
.stage-tabs-divider {
  block-size: 22px;
  margin-inline: 12px;
}

@media (max-width: 1279px) {
  .table-toolbar-actions { justify-content: flex-start; }
}

.applications-filter-trigger.v-btn--variant-flat {
  color: rgb(var(--v-theme-on-primary));
}

.applications-view-switch {
  display: inline-flex;
  gap: 2px;
}

.applications-view-switch .v-btn {
  min-inline-size: 74px;
}

.applications-responsive-table .v-table__wrapper {
  overflow-x: auto;
  scrollbar-width: none;
}

.applications-responsive-table .v-table__wrapper::-webkit-scrollbar {
  display: none;
}

.applications-responsive-table .v-table__wrapper > table {
  min-inline-size: 1700px;
}

.applications-responsive-table .v-table__wrapper > table > thead > tr > th,
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td {
  min-inline-size: 9rem;
  padding-inline: 1.25rem;
  white-space: nowrap;
}

/* Give every column room for longer names and destinations instead of
   compressing the intentionally scrollable operational table. */
.applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(1),
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(1) { min-inline-size: 5.5rem; }

.applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(2),
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(2) { min-inline-size: 16rem; }

.applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(3),
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(3) { min-inline-size: 13rem; }

.applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(4),
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(4) { min-inline-size: 13rem; }

.applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(5),
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(5) { min-inline-size: 11rem; }

.applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(6),
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(6) { min-inline-size: 15rem; }

.applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(7),
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(7) { min-inline-size: 10rem; }

.applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(8),
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(8) { min-inline-size: 9.5rem; }

.applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(9),
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(9) { min-inline-size: 11rem; }

.applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(10),
.applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(10) { min-inline-size: 5.5rem; }

@media (min-width: 960px) {
  /* Override the table's width distribution for the two long-text columns. */
  .applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(2),
  .applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(2) {
    inline-size: 20rem !important;
    min-inline-size: 20rem !important;
  }

  .applications-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(4),
  .applications-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(4) {
    inline-size: 21rem !important;
    min-inline-size: 21rem !important;
  }
}

.applications-responsive-table .v-table__wrapper > table > tbody > tr:hover > td {
  background-color: rgba(var(--v-theme-on-surface), 0.035) !important;
}

.applications-responsive-table .v-table__wrapper:has(.applications-empty-state) {
  overflow-x: hidden;
}

.applications-responsive-table .v-table__wrapper > table:has(.applications-empty-state) {
  min-inline-size: 100%;
  table-layout: fixed;
}

.applications-responsive-table .v-table__wrapper > table:has(.applications-empty-state) > thead > tr > th,
.applications-responsive-table .v-table__wrapper > table:has(.applications-empty-state) > tbody > tr > td {
  min-inline-size: 0 !important;
  white-space: normal;
}

.applications-responsive-table .v-table__wrapper > table > tbody > tr:has(.applications-empty-state):hover > td,
.applications-responsive-table .v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:hover > td {
  background-color: transparent !important;
}

.applications-responsive-table .v-table__wrapper > table > tbody > tr:has(.applications-empty-state) > td {
  padding: 0 !important;
}

.applications-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  inline-size: 100%;
  min-block-size: 17rem;
  padding-block: 1.25rem 2rem;
  padding-inline: 1.25rem;
}

.applications-empty-state .table-empty-state {
  min-block-size: 0 !important;
  padding-block: 1rem;
}

.applications-actions-trigger {
  border-radius: 999px !important;
  background-color: rgba(var(--v-theme-on-surface), 0.04) !important;
  color: rgba(var(--v-theme-on-surface), 0.72) !important;
}

.applications-actions-trigger:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.08) !important;
}

.applications-filter-card,
.visitor-form-dialog .visitor-form-card {
  overflow: hidden;
}

.applications-filter-dialog__title,
.applications-filter-dialog__actions,
.visitor-form-dialog .visitor-dialog-title,
.visitor-form-dialog .visitor-dialog-actions {
  display: flex;
  align-items: center;
  border-width: 0;
  border-style: solid;
  border-color: rgba(var(--v-theme-on-surface), 0.035);
  background-color: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%);
}

.applications-filter-dialog__title,
.visitor-form-dialog .visitor-dialog-title {
  border-block-end-width: 1px;
}

.applications-filter-dialog__actions,
.visitor-form-dialog .visitor-dialog-actions {
  border-block-start-width: 1px;
}

.applications-filter-dialog__heading,
.visitor-form-dialog .visitor-dialog-heading {
  font-size: 1.625rem;
  font-weight: 700;
}

.applications-filter-dialog__body,
.visitor-form-dialog .visitor-dialog-body {
  background-color: rgb(var(--v-theme-surface));
}

.applications-filter-dialog__actions {
  justify-content: flex-end;
  gap: 0.5rem;
}

.applications-filter-dialog__close,
.visitor-form-dialog .visitor-dialog-close {
  border: 1px solid rgba(var(--v-theme-error), 0.38);
  border-radius: 50%;
  block-size: 36px;
  color: rgb(var(--v-theme-error));
  inline-size: 36px;
}

.applications-filter-dialog__close:hover,
.visitor-form-dialog .visitor-dialog-close:hover {
  background-color: rgba(var(--v-theme-error), 0.18) !important;
}

.applications-filter-dialog__reset,
.visitor-form-dialog button.visitor-dialog-cancel.v-btn {
  border: 1px solid rgba(var(--v-theme-error), 0.62) !important;
  background-color: rgba(var(--v-theme-error), 0.12) !important;
  color: rgb(var(--v-theme-error)) !important;
}

.applications-filter-dialog__reset:hover,
.applications-filter-dialog__reset:focus-visible,
.visitor-form-dialog button.visitor-dialog-cancel.v-btn:hover,
.visitor-form-dialog button.visitor-dialog-cancel.v-btn:focus-visible {
  border-color: rgb(var(--v-theme-error));
  background-color: rgb(var(--v-theme-error)) !important;
  color: rgb(var(--v-theme-on-error)) !important;
}

.applications-filter-dialog__apply,
.visitor-form-dialog .visitor-dialog-submit {
  background-color: rgb(var(--v-theme-primary)) !important;
  color: rgb(var(--v-theme-on-primary)) !important;
}

.applications-filter-dialog__apply:hover,
.visitor-form-dialog .visitor-dialog-submit:hover {
  filter: brightness(0.9);
}

.v-overlay:has(.applications-filter-dialog) .v-overlay__scrim,
.v-overlay:has(.visitor-form-dialog) .v-overlay__scrim {
  backdrop-filter: blur(4px);
  background-color: rgba(var(--v-theme-on-surface), 0.24);
}

@media (max-width: 959px) {
  .applications-responsive-table.responsive-data-table--mobile-cards .v-table__wrapper > table > tbody > tr:hover > td {
    background-color: transparent !important;
  }

  .applications-responsive-table.responsive-data-table--mobile-cards .responsive-data-table__field + .responsive-data-table__field {
    border-block-start-color: rgba(var(--v-theme-on-surface), 0.08);
  }

  .applications-responsive-table.responsive-data-table--mobile-cards .responsive-data-table__field-value > .d-flex {
    justify-content: flex-end;
  }
}
</style>
