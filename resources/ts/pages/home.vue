<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'
import { formatLocalizedDate, formatLocalizedNumber } from '@/utils/localization'
import DashboardApi, { type DashboardData, type DashboardTask } from '@/api/dashboard'

interface ExecutiveMetric {
  key: string
  label: string
  value: string
  context: string
  icon: string
  color: 'primary' | 'success' | 'warning' | 'error' | 'info'
  trend: number[]
}

const emptyDashboard = (): DashboardData => ({
  capabilities: {
    finance: false,
    attendance: false,
    customers: false,
    asset: false,
    employees: false,
    contracts: false,
    payroll: false,
    visitors: false,
    services: false,
  },
  currency: { code: 'USD', symbol: '$', decimal_places: 2 },
  summary: {
    monthly_income: null,
    previous_month_income: null,
    finance_bases: { display_base: 'USD', monthly_income: {}, previous_month_income: {} },
    active_tasks: 0,
    nearing_completion_tasks: 0,
    completed_tasks_this_month: 0,
    overdue_tasks: 0,
    average_task_progress: 0,
    task_completion_rate: 0,
    attendance_rate: null,
    attendance_present: null,
    attendance_marked: null,
    monthly_expenses: null,
    monthly_net_cash: null,
    applicant_count: null,
    new_applicants_this_month: null,
    employee_count: null,
    asset_asset_value: null,
  },
  sparklines: { income: [], active_tasks: [], attendance: [] },
  weekly_activity: { labels: [], created: [], completed: [] },
  secondary_chart: { labels: [], rates: [] },
  tertiary_chart: { labels: [], values: [], current_value: 0 },
  active_tasks: [],
  financial_chart: null,
  expense_breakdown: [],
  top_applicants: [],
  asset_health: null,
  team_health: null,
  attention_items: [],
  commercial_overview: {
    applicants: null,
    new_applicants_this_month: null,
    active_services: null,
    service_deadlines_30_days: null,
    active_contracts: null,
    expiring_contracts_30_days: null,
    draft_contracts: null,
    visitors_this_month: null,
  },
  people_overview: {
    employees: null,
    present_today: null,
    attendance_marked_today: null,
    unmarked_today: null,
    late_minutes_this_month: null,
    overtime_minutes_this_month: null,
    pending_leave_requests: null,
    latest_payroll_period: null,
    latest_payroll_total: null,
  },
  kpi_trends: { labels: [], applicants: [], payroll: [], visitors: [], completedTasks: [] },
  business_trend: null,
  service_portfolio: [],
  task_mix: [],
  executive_actions: [],
})

const { global } = useTheme()
const { locale } = useI18n()
const isRtl = computed(() => ['fa-AF', 'ps-AF'].includes(locale.value))
const t = (key: string, values?: Record<string, unknown>) => translate(`pages.dashboard.executive.${key}`, values)
const dashboard = ref<DashboardData>(emptyDashboard())
const isLoading = ref(false)
const error = ref('')
const refreshedAt = ref<Date | null>(null)
const selectedPeriod = ref<'1m' | '3m' | '1y' | '2y' | 'custom'>('1m')
const customStartDate = ref('')
const customEndDate = ref('')

const periodOptions = [
  { titleKey: 'filters.oneMonth', value: '1m' as const },
  { titleKey: 'filters.threeMonths', value: '3m' as const },
  { titleKey: 'filters.oneYear', value: '1y' as const },
  { titleKey: 'filters.twoYears', value: '2y' as const },
]

const formatNumber = (value: number | null | undefined, digits = 0) => formatLocalizedNumber(value ?? 0, { maximumFractionDigits: digits })

const formatMoney = (value: number | null | undefined) => new Intl.NumberFormat(locale.value, {
  style: 'currency',
  currency: dashboard.value.currency.code,
  minimumFractionDigits: dashboard.value.currency.decimal_places,
  maximumFractionDigits: dashboard.value.currency.decimal_places,
}).format(value ?? 0)

const formatCompactMoney = (value: number | null | undefined) => new Intl.NumberFormat(locale.value, {
  style: 'currency', currency: dashboard.value.currency.code, notation: 'compact', maximumFractionDigits: 1,
}).format(value ?? 0)

const formatDate = (value: string | null, options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' }) => {
  if (!value)
    return t('common.notSet')
  const date = new Date(`${value}T00:00:00`)

  return Number.isNaN(date.getTime()) ? value : formatLocalizedDate(date, options)
}

