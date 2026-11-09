<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ReportApi, { type EducationalReport, type EducationalReportType } from '@/api/educationalReports'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import ApplicantApi, { type ApplicantStage, type BuiltInService } from '@/api/applicants'
import EducationalReportPrint from '@/components/educational/EducationalReportPrint.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import ResponsiveDataTable from '@/components/responsive-data-table/ResponsiveDataTable.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import { canAccess } from '@/utils/authorization'
import { formatLocalizedDate } from '@/utils/localization'
import { printDocument } from '@/utils/printDocument'
import { useAcademyEnabled } from '@/composables/useAcademyEnabled'

const toast = useToast()
const { enabled: academyEnabled } = useAcademyEnabled()
const { t, locale } = useI18n()
const route = useRoute()

type ReportWorkspace = 'applications' | 'finance' | 'hrm'
const workspace = computed<ReportWorkspace>(() => (route.meta.reportWorkspace as ReportWorkspace | undefined) ?? 'applications')
const workspaceDefault: Record<ReportWorkspace, EducationalReportType> = { applications: 'applicants', finance: 'financial', hrm: 'employee-performance' }
const tab = ref<EducationalReportType>(workspaceDefault[workspace.value])
const report = ref<EducationalReport | null>(null)
// Start in the loading state so the table skeleton is the first rendered state;
// an empty-state decision is only made after the report request completes.
const loading = ref(true)
const loadToken = ref(0)
const exporting = ref(false)
const reportLoadingHeaders = computed(() => (report.value?.columns?.length
  ? report.value.columns.map(column => ({ title: columnLabel(column), key: column }))
  : Array.from({ length: 6 }, (_, index) => ({ title: '', key: `loading-${index}` }))))
const page = ref(1)
const filterDialog = ref(false)
const employeePerformanceFilterMenu = ref(false)
const search = ref('')

const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const levels = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])
const stages = ref<ApplicantStage[]>([])

const countryId = ref<number | ''>('')
const universityId = ref<number | ''>('')
const levelId = ref<number | ''>('')
const employeeId = ref<number | ''>('')
const service = ref<BuiltInService | ''>('')
const stage = ref('')
const status = ref<'active' | 'closed' | ''>('')
const dateFrom = ref('')
const dateTo = ref('')
const filterDraft = reactive({
  countryId: countryId.value,
  universityId: universityId.value,
  levelId: levelId.value,
  employeeId: employeeId.value,
  service: service.value,
  stage: stage.value,
  status: status.value,
  dateFrom: dateFrom.value,
  dateTo: dateTo.value,
})

const canExport = computed(() => canAccess('export_reports'))
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  ...(academyEnabled.value ? [{ title: t('educational.builtInServices.academy'), value: 'academy' }] : []),
  { title: t('educational.builtInServices.private'), value: 'private' },
])
const activeFilterCount = computed(() => [countryId.value, universityId.value, levelId.value, employeeId.value, service.value, stage.value, status.value, dateFrom.value, dateTo.value].filter(Boolean).length)
const tabs: Array<{ value: EducationalReportType; title: string; workspace: ReportWorkspace }> = [
  { value: 'applicants', title: t('educational.applicants'), workspace: 'applications' },
  { value: 'contracts', title: t('educational.contractsReport'), workspace: 'applications' },
  { value: 'services', title: t('educational.servicesReport'), workspace: 'applications' },
  { value: 'universities', title: t('educational.universityReport'), workspace: 'applications' },
  { value: 'visa-outcomes', title: t('educational.visaOutcomes'), workspace: 'applications' },
  { value: 'financial', title: t('educational.financialReport'), workspace: 'finance' },
  { value: 'employee-performance', title: t('educational.employeePerformance'), workspace: 'hrm' },
]
const visibleTabs = computed(() => tabs.filter(item => item.workspace === workspace.value))
const activeTab = computed(() => tabs.find(item => item.value === tab.value))
const pageTitle = computed(() => t(`educational.reportWorkspaces.${workspace.value}.title`))
const pageDescription = computed(() => workspace.value === 'applications'
  ? ''
  : workspace.value === 'hrm'
    ? ''
  : t(`educational.reportWorkspaces.${workspace.value}.description`))
