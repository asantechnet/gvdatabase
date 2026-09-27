<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { type Applicant, type ApplicantBoard, type ApplicantStage } from '@/api/applicants'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'
import { translateBackendValue } from '@/utils/localization'
import { apiErrorMessage } from '@/utils/apiErrors'

const toast = useToast()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

type Tab = '' | ApplicantBoard

const tab = ref<Tab>('operation')
const rows = ref<Applicant[]>([])
const loading = ref(false)
const page = ref(1)
const lastPage = ref(1)
const perPage = ref(25)
const total = ref(0)
const filtersOpen = ref(false)

const search = ref((route.query.search as string) ?? '')
const stage = ref((route.query.stage as string) ?? '')
const status = ref<'active' | 'closed' | ''>((route.query.status as 'active' | 'closed') ?? '')
const responsibleEmployeeId = ref<number | null>(null)
const countryId = ref<number | null>(null)
const universityId = ref<number | null>(null)
const educationLevelId = ref<number | null>(null)
const languageId = ref<number | null>(null)
const semesterId = ref<number | null>(null)
const dateFrom = ref('')
const dateTo = ref('')

const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const levels = ref<MasterDataRecord[]>([])
const languages = ref<MasterDataRecord[]>([])
const semesters = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])
const stages = ref<ApplicantStage[]>([])

const canViewPipeline = computed(() => canAccess('view_applicants'))
const canCreate = computed(() => canAccess('create_applicants'))
const canAssignEmployee = computed(() => canAccess('assign_applicant_employee'))
const activeFilterCount = computed(() => [search.value, stage.value, status.value, responsibleEmployeeId.value, countryId.value, universityId.value, educationLevelId.value, languageId.value, semesterId.value, dateFrom.value, dateTo.value].filter(Boolean).length)

const createDialog = ref(false)
const creating = ref(false)
const createForm = ref({ name: '', contact: '', email: '', responsible_employee_id: null as number | null, country_id: null as number | null, university_id: null as number | null, education_level_id: null as number | null, language_id: null as number | null, semester_id: null as number | null, application_date: '', notes: '' })
const createUniversityOptions = computed(() => universities.value
  .filter(university => university.is_active && university.country_id === createForm.value.country_id)
  .map(university => ({ title: university.name, value: university.id })))
const tabs: Array<{ value: Tab; title: string }> = [
  { value: 'operation', title: t('educational.operationList') },
  { value: 'final', title: t('educational.finalList') },
  { value: 'cancelled', title: t('educational.cancelList') },
  { value: '', title: t('educational.allApplicants') },
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
      semester_id: semesterId.value,
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
    toast.error(t('educational.messages.loadApplicantsFailed'))
  }
  finally {
    loading.value = false
  }
}

async function loadCatalogs() {
  try {
    const [countryRows, universityRows, levelRows, languageRows, semesterRows, employeeRows, stageRows] = await Promise.all([
      MasterDataApi.list('countries'),
      MasterDataApi.list('universities'),
      MasterDataApi.list('education-levels'),
      MasterDataApi.list('languages'),
      MasterDataApi.list('semesters'),
      EmployeeApi.getOptions(),
      ApplicantApi.stages(),
    ])

    countries.value = countryRows
    universities.value = universityRows
    levels.value = levelRows
    languages.value = languageRows
    semesters.value = semesterRows
    employees.value = employeeRows
    stages.value = stageRows
  }
  catch {
    toast.error(t('educational.messages.loadFiltersFailed'))
  }
}

function onTabChange() {
  page.value = 1
  load()
}

function openCreate() {
  createForm.value = { name: '', contact: '', email: '', responsible_employee_id: null, country_id: null, university_id: null, education_level_id: null, language_id: null, semester_id: null, application_date: '', notes: '' }
  createDialog.value = true
}

