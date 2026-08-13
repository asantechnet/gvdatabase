<script setup lang="ts">
import DashboardApi, { type DashboardData, type DashboardTask } from '@/api/dashboard'
import ApexCharts, { type ApexOptions } from 'apexcharts'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useTheme } from 'vuetify'

interface MicroMetric {
  key: string
  title: string
  value: string | number
  subtext: string
  trendColor: string
  sparklineData: number[]
  icon: string
}

const emptyDashboard = (): DashboardData => ({
  capabilities: { finance: false, attendance: false },
  currency: { code: 'USD', symbol: '$', decimal_places: 2 },
  summary: {
    monthly_income: null,
    previous_month_income: null,
    active_tasks: 0,
    nearing_completion_tasks: 0,
    completed_tasks_this_month: 0,
    overdue_tasks: 0,
    average_task_progress: 0,
    task_completion_rate: 0,
    attendance_rate: null,
    attendance_present: null,
    attendance_marked: null,
  },
  sparklines: { income: [], active_tasks: [], attendance: [] },
  weekly_activity: { labels: [], created: [], completed: [] },
  secondary_chart: { labels: [], rates: [] },
  tertiary_chart: { labels: [], values: [], current_value: 0 },
  active_tasks: [],
})

const { global } = useTheme()
const dashboard = ref<DashboardData>(emptyDashboard())
const isLoading = ref(false)
const error = ref('')

const velocityChart = ref<HTMLElement | null>(null)
const secondaryChart = ref<HTMLElement | null>(null)
const tertiaryChart = ref<HTMLElement | null>(null)
let velocityChartInstance: ApexCharts | null = null
let secondaryChartInstance: ApexCharts | null = null
let tertiaryChartInstance: ApexCharts | null = null

const browserLocale = typeof document === 'undefined' ? 'en' : document.documentElement.lang || 'en'
const formatNumber = (value: number, maximumFractionDigits = 1) => new Intl.NumberFormat(browserLocale, {
  maximumFractionDigits,
}).format(value)

const formatMoney = (value: number) => new Intl.NumberFormat(browserLocale, {
  style: 'currency',
  currency: dashboard.value.currency.code,
  minimumFractionDigits: dashboard.value.currency.decimal_places,
  maximumFractionDigits: dashboard.value.currency.decimal_places,
}).format(value)

const formatDateLabel = (value: string, options: Intl.DateTimeFormatOptions) => {
  const date = new Date(`${value}T00:00:00`)
  return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(browserLocale, options).format(date)
}

const incomeComparison = computed(() => {
  const current = dashboard.value.summary.monthly_income ?? 0
  const previous = dashboard.value.summary.previous_month_income ?? 0
  if (previous === 0)
    return translate(current > 0 ? 'pages.dashboard.metrics.newIncome' : 'pages.dashboard.metrics.noPreviousIncome')

  const change = ((current - previous) / previous) * 100
  return translate('pages.dashboard.metrics.incomeComparison', { change: `${change >= 0 ? '+' : ''}${formatNumber(change)}` })
})

const statCards = computed<MicroMetric[]>(() => [
  dashboard.value.capabilities.finance
    ? {
        key: 'income',
        title: translate('pages.dashboard.home.home.monthlyIncome'),
        value: formatMoney(dashboard.value.summary.monthly_income ?? 0),
        subtext: incomeComparison.value,
        trendColor: 'success',
        sparklineData: dashboard.value.sparklines.income,
        icon: 'tabler-currency-dollar',
      }
    : {
        key: 'completed',
        title: translate('pages.dashboard.home.home.completedThisMonth'),
        value: dashboard.value.summary.completed_tasks_this_month,
        subtext: translate('pages.dashboard.metrics.taskCompletionRate', { rate: formatNumber(dashboard.value.summary.task_completion_rate) }),
        trendColor: 'success',
        sparklineData: dashboard.value.sparklines.income,
        icon: 'tabler-circle-check',
      },
  {
    key: 'active',
    title: translate('pages.dashboard.home.home.activeTasks'),
    value: dashboard.value.summary.active_tasks,
    subtext: translate('pages.dashboard.metrics.nearingCompletion', { count: formatNumber(dashboard.value.summary.nearing_completion_tasks) }),
    trendColor: 'info',
    sparklineData: dashboard.value.sparklines.active_tasks,
    icon: 'tabler-list-check',
  },
  dashboard.value.capabilities.attendance
    ? {
        key: 'attendance',
        title: translate('pages.dashboard.home.home.attendanceRate'),
        value: `${formatNumber(dashboard.value.summary.attendance_rate ?? 0)}%`,
        subtext: translate('pages.dashboard.metrics.attendanceSummary', {
          present: formatNumber(dashboard.value.summary.attendance_present ?? 0),
          marked: formatNumber(dashboard.value.summary.attendance_marked ?? 0),
        }),
        trendColor: 'warning',
        sparklineData: dashboard.value.sparklines.attendance,
        icon: 'tabler-users',
      }
    : {
        key: 'overdue',
        title: translate('pages.dashboard.home.home.overdueTasks'),
        value: dashboard.value.summary.overdue_tasks,
        subtext: translate('pages.dashboard.metrics.averageProgress', { progress: formatNumber(dashboard.value.summary.average_task_progress) }),
        trendColor: 'warning',
        sparklineData: dashboard.value.sparklines.attendance,
        icon: 'tabler-alert-triangle',
      },
])