const pageSection = computed(() => workspace.value === 'applications' ? 'applications' : workspace.value === 'finance' ? 'finance' : 'hr')
const filteredRows = computed(() => {
  const query = search.value.trim().toLocaleLowerCase()
  if (!query)
    return report.value?.rows ?? []

  return (report.value?.rows ?? []).filter(row => Object.values(row).some(value => String(value ?? '').toLocaleLowerCase().includes(query)))
})
const emptyTitle = computed(() => search.value || activeFilterCount.value ? t('educational.reportEmpty.filteredTitle') : t('educational.reportEmpty.title'))
const emptyDescription = computed(() => search.value || activeFilterCount.value ? t('educational.reportEmpty.filteredDescription') : t('educational.reportEmpty.description'))

const applicantStatusColor = (value: unknown) => ({
  active: 'success',
  closed: 'secondary',
  recorded: 'success',
  approved: 'success',
  paid: 'success',
  completed: 'success',
  pending: 'warning',
  open: 'warning',
  partially_paid: 'warning',
  rejected: 'error',
  cancelled: 'error',
  withdrawn: 'error',
  unpaid: 'error',
}[String(value).toLowerCase()] ?? 'default')

const statusLabel = (value: unknown) => {
  const statusValue = String(value).toLowerCase()

  return statusValue === 'active' || statusValue === 'closed'
    ? t(`educational.${statusValue}`)
    : formatToken(value)
}

watch(workspace, async () => {
  tab.value = workspaceDefault[workspace.value]
  search.value = ''
  countryId.value = ''
  universityId.value = ''
  levelId.value = ''
  employeeId.value = ''
  service.value = ''
  stage.value = ''
  status.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  page.value = 1
  await loadCatalogs()
  await load()
})
const columnLabel = (column: string) => t(`educational.reportColumns.${column}`)
const isDateColumn = (column: string) => column === 'date' || column.endsWith('_date')

function displayValue(column: string, value: string | number | null | undefined): string | number {
  if (value === null || value === undefined || value === '')
    return '—'

  if (isDateColumn(column))
    return formatLocalizedDate(String(value), { year: 'numeric', month: 'short', day: 'numeric' })

  if (['service', 'status', 'record_type', 'contract_type', 'category'].includes(column))
    return formatToken(value)

  return value
}

function formatToken(value: unknown): string {
  return String(value ?? '')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, character => character.toUpperCase())
}

function params() {
  return {
    country_id: countryId.value || undefined,
    university_id: universityId.value || undefined,
    education_level_id: levelId.value || undefined,
    employee_id: employeeId.value || undefined,
    service: service.value || undefined,
    stage: stage.value || undefined,
    status: status.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    per_page: 500,
    page: page.value,
  }
}

async function load() {
  const token = ++loadToken.value
  loading.value = true
  report.value = null
  // Let Vue commit the state, then keep the skeleton visible long enough for a
  // browser paint even when the report response is already cached.
  await nextTick()
  try {
    const reportRequest = ReportApi.get(tab.value, params())
    await new Promise<void>(resolve => window.setTimeout(resolve, 180))
    const response = await reportRequest
    if (token === loadToken.value)
      report.value = response
  }
  catch {
    if (token === loadToken.value)
      toast.error(t('educational.loadReportFailed'))
  }
  finally {
    if (token === loadToken.value)
      loading.value = false
  }
}

async function download() {
  exporting.value = true
  try {
    const blob = await ReportApi.exportCsv(tab.value, params())
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')

    link.href = url
    link.download = `${workspace.value}-${tab.value}-report.csv`
    link.click()
    URL.revokeObjectURL(url)
    toast.success(t('educational.exported'))
  }
  catch {
    toast.error(t('educational.exportFailed'))
  }
  finally {
    exporting.value = false
  }
}

function onTabChange(value: unknown) {
  if (!tabs.some(item => item.value === value))
    return

  tab.value = value as EducationalReportType
  page.value = 1
  search.value = ''
  void load()
}

function clearFilters() {
  countryId.value = ''
  universityId.value = ''
  levelId.value = ''
  employeeId.value = ''
  service.value = ''
  stage.value = ''
  status.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  page.value = 1
  employeePerformanceFilterMenu.value = false
  void load()
}

function syncFilterDraft() {
  filterDraft.countryId = countryId.value
  filterDraft.universityId = universityId.value
  filterDraft.levelId = levelId.value
  filterDraft.employeeId = employeeId.value
  filterDraft.service = service.value
  filterDraft.stage = stage.value
  filterDraft.status = status.value
  filterDraft.dateFrom = dateFrom.value
  filterDraft.dateTo = dateTo.value
}

