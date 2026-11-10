<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ReportApi, { type GeneratedReport, type ReportCatalog, type ReportFilters, type ReportSection, type ReportType } from '@/api/report'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import ResponsiveDataTable from '@/components/responsive-data-table/ResponsiveDataTable.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import { canAccess } from '@/utils/authorization'
import { apiErrorMessage } from '@/utils/apiErrors'

interface ReportProfile {
  key: ReportType
}

interface Metric {
  label: string
  value: string
  detail: string
  icon: string
  color: string
}

const profiles: Record<ReportType, ReportProfile> = {
  financial: { key: 'financial' },
  payroll: { key: 'payroll' },
  attendance: { key: 'attendance' },
  educational: { key: 'educational' },
  crm: { key: 'crm' },
}

const { locale } = useI18n()
const route = useRoute()
const rt = (key: string, values?: Record<string, unknown>) => translate(`pages.reports.studio.${key}`, values)
const profileText = (profile: ReportProfile) => rt(`profiles.${profile.key}.label`)
const now = new Date()
const dateKey = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
const type = ref<ReportType>('financial')
const catalog = ref<ReportCatalog | null>(null)
const report = ref<GeneratedReport | null>(null)
const loading = ref(false)
const exporting = ref(false)
const error = ref('')
const filtersOpen = ref(false)
const tableSearch = ref('')
const filters = reactive<ReportFilters>({ start_date: dateKey(startOfMonth), end_date: dateKey(now) })

const availableTypes = computed(() => catalog.value?.types.map(item => item.value) ?? [])

const canExport = computed(() => canAccess('export_reports') && (type.value !== 'financial' || canAccess('export_financial_reports')))
const baseCurrency = computed(() => catalog.value?.currencies.find(currency => currency.is_base)?.code ?? 'USD')

const resetContextFilters = () => {
  filters.employee_id = null; filters.office_id = null; filters.applicant_id = null; filters.currency_id = null
  filters.board_id = null; filters.status = null; filters.contract_type = null; filters.attendance_state = null
}

const generate = async () => {
  loading.value = true
  error.value = ''
  try {
    report.value = await ReportApi.generate(type.value, filters)
  }
  catch (caught) {
    report.value = null
    error.value = apiErrorMessage(caught, rt('errors.generate'))
  }
  finally { loading.value = false }
}

const changeType = async (nextType: ReportType) => {
  type.value = nextType
  resetContextFilters()
  await generate()
}

const resetFilters = async () => {
  resetContextFilters()
  filters.start_date = dateKey(startOfMonth)
  filters.end_date = dateKey(now)
  await generate()
}

const runAnalysis = async () => {
  await generate()
  filtersOpen.value = false
}

const exportCsv = async () => {
  exporting.value = true; error.value = ''
  try {
    const blob = await ReportApi.export(type.value, filters)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')

    link.href = url; link.download = `${type.value}-report-${filters.start_date}-${filters.end_date}.csv`; link.click()
    URL.revokeObjectURL(url)
  }
  catch (caught) { error.value = apiErrorMessage(caught, rt('errors.export')) }
  finally { exporting.value = false }
}

const openPrintReport = async () => {
  window.print()
}

const section = (key: string) => report.value?.sections.find(item => item.key === key)

const numericTotal = (reportSection: ReportSection | undefined, key: string) => {
  const value = reportSection?.totals[key]
  if (typeof value === 'number')
    return value
  if (value && typeof value === 'object')
    return Object.values(value as Record<string, unknown>).reduce<number>((sum, item) => sum + (Number(item) || 0), 0)

  return Number(value) || 0
}

const number = (value: number, digits = 0) => new Intl.NumberFormat(locale.value, { maximumFractionDigits: digits }).format(value)

const currency = (value: number, code = baseCurrency.value, compact = false) => {
  try { return new Intl.NumberFormat(locale.value, { style: 'currency', currency: code, notation: compact ? 'compact' : 'standard', maximumFractionDigits: compact ? 1 : 2 }).format(value) }
  catch { return `${code} ${number(value, 2)}` }
}

