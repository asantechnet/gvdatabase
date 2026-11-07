<script setup lang="ts">
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import ApplicantPipelineSkeleton from '@/components/ApplicantPipelineSkeleton.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { type ApplicantListParams, type BuiltInService, type PipelineCard, type PipelineColumn } from '@/api/applicants'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'
import { translateApplicantStage, translateBackendValue } from '@/utils/localization'

const toast = useToast()
const router = useRouter()
const { t } = useI18n()
const props = withDefaults(defineProps<{ embedded?: boolean; externalFilters?: ApplicantListParams }>(), { embedded: false, externalFilters: undefined })

// Start in the skeleton state so swapping to Kanban never flashes an empty
// board before the first request resolves.
const loading = ref(true)
const movingId = ref<string | null>(null)
const dragCard = ref<PipelineCard | null>(null)
const pipelineScroller = ref<HTMLElement | null>(null)
const stages = ref<PipelineColumn[]>([])
const cards = ref<Record<string, PipelineCard[]>>({})
const totals = ref({ active: 0, closed: 0, all: 0 })
const filtersOpen = ref(false)

const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const levels = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])

// The board shows one service's stage list at a time; the line's own stage is
// what places a card, so mixing services in one board is meaningless here.
const filters = ref<ApplicantListParams>({ service: 'scholarship', search: '', status: undefined, responsible_employee_id: null, country_id: null, university_id: null, education_level_id: null, date_from: '', date_to: '' })
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  { title: t('educational.builtInServices.academy'), value: 'academy' },
  { title: t('educational.builtInServices.private'), value: 'private' },
])

const canMove = computed(() => canAccess('change_applicant_stage'))
// A service is required to choose the board's stage catalogue, so it is a
// board scope rather than an optional active filter in the filter-button cue.
const activeFilterCount = computed(() => Object.entries(filters.value)
  .filter(([key, value]) => key !== 'service' && value !== '' && value !== null && value !== undefined).length)
const perColumnCap = 30

const activeCountries = computed(() => countries.value.filter(c => c.is_active))
const activeUniversities = computed(() => universities.value.filter(u => u.is_active && (!filters.value.country_id || u.country_id === filters.value.country_id)))
const activeLevels = computed(() => levels.value.filter(l => l.is_active))
const hasCards = computed(() => Object.values(cards.value).some(column => column.length > 0))

watch(() => props.externalFilters, incoming => {
  if (!props.embedded || !incoming)
    return

  // Kanban deliberately shares the Applications toolbar filters. It still
  // needs one service to resolve its stage catalogue, so use Scholarship only
  // while no service has been selected in the shared filter.
  filters.value = { ...filters.value, ...incoming, service: incoming.service || 'scholarship' }
  void load()
}, { deep: true, immediate: true })

// Destination is part of the board's stage catalogue, not just a row filter:
// a country or university adds its own stages to the columns. Reload as soon
// as either changes so the columns and the cards describe one context.
watch(
  [() => filters.value.country_id, () => filters.value.university_id],
  () => {
    const stillVisible = activeUniversities.value.some(u => u.id === filters.value.university_id)

    // A university from the previous country no longer applies; the null write
    // re-runs this watcher once with the corrected context.
    if (filters.value.university_id && !stillVisible) {
      filters.value.university_id = null

      return
    }

    void load()
  },
)

// A different service is a different stage list, so switch the board at once.
watch(() => filters.value.service, () => {
  void load()
})

function stageOf(card: PipelineCard) {
  return stages.value.find(s => s.id === card.current_stage_id)
}

function allowedTargets(card: PipelineCard) {
  const current = card.current_stage

  if (!current?.allowed_next_keys?.length)
    return []

  return stages.value.filter(s => current.allowed_next_keys.includes(s.key))
}

function dropAllowed(card: PipelineCard, targetKey: string) {
  return (card.current_stage?.allowed_next_keys ?? []).includes(targetKey)
}

