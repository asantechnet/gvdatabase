<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import ApplicantApi, { type Applicant, type ApplicantListParams, type PipelineColumn } from '@/api/applicants'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const router = useRouter()

const loading = ref(false)
const movingId = ref<number | null>(null)
const dragId = ref<number | null>(null)
const stages = ref<PipelineColumn[]>([])
const cards = ref<Record<string, Applicant[]>>({})
const totals = ref({ active: 0, closed: 0, all: 0 })

const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const levels = ref<MasterDataRecord[]>([])
const languages = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])

const filters = ref<ApplicantListParams>({ search: '', status: undefined, responsible_employee_id: null, country_id: null, university_id: null, education_level_id: null, language_id: null, date_from: '', date_to: '' })

const canMove = computed(() => canAccess('change_applicant_stage'))
const activeFilterCount = computed(() => Object.values(filters.value).filter(v => v !== '' && v !== null && v !== undefined).length)
const perColumnCap = 30

const activeCountries = computed(() => countries.value.filter(c => c.is_active))
const activeUniversities = computed(() => universities.value.filter(u => u.is_active && (!filters.value.country_id || u.country_id === filters.value.country_id)))
const activeLevels = computed(() => levels.value.filter(l => l.is_active))
const activeLanguages = computed(() => languages.value.filter(l => l.is_active))

// A university selected for one country must not silently filter everything
// out after the country changes.
watch(() => filters.value.country_id, () => {
  const stillVisible = activeUniversities.value.some(u => u.id === filters.value.university_id)

  if (!stillVisible)
    filters.value.university_id = null
})

function stageOf(applicant: Applicant) {
  return stages.value.find(s => s.id === applicant.current_stage_id)
}

function allowedTargets(applicant: Applicant) {
  const current = stageOf(applicant)

  if (!current?.allowed_next_keys?.length)
    return []

  return stages.value.filter(s => current.allowed_next_keys!.includes(s.key))
}

function dropAllowed(applicant: Applicant, targetKey: string) {
  const current = stageOf(applicant)

  if (!current)
    return false

  return (current.allowed_next_keys ?? []).includes(targetKey)
}

async function load() {
  loading.value = true
  try {
    const params = { ...filters.value, per_page: 200 } as ApplicantListParams
    const [pipeline, list] = await Promise.all([ApplicantApi.pipeline(params), ApplicantApi.list(params)])

    stages.value = [...pipeline.stages].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
    totals.value = pipeline.totals

    const grouped: Record<string, Applicant[]> = {}

    for (const stage of stages.value)
      grouped[stage.key] = []

    for (const row of list.data) {
      const key = row.current_stage?.key ?? stages.value.find(s => s.id === row.current_stage_id)?.key ?? 'new'

      if (!grouped[key])
        grouped[key] = []

      if (grouped[key].length < perColumnCap)
        grouped[key].push(row)
    }

    cards.value = grouped
  }
  catch {
    toast.error('Unable to load the applicant pipeline.')
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
    toast.error('Unable to load pipeline filters.')
  }
}

function onDragStart(applicant: Applicant) {
  dragId.value = applicant.id
}

async function onDrop(target: PipelineColumn) {
  const id = dragId.value

  dragId.value = null

  if (!id)
    return

  await moveApplicant(id, target)
}

async function moveApplicant(id: number, target: PipelineColumn) {
  const applicant = Object.values(cards.value).flat().find(a => a.id === id)

  if (!applicant)
    return

  if (applicant.current_stage_id === target.id)
    return

  if (!canMove.value) {
    toast.error('You do not have permission to move applicant stages.')

    return
  }

  if (!dropAllowed(applicant, target.key)) {
    const from = stageOf(applicant)?.name ?? 'current stage'

    toast.error(`Cannot move from ${from} to ${target.name}. Only the highlighted stages are allowed.`)

    return
  }

  movingId.value = id
  try {
    await ApplicantApi.changeStage(id, target.id, `Pipeline move to ${target.name}`)
    toast.success(`Moved to ${target.name}.`)
    await load()
  }
  catch {
    toast.error('This stage transition is not allowed.')
  }
  finally {
    movingId.value = null
  }
}

function clearFilters() {
  filters.value = { search: '', status: undefined, responsible_employee_id: null, country_id: null, university_id: null, education_level_id: null, language_id: null, date_from: '', date_to: '' }
  load()
}

function openCase(id: number) {
  router.push({ name: 'applicant-case', params: { id } })
}

onMounted(async () => {
  await loadCatalogs()
  await load()
})
</script>