const duration = (minutes: number) => minutes < 60 ? rt('common.minutesShort', { value: number(minutes) }) : rt('common.hoursShort', { value: number(minutes / 60, 1) })
const columnTitle = (column: string) => {
  const translated = translate(`pages.reports.columns.${column}`)

  return translated.startsWith('[missing:') || translated === `pages.reports.columns.${column}`
    ? column.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())
    : translated
}
const sectionHeaders = (reportSection: ReportSection) => reportSection.columns.map(column => ({ title: columnTitle(column), key: column }))
const matchingRows = (reportSection: ReportSection) => {
  const query = tableSearch.value.trim().toLocaleLowerCase()
  if (!query)
    return reportSection.rows

  return reportSection.rows.filter(row => Object.values(row).some(value => String(value ?? '').toLocaleLowerCase().includes(query)))
}
const tableValue = (value: unknown) => {
  if (value === null || value === undefined || value === '')
    return '—'
  if (typeof value === 'object')
    return Object.entries(value as Record<string, unknown>).map(([key, item]) => `${key}: ${number(Number(item) || 0, 2)}`).join(' · ')

  return String(value)
}
const loadingHeaders = Array.from({ length: 6 }, (_, index) => ({ title: '', key: `loading-${index}` }))
const activeSection = computed(() => {
  const preferred: Record<ReportType, string> = { financial: 'income', payroll: 'salary_payments', attendance: 'attendance', educational: 'applicants', crm: 'visitors' }

  return report.value?.sections.find(item => item.key === preferred[type.value]) ?? report.value?.sections[0] ?? null
})