const secondaryTitle = computed(() => dashboard.value.capabilities.attendance
  ? translate('pages.dashboard.metrics.attendanceRate')
  : translate('pages.dashboard.metrics.taskCompletionRateTitle'))
const secondaryValue = computed(() => dashboard.value.capabilities.attendance
  ? dashboard.value.summary.attendance_rate ?? 0
  : dashboard.value.summary.task_completion_rate)
const tertiaryTitle = computed(() => dashboard.value.capabilities.finance
  ? translate('pages.dashboard.metrics.netCashFlow')
  : translate('pages.dashboard.metrics.averageTaskProgress'))
const tertiaryValue = computed(() => dashboard.value.capabilities.finance
  ? formatMoney(dashboard.value.tertiary_chart.current_value)
  : `${formatNumber(dashboard.value.tertiary_chart.current_value)}%`)

const generateSvgPath = (data: number[], width: number, height: number): string => {
  if (data.length === 0) return ''
  if (data.length === 1) return `M 0 ${height / 2} L ${width} ${height / 2}`
  const max = Math.max(...data)
  const min = Math.min(...data)
  const range = max - min || 1

  return data.map((value, index) => {
    const x = (index / (data.length - 1)) * width
    const y = height - ((value - min) / range) * (height - 4) - 2
    return `${index === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`
  }).join(' ')
}

const chartTheme = () => global.current.value.dark ? 'dark' : 'light'

const velocityOptions = (): ApexOptions => ({
  chart: { type: 'line', height: 300, toolbar: { show: false }, zoom: { enabled: false } },
  colors: ['#7367f0', '#00bad1'],
  series: [
    { name: translate('pages.dashboard.home.home.created'), data: dashboard.value.weekly_activity.created },
    { name: translate('common.statuses.completed'), data: dashboard.value.weekly_activity.completed },
  ],
  stroke: { width: 3, curve: 'smooth' },
  fill: { type: 'solid', opacity: 0.1 },
  xaxis: { categories: dashboard.value.weekly_activity.labels.map(value => formatDateLabel(value, { month: 'short', day: 'numeric' })) },
  grid: { borderColor: 'rgba(127, 127, 127, 0.15)' },
  tooltip: { theme: chartTheme() },
  legend: { show: false },
  noData: { text: translate('common.messages.noRecordsFound') },
})

const secondaryOptions = (): ApexOptions => ({
  chart: { type: 'bar', height: 100, sparkline: { enabled: true }, toolbar: { show: false } },
  colors: ['#28c76f'],
  series: [{ name: secondaryTitle.value, data: dashboard.value.secondary_chart.rates }],
  plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
  xaxis: { categories: dashboard.value.secondary_chart.labels.map(value => formatDateLabel(value, { weekday: 'short' })) },
  tooltip: { theme: chartTheme(), y: { formatter: (value: number) => `${formatNumber(value)}%` } },
})

const tertiaryOptions = (): ApexOptions => ({
  chart: { type: 'area', height: 80, sparkline: { enabled: true }, toolbar: { show: false } },
  colors: ['#00bad1'],
  series: [{ name: tertiaryTitle.value, data: dashboard.value.tertiary_chart.values }],
  stroke: { width: 2, curve: 'smooth' },
  fill: { type: 'gradient', gradient: { shadeIntensity: 0.1, opacityFrom: 0.3, opacityTo: 0, stops: [0, 90, 100] } },
  xaxis: {
    categories: dashboard.value.tertiary_chart.labels.map(value => dashboard.value.capabilities.finance
      ? formatDateLabel(value, { month: 'short' })
      : value),
  },
  tooltip: {
    theme: chartTheme(),
    y: {
      formatter: (value: number) => dashboard.value.capabilities.finance ? formatMoney(value) : `${formatNumber(value)}%`,
    },
  },
})

