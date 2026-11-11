<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useTheme } from 'vuetify'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateFilterMenu from '@/components/erp/ErpDateFilterMenu.vue'
import AgencyOverviewSkeleton from '@/components/dashboard/AgencyOverviewSkeleton.vue'
import DashboardApi, { type EducationalDashboard, type EducationalDashboardParams } from '@/api/educationalDashboard'
import { useDisplayCurrency } from '@/composables/useDisplayCurrency'
import { canAccess } from '@/utils/authorization'
import { formatActiveMoney, translateApplicantStage, translateBackendValue } from '@/utils/localization'

const { t, n, locale } = useI18n()
const route = useRoute()
const theme = useTheme()
const toast = useToast()
const data = ref<EducationalDashboard | null>(null)
const loading = ref(false)
const failed = ref(false)
const appliedFilters = ref<EducationalDashboardParams>({})
const { currencies: displayCurrencies, loadCurrencies: loadDisplayCurrencies } = useDisplayCurrency()

// This can only evaluate to true in Vite development. Production builds replace
// `import.meta.env.DEV` with false, so the mock module is never loaded there.
const developmentMockMode = computed(() => import.meta.env.DEV && route.query.dashboardMock === '1')

async function loadDevelopmentDashboardMock(): Promise<EducationalDashboard | null> {
  if (!import.meta.env.DEV)
    return null

  // Keep this runtime-resolved for Vite development. `@vite-ignore` prevents
  // the fixture from becoming a production chunk; the DEV guard prevents this
  // import from ever running outside development.
  const modulePath = './dashboard/educationalDashboardMock.ts'
  const mockModule = await import(/* @vite-ignore */ modulePath) as typeof import('./dashboard/educationalDashboardMock')

  return mockModule.createEducationalDashboardMock()
}

const applicationLink = (extra: Record<string, string | number> = {}) => ({
  name: 'applications',
  query: {
    ...Object.fromEntries(Object.entries(appliedFilters.value).filter(([, value]) => value != null)),
    ...extra,
  },
})

const money = (rows: Array<{ amount: number; currency: string | null }>) =>
  rows.map(row => row.currency
    ? formatActiveMoney(row.amount, displayCurrencies.value.find(currency => currency.code === row.currency) ?? { code: row.currency })
    : `${n(row.amount, 'decimal')} ${t('agencyDashboard.unspecifiedCurrency')}`,
  ).join(' · ') || n(0)

const alertGroups = [
  { key: 'overdue_tasks' as const, label: () => t('agencyDashboard.overdueTasks'), icon: 'tabler-calendar-exclamation', tone: 'error' },
  { key: 'stuck_applicants' as const, label: () => t('agencyDashboard.stuckCasesShort'), icon: 'tabler-clock-exclamation', tone: 'warning' },
  { key: 'missing_documents' as const, label: () => t('agencyDashboard.missingDocuments'), icon: 'tabler-file-alert', tone: 'warning' },
  { key: 'contracts_requiring_attention' as const, label: () => t('agencyDashboard.contractAttentionShort'), icon: 'tabler-file-description', tone: 'error' },
] as const

// Draft records are actionable only for people who can actually approve or
// reject them. The API still returns the aggregate to all overview viewers,
// so this keeps the dashboard's action list permission-aware.
const canApproveFinanceActions = canAccess('approve_draft_finance_records')

const actionCount = computed(() => {
  if (!data.value)
    return 0

  return Object.values(data.value.attention.counts).reduce((total, count) => total + count, 0)
    + data.value.work.tasks.due_today
    + data.value.work.tasks.unassigned
    + data.value.operations.unassigned_cases
    + (canApproveFinanceActions ? data.value.attention.pending_approvals : 0)
})

const kpis = computed(() => {
  if (!data.value)
    return []

  return [
    {
      key: 'cases',
      label: t('educational.activeCasesLabel'),
      value: n(data.value.operations.active_cases),
      context: t('agencyDashboard.kpiUnassigned', { count: n(data.value.operations.unassigned_cases) }),
      icon: 'tabler-users-group',
      tone: 'primary',
      to: applicationLink({ status: 'active', date_from: '', date_to: '' }),
    },
    {
      key: 'actions',
      label: t('agencyDashboard.needsAction'),
      value: n(actionCount.value),
      context: t('agencyDashboard.kpiActionsHelp'),
      icon: 'tabler-bell-ringing',
      tone: actionCount.value > 0 ? 'warning' : 'success',
      href: '#agency-actions',
    },
    {
      key: 'receivables',
      label: t('agencyDashboard.toCollectShort'),
      value: money(data.value.operations.outstanding),
      context: t('agencyDashboard.kpiCollectionsHelp'),
      icon: 'tabler-wallet',
      tone: 'info',
      to: applicationLink({ focus: 'outstanding', date_from: '', date_to: '' }),
    },
    {
      key: 'offers',
      label: t('agencyDashboard.admittedShort'),
      value: n(data.value.operations.admissions.admitted),
      context: t('agencyDashboard.kpiWaiting', { count: n(data.value.operations.admissions.pending + data.value.operations.admissions.applied) }),
      icon: 'tabler-school',
      tone: 'success',
      to: applicationLink({ focus: 'admitted', date_from: '', date_to: '' }),
    },
  ]
})

const stageRows = computed(() => {
  const totals = new Map<string, { count: number; keys: string[] }>()
  for (const stage of data.value?.pipeline.stages ?? []) {
    const name = translateApplicantStage(stage)
    const previous = totals.get(name) ?? { count: 0, keys: [] }

    totals.set(name, { count: previous.count + stage.count, keys: [...previous.keys, stage.key] })
  }
  const maximum = Math.max(1, ...[...totals.values()].map(stage => stage.count))

  return [...totals.entries()].map(([name, stage], index) => ({
    name,
    count: stage.count,
    relative: Math.max(8, (stage.count / maximum) * 100),
    color: ['primary', 'info', 'teal', 'warning', 'success'][index % 5],
    route: applicationLink({ stage: stage.keys[0], date_from: '', date_to: '' }),
  }))
})

