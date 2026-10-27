<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ReportApi, { type GeneratedReport, type ReportCatalog, type ReportFilters, type ReportSection, type ReportType } from '@/api/report'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
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
const datePreset = ref('month')
const filtersOpen = ref(false)
const filters = reactive<ReportFilters>({ start_date: dateKey(startOfMonth), end_date: dateKey(now) })

const availableTypes = computed(() => catalog.value?.types.map(item => item.value) ?? [])

const canExport = computed(() => canAccess('export_reports') && (type.value !== 'financial' || canAccess('export_financial_reports')))
const baseCurrency = computed(() => catalog.value?.currencies.find(currency => currency.is_base)?.code ?? 'USD')

const resetContextFilters = () => {
  filters.employee_id = null; filters.office_id = null; filters.applicant_id = null; filters.currency_id = null
  filters.board_id = null; filters.status = null; filters.contract_type = null; filters.attendance_state = null
}

const applyPreset = (preset: string) => {
  const end = new Date()
  let start = new Date(end.getFullYear(), end.getMonth(), 1)
  if (preset === '30days')
    start = new Date(end.getFullYear(), end.getMonth(), end.getDate() - 29)
  if (preset === 'quarter')
    start = new Date(end.getFullYear(), Math.floor(end.getMonth() / 3) * 3, 1)
  if (preset === 'year')
    start = new Date(end.getFullYear(), 0, 1)
  if (preset === 'all')
    start = new Date(2020, 0, 1)
  filters.start_date = dateKey(start)
  filters.end_date = dateKey(end)
  datePreset.value = preset
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
  if (type.value === nextType && report.value)
    return
  type.value = nextType
  resetContextFilters()
  await generate()
}