const metrics = computed<Metric[]>(() => {
  if (type.value === 'financial') {
    const income = numericTotal(section('income'), 'base_amount_by_base')
    const expenses = numericTotal(section('expenses'), 'base_amount_by_base')

    return [
      { label: rt('metrics.cashCollected'), value: currency(income, baseCurrency.value, true), detail: rt('metrics.recordsInPeriod', { count: number(numericTotal(section('income'), 'count')) }), icon: 'tabler-cash', color: 'success' },
      { label: rt('metrics.expenses'), value: currency(expenses, baseCurrency.value, true), detail: rt('metrics.recordsInPeriod', { count: number(numericTotal(section('expenses'), 'count')) }), icon: 'tabler-receipt-2', color: 'warning' },
      { label: rt('metrics.netCashFlow'), value: currency(income - expenses, baseCurrency.value, true), detail: rt('metrics.spent', { amount: currency(expenses, baseCurrency.value, true) }), icon: 'tabler-chart-arrows-vertical', color: income - expenses >= 0 ? 'success' : 'error' },
      { label: rt('metrics.recordsInPeriod', { count: number(numericTotal(section('income'), 'count') + numericTotal(section('expenses'), 'count')) }), value: number(numericTotal(section('asset_assets'), 'records')), detail: rt('metrics.activeCount', { count: number(numericTotal(section('asset_assets'), 'units')) }), icon: 'tabler-building-warehouse', color: 'info' },
    ]
  }
  if (type.value === 'payroll') {
    const payroll = numericTotal(section('salary_payments'), 'base_amount_by_base')
    const deductions = numericTotal(section('salary_payments'), 'deductions_by_base')
    const bonuses = numericTotal(section('bonuses'), 'base_amount_by_base')

    return [
      { label: rt('metrics.payrollCost'), value: currency(payroll, baseCurrency.value, true), detail: rt('metrics.payments', { count: number(numericTotal(section('salary_payments'), 'count')) }), icon: 'tabler-cash-banknote', color: 'primary' },
      { label: rt('metrics.bonuses'), value: currency(bonuses, baseCurrency.value, true), detail: rt('metrics.nominations', { count: number(numericTotal(section('bonuses'), 'count')) }), icon: 'tabler-award', color: 'success' },
      { label: rt('metrics.deductions'), value: currency(deductions, baseCurrency.value, true), detail: rt('metrics.acrossSalaryPayments'), icon: 'tabler-receipt-tax', color: 'warning' },
      { label: rt('metrics.headcount'), value: number(numericTotal(section('employee_roster'), 'company_headcount')), detail: rt('metrics.joinedInPeriod', { count: number(numericTotal(section('employee_roster'), 'joined_in_period')) }), icon: 'tabler-users-group', color: 'info' },
    ]
  }
  if (type.value === 'attendance') {
    const records = numericTotal(section('attendance'), 'records')
    const present = numericTotal(section('attendance'), 'present')

    return [
      { label: rt('metrics.attendanceRate'), value: `${records ? number((present / records) * 100, 1) : 0}%`, detail: rt('metrics.presentRecords', { count: number(present) }), icon: 'tabler-user-check', color: 'success' },
      { label: rt('metrics.workedTime'), value: duration(numericTotal(section('attendance'), 'worked_minutes')), detail: rt('metrics.netWorkingTime'), icon: 'tabler-clock-hour-4', color: 'primary' },
      { label: rt('metrics.overtime'), value: duration(numericTotal(section('attendance'), 'overtime_minutes')), detail: rt('metrics.beyondSchedules'), icon: 'tabler-clock-plus', color: 'info' },
      { label: rt('metrics.incompleteRecords'), value: number(numericTotal(section('attendance'), 'incomplete')), detail: rt('metrics.missingPunch'), icon: 'tabler-alert-triangle', color: numericTotal(section('attendance'), 'incomplete') ? 'warning' : 'success' },
    ]
  }

  if (type.value === 'educational') {
    return [
      { label: rt('metrics.newApplicants'), value: number(numericTotal(section('applicants'), 'count')), detail: rt('metrics.addedInPeriod'), icon: 'tabler-user-plus', color: 'primary' },
      { label: rt('metrics.serviceCommitments'), value: number(numericTotal(section('applicant_contracts'), 'commitments')), detail: rt('metrics.activeCount', { count: number(numericTotal(section('applicant_contracts'), 'active')) }), icon: 'tabler-heart-handshake', color: 'success' },
      { label: rt('metrics.contractsInPeriod'), value: number(numericTotal(section('contracts'), 'count')), detail: rt('metrics.addedInPeriod'), icon: 'tabler-file-text', color: 'info' },
      { label: rt('metrics.activeCount', { count: number(numericTotal(section('applicant_contracts'), 'active')) }), value: number(numericTotal(section('applicant_contracts'), 'active')), detail: rt('metrics.serviceCommitments'), icon: 'tabler-chart-bar', color: 'warning' },
    ]
  }

  return [
    { label: rt('metrics.visitors'), value: number(numericTotal(section('visitors'), 'count')), detail: rt('metrics.commercialInterest'), icon: 'tabler-walk', color: 'info' },
    { label: rt('metrics.taskDeadlines'), value: number(numericTotal(section('tasks'), 'count')), detail: rt('metrics.tasksInReport', { count: number(numericTotal(section('tasks'), 'count')) }), icon: 'tabler-checklist', color: 'primary' },
    { label: rt('metrics.deliveryRisk'), value: number(numericTotal(section('tasks'), 'overdue')), detail: rt('metrics.tasksInReport', { count: number(numericTotal(section('tasks'), 'count')) }), icon: 'tabler-calendar-exclamation', color: numericTotal(section('tasks'), 'overdue') ? 'error' : 'success' },
    { label: rt('metrics.activeCount', { count: number(numericTotal(section('tasks'), 'completed')) }), value: number(numericTotal(section('tasks'), 'completed')), detail: rt('metrics.tasksInReport', { count: number(numericTotal(section('tasks'), 'count')) }), icon: 'tabler-circle-check', color: 'success' },
  ]
})

const filteredChipCount = computed(() => Object.entries(filters).filter(([key, value]) => !['start_date', 'end_date'].includes(key) && value !== null && value !== undefined && value !== '').length)

onMounted(async () => {
  loading.value = true
  try {
    catalog.value = await ReportApi.catalog()
    if (catalog.value.types.length)
      type.value = route.query.report === 'financial' && catalog.value.types.some(item => item.value === 'financial')
        ? 'financial'
        : catalog.value.types[0].value
    await generate()
  }
  catch (caught) { error.value = apiErrorMessage(caught, rt('errors.catalog')) }
  finally { loading.value = false }
})
</script>