type ActionRow = {
  id: string
  title: string
  count: number
  route: { name: string; params?: Record<string, string | number>; query?: Record<string, string | number> }
  icon: string
  tone: 'error' | 'warning' | 'success' | 'info'
}

const actionRows = computed(() => {
  if (!data.value)
    return []

  const rows: ActionRow[] = alertGroups.map(group => {
    const hit = data.value!.attention[group.key][0]

    return {
      id: group.key,
      title: group.label(),
      count: data.value!.attention.counts[group.key],
      route: hit?.route ?? { name: 'applications' },
      icon: group.icon,
      tone: group.tone,
    }
  })

  const financeRows: ActionRow[] = []
  if (canApproveFinanceActions) {
    financeRows.push({
      id: 'pending',
      title: t('agencyDashboard.pendingApproval'),
      count: data.value.attention.pending_approvals,
      route: { name: 'income' },
      icon: 'tabler-circle-check',
      tone: 'warning',
    })
  }

  const operationalRows: ActionRow[] = [
    {
      id: 'tasks_due_today',
      title: t('agencyDashboard.tasksDueToday'),
      count: data.value.work.tasks.due_today,
      route: { name: 'tasks' },
      icon: 'tabler-calendar-due',
      tone: 'warning',
    },
    {
      id: 'unassigned_tasks',
      title: t('agencyDashboard.unassignedTasks'),
      count: data.value.work.tasks.unassigned,
      route: { name: 'tasks' },
      icon: 'tabler-user-question',
      tone: 'warning',
    },
    {
      id: 'unassigned_cases',
      title: t('agencyDashboard.unassignedCases'),
      count: data.value.operations.unassigned_cases,
      route: applicationLink({ status: 'active', responsible_employee_id: '' }),
      icon: 'tabler-user-exclamation',
      tone: 'warning',
    },
  ]

  return [...rows, ...operationalRows, ...financeRows]
    .filter(row => row.count > 0)
    .sort((a, b) => a.tone !== b.tone ? (a.tone === 'error' ? -1 : 1) : b.count - a.count)
})

const outstandingRows = computed(() => data.value?.operations.outstanding ?? [])

const financialRows = computed(() => {
  if (!data.value)
    return []

  return [
    { label: t('educational.postedIncome'), value: money(data.value.financial.income), icon: 'tabler-arrow-up-right', tone: 'success', to: canAccess('view_incomes') ? { name: 'income' } : undefined },
    { label: t('educational.postedExpenses'), value: money(data.value.financial.expenses), icon: 'tabler-arrow-down-right', tone: 'error', to: canAccess('view_expenses') ? { name: 'expenses' } : undefined },
    { label: t('agencyDashboard.payroll'), value: money(data.value.financial.salaries), icon: 'tabler-cash-banknote', tone: 'info' },
  ]
})

const admissionRows = computed(() => {
  const admissions = data.value?.operations.admissions
  if (!admissions)
    return []
  const colors = { pending: '#ffb547', applied: '#7367f0', admitted: '#28c76f', rejected: '#ea5455', withdrawn: '#8a8794' }

  return (Object.entries(admissions) as Array<[keyof typeof admissions, number]>).map(([status, count]) => ({
    status, label: translateBackendValue(status), count, color: colors[status],
  }))
})

const admissionTotal = computed(() => admissionRows.value.reduce((total, row) => total + row.count, 0))
const admissionSeries = computed(() => [{ name: t('agencyDashboard.applications'), data: admissionRows.value.map(row => row.count) }])
const admittedApplications = computed(() => data.value?.operations.admissions.admitted ?? 0)
const admissionSuccessRate = computed(() => admissionTotal.value > 0 ? Math.round((admittedApplications.value / admissionTotal.value) * 100) : 0)
const isRtl = computed(() => ['fa-AF', 'ps-AF'].includes(locale.value))

const admissionOptions = computed(() => {
  const dark = theme.global.current.value.dark
  const rtl = isRtl.value
  const text = dark ? '#d8d6e1' : '#625f6e'
  const muted = dark ? '#9794a5' : '#8c8997'

  return {
    chart: { type: 'bar', toolbar: { show: false }, animations: { enabled: true, speed: 450 }, parentHeightOffset: 0, fontFamily: 'inherit' },
    colors: admissionRows.value.map(row => row.color),
    plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 8, barHeight: '56%', dataLabels: { position: 'top' } } },
    dataLabels: {
      enabled: true,
      formatter: (value: number) => n(value),
      offsetX: rtl ? -8 : 8,
      style: { colors: [text], fontSize: '11px', fontWeight: 700 },
      background: { enabled: false },
    },
    fill: { type: 'gradient', gradient: { shade: dark ? 'dark' : 'light', type: 'horizontal', shadeIntensity: 0.18, opacityFrom: 0.95, opacityTo: 0.62, stops: [0, 100] } },
    stroke: { width: 0 },
    legend: { show: false },
    tooltip: { theme: dark ? 'dark' : 'light', y: { formatter: (value: number) => n(value) } },
    grid: { borderColor: dark ? 'rgba(216, 214, 225, .1)' : 'rgba(98, 95, 110, .1)', strokeDashArray: 4, padding: { top: -4, right: rtl ? 4 : 28, bottom: -4, left: rtl ? 28 : 4 } },
    xaxis: { categories: admissionRows.value.map(row => row.label), min: 0, tickAmount: 4, reversed: rtl, labels: { style: { colors: muted, fontSize: '10px' }, formatter: (value: string) => n(Number(value)) }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { opposite: rtl, labels: { style: { colors: text, fontSize: '11px', fontWeight: 650 }, maxWidth: 132 } },
  }
})

const staff = computed(() => [...(data.value?.staff ?? [])].sort((a, b) => b.active_applicants - a.active_applicants).slice(0, 6))
const destinations = computed(() => (data.value?.countries ?? []).slice(0, 6))
const universities = computed(() => (data.value?.universities ?? []).slice(0, 6))
const largestStaffWorkload = computed(() => Math.max(1, ...staff.value.map(row => row.active_applicants)))
const largestDestinationCount = computed(() => Math.max(1, ...destinations.value.map(row => row.active)))
const progressWidth = (value: number, maximum: number) => `${Math.max(0, Math.min(100, (value / maximum) * 100))}%`
const admissionRate = (admitted: number, applications: number) => applications > 0 ? Math.round((admitted / applications) * 100) : 0