const formatPeriod = (value: string | null) => value
  ? formatLocalizedDate(new Date(`${value}-01T00:00:00`), { month: 'short', year: 'numeric' })
  : t('common.noPayroll')

const formatMinutes = (value: number | null) => {
  const minutes = value ?? 0
  if (minutes < 60)
    return t('common.minutesShort', { value: formatNumber(minutes) })

  return t('common.hoursShort', { value: formatNumber(minutes / 60, 1) })
}

const executiveMetrics = computed<ExecutiveMetric[]>(() => {
  if (!dashboard.value.capabilities.finance) {
    return [
      { key: 'active', label: t('metrics.activeWork'), value: formatNumber(dashboard.value.summary.active_tasks), context: t('metrics.nearingCompletion', { count: formatNumber(dashboard.value.summary.nearing_completion_tasks) }), icon: 'tabler-list-check', color: 'primary', trend: dashboard.value.sparklines.active_tasks },
      { key: 'completed', label: t('metrics.completedThisMonth'), value: formatNumber(dashboard.value.summary.completed_tasks_this_month), context: t('metrics.completionRate', { rate: formatNumber(dashboard.value.summary.task_completion_rate, 1) }), icon: 'tabler-circle-check', color: 'success', trend: dashboard.value.kpi_trends.completedTasks },
      { key: 'overdue', label: t('metrics.overdueWork'), value: formatNumber(dashboard.value.summary.overdue_tasks), context: t('metrics.pastDueDates'), icon: 'tabler-calendar-exclamation', color: 'error', trend: dashboard.value.weekly_activity.created },
      { key: 'progress', label: t('metrics.averageProgress'), value: `${formatNumber(dashboard.value.summary.average_task_progress, 1)}%`, context: t('metrics.accessibleWork'), icon: 'tabler-progress', color: 'info', trend: dashboard.value.secondary_chart.rates },
    ]
  }

  const cards: ExecutiveMetric[] = [
    { key: 'customers', label: t('metrics.customerBase'), value: formatNumber(dashboard.value.commercial_overview.applicants), context: t('metrics.activeCommitments', { count: formatNumber(dashboard.value.commercial_overview.active_services) }), icon: 'tabler-building-store', color: 'primary', trend: dashboard.value.kpi_trends.applicants },
  ]

  cards.push(dashboard.value.capabilities.payroll
    ? { key: 'payroll', label: t('metrics.latestPayroll'), value: formatCompactMoney(dashboard.value.people_overview.latest_payroll_total), context: t('metrics.payrollContext', { period: formatPeriod(dashboard.value.people_overview.latest_payroll_period), count: formatNumber(dashboard.value.people_overview.employees) }), icon: 'tabler-cash-banknote', color: 'info', trend: dashboard.value.kpi_trends.payroll }
    : { key: 'visitors', label: t('metrics.visitorsThisMonth'), value: formatNumber(dashboard.value.commercial_overview.visitors_this_month), context: t('metrics.newCustomers', { count: formatNumber(dashboard.value.commercial_overview.new_applicants_this_month) }), icon: 'tabler-walk', color: 'info', trend: dashboard.value.kpi_trends.visitors })

  return cards
})

const colorHex: Record<string, string> = { primary: '#7367f0', success: '#28c76f', warning: '#ff9f43', error: '#ea5455', info: '#00bad1' }
const chartTextColor = computed(() => global.current.value.dark ? '#d4d2dc' : '#6d6b77')

const sparkValue = (metric: ExecutiveMetric, value: number) => {
  if (metric.key === 'payroll')
    return formatCompactMoney(value)
  if (metric.key === 'progress')
    return `${formatNumber(value, 1)}%`

  return formatNumber(value, 1)
}

const sparkOptions = (metric: ExecutiveMetric) => ({
  chart: { type: 'area', sparkline: { enabled: true }, animations: { enabled: true, speed: 350 } },
  colors: [colorHex[metric.color] ?? colorHex.primary],
  stroke: { curve: 'smooth', width: 2.5 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.34, opacityTo: 0.02, stops: [0, 95] } },
  dataLabels: { enabled: false },
  tooltip: {
    theme: global.current.value.dark ? 'dark' : 'light',
    x: { show: false },
    marker: { show: false },
    y: { formatter: (value: number) => sparkValue(metric, value), title: { formatter: () => '' } },
  },
})

const monthLabels = computed(() => dashboard.value.kpi_trends.labels.map(label => formatDate(label, { month: 'short' })))

