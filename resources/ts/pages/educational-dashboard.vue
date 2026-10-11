<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import DashboardApi, { type EducationalDashboard } from '@/api/educationalDashboard'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'

const toast = useToast()
const { t, n } = useI18n()
const router = useRouter()
const loading = ref(false)
const filtersOpen = ref(false)
const data = ref<EducationalDashboard | null>(null)
const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])
const employeeId = ref<number | null>(null)
const countryId = ref<number | null>(null)
const universityId = ref<number | null>(null)
const dateFrom = ref('')
const dateTo = ref('')

const activeFilterCount = computed(() => [employeeId.value, countryId.value, universityId.value, dateFrom.value, dateTo.value].filter(Boolean).length)
const maxStage = computed(() => Math.max(1, ...(data.value?.pipeline.stages.map(stage => stage.count) ?? [1])))
const attentionCount = computed(() => data.value
  ? data.value.attention.stuck_applicants.length + data.value.attention.overdue_tasks.length + data.value.attention.missing_documents.length + data.value.attention.contracts_requiring_attention.length + data.value.attention.pending_approvals
  : 0)
const totalSales = computed(() => data.value ? [data.value.sales.scholarships, data.value.sales.test_offers, data.value.sales.academy, data.value.sales.private].reduce((sum, row) => sum + row.sold, 0) : 0)
const visibleStages = computed(() => data.value?.pipeline.stages.filter(stage => stage.count > 0) ?? [])
const topCountries = computed(() => [...(data.value?.countries ?? [])].sort((a, b) => b.total - a.total).slice(0, 6))
const attentionGroups = computed(() => data.value ? [
  { icon: 'tabler-clock-exclamation', color: 'warning', title: t('educational.stuckCases'), rows: data.value.attention.stuck_applicants },
  { icon: 'tabler-calendar-due', color: 'error', title: t('educational.overdueCaseTasks'), rows: data.value.attention.overdue_tasks },
  { icon: 'tabler-file-alert', color: 'info', title: t('educational.missingDocuments'), rows: data.value.attention.missing_documents },
  { icon: 'tabler-file-description', color: 'secondary', title: t('educational.contractAttention'), rows: data.value.attention.contracts_requiring_attention },
] : [])

const money = (rows: Array<{ amount: number; currency: string | null }>) => rows.map(row => `${n(row.amount, 'decimal')} ${row.currency ?? ''}`.trim()).join(' · ') || '—'
const salesName = (name: string) => name === 'private' ? t('educational.builtInServices.private') : t(`educational.salesTypes.${name}`)

async function load() {
  loading.value = true
  try {
    data.value = await DashboardApi.get({ responsible_employee_id: employeeId.value, country_id: countryId.value, university_id: universityId.value, date_from: dateFrom.value || undefined, date_to: dateTo.value || undefined })
  }
  catch { toast.error(t('educational.loadOverviewFailed')) }
  finally { loading.value = false }
}

function applyFilters() {
  filtersOpen.value = false
  load()
}

function clearFilters() {
  employeeId.value = null
  countryId.value = null
  universityId.value = null
  dateFrom.value = ''
  dateTo.value = ''
  applyFilters()
}

function openStage(key: string) { router.push({ name: 'applicants', query: { stage: key } }) }

onMounted(async () => {
  try {
    const [countryRows, universityRows, employeeRows] = await Promise.all([MasterDataApi.list('countries'), MasterDataApi.list('universities'), EmployeeApi.getOptions()])
    countries.value = countryRows
    universities.value = universityRows
    employees.value = employeeRows
  }
  catch { toast.error(t('educational.loadFiltersFailed')) }
  await load()
})
</script>

