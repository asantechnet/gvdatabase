<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import {
  type WhatsappAudience,
  type WhatsappTemplate,
  type WhatsappTemplatePayload,
  createWhatsappTemplate,
  deleteWhatsappTemplate,
  updateWhatsappTemplate,
} from '@/api/whatsappTemplates'
import TableEmptyState from '@/components/TableEmptyState.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import WhatsappMessageEditor from '@/components/whatsapp/WhatsappMessageEditor.vue'
import { confirmActionWarning } from '@/composables/useActionWarning'
import { invalidateWhatsappTemplates, useWhatsappTemplates } from '@/composables/useWhatsappTemplates'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { canAccess } from '@/utils/authorization'
import { translateOrFallback } from '@/utils/localization'
import {
  WHATSAPP_AUDIENCES,
  WHATSAPP_AUDIENCE_TABS,
  parseWhatsappLabels,
  renderWhatsappMessage,
  whatsappPreviewHtml,
  whatsappSampleRecord,
  whatsappTemplateTokens,
} from '@/utils/whatsappTemplates'

type TemplateStatus = 'all' | 'active' | 'inactive'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const audiences = computed(() => WHATSAPP_AUDIENCES.filter(audience => canAccess(WHATSAPP_AUDIENCE_TABS[audience].permission)))
const tab = ref<WhatsappAudience>('visitor')
const activeTab = computed(() => audiences.value.includes(tab.value) ? tab.value : audiences.value[0] ?? 'visitor')
const canManage = (audience: WhatsappAudience) => canAccess(WHATSAPP_AUDIENCE_TABS[audience].managePermission)
const tabLabel = (audience: WhatsappAudience) => t(WHATSAPP_AUDIENCE_TABS[audience].labelKey)
const { templates, fieldsFor, loading, error, load } = useWhatsappTemplates()
const search = ref('')
const status = ref<TemplateStatus>('all')
const statusDraft = ref<TemplateStatus>('all')
const filterOpen = ref(false)
const editorDialog = ref(false)
const deleting = ref<WhatsappTemplate | null>(null)
const editing = ref<WhatsappTemplate | null>(null)
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string>>({})
const initialForm = ref('')
const form = ref({ audience: 'visitor' as WhatsappAudience, title: '', labels: [] as string[], message: '', active: true, sort_order: 0 })

const statusOptions = computed(() => [
  { title: t('pages.whatsapp.filters.all'), value: 'all' },
  { title: t('common.statuses.active'), value: 'active' },
  { title: t('common.statuses.inactive'), value: 'inactive' },
])
const filtered = computed(() => {
  const query = search.value.trim().toLocaleLowerCase()
  return templates.value.filter(template => {
    const matchesQuery = !query || [template.title, template.message, ...(template.labels ?? [])]
      .some(value => value.toLocaleLowerCase().includes(query))
    const matchesStatus = status.value === 'all' || template.active === (status.value === 'active')
    return template.audience === activeTab.value && matchesQuery && matchesStatus
  })
})
const formFields = computed(() => fieldsFor(form.value.audience).map(field => ({
  ...field,
  label: translateOrFallback(field.label_key, field.label),
})))
const formLabels = computed(() => parseWhatsappLabels(form.value.labels))
const unknownTokens = computed(() => whatsappTemplateTokens(form.value.message)
  .filter(token => !formFields.value.some(field => field.token === token)))
const isDirty = computed(() => JSON.stringify(form.value) !== initialForm.value)

const sampleFor = (audience: WhatsappAudience) => whatsappSampleRecord(fieldsFor(audience))
const cardPreview = (template: WhatsappTemplate) => whatsappPreviewHtml(renderWhatsappMessage(
  template.message,
  fieldsFor(template.audience),
  sampleFor(template.audience),
))
const snapshot = () => JSON.stringify(form.value)
const blankForm = (audience = activeTab.value) => ({ audience, title: '', labels: [] as string[], message: '', active: true, sort_order: 0 })
const audienceFromQuery = (): WhatsappAudience => {
  const value = String(route.query.tab ?? '')
  return WHATSAPP_AUDIENCES.includes(value as WhatsappAudience) && audiences.value.includes(value as WhatsappAudience)
    ? value as WhatsappAudience
    : audiences.value[0] ?? 'visitor'
}

