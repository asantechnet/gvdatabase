<script setup lang="ts">
import { useToast } from 'vue-toastification'
import {
  type Announcement,
  type AnnouncementAudienceOptions,
  type AnnouncementScope,
  type AnnouncementScopeType,
  createAnnouncement,
  deleteAnnouncement,
  getAnnouncementAudienceOptions,
  getAnnouncements,
  updateAnnouncement,
} from '@/api/announcements'
import { apiErrorMessage } from '@/utils/apiErrors'
import { canAccess } from '@/utils/authorization'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import ErpField from '@/components/erp/ErpField.vue'

const toast = useToast()
const { t } = useI18n()

const announcements = ref<Announcement[]>([])
const loading = ref(true)
const sending = ref(false)
const deleting = ref(false)
const showComposer = ref(false)
const editingAnnouncement = ref<Announcement | null>(null)
const deletingAnnouncement = ref<Announcement | null>(null)
const deleteDialogOpen = ref(false)
const search = ref('')

const audienceOptions = ref<AnnouncementAudienceOptions | null>(null)
const audienceLoading = ref(false)

const form = ref<{ title: string; message: string; scopes: Array<{ type: AnnouncementScopeType; id?: number }> }>({
  title: '',
  message: '',
  scopes: [],
})

const formError = ref('')

// The VSelect is bound to this single scalar ref, never to form.scopes.
// Binding the select directly to the array made Vuetify replace the whole
// scopes list with the selected item object on every pick.
const selectedScopeType = ref<AnnouncementScopeType | null>(null)

// Types that need a concrete target picked before the scope is sendable.
const TARGETED_SCOPE_TYPES: AnnouncementScopeType[] = ['position', 'role', 'department']

const scopeTypeOptions = computed(() => ([
  { value: 'all_employees' as const, title: t('pages.announcements.composer.scopeAllEmployees'), disabled: !canAccess('announce_to_all_employees') },
  { value: 'position' as const, title: t('pages.announcements.composer.scopePosition') },
  { value: 'role' as const, title: t('pages.announcements.composer.scopeRole') },
  { value: 'department' as const, title: t('pages.announcements.composer.scopeDepartment') },
  { value: 'applicants' as const, title: t('pages.announcements.composer.scopeApplicants'), disabled: !canAccess('announce_to_applicants') },
]))

// Hide types the user cannot use, and types already added, so a scope can never
// be duplicated by picking it twice.
const scopeItems = computed(() => scopeTypeOptions.value.filter(option =>
  !option.disabled && !form.value.scopes.some(scope => scope.type === option.value),
))
const scopeTypeLabel = (type: AnnouncementScopeType) => scopeTypeOptions.value.find(option => option.value === type)?.title ?? type

/** The selectable targets for a targeted scope type. */
const targetOptionsFor = (type: AnnouncementScopeType) => {
  const options = audienceOptions.value
  const list = type === 'position'
    ? options?.positions
    : type === 'role'
      ? options?.roles
      : options?.departments

  return (list ?? []).map(item => ({
    title: 'title' in item ? item.title : item.name,
    value: item.id,
  }))
}

const addScope = (type: AnnouncementScopeType | null) => {
  if (!type || form.value.scopes.some(scope => scope.type === type))
    return

  form.value.scopes.push({ type })
  formError.value = ''
  selectedScopeType.value = null
}

const removeScope = (index: number) => {
  form.value.scopes.splice(index, 1)
}

const targetLabel = (scope: AnnouncementScope, unresolvedLabel = t('pages.announcements.composer.chooseTargetLabel')): string => {
  if (scope.type === 'all_employees')
    return t('pages.announcements.composer.scopeAllEmployees')
  if (scope.type === 'applicants')
    return t('pages.announcements.composer.scopeApplicants')

  const options = audienceOptions.value
  const list = scope.type === 'position'
    ? options?.positions
    : scope.type === 'role'
      ? options?.roles
      : options?.departments
  const match = list?.find(item => item.id === scope.id)

  if (!match) {
    return unresolvedLabel
  }

  return scope.type === 'position' ? (match as { title: string }).title : (match as { name: string }).name
}

const selectedScopeTargets = computed(() => form.value.scopes.map((scope, index) => ({
  index,
  scope,
  label: targetLabel(scope),
  // Targeted scopes always render a picker so the target can be changed;
  // `incomplete` only drives the warning colour.
  needsTarget: TARGETED_SCOPE_TYPES.includes(scope.type),
  incomplete: TARGETED_SCOPE_TYPES.includes(scope.type) && !scope.id,
  count: scope.type === 'all_employees'
    ? audienceOptions.value?.counts.all_employees
    : scope.type === 'applicants'
      ? audienceOptions.value?.counts.applicants
      : undefined,
})))