let requestId = 0
async function load(range: EducationalDashboardParams = appliedFilters.value) {
  if (loading.value || (range.date_from && range.date_to && range.date_from > range.date_to))
    return
  const id = ++requestId

  loading.value = true
  failed.value = false
  try {
    if (developmentMockMode.value) {
      // Keep the fixture in a lazy development-only chunk. Real API data is
      // never requested, changed, or reused while visual mock mode is active.
      const mock = await loadDevelopmentDashboardMock()

      if (id === requestId && mock) {
        data.value = mock
        appliedFilters.value = { ...range }
      }

      return
    }

    const result = await DashboardApi.get(range)
    if (id === requestId) {
      data.value = result
      appliedFilters.value = { ...range }
    }
  }
  catch {
    if (id === requestId) {
      failed.value = true
      toast.error(t('educational.loadOverviewFailed'))
    }
  }
  finally {
    if (id === requestId)
      loading.value = false
  }
}

onMounted(() => {
  if (canAccess('view_currencies'))
    loadDisplayCurrencies().catch(() => undefined)
  load()
})

// Enables switching by adding or removing `?dashboardMock=1` without a full
// reload while remaining a no-op in production, where the computed value is
// permanently false.
watch(developmentMockMode, async () => {
  // Invalidate an in-flight request before starting the selected source.
  // The request-id guard then discards a late real or mock response.
  if (loading.value) {
    requestId++
    loading.value = false
  }

  await load()
})
</script>