function applyFilters() {
  status.value = statusDraft.value
  filterOpen.value = false
}
function resetFilters() {
  status.value = 'all'
  statusDraft.value = 'all'
  filterOpen.value = false
}
function openEditor(template?: WhatsappTemplate) {
  editing.value = template ?? null
  formError.value = ''
  fieldErrors.value = {}
  form.value = template
    ? { audience: template.audience, title: template.title, labels: [...template.labels], message: template.message, active: template.active, sort_order: template.sort_order ?? 0 }
    : blankForm()
  initialForm.value = snapshot()
  editorDialog.value = true
}
async function closeEditor() {
  if (isDirty.value && !await confirmActionWarning({
    key: 'discard-form-changes',
    title: t('common.actionWarnings.discardTitle'),
    message: t('common.actionWarnings.discardMessage'),
    confirmText: t('common.actionWarnings.discardConfirm'),
    cancelText: t('common.actions.cancel'),
    icon: 'tabler-file-alert',
    tone: 'primary',
  })) return
  editorDialog.value = false
}
async function save() {
  fieldErrors.value = {}
  formError.value = ''
  if (!form.value.title.trim()) {
    fieldErrors.value = { title: t('pages.whatsapp.validation.titleRequired') }
    return
  }
  if (!form.value.message.trim()) {
    fieldErrors.value = { message: t('pages.whatsapp.validation.messageRequired') }
    return
  }
  if (unknownTokens.value.length) {
    formError.value = t('pages.whatsapp.validation.unknownPlaceholder')
    return
  }
  saving.value = true
  try {
    const payload: WhatsappTemplatePayload = {
      audience: form.value.audience,
      title: form.value.title.trim(),
      labels: parseWhatsappLabels(form.value.labels),
      message: form.value.message.trim(),
      active: Boolean(form.value.active),
      sort_order: Number(form.value.sort_order) || 0,
    }
    const isNew = !editing.value
    if (editing.value) await updateWhatsappTemplate(editing.value.id, payload)
    else await createWhatsappTemplate(payload)
    editorDialog.value = false
    invalidateWhatsappTemplates()
    await load()
    toast.success(t(isNew ? 'pages.whatsapp.created' : 'pages.whatsapp.updated'))
  }
  catch (caught) {
    fieldErrors.value = apiFieldErrors(caught)
    formError.value = apiErrorMessage(caught, t('common.messages.unableToSaveChanges'))
  }
  finally { saving.value = false }
}
async function remove() {
  if (!deleting.value) return
  saving.value = true
  try {
    await deleteWhatsappTemplate(deleting.value.id)
    deleting.value = null
    invalidateWhatsappTemplates()
    await load()
    toast.success(t('pages.whatsapp.deleted'))
  }
  catch (caught) { toast.error(apiErrorMessage(caught, t('pages.whatsapp.deleteFailed'))) }
  finally { saving.value = false }
}

watch(tab, value => {
  if (route.query.tab !== value) void router.replace({ query: { ...route.query, tab: value } })
  search.value = ''
  resetFilters()
})
watch(() => route.query.tab, () => { tab.value = audienceFromQuery() })
watch(filterOpen, open => { if (open) statusDraft.value = status.value })
onMounted(async () => {
  tab.value = audienceFromQuery()
  if (route.query.tab !== tab.value) await router.replace({ query: { ...route.query, tab: tab.value } })
  await load()
})
</script>