const resetFilters = async () => {
  resetContextFilters()
  applyPreset('month')
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
  exporting.value = true; error.value = ''
  try {
    const blob = await ReportApi.export(type.value, filters, 'print')
    const url = URL.createObjectURL(blob)

    window.open(url, '_blank', 'noopener,noreferrer')
    window.setTimeout(() => URL.revokeObjectURL(url), 60_000)
  }
  catch (caught) { error.value = apiErrorMessage(caught, rt('errors.print')) }
  finally { exporting.value = false }
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

const metrics = computed<Metric[]>(() => {
  if (!report.value)
    return []
  if (type.value === 'financial') {
    const income = numericTotal(section('income'), 'base_amount_by_base')
    const expenses = numericTotal(section('expenses'), 'base_amount_by_base')

    return [
      { label: rt('metrics.cashCollected'), value: currency(income, baseCurrency.value, true), detail: rt('metrics.recordsInPeriod', { count: number(numericTotal(section('income'), 'count')) }), icon: 'tabler-cash', color: 'success' },
      { label: rt('metrics.expenses'), value: currency(expenses, baseCurrency.value, true), detail: rt('metrics.recordsInPeriod', { count: number(numericTotal(section('expenses'), 'count')) }), icon: 'tabler-receipt-2', color: 'warning' },
      { label: rt('metrics.netCashFlow'), value: currency(income - expenses, baseCurrency.value, true), detail: rt('metrics.spent', { amount: currency(expenses, baseCurrency.value, true) }), icon: 'tabler-chart-arrows-vertical', color: income - expenses >= 0 ? 'success' : 'error' },
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
    ]
  }

  return [
    { label: rt('metrics.visitors'), value: number(numericTotal(section('visitors'), 'count')), detail: rt('metrics.commercialInterest'), icon: 'tabler-walk', color: 'info' },
    { label: rt('metrics.taskDeadlines'), value: number(numericTotal(section('tasks'), 'count')), detail: rt('metrics.tasksInReport', { count: number(numericTotal(section('tasks'), 'count')) }), icon: 'tabler-checklist', color: 'primary' },
    { label: rt('metrics.deliveryRisk'), value: number(numericTotal(section('tasks'), 'overdue')), detail: rt('metrics.tasksInReport', { count: number(numericTotal(section('tasks'), 'count')) }), icon: 'tabler-calendar-exclamation', color: numericTotal(section('tasks'), 'overdue') ? 'error' : 'success' },
  ]
})

const filteredChipCount = computed(() => Object.entries(filters).filter(([key, value]) => !['start_date', 'end_date'].includes(key) && value !== null && value !== undefined && value !== '').length)

onMounted(async () => {
  loading.value = true
  try {
    catalog.value = await ReportApi.catalog()
    if (catalog.value.types.length)
      type.value = catalog.value.types[0].value
    await generate()
  }
  catch (caught) { error.value = apiErrorMessage(caught, rt('errors.catalog')) }
  finally { loading.value = false }
})
</script>

<template>
  <main class="reporting-studio pa-4 pa-md-6">
    <header class="d-flex flex-wrap align-end justify-space-between ga-4 mb-6 no-print">
      <div>
        <div class="eyebrow mb-2">
          {{ rt('header.eyebrow') }}
        </div><h1 class="text-h3 font-weight-bold tracking-tight mb-1">
          {{ rt('header.title') }}
        </h1><p class="text-body-1 text-medium-emphasis mb-0">
          {{ rt('header.subtitle') }}
        </p>
      </div>
      <div class="d-flex ga-2">
        <VBtn
          v-if="canExport"
          variant="outlined"
          prepend-icon="tabler-file-spreadsheet"
          :loading="exporting"
          :disabled="!report"
          @click="exportCsv"
        >
          {{ rt('header.exportCsv') }}
        </VBtn><VBtn
          v-if="canExport"
          color="primary"
          prepend-icon="tabler-printer"
          :loading="exporting"
          :disabled="!report"
          @click="openPrintReport"
        >
          {{ rt('header.printReport') }}
        </VBtn>
      </div>
    </header>

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

    <section class="report-library no-print mb-5">
      <button
        v-for="reportType in availableTypes"
        :key="reportType"
        class="report-type-card"
        :class="{ active: type === reportType }"
        type="button"
        @click="changeType(reportType)"
      >
        {{ profileText(profiles[reportType]) }}
      </button>
    </section>

    <div class="d-flex justify-end mb-6 no-print">
      <DataTableFilterMenu
        v-model="filtersOpen"
        :active="filteredChipCount > 0 || datePreset !== 'month'"
        @reset="resetFilters"
      >
        <VBtnToggle
          v-model="datePreset"
          mandatory
          density="compact"
          color="primary"
          variant="outlined"
          divided
          class="date-preset-toggle"
        >
          <VBtn
            value="month"
            @click="applyPreset('month')"
          >
            {{ rt('filters.thisMonth') }}
          </VBtn>
          <VBtn
            value="30days"
            @click="applyPreset('30days')"
          >
            {{ rt('filters.thirtyDays') }}
          </VBtn>
          <VBtn
            value="quarter"
            @click="applyPreset('quarter')"
          >
            {{ rt('filters.quarter') }}
          </VBtn>
          <VBtn
            value="year"
            @click="applyPreset('year')"
          >
            {{ rt('filters.year') }}
          </VBtn>
          <VBtn
            value="all"
            @click="applyPreset('all')"
          >
            {{ rt('filters.allTime') }}
          </VBtn>
        </VBtnToggle>
        <VRow
          class="align-end"
          dense
        >
          <VCol
            cols="12"
            sm="6"
            md="6"
          >
            <AppDateTimePicker
              v-model="filters.start_date"
              :label="rt('filters.startDate')"
              :config="{ dateFormat: 'Y-m-d' }"
              @update:model-value="datePreset = 'custom'"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="6"
          >
            <AppDateTimePicker
              v-model="filters.end_date"
              :label="rt('filters.endDate')"
              :config="{ dateFormat: 'Y-m-d' }"
              @update:model-value="datePreset = 'custom'"
            />
          </VCol>
          <VCol
            v-if="type === 'payroll' || type === 'attendance'"
            cols="12"
            md="6"
          >
            <VSelect
              v-model="filters.employee_id"
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
            <VSelect
              v-model="filters.office_id"
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
            <VSelect
              v-model="filters.applicant_id"
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
            <VSelect
              v-model="filters.currency_id"
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
            <VSelect
              v-model="filters.board_id"
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
            <VSelect
              v-model="filters.contract_type"
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
            <VSelect
              v-model="filters.attendance_state"
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
            <VCombobox
              v-model="filters.status"
              :items="type === 'financial' ? ['Issued', 'Draft', 'Paid', 'Cancelled'] : ['granted', 'pending']"
              :label="rt('filters.status')"
              clearable
            />
          </VCol>
        </VRow>
        <VBtn
          color="primary"
          prepend-icon="tabler-player-play"
          :loading="loading"
          block
          @click="runAnalysis"
        >
          {{ rt('filters.runAnalysis') }}
        </VBtn>
      </DataTableFilterMenu>
    </div>

    <div
      v-if="loading && !report"
      class="text-center py-16"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="48"
      />
    </div>

    <article
      v-if="report"
      class="report-result"
    >
      <VRow
        g="4"
        class="mb-1"
      >
        <VCol
          v-for="metric in metrics"
          :key="metric.label"
          cols="12"
          sm="6"
          lg="3"
        >
          <VCard class="metric-card h-100 pa-5">
            <div class="d-flex justify-space-between ga-3">
              <div class="min-w-0">
                <p class="metric-label mb-2">
                  {{ metric.label }}
                </p><h3 class="text-h5 font-weight-bold text-truncate mb-1">
                  {{ metric.value }}
                </h3><p class="text-caption text-medium-emphasis text-truncate mb-0">
                  {{ metric.detail }}
                </p>
              </div><VAvatar
                :color="metric.color"
                variant="tonal"
                rounded="lg"
                size="40"
              >
                <VIcon
                  :icon="metric.icon"
                  size="21"
                />
              </VAvatar>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </article>
  </main>
</template>

<style scoped>
.reporting-studio { margin-inline: auto; }
.tracking-tight { letter-spacing: -.035em !important; }
.eyebrow,
.metric-label { color: rgba(var(--v-theme-on-surface), .56); font-size: .69rem; font-weight: 700; letter-spacing: .105em; text-transform: uppercase; }
.report-library { display: flex; gap: 12px; overflow-x: auto; overflow-y: hidden; padding-block-end: 2px; scrollbar-width: none; }
.report-library::-webkit-scrollbar,
.date-preset-toggle::-webkit-scrollbar { display: none; }
.report-type-card { flex: 0 0 auto; min-inline-size: 13rem; padding: 14px; color: inherit; text-align: start; border: 1px solid rgba(var(--v-theme-on-surface), .08); border-radius: 12px; background: rgb(var(--v-theme-surface)); transition: .18s ease; }
.report-type-card:hover { border-color: rgba(var(--v-theme-primary), .35); transform: translateY(-1px); }
.report-type-card.active { border-color: rgba(var(--v-theme-primary), .65); box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), .08); }
.metric-card { border: 1px solid rgba(var(--v-theme-on-surface), .065); box-shadow: 0 3px 16px rgba(20, 24, 40, .035) !important; }
.date-preset-toggle { display: flex; max-width: 100%; overflow-x: auto; overflow-y: hidden; padding-block-end: 2px; scrollbar-width: none; }
.date-preset-toggle :deep(.v-btn) { flex: 0 0 auto !important; min-width: max-content !important; padding-inline: 12px !important; white-space: nowrap; }
.min-w-0 { min-width: 0; }

@media (max-width: 599px) {
  .report-type-card { min-inline-size: 11rem; }
}
</style>
