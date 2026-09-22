<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import MasterDataApi, { type EducationalCatalog, type MasterDataRecord } from '@/api/educationalMasterData'
import ApplicantApi, { type ApplicantStage } from '@/api/applicants'
import PackagesApi from '@/api/packages'
import ServiceApi, { type Service } from '@/api/services'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const { t } = useI18n()
const route = useRoute()
type Catalog = EducationalCatalog | 'applicant-stages'
type CatalogRow = MasterDataRecord & Partial<ApplicantStage>

const catalog = ref<Catalog>(route.query.catalog === 'applicant-stages' ? 'applicant-stages' : 'countries')
const rows = ref<CatalogRow[]>([])
const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const services = ref<Service[]>([])
const dialog = ref(false)
const editing = ref<CatalogRow | null>(null)
const loading = ref(false)
const form = ref<Partial<CatalogRow>>({ name: '', is_active: true })
const labels = computed<Record<Catalog, string>>(() => ({ countries: t('educational.masterData.countries'), 'education-levels': t('educational.masterData.educationLevels'), languages: t('educational.masterData.languages'), 'package-types': t('educational.masterData.packageTypes'), universities: t('educational.masterData.universities'), semesters: t('educational.semester'), 'applicant-stages': t('educational.masterData.applicantStages') }))
const isStageCatalog = computed(() => catalog.value === 'applicant-stages')
const hasCode = computed(() => ['countries', 'languages', 'semesters', 'applicant-stages'].includes(catalog.value))
const canManageCurrent = computed(() => isStageCatalog.value ? canAccess('manage_applicant_stages') : catalog.value !== 'package-types' || canAccess('manage_packages'))

async function load() {
  loading.value = true
  try {
    rows.value = catalog.value === 'applicant-stages'
      ? await ApplicantApi.stageDefinitions() as CatalogRow[]
      : catalog.value === 'package-types' ? await PackagesApi.list() as MasterDataRecord[] : await MasterDataApi.list(catalog.value)
    ;[countries.value, universities.value] = await Promise.all([MasterDataApi.list('countries'), MasterDataApi.list('universities')])
    if (catalog.value === 'package-types')
      services.value = (await ServiceApi.getAll()).data.filter(row => row.status === 'active')
  }
  catch { toast.error(t('educational.masterData.loadFailed')) }
  finally { loading.value = false }
}

function open(row?: CatalogRow) {
  editing.value = row ?? null
  form.value = row ? { ...row, service_ids: row.services?.map(service => service.id) ?? [], allowed_next_keys: [...(row.allowed_next_keys ?? [])] } : isStageCatalog.value ? { key: '', name: '', sort_order: 0, is_active: true, is_terminal: false, allowed_next_keys: [] } : { name: '', is_active: true, service_ids: [] }
  dialog.value = true
}

function stageKeyFromName(name: string): string {
  return name.trim().toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '_').replace(/^_+|_+$/g, '')
}

