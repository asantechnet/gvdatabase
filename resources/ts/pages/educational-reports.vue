<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import ReportApi, { type EducationalReport, type EducationalReportType } from '@/api/educationalReports'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'

const toast = useToast()

const tab = ref<EducationalReportType>('applicants')
const report = ref<EducationalReport | null>(null)
const loading = ref(false)
const exporting = ref(false)
const page = ref(1)

const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const levels = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])

const countryId = ref<number | ''>('')
const universityId = ref<number | ''>('')
const levelId = ref<number | ''>('')
const employeeId = ref<number | ''>('')
const stage = ref('')
const status = ref<'active' | 'closed' | ''>('')
const dateFrom = ref('')
const dateTo = ref('')

const canExport = computed(() => canAccess('export_reports'))
const tabs: Array<{ value: EducationalReportType; title: string }> = [
  { value: 'applicants', title: 'Applicants' },
  { value: 'employee-performance', title: 'Employee performance' },
  { value: 'sales', title: 'Sales' },
  { value: 'visa-outcomes', title: 'Visa outcomes' },
]

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
    toast.error('Unable to load the report.')
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
    toast.success('Report exported.')
  }
  catch {
    toast.error('Unable to export the report.')
  }
  finally {
    exporting.value = false
  }
}

function onTabChange() {
  page.value = 1
  load()
}

async function loadCatalogs() {
  try {
    const [countryRows, universityRows, levelRows, employeeRows] = await Promise.all([
      MasterDataApi.list('countries'),
      MasterDataApi.list('universities'),
      MasterDataApi.list('education-levels'),
      EmployeeApi.getOptions(),
    ])

    countries.value = countryRows
    universities.value = universityRows
    levels.value = levelRows
    employees.value = employeeRows
  }
  catch {
    toast.error('Unable to load report filters.')
  }
}

onMounted(async () => {
  await loadCatalogs()
  await load()
})
</script>

<template>
  <VCard>
    <VCardItem title="Visa reports" subtitle="Tabular detail behind the overview, with CSV export.">
      <template #append><VBtn v-if="canExport" color="primary" :loading="exporting" prepend-icon="tabler-download" @click="download">Export CSV</VBtn></template>
    </VCardItem>
    <VCardText>
      <VTabs v-model="tab" @update:model-value="onTabChange">
        <VTab v-for="item in tabs" :key="item.value" :value="item.value">{{ item.title }}</VTab>
      </VTabs>
      <VRow dense class="mt-4">
        <VCol cols="12" md="2"><VSelect v-model="countryId" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" label="Country" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="universityId" :items="universities.filter(u => u.is_active).map(u => ({ title: u.name, value: u.id }))" label="University" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="levelId" :items="levels.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" label="Education level" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="employeeId" :items="employees.map(e => ({ title: e.name, value: e.id }))" label="Employee" clearable /></VCol>
        <VCol v-if="tab === 'applicants'" cols="12" md="2"><VTextField v-model="stage" label="Stage key" clearable /></VCol>
        <VCol v-if="tab === 'applicants'" cols="12" md="2"><VSelect v-model="status" :items="[{ title: 'Active', value: 'active' }, { title: 'Closed', value: 'closed' }]" label="Status" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="dateFrom" type="date" label="From" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="dateTo" type="date" label="To" clearable /></VCol>
      </VRow>
      <div class="d-flex gap-2 mt-2">
        <VBtn color="primary" :loading="loading" @click="page = 1; load()">Apply</VBtn>
        <VBtn v-if="!canExport" variant="text" disabled>CSV export requires permission</VBtn>
        <VSpacer />
        <span class="text-caption text-medium-emphasis align-self-center">{{ report?.total ?? 0 }} row{{ (report?.total ?? 0) === 1 ? '' : 's' }}</span>
      </div>
    </VCardText>
    <VProgressLinear v-if="loading" indeterminate />
    <VTable v-if="report">
      <thead><tr><th v-for="column in report.columns" :key="column" class="text-capitalize">{{ column.replaceAll('_', ' ') }}</th></tr></thead>
      <tbody>
        <tr v-for="(row, index) in report.rows" :key="index"><td v-for="column in report.columns" :key="column">{{ row[column] }}</td></tr>
        <tr v-if="!report.rows.length"><td :colspan="report.columns.length || 1" class="text-center text-medium-emphasis py-8">No rows match these filters.</td></tr>
      </tbody>
    </VTable>
    <VCardText v-if="report?.last_page && report.last_page > 1" class="d-flex">
      <VPagination v-model="page" :length="report.last_page" size="small" @update:model-value="load" />
    </VCardText>
  </VCard>
</template>