<template>
  <main class="reporting-studio">
    <ErpPageHeader :title="$t('common.navigation.reports')" section="finance" class="mb-5" />

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      closable
      class="mb-5 no-print"
      @click:close="error = ''"
    >
      {{ error }}
    </VAlert>

    <VCard class="report-toolbar mb-5 no-print" elevation="0">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="5"><ErpSearchField v-model="tableSearch" :placeholder="$t('common.actions.search')" /></VCol>
          <VCol cols="12" md="7" class="report-toolbar__actions d-flex flex-wrap align-center justify-md-end ga-2">
      <DataTableFilterMenu
        v-model="filtersOpen"
        :active="filteredChipCount > 0"
        @reset="resetFilters"
      >
        <VRow
          class="align-end"
          dense
        >
          <VCol
            cols="12"
            sm="6"
            md="6"
          >
            <ErpField
              v-model="filters.start_date"
              kind="date"
              :label="rt('filters.startDate')"
              :placeholder="rt('filters.startDate')"
              :config="{ dateFormat: 'Y-m-d' }"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="6"
          >
            <ErpField
              v-model="filters.end_date"
              kind="date"
              :label="rt('filters.endDate')"
              :placeholder="rt('filters.endDate')"
              :config="{ dateFormat: 'Y-m-d' }"
            />
          </VCol>
          <VCol
            v-if="type === 'payroll' || type === 'attendance'"
            cols="12"
            md="6"
          >
            <ErpField
              v-model="filters.employee_id"
              kind="select"
              :items="catalog?.employees ?? []"
              item-title="name"
              item-value="id"
              :label="rt('filters.employee')"
              clearable
            />
          </VCol>
          <VCol
            v-if="type === 'payroll' || type === 'attendance'"
            cols="12"
            md="6"
          >
            <ErpField
              v-model="filters.office_id"
              kind="select"
              :items="catalog?.offices ?? []"
              item-title="name"
              item-value="id"
              :label="rt('filters.office')"
              clearable
            />
          </VCol>
          <VCol
            v-if="type === 'financial'"
            cols="12"
            md="6"
          >
            <ErpField
              v-model="filters.applicant_id"
              kind="select"
              :items="catalog?.applicants ?? []"
              item-title="name"
              item-value="id"
              :label="rt('filters.applicant')"
              clearable
            />
          </VCol>
          <VCol
            v-if="type === 'financial' || type === 'payroll'"
            cols="12"
            md="6"
          >
            <ErpField
              v-model="filters.currency_id"
              kind="select"
              :items="catalog?.currencies ?? []"
              item-title="code"
              item-value="id"
              :label="rt('filters.currency')"
              clearable
            />
          </VCol>
          <VCol
            v-if="type === 'crm'"
            cols="12"
            md="6"
          >
            <ErpField
              v-model="filters.board_id"
              kind="select"
              :items="catalog?.boards ?? []"
              item-title="title"
              item-value="id"
              :label="rt('filters.taskBoard')"
              clearable
            />
          </VCol>
          <VCol
            v-if="type === 'educational'"
            cols="12"
            md="6"
          >
            <ErpField
              v-model="filters.contract_type"
              kind="select"
              :items="catalog?.contract_types ?? []"
              :label="rt('filters.contractType')"
              clearable
            />
          </VCol>
          <VCol
            v-if="type === 'attendance'"
            cols="12"
            md="6"
          >
            <ErpField
              v-model="filters.attendance_state"
              kind="select"
                  :items="['present', 'absent', 'off_day', 'complete', 'incomplete']"
              :item-title="translateBackendValue"
              :label="rt('filters.attendanceState')"
              clearable
            />
          </VCol>
          <VCol
            v-if="type === 'financial' || type === 'payroll'"
            cols="12"
            md="6"
          >
            <ErpField
              v-model="filters.status"
              kind="select"
              :items="type === 'financial' ? ['Issued', 'Draft', 'Paid', 'Cancelled'] : ['granted', 'pending']"
              :label="rt('filters.status')"
              clearable
            />
          </VCol>
        </VRow>
        <template #actions>
          <VBtn variant="tonal" color="secondary" @click="resetFilters">{{ $t('common.messages.reset') }}</VBtn>
          <VBtn color="primary" variant="flat" :loading="loading" @click="runAnalysis">{{ $t('educational.apply') }}</VBtn>
        </template>
      </DataTableFilterMenu>
            <VBtn v-if="canExport" size="small" variant="tonal" prepend-icon="tabler-file-spreadsheet" :loading="exporting" :disabled="!report" @click="exportCsv">{{ rt('header.exportCsv') }}</VBtn>
            <VBtn v-if="canExport" icon="tabler-printer" size="small" variant="tonal" :loading="exporting" :disabled="!report" :aria-label="$t('common.actions.print')" @click="openPrintReport"><VIcon icon="tabler-printer" /></VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <ResponsiveDataTable v-if="loading && !report" class="report-data-table report-data-table--loading" :headers="loadingHeaders" :items="[]" :loading="true" :skeleton-rows="6" :show-scroll-affordance="false" :mobile-cards="true" :sticky-key-column="false" :sticky-action-column="false" />
      <section v-if="activeSection" class="report-sections">
        <VTabs v-model="type" show-arrows class="report-tabs" @update:model-value="changeType"><VTab v-for="reportType in availableTypes" :key="reportType" :value="reportType">{{ profileText(profiles[reportType]) }}</VTab></VTabs>
        <ResponsiveDataTable
          :headers="loading ? loadingHeaders : sectionHeaders(activeSection)"
          :items="matchingRows(activeSection)"
          :loading="loading"
          :skeleton-rows="5"
          :show-scroll-affordance="false"
          :mobile-cards="true"
          :mobile-fields="activeSection.columns.slice(1, 4)"
          :sticky-first-column="false"
          :sticky-last-column="false"
          :sticky-key-column="false"
          :sticky-action-column="false"
          :class="['report-data-table', `report-data-table--${type}`, loading && 'report-data-table--loading']"
        >
          <template #no-data><TableEmptyState icon="tabler-report-off" :title="$t('common.messages.noRecordsFound')" :description="$t('common.messages.noMatchingRecords')" /></template>
          <template v-for="column in activeSection.columns" :key="column" #[`item.${column}`]="{ item }">{{ tableValue(item[column]) }}</template>
        </ResponsiveDataTable>
      </section>
    </VCard>

    <article class="report-result">
      <div class="report-kpis mb-5">
        <VCard
          v-for="metric in metrics"
          :key="metric.label"
          class="report-kpi"
          border
          variant="flat"
        >
          <VCard class="pa-4" variant="flat">
            <div class="d-flex justify-space-between ga-3">
              <div class="min-w-0">
                <p class="metric-label mb-1">
                  {{ metric.label }}
                </p><h3 class="text-h4 font-weight-bold text-truncate mb-1">
                  {{ metric.value }}
                </h3><p class="text-caption text-medium-emphasis text-truncate mb-0">
                  {{ metric.detail }}
                </p>
              </div><VAvatar
                :color="metric.color"
                variant="tonal"
                rounded="lg"
                size="48"
              >
                <VIcon
                  :icon="metric.icon"
                  size="24"
                />
              </VAvatar>
            </div>
          </VCard>
        </VCard>
      </div>
    </article>
  </main>