async function create() {
  if (!createForm.value.name.trim())
    return

  creating.value = true
  try {
    const payload: Record<string, unknown> = { name: createForm.value.name.trim(), contact: createForm.value.contact || null, email: createForm.value.email || null }

    for (const key of ['responsible_employee_id', 'country_id', 'university_id', 'education_level_id', 'language_id', 'semester_id'] as const) {
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
    toast.success(t('educational.messages.caseCreated', { id: created.id }))
    router.push({ name: 'applicant-case', params: { id: created.id } })
  }
  catch (error) {
    toast.error(apiErrorMessage(error, t('educational.messages.createCaseFailed')))
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
  semesterId.value = null
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

watch(() => createForm.value.country_id, () => {
  if (!createForm.value.university_id)
    return

  const selectedUniversity = universities.value.find(university => university.id === createForm.value.university_id)
  if (!selectedUniversity || selectedUniversity.country_id !== createForm.value.country_id)
    createForm.value.university_id = null
})

onMounted(async () => {
  await loadCatalogs()
  await load()
})
</script>

<template>
  <div class="education-workspace">
    <header class="education-page-header">
      <div><div class="education-eyebrow">{{ $t('educational.caseManagement') }}</div><h1>{{ $t('educational.applicantCases') }}</h1><p>{{ $t('educational.applicantCasesHelp') }}</p></div>
      <div class="d-flex flex-wrap gap-2"><VBtn variant="tonal" prepend-icon="tabler-adjustments-horizontal" @click="filtersOpen = !filtersOpen">{{ $t('educational.filters') }}<VBadge v-if="activeFilterCount" :content="activeFilterCount" color="primary" inline /></VBtn><VBtn v-if="canViewPipeline" color="primary" variant="tonal" prepend-icon="tabler-layout-kanban" :to="{ name: 'applicant-pipeline' }">{{ $t('educational.pipelineBoard') }}</VBtn><VBtn v-if="canCreate" color="primary" prepend-icon="tabler-plus" @click="openCreate">{{ $t('educational.newCase') }}</VBtn></div>
    </header>
  <VCard class="education-panel">
    <VTabs v-model="tab" show-arrows class="px-3" @update:model-value="onTabChange">
      <VTab v-for="item in tabs" :key="item.title" :value="item.value">{{ item.title }}</VTab>
    </VTabs>
    <VDivider />
    <VExpandTransition><VCardText v-if="filtersOpen" class="education-filter-card rounded-0 border-0">
      <VRow dense class="mt-4">
        <VCol cols="12" md="3"><VTextField v-model="search" :label="$t('educational.searchApplicant')" clearable @keyup.enter="page = 1; load()" /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="stage" :items="stages.map(s => ({ title: s.name, value: s.key }))" :label="$t('educational.stage')" clearable @update:model-value="page = 1; load()" /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="status" :items="[{ title: $t('educational.active'), value: 'active' }, { title: $t('educational.closed'), value: 'closed' }]" :label="$t('educational.status')" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="responsibleEmployeeId" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.employee')" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="countryId" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" :label="$t('educational.country')" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="universityId" :items="universities.filter(u => u.is_active).map(u => ({ title: u.name, value: u.id }))" :label="$t('educational.university')" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="educationLevelId" :items="levels.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.educationLevel')" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="languageId" :items="languages.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.language')" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="semesterId" :items="semesters.filter(s => s.is_active).map(s => ({ title: s.name, value: s.id }))" :label="$t('educational.semester')" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="dateFrom" type="date" :label="$t('educational.from')" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="dateTo" type="date" :label="$t('educational.to')" clearable /></VCol>
      </VRow>
      <div class="d-flex gap-2 mt-2">
        <VBtn color="primary" :loading="loading" @click="page = 1; load()">{{ $t('educational.apply') }}</VBtn>
        <VBtn variant="text" @click="clearFilters">{{ $t('educational.clear') }}</VBtn>
        <VSpacer />
        <span class="text-caption text-medium-emphasis align-self-center">{{ $t('educational.caseCount', { count: total }) }}</span>
      </div>
    </VCardText></VExpandTransition>
    <div class="d-flex align-center gap-2 px-4 py-3"><VIcon icon="tabler-users" color="primary" /><strong>{{ $t('educational.resultCount', { count: total }) }}</strong><VSpacer /><VBtn v-if="activeFilterCount" size="small" variant="text" prepend-icon="tabler-filter" @click="filtersOpen = true">{{ $t('educational.activeFilters', { count: activeFilterCount }) }}</VBtn></div>
    <VProgressLinear v-if="loading" indeterminate />
    <div class="education-table-scroll"><VTable density="compact" class="education-table">
      <thead><tr><th>{{ $t('educational.id') }}</th><th>{{ $t('educational.applicant') }}</th><th>{{ $t('educational.caseUi.file') }}</th><th>{{ $t('educational.employee') }}</th><th>{{ $t('educational.destination') }}</th><th>{{ $t('educational.stage') }}</th><th>{{ $t('educational.status') }}</th><th /></tr></thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id" class="cursor-pointer" @click="router.push({ name: 'applicant-case', params: { id: row.id } })">
          <td>#{{ row.id }}</td>
          <td>{{ row.name }}</td>
          <td><VChip size="x-small" :color="row.attachments_count ? 'success' : 'warning'" variant="tonal">{{ row.attachments_count ? $t('educational.caseUi.documentCount', { count: row.attachments_count }) : $t('educational.caseUi.documentsNeeded') }}</VChip></td>
          <td>{{ row.responsible_employee?.name ?? $t('educational.unassigned') }}</td>
          <td>{{ row.country?.name ?? '—' }}{{ row.university?.name ? ` · ${row.university.name}` : '' }}</td>
          <td>{{ row.current_stage?.name ?? '—' }}</td>
          <td><VChip size="small" :color="row.status === 'active' ? 'success' : 'secondary'">{{ translateBackendValue(row.status) }}</VChip></td>
          <td class="text-end"><VBtn size="small" variant="text" @click="router.push({ name: 'applicant-case', params: { id: row.id } })">{{ $t('educational.openCase') }}</VBtn></td>
        </tr>
        <tr v-if="!loading && !rows.length"><td colspan="8" class="text-center text-medium-emphasis py-8">{{ $t('educational.noApplicants') }}</td></tr>
      </tbody>
    </VTable></div>
    <VCardText class="d-flex align-center gap-4">
      <VPagination v-model="page" :length="Math.max(lastPage, 1)" size="small" @update:model-value="load" />
      <VSelect v-model="perPage" :items="[10, 25, 50, 100]" :label="$t('educational.perPage')" style="max-inline-size: 140px;" @update:model-value="page = 1; load()" />
    </VCardText>
  </VCard>
  </div>
  <VDialog v-model="createDialog" max-width="600">
    <VCard :title="$t('educational.newApplicantCase')">
      <VCardText>
        <VTextField v-model="createForm.name" :label="$t('common.fields.name')" class="mb-3" />
        <VTextField v-model="createForm.contact" :label="$t('common.messages.contact')" class="mb-3" />
        <VTextField v-model="createForm.email" :label="$t('common.fields.email')" type="email" class="mb-3" />
        <VSelect v-if="canAssignEmployee" v-model="createForm.responsible_employee_id" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.responsibleEmployee')" clearable class="mb-3" />
        <VRow dense>
          <VCol cols="6"><VSelect v-model="createForm.country_id" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" :label="$t('educational.destinationCountry')" clearable class="mb-3" /></VCol>
          <VCol cols="6"><VSelect v-model="createForm.university_id" :items="createUniversityOptions" :label="$t('educational.university')" :disabled="!createForm.country_id" clearable class="mb-3" /></VCol>
          <VCol cols="6"><VSelect v-model="createForm.education_level_id" :items="levels.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.educationLevel')" clearable class="mb-3" /></VCol>
          <VCol cols="6"><VSelect v-model="createForm.language_id" :items="languages.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.language')" clearable class="mb-3" /></VCol>
          <VCol cols="6"><VSelect v-model="createForm.semester_id" :items="semesters.filter(s => s.is_active).map(s => ({ title: s.name, value: s.id }))" :label="$t('educational.semester')" clearable class="mb-3" /></VCol>
        </VRow>
        <VTextField v-model="createForm.application_date" type="date" :label="$t('educational.applicationDate')" clearable class="mb-3" />
        <VTextarea v-model="createForm.notes" :label="$t('educational.notes')" />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="createDialog = false">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="creating" :disabled="!createForm.name.trim()" @click="create">{{ $t('educational.createCase') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
