<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
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

const loading = ref(false)
const movingId = ref<string | null>(null)
const dragCard = ref<PipelineCard | null>(null)
const pipelineScroller = ref<HTMLElement | null>(null)
const stages = ref<PipelineColumn[]>([])
const cards = ref<Record<string, PipelineCard[]>>({})
const totals = ref({ active: 0, closed: 0, all: 0 })

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
const activeFilterCount = computed(() => Object.values(filters.value).filter(v => v !== '' && v !== null && v !== undefined).length)
const perColumnCap = 30

const activeCountries = computed(() => countries.value.filter(c => c.is_active))
const activeUniversities = computed(() => universities.value.filter(u => u.is_active && (!filters.value.country_id || u.country_id === filters.value.country_id)))
const activeLevels = computed(() => levels.value.filter(l => l.is_active))

// A university selected for one country must not silently filter everything
// out after the country changes.
watch(() => filters.value.country_id, () => {
  const stillVisible = activeUniversities.value.some(u => u.id === filters.value.university_id)

  if (!stillVisible)
    filters.value.university_id = null
})

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

    // A contracted line moves its own workflow; a case that has not bought the
    // service yet moves through the applicant-level stage until the line exists.
    if (card.line_id)
      await ApplicantApi.changeLineStage(card.line_id, target.id, note)
    else
      await ApplicantApi.changeStage(card.applicant_id, target.id, note)

    toast.success(t('educational.messages.stageMoved', { stage: translateApplicantStage(target) }))
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
  filters.value = { service: filters.value.service, search: '', status: undefined, responsible_employee_id: null, country_id: null, university_id: null, education_level_id: null, date_from: '', date_to: '' }
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
  <div class="education-workspace erp-workspace">
    <ErpPageHeader :title="$t('educational.applicantPipeline')" section="applications" />
    <VCard class="education-panel">
    <VCardItem :subtitle="$t('educational.pipelineInstructions')">
      <template #append>
<VBtn v-if="canAccess('view_applicant_stages')" size="small" variant="text" :to="{ name: 'applicant-stages' }">{{ $t('educational.stage') }}</VBtn>
        <VChip color="success" size="small" class="me-2">{{ $t('educational.activeCount', { count: totals.active }) }}</VChip>
        <VChip color="secondary" size="small" class="me-2">{{ $t('educational.closedCount', { count: totals.closed }) }}</VChip>
        <VChip color="primary" size="small">{{ $t('educational.allCount', { count: totals.all }) }}</VChip>
      </template>
    </VCardItem>
    <VCardText>
      <VRow dense>
        <VCol cols="12" md="3"><VSelect v-model="filters.service" :items="serviceOptions" :label="$t('educational.service')" /></VCol>
        <VCol cols="12" md="3"><VTextField v-model="filters.search" :label="$t('educational.searchApplicant')" clearable @keyup.enter="load" /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.responsible_employee_id" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.employee')" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.country_id" :items="activeCountries.map(c => ({ title: c.name, value: c.id }))" :label="$t('educational.country')" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.university_id" :items="activeUniversities.map(u => ({ title: u.name, value: u.id }))" :label="$t('educational.university')" clearable /></VCol>
        <VCol cols="12" md="3"><VSelect v-model="filters.education_level_id" :items="activeLevels.map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.educationLevel')" clearable /></VCol>
        <VCol cols="12" md="2"><VSelect v-model="filters.status" :items="[{ title: $t('educational.active'), value: 'active' }, { title: $t('educational.closed'), value: 'closed' }]" :label="$t('educational.status')" clearable /></VCol>
        <VCol cols="12" md="6"><ErpDateRange v-model:from="filters.date_from" v-model:to="filters.date_to" @apply="load" /></VCol>
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
      <div v-else ref="pipelineScroller" class="d-flex gap-3 overflow-x-auto pb-2 pipeline-scroller" @dragover.prevent="onPipelineDragOver">
        <VCard v-for="stage in stages" :key="stage.key" variant="outlined" min-width="260" class="flex-shrink-0 pipeline-column" :class="{ 'pipeline-column--allowed': isAllowedDropStage(stage), 'pipeline-column--blocked': dragCard && !isAllowedDropStage(stage) }" style="inline-size: 260px;" @dragover.prevent @drop="onDrop(stage)">
          <VCardItem :subtitle="$t('educational.caseCount', { count: stage.count })">
            <template #title><VTooltip location="top"><template #activator="{ props }"><span v-bind="props" class="d-inline-block text-truncate" style="max-inline-size: 185px">{{ translateApplicantStage(stage) }}</span></template>{{ translateApplicantStage(stage) }}</VTooltip></template>
            <template #append><VChip size="small" :color="stage.is_terminal ? 'secondary' : 'primary'">{{ stage.count }}</VChip></template>
          </VCardItem>
          <VCardText class="d-flex flex-column gap-2" style="min-block-size: 120px;">
            <VCard v-for="card in (cards[stage.key] || [])" :key="card.id" variant="tonal" :draggable="canMove && movingId !== card.id" @dragstart="onDragStart(card)" @dragend="onDragEnd">
              <VCardText class="py-2">
                <div class="font-weight-medium">{{ card.name || $t('educational.applicantNumber', { id: card.applicant_id }) }}</div>
                <div class="text-caption text-medium-emphasis">#{{ card.applicant_id }} · {{ translateBackendValue(card.status) }}<span v-if="card.contract_number"> · {{ card.contract_number }}</span></div>
                <div class="text-caption">{{ serviceOptions.find(option => option.value === card.service)?.title }}<span v-if="card.country">{{ ` · ${card.country}` }}</span><span v-if="card.university">{{ ` · ${card.university}` }}</span></div>
                <div class="text-caption text-medium-emphasis">{{ card.responsible_employee?.name ?? $t('educational.unassigned') }}</div>
                <div class="d-flex gap-1 mt-2">
                  <VBtn size="x-small" variant="text" @click="openCase(card.applicant_id)">{{ $t('educational.openCase') }}</VBtn>
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
            <div v-if="!(cards[stage.key] || []).length" class="text-caption text-medium-emphasis text-center py-4">{{ $t('educational.dropAllowedCases') }}</div>
            <VBtn v-if="stage.count > perColumnCap" size="x-small" variant="text" :to="{ name: 'applications', query: { service: filters.service, stage: stage.key } }">{{ $t('educational.viewAllCount', { count: stage.count }) }}</VBtn>
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