const businessSeries = computed(() => dashboard.value.business_trend
  ? [
    { name: t('charts.collected'), type: 'line', data: dashboard.value.business_trend.collected },
    { name: t('charts.spent'), type: 'line', data: dashboard.value.business_trend.spent },
  ]
  : [])

const businessChartOptions = computed(() => ({
  chart: { type: 'line', toolbar: { show: false }, parentHeightOffset: 0, zoom: { enabled: false } },
  colors: [colorHex.success, colorHex.warning],
  stroke: { width: [3, 3], curve: 'smooth' },
  dataLabels: { enabled: false },
  fill: { opacity: [1, 1] },
  grid: { borderColor: 'rgba(var(--v-theme-on-surface), .10)', strokeDashArray: 5 },
  xaxis: { categories: monthLabels.value, axisBorder: { show: false }, axisTicks: { show: false }, crosshairs: { show: false }, tooltip: { enabled: false }, labels: { style: { colors: chartTextColor.value } } },
  yaxis: { labels: { style: { colors: chartTextColor.value }, formatter: (value: number) => formatCompactMoney(value) } },
  legend: { position: 'top', horizontalAlign: 'right', markers: { radius: 12 }, labels: { colors: chartTextColor.value } },
  tooltip: { shared: true, theme: global.current.value.dark ? 'dark' : 'light', y: { formatter: (value: number) => formatMoney(value) } },
  noData: { text: t('charts.noBillingActivity'), style: { color: chartTextColor.value } },
}))

const commercialActivitySeries = computed(() => [
  { name: t('charts.visitors'), data: dashboard.value.kpi_trends.visitors },
  { name: t('charts.newCustomers'), data: dashboard.value.kpi_trends.applicants },
])

const commercialActivityOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, parentHeightOffset: 0 },
  colors: [colorHex.info, colorHex.primary],
  plotOptions: { bar: { borderRadius: 5, columnWidth: '48%' } },
  dataLabels: { enabled: false },
  grid: { borderColor: 'rgba(var(--v-theme-on-surface), .10)', strokeDashArray: 5 },
  xaxis: { categories: monthLabels.value, axisBorder: { show: false }, axisTicks: { show: false }, crosshairs: { show: false }, tooltip: { enabled: false }, labels: { style: { colors: chartTextColor.value } } },
  yaxis: { min: 0, forceNiceScale: true, labels: { style: { colors: chartTextColor.value }, formatter: (value: number) => formatNumber(value) } },
  legend: { position: 'top', horizontalAlign: 'right', markers: { radius: 12 }, labels: { colors: chartTextColor.value } },
  tooltip: { theme: global.current.value.dark ? 'dark' : 'light' },
}))

const serviceSeries = computed(() => [{ name: t('charts.activeCustomers'), data: dashboard.value.service_portfolio.map(item => item.value) }])
const serviceAxisMax = computed(() => Math.max(1, ...dashboard.value.service_portfolio.map(item => item.value)))

const serviceOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, parentHeightOffset: 0 },
  colors: [colorHex.primary],
  plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '58%', distributed: true } },
  dataLabels: { enabled: false },
  grid: { show: false, padding: { top: -8, right: 8, bottom: -4, left: 8 } },
  legend: { show: false },
  xaxis: { categories: dashboard.value.service_portfolio.map(item => item.label), min: 0, max: serviceAxisMax.value, tickAmount: serviceAxisMax.value, decimalsInFloat: 0, labels: { style: { colors: chartTextColor.value }, formatter: (value: string) => formatNumber(Number(value)) }, axisBorder: { show: false }, axisTicks: { show: false } },
  yaxis: { opposite: isRtl.value, labels: { minWidth: 96, maxWidth: 112, align: isRtl.value ? 'right' : 'left', offsetX: 0, style: { colors: chartTextColor.value } } },
  tooltip: { theme: global.current.value.dark ? 'dark' : 'light' },
  noData: { text: t('charts.noActiveServices'), style: { color: chartTextColor.value } },
}))

const taskSeries = computed(() => dashboard.value.task_mix.map(item => item.value))

