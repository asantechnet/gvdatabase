<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import MasterDataApi, { type EducationalCatalog, type MasterDataRecord } from '@/api/educationalMasterData'
import CrmRelationshipDetails from '@/components/crm/CrmRelationshipDetails.vue'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import { useAcademyEnabled } from '@/composables/useAcademyEnabled'
import { moveCreatedRowFirst } from '@/utils/createdFirst'
import { canAccess } from '@/utils/authorization'
import { useAuditTrail } from '@/composables/useAuditTrail'
import { auditCellClass, auditCellTitle, auditTrailRowClass } from '@/utils/auditTrail'
import AuditTrailButton from '@/components/audit/AuditTrailButton.vue'
import AuditTrailLegend from '@/components/audit/AuditTrailLegend.vue'

const toast = useToast()
const { t } = useI18n()
const { enabled: academyEnabled, load: loadAcademyEnabled } = useAcademyEnabled()
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
// Times, durations, teachers and classes moved to the Academy workspace
// (see `pages/academy/catalog.vue` and `pages/academy/classes.vue`).
const labels = computed<Record<Catalog, string>>(() => {
  const items: Record<Catalog, string> = { countries: t('educational.masterData.countries'), 'education-levels': t('educational.masterData.educationLevels'), languages: t('educational.masterData.languages'), universities: t('educational.masterData.universities'), semesters: t('educational.semester'), subjects: t('educational.masterData.subjects'), tests: t('educational.masterData.tests'), skills: t('educational.masterData.skills'), programs: t('educational.masterData.programs'), scholarships: t('erp.scholarship'), provinces: t('educational.masterData.provinces'), 'english-levels': t('educational.masterData.englishLevels'), 'acquisition-routes': t('educational.masterData.acquisitionRoutes') }
  if (!academyEnabled.value)
    delete (items as Partial<Record<Catalog, string>>).programs
  return items
})
const hasCode = computed(() => ['countries', 'languages', 'semesters'].includes(catalog.value))
const isSortedCatalog = computed(() => ['education-levels', 'subjects', 'tests', 'skills', 'scholarships', 'provinces', 'english-levels', 'acquisition-routes'].includes(catalog.value))
// Countries and universities are the two catalogues a visa case contacts
// directly, so they carry the optional phone, email and portal address.
const hasContact = computed(() => ['countries', 'universities'].includes(catalog.value))
const canEditCatalog = computed(() => canAccess(catalog.value === 'countries' ? 'edit_country' : 'edit_university'))

// The raw catalogue table cannot infer its resource key from a response tag.
const auditResourceByCatalog: Record<Catalog, string | null> = {
  'countries': 'countries',
  'education-levels': 'education_levels',
  'languages': 'languages',
  'universities': 'universities',
  'semesters': 'semesters',
  'subjects': 'subjects',
  'tests': 'tests',
  'skills': 'skills',
  'programs': 'programs',
  'scholarships': 'educational_scholarships',
  'provinces': null,
  'english-levels': null,
  'acquisition-routes': null,
}
const auditResource = computed(() => auditResourceByCatalog[catalog.value])
const { trailFor, legendVisible } = useAuditTrail(() => rows.value, { resource: auditResource })
const trailForRow = (row: CatalogRow) => trailFor(row.id)

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
    const wasNew = !editing.value
    const saved = editing.value
      ? await MasterDataApi.update(catalog.value, editing.value.id, form.value)
      : await MasterDataApi.create(catalog.value, form.value)
    dialog.value = false
    await load()
    if (wasNew)
      rows.value = moveCreatedRowFirst(rows.value, saved)
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
onMounted(() => Promise.all([loadAcademyEnabled(), load()]))
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
        <AuditTrailLegend v-if="legendVisible" class="mb-2" />
        <div class="education-table-scroll"><VTable density="compact" class="education-table">
          <thead><tr><th>{{ $t('common.fields.name') }}</th><th v-if="hasCode">{{ $t('common.fields.code') }}</th><th>{{ $t('educational.masterData.details') }}</th><th>{{ $t('common.fields.status') }}</th><th /></tr></thead>
          <tbody><tr v-for="row in rows" :key="row.id" :class="auditTrailRowClass(trailForRow(row))"><td :class="auditCellClass(trailForRow(row), 'name')" :title="auditCellTitle(trailForRow(row), 'name')">{{ row.name }}</td><td v-if="hasCode">{{ row.code }}</td><td :class="auditCellClass(trailForRow(row), 'description')" :title="auditCellTitle(trailForRow(row), 'description')"><div class="d-flex align-center flex-wrap ga-1"><span>{{ row.phone || row.email || row.city || row.portal_url || row.contact || row.description || '—' }}</span><template v-if="['countries', 'universities'].includes(catalog)"><ContactActionMenu v-if="row.phone" kind="phone" :value="row.phone" :contactable-type="canEditCatalog ? (catalog === 'countries' ? 'country' : 'university') : undefined" :contactable-id="row.id" /><ContactActionMenu v-if="row.email" kind="email" :value="row.email" :contactable-type="canEditCatalog ? (catalog === 'countries' ? 'country' : 'university') : undefined" :contactable-id="row.id" /></template></div></td><td :class="auditCellClass(trailForRow(row), 'is_active')" :title="auditCellTitle(trailForRow(row), 'is_active')"><VChip :color="row.is_active ? 'success':'secondary'" size="small">{{ row.is_active ? $t('common.statuses.active') : $t('common.statuses.inactive') }}</VChip></td><td class="text-no-wrap"><AuditTrailButton v-if="trailForRow(row)" :trail="trailForRow(row)!" :subject="row.name" /><CrmRelationshipDetails v-if="['countries', 'universities'].includes(catalog)" :type="catalog === 'countries' ? 'country' : 'university'" :id="row.id" :title="row.name" :readonly="!canEditCatalog" /><VBtn icon="tabler-pencil" size="small" variant="text" @click="open(row)" /><VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="remove(row)" /></td></tr></tbody>
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
    <template v-if="hasContact">
      <VTextField v-model="form.phone" :label="$t('educational.masterData.phone')" class="mb-3" />
      <VTextField v-model="form.email" type="email" :label="$t('educational.masterData.email')" class="mb-3" />
      <VTextField v-model="form.portal_url" :label="$t('educational.masterData.portalUrl')" :hint="$t('educational.masterData.portalUrlHint')" persistent-hint class="mb-3" />
    </template>
    <VTextarea v-if="!hasCode" v-model="form.description" :label="$t('common.fields.description')" />

    <VSwitch v-model="form.is_active" :label="$t('common.statuses.active')" />
  </VCardText><VCardActions><VSpacer /><VBtn @click="dialog=false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions></VCard></VDialog>
</template>