async function save() {
  try {
    if (isStageCatalog.value) {
      if (editing.value) await ApplicantApi.updateStageDefinition(editing.value.id, form.value)
      else await ApplicantApi.createStage({ ...form.value, key: stageKeyFromName(form.value.name ?? '') })
    } else if (catalog.value === 'package-types') {
      const payload = { name: form.value.name || '', description: form.value.description, is_active: form.value.is_active, service_ids: form.value.service_ids ?? [] }
      if (editing.value) await PackagesApi.update(editing.value.id, payload)
      else await PackagesApi.create(payload)
    }
    else if (editing.value) await MasterDataApi.update(catalog.value, editing.value.id, form.value)
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
    if (isStageCatalog.value) await ApplicantApi.removeStageDefinition(row.id)
    else if (catalog.value === 'package-types') await PackagesApi.remove(row.id)
    else await MasterDataApi.remove(catalog.value, row.id)
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
        <VBtn v-if="canManageCurrent" prepend-icon="tabler-plus" @click="open()">{{ $t('common.actions.add') }}</VBtn>
      </VCardTitle>
      <VCardText>
        <VTabs v-model="catalog" show-arrows><VTab v-for="(label,key) in labels" :key="key" :value="key">{{ label }}</VTab></VTabs>
        <VProgressLinear v-if="loading" indeterminate class="my-4" />
        <div class="education-table-scroll"><VTable density="compact" class="education-table">
          <thead><tr><th>{{ $t('common.fields.name') }}</th><th v-if="hasCode && !isStageCatalog">{{ $t('common.fields.code') }}</th><th>{{ $t('educational.masterData.details') }}</th><th>{{ $t('common.fields.status') }}</th><th /></tr></thead>
          <tbody><tr v-for="row in rows" :key="row.id"><td>{{ row.name }}</td><td v-if="hasCode && !isStageCatalog">{{ row.code }}</td><td>{{ isStageCatalog ? $t('educational.masterData.stageDetails', { order: row.sort_order ?? 0, next: (row.allowed_next_keys ?? []).join(', ') || '—' }) : row.services?.map(service => service.type).join(', ') || row.city || row.description || '—' }}</td><td><VChip :color="row.is_active ? 'success':'secondary'" size="small">{{ row.is_active ? $t('common.statuses.active') : $t('common.statuses.inactive') }}</VChip><VChip v-if="isStageCatalog && row.is_terminal" class="ms-2" color="warning" size="small">{{ $t('educational.masterData.terminalStage') }}</VChip></td><td class="text-no-wrap"><template v-if="canManageCurrent"><VBtn icon="tabler-pencil" size="small" variant="text" @click="open(row)" /><VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="remove(row)" /></template></td></tr></tbody>
        </VTable></div>
      </VCardText>
    </VCard>
  </div>

  <VDialog v-model="dialog" max-width="560"><VCard><VCardTitle>{{ $t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: labels[catalog] }) }}</VCardTitle><VCardText>
    <VTextField v-model="form.name" :label="$t('common.fields.name')" class="mb-3" />
    <VTextField v-if="hasCode && !isStageCatalog" v-model="form.code" :label="$t('common.fields.code')" class="mb-3" />
    <VTextField v-if="catalog==='education-levels' || isStageCatalog" v-model.number="form.sort_order" type="number" min="0" :label="$t('educational.masterData.displayOrder')" :hint="$t('educational.masterData.displayOrderHint')" persistent-hint class="mb-3" />
    <VSelect v-if="catalog==='universities' || isStageCatalog" v-model="form.country_id" :items="countries.filter(x=>x.is_active).map(x=>({title:x.name,value:x.id}))" :label="$t('educational.country')" clearable class="mb-3" />
    <VSelect v-if="isStageCatalog" v-model="form.university_id" :items="universities.filter(u=>!form.country_id||u.country_id===form.country_id).map(u=>({title:u.name,value:u.id}))" :label="$t('educational.university')" clearable class="mb-3" />
    <VTextField v-if="catalog==='universities'" v-model="form.city" :label="$t('educational.masterData.city')" class="mb-3" />
    <VTextField v-if="catalog==='universities'" v-model="form.website" :label="$t('educational.masterData.website')" class="mb-3" />
    <VSelect v-if="catalog==='package-types'" v-model="form.service_ids" :items="services.map(service => ({ title: `${service.type} (${service.category.replace('_', ' ')})`, value: service.id }))" label="Included services" multiple chips closable-chips hint="Each selected service is issued once when this package is sold." persistent-hint class="mb-3" />
    <VTooltip v-if="isStageCatalog" location="top" :text="$t('educational.masterData.allowedNextStagesHint')"><template #activator="{ props }"><div v-bind="props"><VCombobox v-model="form.allowed_next_keys" :items="rows.filter(row => row.id !== editing?.id).map(row => ({ title: `${row.name} (${row.key})`, value: row.key }))" item-title="title" item-value="value" multiple chips :label="$t('educational.masterData.allowedNextStages')" class="mb-3" /></div></template></VTooltip>
    <VTooltip v-if="isStageCatalog" location="top" :text="$t('educational.masterData.terminalStageHint')"><template #activator="{ props }"><div v-bind="props"><VSwitch v-model="form.is_terminal" :label="$t('educational.masterData.terminalStage')" /></div></template></VTooltip>
    <VTextarea v-if="!hasCode" v-model="form.description" :label="$t('common.fields.description')" />
    <VTooltip v-if="isStageCatalog" location="top" :text="$t('educational.masterData.activeStageHint')"><template #activator="{ props }"><div v-bind="props"><VSwitch v-model="form.is_active" :label="$t('common.statuses.active')" /></div></template></VTooltip><VSwitch v-else v-model="form.is_active" :label="$t('common.statuses.active')" />
  </VCardText><VCardActions><VSpacer /><VBtn @click="dialog=false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :disabled="catalog === 'package-types' && !form.service_ids?.length" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions></VCard></VDialog>
</template>