const taskOptions = computed(() => ({
  chart: { type: 'donut' },
  labels: dashboard.value.task_mix.map(item => translateBackendValue(item.label)),
  colors: ['#82868b', '#7367f0', '#ff9f43', '#ea5455'],
  stroke: { width: 0 },
  dataLabels: { enabled: false },
  legend: { position: 'bottom', markers: { radius: 12 }, labels: { colors: chartTextColor.value } },
  plotOptions: { pie: { donut: { size: '70%', labels: { show: true, name: { color: chartTextColor.value }, value: { color: chartTextColor.value }, total: { show: true, color: chartTextColor.value, label: t('charts.activeTasks'), formatter: () => formatNumber(dashboard.value.summary.active_tasks) } } } } },
  tooltip: { theme: global.current.value.dark ? 'dark' : 'light' },
}))

const attendanceRateToday = computed(() => {
  const marked = dashboard.value.people_overview.attendance_marked_today ?? 0
  const employees = dashboard.value.people_overview.employees ?? 0

  return employees > 0 ? Math.round((marked / employees) * 100) : 0
})

const severityColor = (severity: string) => severity === 'high' ? 'error' : severity === 'medium' ? 'warning' : 'info'
const taskStatus = (task: DashboardTask) => translateBackendValue(task.status)

const actionParams = (params: Record<string, string | number>) => ({
  ...params,
  ...(params.amount !== undefined ? { amount: formatMoney(Number(params.amount)) } : {}),
})

