<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import MasterDataApi, { type EducationalCatalog, type MasterDataRecord } from '@/api/educationalMasterData'

const toast = useToast()
const { t } = useI18n()
type Catalog = Exclude<EducationalCatalog, 'times' | 'durations' | 'teachers' | 'classes'>
type CatalogRow = MasterDataRecord

// Applicant pipeline stages have their own service-scoped editor at
// `/applicant-stages`; they are not a master-data catalogue.
const catalog = ref<Catalog>('countries')
const rows = ref<CatalogRow[]>([])
const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const dialog = ref(false)
const editing = ref<CatalogRow | null>(null)
const loading = ref(false)
const form = ref<Partial<CatalogRow>>({ name: '', is_active: true })
// Times, durations, teachers and classes moved to the academy data page under
// Options -> Contracts (see `pages/academy-data.vue`).
const labels = computed<Record<Catalog, string>>(() => ({ countries: t('educational.masterData.countries'), 'education-levels': t('educational.masterData.educationLevels'), languages: t('educational.masterData.languages'), universities: t('educational.masterData.universities'), semesters: t('educational.semester'), subjects: t('educational.masterData.subjects'), tests: t('educational.masterData.tests'), skills: t('educational.masterData.skills'), programs: t('educational.masterData.programs'), scholarships: t('erp.scholarship') }))
const hasCode = computed(() => ['countries', 'languages', 'semesters'].includes(catalog.value))
const isSortedCatalog = computed(() => ['education-levels', 'subjects', 'tests', 'skills', 'programs', 'scholarships'].includes(catalog.value))

async function load() {
  loading.value = true
  try {
    rows.value = await MasterDataApi.list(catalog.value)
    ;[countries.value, universities.value] = await Promise.all([MasterDataApi.list('countries'), MasterDataApi.list('universities')])
  }
  catch { toast.error(t('educational.masterData.loadFailed')) }
  finally { loading.value = false }
}

function open(row?: CatalogRow) {
  editing.value = row ?? null
  form.value = row ? { ...row } : { name: '', is_active: true }
  dialog.value = true
}

async function save() {
  try {
    if (editing.value) await MasterDataApi.update(catalog.value, editing.value.id, form.value)
    else await MasterDataApi.create(catalog.value, form.value)
    dialog.value = false
    await load()
    toast.success(t('educational.masterData.saved'))
  }
  catch (error: any) { toast.error(error.message ?? t('educational.masterData.saveFailed')) }
}

async function remove(row: CatalogRow) {
  if (!confirm(t('educational.masterData.removeConfirm', { name: row.name }))) return
  try {
    await MasterDataApi.remove(catalog.value, row.id)
    await load()
  }
  catch (error: any) { toast.error(error.message ?? t('educational.masterData.inUse')) }
}

watch(catalog, load)
onMounted(load)
</script>

<template>
  <div class="education-workspace">
    <VCard class="education-panel">
      <VCardTitle class="d-flex justify-space-between">
        <span>{{ $t('educational.masterData.title', { catalog: labels[catalog] }) }}</span>
        <VBtn prepend-icon="tabler-plus" @click="open()">{{ $t('common.actions.add') }}</VBtn>
      </VCardTitle>
      <VCardText>
        <VTabs v-model="catalog" show-arrows><VTab v-for="(label,key) in labels" :key="key" :value="key">{{ label }}</VTab></VTabs>
        <VProgressLinear v-if="loading" indeterminate class="my-4" />
        <div class="education-table-scroll"><VTable density="compact" class="education-table">
          <thead><tr><th>{{ $t('common.fields.name') }}</th><th v-if="hasCode">{{ $t('common.fields.code') }}</th><th>{{ $t('educational.masterData.details') }}</th><th>{{ $t('common.fields.status') }}</th><th /></tr></thead>
          <tbody><tr v-for="row in rows" :key="row.id"><td>{{ row.name }}</td><td v-if="hasCode">{{ row.code }}</td><td>{{ row.contact || row.city || row.email || row.description || '—' }}</td><td><VChip :color="row.is_active ? 'success':'secondary'" size="small">{{ row.is_active ? $t('common.statuses.active') : $t('common.statuses.inactive') }}</VChip></td><td class="text-no-wrap"><VBtn icon="tabler-pencil" size="small" variant="text" @click="open(row)" /><VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="remove(row)" /></td></tr></tbody>
        </VTable></div>
      </VCardText>
    </VCard>
  </div>

  <VDialog v-model="dialog" max-width="560"><VCard><VCardTitle>{{ $t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: labels[catalog] }) }}</VCardTitle><VCardText>
    <VTextField v-model="form.name" :label="$t('common.fields.name')" class="mb-3" />
    <VTextField v-if="hasCode" v-model="form.code" :label="$t('common.fields.code')" class="mb-3" />
    <VTextField v-if="isSortedCatalog" v-model.number="form.sort_order" type="number" min="0" :label="$t('educational.masterData.displayOrder')" :hint="$t('educational.masterData.displayOrderHint')" persistent-hint class="mb-3" />
    <VSelect v-if="catalog==='universities'" v-model="form.country_id" :items="countries.filter(x=>x.is_active).map(x=>({title:x.name,value:x.id}))" :label="$t('educational.country')" clearable class="mb-3" />
    <VTextField v-if="catalog==='universities'" v-model="form.city" :label="$t('educational.masterData.city')" class="mb-3" />
    <VTextField v-if="catalog==='universities'" v-model="form.website" :label="$t('educational.masterData.website')" class="mb-3" />
    <VTextarea v-if="!hasCode" v-model="form.description" :label="$t('common.fields.description')" />

    <VSwitch v-model="form.is_active" :label="$t('common.statuses.active')" />
  </VCardText><VCardActions><VSpacer /><VBtn @click="dialog=false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions></VCard></VDialog>
</template>