<template>
  <main
    class="dashboard-home"
    :aria-busy="loading"
  >
    <ErpPageHeader
      :title="t('agencyDashboard.pageTitle')"
      section="dashboard"
      :breadcrumbs="false"
    >
      <template #actions>
        <ErpDateFilterMenu
          :from="appliedFilters.date_from"
          :to="appliedFilters.date_to"
          :disabled="loading"
          @apply="load"
        />
        <VBtn
          class="dashboard-button"
          size="small"
          prepend-icon="tabler-refresh"
          :disabled="loading"
          @click="load"
        >
          {{ t("agencyDashboard.refresh") }}
        </VBtn>
      </template>
    </ErpPageHeader>

    <AgencyOverviewSkeleton v-if="loading" />
    <VAlert
      v-else-if="failed"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ t("educational.loadOverviewFailed") }}
      <VBtn
        class="dashboard-button ms-2"
        size="small"
        @click="load"
      >
        {{ t("agencyDashboard.refresh") }}
      </VBtn>
    </VAlert>

    <div
      v-else-if="data"
      class="dashboard-content"
    >
      <section
        class="metric-grid"
        :aria-label="t('agencyDashboard.pageTitle')"
      >
        <component
          :is="metric.to ? RouterLink : 'a'"
          v-for="metric in kpis"
          :key="metric.key"
          class="metric-card"
          :class="[`metric-card--${metric.tone}`, `metric-card--${metric.key}`]"
          :to="metric.to"
          :href="metric.href"
        >
          <span
            class="metric-card__glow"
            aria-hidden="true"
          />
          <span class="metric-card__icon"><VIcon
            :icon="metric.icon"
            size="23"
          /></span>
          <span class="metric-card__copy"><small>{{ metric.label }}</small><strong>{{ metric.value }}</strong><span>{{ metric.context }}</span></span>
          <VIcon
            class="metric-card__arrow"
            icon="tabler-arrow-up-right"
            size="18"
          />
        </component>
      </section>

      <section class="dashboard-grid dashboard-grid--hero">
        <article class="dashboard-panel pipeline-panel">
          <header class="panel-header">
            <div><span class="panel-eyebrow">{{ t("agencyDashboard.overview") }}</span><h2>{{ t("agencyDashboard.caseProgressShort") }}</h2></div>
            <div class="pipeline-total">
              <strong>{{ n(data.pipeline.totals.active) }}</strong><span>{{ t("educational.activeCasesLabel") }}</span>
            </div>
          </header>
          <div
            v-if="stageRows.length"
            class="stage-journey"
          >
            <RouterLink
              v-for="(stage, index) in stageRows"
              :key="stage.name"
              :to="stage.route"
              class="stage-step"
              :class="`stage-step--${stage.color}`"
            >
              <span class="stage-step__index">{{ n(index + 1) }}</span>
              <span
                class="stage-step__visual"
                aria-hidden="true"
              ><i :style="{ blockSize: `${stage.relative}%` }" /></span>
              <strong>{{ n(stage.count) }}</strong><span class="stage-step__name">{{ stage.name }}</span>
            </RouterLink>
          </div>
          <div
            v-else
            class="empty-state empty-state--pipeline"
          >
            <VIcon
              icon="tabler-route-off"
              size="28"
            /><span>{{ t("educational.noActivePipeline") }}</span>
          </div>
          <footer class="pipeline-footer">
            <span><i class="legend-dot legend-dot--active" />{{ n(data.pipeline.totals.active) }} {{ t("agencyDashboard.openCases") }}</span>
            <span><i class="legend-dot legend-dot--closed" />{{ n(data.pipeline.totals.closed) }} {{ t("agencyDashboard.closedCases") }}</span>
          </footer>
        </article>

        <article
          id="agency-actions"
          class="dashboard-panel action-panel"
        >
          <header class="panel-header panel-header--compact">
            <div><span class="panel-eyebrow">{{ t("agencyDashboard.today") }}</span><h2>{{ t("agencyDashboard.needsAction") }}</h2></div>
            <span
              v-if="actionCount"
              class="action-total"
            >{{ n(actionCount) }}</span>
          </header>
          <div
            v-if="actionRows.length"
            class="action-list"
          >
            <RouterLink
              v-for="alert in actionRows"
              :key="alert.id"
              :to="alert.route"
              class="action-row"
              :class="`action-row--${alert.tone}`"
            >
              <span class="action-row__icon"><VIcon
                :icon="alert.icon"
                size="19"
              /></span><strong>{{ alert.title }}</strong><b>{{ n(alert.count) }}</b>
              <VIcon
                class="direction-icon"
                icon="tabler-chevron-right"
                size="18"
              />
            </RouterLink>
          </div>
          <div
            v-else
            class="all-clear"
          >
            <span><VIcon
              icon="tabler-circle-check"
              size="30"
            /></span><strong>{{ t("agencyDashboard.allClear") }}</strong><small>{{ t("agencyDashboard.allClearHelp") }}</small>
          </div>
        </article>
      </section>

      <section class="dashboard-grid dashboard-grid--insights">
        <article class="dashboard-panel admissions-panel">
          <header class="panel-header panel-header--compact">
            <div><span class="panel-eyebrow">{{ t("agencyDashboard.results") }}</span><h2>{{ t("agencyDashboard.admissions") }}</h2></div>
          </header>
          <div
            v-if="admissionTotal"
            class="admission-content"
          >
            <VueApexCharts
              type="bar"
              height="245"
              :options="admissionOptions"
              :series="admissionSeries"
            />
            <div class="admission-summary">
              <span><strong>{{ n(admissionTotal) }}</strong>{{ t("agencyDashboard.applications") }}</span>
              <span><strong>{{ n(admittedApplications) }}</strong>{{ t("agencyDashboard.admittedShort") }}</span>
              <span><strong>{{ n(admissionSuccessRate) }}%</strong>{{ t("agencyDashboard.results") }}</span>
            </div>
          </div>
          <div
            v-else
            class="empty-state empty-state--admissions"
          >
            <VIcon
              icon="tabler-school-off"
              size="28"
            /><span>{{ t("agencyDashboard.noAdmissions") }}</span>
          </div>
        </article>

        <article class="dashboard-panel collections-panel">
          <header class="panel-header panel-header--compact">
            <div><span class="panel-eyebrow">{{ t("agencyDashboard.currentPeriod") }}</span><h2>{{ t("agencyDashboard.collections") }}</h2></div>
            <span class="panel-icon panel-icon--info"><VIcon
              icon="tabler-wallet"
              size="20"
            /></span>
          </header>
          <RouterLink
            :to="applicationLink({ focus: 'outstanding', date_from: '', date_to: '' })"
            class="collect-balance"
          >
            <span>{{ t("agencyDashboard.toCollectShort") }}</span><strong>{{ money(outstandingRows) }}</strong><small>{{ t("agencyDashboard.balanceHelp") }}</small>
          </RouterLink>
          <div class="finance-list">
            <component
              :is="item.to ? RouterLink : 'div'"
              v-for="item in financialRows"
              :key="item.label"
              :to="item.to"
              class="finance-row"
              :class="{ 'finance-row--static': !item.to }"
            >
              <span
                class="finance-row__icon"
                :class="`finance-row__icon--${item.tone}`"
              ><VIcon
                :icon="item.icon"
                size="18"
              /></span><span>{{ item.label }}</span><strong>{{ item.value }}</strong>
            </component>
          </div>
        </article>

        <article class="dashboard-panel workload-panel">
          <header class="panel-header panel-header--compact">
            <div><span class="panel-eyebrow">{{ t("agencyDashboard.activeWork") }}</span><h2>{{ t("agencyDashboard.teamWorkloadShort") }}</h2></div>
            <span
              v-if="data.operations.unassigned_cases"
              class="unassigned-badge"
            >{{ n(data.operations.unassigned_cases) }} {{ t("agencyDashboard.unassignedShort") }}</span>
          </header>
          <div
            v-if="staff.length"
            class="bar-list"
          >
            <RouterLink
              v-for="member in staff"
              :key="member.id"
              :to="applicationLink({ responsible_employee_id: member.id, status: 'active' })"
              class="bar-row"
              :class="{ 'bar-row--zero': member.active_applicants === 0 }"
            >
              <div class="bar-row__label">
                <strong>{{ member.name }}</strong><b>{{ n(member.active_applicants) }}</b>
              </div>
              <span class="bar-row__track"><i :style="{ inlineSize: progressWidth(member.active_applicants, largestStaffWorkload) }" /></span>
            </RouterLink>
          </div>
          <div
            v-else
            class="empty-state empty-state--workload"
          >
            <VIcon
              icon="tabler-user-off"
              size="28"
            /><strong>{{ t("agencyDashboard.noWorkloadTitle") }}</strong><span>{{ t("agencyDashboard.noWorkloadHelp") }}</span>
          </div>
        </article>
      </section>

      <section class="dashboard-grid dashboard-grid--market">
        <article class="dashboard-panel destinations-panel">
          <header class="panel-header panel-header--compact">
            <div><span class="panel-eyebrow">{{ t("agencyDashboard.popular") }}</span><h2>{{ t("agencyDashboard.destinationsShort") }}</h2></div><span class="panel-icon panel-icon--teal"><VIcon
              icon="tabler-map-pin"
              size="20"
            /></span>
          </header>
          <div
            v-if="destinations.length"
            class="destination-list"
          >
            <RouterLink
              v-for="country in destinations"
              :key="country.id"
              :to="applicationLink({ country_id: country.id })"
              class="destination-row"
              :class="{ 'destination-row--zero': country.active === 0 }"
            >
              <div class="destination-row__head">
                <strong>{{ country.name }}</strong><span>{{ n(country.active) }} / {{ n(country.total) }}</span>
              </div>
              <span class="destination-row__track"><i :style="{ inlineSize: progressWidth(country.active, largestDestinationCount) }" /></span>
            </RouterLink>
          </div>
          <div
            v-else
            class="empty-state empty-state--destinations"
          >
            <VIcon
              icon="tabler-map-off"
              size="28"
            /><strong>{{ t("agencyDashboard.noDestinationsTitle") }}</strong><span>{{ t("agencyDashboard.noDestinationsHelp") }}</span>
          </div>
        </article>

        <article class="dashboard-panel university-panel">
          <header class="panel-header panel-header--compact">
            <div><span class="panel-eyebrow">{{ t("agencyDashboard.topUniversities") }}</span><h2>{{ t("agencyDashboard.universityProgress") }}</h2></div><span class="panel-icon"><VIcon
              icon="tabler-building-bank"
              size="20"
            /></span>
          </header>
          <div
            v-if="universities.length"
            class="university-table"
          >
            <div
              class="university-table__header"
              aria-hidden="true"
            >
              <span>{{ t("agencyDashboard.university") }}</span><span>{{ t("agencyDashboard.applications") }}</span><span>{{ t("agencyDashboard.inProgress") }}</span><span>{{ translateBackendValue("admitted") }}</span>
            </div>
            <RouterLink
              v-for="university in universities"
              :key="university.id"
              :to="applicationLink({ university_id: university.id })"
              class="university-row"
            >
              <div class="university-row__name">
                <span class="university-row__mark"><VIcon
                  icon="tabler-school"
                  size="17"
                /></span><strong>{{ university.name }}</strong>
              </div>
              <span
                class="university-row__stat"
                :data-label="t('agencyDashboard.applications')"
              >{{ n(university.applications) }}</span>
              <span
                class="university-row__stat"
                :data-label="t('agencyDashboard.inProgress')"
              >{{ n(university.in_progress) }}</span>
              <div
                class="university-row__admitted"
                :data-label="translateBackendValue('admitted')"
              >
                <strong>{{ n(university.admitted) }}</strong><span><i :style="{ inlineSize: `${admissionRate(university.admitted, university.applications)}%` }" /></span>
              </div>
            </RouterLink>
          </div>
          <div
            v-else
            class="empty-state empty-state--universities"
          >
            <VIcon
              icon="tabler-building-bank"
              size="28"
            /><strong>{{ t("agencyDashboard.noUniversitiesTitle") }}</strong><span>{{ t("agencyDashboard.noUniversitiesHelp") }}</span>
          </div>
        </article>
      </section>
    </div>
  </main>
