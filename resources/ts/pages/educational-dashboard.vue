<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateFilterMenu from '@/components/erp/ErpDateFilterMenu.vue'
import AgencyOverviewSkeleton from '@/components/dashboard/AgencyOverviewSkeleton.vue'
import '@/components/dashboard/agency-overview.scss'
import DashboardApi, { type EducationalDashboard, type EducationalDashboardParams } from '@/api/educationalDashboard'
import { canAccess } from '@/utils/authorization'
import { translateApplicantStage, translateBackendValue } from '@/utils/localization'

const { t, n } = useI18n()
const toast = useToast()
const data = ref<EducationalDashboard | null>(null)
const loading = ref(false)
const failed = ref(false)
const appliedFilters = ref<EducationalDashboardParams>({})
const dashboardContent = ref<HTMLElement>()
const skeletonHeights = ref<number[]>([])
const skeletonSummaryHeight = ref<number>()
const applicationLink = (extra: Record<string, string | number> = {}) => ({ name: 'applications', query: { ...Object.fromEntries(Object.entries(appliedFilters.value).filter(([, value]) => value != null)), ...extra } })
const serviceNames: Record<string, string> = { scholarship: 'scholarship', test_offer: 'testOffer', academy: 'academy', private: 'private' }
const serviceName = (service: string) => t(`educational.builtInServices.${serviceNames[service] ?? service}`)
const money = (rows: Array<{ amount: number; currency: string | null }>) => rows.map(row => `${n(row.amount, 'decimal')} ${row.currency ?? t('agencyDashboard.unspecifiedCurrency')}`).join(' · ') || n(0)
const stages = computed(() => data.value?.pipeline.stages.filter(row => row.count > 0) ?? [])
const maxStage = computed(() => Math.max(1, ...stages.value.map(row => row.count)))
const priorities = computed(() => [
  { key: 'overdue_tasks' as const, icon: 'tabler-calendar-due', color: 'error', label: t('educational.overdueCaseTasks') },
  { key: 'stuck_applicants' as const, icon: 'tabler-clock-exclamation', color: 'warning', label: t('educational.stuckCases') },
  { key: 'missing_documents' as const, icon: 'tabler-file-alert', color: 'info', label: t('agencyDashboard.documentsAwaited') },
  { key: 'contracts_requiring_attention' as const, icon: 'tabler-file-description', color: 'secondary', label: t('educational.contractAttention') },
])
const priorityCount = computed(() => data.value ? Object.values(data.value.attention.counts).reduce((sum, count) => sum + count, 0) + data.value.attention.pending_approvals + data.value.operations.unassigned_cases : 0)
const staff = computed(() => [...(data.value?.staff ?? [])].sort((a, b) => b.active_applicants - a.active_applicants).slice(0, 6))
const sales = computed(() => data.value ? [
  { service: 'scholarship', route: 'scholarship-contracts', row: data.value.sales.scholarships },
  { service: 'private', route: 'private-contracts', row: data.value.sales.private },
  { service: 'test_offer', route: 'test-offer-contracts', row: data.value.sales.test_offers },
  { service: 'academy', route: 'academy-contracts', row: data.value.sales.academy },
] : [])
let requestId = 0
async function load(range: EducationalDashboardParams = appliedFilters.value) {
  if (loading.value) return
  if (range.date_from && range.date_to && range.date_from > range.date_to) return
  const content = dashboardContent.value
  skeletonHeights.value = content ? Array.from(content.querySelectorAll<HTMLElement>('.analytics-grid > .v-card')).map(card => card.getBoundingClientRect().height) : []
  skeletonSummaryHeight.value = content?.querySelector('.summary-grid')?.getBoundingClientRect().height
  const id = ++requestId
  const requestedFilters = { ...range }
  loading.value = true
  failed.value = false
  try {
    const result = await DashboardApi.get(requestedFilters)
    if (id === requestId) { data.value = result; appliedFilters.value = requestedFilters }
  }
  catch {
    if (id === requestId) { failed.value = true; toast.error(t('educational.loadOverviewFailed')) }
  }
  finally { if (id === requestId) loading.value = false }
}
onMounted(() => load())
</script>