async function renderCharts() {
  velocityChartInstance?.destroy()
  secondaryChartInstance?.destroy()
  tertiaryChartInstance?.destroy()
  velocityChartInstance = null
  secondaryChartInstance = null
  tertiaryChartInstance = null
  await nextTick()

  if (velocityChart.value) {
    velocityChartInstance = new ApexCharts(velocityChart.value, velocityOptions())
    await velocityChartInstance.render()
  }
  if (secondaryChart.value) {
    secondaryChartInstance = new ApexCharts(secondaryChart.value, secondaryOptions())
    await secondaryChartInstance.render()
  }
  if (tertiaryChart.value) {
    tertiaryChartInstance = new ApexCharts(tertiaryChart.value, tertiaryOptions())
    await tertiaryChartInstance.render()
  }
}

async function loadDashboard() {
  isLoading.value = true
  error.value = ''
  try {
    dashboard.value = await DashboardApi.getData()
    await renderCharts()
  }
  catch (caught: any) {
    error.value = caught?.message || translate('pages.dashboard.metrics.unableToLoad')
  }
  finally {
    isLoading.value = false
  }
}

const taskStatus = (task: DashboardTask) => translateBackendValue(task.status)

onMounted(loadDashboard)
watch(() => global.current.value.dark, () => { void renderCharts() })
onBeforeUnmount(() => {
  velocityChartInstance?.destroy()
  secondaryChartInstance?.destroy()
  tertiaryChartInstance?.destroy()
})
</script>