function openFilters() {
  syncFilterDraft()
  filterDialog.value = true
}

function applyFilterDialog() {
  countryId.value = filterDraft.countryId
  universityId.value = filterDraft.universityId
  levelId.value = filterDraft.levelId
  employeeId.value = filterDraft.employeeId
  service.value = filterDraft.service
  stage.value = filterDraft.stage
  status.value = filterDraft.status
  dateFrom.value = filterDraft.dateFrom
  dateTo.value = filterDraft.dateTo
  page.value = 1
  filterDialog.value = false
  employeePerformanceFilterMenu.value = false
  void load()
}

function dockFilterCalendar(_selectedDates: Date[], _dateString: string, instance: { calendarContainer: HTMLElement }) {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.setProperty('transform', 'translateY(0) scale(0.86)', 'important')
    instance.calendarContainer.style.setProperty('transform-origin', 'top left', 'important')
    instance.calendarContainer.style.setProperty('z-index', '2400', 'important')
  })
}

// The print job renders a dedicated, theme-independent document below instead
// of the live Vuetify table, whose colours followed the active theme (a dark
// theme printed dark). Only the rows currently loaded for this tab are printed.
const pagePrintClass = 'printing-education-reports'
const printRef = ref<InstanceType<typeof EducationalReportPrint> | null>(null)
const printGeneratedAt = ref('')

const refreshPrintStamp = () => { printGeneratedAt.value = formatLocalizedDate(new Date(), 'long') }
const printDirection = computed(() => (['fa-AF', 'ps-AF'].includes(String(locale.value)) ? 'rtl' : 'ltr'))
const printColumns = computed(() => (report.value?.columns ?? []).map(columnLabel))
const printRows = computed<Array<Array<string | number>>>(() => {
  const current = report.value
  if (!current)
    return []

  return filteredRows.value.map(row => current.columns.map(column => displayValue(column, row[column])))
})
const printPeriod = computed(() => {
  if (!dateFrom.value && !dateTo.value)
    return ''

  const from = dateFrom.value ? formatLocalizedDate(dateFrom.value) : '—'
  const to = dateTo.value ? formatLocalizedDate(dateTo.value) : '—'

  return `${from} — ${to}`
})
const printFacts = computed(() => {
  const facts: Array<{ label: string, value: string }> = []
  const country = countries.value.find(item => item.id === countryId.value)
  const university = universities.value.find(item => item.id === universityId.value)
  const level = levels.value.find(item => item.id === levelId.value)
  const employee = employees.value.find(item => item.id === employeeId.value)
  const serviceRow = serviceOptions.value.find(item => item.value === service.value)
  const stageRow = stages.value.find(item => item.key === stage.value)

  if (serviceRow) facts.push({ label: t('educational.service'), value: serviceRow.title })
  if (country) facts.push({ label: t('educational.country'), value: country.name })
  if (university) facts.push({ label: t('educational.university'), value: university.name })
  if (level) facts.push({ label: t('educational.educationLevel'), value: level.name })
  if (employee) facts.push({ label: t('educational.employee'), value: employee.name })
  if (stageRow) facts.push({ label: t('educational.stage'), value: stageRow.name })
  if (status.value) facts.push({ label: t('educational.status'), value: status.value === 'active' ? t('educational.active') : t('educational.closed') })

  return facts
})

function printPage() {
  refreshPrintStamp()
  void printDocument({
    bodyClass: pagePrintClass,
    pageStyle: 'size: A4 landscape; margin: 12mm;',
    hostPadding: '12mm',
    element: (printRef.value?.$el as HTMLElement | undefined) ?? null,
  })
}

// Native browser printing (Ctrl+P / menu) bypasses printDocument, so mirror the
// same print-mode class and let the namespaced rules below swap the screen view
// for the print document.
function enablePagePrintMode() {
  refreshPrintStamp()
  document.body.classList.add(pagePrintClass)
}

function disablePagePrintMode() {
  document.body.classList.remove(pagePrintClass)
}