<template>
  <div class="education-workspace erp-workspace agency-dashboard" :aria-busy="loading">
    <ErpPageHeader :title="t('educational.agencyOverview')" section="dashboard">
      <template #actions>
        <ErpDateFilterMenu :from="appliedFilters.date_from" :to="appliedFilters.date_to" :disabled="loading" @apply="load" />
        <VBtn variant="text" size="small" prepend-icon="tabler-refresh" :disabled="loading" @click="load()">{{ t('agencyDashboard.refresh') }}</VBtn>
      </template>
    </ErpPageHeader>
    <AgencyOverviewSkeleton v-if="loading" :panel-heights="skeletonHeights" :summary-height="skeletonSummaryHeight" />
    <VAlert v-if="failed" type="error" variant="tonal" class="mb-4">{{ t('educational.loadOverviewFailed') }}<VBtn variant="text" size="small" @click="load()">{{ t('agencyDashboard.refresh') }}</VBtn></VAlert>
    <div v-if="data" v-show="!loading" ref="dashboardContent">
      <div class="text-caption text-medium-emphasis mb-2">{{ t('agencyDashboard.liveOperations') }}</div>
      <section class="summary-grid">
        <VCard :to="applicationLink({ status: 'active', date_from: '', date_to: '' })" class="summary-card"><div><div class="text-caption">{{ t('educational.activeCasesLabel') }}</div><div class="summary-value"><VIcon color="primary" icon="tabler-users-group" /><strong>{{ n(data.operations.active_cases) }}</strong></div><div class="text-caption text-medium-emphasis">{{ t('agencyDashboard.unassigned', { count: n(data.operations.unassigned_cases) }) }}</div></div></VCard>
        <VCard href="#agency-priorities" class="summary-card"><div><div class="text-caption">{{ t('agencyDashboard.actionItems') }}</div><div class="summary-value"><VIcon color="warning" icon="tabler-alert-triangle" /><strong>{{ n(priorityCount) }}</strong></div><div class="text-caption text-medium-emphasis">{{ t('agencyDashboard.itemsNotCases') }}</div></div></VCard>
        <VCard :to="applicationLink({ focus: 'outstanding', date_from: '', date_to: '' })" class="summary-card"><div><div class="text-caption">{{ t('agencyDashboard.toCollect') }}</div><div class="summary-value"><VIcon color="error" icon="tabler-wallet" /><strong class="money-value">{{ money(data.operations.outstanding) }}</strong></div><div class="text-caption text-medium-emphasis">{{ t('agencyDashboard.balanceHelp') }}</div></div></VCard>
        <VCard :to="applicationLink({ focus: 'admitted', date_from: '', date_to: '' })" class="summary-card"><div><div class="text-caption">{{ t('agencyDashboard.admitted') }}</div><div class="summary-value"><VIcon color="success" icon="tabler-school" /><strong>{{ n(data.operations.admissions.admitted) }}</strong></div><div class="text-caption text-medium-emphasis">{{ t('agencyDashboard.awaitingDecision', { count: n(data.operations.admissions.applied) }) }}</div></div></VCard>
      </section>
      <div class="analytics-grid">
        <VCard class="h-100">
          <VCardItem :title="t('agencyDashboard.caseProgress')" :subtitle="t('agencyDashboard.pipelineHelp')"><template #append><VBtn size="small" variant="text" :to="applicationLink()">{{ t('educational.openBoard') }}</VBtn></template></VCardItem>
          <VCardText class="pipeline-grid"><RouterLink v-for="stage in stages" :key="stage.id" :to="applicationLink({ stage: stage.key, ...(stage.service ? { service: stage.service } : {}) })" class="stage-link"><div class="d-flex justify-space-between gap-2 mb-1"><span>{{ translateApplicantStage(stage) }}<small v-if="stage.service" class="d-block text-medium-emphasis">{{ serviceName(stage.service) }}</small></span><strong>{{ n(stage.count) }}</strong></div><VProgressLinear :model-value="stage.count / maxStage * 100" :color="stage.is_terminal ? 'success' : 'primary'" height="6" rounded /></RouterLink><p v-if="!stages.length" class="text-medium-emphasis">{{ t('educational.noActivePipeline') }}</p></VCardText>
          <VDivider /><VCardText><div class="text-caption text-medium-emphasis mb-2">{{ t('agencyDashboard.admissionsHelp') }}</div><div class="d-flex flex-wrap gap-2"><VChip v-for="(count, status) in data.operations.admissions" :key="status" :to="status === 'admitted' || status === 'applied' ? applicationLink({ focus: status === 'admitted' ? 'admitted' : 'awaiting_decision', date_from: '', date_to: '' }) : undefined" size="small" :color="status === 'admitted' ? 'success' : status === 'rejected' ? 'error' : 'secondary'">{{ translateBackendValue(status) }} · {{ n(count) }}</VChip></div>
            <details v-if="data.operations.service_stages.length" class="service-progress mt-4"><summary>{{ t('agencyDashboard.serviceProgress') }}</summary><div class="text-caption text-medium-emphasis my-2">{{ t('agencyDashboard.serviceProgressHelp') }}</div><div class="d-flex flex-wrap gap-2"><VChip v-for="(stage, index) in data.operations.service_stages" :key="index" size="small" :color="stage.is_terminal ? 'success' : 'primary'">{{ serviceName(stage.service) }} · {{ stage.name ? translateApplicantStage({ key: stage.key ?? undefined, name: stage.name }) : t('agencyDashboard.noStage') }} · {{ n(stage.count) }}</VChip></div></details>
          </VCardText>
        </VCard>
        <VCard id="agency-priorities" class="h-100">
          <VCardItem :title="t('agencyDashboard.priorities')" :subtitle="t('agencyDashboard.priorityHelp')" />
          <div v-for="group in priorities" :key="group.key" class="priority-group"><div class="d-flex align-center gap-2"><VIcon :icon="group.icon" :color="group.color" size="18" /><span class="flex-grow-1 text-body-2">{{ group.label }}</span><VChip :color="data.attention.counts[group.key] ? group.color : 'secondary'" size="x-small">{{ n(data.attention.counts[group.key]) }}</VChip></div><RouterLink v-for="hit in data.attention[group.key].slice(0, 2)" :key="hit.id" :to="hit.route" class="priority-link">{{ hit.title }}<VIcon icon="tabler-arrow-right" size="14" /></RouterLink></div>
          <VListItem v-if="canAccess('view_pending_transactions')" :to="{ name: 'payment-requests' }" :title="t('educational.awaitingApproval')"><template #prepend><VIcon color="warning" icon="tabler-hourglass" /></template><template #append><VChip size="small" color="warning">{{ n(data.attention.pending_approvals) }}</VChip></template></VListItem>
          <VListItem v-if="data.operations.unassigned_cases" :to="applicationLink({ focus: 'unassigned', date_from: '', date_to: '' })" :title="t('agencyDashboard.unassigned', { count: n(data.operations.unassigned_cases) })"><template #prepend><VIcon color="warning" icon="tabler-user-off" /></template></VListItem>
          <p v-if="!priorityCount" class="text-success px-4 pb-4">{{ t('agencyDashboard.noPriorityItems') }}</p>
        </VCard>
        <VCard class="h-100">
          <VCardItem :title="t('agencyDashboard.moneyAndSales')" :subtitle="t('agencyDashboard.postedPeriod')" /><VList density="compact">
            <VListItem :to="canAccess('view_incomes') ? { name: 'income' } : undefined" :title="t('educational.postedIncome')" :subtitle="money(data.financial.income)"><template #prepend><VIcon color="success" icon="tabler-trending-up" /></template></VListItem>
            <VListItem :to="canAccess('view_expenses') ? { name: 'expenses' } : undefined" :title="t('educational.postedExpenses')" :subtitle="money(data.financial.expenses)"><template #prepend><VIcon color="error" icon="tabler-trending-down" /></template></VListItem>
            <VListItem :title="t('educational.postedSalaries')" :subtitle="money(data.financial.salaries)"><template #prepend><VIcon color="info" icon="tabler-wallet" /></template></VListItem>
          </VList><VDivider /><VList density="compact"><VListItem v-for="sale in sales" :key="sale.service" :to="canAccess('view_contracts') ? { name: sale.route } : undefined" :title="serviceName(sale.service)"><template #append><span class="text-body-2">{{ n(sale.row.sold) }}</span></template></VListItem></VList><div class="text-caption text-medium-emphasis px-4 pb-3">{{ t('agencyDashboard.serviceCountHelp') }}</div>
        </VCard>
        <VCard class="h-100">
          <VCardItem :title="t('agencyDashboard.teamWorkload')" :subtitle="t('agencyDashboard.teamHelp')"><template #append><VBtn v-if="canAccess('view_assigned_boards')" :to="{ name: 'tasks' }" size="small" variant="text">{{ t('educational.openTasksBoard') }}</VBtn></template></VCardItem>
          <div class="task-strip"><div><strong>{{ n(data.work.tasks.due_today) }}</strong><small>{{ t('educational.tasksDueToday') }}</small></div><div><strong class="text-error">{{ n(data.work.tasks.overdue) }}</strong><small>{{ t('educational.tasksOverdue') }}</small></div><div><strong>{{ n(data.work.tasks.open) }}</strong><small>{{ t('educational.tasksOpen') }}</small></div></div>
          <VTable density="compact"><thead><tr><th>{{ t('educational.employee') }}</th><th class="text-end">{{ t('educational.activeCases') }}</th></tr></thead><tbody><tr v-for="row in staff" :key="row.id"><td><RouterLink :to="applicationLink({ responsible_employee_id: row.id, status: 'active' })" class="stage-link">{{ row.name }}</RouterLink></td><td class="text-end">{{ n(row.active_applicants) }}</td></tr></tbody></VTable><p v-if="!staff.length" class="text-medium-emphasis px-4">{{ t('educational.noStaff') }}</p>
        </VCard>
        <VCard class="h-100">
          <VCardItem :title="t('agencyDashboard.destinations')" :subtitle="t('agencyDashboard.destinationHelp')" /><VCardText><RouterLink v-for="country in data.countries.slice(0, 5)" :key="country.id" :to="applicationLink({ country_id: country.id })" class="destination-link"><span>{{ country.name }}</span><strong>{{ n(country.active) }} / {{ n(country.total) }}</strong></RouterLink><p v-if="!data.countries.length" class="text-medium-emphasis">{{ t('educational.noCountryData') }}</p></VCardText><VDivider /><VCardText><div class="text-caption text-medium-emphasis mb-2">{{ t('agencyDashboard.universitiesHelp') }}</div><div v-for="university in data.universities.slice(0, 3)" :key="university.id" class="destination-link"><span>{{ university.name }}</span><strong>{{ n(university.admitted) }} / {{ n(university.applications) }}</strong></div><p v-if="!data.universities.length" class="text-medium-emphasis">{{ t('agencyDashboard.noAdmissions') }}</p></VCardText>
        </VCard>
      </div>
    </div>
  </div>