<template>
  <div class="software-agency-dashboard pa-6">
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold text-high-emphasis">{{ $t('pages.dashboard.home.home.dashboardOverview') }}</h1>
      <p class="text-subtitle-1 text-medium-emphasis mt-1">{{ $t('pages.dashboard.home.home.keyBusinessAndTaskMetricsAtAGlance') }}</p>
    </div>

    <VProgressLinear v-if="isLoading" indeterminate color="primary" class="mb-4" />
    <VAlert v-if="error" type="error" variant="tonal" class="mb-4">
      <div class="d-flex align-center justify-space-between ga-3">
        <span>{{ error }}</span>
        <VBtn size="small" variant="text" @click="loadDashboard">{{ $t('common.actions.retry') }}</VBtn>
      </div>
    </VAlert>

    <!-- Stats Cards -->
    <VRow class="mb-6" g="4">
      <VCol v-for="metric in statCards" :key="metric.key" cols="12" sm="6" md="4">
        <VCard class="pa-4 h-100" :style="`background: ${global.current.value.dark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.03)'}`">
          <div class="d-flex flex-column h-100 justify-space-between">
            <div class="d-flex justify-space-between align-start w-100 mb-2">
              <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                {{ metric.title }}
              </span>
              <VIcon :icon="metric.icon" size="20" class="text-medium-emphasis" />
            </div>

            <div class="d-flex align-center justify-space-between mt-auto pt-1">
              <div class="flex-grow-1 pr-2">
                <h3 class="text-h5 font-weight-bold text-high-emphasis">{{ metric.value }}</h3>
                <div class="text-caption font-weight-medium text-medium-emphasis mt-1">
                  {{ metric.subtext }}
                </div>
              </div>

              <div class="sparkline-container">
                <svg class="sparkline-svg" viewBox="0 0 75 32" width="75" height="32">
                  <path
                    :d="generateSvgPath(metric.sparklineData, 75, 32)"
                    fill="none"
                    :stroke="`rgba(var(--v-theme-${metric.trendColor}), 1)`"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </div>
            </div>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Main Chart Row -->
    <VRow class="match-height">
      <VCol cols="12" lg="8">
        <VCard class="pa-5 h-100">
          <div class="d-flex justify-space-between align-center mb-4">
            <div>
              <VCardTitle class="text-h6 font-weight-bold pa-0">{{ $t('pages.dashboard.home.home.taskActivity') }}</VCardTitle>
              <VCardSubtitle class="text-caption text-medium-emphasis pa-0 mt-0">
                {{ $t('pages.dashboard.home.home.tasksCreatedAndCompletedDuringTheLastSevenWeeks') }}
              </VCardSubtitle>
            </div>
            <div class="d-flex gap-2">
              <VChip size="small" variant="tonal" color="primary" class="font-weight-medium">{{ $t('pages.dashboard.home.home.created') }}</VChip>
              <VChip size="small" variant="text" color="info" class="font-weight-medium">{{ $t('common.statuses.completed') }}</VChip>
            </div>
          </div>

          <div class="chart-container">
            <div ref="velocityChart" style="height: 300px;"></div>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" lg="4" class="d-flex flex-column gap-4">
        <VCard class="pa-4 flex-grow-1">
          <div class="d-flex justify-space-between align-start mb-2">
            <div>
              <div class="text-caption text-uppercase text-medium-emphasis font-weight-medium">{{ secondaryTitle }}</div>
              <div class="text-h6 font-weight-bold mt-1 text-high-emphasis">{{ formatNumber(secondaryValue) }}%</div>
            </div>
            <VChip size="x-small" :color="secondaryValue >= 75 ? 'success' : 'warning'" class="font-weight-bold" variant="tonal">
              {{ $t(secondaryValue >= 75 ? 'pages.dashboard.healthy' : 'pages.dashboard.needsAttention') }}
            </VChip>
          </div>
          <div class="chart-container">
            <div ref="secondaryChart" style="height: 100px;"></div>
          </div>
        </VCard>

        <VCard class="pa-4 flex-grow-1 api-response-card">
          <div class="d-flex justify-space-between align-start mb-1">
            <div>
              <div class="text-caption text-uppercase text-medium-emphasis font-weight-medium">{{ tertiaryTitle }}</div>
              <div class="text-h6 font-weight-bold mt-1 text-info">{{ tertiaryValue }}</div>
            </div>
            <span class="text-caption font-weight-bold text-success">{{ $t('pages.dashboard.home.home.currentMonth') }}</span>
          </div>
          <div class="chart-container">
            <div ref="tertiaryChart" style="height: 80px;"></div>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tasks Table -->
    <VRow class="mt-4">
      <VCol cols="12">
        <VCard>
          <VCardItem class="py-4">
            <VCardTitle class="text-h6 font-weight-bold">{{ $t('pages.dashboard.home.home.activeTasks') }}</VCardTitle>
            <VCardSubtitle class="text-caption text-medium-emphasis">
              {{ $t('pages.dashboard.home.home.currentTaskStatusAndProgress') }}
            </VCardSubtitle>
          </VCardItem>

          <div class="responsive-table-container">
            <VTable class="custom-dashboard-table">
              <thead>
                <tr>
                  <th class="text-uppercase text-xs font-weight-medium">{{ $t('pages.dashboard.home.home.task') }}</th>
                  <th class="text-uppercase text-xs font-weight-medium">{{ $t('pages.dashboard.home.home.board') }}</th>
                  <th class="text-uppercase text-xs font-weight-medium">{{ $t('pages.dashboard.home.home.progress') }}</th>
                  <th class="text-uppercase text-xs font-weight-medium">{{ $t('common.fields.status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="task in dashboard.active_tasks" :key="task.id">
                  <td class="font-weight-medium text-high-emphasis">{{ task.title }}</td>
                  <td class="text-medium-emphasis">{{ task.board }}</td>
                  <td style="width: 35%;">
                    <div class="d-flex align-center gap-3">
                      <VProgressLinear
                        :model-value="task.progress"
                        :color="task.color"
                        height="6"
                        rounded
                        class="flex-grow-1"
                      />
                      <span class="text-caption font-weight-medium">{{ task.progress }}%</span>
                    </div>
                  </td>
                  <td>
                    <VChip
                      :color="task.color"
                      size="small"
                      variant="tonal"
                      class="font-weight-medium"
                    >
                      {{ taskStatus(task) }}
                    </VChip>
                  </td>
                </tr>
                <tr v-if="!isLoading && dashboard.active_tasks.length === 0">
                  <td colspan="4" class="text-center text-medium-emphasis py-8">{{ $t('pages.dashboard.home.home.noDataAvailable') }}</td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
/* Stats Cards */
.sparkline-container {
  width: 75px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.sparkline-svg {
  overflow: visible;
}

/* Chart Container */
.chart-container {
  position: relative;
  width: 100%;
}

/* API Response Card with modern blue shadow */
.api-response-card {
  position: relative;
  overflow: visible;
}

.api-response-card::before {
  content: '';
  position: absolute;
  bottom: -8px;
  left: 0;
  right: 0;
  height: 12px;
  background: linear-gradient(135deg,
    rgba(var(--v-theme-info), 0.2) 0%,
    rgba(var(--v-theme-info), 0.1) 50%,
    rgba(var(--v-theme-info), 0) 100%);
  border-radius: 0 0 12px 12px;
  filter: blur(4px);
  z-index: 0;
}

.api-response-card > * {
  position: relative;
  z-index: 1;
}

/* Table Styling */
.responsive-table-container {
  overflow-x: auto;
  width: 100%;
  scrollbar-width: none;
}

.responsive-table-container::-webkit-scrollbar {
  display: none;
}

.custom-dashboard-table :deep(th) {
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
  background-color: rgba(var(--v-theme-surface), 0.5) !important;
  height: 48px !important;
  border-bottom: 1px solid rgba(var(--v-theme-border), 0.1) !important;
}

.custom-dashboard-table :deep(td) {
  height: 60px !important;
  border-bottom: 1px solid rgba(var(--v-theme-border), 0.05) !important;
}

.custom-dashboard-table :deep(tr:hover td) {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}
</style>