async function loadCatalogs() {
  try {
    employees.value = await EmployeeApi.getOptions()
    if (workspace.value === 'applications') {
      const [countryRows, universityRows, levelRows] = await Promise.all([
        MasterDataApi.list('countries'),
        MasterDataApi.list('universities'),
        MasterDataApi.list('education-levels'),
      ])

      countries.value = countryRows
      universities.value = universityRows
      levels.value = levelRows
      await loadStages()
    }
  }
  catch {
    toast.error(t('educational.loadFiltersFailed'))
  }
}

// Stage options follow the selected service so a report cannot be filtered by
// another service's stage key.
async function loadStages() {
  stages.value = await ApplicantApi.stages({ service: service.value || undefined })
}

watch(service, async () => {
  const previousStage = stage.value

  try {
    await loadStages()
  }
  catch {
    toast.error(t('educational.loadFiltersFailed'))

    return
  }

  if (previousStage && !stages.value.some(item => item.key === previousStage))
    stage.value = ''
})

onMounted(async () => {
  window.addEventListener('beforeprint', enablePagePrintMode)
  window.addEventListener('afterprint', disablePagePrintMode)
  await loadCatalogs()
  await load()
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeprint', enablePagePrintMode)
  window.removeEventListener('afterprint', disablePagePrintMode)
  disablePagePrintMode()
})
</script>