<template>
  <div class="whatsapp-workspace">
    <div class="whatsapp-workspace__heading">
      <VBreadcrumbs :items="[{ title: t('erp.home'), to: { name: 'home' } }, { title: t('common.navigation.options'), disabled: true }]" density="compact" class="pa-0" />
      <h1 class="text-h5 font-weight-bold mb-0">{{ t('common.navigation.whatsappMessages') }}</h1>
    </div>

    <VCard class="whatsapp-panel">
      <VCardText class="whatsapp-toolbar pa-4">
        <VRow align="center" class="ma-0">
          <VCol cols="12" md="6" lg="5" class="pa-0">
            <ErpSearchField v-model="search" :placeholder="t('pages.whatsapp.searchTemplates')" />
          </VCol>
          <VCol cols="12" md="6" lg="7" class="d-flex flex-wrap justify-md-end ga-2 pa-0 pt-3 pt-md-0">
            <DataTableFilterMenu v-model="filterOpen" :active="status !== 'all'" :show-reset="false">
              <ErpField v-model="statusDraft" kind="select" :items="statusOptions" :label="t('common.fields.status')" :placeholder="t('common.fields.status')" />
              <template #actions>
                <VBtn class="whatsapp-filter-reset" variant="flat" @click="resetFilters">{{ t('common.messages.reset') }}</VBtn>
                <VBtn class="whatsapp-filter-apply" variant="flat" @click="applyFilters">{{ t('educational.apply') }}</VBtn>
              </template>
            </DataTableFilterMenu>
            <VBtn v-if="canManage(activeTab)" class="whatsapp-add-button" prepend-icon="tabler-plus" @click="openEditor()">{{ t('pages.whatsapp.addTemplate') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VTabs v-model="tab" class="whatsapp-tabs px-4" color="primary" density="comfortable">
        <VTab v-for="audience in audiences" :key="audience" :value="audience" :prepend-icon="WHATSAPP_AUDIENCE_TABS[audience].icon">{{ tabLabel(audience) }}</VTab>
      </VTabs>
      <VDivider />

      <div v-if="loading" class="whatsapp-card-grid whatsapp-card-grid--loading pa-4" aria-busy="true">
        <VCard v-for="index in 6" :key="index" class="whatsapp-template-card whatsapp-template-card--skeleton" elevation="0">
          <VCardText><VSkeletonLoader type="heading, text, text, actions" /></VCardText>
        </VCard>
      </div>
      <div v-else-if="error" class="whatsapp-empty-wrap">
        <TableEmptyState icon="tabler-alert-circle" :min-height="0" :title="t('pages.whatsapp.loadFailed')" :description="t('pages.whatsapp.loadFailed')" />
        <VBtn color="primary" @click="load">{{ t('pages.whatsapp.retry') }}</VBtn>
      </div>
      <div v-else-if="!filtered.length" class="whatsapp-empty-wrap">
        <TableEmptyState icon="tabler-brand-whatsapp" :min-height="0" :title="t('$vuetify.noDataText')" :description="search || status !== 'all' ? t('$vuetify.dataIterator.noResultsText') : t('pages.whatsapp.empty')" />
      </div>
      <div v-else class="whatsapp-card-grid pa-4">
        <VCard v-for="template in filtered" :key="template.id" class="whatsapp-template-card" elevation="0">
          <VCardText class="whatsapp-template-card__body">
            <div class="whatsapp-template-card__header">
              <div class="min-w-0">
                <h2>{{ template.title }}</h2>
                <div v-if="template.labels.length" class="whatsapp-template-card__labels">
                  <VChip v-for="label in template.labels" :key="label" size="x-small" variant="tonal">{{ label }}</VChip>
                </div>
              </div>
              <div class="d-flex align-center ga-1 flex-none">
                <VChip :color="template.active ? 'success' : 'secondary'" size="small" variant="tonal">{{ template.active ? t('common.statuses.active') : t('common.statuses.inactive') }}</VChip>
                <VMenu v-if="canManage(template.audience)" location="bottom end">
                  <template #activator="{ props }"><VBtn v-bind="props" icon="tabler-dots-vertical" size="small" variant="tonal" :aria-label="t('common.messages.actions')" /></template>
                  <VList density="compact"><VListItem prepend-icon="tabler-pencil" :title="t('common.actions.edit')" @click="openEditor(template)" /><VListItem class="whatsapp-delete-action" prepend-icon="tabler-trash" :title="t('common.actions.delete')" @click="deleting = template" /></VList>
                </VMenu>
              </div>
            </div>
            <div class="whatsapp-template-card__preview" dir="auto" v-html="cardPreview(template)" />
            <div class="whatsapp-template-card__footer"><VIcon icon="tabler-sort-ascending" size="16" /><span>{{ t('pages.whatsapp.orderValue', { order: template.sort_order }) }}</span></div>
          </VCardText>
        </VCard>
      </div>
    </VCard>
  </div>

  <VDialog :model-value="editorDialog" max-width="900" scrollable content-class="whatsapp-editor-overlay" @update:model-value="value => { if (!value) void closeEditor() }">
    <VCard class="whatsapp-editor">
      <VCardTitle class="whatsapp-editor__header px-5 py-4"><span>{{ t(editing ? 'pages.whatsapp.editTemplate' : 'pages.whatsapp.addTemplate') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="whatsapp-editor__close" :disabled="saving" :aria-label="t('common.actions.close')" @click="closeEditor" /></VCardTitle>
      <VCardText class="whatsapp-editor__body pa-5">
        <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">{{ formError }}</VAlert>
        <VRow>
          <VCol cols="12" md="4"><ErpField v-model="form.title" required :label="t('pages.whatsapp.titleLabel')" :placeholder="t('pages.whatsapp.titleLabel')" :error-messages="fieldErrors.title" /></VCol>
          <VCol cols="12" md="4"><ErpField v-model="form.labels" kind="combobox" multiple chips closable-chips :delimiters="[',']" :label="t('pages.whatsapp.labelsLabel')" :placeholder="t('pages.whatsapp.labelsLabel')" /></VCol>
          <VCol cols="12" md="4"><ErpField v-model.number="form.sort_order" type="number" min="0" :label="t('pages.whatsapp.sortOrder')" :placeholder="t('pages.whatsapp.sortOrder')" /></VCol>
          <VCol cols="12" md="8" class="d-flex align-center"><VSwitch v-model="form.active" color="success" inset :label="t('common.statuses.active')" hide-details /></VCol>
          <VCol cols="12"><WhatsappMessageEditor v-model="form.message" :fields="formFields" :label="t('pages.whatsapp.editor.message')" :error-messages="fieldErrors.message" /></VCol>
        </VRow>
      </VCardText>
      <VCardActions class="whatsapp-editor__footer justify-end px-5 py-4"><VBtn class="whatsapp-editor__cancel" :disabled="saving" @click="closeEditor">{{ t('common.actions.cancel') }}</VBtn><VBtn class="whatsapp-editor__save" :loading="saving" @click="save">{{ t('common.actions.save') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>

  <VDialog :model-value="Boolean(deleting)" max-width="480" content-class="whatsapp-delete-overlay" @update:model-value="value => { if (!value) deleting = null }">
    <VCard class="whatsapp-delete-dialog"><VCardText class="whatsapp-delete-dialog__content"><div class="whatsapp-delete-dialog__icon"><VIcon icon="tabler-trash" size="30" /></div><h2>{{ t('common.actions.delete') }}</h2><p>{{ t('pages.whatsapp.confirmDelete', { title: deleting?.title ?? '' }) }}</p></VCardText><VCardActions class="whatsapp-delete-dialog__actions"><VBtn class="whatsapp-editor__cancel" :disabled="saving" @click="deleting = null">{{ t('common.actions.cancel') }}</VBtn><VBtn class="whatsapp-delete-dialog__confirm" :loading="saving" @click="remove">{{ t('common.actions.delete') }}</VBtn></VCardActions></VCard>
  </VDialog>
</template>

<style lang="scss">
.whatsapp-workspace { display: flex; flex-direction: column; gap: .75rem; }.whatsapp-workspace__heading { display: flex; flex-direction: column; gap: .15rem; }.whatsapp-workspace__heading h1 { line-height: 1.35; }.whatsapp-panel { overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: .9rem; }.whatsapp-toolbar { border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }.whatsapp-toolbar .data-table-filter-menu__trigger, .whatsapp-add-button { min-block-size: 2.75rem; }.whatsapp-toolbar .data-table-filter-menu__trigger { min-inline-size: 2.75rem; }.whatsapp-add-button, .whatsapp-filter-apply, .whatsapp-editor__save { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }.whatsapp-filter-reset { border: 1px solid rgba(var(--v-theme-primary), .35) !important; background: rgba(var(--v-theme-primary), .13) !important; color: rgb(var(--v-theme-primary)) !important; }.whatsapp-filter-reset:hover { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }.whatsapp-tabs { border-block-end: 0; }.whatsapp-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr)); gap: 1rem; }.whatsapp-template-card { border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: .85rem !important; background: rgb(var(--v-theme-surface)); }.whatsapp-template-card__body { display: flex; flex-direction: column; min-block-size: 15rem; padding: 1.1rem !important; }.whatsapp-template-card__header { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }.whatsapp-template-card h2 { overflow: hidden; margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1rem; font-weight: 700; line-height: 1.4; text-overflow: ellipsis; white-space: nowrap; }.whatsapp-template-card__labels { display: flex; flex-wrap: wrap; gap: .35rem; margin-block-start: .6rem; }.whatsapp-template-card__preview { display: -webkit-box; overflow: hidden; margin-block: 1.15rem auto; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .9rem; line-height: 1.65; -webkit-box-orient: vertical; -webkit-line-clamp: 5; }.whatsapp-template-card__preview :is(p, div) { margin: 0; }.whatsapp-template-card__footer { display: flex; align-items: center; gap: .35rem; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .78rem; }.whatsapp-delete-action { color: rgb(var(--v-theme-error)) !important; }.whatsapp-empty-wrap { display: grid; min-block-size: 21rem; place-items: center; padding: 1rem; }.whatsapp-card-grid--loading .v-skeleton-loader__bone { animation: whatsapp-wave 1.45s ease-in-out infinite; background: linear-gradient(100deg, rgba(var(--v-theme-on-surface), .055) 25%, rgba(var(--v-theme-on-surface), .11) 45%, rgba(var(--v-theme-on-surface), .055) 65%); background-size: 240% 100%; } @keyframes whatsapp-wave { to { background-position: -160% 0; } }
.whatsapp-editor, .whatsapp-delete-dialog { overflow: hidden; background: rgb(var(--v-theme-surface)); }.whatsapp-editor__header, .whatsapp-editor__footer { display: flex; align-items: center; border-color: rgba(var(--v-border-color), .52); border-style: solid; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }.whatsapp-editor__header { border-width: 0 0 1px; color: rgb(var(--v-theme-primary)); font-size: 1.2rem; font-weight: 700; }.whatsapp-editor__footer { gap: .75rem; border-width: 1px 0 0; }.whatsapp-editor__body { background: rgba(var(--v-theme-on-surface), .018); }.whatsapp-editor__close { border: 1px solid rgba(var(--v-theme-error), .4) !important; border-radius: 999px !important; }.whatsapp-editor__cancel { border: 1px solid rgba(var(--v-theme-secondary), .5) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }.whatsapp-delete-dialog__content { display: flex; flex-direction: column; align-items: center; padding: 2rem 2rem 1.25rem !important; text-align: center; }.whatsapp-delete-dialog__content h2 { margin: 0; font-size: 1.2rem; }.whatsapp-delete-dialog__content p { max-inline-size: 23rem; margin: .65rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); }.whatsapp-delete-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin-block-end: 1.25rem; border-radius: 50%; background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); place-items: center; }.whatsapp-delete-dialog__actions { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: 0 2rem 2rem !important; }.whatsapp-delete-dialog__actions .v-btn { min-block-size: 2.85rem; }.whatsapp-delete-dialog__confirm { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }.v-overlay:has(.whatsapp-editor-overlay) .v-overlay__scrim, .v-overlay:has(.whatsapp-delete-overlay) .v-overlay__scrim { background: rgba(20, 25, 35, .3) !important; backdrop-filter: blur(6px); opacity: 1 !important; }
@media (max-width: 599px) { .whatsapp-toolbar .v-row { gap: .75rem; }.whatsapp-toolbar .v-col { padding: 0 !important; }.whatsapp-toolbar .v-btn { flex: 1 1 auto; }.whatsapp-tabs { padding-inline: .25rem !important; }.whatsapp-template-card__body { min-block-size: 13rem; }.whatsapp-delete-dialog__content { padding-inline: 1.25rem !important; }.whatsapp-delete-dialog__actions { padding-inline: 1.25rem !important; } }

/* Card messages use the same soft WhatsApp-green surface as the editor preview. */
.whatsapp-template-card__preview { padding: .8rem .9rem; border: 1px solid rgba(var(--v-theme-success), .24); border-radius: .7rem; background: rgba(var(--v-theme-success), .11); }
.whatsapp-template-card--skeleton { min-block-size: 15rem; }
.whatsapp-editor__header { color: rgb(var(--v-theme-on-surface)); }

/* A card is a readable message preview, not a shortened excerpt. */
.whatsapp-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.whatsapp-template-card__preview { display: block; overflow: visible; border-radius: 6px; -webkit-line-clamp: unset; }

@media (max-width: 599px) {
  .whatsapp-toolbar .data-table-filter-menu__trigger { flex: 0 0 2.75rem !important; block-size: 2.75rem; inline-size: 2.75rem; padding: 0 !important; }
  .whatsapp-card-grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
