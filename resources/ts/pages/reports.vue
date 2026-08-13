<script setup lang="ts">
import ReportApi, { type GeneratedReport, type ReportCatalog, type ReportFilters, type ReportSection, type ReportType } from '@/api/report'
import { apiErrorMessage } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import { canAccess } from '@/utils/authorization'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()
const today = new Date()
const startOfMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`
const todayKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
const type = ref<ReportType>('financial')
const catalog = ref<ReportCatalog | null>(null)
const report = ref<GeneratedReport | null>(null)
const loading = ref(false)
const exporting = ref(false)
const error = ref('')
const filters = reactive<ReportFilters>({ start_date: startOfMonth, end_date: todayKey })

const typeOptions = computed(() => catalog.value?.types.map(item => ({ title: translate(`pages.reports.types.${item.value}`), value: item.value })) ?? [])
const direction = computed(() => locale.value === 'fa-AF' || locale.value === 'ps-AF' ? 'rtl' : 'ltr')
const generatedAt = computed(() => report.value
  ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'medium', timeZone: report.value.meta.timezone }).format(new Date(report.value.meta.generated_at))
  : '')
const canExport = computed(() => canAccess('export_reports') && (type.value !== 'financial' || canAccess('export_financial_reports')))

const resetIrrelevantFilters = () => {
  filters.employee_id = null
  filters.office_id = null
  filters.customer_id = null
  filters.currency_id = null
  filters.board_id = null
  filters.status = null
  filters.contract_type = null
  filters.attendance_state = null
}

const generate = async () => {
  loading.value = true
  error.value = ''
  try {
    report.value = await ReportApi.generate(type.value, filters)
  } catch (caught) {
    report.value = null
    error.value = apiErrorMessage(caught, 'Unable to generate the report.')
  } finally {
    loading.value = false
  }
}

const changeType = async () => {
  resetIrrelevantFilters()
  await generate()
}

const exportCsv = async () => {
  exporting.value = true
  error.value = ''
  try {
    const blob = await ReportApi.export(type.value, filters)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${type.value}-report-${filters.start_date}-${filters.end_date}.csv`
    link.click()
    URL.revokeObjectURL(url)
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Unable to export the report.')
  } finally {
    exporting.value = false
  }
}

const printReport = () => printDocument({ bodyClass: 'printing-generated-report', pageStyle: 'size: A4 landscape; margin: 10mm;' })

const humanize = (value: string) => value.startsWith('reports.')
  ? translate(`pages.${value}`)
  : translateBackendValue(value, 'pages.reports.columns')

const formatValue = (value: unknown, column: string): string => {
  if (value === null || value === undefined || value === '') return translate('common.messages.notAvailable')
  if (typeof value === 'boolean') return translateBackendValue(value ? 'yes' : 'no')
  if (column.endsWith('_utc')) {
    return new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'short', timeZone: report.value?.meta.timezone }).format(new Date(String(value)))
  }
  if (column.endsWith('_minutes') && typeof value === 'number') {
    const hours = Math.floor(value / 60)
    const minutes = value % 60
    return `${new Intl.NumberFormat(locale.value, { style: 'unit', unit: 'hour', unitDisplay: 'narrow' }).format(hours)} ${new Intl.NumberFormat(locale.value, { style: 'unit', unit: 'minute', unitDisplay: 'narrow' }).format(minutes)}`
  }
  if (typeof value === 'number') return formatLocalizedNumber(value, { maximumFractionDigits: 4 })
  if (typeof value === 'object') return Object.entries(value as Record<string, unknown>).map(([key, item]) => `${key}: ${formatValue(item, key)}`).join(' · ')
  if (/status|state|priority|type$/.test(column)) return translateBackendValue(value)
  return String(value)
}

const totals = (section: ReportSection) => Object.entries(section.totals)

