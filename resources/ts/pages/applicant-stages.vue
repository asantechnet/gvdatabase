<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import ApplicantStagesApi, { type ApplicantStage } from '@/api/applicantStages'
import type { BuiltInService } from '@/api/applicants'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import { confirmActionWarning } from '@/composables/useActionWarning'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import { canAccess } from '@/utils/authorization'
import { moveCreatedRowFirst } from '@/utils/createdFirst'
import { translateApplicantStage } from '@/utils/localization'

type StageStatus = '' | 'active' | 'inactive'
type Filters = { service: BuiltInService; status: StageStatus; country_id: number | null; university_id: number | null; test_id: number | null }
const { t } = useI18n()
const toast = useToast()
const blankFilters = (): Filters => ({ service: 'scholarship', status: '', country_id: null, university_id: null, test_id: null })
const filters = ref<Filters>(blankFilters())
const filterDraft = ref<Filters>(blankFilters())
const stages = ref<ApplicantStage[]>([])
const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const tests = ref<MasterDataRecord[]>([])
const loading = ref(false)
const loadFailed = ref(false)
const search = ref('')
const filterDialog = ref(false)
const dialog = ref(false)
const deleteDialog = ref(false)
const saving = ref(false)
const editing = ref<ApplicantStage | null>(null)
const pendingDelete = ref<ApplicantStage | null>(null)
const initialForm = ref('')
const canManage = computed(() => canAccess('manage_applicant_stages'))
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  { title: t('educational.builtInServices.academy'), value: 'academy' },
  { title: t('educational.builtInServices.private'), value: 'private' },
])
const statusOptions = computed(() => [{ title: t('common.statuses.all'), value: '' }, { title: t('common.statuses.active'), value: 'active' }, { title: t('common.statuses.inactive'), value: 'inactive' }])
const blankForm = () => ({ name: '', sort_order: 0, is_active: true, country_id: null as number | null, university_id: null as number | null, test_id: null as number | null, allowed_next_keys: [] as string[] })
const form = ref(blankForm())
const formService = computed<BuiltInService>(() => editing.value?.service ?? filters.value.service)
const usesAdmissionScope = computed(() => ['scholarship', 'private'].includes(formService.value))
const usesTestScope = computed(() => formService.value === 'test_offer')
const countryOptions = computed(() => countries.value.filter(row => row.is_active).map(row => ({ title: row.name, value: row.id })))
const universityOptions = computed(() => universities.value.filter(row => row.is_active && (!form.value.country_id || row.country_id === form.value.country_id)).map(row => ({ title: row.name, value: row.id })))
const filterUniversityOptions = computed(() => universities.value.filter(row => row.is_active && (!filterDraft.value.country_id || row.country_id === filterDraft.value.country_id)).map(row => ({ title: row.name, value: row.id })))
const testOptions = computed(() => [{ title: t('educational.masterData.allTests'), value: null }, ...tests.value.filter(row => row.is_active).map(row => ({ title: row.name, value: row.id }))])
const testNameById = computed(() => Object.fromEntries(tests.value.map(row => [row.id, row.name])))
const stageNameByKey = computed(() => Object.fromEntries(stages.value.map(stage => [stage.key, translateApplicantStage(stage)])))
const nextStageOptions = computed(() => stages.value.filter(stage => stage.id !== editing.value?.id).map(stage => ({ title: translateApplicantStage(stage), value: stage.key })))
const dependencyHeader = computed(() => filters.value.service === 'academy' ? null : filters.value.service === 'test_offer' ? t('educational.masterData.testScope') : `${t('educational.country')} / ${t('educational.university')}`)
const headers = computed(() => [
  { title: t('common.fields.name'), key: 'name', minWidth: '210px' }, { title: t('educational.service'), key: 'service', minWidth: '150px' }, { title: t('educational.masterData.displayOrder'), key: 'sort_order', minWidth: '120px' }, { title: t('educational.masterData.allowedNextStages'), key: 'next_stages', minWidth: '220px' }, ...(dependencyHeader.value ? [{ title: dependencyHeader.value, key: 'scope', minWidth: '190px' }] : []), { title: t('common.fields.status'), key: 'status', minWidth: '150px' }, { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const, minWidth: '92px' },
])
const serviceTitle = (value?: BuiltInService | null) => serviceOptions.value.find(option => option.value === value)?.title ?? '—'
function nextStagesLabel(stage: ApplicantStage) { return (stage.allowed_next_keys ?? []).map(key => stageNameByKey.value[key]).filter(Boolean).join(', ') || '—' }
function scopeLabel(stage: ApplicantStage) {
  if (stage.service === 'academy') return t('educational.masterData.noDependency')
  if (stage.service === 'test_offer') return stage.test_id ? testNameById.value[stage.test_id] ?? '—' : t('educational.masterData.allTests')
  return [countries.value.find(row => row.id === stage.country_id)?.name, universities.value.find(row => row.id === stage.university_id)?.name].filter(Boolean).join(' · ') || '—'
}
const filteredStages = computed(() => {
  const query = search.value.trim().toLocaleLowerCase()
  return stages.value.filter(stage => {
    const matchesSearch = !query || [translateApplicantStage(stage), stage.key, nextStagesLabel(stage), scopeLabel(stage)].some(value => String(value).toLocaleLowerCase().includes(query))
    const matchesStatus = !filters.value.status || Boolean(stage.is_active) === (filters.value.status === 'active')
    return matchesSearch && matchesStatus && (!filters.value.country_id || stage.country_id === filters.value.country_id) && (!filters.value.university_id || stage.university_id === filters.value.university_id) && (!filters.value.test_id || stage.test_id === filters.value.test_id)
  })
})
const hasActiveFilters = computed(() => filters.value.service !== 'scholarship' || Boolean(filters.value.status || filters.value.country_id || filters.value.university_id || filters.value.test_id))
const snapshot = () => JSON.stringify(form.value)
const isDirty = computed(() => snapshot() !== initialForm.value)
async function loadStages() { loading.value = true; loadFailed.value = false; try { stages.value = await ApplicantStagesApi.catalogue(filters.value.service) } catch { stages.value = []; loadFailed.value = true } finally { loading.value = false } }
async function loadCatalogs() {
  const [countryRows, universityRows, testRows] = await Promise.allSettled([MasterDataApi.list('countries'), MasterDataApi.list('universities'), MasterDataApi.list('tests')])
  if (countryRows.status === 'fulfilled') countries.value = countryRows.value
  if (universityRows.status === 'fulfilled') universities.value = universityRows.value
  if (testRows.status === 'fulfilled') tests.value = testRows.value
}
function syncFilterDraft() { filterDraft.value = { ...filters.value } }
async function applyFilters() { const changed = filters.value.service !== filterDraft.value.service; filters.value = { ...filterDraft.value }; filterDialog.value = false; if (changed) await loadStages() }
async function resetFilters() { const next = blankFilters(); const changed = filters.value.service !== next.service; filters.value = next; filterDraft.value = { ...next }; filterDialog.value = false; if (changed) await loadStages() }
function open(stage?: ApplicantStage) {
  if (stage?.is_locked) return
  editing.value = stage ?? null
  form.value = stage ? { name: stage.name, sort_order: stage.sort_order ?? 0, is_active: stage.is_active ?? true, country_id: stage.country_id ?? null, university_id: stage.university_id ?? null, test_id: stage.test_id ?? null, allowed_next_keys: [...(stage.allowed_next_keys ?? [])] } : blankForm()
  initialForm.value = snapshot(); dialog.value = true
}
async function requestClose() {
  if (isDirty.value && !await confirmActionWarning({ key: 'discard-form-changes', title: t('common.actionWarnings.discardTitle'), message: t('common.actionWarnings.discardMessage'), confirmText: t('common.actionWarnings.discardConfirm'), cancelText: t('common.actions.cancel'), icon: 'tabler-file-alert', tone: 'primary' })) return
  dialog.value = false
}
async function save() {
  if (!form.value.name.trim()) return
  saving.value = true
  try {
    const payload = { name: form.value.name.trim(), country_id: usesAdmissionScope.value ? form.value.country_id : null, university_id: usesAdmissionScope.value ? form.value.university_id : null, test_id: usesTestScope.value ? form.value.test_id : null, sort_order: form.value.sort_order ?? 0, allowed_next_keys: form.value.allowed_next_keys ?? [], is_active: Boolean(form.value.is_active) }
    const wasNew = !editing.value
    const saved = editing.value ? await ApplicantStagesApi.update(editing.value.id, payload) : await ApplicantStagesApi.create({ ...payload, service: filters.value.service })
    dialog.value = false; await loadStages(); if (wasNew) stages.value = moveCreatedRowFirst(stages.value, saved); toast.success(t('educational.masterData.saved'))
  } catch (error: any) { toast.error(error.message ?? t('educational.masterData.saveFailed')) } finally { saving.value = false }
}
function requestDelete(stage: ApplicantStage) { if (!stage.is_locked) { pendingDelete.value = stage; deleteDialog.value = true } }
async function remove() { if (!pendingDelete.value) return; saving.value = true; try { await ApplicantStagesApi.remove(pendingDelete.value.id); deleteDialog.value = false; pendingDelete.value = null; await loadStages() } catch (error: any) { toast.error(error.message ?? t('educational.masterData.inUse')) } finally { saving.value = false } }
watch(() => filterDraft.value.country_id, () => { if (filterDraft.value.university_id && !filterUniversityOptions.value.some(item => item.value === filterDraft.value.university_id)) filterDraft.value.university_id = null })
watch(() => filterDraft.value.service, service => { if (!['scholarship', 'private'].includes(service)) { filterDraft.value.country_id = null; filterDraft.value.university_id = null } if (service !== 'test_offer') filterDraft.value.test_id = null })
watch(() => form.value.country_id, () => { if (form.value.university_id && !universityOptions.value.some(item => item.value === form.value.university_id)) form.value.university_id = null })
onMounted(async () => { await Promise.all([loadStages(), loadCatalogs()]) })
</script>