const audienceDescription = (announcement: Announcement): string => announcement.audience
  .map(scope => targetLabel(scope, scopeTypeLabel(scope.type)))
  .join(' · ')

const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))

const headers = computed(() => [
  { title: t('common.fields.title'), key: 'title', minWidth: '180px' },
  { title: t('common.fields.description'), key: 'message', minWidth: '280px' },
  { title: t('pages.announcements.composer.audienceLabel'), key: 'audience', minWidth: '180px' },
  { title: t('common.messages.recordedBy'), key: 'sender', minWidth: '140px' },
  { title: t('common.fields.date'), key: 'created_at', minWidth: '160px' },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const filteredAnnouncements = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query)
    return announcements.value

  return announcements.value.filter(announcement => [
    announcement.title,
    announcement.message,
    announcement.sender?.name,
    audienceDescription(announcement),
  ].filter(Boolean).join(' ').toLowerCase().includes(query))
})

const loadAnnouncements = async () => {
  loading.value = true
  try {
    announcements.value = await getAnnouncements()
  }
  catch (error: any) {
  }
  finally {
    loading.value = false
  }
}

const loadAudienceOptions = async () => {
  audienceLoading.value = true
  try {
    audienceOptions.value = await getAnnouncementAudienceOptions()
  }
  catch (error: any) {
  }
  finally {
    audienceLoading.value = false
  }
}

const openComposer = async (announcement: Announcement | null = null) => {
  editingAnnouncement.value = announcement
  form.value = announcement
    ? { title: announcement.title ?? '', message: announcement.message, scopes: announcement.audience.map(scope => ({ ...scope })) }
    : { title: '', message: '', scopes: [] }
  formError.value = ''
  selectedScopeType.value = null
  showComposer.value = true
  if (!audienceOptions.value)
    await loadAudienceOptions()
}

const resetForm = () => {
  form.value = { title: '', message: '', scopes: [] }
  formError.value = ''
  selectedScopeType.value = null
  editingAnnouncement.value = null
}

const validate = (): string | null => {
  if (!form.value.scopes.length)
    return t('pages.announcements.validation.audienceRequired')
  if (form.value.scopes.some(scope => !['all_employees', 'applicants'].includes(scope.type) && !scope.id))
    return t('pages.announcements.validation.audienceTargetRequired')
  if (!form.value.message.trim())
    return t('pages.announcements.validation.messageRequired')

  return null
}

const send = async () => {
  const validationError = validate()
  if (validationError) {
    formError.value = validationError

    return
  }

  const audience: AnnouncementScope[] = form.value.scopes.map((scope) => {
    if (scope.type === 'all_employees' || scope.type === 'applicants')
      return { type: scope.type }

    return { type: scope.type, id: Number(scope.id) }
  })

  sending.value = true
  formError.value = ''
  try {
    const payload = {
      title: form.value.title.trim() || null,
      message: form.value.message.trim(),
      audience,
    }
    const saved = editingAnnouncement.value
      ? await updateAnnouncement(editingAnnouncement.value.id, payload)
      : await createAnnouncement(payload)

    const index = announcements.value.findIndex(announcement => announcement.id === saved.id)
    if (index === -1)
      announcements.value.unshift(saved)
    else announcements.value[index] = saved
    toast.success(t('pages.announcements.sentToast'))
    resetForm()
    showComposer.value = false
  }
  catch (error: any) {
    formError.value = apiErrorMessage(error, translate('common.messages.unableToSaveChanges'))
  }
  finally {
    sending.value = false
  }
}

const askDelete = (announcement: Announcement) => {
  deletingAnnouncement.value = announcement
  deleteDialogOpen.value = true
}

const confirmDelete = async () => {
  if (!deletingAnnouncement.value || deleting.value)
    return

  deleting.value = true
  try {
    await deleteAnnouncement(deletingAnnouncement.value.id)
    announcements.value = announcements.value.filter(announcement => announcement.id !== deletingAnnouncement.value?.id)
    deleteDialogOpen.value = false
    toast.success(t('common.messages.notificationDeleted'))
  }
  catch (error: any) {
    toast.error(apiErrorMessage(error, t('common.messages.failedToDelete')))
  }
  finally {
    deleting.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadAnnouncements(), loadAudienceOptions()])
})
</script>

