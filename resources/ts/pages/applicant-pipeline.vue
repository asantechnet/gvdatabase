<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { type Applicant, type ApplicantListParams, type PipelineColumn } from '@/api/applicants'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'
import { translateBackendValue } from '@/utils/localization'

const toast = useToast()
const router = useRouter()
const { t } = useI18n()

const loading = ref(false)
const movingId = ref<number | null>(null)
const dragId = ref<number | null>(null)
const pipelineScroller = ref<HTMLElement | null>(null)
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
    toast.error(t('educational.messages.loadPipelineFailed'))
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
    toast.error(t('educational.messages.loadPipelineFiltersFailed'))
  }
}

function onDragStart(applicant: Applicant) {
  dragId.value = applicant.id
}

function onDragEnd() {
  dragId.value = null
}

function onPipelineDragOver(event: DragEvent) {
  const scroller = pipelineScroller.value

  if (!dragId.value || !scroller)
    return

  const bounds = scroller.getBoundingClientRect()
  const edgeSize = Math.min(72, bounds.width / 4)
  const leftDistance = event.clientX - bounds.left
  const rightDistance = bounds.right - event.clientX

  if (leftDistance >= 0 && leftDistance < edgeSize) {
    const speed = Math.ceil(6 + (edgeSize - leftDistance) / edgeSize * 18)
    scroller.scrollLeft -= speed
  }
  else if (rightDistance >= 0 && rightDistance < edgeSize) {
    const speed = Math.ceil(6 + (edgeSize - rightDistance) / edgeSize * 18)
    scroller.scrollLeft += speed
  }
}

function onPipelineWheel(event: WheelEvent) {
  const scroller = pipelineScroller.value

  if (!scroller)
    return

  const horizontalDelta = event.deltaX || event.deltaY

  scroller.scrollLeft += horizontalDelta
}

