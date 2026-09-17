<script setup lang="ts">
import { useToast } from 'vue-toastification'
import { useRouter } from 'vue-router'
import PositionApi, { type Position, type PositionPayload } from '@/api/position'
import PositionForm from '@/components/forms/PositionForm.vue'

const toast = useToast()
const router = useRouter()
const { t } = useI18n()
const positions = ref<Position[]>([])
const search = ref('')
const statusFilter = ref<Position['status'] | null>(null)
const loading = ref(false)
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const editingPosition = ref<Position | null>(null)
const deletingPosition = ref<Position | null>(null)
const form = ref<PositionPayload>({ title: '', description: '', salary: 0, status: 'active' })

const headers = computed(() => [
  { title: t('common.messages.position'), key: 'title' },
  { title: t('common.fields.description'), key: 'description', sortable: false },
  { title: t('pages.settings.positions.defaultSalary'), key: 'salary' },
  { title: t('common.fields.status'), key: 'status' },
  { title: t('pages.settings.positions.assigned'), key: 'employee_count' },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const filteredPositions = computed(() => {
  const query = search.value.trim().toLowerCase()

  return positions.value.filter(position => {
    const matchesSearch = !query || `${position.title} ${position.description ?? ''}`.toLowerCase().includes(query)

    return matchesSearch && (!statusFilter.value || position.status === statusFilter.value)
  })
})

const assignedLabel = (position: Position) => t('pages.settings.positions.assignedPeople', { staff: position.employee_count, users: position.user_count })

const formatSalary = (position: Position) => {
  const precision = position.currency?.decimalPlaces ?? 2
  const value = formatLocalizedNumber(Number(position.salary || 0), { minimumFractionDigits: precision, maximumFractionDigits: precision })

  return `${position.currency?.symbol ?? ''}${value}${position.currency?.code ? ` ${position.currency.code}` : ''}`
}

async function loadPositions() {
  loading.value = true
  try {
    const response = await PositionApi.getAll()

    positions.value = response.data.data
  }
  catch (error: any) {
    toast.error(error.message || t('pages.settings.positions.couldNotLoad'))
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editingPosition.value = null
  form.value = { title: '', description: '', salary: 0, status: 'active' }
  dialogOpen.value = true
}

function openEdit(position: Position) {
  editingPosition.value = position
  form.value = { title: position.title, description: position.description ?? '', salary: Number(position.salary), status: position.status }
  dialogOpen.value = true
}

function editPositionContract(position: Position) {
  router.push({ name: 'contract-templates', query: { positionId: position.id } })
}

async function save() {
  if (!form.value.title.trim())
    return toast.error(translate('pages.settings.positions.positionTitleIsRequired'))
  if (!Number.isFinite(Number(form.value.salary)) || Number(form.value.salary) < 0)
    return toast.error(translate('pages.settings.positions.defaultSalaryMustBeZeroOrGreater'))

  loading.value = true
  try {
    const payload = { ...form.value, title: form.value.title.trim(), salary: Number(form.value.salary) }

    const response = editingPosition.value
      ? await PositionApi.update(editingPosition.value.id, payload)
      : await PositionApi.create(payload)

    const saved = response.data.data as Position
    const index = positions.value.findIndex(position => position.id === saved.id)
    if (index === -1)
      positions.value.unshift(saved)
    else positions.value[index] = saved
    dialogOpen.value = false
    toast.success(translate(editingPosition.value ? 'pages.settings.positions.updated' : 'pages.settings.positions.created'))
  }
  catch (error: any) {
    toast.error(error.errors?.title?.[0] || error.message || t('pages.settings.positions.couldNotSave'))
  }
  finally {
    loading.value = false
  }
}

function askDelete(position: Position) {
  deletingPosition.value = position
  deleteDialogOpen.value = true
}

async function confirmDelete() {
  if (!deletingPosition.value)
    return
  loading.value = true
  try {
    await PositionApi.delete(deletingPosition.value.id)
    positions.value = positions.value.filter(position => position.id !== deletingPosition.value?.id)
    deleteDialogOpen.value = false
    toast.success(translate('pages.settings.positions.positionDeleted'))
  }
  catch (error: any) {
    toast.error(error.message || t('pages.settings.positions.couldNotDelete'))
  }
  finally {
    loading.value = false
  }
}

onMounted(loadPositions)
</script>

<template>
  <DataTablePageKpis
    :items="filteredPositions"
    :base-currency="filteredPositions[0]?.currency ?? null"
    title="Positions"
    description="Review team roles, staffing levels, and position availability."
    icon="tabler-briefcase"
  />
  <VCard
    :title="$t('pages.settings.positions.positions')"
    :loading="loading"
  >
    <template #subtitle>
      {{ $t('pages.settings.positions.hrJobTitlesAreSeparateFromAccountAccessRoles') }}
    </template>
    <VCardText>
      <VRow align="center">
        <VCol
          cols="12"
          md="8"
        >
          <VTextField
            v-model="search"
            :label="$t('pages.settings.positions.searchPositions')"
            class="data-table-search"
            prepend-inner-icon="tabler-search"
            density="compact"
            hide-details
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
          class="d-flex justify-end ga-2"
        >
          <DataTableFilterMenu
            :active="Boolean(statusFilter)"
            @reset="statusFilter = null"
          >
            <VSelect
              v-model="statusFilter"
              :label="$t('common.fields.status')"
              :items="['active', 'inactive']"
              :item-title="translateBackendValue"
              density="compact"
              hide-details
              clearable
            />
          </DataTableFilterMenu>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openCreate"
          >
            {{ $t('pages.settings.positions.addPosition') }}
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <ResponsiveDataTable
      :headers="headers"
      :items="filteredPositions"
      :loading="loading"
      item-value="id"
      class="text-no-wrap"
    >
      <template #item.title="{ item }">
        <div class="font-weight-medium">
          {{ item.title }}
        </div>
      </template>
      <template #item.description="{ item }">
        <span class="text-medium-emphasis">{{ item.description || '--' }}</span>
      </template>
      <template #item.salary="{ item }">
        <div class="font-weight-medium">
          {{ formatSalary(item) }}
        </div><div class="text-caption text-medium-emphasis">
          {{ item.currency?.code ? $t('pages.settings.positions.currentBaseSalary', { currency: item.currency.code }) : $t('pages.settings.positions.legacyCurrencyNotRecorded') }}
        </div>
      </template>
      <template #item.status="{ item }">
        <VChip
          size="small"
          label
          :color="item.status === 'active' ? 'success' : 'secondary'"
        >
          {{ translateBackendValue(item.status) }}
        </VChip>
      </template>
      <template #item.employee_count="{ item }">
        {{ assignedLabel(item) }}
      </template>
      <template #item.actions="{ item }">
        <FullDetailsButton
          :item="item"
          :title="item.title"
        />
        <VBtn
          size="small"
          color="primary"
          variant="tonal"
          class="mx-1"
          prepend-icon="tabler-file-pencil"
          @click="editPositionContract(item)"
        >
          {{ $t('pages.settings.positions.editContract') }}
        </VBtn>
        <IconBtn
          :aria-label="$t('pages.settings.positions.editPosition')"
          @click="openEdit(item)"
        >
          <VIcon icon="tabler-pencil" />
        </IconBtn>
        <IconBtn
          :aria-label="$t('common.messages.deletePosition')"
          @click="askDelete(item)"
        >
          <VIcon icon="tabler-trash" />
        </IconBtn>
      </template>
      <template #no-data>
        <div class="py-6 text-medium-emphasis">
          {{ $t('pages.settings.positions.noPositionsMatchTheCurrentFilters') }}
        </div>
      </template>
    </ResponsiveDataTable>
  </VCard>

  <VDialog
    v-model="dialogOpen"
    max-width="620"
  >
    <VCard :title="editingPosition ? $t('pages.settings.positions.editPosition') : $t('pages.settings.positions.addPosition')">
      <VCardText>
        <PositionForm
          :form="form"
          :loading="loading"
          @submit="save"
          @cancel="dialogOpen = false"
        />
      </VCardText>
    </VCard>
  </VDialog>

  <VDialog
    v-model="deleteDialogOpen"
    max-width="460"
  >
    <VCard :title="$t('common.messages.deletePosition')">
      <VCardText>{{ $t('pages.settings.positions.deleteConfirmation', { position: deletingPosition?.title }) }}</VCardText>
      <VCardActions>
        <VSpacer /><VBtn
          variant="tonal"
          @click="deleteDialogOpen = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn><VBtn
          color="error"
          :loading="loading"
          @click="confirmDelete"
        >
          {{ $t('common.actions.delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