<template>
  <div class="stage-workspace">
    <div class="stage-workspace__heading"><VBreadcrumbs :items="[{ title: t('erp.home'), to: { name: 'home' } }, { title: t('common.navigation.options'), disabled: true }]" density="compact" class="pa-0" /><h1 class="text-h5 font-weight-bold mb-0">{{ t('common.navigation.contractStages') }}</h1></div>
    <VCard class="stage-panel">
      <VCardText class="stage-toolbar pa-4"><VRow align="center" class="ma-0"><VCol cols="12" md="6" lg="5" class="pa-0"><ErpSearchField v-model="search" :placeholder="t('common.actions.search')" /></VCol><VCol cols="12" md="6" lg="7" class="d-flex flex-wrap justify-md-end ga-2 pa-0 pt-3 pt-md-0"><DataTableFilterMenu v-model="filterDialog" :active="hasActiveFilters" :show-reset="false" @update:model-value="open => { if (open) syncFilterDraft() }"><ErpField v-model="filterDraft.service" kind="select" :items="serviceOptions" :label="t('educational.service')" :placeholder="t('educational.service')" /><ErpField v-model="filterDraft.status" kind="select" :items="statusOptions" :label="t('common.fields.status')" :placeholder="t('common.fields.status')" /><template v-if="['scholarship', 'private'].includes(filterDraft.service)"><ErpField v-model="filterDraft.country_id" kind="select" :items="countryOptions" :label="t('educational.country')" :placeholder="t('educational.country')" clearable /><ErpField v-model="filterDraft.university_id" kind="select" :items="filterUniversityOptions" :label="t('educational.university')" :placeholder="t('educational.university')" clearable /></template><ErpField v-else-if="filterDraft.service === 'test_offer'" v-model="filterDraft.test_id" kind="select" :items="testOptions" :label="t('educational.masterData.testScope')" :placeholder="t('educational.masterData.testScope')" clearable /><template #actions><VBtn class="stage-filter-reset" variant="flat" @click="resetFilters">{{ t('common.messages.reset') }}</VBtn><VBtn color="primary" variant="flat" class="stage-filter-apply" @click="applyFilters">{{ t('educational.apply') }}</VBtn></template></DataTableFilterMenu><VBtn v-if="canManage" class="stage-add-button" prepend-icon="tabler-plus" @click="open()">{{ t('common.actions.add') }}</VBtn></VCol></VRow></VCardText>
      <ResponsiveDataTable :items="filteredStages" :headers="headers" item-value="id" class="stage-table" :loading="loading" :skeleton-rows="6" mobile-cards mobile-expand-all mobile-title-key="name" mobile-action-key="actions" :sticky-key-column="false" :sticky-action-column="false">
        <template #item.name="{ item }">{{ translateApplicantStage(item) }}</template><template #item.service="{ item }">{{ serviceTitle(item.service) }}</template><template #item.next_stages="{ item }">{{ nextStagesLabel(item) }}</template><template #item.scope="{ item }">{{ scopeLabel(item) }}</template>
        <template #item.status="{ item }"><div class="d-flex flex-wrap ga-1"><VChip :color="item.is_active ? 'success' : 'secondary'" size="small" variant="tonal">{{ item.is_active ? t('common.statuses.active') : t('common.statuses.inactive') }}</VChip><VChip v-if="item.is_terminal" color="warning" size="small" variant="tonal">{{ t('educational.masterData.terminalStage') }}</VChip><VChip v-if="item.is_locked" color="secondary" size="small" variant="tonal" prepend-icon="tabler-lock" :title="t('educational.masterData.lockedStageHint')">{{ t('educational.masterData.lockedStage') }}</VChip></div></template>
        <template #item.actions="{ item }"><VMenu v-if="canManage && !item.is_locked" location="bottom end"><template #activator="{ props }"><VBtn v-bind="props" icon="tabler-dots-vertical" size="small" variant="tonal" :aria-label="t('common.messages.actions')" /></template><VList density="compact"><VListItem prepend-icon="tabler-pencil" :title="t('common.actions.edit')" @click="open(item)" /><VListItem prepend-icon="tabler-trash" class="stage-table__delete" :title="t('common.actions.delete')" @click="requestDelete(item)" /></VList></VMenu></template>
        <template #no-data><div class="stage-empty-state"><TableEmptyState icon="tabler-database-off" :min-height="0" :title="loadFailed ? t('educational.masterData.loadFailed') : t('$vuetify.noDataText')" :description="loadFailed ? t('educational.masterData.loadFailed') : (search ? t('$vuetify.dataIterator.noResultsText') : t('educational.noActiveStages'))" /></div></template>
      </ResponsiveDataTable>
    </VCard>
  </div>
  <VDialog :model-value="dialog" max-width="760" scrollable content-class="stage-editor-dialog" @update:model-value="value => { if (!value) void requestClose() }"><VCard class="stage-editor"><VCardTitle class="stage-editor__header px-5 py-4"><span>{{ t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: t('educational.stage') }) }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="stage-editor__close" :disabled="saving" :aria-label="t('common.actions.close')" @click="requestClose" /></VCardTitle><VCardText class="stage-editor__body pa-5"><VForm @submit.prevent="save"><VRow><VCol cols="12" md="6"><ErpField v-model="form.name" required :label="t('common.fields.name')" :placeholder="t('common.fields.name')" /></VCol><VCol cols="12" md="6"><ErpField v-model.number="form.sort_order" type="number" min="0" :label="t('educational.masterData.displayOrder')" :placeholder="t('educational.masterData.displayOrder')" /></VCol><template v-if="usesAdmissionScope"><VCol cols="12" md="6"><ErpField v-model="form.country_id" kind="select" :items="countryOptions" :label="t('educational.country')" :placeholder="t('educational.country')" clearable /></VCol><VCol cols="12" md="6"><ErpField v-model="form.university_id" kind="select" :items="universityOptions" :label="t('educational.university')" :placeholder="t('educational.university')" clearable /></VCol></template><VCol v-if="usesTestScope" cols="12" md="6"><ErpField v-model="form.test_id" kind="select" :items="testOptions" :label="t('educational.masterData.testScope')" :placeholder="t('educational.masterData.testScope')" clearable /></VCol><VCol cols="12"><ErpField v-model="form.allowed_next_keys" kind="select" :items="nextStageOptions" item-title="title" item-value="value" multiple chips closable-chips :label="t('educational.masterData.allowedNextStages')" :placeholder="t('educational.masterData.allowedNextStages')" /></VCol><VCol cols="12"><VSwitch v-model="form.is_active" color="success" inset :label="t('common.statuses.active')" hide-details /></VCol></VRow></VForm></VCardText><VCardActions class="stage-editor__footer justify-end px-5 py-4"><VBtn class="stage-editor__cancel" :disabled="saving" @click="requestClose">{{ t('common.actions.cancel') }}</VBtn><VBtn class="stage-editor__save" :loading="saving" :disabled="!form.name.trim()" @click="save">{{ t('common.actions.save') }}</VBtn></VCardActions></VCard></VDialog>
  <VDialog v-model="deleteDialog" max-width="480" content-class="stage-delete-overlay"><VCard class="stage-delete-dialog"><VCardText class="stage-delete-dialog__content"><div class="stage-delete-dialog__icon"><VIcon icon="tabler-trash" size="30" /></div><h2>{{ t('common.actions.delete') }}</h2><p>{{ t('educational.masterData.removeConfirm', { name: pendingDelete ? translateApplicantStage(pendingDelete) : '' }) }} {{ t('common.confirmations.thisActionCannotBeUndone') }}</p></VCardText><VCardActions class="stage-delete-dialog__actions"><VBtn class="stage-editor__cancel" :disabled="saving" @click="deleteDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="stage-delete-dialog__confirm" color="error" :loading="saving" @click="remove">{{ t('common.actions.delete') }}</VBtn></VCardActions></VCard></VDialog>