function isAllowedDropStage(stage: PipelineColumn) {
  const applicant = dragId.value
    ? Object.values(cards.value).flat().find(row => row.id === dragId.value)
    : undefined

  return !!applicant && dropAllowed(applicant, stage.key)
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
    toast.error(t('educational.messages.stagePermissionRequired'))

    return
  }

  if (!dropAllowed(applicant, target.key)) {
    const from = stageOf(applicant)?.name ?? t('educational.currentStage')

    toast.error(t('educational.messages.stageMoveNotAllowed', { from, to: target.name }))

    return
  }

  movingId.value = id
  try {
    await ApplicantApi.changeStage(id, target.id, t('educational.pipelineMoveNote', { stage: target.name }))
    toast.success(t('educational.messages.stageMoved', { stage: target.name }))
    await load()
  }
  catch {
    toast.error(t('educational.messages.transitionNotAllowed'))
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
  <div class="education-workspace"><VCard class="education-panel">
    <VCardItem :title="$t('educational.applicantPipeline')" :subtitle="$t('educational.pipelineInstructions')">
      <template #append>
        <VBtn v-if="canAccess('manage_applicant_stages')" size="small" variant="text" :to="{ name: 'applicant-stages' }">{{ $t('educational.stage') }}</VBtn>
        <VChip color="success" size="small" class="me-2">{{ $t('educational.activeCount', { count: totals.active }) }}</VChip>
        <VChip color="secondary" size="small" class="me-2">{{ $t('educational.closedCount', { count: totals.closed }) }}</VChip>
        <VChip color="primary" size="small">{{ $t('educational.allCount', { count: totals.all }) }}</VChip>
      </template>
    </VCardItem>
    <VCardText>
      <VRow dense>
        <VCol cols="12" md="3"><VTextField v-model="filters.search" :label="$t('educational.searchApplicant')" clearable @keyup.enter="load" /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.responsible_employee_id" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.employee')" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.country_id" :items="activeCountries.map(c => ({ title: c.name, value: c.id }))" :label="$t('educational.country')" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.university_id" :items="activeUniversities.map(u => ({ title: u.name, value: u.id }))" :label="$t('educational.university')" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.education_level_id" :items="activeLevels.map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.educationLevel')" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.language_id" :items="activeLanguages.map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.language')" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="filters.status" :items="[{ title: $t('educational.active'), value: 'active' }, { title: $t('educational.closed'), value: 'closed' }]" :label="$t('educational.status')" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="filters.date_from" type="date" :label="$t('educational.from')" clearable /></VCol>
        <VCol cols="6" md="2"><VTextField v-model="filters.date_to" type="date" :label="$t('educational.to')" clearable /></VCol>
      </VRow>
      <div class="d-flex gap-2 mt-2">
        <VBtn color="primary" :loading="loading" @click="load">{{ $t('educational.applyFilters', { count: activeFilterCount }) }}</VBtn>
        <VBtn variant="text" @click="clearFilters">{{ $t('educational.clear') }}</VBtn>
        <VBtn v-if="!canMove" variant="text" disabled>{{ $t('educational.stageMovePermission') }}</VBtn>
      </div>
    </VCardText>
    <VProgressLinear v-if="loading" indeterminate />
    <VCardText>
      <div v-if="!loading && !stages.length" class="text-center text-medium-emphasis py-8">{{ $t('educational.noActiveStages') }}</div>
      <div v-else ref="pipelineScroller" class="d-flex gap-3 overflow-x-auto pb-2 pipeline-scroller" @dragover.prevent="onPipelineDragOver" @wheel.prevent="onPipelineWheel">
        <VCard v-for="stage in stages" :key="stage.key" variant="outlined" min-width="260" class="flex-shrink-0 pipeline-column" :class="{ 'pipeline-column--allowed': isAllowedDropStage(stage), 'pipeline-column--blocked': dragId && !isAllowedDropStage(stage) }" style="inline-size: 260px;" @dragover.prevent @drop="onDrop(stage)">
          <VCardItem :subtitle="$t('educational.caseCount', { count: stage.count })">
            <template #title><VTooltip location="top"><template #activator="{ props }"><span v-bind="props" class="d-inline-block text-truncate" style="max-inline-size: 185px">{{ stage.name }}</span></template>{{ stage.name }}</VTooltip></template>
            <template #append><VChip size="small" :color="stage.is_terminal ? 'secondary' : 'primary'">{{ stage.count }}</VChip></template>
          </VCardItem>
          <VCardText class="d-flex flex-column gap-2" style="min-block-size: 120px;">
            <VCard v-for="applicant in (cards[stage.key] || [])" :key="applicant.id" variant="tonal" :draggable="canMove && movingId !== applicant.id" @dragstart="onDragStart(applicant)" @dragend="onDragEnd">
              <VCardText class="py-2">
                <div class="font-weight-medium">{{ applicant.customer?.name ?? $t('educational.applicantNumber', { id: applicant.id }) }}</div>
                <div class="text-caption text-medium-emphasis">#{{ applicant.id }} · {{ translateBackendValue(applicant.status) }}</div>
                <div class="text-caption">{{ applicant.country?.name ?? '—' }}{{ applicant.university?.name ? ` · ${applicant.university.name}` : '' }}</div>
                <div class="text-caption text-medium-emphasis">{{ applicant.responsible_employee?.name ?? $t('educational.unassigned') }}</div>
                <div class="d-flex gap-1 mt-2">
                  <VBtn size="x-small" variant="text" @click="openCase(applicant.id)">{{ $t('educational.openCase') }}</VBtn>
                  <VMenu v-if="canMove && allowedTargets(applicant).length">
                    <template #activator="{ props }"><VBtn size="x-small" variant="text" color="primary" v-bind="props">{{ $t('educational.move') }}</VBtn></template>
                    <VList density="compact">
                      <VListItem v-for="target in allowedTargets(applicant)" :key="target.id" :title="$t('educational.moveTo', { stage: target.name })" @click="moveApplicant(applicant.id, target)" />
                    </VList>
                  </VMenu>
                  <VChip v-if="movingId === applicant.id" size="x-small" color="primary">{{ $t('educational.moving') }}</VChip>
                </div>
              </VCardText>
            </VCard>
            <div v-if="!(cards[stage.key] || []).length" class="text-caption text-medium-emphasis text-center py-4">{{ $t('educational.dropAllowedCases') }}</div>
            <VBtn v-if="stage.count > perColumnCap" size="x-small" variant="text" :to="{ name: 'applicants', query: { stage: stage.key } }">{{ $t('educational.viewAllCount', { count: stage.count }) }}</VBtn>
          </VCardText>
        </VCard>
      </div>
    </VCardText>
  </VCard></div>
</template>

<style scoped>
.pipeline-scroller {
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.pipeline-scroller::-webkit-scrollbar {
  display: none;
}

.pipeline-column {
  transition: border-color 160ms ease, box-shadow 160ms ease, opacity 160ms ease, transform 160ms ease;
}

.pipeline-column--allowed {
  border-color: rgb(var(--v-theme-success)) !important;
  box-shadow: 0 0 0 2px rgba(var(--v-theme-success), 0.24);
  transform: translateY(-2px);
}

.pipeline-column--allowed :deep(.v-card-item) {
  background: rgba(var(--v-theme-success), 0.1);
}

.pipeline-column--blocked {
  opacity: 0.55;
}
</style>
