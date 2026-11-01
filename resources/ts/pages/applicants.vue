<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { type Applicant, type ApplicantStage, type BuiltInService, type ApplicantPayload, type ApplicantListParams, type ConsultantReview, applicantServicePayload } from '@/api/applicants'
import ApplicantServiceFields from '@/components/ApplicantServiceFields.vue'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'
import { translateApplicantStage, translateBackendValue } from '@/utils/localization'
import CrmRelationshipDetails from '@/components/crm/CrmRelationshipDetails.vue'
import { apiErrorMessage } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'

const toast = useToast()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

const rows = ref<Applicant[]>([])
const loading = ref(false)
// The shared A4 report prints the whole filtered set, not the visible page, so
// it is filled from every page right before printing.
const printing = ref(false)
const reportRows = ref<string[][]>([])
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const filtersOpen = ref(false)

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
  { title: t('educational.builtInServices.academy'), value: 'academy' },
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
const activeFilterCount = computed(() => [focus.value, service.value, search.value, stage.value, status.value, paymentStatus.value, responsibleEmployeeId.value, countryId.value, universityId.value, educationLevelId.value, semesterId.value, dateFrom.value, dateTo.value].filter(Boolean).length)
const headers = computed(() => [
  { title: t('educational.id'), key: 'id', sortable: false },
  { title: t('educational.applicant'), key: 'name', sortable: false },
  { title: t('educational.employee'), key: 'employee', sortable: false },
  { title: t('educational.destination'), key: 'destination', sortable: false },
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
const paymentStatusLabel = (row: Applicant) => row.payment_status ? t(`finance.paymentStatus.${row.payment_status}`) : t('educational.noServiceLines')
// The API returns the same stage it filters by: the service line's under a
// service filter, the applicant mirror otherwise.
const stageOf = (row: Applicant) => row.display_stage ?? row.current_stage
const reportRow = (row: Applicant) => [
  `#${row.id}`,
  row.name,
  row.service === 'academy' ? '—' : (row.responsible_employee?.name ?? t('educational.unassigned')),
  destinationLabel(row),
  stageOf(row) ? translateApplicantStage(stageOf(row)!) : '—',
  translateBackendValue(row.status),
  ...(canViewFinancials.value ? [paymentStatusLabel(row)] : []),
]
const setReportRows = (source: Applicant[]) => { reportRows.value = source.map(reportRow) }

const createDialog = ref(false)
const creating = ref(false)
const reviewDialog = ref(false)
const reviewApplicant = ref<Applicant | null>(null)
const consultantReview = ref<ConsultantReview | ''>('')
const reviewOptions = computed(() => [
  { title: t('common.statuses.good'), value: 'good' },
  { title: t('common.statuses.bad'), value: 'bad' },
  { title: t('common.statuses.great'), value: 'great' },
])
const createForm = ref({ name: '', father_name: '', father_phone: '', backup_phone: '', contact: '', email: '', admission_employee_id: null as number | null, responsible_employee_id: null as number | null, notes: '' })
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
  // Stage tabs follow the selected service. Without one, the API returns the
  // cross-service list deduped by key (the legacy single-pipeline shape).
  stages.value = await ApplicantApi.stages({ service: service.value || undefined })
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
      admission_employee_id: createForm.value.admission_employee_id,
    }

    Object.assign(payload, applicantServicePayload(serviceForm.value))
    // Academy enrolment has no admission officer, so no employee is attached.
    if (serviceForm.value.service !== 'academy')
      payload.admission_employee_id = createForm.value.admission_employee_id
    else
      delete payload.admission_employee_id
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
    toast.error(apiErrorMessage(error, t('educational.messages.createCaseFailed')))
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
    toast.error(apiErrorMessage(error))
  }
  finally {
    creating.value = false
  }
}

function clearFilters() {
  focus.value = ''
  service.value = ''
  search.value = ''
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
  load()
}