async function load() {
  loading.value = true
  try {
    // The board returns its own capped card set; `per_page` belongs to the list.
    const params = { ...filters.value } as ApplicantListParams
    const pipeline = await ApplicantApi.pipeline(params)

    stages.value = [...pipeline.stages].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
    totals.value = pipeline.totals

    const grouped: Record<string, PipelineCard[]> = {}

    for (const stage of stages.value)
      grouped[stage.key] = []

    for (const card of pipeline.cards) {
      // Keep the board scannable: university/family detail belongs in the case,
      // not on the compact workflow card.
      card.university = undefined
      const key = card.current_stage?.key ?? stages.value[0]?.key

      if (!key)
        continue

      if (!grouped[key])
        grouped[key] = []

      if (grouped[key].length < perColumnCap)
        grouped[key].push(card)
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
    toast.error(t('educational.messages.loadPipelineFiltersFailed'))
  }
}

function onDragStart(card: PipelineCard) {
  dragCard.value = card
}

function onDragEnd() {
  dragCard.value = null
}

function onPipelineDragOver(event: DragEvent) {
  const scroller = pipelineScroller.value

  if (!dragCard.value || !scroller)
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

function isAllowedDropStage(stage: PipelineColumn) {
  const card = dragCard.value

  return !!card && dropAllowed(card, stage.key)
}

async function onDrop(target: PipelineColumn) {
  const card = dragCard.value

  dragCard.value = null

  if (!card)
    return

  await moveCard(card, target)
}

async function moveCard(card: PipelineCard, target: PipelineColumn) {
  if (card.current_stage_id === target.id)
    return

  if (!canMove.value) {
    toast.error(t('educational.messages.stagePermissionRequired'))

    return
  }

  if (!dropAllowed(card, target.key)) {
    const from = stageOf(card)?.name ?? t('educational.currentStage')

    toast.error(t('educational.messages.stageMoveNotAllowed', { from, to: translateApplicantStage(target) }))

    return
  }

  movingId.value = card.id
  try {
    const note = t('educational.pipelineMoveNote', { stage: translateApplicantStage(target) })

    // Every card is a contracted service line; the line endpoint is the only
    // writer, so one move can never touch a sibling case.
    await ApplicantApi.changeLineStage(card.line_id, target.id, note)

    toast.success(t('educational.messages.stageMoved', { stage: translateApplicantStage(target) }))
    await load()
  }
  catch {
    // The shared API notifier already explains the refusal (document gate,
    // terminal reason, illegal transition); a second generic toast would
    // contradict it.
  }
  finally {
    movingId.value = null
  }
}

function clearFilters() {
  filters.value = { service: filters.value.service, search: '', status: undefined, responsible_employee_id: null, country_id: null, university_id: null, education_level_id: null, date_from: '', date_to: '' }
  load()
}

function applyFilters() {
  void load()
}

function openCase(lineId: number) {
  router.push({ name: 'service-case', params: { line: lineId } })
}

// The applications page must open on the same destination context, or its
// stage tabs would not match the column that was clicked here.
function stageListQuery(stageKey: string) {
  return {
    service: filters.value.service,
    stage: stageKey,
    ...(filters.value.country_id ? { country_id: filters.value.country_id } : {}),
    ...(filters.value.university_id ? { university_id: filters.value.university_id } : {}),
  }
}

onMounted(async () => {
  if (!props.embedded) {
    await loadCatalogs()
    await load()
  }
})
</script>

<template>
  <div :class="props.embedded ? 'applicant-pipeline-embedded' : 'education-workspace erp-workspace'">
    <VCard class="education-panel">
    <VCardItem v-if="!props.embedded">
      <template #append>
        <div class="pipeline-header-actions">
          <VBtn v-if="canAccess('view_applicant_stages')" size="small" variant="text" :to="{ name: 'applicant-stages' }">{{ $t('educational.stage') }}</VBtn>
          <DataTableFilterMenu v-model="filtersOpen" :active="activeFilterCount > 0" @reset="clearFilters">
            <VSelect v-model="filters.service" :items="serviceOptions" :label="$t('educational.service')" />
            <VTextField v-model="filters.search" :label="$t('educational.searchApplicant')" clearable @keyup.enter="applyFilters" />
            <VSelect v-model="filters.responsible_employee_id" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.employee')" clearable />
            <VSelect v-model="filters.country_id" :items="activeCountries.map(c => ({ title: c.name, value: c.id }))" :label="$t('educational.country')" clearable />
            <VSelect v-model="filters.university_id" :items="activeUniversities.map(u => ({ title: u.name, value: u.id }))" :label="$t('educational.university')" clearable />
            <VSelect v-model="filters.education_level_id" :items="activeLevels.map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.educationLevel')" clearable />
            <VSelect v-model="filters.status" :items="[{ title: $t('educational.active'), value: 'active' }, { title: $t('educational.closed'), value: 'closed' }]" :label="$t('educational.status')" clearable />
            <ErpDateRange v-model:from="filters.date_from" v-model:to="filters.date_to" @apply="applyFilters" />
            <VBtn color="primary" :loading="loading" @click="applyFilters">{{ $t('educational.applyFilters', { count: activeFilterCount }) }}</VBtn>
          </DataTableFilterMenu>
        </div>
      </template>
    </VCardItem>
    <VCardText>
      <VAlert v-if="!canMove" type="info" variant="tonal" density="compact" class="mb-4">{{ $t('educational.stageMovePermission') }}</VAlert>
      <ApplicantPipelineSkeleton v-if="loading" />
      <TableEmptyState v-else-if="!stages.length || !hasCards" icon="tabler-clipboard-list" :title="$t('pages.contracts.visitor.visitor.noApplicants')" :description="$t('educational.noApplicants')" />
      <div v-else ref="pipelineScroller" class="d-flex gap-3 overflow-x-auto pb-2 pipeline-scroller" @dragover.prevent="onPipelineDragOver">
        <VCard v-for="stage in stages" :key="stage.key" variant="outlined" min-width="260" class="flex-shrink-0 pipeline-column" :class="{ 'pipeline-column--allowed': isAllowedDropStage(stage), 'pipeline-column--blocked': dragCard && !isAllowedDropStage(stage) }" style="inline-size: 260px;" @dragover.prevent @drop="onDrop(stage)">
          <VCardItem :subtitle="$t('educational.pipelineCardCount', { count: stage.count })">
            <template #title><VTooltip location="top"><template #activator="{ props }"><span v-bind="props" class="d-inline-block text-truncate" style="max-inline-size: 185px">{{ translateApplicantStage(stage) }}</span></template>{{ translateApplicantStage(stage) }}</VTooltip></template>
            <template #append><VChip size="small" :color="stage.is_terminal ? 'secondary' : 'primary'">{{ stage.count }}</VChip></template>
          </VCardItem>
          <VCardText class="d-flex flex-column gap-3 pa-3" style="min-block-size: 150px;">
            <VCard v-for="card in (cards[stage.key] || [])" :key="card.id" variant="tonal" class="pipeline-case-card" :draggable="canMove && movingId !== card.id" @dragstart="onDragStart(card)" @dragend="onDragEnd">
              <VCardText class="py-2">
                <div class="font-weight-medium">{{ card.name || $t('educational.applicantNumber', { id: card.applicant_id }) }}</div>
                <div class="text-caption text-medium-emphasis">#{{ card.applicant_id }} · {{ translateBackendValue(card.status) }}<span v-if="card.contract_number"> · {{ card.contract_number }}</span></div>
                <div v-if="card.contract_role === 'extension'" class="text-caption text-warning">{{ $t('educational.extensionOf', { number: card.parent_contract_number ?? '—' }) }}</div>
                <div class="text-caption">{{ serviceOptions.find(option => option.value === card.service)?.title }}<span v-if="card.country">{{ ` · ${card.country}` }}</span><span v-if="card.university">{{ ` · ${card.university}` }}</span></div>
                <div class="text-caption text-medium-emphasis">{{ card.responsible_employee?.name ?? $t('educational.unassigned') }}</div>
                <div class="d-flex gap-1 mt-2">
                  <VBtn size="x-small" variant="text" @click="openCase(card.line_id)">{{ $t('educational.openCase') }}</VBtn>
                  <VMenu v-if="canMove && allowedTargets(card).length">
                    <template #activator="{ props }"><VBtn size="x-small" variant="text" color="primary" v-bind="props">{{ $t('educational.move') }}</VBtn></template>
                    <VList density="compact">
                      <VListItem v-for="target in allowedTargets(card)" :key="target.id" :title="$t('educational.moveTo', { stage: translateApplicantStage(target) })" @click="moveCard(card, target)" />
                    </VList>
                  </VMenu>
                  <VChip v-if="movingId === card.id" size="x-small" color="primary">{{ $t('educational.moving') }}</VChip>
                </div>
              </VCardText>
            </VCard>
            <div v-if="!(cards[stage.key] || []).length" class="pipeline-column-empty text-center py-5"><VIcon icon="tabler-arrows-down" size="20" /><span>{{ $t('educational.dropAllowedCases') }}</span></div>
            <VBtn v-if="stage.count > perColumnCap" size="x-small" variant="text" :to="{ name: 'applications', query: stageListQuery(stage.key) }">{{ $t('educational.viewAllCount', { count: stage.count }) }}</VBtn>
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
  background: rgb(var(--v-theme-surface));
  border-color: rgb(var(--v-border-color) / 0.45) !important;
  border-radius: 14px;
  transition: border-color 160ms ease, box-shadow 160ms ease, opacity 160ms ease, transform 160ms ease;
}

.pipeline-case-card {
  /* A gentle lifted white surface in light mode; the dark-theme rule below
     mirrors it with a slightly deeper surface rather than a bright tint. */
  background: rgb(var(--v-theme-surface)) !important;
  border: 1px solid rgb(var(--v-border-color) / 0.28);
  border-radius: 7px;
  box-shadow: 0 1px 2px rgb(var(--v-theme-on-surface) / 0.025);
}

:global(.v-theme--dark) .pipeline-case-card {
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, black 4%) !important;
  border-color: rgb(var(--v-border-color) / 0.38);
}

.pipeline-case-card :deep(.v-card-text) {
  padding: 0.875rem !important;
}

.pipeline-case-card :deep(.v-card-text > div + div) {
  margin-block-start: 0.45rem;
}

.pipeline-case-card :deep(.v-card-text > .mt-2) {
  margin-block-start: 0.7rem !important;
}

.pipeline-column-empty {
  align-items: center;
  background: rgb(var(--v-theme-surface) / 0.34);
  border: 1px dashed rgb(var(--v-border-color) / 0.46);
  border-radius: 10px;
  color: rgb(var(--v-theme-on-surface) / var(--v-medium-emphasis-opacity));
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.pipeline-header-actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: flex-end;
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

@media (max-width: 599px) {
  .pipeline-header-actions {
    justify-content: flex-start;
  }
}
</style>