onMounted(async () => {
  loading.value = true
  try {
    catalog.value = await ReportApi.catalog()
    if (catalog.value.types.length) type.value = catalog.value.types[0].value
    await generate()
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Unable to load report options.')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="reports-page">
    <VCard class="mb-6 report-controls no-print">
      <VCardText>
        <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-5">
          <div>
            <h2 class="text-h4 font-weight-bold mb-1">{{ $t('pages.reports.reports.reports.reports') }}</h2>
            <p class="text-body-2 text-medium-emphasis mb-0">{{ $t('pages.reports.reports.reports.generatePermissionAwareOperationalReportsFromRecordedSystemData') }}</p>
          </div>
          <div class="d-flex gap-2">
            <VBtn v-if="canExport" variant="tonal" prepend-icon="tabler-file-spreadsheet" :loading="exporting" :disabled="!report" @click="exportCsv">{{ $t('pages.reports.reports.reports.exportCSV') }}</VBtn>
            <VBtn v-if="canExport" color="primary" prepend-icon="tabler-printer" :disabled="!report" @click="printReport">{{ $t('pages.reports.reports.reports.printSavePDF') }}</VBtn>
          </div>
        </div>

        <VRow>
          <VCol cols="12" md="3"><VSelect v-model="type" :items="typeOptions" :label="$t('pages.reports.reports.reports.reportCategory')" @update:model-value="changeType" /></VCol>
          <VCol cols="12" sm="6" md="3"><AppDateTimePicker v-model="filters.start_date" :label="$t('common.fields.startDate')" :config="{ dateFormat: 'Y-m-d' }" /></VCol>
          <VCol cols="12" sm="6" md="3"><AppDateTimePicker v-model="filters.end_date" :label="$t('common.fields.endDate')" :config="{ dateFormat: 'Y-m-d' }" /></VCol>
          <VCol v-if="type === 'payroll' || type === 'attendance'" cols="12" md="3"><VSelect v-model="filters.employee_id" :items="catalog?.employees ?? []" item-title="name" item-value="id" :label="$t('common.fields.employee')" clearable /></VCol>
          <VCol v-if="type === 'payroll' || type === 'attendance'" cols="12" md="3"><VSelect v-model="filters.office_id" :items="catalog?.offices ?? []" item-title="name" item-value="id" :label="$t('common.fields.office')" clearable /></VCol>
          <VCol v-if="type === 'financial'" cols="12" md="3"><VSelect v-model="filters.customer_id" :items="catalog?.customers ?? []" item-title="name" item-value="id" :label="$t('common.messages.customer')" clearable /></VCol>
          <VCol v-if="type === 'financial' || type === 'payroll'" cols="12" md="3"><VSelect v-model="filters.currency_id" :items="catalog?.currencies ?? []" item-title="code" item-value="id" :label="$t('common.fields.currency')" clearable /></VCol>
          <VCol v-if="type === 'operations'" cols="12" md="3"><VSelect v-model="filters.board_id" :items="catalog?.boards ?? []" item-title="title" item-value="id" :label="$t('pages.reports.reports.reports.taskBoard')" clearable /></VCol>
          <VCol v-if="type === 'operations'" cols="12" md="3"><VSelect v-model="filters.contract_type" :items="catalog?.contract_types ?? []" :label="$t('pages.reports.reports.reports.contractType')" clearable /></VCol>
          <VCol v-if="type === 'attendance'" cols="12" md="3"><VSelect v-model="filters.attendance_state" :items="['present', 'absent', 'paid-off-day', 'complete', 'incomplete']" :item-title="translateBackendValue" :label="$t('pages.reports.reports.reports.attendanceState')" clearable /></VCol>
          <VCol v-if="type === 'financial' || type === 'payroll' || type === 'operations'" cols="12" md="3"><VTextField v-model="filters.status" :label="$t('common.fields.status')" clearable /></VCol>
          <VCol cols="12" md="3" class="d-flex align-center"><VBtn block color="primary" size="large" :loading="loading" @click="generate">{{ $t('pages.reports.reports.reports.generateReport') }}</VBtn></VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VAlert v-if="error" type="error" variant="tonal" class="mb-5 no-print">{{ error }}</VAlert>
    <div v-if="loading && !report" class="text-center py-12 no-print"><VProgressCircular indeterminate color="primary" /></div>

    <article v-if="report" class="generated-report" :dir="direction">
      <header class="report-header">
        <div class="d-flex align-center gap-3">
          <img v-if="report.meta.company.logo" :src="report.meta.company.logo" alt="" class="company-logo">
          <div>
            <h1>{{ report.meta.company.name || report.meta.company.short_name }}</h1>
            <div class="text-medium-emphasis">{{ $t('pages.reports.reportTitle', { type: $t(`pages.reports.types.${report.type}`) }) }}</div>
          </div>
        </div>
        <div class="report-meta">
          <div>{{ formatLocalizedDate(`${report.filters.start_date}T00:00:00`) }} — {{ formatLocalizedDate(`${report.filters.end_date}T00:00:00`) }}</div>
          <div>{{ generatedAt }}</div>
          <div>{{ report.meta.timezone }}</div>
        </div>
      </header>

      <section v-for="section in report.sections" :key="section.key" class="report-section">
        <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-3">
          <h2>{{ $t(`pages.reports.sections.${section.key}`) }}</h2>
          <div class="d-flex flex-wrap gap-2">
            <VChip v-for="([key, value]) in totals(section)" :key="key" size="small" variant="tonal">
              {{ humanize(key) }}: {{ formatValue(value, key) }}
            </VChip>
          </div>
        </div>
        <div class="report-table-wrap">
          <table>
            <thead><tr><th v-for="column in section.columns" :key="column">{{ humanize(column) }}</th></tr></thead>
            <tbody>
              <tr v-for="(row, rowIndex) in section.rows" :key="rowIndex">
                <td v-for="column in section.columns" :key="column">{{ formatValue(row[column], column) }}</td>
              </tr>
              <tr v-if="section.rows.length === 0"><td :colspan="section.columns.length" class="text-center text-medium-emphasis py-6">{{ $t('pages.reports.reports.reports.noRecordsFound') }}</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <footer class="report-footer">
        <div>{{ $t(report.meta.currency_basis) }}</div>
        <div>{{ report.meta.company.address }} · {{ report.meta.company.email }} · {{ report.meta.company.phone }}</div>
      </footer>
    </article>
  </div>
</template>

<style scoped>
@font-face { font-family: "Report Persian"; src: local("Vazirmatn"), local("Vazir"), local("Tahoma"); font-display: swap; }
.reports-page { font-family: "Report Persian", Tahoma, "Segoe UI", Arial, sans-serif; }
.report-controls { border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.generated-report { background: rgb(var(--v-theme-surface)); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 12px; padding: 24px; }
.report-header { align-items: center; border-bottom: 2px solid rgb(var(--v-theme-primary)); display: flex; justify-content: space-between; margin-bottom: 24px; padding-bottom: 16px; }
.report-header h1 { font-size: 1.5rem; margin: 0; }.report-meta { font-size: .8rem; line-height: 1.6; text-align: end; }
.company-logo { height: 52px; max-width: 150px; object-fit: contain; }.report-section { margin-top: 24px; }.report-section h2 { font-size: 1.1rem; margin: 0; }
.report-table-wrap { overflow-x: auto; } table { border-collapse: collapse; min-width: 100%; white-space: nowrap; } th, td { border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); padding: 8px 10px; text-align: start; } th { background: rgba(var(--v-theme-primary), .08); font-size: .75rem; text-transform: uppercase; }
.report-footer { border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); color: rgb(var(--v-theme-on-surface-variant)); display: flex; font-size: .75rem; justify-content: space-between; margin-top: 28px; padding-top: 12px; }
@media (max-width: 700px) { .report-header, .report-footer { align-items: flex-start; flex-direction: column; gap: 8px; } }
</style>

<style>
@media print {
  body.printing-generated-report .no-print,
  body.printing-generated-report .layout-navbar,
  body.printing-generated-report .layout-horizontal-nav,
  body.printing-generated-report .v-navigation-drawer,
  body.printing-generated-report .layout-footer { display: none !important; }
  body.printing-generated-report .generated-report { border: 0 !important; color: #172033 !important; font-family: "Report Persian", Tahoma, "Segoe UI", Arial, sans-serif !important; padding: 0 !important; }
  body.printing-generated-report .report-section { break-inside: auto; }
  body.printing-generated-report tr { break-inside: avoid; }
  body.printing-generated-report .report-table-wrap { overflow: visible !important; }
  body.printing-generated-report table { font-size: 8px; white-space: normal; }
  body.printing-generated-report .v-chip { border: 1px solid #ccd2dc !important; color: #172033 !important; }
}
</style>