async function loadDashboard() {
  isLoading.value = true
  error.value = ''
  try {
    if (selectedPeriod.value === 'custom' && (!customStartDate.value || !customEndDate.value)) {
      error.value = t('filters.selectBothDates')

      return
    }
    dashboard.value = await DashboardApi.getData({
      period: selectedPeriod.value,
      ...(selectedPeriod.value === 'custom' ? { start_date: customStartDate.value, end_date: customEndDate.value } : {}),
    })
    refreshedAt.value = new Date()
  }
  catch (caught: any) {
    error.value = caught?.message || t('errors.load')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(loadDashboard)
</script>

<template>
  <main class="executive-dashboard erp-workspace">
    <ErpPageHeader :title="t('header.title')" section="dashboard" />
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div class="dashboard-controls d-flex flex-wrap align-center justify-end ga-2">
        <div
          class="period-segments"
          role="group"
          :aria-label="t('filters.label')"
        >
          <button
            v-for="option in periodOptions"
            :key="option.value"
            type="button"
            class="period-segment"
            :class="{ 'period-segment--active': selectedPeriod === option.value }"
            @click="selectedPeriod = option.value; loadDashboard()"
          >
            {{ t(option.titleKey) }}
          </button>
        </div>
        <ErpDateRange v-model:from="customStartDate" v-model:to="customEndDate" @apply="selectedPeriod = 'custom'; loadDashboard()" />
        <span
          v-if="refreshedAt"
          class="text-caption text-medium-emphasis d-none d-sm-inline"
        >{{ t('header.updated', { time: formatLocalizedDate(refreshedAt, 'time') }) }}</span>
        <VBtn
          class="dashboard-refresh-button"
          variant="flat"
          size="small"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="isLoading"
          @click="loadDashboard"
        >
          {{ t('header.refresh') }}
        </VBtn>
      </div>
    </div>

    <VProgressLinear
      v-if="isLoading"
      indeterminate
      color="primary"
      class="mb-5"
    />
    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mb-5"
      closable
      @click:close="error = ''"
    >
      {{ error }}
    </VAlert>

    <VRow
      g="4"
      class="mb-2"
    >
      <VCol
        v-for="metric in executiveMetrics"
        :key="metric.key"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard class="kpi-card h-100">
          <VCardText class="pa-5 pb-0">
            <div class="d-flex align-start justify-space-between ga-3">
              <div class="min-w-0">
                <p class="kpi-label mb-2">
                  {{ metric.label }}
                </p>
                <h2 class="text-h4 font-weight-bold text-high-emphasis text-truncate mb-1">
                  {{ metric.value }}
                </h2>
                <p class="text-caption text-medium-emphasis mb-0 text-truncate">
                  {{ metric.context }}
                </p>
              </div>
              <VAvatar
                :color="metric.color"
                variant="tonal"
                rounded="lg"
                size="42"
              >
                <VIcon
                  :icon="metric.icon"
                  size="22"
                />
              </VAvatar>
            </div>
          </VCardText>
          <VueApexCharts
            type="area"
            height="62"
            :options="sparkOptions(metric)"
            :series="[{ name: metric.label, data: metric.trend }]"
          />
        </VCard>
      </VCol>
    </VRow>

    <section
      v-if="dashboard.capabilities.finance"
      class="answer-strip mt-5 mb-5 pa-5 rounded-lg"
    >
      <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-4">
        <div>
          <p class="section-eyebrow mb-1">
            {{ t('answers.eyebrow') }}
          </p><h2 class="text-h5 font-weight-bold mb-0">
            {{ t('answers.title') }}
          </h2>
        </div>
        <VChip
          size="small"
          color="primary"
          variant="tonal"
        >
          {{ t('answers.liveData') }}
        </VChip>
      </div>
      <VRow g="0">
        <VCol
          cols="12"
          sm="6"
          lg="3"
          class="answer-cell pa-3"
        >
          <div class="d-flex ga-3">
            <VIcon
              icon="tabler-heart-handshake"
              color="primary"
            /><div>
              <p class="text-caption text-medium-emphasis mb-1">
                {{ t('answers.customersQuestion') }}
              </p><p class="font-weight-bold mb-1">
                {{ t('answers.liveCommitments', { count: formatNumber(dashboard.commercial_overview.active_services) }) }}
              </p><p class="text-caption text-medium-emphasis mb-0">
                {{ t('answers.customerContext', { customers: formatNumber(dashboard.commercial_overview.applicants), due: formatNumber(dashboard.commercial_overview.service_deadlines_30_days) }) }}
              </p>
            </div>
          </div>
        </VCol>
        <VCol
          cols="12"
          sm="6"
          lg="3"
          class="answer-cell pa-3"
        >
          <div class="d-flex ga-3">
            <VIcon
              icon="tabler-users"
              color="success"
            /><div>
              <p class="text-caption text-medium-emphasis mb-1">
                {{ t('answers.teamQuestion') }}
              </p><p class="font-weight-bold mb-1">
                {{ t('answers.markedToday', { rate: formatNumber(attendanceRateToday) }) }}
              </p><p class="text-caption text-medium-emphasis mb-0">
                {{ t('answers.attendanceMissing', { count: formatNumber(dashboard.people_overview.unmarked_today) }) }}
              </p>
            </div>
          </div>
        </VCol>
        <VCol
          cols="12"
          sm="6"
          lg="3"
          class="answer-cell pa-3"
        >
          <div class="d-flex ga-3">
            <VIcon
              icon="tabler-target-arrow"
              color="error"
            /><div>
              <p class="text-caption text-medium-emphasis mb-1">
                {{ t('answers.deliveryQuestion') }}
              </p><p class="font-weight-bold mb-1">
                {{ t('answers.overdueCommitments', { count: formatNumber(dashboard.summary.overdue_tasks) }) }}
              </p><p class="text-caption text-medium-emphasis mb-0">
                {{ t('answers.nearingDone', { count: formatNumber(dashboard.summary.nearing_completion_tasks) }) }}
              </p>
            </div>
          </div>
        </VCol>
      </VRow>
    </section>

    <VRow
      v-if="dashboard.capabilities.finance"
      g="4"
      class="mt-1"
    >
      <VCol
        cols="12"
        lg="12"
      >
        <VCard class="h-100">
          <VCardItem class="px-5 pt-5 pb-0">
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
              >
                <VIcon icon="tabler-chart-histogram" />
              </VAvatar>
            </template>
            <VCardTitle class="font-weight-bold">
              {{ t('sections.cashMovement') }}
            </VCardTitle>
            <VCardSubtitle>{{ t('sections.cashMovementSubtitle') }}</VCardSubtitle>
          </VCardItem>
          <VCardText class="pt-2">
            <VueApexCharts
              type="line"
              height="330"
              :options="businessChartOptions"
              :series="businessSeries"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow
      g="4"
      class="mt-1"
    >
      <VCol
        v-if="dashboard.capabilities.visitors || dashboard.capabilities.customers"
        cols="12"
        lg="6"
      >
        <VCard class="h-100">
          <VCardItem class="px-5 pt-5 pb-0">
            <VCardTitle class="font-weight-bold">
              {{ t('sections.commercialMomentum') }}
            </VCardTitle><VCardSubtitle>{{ t('sections.commercialMomentumSubtitle') }}</VCardSubtitle>
          </VCardItem>
          <VCardText class="pt-2">
            <VueApexCharts
              type="bar"
              height="290"
              :options="commercialActivityOptions"
              :series="commercialActivitySeries"
            />
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        v-if="dashboard.capabilities.services"
        cols="12"
        lg="6"
      >
        <VCard class="h-100">
          <VCardItem class="px-5 pt-5 pb-0">
            <VCardTitle class="font-weight-bold">
              {{ t('sections.servicePortfolio') }}
            </VCardTitle><VCardSubtitle>{{ t('sections.servicePortfolioSubtitle') }}</VCardSubtitle>
          </VCardItem>
          <VCardText class="pt-3">
            <VueApexCharts
              type="bar"
              height="280"
              :options="serviceOptions"
              :series="serviceSeries"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow
      g="4"
      class="mt-1"
    >
      <VCol
        cols="12"
        lg="8"
      >
        <VCard class="h-100">
          <VCardItem class="px-5 pt-5 pb-3">
            <div class="d-flex align-center justify-space-between ga-3">
              <div>
                <VCardTitle class="font-weight-bold pa-0">
                  {{ t('sections.actionCenter') }}
                </VCardTitle><VCardSubtitle class="pa-0">
                  {{ t('sections.actionCenterSubtitle') }}
                </VCardSubtitle>
              </div><VChip
                :color="dashboard.executive_actions.length ? 'error' : 'success'"
                size="small"
                variant="tonal"
              >
                {{ t('actions.count', { count: formatNumber(dashboard.executive_actions.length) }) }}
              </VChip>
            </div>
          </VCardItem>
          <div
            v-if="dashboard.executive_actions.length"
            class="action-list px-5 pb-4"
          >
            <div
              v-for="action in dashboard.executive_actions"
              :key="`${action.areaKey}-${action.titleKey}`"
              class="action-row d-flex align-center ga-3 py-3"
            >
              <VAvatar
                :color="severityColor(action.severity)"
                variant="tonal"
                rounded="lg"
                size="40"
              >
                <VIcon
                  :icon="action.icon"
                  size="21"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-center ga-2">
                  <span class="text-body-2 font-weight-bold">{{ t(`actions.${action.titleKey}`, actionParams(action.params)) }}</span><VChip
                    :color="severityColor(action.severity)"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ t(`actions.areas.${action.areaKey}`) }}
                  </VChip>
                </div><p class="text-caption text-medium-emphasis mb-0 text-truncate">
                  {{ t(`actions.${action.detailKey}`, actionParams(action.params)) }}
                </p>
              </div>
              <VBtn
                :to="action.route"
                :aria-label="t('actions.open')"
                icon="tabler-arrow-up-right"
                size="small"
                variant="text"
                color="secondary"
              />
            </div>
          </div>
          <div
            v-else
            class="empty-state text-center pa-8"
          >
            <VAvatar
              color="success"
              variant="tonal"
              size="50"
            >
              <VIcon icon="tabler-circle-check" />
            </VAvatar><h3 class="text-h6 mt-3 mb-1">
              {{ t('actions.emptyTitle') }}
            </h3><p class="text-body-2 text-medium-emphasis mb-0">
              {{ t('actions.emptyBody') }}
            </p>
          </div>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <VCard class="h-100">
          <VCardItem class="px-5 pt-5 pb-0">
            <VCardTitle class="font-weight-bold">
              {{ t('sections.workloadRisk') }}
            </VCardTitle><VCardSubtitle>{{ t('sections.workloadRiskSubtitle') }}</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <VueApexCharts
              type="donut"
              height="270"
              :options="taskOptions"
              :series="taskSeries"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow
      g="4"
      class="mt-1"
    >
      <VCol
        v-if="dashboard.capabilities.contracts"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard class="domain-card h-100 pa-5">
          <div class="d-flex justify-space-between">
            <div>
              <p class="kpi-label mb-2">
                {{ t('domains.contractCoverage') }}
              </p><h3 class="text-h5 font-weight-bold">
                {{ t('domains.activeCount', { count: formatNumber(dashboard.commercial_overview.active_contracts) }) }}
              </h3>
            </div><VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
            >
              <VIcon icon="tabler-signature" />
            </VAvatar>
          </div><VDivider class="my-4" /><div class="d-flex justify-space-between text-caption">
            <span class="text-medium-emphasis">{{ t('domains.expiring30Days') }}</span><strong>{{ formatNumber(dashboard.commercial_overview.expiring_contracts_30_days) }}</strong>
          </div><div class="d-flex justify-space-between text-caption mt-2">
            <span class="text-medium-emphasis">{{ t('domains.drafts') }}</span><strong>{{ formatNumber(dashboard.commercial_overview.draft_contracts) }}</strong>
          </div>
        </VCard>
      </VCol>
      <VCol
        v-if="dashboard.people_overview.employees !== null"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard class="domain-card h-100 pa-5">
          <div class="d-flex justify-space-between">
            <div>
              <p class="kpi-label mb-2">
                {{ t('domains.peopleReadiness') }}
              </p><h3 class="text-h5 font-weight-bold">
                {{ t('domains.employeeCount', { count: formatNumber(dashboard.people_overview.employees) }) }}
              </h3>
            </div><VAvatar
              color="success"
              variant="tonal"
              rounded="lg"
            >
              <VIcon icon="tabler-users-group" />
            </VAvatar>
          </div><VDivider class="my-4" /><div class="d-flex justify-space-between text-caption">
            <span class="text-medium-emphasis">{{ t('domains.pendingLeave') }}</span><strong>{{ formatNumber(dashboard.people_overview.pending_leave_requests) }}</strong>
          </div><div class="d-flex justify-space-between text-caption mt-2">
            <span class="text-medium-emphasis">{{ t('domains.overtimeMonth') }}</span><strong>{{ formatMinutes(dashboard.people_overview.overtime_minutes_this_month) }}</strong>
          </div>
        </VCard>
      </VCol>
      <VCol
        v-if="dashboard.asset_health"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard class="domain-card h-100 pa-5">
          <div class="d-flex justify-space-between">
            <div>
              <p class="kpi-label mb-2">
                {{ t('domains.assetControl') }}
              </p><h3 class="text-h5 font-weight-bold">
                {{ formatMoney(dashboard.summary.asset_asset_value) }}
              </h3>
            </div><VAvatar
              color="warning"
              variant="tonal"
              rounded="lg"
            >
              <VIcon icon="tabler-packages" />
            </VAvatar>
          </div><VDivider class="my-4" /><div class="d-flex justify-space-between text-caption">
            <span class="text-medium-emphasis">{{ t('domains.availableAssigned') }}</span><strong>{{ dashboard.asset_health.available_items }} / {{ dashboard.asset_health.assigned_items }}</strong>
          </div><div class="d-flex justify-space-between text-caption mt-2">
            <span class="text-medium-emphasis">{{ t('domains.needInspection') }}</span><strong :class="dashboard.asset_health.attention_items ? 'text-warning' : ''">{{ dashboard.asset_health.attention_items }}</strong>
          </div>
        </VCard>
      </VCol>
      <VCol
        v-if="dashboard.capabilities.visitors"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard class="domain-card h-100 pa-5">
          <div class="d-flex justify-space-between">
            <div>
              <p class="kpi-label mb-2">
                {{ t('domains.frontDoor') }}
              </p><h3 class="text-h5 font-weight-bold">
                {{ t('domains.visitorCount', { count: formatNumber(dashboard.commercial_overview.visitors_this_month) }) }}
              </h3>
            </div><VAvatar
              color="info"
              variant="tonal"
              rounded="lg"
            >
              <VIcon icon="tabler-walk" />
            </VAvatar>
          </div><VDivider class="my-4" /><div class="d-flex justify-space-between text-caption">
            <span class="text-medium-emphasis">{{ t('domains.newCustomers') }}</span><strong>{{ formatNumber(dashboard.commercial_overview.new_applicants_this_month) }}</strong>
          </div><div class="d-flex justify-space-between text-caption mt-2">
            <span class="text-medium-emphasis">{{ t('domains.activeServices') }}</span><strong>{{ formatNumber(dashboard.commercial_overview.active_services) }}</strong>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <VRow
      g="4"
      class="mt-1"
    >
      <VCol
        cols="12"
        lg="12"
      >
        <VCard class="h-100">
          <VCardItem class="px-5 pt-5">
            <div class="d-flex justify-space-between">
              <div>
                <VCardTitle class="font-weight-bold pa-0">
                  {{ t('delivery.title') }}
                </VCardTitle><VCardSubtitle class="pa-0">
                  {{ t('delivery.subtitle') }}
                </VCardSubtitle>
              </div><VBtn
                to="/tasks"
                variant="text"
                size="small"
                append-icon="tabler-arrow-right"
              >
                {{ t('delivery.allTasks') }}
              </VBtn>
            </div>
          </VCardItem>
          <div class="px-5 pb-4">
            <div
              v-for="task in dashboard.active_tasks.slice(0, 5)"
              :key="task.id"
              class="task-row py-3"
            >
              <div class="d-flex align-center justify-space-between ga-3 mb-2">
                <div class="min-w-0">
                  <p class="text-body-2 font-weight-bold text-truncate mb-0">
                    {{ task.title }}
                  </p><p class="text-caption text-medium-emphasis mb-0">
                    {{ task.board || t('delivery.noBoard') }}
                  </p>
                </div><VChip
                  :color="task.color"
                  size="x-small"
                  variant="tonal"
                >
                  {{ taskStatus(task) }}
                </VChip>
              </div><div class="d-flex align-center ga-3">
                <VProgressLinear
                  :model-value="task.progress"
                  :color="task.color"
                  height="6"
                  rounded
                /><span class="text-caption font-weight-bold">{{ task.progress }}%</span>
              </div>
            </div><p
              v-if="!dashboard.active_tasks.length"
              class="text-center text-medium-emphasis py-8 mb-0"
            >
              {{ t('delivery.empty') }}
            </p>
          </div>
        </VCard>
      </VCol>
    </VRow>
  </main>