<template>
  <div class="erp-workspace announcements-page">
    <ErpPageHeader :title="$t('pages.announcements.title')" section="hr" />

    <VDialog
      v-model="showComposer"
      max-width="900"
      scrollable
      :persistent="sending"
      content-class="announcement-composer-dialog"
    >
      <VCard class="composer-card">
      <VCardTitle class="announcement-composer-dialog__header d-flex align-center justify-space-between px-5 py-4">
        <span>{{ editingAnnouncement ? $t('common.actions.edit') : $t('pages.announcements.composer.title') }}</span>
        <VBtn class="announcement-composer-dialog__close" icon="tabler-x" variant="flat" size="small" :aria-label="$t('common.actions.close')" :disabled="sending" @click="resetForm(); showComposer = false" />
      </VCardTitle>
      <VCardText class="pa-5">
        <VForm
          class="app-form"
          @submit.prevent="send"
        >
          <VAlert
            v-if="formError"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ formError }}
          </VAlert>
          <VRow>
            <VCol cols="12" md="6">
              <ErpField
                v-model="form.title"
                :label="$t('pages.announcements.composer.titleLabel')"
                :placeholder="$t('pages.announcements.composer.titlePlaceholder')"
                maxlength="255"
              />
            </VCol>
            <VCol cols="12" md="6">
              <ErpField v-model="selectedScopeType" kind="select" :items="scopeItems" item-title="title" item-value="value" :label="$t('pages.announcements.composer.audienceLabel')" :placeholder="$t('pages.announcements.composer.addAudienceLabel')" :loading="audienceLoading" @update:model-value="addScope" />
            </VCol>
            <VCol v-for="target in selectedScopeTargets" :key="target.scope.type" cols="12" md="6">
              <div class="announcement-scope-field">
                <ErpField v-if="target.needsTarget" v-model="target.scope.id" kind="select" :items="targetOptionsFor(target.scope.type)" item-title="title" item-value="value" :label="scopeTypeLabel(target.scope.type)" :placeholder="$t('pages.announcements.composer.chooseTargetLabel')" :error="target.incomplete" />
                <ErpField v-else :model-value="target.label" :label="scopeTypeLabel(target.scope.type)" disabled />
                <VBtn class="announcement-scope-field__remove" icon="tabler-x" variant="text" size="x-small" :aria-label="$t('common.actions.remove')" @click="removeScope(target.index)" />
              </div>
            </VCol>
            <VCol cols="12">
              <ErpField
                v-model="form.message"
                kind="textarea"
                :label="$t('pages.announcements.composer.messageLabel')"
                :placeholder="$t('pages.announcements.composer.messagePlaceholder')"
                rows="4"
                auto-grow
                required
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      <VCardActions class="announcement-composer-dialog__footer px-5 py-4">
        <VBtn class="announcement-composer-dialog__cancel" :disabled="sending" @click="resetForm(); showComposer = false">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn class="announcement-composer-dialog__send" :loading="sending" @click="send"><VIcon icon="tabler-send" class="me-1" />{{ $t('pages.announcements.composer.send') }}</VBtn>
      </VCardActions>
      </VCard>
    </VDialog>

    <VCard class="announcements-table-card">
      <VCardText>
        <VRow align="center" class="table-toolbar">
          <VCol cols="12" md="7" lg="6"><ErpSearchField v-model="search" :placeholder="$t('common.actions.search')" /></VCol>
          <VCol cols="12" md="5" lg="6" class="announcements-toolbar-actions"><VBtn color="primary" @click="openComposer()"><VIcon icon="tabler-speakerphone" class="me-1" />{{ $t('pages.announcements.compose') }}</VBtn></VCol>
        </VRow>
      </VCardText>
      <ResponsiveDataTable :headers="headers" :items="filteredAnnouncements" :loading="loading" :skeleton-rows="5" :sticky-key-column="false" :sticky-action-column="false" item-value="id" mobile-cards mobile-expand-all mobile-actions-as-menu mobile-title-key="title" mobile-action-key="actions" :mobile-fields="['message', 'audience', 'sender', 'created_at']" class="announcements-responsive-table">
        <template #item.title="{ item }"><div class="announcement-table-title">{{ item.title || $t('pages.announcements.list.untitled') }}</div></template>
        <template #item.message="{ item }"><div class="announcement-table-message">{{ item.message }}</div></template>
        <template #item.audience="{ item }"><VChip size="small" color="primary" variant="tonal" prepend-icon="tabler-target">{{ audienceDescription(item) }}</VChip></template>
        <template #item.sender="{ item }"><VChip size="small" variant="tonal" color="secondary" prepend-icon="tabler-user">{{ item.sender?.name || '—' }}</VChip></template>
        <template #item.created_at="{ item }"><span class="text-no-wrap text-medium-emphasis">{{ formatDate(item.created_at) }}</span></template>
        <template #item.actions="{ item }"><div class="d-flex justify-end"><VMenu location="bottom end"><template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" icon="tabler-dots-vertical" variant="tonal" size="small" :aria-label="$t('common.messages.actions')" /></template><VList density="compact" min-width="190"><VListItem prepend-icon="tabler-pencil" :title="$t('common.actions.edit')" @click="openComposer(item)" /><VDivider class="my-1" /><VListItem prepend-icon="tabler-trash" class="announcement-actions-menu__delete" :title="$t('common.actions.delete')" @click="askDelete(item)" /></VList></VMenu></div></template>
        <template #no-data><TableEmptyState icon="tabler-speakerphone" :title="$t('pages.announcements.compose')" :description="$t('pages.announcements.list.emptyActionDescription')" :min-height="0" /></template>
      </ResponsiveDataTable>
    </VCard>

    <VDialog v-model="deleteDialogOpen" :persistent="deleting" max-width="460" content-class="announcement-delete-dialog"><VCard class="announcement-delete-dialog__card"><VCardText class="announcement-delete-dialog__content"><div class="announcement-delete-dialog__icon"><VIcon icon="tabler-trash" size="30" /></div><h2>{{ $t('common.messages.confirmDelete') }}</h2><p>{{ deletingAnnouncement?.title || $t('pages.announcements.list.untitled') }}</p><p class="text-caption text-medium-emphasis">{{ $t('common.confirmations.thisActionCannotBeUndone') }}</p></VCardText><VCardActions class="announcement-delete-dialog__actions px-5 pb-5"><VBtn class="announcement-composer-dialog__cancel" :disabled="deleting" @click="deleteDialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn class="announcement-delete-dialog__delete" :loading="deleting" @click="confirmDelete">{{ $t('common.actions.delete') }}</VBtn></VCardActions></VCard></VDialog>

  </div>
