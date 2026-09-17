<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import ApplicantApi, { type Applicant, type ApplicantBoard } from '@/api/applicants'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const router = useRouter()
const route = useRoute()

type Tab = '' | ApplicantBoard

const tab = ref<Tab>('operation')
const rows = ref<Applicant[]>([])
const loading = ref(false)
const page = ref(1)
const lastPage = ref(1)
const perPage = ref(25)
const total = ref(0)

const search = ref((route.query.search as string) ?? '')
const stage = ref((route.query.stage as string) ?? '')
const status = ref<'active' | 'closed' | ''>((route.query.status as 'active' | 'closed') ?? '')
const responsibleEmployeeId = ref<number | null>(null)
const countryId = ref<number | null>(null)
const universityId = ref<number | null>(null)
const educationLevelId = ref<number | null>(null)
const languageId = ref<number | null>(null)
const dateFrom = ref('')
const dateTo = ref('')

const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const levels = ref<MasterDataRecord[]>([])
const languages = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])

const canViewPipeline = computed(() => canAccess('view_applicants'))
const tabs: Array<{ value: Tab; title: string }> = [
  { value: 'operation', title: 'Operation List' },
  { value: 'final', title: 'Final List' },
  { value: 'cancelled', title: 'Cancel List' },
  { value: '', title: 'All Applicants' },
]

async function load() {
  loading.value = true
  try {
    const result = await ApplicantApi.list({
      board: (tab.value || undefined) as ApplicantBoard | undefined,
      stage: stage.value || undefined,
      status: (status.value || undefined) as 'active' | 'closed' | undefined,
      search: search.value || undefined,
      responsible_employee_id: responsibleEmployeeId.value,
      country_id: countryId.value,
      university_id: universityId.value,
      education_level_id: educationLevelId.value,
      language_id: languageId.value,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })

    rows.value = result.data
    page.value = result.current_page
    lastPage.value = result.last_page
    perPage.value = result.per_page
    total.value = result.total
  }
  catch {
    toast.error('Unable to load applicants.')
  }
  finally {
    loading.value = false
  }
}

async function loadCatalogs() {
  try {
    const [countryRows, universityRows, levelRows, languageRows, employeeRows] = await Promise.all([
      MasterDataApi.list('countries'),
      MasterDataApi.list('universities'),
      MasterDataApi.list('education-levels'),
      MasterDataApi.list('languages'),
      EmployeeApi.getOptions(),
    ])

    countries.value = countryRows
    universities.value = universityRows
    levels.value = levelRows
    languages.value = languageRows
    employees.value = employeeRows
  }
  catch {
    toast.error('Unable to load applicant filters.')
  }
}

function onTabChange() {
  page.value = 1
  load()
}

function clearFilters() {
  search.value = ''
  stage.value = ''
  status.value = ''
  responsibleEmployeeId.value = null
  countryId.value = null
  universityId.value = null
  educationLevelId.value = null
  languageId.value = null
  dateFrom.value = ''
  dateTo.value = ''
  page.value = 1
  load()
}

watch([page, perPage], load)
onMounted(async () => {
  await loadCatalogs()
  await load()
})
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex flex-wrap align-center gap-2">
      <span>Applicants / Visa Cases</span>
      <VSpacer />
      <VBtn v-if="canViewPipeline" size="small" color="primary" variant="tonal" :to="{ name: 'applicant-pipeline' }">Pipeline board</VBtn>
    </VCardTitle>
    <VCardText>
      <VTabs v-model="tab" @update:model-value="onTabChange">
        <VTab v-for="item in tabs" :key="item.title" :value="item.value">{{ item.title }}</VTab>
      </VTabs>
      <VRow dense class="mt-4">
        <VCol cols="12" md="3"><VTextField v-model="search" label="Search name, phone, email, ID" clearable @keyup.enter="page = 1; load()" /></VCol>
        <VCol cols="12" md="3"><VTextField v-model="stage" label="Stage key (e.g. consultation)" clearable @keyup.enter="page = 1; load()" /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="status" :items="[{ title: 'Active', value: 'active' }, { title: 'Closed', value: 'closed' }]" label="Status" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="responsibleEmployeeId" :items="employees.map(e => ({ title: e.name, value: e.id }))" label="Employee" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="countryId" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" label="Country" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="universityId" :items="universities.filter(u => u.is_active).map(u => ({ title: u.name, value: u.id }))" label="University" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="educationLevelId" :items="levels.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" label="Education level" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="languageId" :items="languages.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" label="Language" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="dateFrom" type="date" label="From" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="dateTo" type="date" label="To" clearable /></VCol>
      </VRow>
      <div class="d-flex gap-2 mt-2">
        <VBtn color="primary" :loading="loading" @click="page = 1; load()">Apply</VBtn>
        <VBtn variant="text" @click="clearFilters">Clear</VBtn>
        <VSpacer />
        <span class="text-caption text-medium-emphasis align-self-center">{{ total }} case{{ total === 1 ? '' : 's' }}</span>
      </div>
    </VCardText>
    <VProgressLinear v-if="loading" indeterminate />
    <VTable>
      <thead><tr><th>ID</th><th>Customer</th><th>Employee</th><th>Country / University</th><th>Stage</th><th>Status</th><th /></tr></thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>#{{ row.id }}</td>
          <td>{{ row.customer?.name ?? '—' }}</td>
          <td>{{ row.responsible_employee?.name ?? 'Unassigned' }}</td>
          <td>{{ row.country?.name ?? '—' }}{{ row.university?.name ? ` · ${row.university.name}` : '' }}</td>
          <td>{{ row.current_stage?.name ?? '—' }}</td>
          <td><VChip size="small" :color="row.status === 'active' ? 'success' : 'secondary'">{{ row.status }}</VChip></td>
          <td class="text-end"><VBtn size="small" variant="text" @click="router.push({ name: 'applicant-case', params: { id: row.id } })">Open case</VBtn></td>
        </tr>
        <tr v-if="!loading && !rows.length"><td colspan="7" class="text-center text-medium-emphasis py-8">No applicants match these filters.</td></tr>
      </tbody>
    </VTable>
    <VCardText class="d-flex align-center gap-4">
      <VPagination v-model="page" :length="Math.max(lastPage, 1)" size="small" />
      <VSelect v-model="perPage" :items="[10, 25, 50, 100]" label="Per page" style="max-inline-size: 140px;" @update:model-value="page = 1; load()" />
    </VCardText>
  </VCard>
</template>