</template>

<style scoped lang="scss">
.dashboard-home { min-block-size: 100%; background: rgb(var(--v-theme-background)); }
.dashboard-content { display: grid; gap: 20px; }
.metric-grid, .dashboard-grid { display: grid; gap: 16px; min-inline-size: 0; }
.metric-grid { grid-template-columns: repeat(12, minmax(0, 1fr)); }
.metric-card {
  --metric-color: var(--v-theme-primary); position: relative; display: grid; overflow: hidden; grid-column: span 3;
  grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 14px; min-block-size: 126px; padding: 20px;
  border: 1px solid rgba(var(--metric-color), .18); border-radius: 18px;
  background: linear-gradient(145deg, rgba(var(--metric-color), .1), transparent 62%), rgb(var(--v-theme-surface));
  box-shadow: 0 10px 30px rgba(35, 31, 60, .06); color: inherit; text-decoration: none;
  cursor: pointer; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.metric-card:hover, .metric-card:focus-visible { border-color: rgba(var(--metric-color), .42); box-shadow: 0 14px 32px rgba(35, 31, 60, .11); outline: none; transform: translateY(-2px); }
.metric-card--primary { --metric-color: var(--v-theme-primary); }
.metric-card--warning { --metric-color: var(--v-theme-warning); }
.metric-card--success { --metric-color: var(--v-theme-success); }
.metric-card--info { --metric-color: var(--v-theme-info); }
.metric-card__glow { position: absolute; inset-block-start: -55px; inset-inline-end: -45px; inline-size: 130px; block-size: 130px; border-radius: 50%; background: rgba(var(--metric-color), .12); }
.metric-card__icon { position: relative; display: grid; inline-size: 46px; block-size: 46px; place-items: center; border: 1px solid rgba(var(--metric-color), .16); border-radius: 14px; background: rgba(var(--metric-color), .13); color: rgb(var(--metric-color)); }
.metric-card__copy { position: relative; display: grid; min-inline-size: 0; gap: 3px; }
.metric-card__copy small { color: rgba(var(--v-theme-on-surface), .84); font-size: 1rem; font-weight: 750; line-height: 1.2; }
.metric-card__copy strong { overflow-wrap: anywhere; color: rgb(var(--v-theme-on-surface)); font-size: clamp(1.15rem, 1.45vw, 1.45rem); font-variant-numeric: tabular-nums; line-height: 1.22; }
.metric-card--receivables .metric-card__copy strong { font-size: clamp(.98rem, 1.18vw, 1.2rem); }
.metric-card__copy > span { display: -webkit-box; overflow: hidden; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; line-height: 1.25; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.metric-card__arrow { align-self: start; color: rgba(var(--metric-color), .7); }

.dashboard-grid--hero { grid-template-columns: minmax(0, 2fr) minmax(310px, 1fr); }
.dashboard-grid--insights { grid-template-columns: minmax(270px, 3fr) minmax(360px, 5fr) minmax(300px, 4fr); }
.dashboard-grid--market { grid-template-columns: minmax(320px, 5fr) minmax(500px, 7fr); }
.dashboard-panel { display: flex; flex-direction: column; min-inline-size: 0; padding: 22px; border: 1px solid rgba(var(--v-theme-on-surface), .09); border-radius: 20px; background: rgb(var(--v-theme-surface)); box-shadow: 0 10px 34px rgba(35, 31, 60, .055); }
.panel-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-block-end: 22px; }
.panel-header--compact { margin-block-end: 18px; }
.panel-eyebrow { display: block; margin-block-end: 4px; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .68rem; font-weight: 700; letter-spacing: .055em; text-transform: uppercase; }
:global(html[dir="rtl"] .panel-eyebrow) { letter-spacing: 0; text-transform: none; }
.panel-header h2 { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1.05rem; font-weight: 750; line-height: 1.35; }
.panel-icon { display: grid; inline-size: 38px; block-size: 38px; place-items: center; border-radius: 12px; background: rgba(var(--v-theme-primary), .11); color: rgb(var(--v-theme-primary)); }
.panel-icon--info { background: rgba(var(--v-theme-info), .12); color: rgb(var(--v-theme-info)); }
.panel-icon--teal { background: rgba(var(--v-theme-success), .12); color: rgb(var(--v-theme-success)); }

.pipeline-panel { position: relative; overflow: hidden; block-size: 430px; max-block-size: 430px; background: radial-gradient(circle at 95% 0%, rgba(var(--v-theme-primary), .13), transparent 30%), rgb(var(--v-theme-surface)); }
.pipeline-total { display: grid; text-align: end; }
.pipeline-total strong { color: rgb(var(--v-theme-primary)); font-size: 1.45rem; line-height: 1; }
.pipeline-total span { margin-block-start: 4px; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .72rem; }
.stage-journey { display: grid; overflow-x: auto; overflow-y: hidden; grid-auto-columns: minmax(124px, 1fr); grid-auto-flow: column; gap: 8px; block-size: 285px; padding: 8px 2px 14px; scrollbar-width: none; }
.stage-journey::-webkit-scrollbar, .action-list::-webkit-scrollbar { display: none; }
.stage-step { --stage-color: var(--v-theme-primary); position: relative; display: grid; grid-template-rows: auto 1fr auto minmax(34px, auto); gap: 8px; min-inline-size: 0; padding: 10px 9px; border-radius: 14px; color: inherit; text-align: center; text-decoration: none; transition: background-color .18s ease, transform .18s ease; }
.stage-step:not(:last-child)::after { position: absolute; z-index: 2; inset-block-start: 19px; inset-inline-end: -8px; inline-size: 16px; border-block-start: 2px dotted rgba(var(--v-theme-primary), .34); content: ""; }
.stage-step:hover, .stage-step:focus-visible { background: rgba(var(--stage-color), .07); outline: none; transform: translateY(-2px); }
.stage-step--info { --stage-color: var(--v-theme-info); }
.stage-step--teal, .stage-step--success { --stage-color: var(--v-theme-success); }
.stage-step--warning { --stage-color: var(--v-theme-warning); }
.stage-step__index { display: grid; z-index: 1; inline-size: 22px; block-size: 22px; margin-inline: auto; place-items: center; border: 3px solid rgb(var(--v-theme-surface)); border-radius: 50%; background: rgb(var(--stage-color)); box-shadow: 0 0 0 2px rgba(var(--stage-color), .18); color: #fff; font-size: .62rem; font-weight: 700; }
.stage-step__visual { display: flex; align-items: flex-end; justify-content: center; min-block-size: 130px; padding: 0 14px; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .1); }
.stage-step__visual i { inline-size: 100%; min-block-size: 10px; border-radius: 9px 9px 3px 3px; background: repeating-linear-gradient(135deg, rgba(255, 255, 255, .28) 0 3px, transparent 3px 7px), linear-gradient(180deg, rgba(var(--stage-color), .85), rgba(var(--stage-color), .48)); box-shadow: 0 8px 18px rgba(var(--stage-color), .16); }
.stage-step > strong { color: rgb(var(--v-theme-on-surface)); font-size: 1.12rem; font-variant-numeric: tabular-nums; }
.stage-step__name { display: -webkit-box; overflow: hidden; min-block-size: 2.7em; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .72rem; font-weight: 650; line-height: 1.3; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
.pipeline-footer { display: flex; flex-wrap: wrap; gap: 18px; padding-block-start: 14px; border-block-start: 1px solid rgba(var(--v-theme-on-surface), .08); }
.pipeline-footer span { display: flex; align-items: center; gap: 7px; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .74rem; }
.legend-dot { inline-size: 8px; block-size: 8px; border-radius: 50%; }
.legend-dot--active { background: rgb(var(--v-theme-primary)); box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), .1); }
.legend-dot--closed { background: rgb(var(--v-theme-success)); box-shadow: 0 0 0 4px rgba(var(--v-theme-success), .1); }