<template>
  <div class="education-workspace erp-workspace">
    <ErpPageHeader :title="pageTitle" :description="pageDescription" :section="pageSection" />

    <VCard class="education-panel">
      <VTabs v-if="visibleTabs.length > 1" v-model="tab" show-arrows class="px-3 report-tabs" @update:model-value="onTabChange"><VTab v-for="item in visibleTabs" :key="item.value" :value="item.value">{{ item.title }}</VTab></VTabs>
      <VDivider v-if="visibleTabs.length > 1" />
      <VCardText>
        <VRow align="center" class="table-toolbar">
          <VCol cols="12" md="6" lg="5"><ErpSearchField v-model="search" :placeholder="$t('educational.searchReports')" /></VCol>
          <VCol cols="12" md="6" lg="7" class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2">
            <DataTableFilterMenu v-if="workspace === 'hrm'" v-model="employeePerformanceFilterMenu" :active="activeFilterCount > 0" :show-reset="false" @update:model-value="open => { if (open) syncFilterDraft() }">
              <ErpField v-model="filterDraft.employeeId" kind="autocomplete" :items="employees.map(employee => ({ title: employee.name, value: employee.id }))" :label="$t('educational.employee')" clearable />
              <ErpField v-model="filterDraft.dateFrom" kind="date" :label="$t('common.fields.startDate')" :config="{ dateFormat: 'Y-m-d', maxDate: filterDraft.dateTo || undefined, static: false, onOpen: dockFilterCalendar }" />
              <ErpField v-model="filterDraft.dateTo" kind="date" :label="$t('common.fields.endDate')" :config="{ dateFormat: 'Y-m-d', minDate: filterDraft.dateFrom || undefined, static: false, onOpen: dockFilterCalendar }" />
              <template #actions><VBtn class="education-filter-reset" variant="flat" @click="clearFilters">{{ $t('common.messages.reset') }}</VBtn><VBtn color="primary" variant="flat" @click="applyFilterDialog">{{ $t('educational.apply') }}</VBtn></template>
            </DataTableFilterMenu>
            <VBtn v-else icon="tabler-filter" :variant="activeFilterCount ? 'flat' : 'tonal'" :color="activeFilterCount ? 'primary' : undefined" size="small" :aria-label="$t('common.messages.filters')" @click="openFilters"><VIcon icon="tabler-filter" /><VTooltip activator="parent" location="top">{{ $t('common.messages.filters') }}</VTooltip></VBtn>
            <VBtn icon="tabler-printer" variant="tonal" class="flex-shrink-0" :disabled="!report" :aria-label="$t('common.actions.print')" @click="printPage"><VIcon icon="tabler-printer" /><VTooltip activator="parent" location="top">{{ $t('common.actions.print') }}</VTooltip></VBtn>
            <VBtn v-if="canExport" icon="tabler-download" variant="tonal" color="primary" class="flex-shrink-0" :loading="exporting" :aria-label="$t('educational.exportCsv')" @click="download"><VIcon icon="tabler-download" /><VTooltip activator="parent" location="top">{{ $t('educational.exportCsv') }}</VTooltip></VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <ResponsiveDataTable v-if="loading" :headers="reportLoadingHeaders" :items="[]" :loading="true" :skeleton-rows="6" :sticky-key-column="false" :sticky-action-column="false" mobile-cards mobile-title-key="loading-0" :mobile-fields="['loading-1', 'loading-2']" class="education-report-loading-table" />
      <TableEmptyState v-else-if="report && !filteredRows.length" icon="tabler-report-off" :title="emptyTitle" :description="emptyDescription" />
      <div v-else-if="report" class="education-table-scroll"><VTable density="compact" class="education-table report-table">
        <thead><tr><th v-for="column in report.columns" :key="column">{{ columnLabel(column) }}</th></tr></thead>
        <tbody><tr v-for="(row, index) in filteredRows" :key="index"><td v-for="column in report.columns" :key="column"><VChip v-if="column === 'status'" size="small" variant="tonal" :color="applicantStatusColor(row[column])">{{ statusLabel(row[column]) }}</VChip><template v-else>{{ displayValue(column, row[column]) }}</template></td></tr></tbody>
      </VTable>
        <div class="education-report-mobile-cards">
          <article v-for="(row, index) in filteredRows" :key="index" class="education-report-mobile-card">
            <div v-for="column in report.columns" :key="column" class="education-report-mobile-card__field">
              <span>{{ columnLabel(column) }}</span>
              <strong v-if="column !== 'status'">{{ displayValue(column, row[column]) }}</strong>
              <VChip v-else size="small" variant="tonal" :color="applicantStatusColor(row[column])">{{ statusLabel(row[column]) }}</VChip>
            </div>
          </article>
        </div>
      </div>
      <VDivider v-if="report?.last_page && report.last_page > 1" />
      <VCardText v-if="report?.last_page && report.last_page > 1" class="d-flex justify-center py-2 education-pagination"><VPagination v-model="page" :length="report.last_page" size="small" :total-visible="7" @update:model-value="load" /></VCardText>
    </VCard>

    <EducationalReportPrint
      ref="printRef"
      :title="activeTab?.title ?? $t('educational.visaReports')"
      :subtitle="pageDescription"
      :columns="printColumns"
      :rows="printRows"
      :facts="printFacts"
      :period="printPeriod"
      :generated-at="printGeneratedAt"
      :total="report?.total ?? 0"
      :direction="printDirection"
    />
  </div>
  <VDialog v-model="filterDialog" max-width="860" content-class="applications-filter-dialog" scroll-strategy="block">
    <VCard class="applications-filter-card">
      <VCardTitle class="applications-filter-dialog__title px-5 py-4">
        <span class="applications-filter-dialog__heading">{{ $t('common.messages.filters') }}</span><VSpacer />
        <VBtn icon="tabler-x" variant="tonal" color="error" class="applications-filter-dialog__close" :aria-label="$t('common.actions.close')" @click="filterDialog = false" />
      </VCardTitle>
      <VCardText class="applications-filter-dialog__body pa-5">
        <VRow>
          <VCol v-if="workspace === 'applications'" cols="12" sm="6" md="4"><ErpField v-model="filterDraft.service" kind="select" :items="serviceOptions" :label="$t('educational.service')" :placeholder="$t('educational.service')" clearable /></VCol>
          <VCol v-if="tab === 'applicants'" cols="12" sm="6" md="4"><ErpField v-model="filterDraft.stage" kind="select" :items="stages.map(item => ({ title: item.name, value: item.key }))" :label="$t('educational.stage')" :placeholder="$t('educational.stage')" clearable /></VCol>
          <VCol v-if="tab === 'applicants'" cols="12" sm="6" md="4"><ErpField v-model="filterDraft.status" kind="select" :items="[{ title: $t('educational.active'), value: 'active' }, { title: $t('educational.closed'), value: 'closed' }]" :label="$t('educational.status')" :placeholder="$t('educational.status')" clearable /></VCol>
          <VCol cols="12" sm="6" md="4"><ErpField v-model="filterDraft.employeeId" kind="autocomplete" :items="employees.map(employee => ({ title: employee.name, value: employee.id }))" :label="$t('educational.employee')" :placeholder="$t('educational.employee')" clearable /></VCol>
          <template v-if="workspace === 'applications'">
            <VCol cols="12" sm="6" md="4"><ErpField v-model="filterDraft.countryId" kind="select" :items="countries.filter(country => country.is_active).map(country => ({ title: country.name, value: country.id }))" :label="$t('educational.country')" :placeholder="$t('educational.country')" clearable /></VCol>
            <VCol cols="12" sm="6" md="4"><ErpField v-model="filterDraft.universityId" kind="select" :items="universities.filter(university => university.is_active && (!filterDraft.countryId || university.country_id === filterDraft.countryId)).map(university => ({ title: university.name, value: university.id }))" :label="$t('educational.university')" :placeholder="$t('educational.university')" clearable /></VCol>
            <VCol cols="12" sm="6" md="4"><ErpField v-model="filterDraft.levelId" kind="select" :items="levels.filter(level => level.is_active).map(level => ({ title: level.name, value: level.id }))" :label="$t('educational.educationLevel')" :placeholder="$t('educational.educationLevel')" clearable /></VCol>
          </template>
          <VCol cols="12" sm="6" md="4"><div class="erp-field"><label class="erp-field-label">{{ $t('common.fields.startDate') }}</label><AppDateTimePicker v-model="filterDraft.dateFrom" class="erp-field-control" :placeholder="$t('common.fields.startDate')" persistent-placeholder :config="{ dateFormat: 'Y-m-d', maxDate: filterDraft.dateTo || undefined, static: false, onOpen: dockFilterCalendar }" /></div></VCol>
          <VCol cols="12" sm="6" md="4"><div class="erp-field"><label class="erp-field-label">{{ $t('common.fields.endDate') }}</label><AppDateTimePicker v-model="filterDraft.dateTo" class="erp-field-control" :placeholder="$t('common.fields.endDate')" persistent-placeholder :config="{ dateFormat: 'Y-m-d', minDate: filterDraft.dateFrom || undefined, static: false, onOpen: dockFilterCalendar }" /></div></VCol>
        </VRow>
      </VCardText>
      <VCardActions class="applications-filter-dialog__actions px-5 py-4">
        <VBtn class="applications-filter-dialog__reset" variant="flat" @click="clearFilters(); filterDialog = false">{{ $t('common.messages.reset') }}</VBtn>
        <VBtn class="applications-filter-dialog__apply" color="primary" variant="flat" :loading="loading" :disabled="!!filterDraft.dateFrom && !!filterDraft.dateTo && filterDraft.dateFrom > filterDraft.dateTo" @click="applyFilterDialog">{{ $t('educational.apply') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style>
/* Native browser printing (Ctrl+P) bypasses printDocument, so these rules swap
   the screen view for the dedicated print document. The print button instead
   uses `printing-isolated-document`, which hides the whole app shell by itself.
   Every selector is namespaced with this page's print-mode body class. */
@media print {
  body.printing-education-reports .layout-navbar,
  body.printing-education-reports .layout-menu,
  body.printing-education-reports .menu-vertical,
  body.printing-education-reports .layout-horizontal-nav,
  body.printing-education-reports .layout-vertical-nav,
  body.printing-education-reports .vertical-nav-wrapper,
  body.printing-education-reports .layout-overlay,
  body.printing-education-reports .v-app-bar,
  body.printing-education-reports .v-navigation-drawer,
  body.printing-education-reports .v-bottom-navigation,
  body.printing-education-reports .v-footer,
  body.printing-education-reports .layout-footer,
  body.printing-education-reports #app > header,
  body.printing-education-reports #app > nav,
  body.printing-education-reports .erp-page-header,
  body.printing-education-reports .education-panel { display: none !important; }

  body.printing-education-reports,
  body.printing-education-reports .v-application,
  body.printing-education-reports .layout-wrapper { background: #fff !important; }

  body.printing-education-reports .v-application,
  body.printing-education-reports .v-layout,
  body.printing-education-reports .v-main {
    --v-layout-left: 0px !important;
    --v-layout-right: 0px !important;
    --v-layout-top: 0px !important;
    --v-layout-bottom: 0px !important;
  }

  body.printing-education-reports .v-main,
  body.printing-education-reports .layout-page,
  body.printing-education-reports .layout-page-content,
  body.printing-education-reports .layout-content-wrapper,
  body.printing-education-reports .v-application__wrap,
  body.printing-education-reports .education-workspace {
    padding: 0 !important;
    margin: 0 !important;
    min-height: 0 !important;
    min-block-size: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
  }

  body.printing-education-reports .education-workspace { display: block !important; overflow: visible !important; }
}

.education-table-scroll {
  overflow-x: auto;
  overscroll-behavior-inline: contain;
  scrollbar-width: none;
}

.education-table-scroll::-webkit-scrollbar { display: none; }

/* Vuetify places the actual scrolling element one level inside VTable; the
   skeleton has its own scroll host. Keep both scrollable, but never visible. */
.education-table-scroll .v-table__wrapper,
.education-report-loading-table :deep(.v-table__wrapper) {
  scrollbar-width: none;
}

.education-table-scroll .v-table__wrapper::-webkit-scrollbar,
.education-report-loading-table :deep(.v-table__wrapper::-webkit-scrollbar) { display: none; }

.report-table { min-inline-size: 70rem; }
.report-table :is(th, td) { min-inline-size: 9rem; white-space: nowrap; }
.report-table th:first-child, .report-table td:first-child { min-inline-size: 5rem; }
.education-report-mobile-cards { display: none; }

/* The report filter intentionally reuses the Applications dialog language so
   users meet the same layout, actions, and visual feedback everywhere. */
.applications-filter-card { overflow: hidden; }
.applications-filter-dialog__title,
.applications-filter-dialog__actions {
  align-items: center;
  background-color: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%);
  border-color: rgba(var(--v-theme-on-surface), 0.035);
  border-style: solid;
  border-width: 0;
  display: flex;
}
.applications-filter-dialog__title { border-block-end-width: 1px; }
.applications-filter-dialog__actions { border-block-start-width: 1px; gap: .5rem; justify-content: flex-end; }
.applications-filter-dialog__heading { font-size: 1.625rem; font-weight: 700; }
.applications-filter-dialog__body { background-color: rgb(var(--v-theme-surface)); }
.applications-filter-dialog__close { border: 1px solid rgba(var(--v-theme-error), .38); border-radius: 50%; block-size: 36px; color: rgb(var(--v-theme-error)); inline-size: 36px; }
.applications-filter-dialog__close:hover { background-color: rgba(var(--v-theme-error), .18) !important; }
.applications-filter-dialog__reset { background-color: rgba(var(--v-theme-error), .12) !important; border: 1px solid rgba(var(--v-theme-error), .62) !important; color: rgb(var(--v-theme-error)) !important; }
.applications-filter-dialog__reset:hover,
.applications-filter-dialog__reset:focus-visible { background-color: rgb(var(--v-theme-error)) !important; border-color: rgb(var(--v-theme-error)); color: rgb(var(--v-theme-on-error)) !important; }
.applications-filter-dialog__apply { background-color: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.applications-filter-dialog__apply:hover { filter: brightness(.9); }
.education-filter-reset { border: 1px solid rgba(var(--v-theme-error), .62) !important; background: rgba(var(--v-theme-error), .12) !important; color: rgb(var(--v-theme-error)) !important; }
.education-filter-reset:hover { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.v-overlay:has(.applications-filter-dialog) .v-overlay__scrim { backdrop-filter: blur(4px); background-color: rgba(var(--v-theme-on-surface), .24); }

@media (max-width: 959px) {
  .education-table-scroll { overflow: visible; }
  .report-table { display: none; }
  .education-report-mobile-cards { display: grid; gap: .75rem; }
  .education-report-mobile-card { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .58); border-radius: .75rem; background: rgb(var(--v-theme-surface)); box-shadow: 0 1px 2px rgb(var(--v-theme-on-surface) / .04); }
  .education-report-mobile-card__field { display: grid; grid-template-columns: minmax(7.75rem, .9fr) minmax(0, 1.1fr); align-items: center; gap: .75rem; min-block-size: 2.75rem; padding: .625rem .875rem; border-block-end: 1px solid rgba(var(--v-border-color), .38); }
  .education-report-mobile-card__field:last-child { border-block-end: 0; }
  .education-report-mobile-card__field > span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; font-weight: 700; }
  .education-report-mobile-card__field > strong { min-inline-size: 0; color: rgb(var(--v-theme-on-surface)); font-size: .8125rem; font-weight: 600; overflow-wrap: anywhere; text-align: end; }
  html[dir="rtl"] .education-report-mobile-card__field > strong { text-align: start; }
}
</style>