</template>

<style scoped>
.reporting-studio { display: flex; flex-direction: column; margin-inline: auto; }
.reporting-studio > :deep(.erp-page-header) { order: 1; }
.reporting-studio > .v-alert { order: 2; }
.report-result { display: contents; }
.report-result > .report-kpis { order: 4; }
.report-toolbar { order: 5; }
.report-sections { order: 6; }
.report-table-card--loading { order: 6; }
.metric-label { color: rgba(var(--v-theme-on-surface), .56); font-size: .69rem; font-weight: 700; letter-spacing: .105em; text-transform: uppercase; }
.report-toolbar { overflow: hidden; border: 0; border-radius: 16px; background: rgb(var(--v-theme-surface)); box-shadow: 0 10px 28px rgb(15 23 42 / .08); }
.report-tabs :deep(.v-slide-group__content) { flex-wrap: wrap; }
.report-toolbar :deep(.v-field) { border-radius: 10px; background: rgb(var(--v-theme-on-surface) / .025); }
.report-toolbar__actions :deep(.v-btn) { min-block-size: 38px; padding-inline: .85rem; font-size: .88rem; }
.report-kpis { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(220px, 1fr)); }
.report-kpis::-webkit-scrollbar { display: none; }
.report-kpi { min-inline-size: 0; border-radius: 12px !important; }
.report-kpi > .v-card { box-shadow: none !important; }
.report-kpi :deep(.text-h4) { font-size: 1.15rem !important; line-height: 1.3; }
.report-data-table :deep(.v-table__wrapper) { overflow-x: auto !important; overflow-y: hidden; scrollbar-width: none; }
.report-data-table :deep(.v-table__wrapper::-webkit-scrollbar) { display: none; }
.report-data-table :deep(.v-table__wrapper > table) { inline-size: max-content !important; min-inline-size: 100% !important; table-layout: auto !important; }
.report-toolbar :deep(thead th), .report-data-table :deep(.v-table__wrapper > table th) { min-block-size: 3rem; padding-block: .9rem !important; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.report-data-table--loading :deep(thead th) { position: relative; color: transparent !important; }
.report-data-table--loading :deep(thead th::after) { position: absolute; inset-block-start: 50%; inline-size: 58%; block-size: .72rem; border-radius: 999px; background: linear-gradient(90deg, rgb(var(--v-theme-on-surface) / .07), rgb(var(--v-theme-on-surface) / .15), rgb(var(--v-theme-on-surface) / .07)); content: ''; transform: translateY(-50%); animation: report-table-skeleton-wave 1.45s ease-in-out infinite; }
.report-data-table--loading :deep(thead th:nth-child(odd)::after) { inline-size: 72%; }
.report-data-table :deep(.v-table__wrapper > table th), .report-data-table :deep(.v-table__wrapper > table td) { inline-size: max-content; min-inline-size: max-content !important; white-space: nowrap !important; }
.min-w-0 { min-width: 0; }

@keyframes report-table-skeleton-wave {
  0%, 100% { opacity: .55; }
  50% { opacity: 1; }
}

@media (max-width: 959px) {
  .report-kpis { display: flex; overflow-x: auto; padding-block-end: .25rem; scrollbar-width: none; }
  .report-kpi { flex: 0 0 17rem; }
}
</style>

<!-- The responsive table is rendered by a child component.  Keep the wide
 finance report table sizing global so the real table is targeted. -->
<style>
.report-data-table--financial .v-table__wrapper,
.report-data-table--payroll .v-table__wrapper,
.report-data-table--attendance .v-table__wrapper {
  overflow-x: auto !important;
  overflow-y: hidden;
  scrollbar-width: none;
}
.report-data-table--financial .v-table__wrapper::-webkit-scrollbar,
.report-data-table--payroll .v-table__wrapper::-webkit-scrollbar,
.report-data-table--attendance .v-table__wrapper::-webkit-scrollbar { display: none; }
.report-data-table--financial .v-table__wrapper > table,
.report-data-table--payroll .v-table__wrapper > table,
.report-data-table--attendance .v-table__wrapper > table {
  inline-size: max-content !important;
  min-inline-size: 92rem !important;
  table-layout: auto !important;
}
.report-data-table--financial .v-table__wrapper > table > thead > tr > th,
.report-data-table--financial .v-table__wrapper > table > tbody > tr > td,
.report-data-table--payroll .v-table__wrapper > table > thead > tr > th,
.report-data-table--payroll .v-table__wrapper > table > tbody > tr > td,
.report-data-table--attendance .v-table__wrapper > table > thead > tr > th,
.report-data-table--attendance .v-table__wrapper > table > tbody > tr > td {
  min-inline-size: 10rem !important;
  padding-inline: 1rem !important;
  white-space: nowrap !important;
}
</style>