.action-panel { display: flex; flex-direction: column; overflow: hidden; block-size: 430px; max-block-size: 430px; background: linear-gradient(155deg, rgba(var(--v-theme-warning), .07), transparent 42%), rgb(var(--v-theme-surface)); }
.action-total { display: grid; min-inline-size: 34px; block-size: 34px; padding-inline: 8px; place-items: center; border-radius: 11px; background: rgba(var(--v-theme-warning), .13); color: rgb(var(--v-theme-warning)); font-size: .82rem; font-weight: 800; }
.action-list { display: grid; flex: 1; overflow-y: auto; align-content: start; gap: 10px; min-block-size: 0; padding-inline-end: 3px; scrollbar-width: none; }
.action-row { --action-color: var(--v-theme-warning); display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; align-items: center; gap: 11px; min-inline-size: 0; padding: 12px; border: 1px solid rgba(var(--action-color), .13); border-radius: 13px; background: rgba(var(--action-color), .055); color: inherit; text-decoration: none; transition: background-color .18s ease, border-color .18s ease; }
.action-row--error { --action-color: var(--v-theme-error); }
.action-row:hover, .action-row:focus-visible { border-color: rgba(var(--action-color), .32); background: rgba(var(--action-color), .1); outline: none; }
.action-row__icon { display: grid; inline-size: 36px; block-size: 36px; place-items: center; border-radius: 11px; background: rgba(var(--action-color), .13); color: rgb(var(--action-color)); }
.action-row strong { overflow: hidden; color: rgb(var(--v-theme-on-surface)); font-size: .82rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
.action-row b { color: rgb(var(--action-color)); font-size: .83rem; font-variant-numeric: tabular-nums; }
.direction-icon { color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity)); }
:global(html[dir="rtl"] .direction-icon) { transform: rotate(180deg); }
.all-clear, .empty-state { display: grid; flex: 1 1 auto; min-block-size: 0; align-content: center; justify-items: center; gap: 8px; inline-size: 100%; margin: 0; padding: 18px; border: 1px dashed rgba(var(--v-theme-primary), .18); border-radius: 16px; background: linear-gradient(145deg, rgba(var(--v-theme-primary), .1), rgba(var(--v-theme-info), .045) 58%, transparent); color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)); text-align: center; }
.all-clear > span, .empty-state > .v-icon { display: grid; inline-size: 52px; block-size: 52px; place-items: center; border-radius: 16px; background: rgb(var(--v-theme-primary)); box-shadow: 0 8px 18px rgba(var(--v-theme-primary), .22); color: rgb(var(--v-theme-on-primary)); }
.all-clear strong, .empty-state strong { color: rgb(var(--v-theme-on-surface)); font-size: .9rem; font-weight: 750; }
.all-clear small, .empty-state span { max-inline-size: 250px; font-size: .75rem; line-height: 1.35; }
.all-clear { border-color: rgba(var(--v-theme-success), .22); background: linear-gradient(145deg, rgba(var(--v-theme-success), .12), rgba(var(--v-theme-info), .05) 58%, transparent); }
.all-clear > span { background: rgb(var(--v-theme-success)); box-shadow: 0 8px 18px rgba(var(--v-theme-success), .22); }
.empty-state--pipeline { border-color: rgba(var(--v-theme-primary), .22); }
.empty-state--admissions { border-color: rgba(var(--v-theme-success), .22); background: linear-gradient(145deg, rgba(var(--v-theme-success), .11), rgba(var(--v-theme-info), .045) 58%, transparent); }
.empty-state--admissions > .v-icon, .empty-state--destinations > .v-icon { background: rgb(var(--v-theme-success)); box-shadow: 0 8px 18px rgba(var(--v-theme-success), .22); }
.empty-state--workload { border-color: rgba(var(--v-theme-info), .23); background: linear-gradient(145deg, rgba(var(--v-theme-info), .12), rgba(var(--v-theme-primary), .045) 58%, transparent); }
.empty-state--workload > .v-icon { background: rgb(var(--v-theme-info)); box-shadow: 0 8px 18px rgba(var(--v-theme-info), .22); }
.empty-state--destinations { border-color: rgba(var(--v-theme-success), .22); background: linear-gradient(145deg, rgba(var(--v-theme-success), .11), rgba(var(--v-theme-info), .045) 58%, transparent); }
.empty-state--universities { border-color: rgba(var(--v-theme-warning), .25); background: linear-gradient(145deg, rgba(var(--v-theme-warning), .12), rgba(var(--v-theme-primary), .045) 58%, transparent); }
.empty-state--universities > .v-icon { background: rgb(var(--v-theme-warning)); box-shadow: 0 8px 18px rgba(var(--v-theme-warning), .22); }

