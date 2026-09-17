<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import DashboardApi, { type EducationalDashboard } from '@/api/educationalDashboard'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'

const toast = useToast()
const router = useRouter()

const loading = ref(false)
const data = ref<EducationalDashboard | null>(null)

const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])

const employeeId = ref<number | null>(null)
const countryId = ref<number | null>(null)
const universityId = ref<number | null>(null)
const dateFrom = ref('')
const dateTo = ref('')

const maxStage = computed(() => Math.max(1, ...(data.value?.pipeline.stages.map(s => s.count) ?? [1])))

async function load() {
  loading.value = true
  try {
    data.value = await DashboardApi.get({
      responsible_employee_id: employeeId.value,
      country_id: countryId.value,
      university_id: universityId.value,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    })
  }
  catch {
    toast.error('Unable to load the agency overview.')
  }
  finally {
    loading.value = false
  }
}

async function loadCatalogs() {
  try {
    const [countryRows, universityRows, employeeRows] = await Promise.all([
      MasterDataApi.list('countries'),
      MasterDataApi.list('universities'),
      EmployeeApi.getOptions(),
    ])

    countries.value = countryRows
    universities.value = universityRows
    employees.value = employeeRows
  }
  catch {
    toast.error('Unable to load dashboard filters.')
  }
}

function openStage(key: string) {
  router.push({ name: 'applicants', query: { stage: key } })
}

onMounted(async () => {
  await loadCatalogs()
  await load()
})
</script>

<template>
  <VCard>
    <VCardItem title="Agency overview" subtitle="Live counts from cases, sales, tasks, and approvals — no stored counters." />
    <VCardText>
      <VRow dense>
        <VCol cols="12" md="3"><VSelect v-model="employeeId" :items="employees.map(e => ({ title: e.name, value: e.id }))" label="Employee" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="countryId" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" label="Country" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="universityId" :items="universities.filter(u => u.is_active).map(u => ({ title: u.name, value: u.id }))" label="University" clearable /></VCol>
        <VCol cols="6" md="1"><VTextField v-model="dateFrom" type="date" label="From" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="dateTo" type="date" label="To" clearable /></VCol>
      </VRow>
      <VBtn color="primary" :loading="loading" @click="load">Apply</VBtn>
    </VCardText>
    <VProgressLinear v-if="loading" indeterminate />
    <VCardText v-if="data">
      <div class="d-flex gap-2 flex-wrap mb-4">
        <VChip color="success">Active: {{ data.pipeline.totals.active }}</VChip>
        <VChip color="secondary">Closed: {{ data.pipeline.totals.closed }}</VChip>
        <VChip color="primary">All: {{ data.pipeline.totals.all }}</VChip>
        <VChip color="warning">Pending approvals: {{ data.attention.pending_approvals }}</VChip>
      </div>
      <VRow>
        <VCol cols="12" md="6">
          <h3 class="mb-2">Pipeline</h3>
          <div v-for="stage in data.pipeline.stages" :key="stage.key" class="d-flex align-center gap-2 mb-1">
            <span class="text-caption" style="inline-size: 150px;">{{ stage.name }}</span>
            <VProgressLinear :model-value="(stage.count / maxStage) * 100" :color="stage.is_terminal ? 'secondary' : 'primary'" style="flex: 1;" />
            <VBtn size="x-small" variant="text" @click="openStage(stage.key)">{{ stage.count }}</VBtn>
          </div>
        </VCol>
        <VCol cols="12" md="6">
          <h3 class="mb-2">Sales</h3>
          <VTable density="compact">
            <thead><tr><th>Service</th><th>Sold</th><th>Revenue</th></tr></thead>
            <tbody>
              <tr v-for="(row, key) in data.sales" :key="key">
                <td class="text-capitalize">{{ String(key).replace('_', ' ') }}</td>
                <td>{{ row.sold }}</td>
                <td>{{ row.by_currency.map(c => `${c.revenue.toLocaleString()} ${c.currency ?? ''}`.trim()).join(' · ') || '—' }}</td>
              </tr>
            </tbody>
          </VTable>
          <h3 class="mt-4 mb-2">Cases by country</h3>
          <VTable density="compact">
            <thead><tr><th>Country</th><th>Active</th><th>Total</th></tr></thead>
            <tbody>
              <tr v-for="row in data.countries" :key="row.id"><td>{{ row.name }}</td><td>{{ row.active }}</td><td>{{ row.total }}</td></tr>
              <tr v-if="!data.countries.length"><td colspan="3" class="text-center text-medium-emphasis">No country data yet.</td></tr>
            </tbody>
          </VTable>
        </VCol>
      </VRow>
      <h3 class="mt-4 mb-2">Employee performance</h3>
      <VTable density="compact">
        <thead><tr><th>Employee</th><th>Active cases</th><th>Sales recorded</th><th>Sales value</th></tr></thead>
        <tbody>
          <tr v-for="row in data.staff" :key="row.id"><td>{{ row.name }}</td><td>{{ row.active_applicants }}</td><td>{{ row.sales_recorded }}</td><td>{{ row.sales_source_total.toLocaleString() }}</td></tr>
          <tr v-if="!data.staff.length"><td colspan="4" class="text-center text-medium-emphasis">No staff rows.</td></tr>
        </tbody>
      </VTable>
      <h3 class="mt-4 mb-2">Attention required</h3>
      <VRow>
        <VCol cols="12" md="6">
          <h4 class="text-caption text-medium-emphasis mb-1">STUCK CASES (IDLE 14+ DAYS)</h4>
          <VList density="compact">
            <VListItem v-for="hit in data.attention.stuck_applicants" :key="hit.id" :title="hit.title" :subtitle="hit.subtitle" :to="{ name: 'applicant-case', params: { id: hit.id } }" />
          </VList>
          <p v-if="!data.attention.stuck_applicants.length" class="text-caption text-medium-emphasis">Nothing stuck. Good.</p>
        </VCol>
        <VCol cols="12" md="6">
          <h4 class="text-caption text-medium-emphasis mb-1">OVERDUE CASE TASKS</h4>
          <VList density="compact">
            <VListItem v-for="hit in data.attention.overdue_tasks" :key="hit.id" :title="hit.title" :subtitle="hit.subtitle" :to="{ name: 'applicant-case', params: { id: hit.route.params?.id } }" />
          </VList>
          <p v-if="!data.attention.overdue_tasks.length" class="text-caption text-medium-emphasis">No overdue case tasks.</p>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
