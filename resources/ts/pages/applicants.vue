<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import ApplicantApi, { type Applicant, type ApplicantBoard, type ApplicantStage } from '@/api/applicants'
import CustomerApi, { type Customer } from '@/api/customer'
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
const stages = ref<ApplicantStage[]>([])
const customers = ref<Customer[]>([])

const canViewPipeline = computed(() => canAccess('view_applicants'))
const canCreate = computed(() => canAccess('create_applicants'))

const createDialog = ref(false)
const creating = ref(false)
const createForm = ref({ customer_id: null as number | null, responsible_employee_id: null as number | null, country_id: null as number | null, university_id: null as number | null, education_level_id: null as number | null, language_id: null as number | null, application_date: '', notes: '' })
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
    const [countryRows, universityRows, levelRows, languageRows, employeeRows, stageRows, customerResponse] = await Promise.all([
      MasterDataApi.list('countries'),
      MasterDataApi.list('universities'),
      MasterDataApi.list('education-levels'),
      MasterDataApi.list('languages'),
      EmployeeApi.getOptions(),
      ApplicantApi.stages(),
      CustomerApi.getAll({}),
    ])

    countries.value = countryRows
    universities.value = universityRows
    levels.value = levelRows
    languages.value = languageRows
    employees.value = employeeRows
    stages.value = stageRows
    customers.value = customerResponse.data
  }
  catch {
    toast.error('Unable to load applicant filters.')
  }
}

function onTabChange() {
  page.value = 1
  load()
}

function openCreate() {
  createForm.value = { customer_id: null, responsible_employee_id: null, country_id: null, university_id: null, education_level_id: null, language_id: null, application_date: '', notes: '' }
  createDialog.value = true
}

async function create() {
  if (!createForm.value.customer_id)
    return

  creating.value = true
  try {
    const payload: Record<string, unknown> = { customer_id: createForm.value.customer_id }

    for (const key of ['responsible_employee_id', 'country_id', 'university_id', 'education_level_id', 'language_id'] as const) {
      if (createForm.value[key])
        payload[key] = createForm.value[key]
    }

    if (createForm.value.application_date)
      payload.application_date = createForm.value.application_date

    if (createForm.value.notes.trim())
      payload.notes = createForm.value.notes.trim()

    const created = await ApplicantApi.create(payload)

    createDialog.value = false
    await load()
    toast.success(`Case #${created.id} created in New stage.`)
    router.push({ name: 'applicant-case', params: { id: created.id } })
  }
  catch {
    toast.error('Unable to create the case.')
  }
  finally {
    creating.value = false
  }
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

// Pagination controls call load() explicitly; no page watcher, so changing
// pages never fires duplicate requests.
watch(countryId, () => {
  const stillVisible = universities.value
    .filter(u => u.is_active && (!countryId.value || u.country_id === countryId.value))
    .some(u => u.id === universityId.value)

  if (!stillVisible)
    universityId.value = null
})

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
      <VBtn v-if="canCreate" size="small" color="primary" prepend-icon="tabler-plus" @click="openCreate">New case</VBtn>
      <VBtn v-if="canViewPipeline" size="small" color="primary" variant="tonal" :to="{ name: 'applicant-pipeline' }">Pipeline board</VBtn>
    </VCardTitle>
    <VCardText>
      <VTabs v-model="tab" @update:model-value="onTabChange">
        <VTab v-for="item in tabs" :key="item.title" :value="item.value">{{ item.title }}</VTab>
      </VTabs>
      <VRow dense class="mt-4">
        <VCol cols="12" md="3"><VTextField v-model="search" label="Search name, phone, email, ID" clearable @keyup.enter="page = 1; load()" /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="stage" :items="stages.map(s => ({ title: s.name, value: s.key }))" label="Stage" clearable @update:model-value="page = 1; load()" /></VCol>
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
      <VPagination v-model="page" :length="Math.max(lastPage, 1)" size="small" @update:model-value="load" />
      <VSelect v-model="perPage" :items="[10, 25, 50, 100]" label="Per page" style="max-inline-size: 140px;" @update:model-value="page = 1; load()" />
    </VCardText>
  </VCard>
  <VDialog v-model="createDialog" max-width="600">
    <VCard title="New applicant case">
      <VCardText>
        <VSelect v-model="createForm.customer_id" :items="customers.map(c => ({ title: c.name, value: c.id }))" label="Customer (payer)" class="mb-3" />
        <VSelect v-model="createForm.responsible_employee_id" :items="employees.map(e => ({ title: e.name, value: e.id }))" label="Responsible employee" clearable class="mb-3" />
        <VRow dense>
          <VCol cols="6"><VSelect v-model="createForm.country_id" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" label="Destination country" clearable class="mb-3" /></VCol>
          <VCol cols="6"><VSelect v-model="createForm.university_id" :items="universities.filter(u => u.is_active).map(u => ({ title: u.name, value: u.id }))" label="University" clearable class="mb-3" /></VCol>
          <VCol cols="6"><VSelect v-model="createForm.education_level_id" :items="levels.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" label="Education level" clearable class="mb-3" /></VCol>
          <VCol cols="6"><VSelect v-model="createForm.language_id" :items="languages.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" label="Language" clearable class="mb-3" /></VCol>
        </VRow>
        <VTextField v-model="createForm.application_date" type="date" label="Application date" clearable class="mb-3" />
        <VTextarea v-model="createForm.notes" label="Notes" />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="createDialog = false">Cancel</VBtn>
        <VBtn color="primary" :loading="creating" :disabled="!createForm.customer_id" @click="create">Create case</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
