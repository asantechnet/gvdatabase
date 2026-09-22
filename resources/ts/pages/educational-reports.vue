<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ReportApi, { type EducationalReport, type EducationalReportType } from '@/api/educationalReports'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import ApplicantApi, { type ApplicantStage } from '@/api/applicants'
import { canAccess } from '@/utils/authorization'
import { formatLocalizedDate } from '@/utils/localization'

const toast = useToast()
const { t } = useI18n()

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
const stage = ref('')
const status = ref<'active' | 'closed' | ''>('')
const dateFrom = ref('')
const dateTo = ref('')

const canExport = computed(() => canAccess('export_reports'))
const activeFilterCount = computed(() => [countryId.value, universityId.value, levelId.value, employeeId.value, stage.value, status.value, dateFrom.value, dateTo.value].filter(Boolean).length)
const tabs: Array<{ value: EducationalReportType; title: string }> = [
  { value: 'applicants', title: t('educational.applicants') },
  { value: 'employee-performance', title: t('educational.employeePerformance') },
  { value: 'contracts', title: t('educational.contractsReport') },
  { value: 'services', title: t('educational.servicesReport') },
  { value: 'financial', title: t('educational.financialReport') },
  { value: 'universities', title: t('educational.universityReport') },
  { value: 'visa-outcomes', title: t('educational.visaOutcomes') },
]
const activeTab = computed(() => tabs.find(item => item.value === tab.value))
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
  stage.value = ''
  status.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  page.value = 1
  load()
}

async function loadCatalogs() {
  try {
    const [countryRows, universityRows, levelRows, employeeRows, stageRows] = await Promise.all([
      MasterDataApi.list('countries'),
      MasterDataApi.list('universities'),
      MasterDataApi.list('education-levels'),
      EmployeeApi.getOptions(),
      ApplicantApi.stages(),
    ])

    countries.value = countryRows
    universities.value = universityRows
    levels.value = levelRows
    employees.value = employeeRows
    stages.value = stageRows
  }
  catch {
    toast.error(t('educational.loadFiltersFailed'))
  }
}

onMounted(async () => {
  await loadCatalogs()
  await load()
})
</script>

<template>
  <div class="education-workspace">
    <header class="education-page-header">
      <div><div class="education-eyebrow">{{ $t('educational.decisionReports') }}</div><h1>{{ $t('educational.visaReports') }}</h1><p>{{ $t('educational.reportsSubtitle') }}</p></div>
      <div class="d-flex gap-2"><VBtn variant="tonal" prepend-icon="tabler-adjustments-horizontal" @click="filtersOpen = !filtersOpen">{{ $t('educational.filters') }}<VBadge v-if="activeFilterCount" :content="activeFilterCount" color="primary" inline /></VBtn><VBtn v-if="canExport" color="primary" :loading="exporting" prepend-icon="tabler-download" @click="download">{{ $t('educational.exportCsv') }}</VBtn></div>
    </header>

    <VCard class="education-panel">
      <VTabs v-model="tab" show-arrows class="px-3 report-tabs" @update:model-value="onTabChange"><VTab v-for="item in tabs" :key="item.value" :value="item.value">{{ item.title }}</VTab></VTabs>
      <VDivider />
      <VExpandTransition><VCardText v-if="filtersOpen" class="education-filter-card rounded-0 border-0">
        <VRow dense>
          <VCol cols="12" sm="6" lg="3"><VSelect v-model="countryId" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" :label="$t('educational.country')" clearable hide-details /></VCol>
          <VCol cols="12" sm="6" lg="3"><VSelect v-model="universityId" :items="universities.filter(u => u.is_active).map(u => ({ title: u.name, value: u.id }))" :label="$t('educational.university')" clearable hide-details /></VCol>
          <VCol cols="12" sm="6" lg="3"><VSelect v-model="levelId" :items="levels.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.educationLevel')" clearable hide-details /></VCol>
          <VCol cols="12" sm="6" lg="3"><VSelect v-model="employeeId" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.employee')" clearable hide-details /></VCol>
          <VCol v-if="tab === 'applicants'" cols="12" sm="6" lg="3"><VSelect v-model="stage" :items="stages.map(item => ({ title: item.name, value: item.key }))" :label="$t('educational.stage')" clearable hide-details /></VCol>
          <VCol v-if="tab === 'applicants'" cols="12" sm="6" lg="3"><VSelect v-model="status" :items="[{ title: $t('educational.active'), value: 'active' }, { title: $t('educational.closed'), value: 'closed' }]" :label="$t('educational.status')" clearable hide-details /></VCol>
          <VCol cols="6" sm="3" lg="2"><VTextField v-model="dateFrom" type="date" :label="$t('educational.from')" clearable hide-details /></VCol>
          <VCol cols="6" sm="3" lg="2"><VTextField v-model="dateTo" type="date" :label="$t('educational.to')" clearable hide-details /></VCol>
        </VRow>
        <div class="d-flex justify-end gap-2 mt-3"><VBtn variant="text" @click="clearFilters">{{ $t('educational.clear') }}</VBtn><VBtn color="primary" :loading="loading" @click="page = 1; load()">{{ $t('educational.apply') }}</VBtn></div>
      </VCardText></VExpandTransition>

      <div class="d-flex align-center flex-wrap gap-2 px-4 py-3">
        <VAvatar color="primary" variant="tonal" size="36"><VIcon icon="tabler-chart-bar" size="20" /></VAvatar>
        <div><strong>{{ activeTab?.title }}</strong><div class="text-caption text-medium-emphasis">{{ $t('educational.resultCount', { count: report?.total ?? 0 }) }}</div></div>
        <VSpacer /><VBtn v-if="activeFilterCount" size="small" variant="text" prepend-icon="tabler-filter" @click="filtersOpen = true">{{ $t('educational.activeFilters', { count: activeFilterCount }) }}</VBtn><span v-if="!canExport" class="text-caption text-medium-emphasis">{{ $t('educational.exportRequired') }}</span>
      </div>
      <VProgressLinear v-if="loading" indeterminate />
      <div v-if="report" class="education-table-scroll"><VTable density="compact" class="education-table report-table">
        <thead><tr><th v-for="column in report.columns" :key="column">{{ columnLabel(column) }}</th></tr></thead>
        <tbody><tr v-for="(row, index) in report.rows" :key="index"><td v-for="column in report.columns" :key="column">{{ displayValue(column, row[column]) }}</td></tr><tr v-if="!report.rows.length"><td :colspan="report.columns.length || 1"><div class="education-empty"><VIcon icon="tabler-report-off" size="34" /><strong>{{ $t('educational.noRows') }}</strong><span>{{ $t('educational.adjustFilters') }}</span></div></td></tr></tbody>
      </VTable></div>
      <VDivider v-if="report?.last_page && report.last_page > 1" />
      <VCardText v-if="report?.last_page && report.last_page > 1" class="d-flex justify-center py-2"><VPagination v-model="page" :length="report.last_page" size="small" :total-visible="7" @update:model-value="load" /></VCardText>
    </VCard>
  </div>
</template>
