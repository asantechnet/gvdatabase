<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ReportApi, { type EducationalReport, type EducationalReportType } from '@/api/educationalReports'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import ApplicantApi, { type ApplicantStage, type BuiltInService } from '@/api/applicants'
import EducationalReportPrint from '@/components/educational/EducationalReportPrint.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import { canAccess } from '@/utils/authorization'
import { formatLocalizedDate } from '@/utils/localization'
import { printDocument } from '@/utils/printDocument'

const toast = useToast()
const { t, locale } = useI18n()

const tab = ref<EducationalReportType>('applicants')
const report = ref<EducationalReport | null>(null)
const loading = ref(false)
const exporting = ref(false)
const page = ref(1)
const filtersOpen = ref(false)

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

const canExport = computed(() => canAccess('export_reports'))
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  { title: t('educational.builtInServices.academy'), value: 'academy' },
  { title: t('educational.builtInServices.private'), value: 'private' },
])
const activeFilterCount = computed(() => [countryId.value, universityId.value, levelId.value, employeeId.value, service.value, stage.value, status.value, dateFrom.value, dateTo.value].filter(Boolean).length)
// Reports are grouped by the business domain they serve, so the workspace reads
// as Educational & CRM / Finance / HRM instead of one flat tab list.
type ReportDomain = 'educational' | 'finance' | 'hrm'

const tabs: Array<{ value: EducationalReportType; title: string; domain: ReportDomain }> = [
  { value: 'applicants', title: t('educational.applicants'), domain: 'educational' },
  { value: 'contracts', title: t('educational.contractsReport'), domain: 'educational' },
  { value: 'services', title: t('educational.servicesReport'), domain: 'educational' },
  { value: 'universities', title: t('educational.universityReport'), domain: 'educational' },
  { value: 'visa-outcomes', title: t('educational.visaOutcomes'), domain: 'educational' },
  { value: 'financial', title: t('educational.financialReport'), domain: 'finance' },
  { value: 'employee-performance', title: t('educational.employeePerformance'), domain: 'hrm' },
]
const domain = ref<ReportDomain>('educational')
const domainGroups: Array<{ value: ReportDomain; title: string; icon: string }> = [
  { value: 'educational', title: t('educational.reportDomains.educational'), icon: 'tabler-school' },
  { value: 'finance', title: t('educational.reportDomains.finance'), icon: 'tabler-cash-banknote' },
  { value: 'hrm', title: t('educational.reportDomains.hrm'), icon: 'tabler-users-group' },
]
const visibleTabs = computed(() => tabs.filter(item => item.domain === domain.value))
const activeTab = computed(() => tabs.find(item => item.value === tab.value))

const applicantStatusColor = (value: unknown) => ({
  active: 'success',
  closed: 'secondary',
}[String(value).toLowerCase()] ?? 'default')

const applicantStatusLabel = (value: unknown) => {
  const statusValue = String(value).toLowerCase()

  return statusValue === 'active' || statusValue === 'closed'
    ? t(`educational.${statusValue}`)
    : displayValue('status', value as string | number | null | undefined)
}

// Selecting a domain only reloads when the reported type actually changes, so
// switching back to a domain keeps the report already on screen.
watch(domain, () => {
  if (visibleTabs.value.some(item => item.value === tab.value))
    return

  const first = visibleTabs.value[0]
  if (!first)
    return

  tab.value = first.value
  page.value = 1
  void load()
})
const columnLabel = (column: string) => t(`educational.reportColumns.${column}`)
const isDateColumn = (column: string) => column === 'date' || column.endsWith('_date')

function displayValue(column: string, value: string | number | null | undefined): string | number {
  if (value === null || value === undefined || value === '')
    return '—'

  if (isDateColumn(column))
    return formatLocalizedDate(String(value), { year: 'numeric', month: 'short', day: 'numeric' })

  return value
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
    page: page.value,
  }
}