</template>

<style scoped>
.composer-card { display: grid; grid-template-rows: auto minmax(0, 1fr) auto; max-block-size: min(90dvh, 820px); overflow: hidden; border: 1px solid rgb(var(--v-theme-primary) / 25%); }
.announcement-composer-dialog__header, .announcement-composer-dialog__footer { z-index: 1; display: flex; align-items: center; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }
.announcement-composer-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .5); font-weight: 700; }
.announcement-composer-dialog__footer { border-block-start: 1px solid rgba(var(--v-border-color), .5); justify-content: flex-end; gap: .75rem; }
.announcement-composer-dialog__close { background: rgba(var(--v-theme-error), .13) !important; color: rgb(var(--v-theme-error)) !important; }
.announcement-composer-dialog__close:hover:not(:disabled) { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.announcement-composer-dialog__cancel { border: 1px solid rgba(var(--v-theme-secondary), .52) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.announcement-composer-dialog__cancel:hover:not(:disabled) { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
.announcement-composer-dialog__send { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.announcement-scope-field { position: relative; }
.announcement-scope-field__remove { position: absolute; z-index: 1; inset-block-start: 32px; inset-inline-end: 1.85rem; min-inline-size: 1.35rem !important; inline-size: 1.35rem; min-block-size: 1.35rem !important; block-size: 1.35rem; background: transparent !important; color: rgb(var(--v-theme-error)); }
.announcement-scope-field__remove:hover { background: transparent !important; color: rgb(var(--v-theme-error)); opacity: .72; }
.announcement-actions-menu__delete { color: rgb(var(--v-theme-error)); }
.announcement-delete-dialog__card { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .2) !important; border-radius: 16px !important; background: rgb(var(--v-theme-surface)) !important; box-shadow: 0 24px 48px rgba(0, 0, 0, .18) !important; text-align: center; }
.announcement-delete-dialog__content { display: flex; flex-direction: column; align-items: center; padding: 2rem 2rem 1.25rem; }
.announcement-delete-dialog__content h2 { margin: 0; font-size: 1.2rem; font-weight: 700; }
.announcement-delete-dialog__content p { margin: .65rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); }
.announcement-delete-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin-block-end: 1.25rem; border-radius: 50%; place-items: center; background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); }
.announcement-delete-dialog__actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.announcement-delete-dialog__actions .v-btn { min-block-size: 2.85rem; }
.announcement-delete-dialog__delete { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.announcements-toolbar-actions { display: flex; justify-content: flex-end; }
:deep(.announcements-responsive-table .v-data-table__thead > tr > th) { background: rgba(var(--v-theme-primary), .1) !important; color: rgb(var(--v-theme-on-surface)); font-weight: 700; }
.announcement-table-title { min-inline-size: 10rem; font-weight: 700; white-space: normal; }
.announcement-table-message { max-inline-size: 38rem; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); line-height: 1.55; overflow-wrap: anywhere; white-space: normal; }
@media (max-width: 599px) { .announcement-composer-dialog__header, .announcement-composer-dialog__footer { padding-inline: 1rem !important; } .announcements-toolbar-actions { justify-content: stretch; } .announcements-toolbar-actions .v-btn { inline-size: 100%; } }
</style>
<style>
body:has(.announcement-composer-dialog) .v-overlay__scrim, body:has(.announcement-delete-dialog) .v-overlay__scrim { background: rgba(20, 25, 35, .32) !important; backdrop-filter: blur(6px); opacity: 1 !important; }
</style>