</template>

<style scoped>
.summary-card { padding: 14px 16px; }
.summary-value { display: flex; align-items: center; gap: 8px; margin-block: 4px; }
.summary-card > div { min-inline-size: 0; }
.summary-card strong { display: block; font-size: 1.75rem; line-height: 1.5; font-variant-numeric: tabular-nums; }
.summary-card .money-value { font-size: 1.15rem; overflow-wrap: anywhere; margin-block: 6px; }
.pipeline-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
.stage-link, .priority-link, .destination-link { color: inherit; text-decoration: none; }
.stage-link:hover, .priority-link:hover, .destination-link:hover { color: rgb(var(--v-theme-primary)); }
.priority-group { padding: 10px 20px; border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08); }
.priority-link { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-block-start: 5px; padding-inline-start: 26px; font-size: 0.8rem; }
.task-strip { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 0 20px 16px; }
.task-strip strong, .task-strip small { display: block; }
.task-strip strong { font-size: 1.3rem; }
.task-strip small { color: rgba(var(--v-theme-on-surface), 0.65); }
.destination-link { display: flex; justify-content: space-between; gap: 12px; padding-block: 7px; font-size: 0.85rem; }
.destination-link strong { white-space: nowrap; font-variant-numeric: tabular-nums; }
.service-progress summary { cursor: pointer; font-size: 0.85rem; color: rgb(var(--v-theme-primary)); }
/* Vue drops every selector after a global pseudo-selector block, so the whole
   selector must live inside it; otherwise the rotation lands on the app root. */
:global(html[dir='rtl'] .agency-dashboard .priority-link .v-icon) { transform: rotate(180deg); }
#agency-priorities { scroll-margin-block-start: 90px; }
@media (max-width: 599px) { .pipeline-grid { grid-template-columns: 1fr; } .summary-card { padding: 12px; gap: 8px; } .summary-card strong { font-size: 1.4rem; } }
</style>