</template>

<style scoped>
.executive-dashboard { margin-inline: auto; }
.dashboard-controls { margin-inline-start: auto; }
.period-segments { display: grid; grid-template-columns: repeat(4, minmax(76px, 1fr)); gap: 3px; padding: 3px; border: 1px solid rgba(var(--v-theme-on-surface), .14); border-radius: 12px; background: rgba(var(--v-theme-on-surface), .045); }
.period-segment { min-height: 34px; padding: 6px 12px; border: 0; border-radius: 8px; background: transparent; color: rgba(var(--v-theme-on-surface), .68); font: inherit; font-size: .78rem; font-weight: 650; line-height: 1.15; white-space: nowrap; cursor: pointer; transition: background .18s ease, color .18s ease, box-shadow .18s ease; }
.period-segment:hover { background: rgba(var(--v-theme-primary), .08); color: rgb(var(--v-theme-primary)); }
.period-segment--active { background: rgb(var(--v-theme-primary)); color: rgb(var(--v-theme-on-primary)); box-shadow: 0 2px 8px rgba(var(--v-theme-primary), .28); }
.dashboard-refresh-button { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.dashboard-refresh-button:hover, .dashboard-refresh-button:focus-visible { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.dashboard-refresh-button :deep(.v-icon), .dashboard-refresh-button.v-btn--disabled, .dashboard-refresh-button.v-btn--disabled :deep(.v-icon) { color: rgb(var(--v-theme-on-primary)) !important; }
.dashboard-range-card { border-radius: 14px; }
@media (max-width: 700px) { .dashboard-controls { justify-content: flex-start !important; margin-inline-start: 0; width: 100%; } .period-segments { grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; } .period-segment { white-space: normal; } }
.tracking-tight { letter-spacing: -.035em !important; }
.section-eyebrow, .kpi-label { color: rgba(var(--v-theme-on-surface), .56); font-size: .7rem; font-weight: 700; letter-spacing: .105em; line-height: 1.4; text-transform: uppercase; }
.kpi-card, .domain-card { border: 1px solid rgba(var(--v-theme-on-surface), .065); box-shadow: 0 3px 14px rgba(20, 24, 40, .035) !important; }
.answer-strip { border: 1px solid rgba(var(--v-theme-primary), .13); background: rgba(var(--v-theme-primary), .035); }
.answer-cell:not(:last-child) { border-inline-end: 1px solid rgba(var(--v-theme-on-surface), .085); }
.insight-note { display: flex; align-items: center; background: rgba(var(--v-theme-warning), .08); }
.action-row:not(:last-child), .task-row:not(:last-child) { border-bottom: 1px solid rgba(var(--v-theme-on-surface), .075); }
.executive-table :deep(th) { height: 46px; color: rgba(var(--v-theme-on-surface), .55); background: rgba(var(--v-theme-on-surface), .025); font-size: .69rem; font-weight: 700; letter-spacing: .075em; text-transform: uppercase; white-space: nowrap; }
.executive-table :deep(td) { height: 64px; }
.executive-table :deep(tr:last-child td) { border-bottom: 0; }
.min-w-0 { min-width: 0; }
@media (max-width: 1279px) { .answer-cell:nth-child(2) { border-inline-end: 0; } .answer-cell:nth-child(-n+2) { border-bottom: 1px solid rgba(var(--v-theme-on-surface), .085); } }
@media (max-width: 599px) { .answer-cell { border-inline-end: 0 !important; border-bottom: 1px solid rgba(var(--v-theme-on-surface), .085); } .answer-cell:last-child { border-bottom: 0; } }
</style>
