<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { AcademyClassesApi, type AcademyDurationRecord } from '@/api/academyClasses'
import MasterDataApi, { type EducationalCatalog, type MasterDataRecord } from '@/api/educationalMasterData'
import { canAccess } from '@/utils/authorization'

const { t } = useI18n()
const toast = useToast()
const tab = ref<'programs' | 'times' | 'teachers' | 'durations'>('programs')
const rows = ref<MasterDataRecord[]>([])
const durations = ref<AcademyDurationRecord[]>([])
const loading = ref(false)
const failed = ref(false)
const canManage = computed(() => canAccess('manage_academy_classes'))
const catalog = computed<EducationalCatalog>(() => tab.value as EducationalCatalog)
const isDuration = computed(() => tab.value === 'durations')

const dialogOpen = ref(false)
const editing = ref<MasterDataRecord | null>(null)
const form = ref<Partial<MasterDataRecord>>({ name: '', description: '', sort_order: 0, is_active: true })
const durationOpen = ref(false)
const duration = ref<AcademyDurationRecord | null>(null)
const months = ref<number | null>(null)

const tabs = computed(() => [
  { title: t('academy.catalog.programs'), value: 'programs' },
  { title: t('academy.catalog.times'), value: 'times' },
  { title: t('academy.catalog.teachers'), value: 'teachers' },
  { title: t('academy.catalog.durations'), value: 'durations' },
])

async function load() {
  loading.value = true
  failed.value = false
  try {
    if (isDuration.value)
      durations.value = await AcademyClassesApi.durations()
    else
      rows.value = await MasterDataApi.list(catalog.value)
  }
  catch {
    failed.value = true
  }
  finally {
    loading.value = false
  }
}

function open(row?: MasterDataRecord) {
  editing.value = row ?? null
  form.value = row
    ? { ...row }
    : { name: '', description: '', sort_order: 0, is_active: true, contact: '', email: '' }
  dialogOpen.value = true
}

async function save() {
  if (!form.value.name?.trim())
    return
  try {
    if (editing.value)
      await MasterDataApi.update(catalog.value, editing.value.id, form.value)
    else
      await MasterDataApi.create(catalog.value, form.value)
    dialogOpen.value = false
    toast.success(t('academy.catalog.saved'))
    await load()
  }
  catch { /* Global notifier owns the API error. */ }
}

async function remove(row: MasterDataRecord) {
  if (!confirm(t('academy.catalog.removeConfirm', { name: row.name })))
    return
  try {
    await MasterDataApi.remove(catalog.value, row.id)
    toast.success(t('academy.catalog.removed'))
    await load()
  }
  catch { /* Global notifier owns the API error. */ }
}

function openDuration(row: AcademyDurationRecord) {
  duration.value = row
  months.value = row.months
  durationOpen.value = true
}

async function saveDuration() {
  if (!duration.value)
    return
  try {
    await AcademyClassesApi.updateDuration(duration.value.id, months.value)
    durationOpen.value = false
    toast.success(t('academy.catalog.saved'))
    await load()
  }
  catch { /* Global notifier owns the API error. */ }
}

watch(tab, load)
onMounted(load)
</script>