async function load() {
  loading.value = true
  try {
    report.value = await ReportApi.get(tab.value, params())
  }
  catch {
    toast.error(t('educational.loadReportFailed'))
  }
  finally {
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
    link.download = `visa-${tab.value}-report.csv`
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

function onTabChange() {
  page.value = 1
  load()
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
  load()
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
const printTotalLabel = computed(() => t('educational.resultCount', { count: report.value?.total ?? 0 }))
const printRows = computed<Array<Array<string | number>>>(() => {
  const current = report.value
  if (!current)
    return []

  return current.rows.map(row => current.columns.map(column => displayValue(column, row[column])))
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
    const [countryRows, universityRows, levelRows, employeeRows] = await Promise.all([
      MasterDataApi.list('countries'),
      MasterDataApi.list('universities'),
      MasterDataApi.list('education-levels'),
      EmployeeApi.getOptions(),
    ])

    countries.value = countryRows
    universities.value = universityRows
    levels.value = levelRows
    employees.value = employeeRows
    await loadStages()
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
    <!-- Same concept frame as the other Applications pages: breadcrumbs plus the
         shared in-page navigation, with this page's own actions in the slot. -->
    <ErpPageHeader :title="$t('educational.visaReports')" section="applications">
      <template #actions><div class="d-flex gap-2 education-header-actions"><DataTableFilterMenu v-model="filtersOpen" :active="activeFilterCount > 0" @reset="clearFilters"><VSelect v-model="countryId" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" :label="$t('educational.country')" clearable /><VSelect v-model="universityId" :items="universities.filter(u => u.is_active).map(u => ({ title: u.name, value: u.id }))" :label="$t('educational.university')" clearable /><VSelect v-model="levelId" :items="levels.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.educationLevel')" clearable /><VSelect v-model="employeeId" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.employee')" clearable /><VSelect v-if="tab === 'applicants'" v-model="service" :items="serviceOptions" :label="$t('educational.service')" clearable /><VSelect v-if="tab === 'applicants'" v-model="stage" :items="stages.map(item => ({ title: item.name, value: item.key }))" :label="$t('educational.stage')" clearable /><VSelect v-if="tab === 'applicants'" v-model="status" :items="[{ title: $t('educational.active'), value: 'active' }, { title: $t('educational.closed'), value: 'closed' }]" :label="$t('educational.status')" clearable /><VTextField v-model="dateFrom" type="date" :label="$t('educational.from')" clearable /><VTextField v-model="dateTo" type="date" :label="$t('educational.to')" clearable /><VBtn color="primary" :loading="loading" @click="page = 1; load()">{{ $t('educational.apply') }}</VBtn></DataTableFilterMenu><VBtn variant="outlined" :disabled="!report" prepend-icon="tabler-printer" @click="printPage">{{ $t('educational.printView') }}</VBtn><VBtn v-if="canExport" color="primary" :loading="exporting" prepend-icon="tabler-download" @click="download">{{ $t('educational.exportCsv') }}</VBtn></div></template>
    </ErpPageHeader>

    <VCard class="education-panel">
      <div class="d-flex align-center px-3 pt-3 report-domain-bar">
        <VBtnToggle v-model="domain" class="report-domain-toggle" density="comfortable" variant="tonal" color="primary" mandatory>
          <VBtn v-for="group in domainGroups" :key="group.value" :value="group.value" :prepend-icon="group.icon">{{ group.title }}</VBtn>
        </VBtnToggle>
      </div>
      <VTabs v-model="tab" show-arrows class="px-3 report-tabs" @update:model-value="onTabChange"><VTab v-for="item in visibleTabs" :key="item.value" :value="item.value">{{ item.title }}</VTab></VTabs>
      <div class="d-flex align-center flex-wrap gap-2 px-4 py-3 education-result-bar">
        <VAvatar color="primary" variant="tonal" size="36"><VIcon icon="tabler-chart-bar" size="20" /></VAvatar>
        <div><strong>{{ activeTab?.title }}</strong><div class="text-caption text-medium-emphasis">{{ $t('educational.resultCount', { count: report?.total ?? 0 }) }}<template v-if="printPeriod"> · {{ printPeriod }}</template></div></div>
        <VSpacer /><VBtn v-if="activeFilterCount" size="small" variant="text" prepend-icon="tabler-filter" @click="filtersOpen = true">{{ $t('educational.activeFilters', { count: activeFilterCount }) }}</VBtn><span v-if="!canExport" class="text-caption text-medium-emphasis">{{ $t('educational.exportRequired') }}</span>
      </div>
      <TableLoadingSkeleton v-if="loading" :columns="report?.columns.length ?? 6" :label="$t('common.messages.loading')" />
      <TableEmptyState v-else-if="report && !report.rows.length" icon="tabler-report-off" :description="$t('educational.noRows')" />
      <div v-else-if="report" class="education-table-scroll"><VTable density="compact" class="education-table report-table">
        <thead><tr><th v-for="column in report.columns" :key="column">{{ columnLabel(column) }}</th></tr></thead>
        <tbody><tr v-for="(row, index) in report.rows" :key="index"><td v-for="column in report.columns" :key="column"><VChip v-if="tab === 'applicants' && column === 'status'" size="small" :color="applicantStatusColor(row[column])">{{ applicantStatusLabel(row[column]) }}</VChip><template v-else>{{ displayValue(column, row[column]) }}</template></td></tr></tbody>
      </VTable></div>
      <VDivider v-if="report?.last_page && report.last_page > 1" />
      <VCardText v-if="report?.last_page && report.last_page > 1" class="d-flex justify-center py-2 education-pagination"><VPagination v-model="page" :length="report.last_page" size="small" :total-visible="7" @update:model-value="load" /></VCardText>
    </VCard>

    <EducationalReportPrint
      ref="printRef"
      :title="activeTab?.title ?? $t('educational.visaReports')"
      :subtitle="$t('educational.reportsSubtitle')"
      :columns="printColumns"
      :rows="printRows"
      :facts="printFacts"
      :period="printPeriod"
      :generated-at="printGeneratedAt"
      :total-label="printTotalLabel"
      :direction="printDirection"
    />
  </div>
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

.report-domain-bar {
  /* Keep the report domains on the physical right edge while respecting the
     page's active writing direction and RTL button content. */
  justify-content: flex-end;
  overflow-x: auto;
  scrollbar-width: none;
}

[dir='rtl'] .report-domain-bar { justify-content: flex-start; }

.report-domain-bar::-webkit-scrollbar { display: none; }

.report-domain-toggle {
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  gap: 6px;
  max-inline-size: 100%;
}

.report-domain-toggle .v-btn {
  flex: 0 1 auto;
  min-inline-size: max-content;
  border-radius: 6px !important;
}
</style>