</template>

<style lang="scss">
.stage-workspace { display: flex; flex-direction: column; gap: .75rem; }.stage-workspace__heading { display: flex; flex-direction: column; gap: .15rem; }.stage-workspace__heading h1 { line-height: 1.35; }.stage-panel { overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: .9rem; }.stage-toolbar { border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }.stage-filter-trigger, .stage-add-button { min-block-size: 2.75rem; }.stage-filter-trigger { border: 1px solid rgba(var(--v-theme-primary), .25) !important; background: rgba(var(--v-theme-primary), .13) !important; color: rgb(var(--v-theme-primary)) !important; }.stage-filter-trigger .v-icon { color: rgb(var(--v-theme-primary)) !important; }.stage-add-button, .stage-filter-card__apply, .stage-editor__save { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }.stage-table .v-table__wrapper > table { min-inline-size: 980px; }.stage-table .v-table__wrapper > table > thead > tr > th { background: rgba(var(--v-theme-on-surface), .045); font-size: .82rem; font-weight: 700; letter-spacing: .025em; white-space: nowrap; }.stage-table .v-table__wrapper > table > tbody > tr > td { white-space: nowrap; }.stage-table .v-table__wrapper:has(.stage-empty-state) { overflow-x: hidden; }.stage-table .v-table__wrapper > table:has(.stage-empty-state) { min-inline-size: 100%; }.stage-table .v-table__wrapper > table:has(.stage-empty-state) > tbody > tr > td { padding: 0 !important; }.stage-empty-state { display: grid; min-block-size: 17rem; place-items: center; }.stage-table__delete { color: rgb(var(--v-theme-error)) !important; }
.stage-filter-card, .stage-editor, .stage-delete-dialog { overflow: hidden; background: rgb(var(--v-theme-surface)); }.stage-filter-card__header, .stage-editor__header, .stage-editor__footer, .stage-filter-card__footer { display: flex; align-items: center; border-color: rgba(var(--v-border-color), .52); border-style: solid; border-width: 0; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }.stage-filter-card__header, .stage-editor__header { border-block-end-width: 1px; color: rgb(var(--v-theme-primary)); font-size: 1.2rem; font-weight: 700; }.stage-filter-card__footer, .stage-editor__footer { gap: .75rem; border-block-start-width: 1px; }.stage-filter-card__reset, .stage-editor__cancel { border: 1px solid rgba(var(--v-theme-secondary), .5) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }.stage-filter-card__reset:hover { color: rgb(var(--v-theme-on-primary)) !important; }.stage-editor__body { background: rgba(var(--v-theme-on-surface), .018); }.stage-editor__close { border: 1px solid rgba(var(--v-theme-error), .4) !important; border-radius: 999px !important; }.stage-delete-dialog__content { display: flex; flex-direction: column; align-items: center; padding: 2rem 2rem 1.25rem !important; text-align: center; }.stage-delete-dialog__content h2 { margin: 0; font-size: 1.2rem; }.stage-delete-dialog__content p { max-inline-size: 22rem; margin: .65rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); }.stage-delete-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin-block-end: 1.25rem; border-radius: 50%; background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); place-items: center; }.stage-delete-dialog__actions { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: 0 2rem 2rem !important; }.stage-delete-dialog__actions .v-btn { min-block-size: 2.85rem; }.stage-delete-dialog__confirm { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }.v-overlay:has(.stage-editor-dialog) .v-overlay__scrim, .v-overlay:has(.stage-delete-overlay) .v-overlay__scrim { background: rgba(20, 25, 35, .3) !important; backdrop-filter: blur(6px); opacity: 1 !important; } @media (max-width: 599px) { .stage-toolbar .v-row { gap: .75rem; }.stage-toolbar .v-col { padding: 0 !important; }.stage-toolbar .v-btn { flex: 1 1 auto; }.stage-delete-dialog__content { padding-inline: 1.25rem !important; }.stage-delete-dialog__actions { padding-inline: 1.25rem !important; } }
.stage-filter-reset { border: 1px solid rgba(var(--v-theme-primary), .35) !important; background: rgba(var(--v-theme-primary), .13) !important; color: rgb(var(--v-theme-primary)) !important; }.stage-filter-reset:hover { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
</style>