// Pagination controls call load() explicitly; no page watcher, so changing
// pages never fires duplicate requests.
watch(countryId, () => {
  const stillVisible = universities.value
    .filter(u => u.is_active && (!countryId.value || u.country_id === countryId.value))
    .some(u => u.id === universityId.value)

  if (!stillVisible)
    universityId.value = null
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

  if (previousStage && !stages.value.some(item => item.key === previousStage)) {
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
    <ErpPageHeader :title="$t('educational.applications')" section="applications">
      <template #actions>
        <div class="applicants-header-actions">
          <DataTableFilterMenu v-model="filtersOpen" :active="activeFilterCount > 0" @reset="clearFilters">
            <VSelect v-model="focus" :items="focusOptions" :label="$t('educational.needsAttention')" clearable />
            <VSelect v-model="service" :items="serviceOptions" :label="$t('educational.service')" clearable />
            <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" :show-apply="false" />
            <VSelect v-model="status" :items="[{ title: $t('educational.active'), value: 'active' }, { title: $t('educational.closed'), value: 'closed' }]" :label="$t('educational.status')" clearable />
            <VSelect v-if="canViewFinancials" v-model="paymentStatus" :items="[{ title: $t('finance.paymentStatus.paid'), value: 'paid' }, { title: $t('finance.paymentStatus.partially_paid'), value: 'partially_paid' }, { title: $t('finance.paymentStatus.unpaid'), value: 'unpaid' }]" :label="$t('pages.finance.income.income.paymentStatus')" clearable />
            <VSelect v-model="responsibleEmployeeId" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.employee')" clearable />
            <VSelect v-model="countryId" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" :label="$t('educational.country')" clearable />
            <VSelect v-model="universityId" :items="universities.filter(u => u.is_active && (!countryId || u.country_id === countryId)).map(u => ({ title: u.name, value: u.id }))" :label="$t('educational.university')" clearable />
            <VSelect v-model="educationLevelId" :items="levels.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.educationLevel')" clearable />
            <VSelect v-model="semesterId" :items="semesters.filter(s => s.is_active).map(s => ({ title: s.name, value: s.id }))" :label="$t('educational.semester')" clearable />
            <VBtn color="primary" :loading="loading" :disabled="invalidDateRange" @click="page = 1; load()">{{ $t('educational.apply') }}</VBtn>
          </DataTableFilterMenu>
          <VBtn icon="tabler-printer" variant="text" :loading="loading || printing" @click="printReport"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ $t('common.actions.print') }}</VTooltip></VBtn>
        </div>
      </template>
    </ErpPageHeader>
  <VCard class="education-panel">
    <VCardText>
      <VRow dense align="center">
        <VCol cols="12"><VTextField v-model="search" :label="$t('educational.searchApplicant')" prepend-inner-icon="tabler-search" clearable hide-details @keyup.enter="page = 1; load()" /></VCol>
      </VRow>
    </VCardText>
    <VChip v-if="focus" closable class="mx-4 mb-3" color="primary" @click:close="focus = ''; page = 1; load()">{{ focusOptions.find(option => option.value === focus)?.title }}</VChip>
    <VTabs v-model="stage" show-arrows class="px-3" @update:model-value="onTabChange">
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
    <div class="d-flex align-center gap-2 px-4 py-3"><VIcon icon="tabler-users" color="primary" /><strong>{{ $t('educational.resultCount', { count: total }) }}</strong><VSpacer /><VBtn v-if="activeFilterCount" size="small" variant="text" prepend-icon="tabler-filter" @click="filtersOpen = true">{{ $t('educational.activeFilters', { count: activeFilterCount }) }}</VBtn></div>
    <ResponsiveDataTableServer
      :headers="headers"
      :items="rows"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      item-value="id"
      class="education-applications-table"
      mobile-cards
      mobile-title-key="name"
      mobile-action-key="actions"
      :mobile-fields="['stage', 'employee', 'destination', 'status']"
      @update:options="updateTableOptions"
    >
      <template #loading><TableLoadingSkeleton :columns="headers.length" :label="$t('common.messages.loading')" /></template>
      <template #no-data><TableEmptyState icon="tabler-clipboard-list" :description="$t('educational.noApplicants')" /></template>
      <template #item.id="{ item }">#{{ item.id }}</template>
      <template #item.name="{ item }"><span class="font-weight-medium">{{ item.name }}</span></template>
      <template #item.employee="{ item }">{{ item.service === 'academy' ? '—' : (item.responsible_employee?.name ?? $t('educational.unassigned')) }}</template>
      <template #item.destination="{ item }"><VChip size="x-small" variant="tonal">{{ serviceLabel(item) }}</VChip><span v-if="['scholarship', 'private'].includes(item.service)" class="ms-2">{{ item.details?.country?.name ?? '—' }}{{ item.details?.university?.name ? ` · ${item.details.university.name}` : '' }}</span></template>
      <template #item.stage="{ item }">{{ stageOf(item) ? translateApplicantStage(stageOf(item)!) : '—' }}</template>
      <template #item.status="{ item }"><VChip size="small" :color="item.status === 'active' ? 'success' : 'secondary'">{{ translateBackendValue(item.status) }}</VChip></template>
      <template #item.paymentStatus="{ item }"><VChip v-if="item.payment_status" size="small" variant="tonal" :color="item.payment_status === 'paid' ? 'success' : item.payment_status === 'partially_paid' ? 'warning' : 'error'">{{ paymentStatusLabel(item) }}</VChip><span v-else class="text-medium-emphasis">{{ $t('educational.noServiceLines') }}</span></template>
      <template #item.actions="{ item }"><div class="d-flex justify-end align-center ga-1"><CrmRelationshipDetails type="applicant" :id="item.id" :title="item.name" /><VBtn v-if="canReview" icon="tabler-message" size="small" variant="text" @click="openConsultantReview(item)"><VTooltip activator="parent">{{ $t('pages.contracts.visitor.visitor.consultantReview') }}</VTooltip></VBtn><VBtn size="small" variant="text" @click="router.push({ name: 'applicant-case', params: { id: item.id } })">{{ $t('educational.openCase') }}</VBtn></div></template>
    </ResponsiveDataTableServer>
  </VCard>
  <PrintableListReport :title="t('educational.applications')" :subtitle="t('common.crmUi.printReports.applications.subtitle')" :columns="headers.slice(0, -1).map(column => column.title)" :rows="reportRows" />
  </div>
  <VDialog v-model="createDialog" max-width="1100" scrollable class="erp-form">
    <VCard :title="$t('educational.newApplicantCase')">
      <VCardText>
        <VRow dense>
          <VCol cols="12" sm="6" md="4"><ErpField v-model="createForm.name" :label="$t('common.fields.name')" required /></VCol>
          <VCol cols="12" sm="6" md="4"><ErpField v-model="createForm.contact" :label="$t('common.messages.contact')" /></VCol>
          <VCol cols="12" sm="6" md="4"><ErpField v-model="createForm.email" :label="$t('common.fields.email')" type="email" /></VCol>
          <VCol v-if="['scholarship', 'private'].includes(serviceForm.service || '')" cols="12" sm="6" md="4"><ErpField v-model="createForm.father_name" :label="$t('educational.fatherName')" /></VCol>
          <VCol v-if="['scholarship', 'private'].includes(serviceForm.service || '')" cols="12" sm="6" md="4"><ErpField v-model="createForm.father_phone" :label="$t('educational.fatherPhone')" /></VCol>
          <VCol v-if="serviceForm.service !== 'academy'" cols="12" sm="6" md="4"><ErpField kind="select" v-model="createForm.admission_employee_id" :items="admissionEmployees.map(e => ({ title: e.name, value: e.id }))" :label="$t('agencyStaff.admission')" clearable /></VCol>
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
  <VDialog v-model="reviewDialog" max-width="640">
    <VCard>
      <VCardTitle>{{ $t('pages.contracts.visitor.visitor.consultantReview') }}</VCardTitle>
      <VCardText><VSelect v-model="consultantReview" :items="reviewOptions" :label="$t('pages.contracts.visitor.visitor.consultantReview')" clearable /></VCardText>
      <VCardActions><VSpacer /><VBtn variant="outlined" @click="reviewDialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :loading="creating" @click="saveConsultantReview">{{ $t('common.actions.save') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
/* Separates the open pipeline stages from the terminal (closing) stages. */
.stage-tabs-divider {
  block-size: 22px;
  margin-inline: 12px;
}

:deep(.erp-header-actions) {
  flex: 0 0 auto;
  order: 2;
}

:deep(.erp-subnav) {
  order: 3;
}

.applicants-header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

@media (max-width: 599px) {
  :deep(.erp-header-actions) {
    flex-basis: 100%;
  }
}
</style>
