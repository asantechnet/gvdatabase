<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import MasterDataApi, { type EducationalCatalog, type MasterDataRecord } from '@/api/educationalMasterData'
import PackagesApi from '@/api/packages'
import ServiceApi, { type Service } from '@/api/services'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const { t } = useI18n()
const catalog = ref<EducationalCatalog>('countries')
const rows = ref<MasterDataRecord[]>([])
const countries = ref<MasterDataRecord[]>([])
const services = ref<Service[]>([])
const dialog = ref(false)
const editing = ref<MasterDataRecord | null>(null)
const loading = ref(false)
const form = ref<Partial<MasterDataRecord>>({ name: '', is_active: true })
const labels = computed<Record<EducationalCatalog, string>>(() => ({ countries: t('educational.masterData.countries'), 'education-levels': t('educational.masterData.educationLevels'), languages: t('educational.masterData.languages'), 'package-types': t('educational.masterData.packageTypes'), universities: t('educational.masterData.universities'), semesters: t('educational.semester') }))
const hasCode = computed(() => ['countries', 'languages', 'semesters'].includes(catalog.value))
const canManageCurrent = computed(() => catalog.value !== 'package-types' || canAccess('manage_packages'))

async function load() {
  loading.value = true
  try {
    rows.value = catalog.value === 'package-types' ? await PackagesApi.list() as MasterDataRecord[] : await MasterDataApi.list(catalog.value)
    countries.value = await MasterDataApi.list('countries')
    if (catalog.value === 'package-types')
      services.value = (await ServiceApi.getAll()).data.filter(row => row.status === 'active')
  }
  catch { toast.error(t('educational.masterData.loadFailed')) }
  finally { loading.value = false }
}

function open(row?: MasterDataRecord) {
  editing.value = row ?? null
  form.value = row ? { ...row, service_ids: row.services?.map(service => service.id) ?? [] } : { name: '', is_active: true, service_ids: [] }
  dialog.value = true
}

async function save() {
  try {
    if (catalog.value === 'package-types') {
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

async function remove(row: MasterDataRecord) {
  if (!confirm(t('educational.masterData.removeConfirm', { name: row.name }))) return
  try {
    if (catalog.value === 'package-types') await PackagesApi.remove(row.id)
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
          <thead><tr><th>{{ $t('common.fields.name') }}</th><th v-if="hasCode">{{ $t('common.fields.code') }}</th><th>{{ $t('educational.masterData.details') }}</th><th>{{ $t('common.fields.status') }}</th><th /></tr></thead>
          <tbody><tr v-for="row in rows" :key="row.id"><td>{{ row.name }}</td><td v-if="hasCode">{{ row.code }}</td><td>{{ row.services?.map(service => service.type).join(', ') || row.city || row.description || '—' }}</td><td><VChip :color="row.is_active ? 'success':'secondary'" size="small">{{ row.is_active ? $t('common.statuses.active') : $t('common.statuses.inactive') }}</VChip></td><td class="text-no-wrap"><template v-if="canManageCurrent"><VBtn icon="tabler-pencil" size="small" variant="text" @click="open(row)" /><VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="remove(row)" /></template></td></tr></tbody>
        </VTable></div>
      </VCardText>
    </VCard>
  </div>

  <VDialog v-model="dialog" max-width="560"><VCard><VCardTitle>{{ $t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: labels[catalog] }) }}</VCardTitle><VCardText>
    <VTextField v-model="form.name" :label="$t('common.fields.name')" class="mb-3" />
    <VTextField v-if="hasCode" v-model="form.code" :label="$t('common.fields.code')" class="mb-3" />
    <VTextField v-if="catalog==='education-levels'" v-model.number="form.sort_order" type="number" min="0" :label="$t('educational.masterData.displayOrder')" :hint="$t('educational.masterData.displayOrderHint')" persistent-hint class="mb-3" />
    <VSelect v-if="catalog==='universities'" v-model="form.country_id" :items="countries.filter(x=>x.is_active).map(x=>({title:x.name,value:x.id}))" :label="$t('educational.country')" clearable class="mb-3" />
    <VTextField v-if="catalog==='universities'" v-model="form.city" :label="$t('educational.masterData.city')" class="mb-3" />
    <VTextField v-if="catalog==='universities'" v-model="form.website" :label="$t('educational.masterData.website')" class="mb-3" />
    <VSelect v-if="catalog==='package-types'" v-model="form.service_ids" :items="services.map(service => ({ title: `${service.type} (${service.category.replace('_', ' ')})`, value: service.id }))" label="Included services" multiple chips closable-chips hint="Each selected service is issued once when this package is sold." persistent-hint class="mb-3" />
    <VTextarea v-if="!hasCode" v-model="form.description" :label="$t('common.fields.description')" />
    <VSwitch v-model="form.is_active" :label="$t('common.statuses.active')" />
  </VCardText><VCardActions><VSpacer /><VBtn @click="dialog=false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :disabled="catalog === 'package-types' && !form.service_ids?.length" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions></VCard></VDialog>
</template>