.admissions-panel, .collections-panel, .workload-panel, .destinations-panel, .university-panel { min-block-size: 390px; }
.admission-content { display: grid; flex: 1; grid-template-rows: minmax(0, 1fr) auto; min-block-size: 0; }
.admission-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; padding-block-start: 10px; border-block-start: 1px solid rgba(var(--v-theme-on-surface), .08); }
.admission-summary span { display: grid; gap: 2px; min-inline-size: 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .67rem; line-height: 1.2; }
.admission-summary strong { overflow: hidden; color: rgb(var(--v-theme-on-surface)); font-size: .95rem; font-variant-numeric: tabular-nums; text-overflow: ellipsis; white-space: nowrap; }
.admission-summary span:nth-child(2) strong { color: rgb(var(--v-theme-success)); }
.admission-summary span:nth-child(3) strong { color: rgb(var(--v-theme-primary)); }
:global([dir='rtl'] .admissions-panel .apexcharts-yaxis-texts-g text) { direction: rtl; unicode-bidi: plaintext; }
:global([dir='rtl'] .admissions-panel .apexcharts-data-labels text), :global([dir='rtl'] .admissions-panel .apexcharts-xaxis-texts-g text) { direction: ltr; unicode-bidi: embed; }
.collect-balance { display: grid; gap: 3px; padding: 17px 18px; border: 1px solid rgba(var(--v-theme-primary), .15); border-radius: 16px; background: radial-gradient(circle at 95% 0%, rgba(var(--v-theme-info), .18), transparent 42%), linear-gradient(135deg, rgba(var(--v-theme-primary), .13), rgba(var(--v-theme-info), .06)); color: inherit; text-decoration: none; }
.collect-balance > span { color: rgb(var(--v-theme-primary)); font-size: .75rem; font-weight: 750; }
.collect-balance > strong { overflow-wrap: anywhere; color: rgb(var(--v-theme-on-surface)); font-size: 1.3rem; font-variant-numeric: tabular-nums; line-height: 1.3; }
.collect-balance > small { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .7rem; }
.finance-list { display: grid; margin-block-start: 12px; }
.finance-row { display: grid; grid-template-columns: auto minmax(0, 1fr) minmax(100px, auto); align-items: center; gap: 10px; min-inline-size: 0; padding-block: 11px; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .07); color: inherit; text-decoration: none; }
.finance-row:last-child { border-block-end: 0; }
.finance-row:not(.finance-row--static):hover > span:nth-child(2) { color: rgb(var(--v-theme-primary)); }
.finance-row__icon { display: grid; inline-size: 32px; block-size: 32px; place-items: center; border-radius: 10px; }
.finance-row__icon--success { background: rgba(var(--v-theme-success), .11); color: rgb(var(--v-theme-success)); }
.finance-row__icon--error { background: rgba(var(--v-theme-error), .1); color: rgb(var(--v-theme-error)); }
.finance-row__icon--info { background: rgba(var(--v-theme-info), .11); color: rgb(var(--v-theme-info)); }
.finance-row > span:nth-child(2) { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .76rem; font-weight: 600; }
.finance-row > strong { overflow-wrap: anywhere; color: rgb(var(--v-theme-on-surface)); font-size: .76rem; font-variant-numeric: tabular-nums; text-align: end; }
.unassigned-badge { padding: 6px 9px; border-radius: 9px; background: rgba(var(--v-theme-warning), .12); color: rgb(var(--v-theme-warning)); font-size: .66rem; font-weight: 750; }
.bar-list { display: grid; gap: 16px; }
.bar-row { display: grid; gap: 7px; color: inherit; text-decoration: none; }
.bar-row__label, .destination-row__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.bar-row__label strong, .destination-row__head strong { overflow: hidden; color: rgb(var(--v-theme-on-surface)); font-size: .76rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
.bar-row__label b { color: rgb(var(--v-theme-primary)); font-size: .74rem; font-variant-numeric: tabular-nums; }
.bar-row__track, .destination-row__track { overflow: hidden; block-size: 9px; border-radius: 999px; background: rgba(var(--v-theme-primary), .075); }
.bar-row__track i, .destination-row__track i { display: block; min-inline-size: 3px; block-size: 100%; border-radius: inherit; background: linear-gradient(90deg, rgb(var(--v-theme-primary)), rgb(var(--v-theme-info))); transition: inline-size .3s ease; }
.bar-row--zero .bar-row__track i, .destination-row--zero .destination-row__track i { min-inline-size: 0; }
.bar-row:hover .bar-row__label strong { color: rgb(var(--v-theme-primary)); }
.destination-list { display: grid; gap: 16px; }
.destination-row { display: grid; gap: 7px; color: inherit; text-decoration: none; }
.destination-row__head span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .7rem; font-variant-numeric: tabular-nums; }
.destination-row__track { background: rgba(var(--v-theme-success), .075); }
.destination-row__track i { background: linear-gradient(90deg, rgb(var(--v-theme-success)), rgb(var(--v-theme-info))); }
.destination-row:hover .destination-row__head strong { color: rgb(var(--v-theme-success)); }