<template>
  <div class="education-workspace erp-workspace">
    <ErpPageHeader :title="$t('educational.agencyOverview')" section="dashboard" />
    <VCard class="mb-4"><VCardText><VRow dense align="center">
      <VCol cols="12" md="8"><ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" @apply="load" /></VCol>
      <VCol cols="12" md="4"><VBtn variant="tonal" color="secondary" prepend-icon="tabler-adjustments-horizontal" @click="filtersOpen = !filtersOpen">{{ $t('educational.filters') }}</VBtn></VCol>
    </VRow></VCardText></VCard>

    <VExpandTransition>
      <VCard v-if="filtersOpen" class="education-filter-card mb-4" variant="outlined">
        <VCardText>
          <VRow dense>
            <VCol cols="12" sm="6" lg="3"><VSelect v-model="employeeId" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.responsibleEmployee')" clearable hide-details /></VCol>
            <VCol cols="12" sm="6" lg="3"><VSelect v-model="countryId" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" :label="$t('educational.destinationCountry')" clearable hide-details /></VCol>
            <VCol cols="12" sm="6" lg="3"><VSelect v-model="universityId" :items="universities.filter(u => u.is_active).map(u => ({ title: u.name, value: u.id }))" :label="$t('educational.university')" clearable hide-details /></VCol>
          </VRow>
          <div class="d-flex justify-end gap-2 mt-3"><VBtn variant="text" @click="clearFilters">{{ $t('educational.clear') }}</VBtn><VBtn color="primary" :loading="loading" @click="applyFilters">{{ $t('educational.apply') }}</VBtn></div>
        </VCardText>
      </VCard>
    </VExpandTransition>

    <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-3" rounded />

    <template v-if="data">
      <section class="education-metric-grid mb-4">
        <button class="education-metric education-metric--primary" type="button" @click="router.push({ name: 'applicants', query: { status: 'active' } })">
          <VIcon icon="tabler-users-group" /><span><small>{{ $t('educational.activeCasesLabel') }}</small><strong>{{ n(data.pipeline.totals.active) }}</strong></span><VIcon icon="tabler-chevron-right" size="18" />
        </button>
        <button class="education-metric education-metric--warning" type="button" @click="router.push({ name: 'applicants' })">
          <VIcon icon="tabler-alert-triangle" /><span><small>{{ $t('educational.needsAttention') }}</small><strong>{{ n(attentionCount) }}</strong></span><VIcon icon="tabler-chevron-right" size="18" />
        </button>
        <button class="education-metric education-metric--success" type="button" @click="router.push({ name: 'educational-reports' })">
          <VIcon icon="tabler-cash-banknote" /><span><small>{{ $t('educational.postedIncome') }}</small><strong class="education-money">{{ money(data.financial.income) }}</strong></span><VIcon icon="tabler-chevron-right" size="18" />
        </button>
        <button class="education-metric" type="button" @click="router.push({ name: 'scholarship-contracts' })">
          <VIcon icon="tabler-shopping-bag" /><span><small>{{ $t('educational.salesRecorded') }}</small><strong>{{ n(totalSales) }}</strong></span><VIcon icon="tabler-chevron-right" size="18" />
        </button>
      </section>

      <VRow dense>
        <VCol cols="12" lg="8">
          <VCard class="education-panel h-100">
            <VCardItem :title="$t('educational.applicantPipeline')" :subtitle="$t('educational.pipelineHelp')">
              <template #append><VBtn size="small" variant="text" append-icon="tabler-arrow-right" :to="{ name: 'applicant-pipeline' }">{{ $t('educational.openBoard') }}</VBtn></template>
            </VCardItem>
            <VCardText>
              <div v-for="stage in visibleStages" :key="stage.key" class="pipeline-row" role="button" tabindex="0" @click="openStage(stage.key)" @keyup.enter="openStage(stage.key)">
                <div class="pipeline-row__label"><span>{{ stage.name }}</span><strong>{{ n(stage.count) }}</strong></div>
                <VProgressLinear :model-value="stage.count / maxStage * 100" :color="stage.is_terminal ? 'success' : 'primary'" height="7" rounded />
              </div>
              <div v-if="!visibleStages.length" class="education-empty"><VIcon icon="tabler-route-off" size="34" /><span>{{ $t('educational.noActivePipeline') }}</span></div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" lg="4">
          <VCard class="education-panel h-100">
            <VCardItem :title="$t('educational.attentionRequired')" :subtitle="$t('educational.attentionHelp')">
              <template #append><VChip :color="attentionCount ? 'warning' : 'success'" size="small">{{ attentionCount }}</VChip></template>
            </VCardItem>
            <VList class="education-action-list" lines="two">
              <template v-for="group in attentionGroups" :key="group.title">
                <VListItem v-for="hit in group.rows.slice(0, 2)" :key="`${group.title}-${hit.id}`" :title="hit.title" :subtitle="hit.subtitle" :to="hit.route">
                  <template #prepend><VAvatar :color="group.color" variant="tonal" size="34"><VIcon :icon="group.icon" size="18" /></VAvatar></template>
                  <template #append><VIcon icon="tabler-chevron-right" size="17" /></template>
                </VListItem>
              </template>
              <VListItem v-if="!attentionCount" :title="$t('educational.allCaughtUp')" :subtitle="$t('educational.nothingNeedsAttention')"><template #prepend><VAvatar color="success" variant="tonal"><VIcon icon="tabler-circle-check" /></VAvatar></template></VListItem>
            </VList>
          </VCard>
        </VCol>
      </VRow>

      <VRow dense class="mt-1">
        <VCol cols="12" md="6" lg="4">
          <VCard class="education-panel h-100"><VCardItem :title="$t('educational.financialSnapshot')" :subtitle="$t('educational.postedRecordsOnly')" /><VList density="compact">
            <VListItem :title="$t('educational.postedIncome')" :subtitle="money(data.financial.income)"><template #prepend><VIcon color="success" icon="tabler-trending-up" /></template></VListItem>
            <VListItem :title="$t('educational.postedExpenses')" :subtitle="money(data.financial.expenses)"><template #prepend><VIcon color="error" icon="tabler-trending-down" /></template></VListItem>
            <VListItem :title="$t('educational.postedSalaries')" :subtitle="money(data.financial.salaries)"><template #prepend><VIcon color="info" icon="tabler-wallet" /></template></VListItem>
            <VListItem :title="$t('educational.awaitingApproval')" :subtitle="String(data.attention.pending_approvals)"><template #prepend><VIcon color="warning" icon="tabler-hourglass" /></template></VListItem>
          </VList></VCard>
        </VCol>
        <VCol cols="12" md="6" lg="4">
          <VCard class="education-panel h-100"><VCardItem :title="$t('educational.salesByService')" :subtitle="$t('educational.salesHelp')" /><VTable density="compact" class="education-table"><tbody><tr v-for="(row, name) in data.sales" :key="name"><td><strong>{{ salesName(String(name)) }}</strong><div class="text-caption text-medium-emphasis">{{ row.by_currency.map(c => `${n(c.revenue)} ${c.currency ?? ''}`.trim()).join(' · ') || '—' }}</div></td><td class="text-end"><VChip size="small" variant="tonal">{{ row.sold }}</VChip></td></tr></tbody></VTable></VCard>
        </VCol>
        <VCol cols="12" lg="4">
          <VCard class="education-panel h-100"><VCardItem :title="$t('educational.casesByCountry')" :subtitle="$t('educational.countryHelp')" /><VCardText><div v-for="country in topCountries" :key="country.id" class="country-row"><span>{{ country.name }}</span><span class="text-medium-emphasis">{{ country.active }} / {{ country.total }}</span><VProgressLinear :model-value="country.total ? country.active / country.total * 100 : 0" height="5" rounded /></div><div v-if="!topCountries.length" class="education-empty">{{ $t('educational.noCountryData') }}</div></VCardText></VCard>
        </VCol>
      </VRow>

      <VCard class="education-panel mt-4">
        <VCardItem :title="$t('educational.employeePerformance')" :subtitle="$t('educational.performanceHelp')" />
        <div class="education-table-scroll"><VTable density="compact" class="education-table"><thead><tr><th>{{ $t('educational.employee') }}</th><th>{{ $t('educational.activeCases') }}</th><th>{{ $t('educational.servicesReport') }}</th><th>{{ $t('educational.contractsReport') }}</th><th>{{ $t('educational.bonusesGenerated') }}</th></tr></thead><tbody><tr v-for="row in data.staff" :key="row.id"><td class="font-weight-medium">{{ row.name }}</td><td>{{ n(row.active_applicants) }}</td><td>{{ n(row.sales_recorded) }}</td><td>{{ n(row.contracts_sold) }}</td><td>{{ n(row.bonuses_generated) }}</td></tr><tr v-if="!data.staff.length"><td colspan="5" class="education-empty">{{ $t('educational.noStaff') }}</td></tr></tbody></VTable></div>
      </VCard>
    </template>
  </div>
</template>