<template>
  <div class="academy-page pa-4 pa-sm-6">
    <ErpPageHeader :title="t('academy.catalog.title')" :description="t('academy.catalog.description')" section="academy">
      <template #actions>
        <VBtn v-if="canManage && !isDuration" color="primary" prepend-icon="tabler-plus" @click="open()">
          {{ t('academy.catalog.add') }}
        </VBtn>
      </template>
    </ErpPageHeader>

    <VCard>
      <VTabs v-model="tab" class="px-4 pt-2">
        <VTab v-for="item in tabs" :key="item.value" :value="item.value">{{ item.title }}</VTab>
      </VTabs>
      <VCardText>
        <TableLoadingSkeleton v-if="loading" :columns="4" :label="t('common.messages.loading')" />
        <VAlert v-else-if="failed" type="error" variant="tonal">{{ t('academy.catalog.loadFailed') }}</VAlert>
        <TableEmptyState v-else-if="isDuration && durations.length === 0" icon="tabler-clock-stop" :title="t('academy.catalog.emptyTitle')" :description="t('academy.catalog.emptyDescription')" />
        <TableEmptyState v-else-if="!isDuration && rows.length === 0" icon="tabler-book-2" :title="t('academy.catalog.emptyTitle')" :description="t('academy.catalog.emptyDescription')" />
        <VTable v-else-if="isDuration" density="compact">
          <thead><tr><th>{{ t('common.fields.name') }}</th><th>{{ t('academy.fields.months') }}</th><th>{{ t('common.fields.status') }}</th><th class="text-end">{{ t('common.fields.actions') }}</th></tr></thead>
          <tbody>
            <tr v-for="row in durations" :key="row.id">
              <td>{{ row.name }}</td>
              <td>{{ row.months ?? '—' }}</td>
              <td><VChip size="small" :color="row.is_active ? 'success' : 'secondary'">{{ t(row.is_active ? 'common.statuses.active' : 'common.statuses.inactive') }}</VChip></td>
              <td class="text-end"><VBtn v-if="canManage" icon="tabler-pencil" size="small" variant="text" :aria-label="t('common.actions.edit')" @click="openDuration(row)" /></td>
            </tr>
          </tbody>
        </VTable>
        <VTable v-else density="compact">
          <thead><tr><th>{{ t('common.fields.name') }}</th><th>{{ t('common.fields.details') }}</th><th>{{ t('common.fields.status') }}</th><th class="text-end">{{ t('common.fields.actions') }}</th></tr></thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>{{ row.name }}</td>
              <td>{{ row.contact || row.email || row.description || '—' }}</td>
              <td><VChip size="small" :color="row.is_active ? 'success' : 'secondary'">{{ t(row.is_active ? 'common.statuses.active' : 'common.statuses.inactive') }}</VChip></td>
              <td class="text-end">
                <VBtn v-if="canManage" icon="tabler-pencil" size="small" variant="text" :aria-label="t('common.actions.edit')" @click="open(row)" />
                <VBtn v-if="canManage" icon="tabler-trash" size="small" variant="text" color="error" :aria-label="t('common.actions.delete')" @click="remove(row)" />
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <VDialog v-model="dialogOpen" max-width="560">
      <VCard>
        <VCardTitle>{{ t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: t(`academy.catalog.${tab}`) }) }}</VCardTitle>
        <VCardText>
          <VTextField v-model="form.name" :label="t('common.fields.name')" class="mb-3" />
          <VTextField v-model="form.sort_order" type="number" min="0" :label="t('academy.fields.sortOrder')" class="mb-3" />
          <VTextField v-if="tab === 'teachers'" v-model="form.contact" :label="t('academy.fields.contact')" class="mb-3" />
          <VTextField v-if="tab === 'teachers'" v-model="form.email" type="email" :label="t('common.fields.email')" class="mb-3" />
          <VTextarea v-model="form.description" :label="t('common.fields.description')" />
          <VSwitch v-model="form.is_active" :label="t('common.statuses.active')" />
        </VCardText>
        <VCardActions><VSpacer /><VBtn @click="dialogOpen = false">{{ t('common.actions.cancel') }}</VBtn><VBtn color="primary" :disabled="!form.name?.trim()" @click="save">{{ t('common.actions.save') }}</VBtn></VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="durationOpen" max-width="420">
      <VCard>
        <VCardTitle>{{ t('academy.catalog.editMonths', { name: duration?.name ?? '' }) }}</VCardTitle>
        <VCardText>
          <VTextField v-model.number="months" type="number" min="1" max="60" :label="t('academy.fields.months')" />
        </VCardText>
        <VCardActions><VSpacer /><VBtn @click="durationOpen = false">{{ t('common.actions.cancel') }}</VBtn><VBtn color="primary" @click="saveDuration">{{ t('common.actions.save') }}</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