.university-table { display: grid; }
.university-table__header, .university-row { display: grid; grid-template-columns: minmax(180px, 1.7fr) repeat(3, minmax(76px, .6fr)); align-items: center; gap: 12px; }
.university-table__header { padding: 0 10px 10px; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .66rem; font-weight: 700; }
.university-table__header span:not(:first-child) { text-align: center; }
.university-row { min-block-size: 48px; padding: 8px 10px; border-block-start: 1px solid rgba(var(--v-theme-on-surface), .07); border-radius: 9px; color: inherit; text-decoration: none; transition: background-color .18s ease; }
.university-row:hover, .university-row:focus-visible { background: rgba(var(--v-theme-primary), .055); outline: none; }
.university-row__name { display: flex; align-items: center; gap: 9px; min-inline-size: 0; }
.university-row__mark { display: grid; flex: 0 0 30px; inline-size: 30px; block-size: 30px; place-items: center; border-radius: 9px; background: rgba(var(--v-theme-primary), .1); color: rgb(var(--v-theme-primary)); }
.university-row__name strong { overflow: hidden; color: rgb(var(--v-theme-on-surface)); font-size: .75rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
.university-row__stat { overflow: hidden; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; font-variant-numeric: tabular-nums; text-align: center; text-overflow: ellipsis; white-space: nowrap; }
.university-row__admitted { display: grid; justify-items: stretch; gap: 4px; }
.university-row__admitted strong { overflow: hidden; color: rgb(var(--v-theme-success)); font-size: .75rem; font-variant-numeric: tabular-nums; text-align: center; text-overflow: ellipsis; white-space: nowrap; }
.university-row__admitted > span { overflow: hidden; block-size: 4px; border-radius: 99px; background: rgba(var(--v-theme-success), .1); }
.university-row__admitted i { display: block; block-size: 100%; border-radius: inherit; background: rgb(var(--v-theme-success)); }
.dashboard-button { background: rgba(var(--v-theme-primary), .12) !important; color: rgb(var(--v-theme-on-surface)) !important; }
.dashboard-button:hover, .dashboard-button:focus-visible { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }

@media (max-width: 1279px) {
  .metric-grid { display: flex; overflow-x: auto; padding-block: 2px; scrollbar-width: none; scroll-snap-type: inline mandatory; }
  .metric-grid::-webkit-scrollbar { display: none; }
  .metric-card { flex: 0 0 280px; scroll-snap-align: start; }
  .dashboard-grid--insights { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .collections-panel { grid-column: span 2; grid-row: 1; }
  .admissions-panel, .workload-panel { grid-row: 2; }
  .dashboard-grid--market { grid-template-columns: minmax(280px, 5fr) minmax(430px, 7fr); }
}
@media (max-width: 959px) {
  .dashboard-grid--hero, .dashboard-grid--market { grid-template-columns: 1fr; }
  .pipeline-panel, .action-panel { block-size: 400px; max-block-size: 400px; }
  .dashboard-grid--hero .action-panel { grid-row: 1; }
  .pipeline-panel { grid-row: 2; }
}
@media (max-width: 599px) {
  .dashboard-content { gap: 14px; }
  .metric-grid, .dashboard-grid { gap: 12px; }
  .metric-card { flex-basis: min(84vw, 300px); min-block-size: 108px; padding: 16px; border-radius: 16px; }
  .metric-card__copy small { font-size: .95rem; }
  .metric-card__copy > span { -webkit-line-clamp: 2; }
  .dashboard-panel { padding: 17px; border-radius: 17px; }
  .dashboard-grid--insights { grid-template-columns: 1fr; }
  .collections-panel, .admissions-panel, .workload-panel { grid-column: auto; grid-row: auto; min-block-size: auto; }
  .collections-panel { grid-row: 1; }
  .admissions-panel { grid-row: 2; }
  .workload-panel { grid-row: 3; }
  .pipeline-panel, .action-panel { block-size: 390px; max-block-size: 390px; }
  .pipeline-panel { grid-row: 1; }
  .dashboard-grid--hero .action-panel { grid-row: 2; }
  .stage-journey { grid-auto-columns: 116px; block-size: 246px; min-block-size: 246px; padding-block: 5px 10px; }
  .university-table__header { display: none; }
  .university-row { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 9px; padding-block: 12px; }
  .university-row__name { grid-column: 1 / -1; }
  .university-row__stat, .university-row__admitted { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; text-align: start; }
  .university-row__stat::before, .university-row__admitted::before { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); content: attr(data-label); font-size: .65rem; }
  .university-row__admitted > span { display: none; }
  .finance-row { grid-template-columns: auto minmax(0, 1fr); }
  .finance-row > strong { grid-column: 2; text-align: start; }
}
@media (min-width: 960px) and (max-width: 1120px) {
  .dashboard-grid--market { grid-template-columns: 1fr; }
}
@media (prefers-reduced-motion: reduce) {
  .metric-card, .stage-step, .bar-row__track i, .destination-row__track i { transition: none; }
}
</style>