<template>
  <VCard>
    <VCardItem title="Applicant pipeline" subtitle="Drag a card to the next allowed stage. Counts respect the active filters.">
      <template #append>
        <VChip color="success" size="small" class="me-2">Active: {{ totals.active }}</VChip>
        <VChip color="secondary" size="small" class="me-2">Closed: {{ totals.closed }}</VChip>
        <VChip color="primary" size="small">All: {{ totals.all }}</VChip>
      </template>
    </VCardItem>
    <VCardText>
      <VRow dense>
        <VCol cols="12" md="3"><VTextField v-model="filters.search" label="Search name, phone, email, ID" clearable @keyup.enter="load" /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.responsible_employee_id" :items="employees.map(e => ({ title: e.name, value: e.id }))" label="Employee" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.country_id" :items="activeCountries.map(c => ({ title: c.name, value: c.id }))" label="Country" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.university_id" :items="activeUniversities.map(u => ({ title: u.name, value: u.id }))" label="University" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.education_level_id" :items="activeLevels.map(l => ({ title: l.name, value: l.id }))" label="Education level" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.language_id" :items="activeLanguages.map(l => ({ title: l.name, value: l.id }))" label="Language" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="filters.status" :items="[{ title: 'Active', value: 'active' }, { title: 'Closed', value: 'closed' }]" label="Status" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="filters.date_from" type="date" label="From" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="filters.date_to" type="date" label="To" clearable /></VCol>
      </VRow>
      <div class="d-flex gap-2 mt-2">
        <VBtn color="primary" :loading="loading" @click="load">Apply filters{{ activeFilterCount ? ` (${activeFilterCount})` : '' }}</VBtn>
        <VBtn variant="text" @click="clearFilters">Clear</VBtn>
        <VBtn v-if="!canMove" variant="text" disabled>Stage moves require permission</VBtn>
      </div>
    </VCardText>
    <VProgressLinear v-if="loading" indeterminate />
    <VCardText>
      <div v-if="!loading && !stages.length" class="text-center text-medium-emphasis py-8">No active applicant stages.</div>
      <div v-else class="d-flex gap-3 overflow-x-auto pb-2">
        <VCard v-for="stage in stages" :key="stage.key" variant="outlined" min-width="260" class="flex-shrink-0" style="inline-size: 260px;" @dragover.prevent @drop="onDrop(stage)">
          <VCardItem :title="stage.name" :subtitle="`${stage.count} case${stage.count === 1 ? '' : 's'}`">
            <template #append><VChip size="small" :color="stage.is_terminal ? 'secondary' : 'primary'">{{ stage.count }}</VChip></template>
          </VCardItem>
          <VCardText class="d-flex flex-column gap-2" style="min-block-size: 120px;">
            <VCard v-for="applicant in (cards[stage.key] || [])" :key="applicant.id" variant="tonal" :draggable="canMove && movingId !== applicant.id" @dragstart="onDragStart(applicant)">
              <VCardText class="py-2">
                <div class="font-weight-medium">{{ applicant.customer?.name ?? `Applicant #${applicant.id}` }}</div>
                <div class="text-caption text-medium-emphasis">#{{ applicant.id }} · {{ applicant.status }}</div>
                <div class="text-caption">{{ applicant.country?.name ?? '—' }}{{ applicant.university?.name ? ` · ${applicant.university.name}` : '' }}</div>
                <div class="text-caption text-medium-emphasis">{{ applicant.responsible_employee?.name ?? 'Unassigned' }}</div>
                <div class="d-flex gap-1 mt-2">
                  <VBtn size="x-small" variant="text" @click="openCase(applicant.id)">Open case</VBtn>
                  <VMenu v-if="canMove && allowedTargets(applicant).length">
                    <template #activator="{ props }"><VBtn size="x-small" variant="text" color="primary" v-bind="props">Move</VBtn></template>
                    <VList density="compact">
                      <VListItem v-for="target in allowedTargets(applicant)" :key="target.id" :title="`Move to ${target.name}`" @click="moveApplicant(applicant.id, target)" />
                    </VList>
                  </VMenu>
                  <VChip v-if="movingId === applicant.id" size="x-small" color="primary">Moving…</VChip>
                </div>
                <div v-if="canMove && allowedTargets(applicant).length" class="text-caption text-medium-emphasis mt-1">Next: {{ allowedTargets(applicant).map(s => s.name).join(', ') }}</div>
              </VCardText>
            </VCard>
            <div v-if="!(cards[stage.key] || []).length" class="text-caption text-medium-emphasis text-center py-4">Drop allowed cases here</div>
            <VBtn v-if="stage.count > perColumnCap" size="x-small" variant="text" :to="{ name: 'applicants', query: { stage: stage.key } }">View all {{ stage.count }}</VBtn>
          </VCardText>
        </VCard>
      </div>
    </VCardText>
  </VCard>
</template>
