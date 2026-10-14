<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  type WhatsappAudience,
  type WhatsappTemplate,
  type WhatsappTemplatePayload,
  createWhatsappTemplate,
  deleteWhatsappTemplate,
  updateWhatsappTemplate,
} from '@/api/whatsappTemplates'
import WhatsappMessageEditor from '@/components/whatsapp/WhatsappMessageEditor.vue'
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

/**
 * Options → WhatsApp Messages.
 *
 * One workspace with a subpage per audience. A subpage manages the templates
 * written for that record type, and each template declares its audience, so the
 * same rows are what the visitor, applicant and employee quick-contact menus
 * offer — filtered to the page they are used on.
 */

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { t } = useI18n()

// Access follows the record type: a user only sees the subpages they could
// already open, and only manages the ones they could already edit.
const availableAudiences = computed(() => WHATSAPP_AUDIENCES.filter(audience => canAccess(WHATSAPP_AUDIENCE_TABS[audience].permission)))

const tab = ref<WhatsappAudience>('visitor')
const activeTab = computed(() => (availableAudiences.value.includes(tab.value) ? tab.value : availableAudiences.value[0] ?? 'visitor'))
const canManage = (audience: WhatsappAudience) => canAccess(WHATSAPP_AUDIENCE_TABS[audience].managePermission)

const tabLabel = (audience: WhatsappAudience) => t(WHATSAPP_AUDIENCE_TABS[audience].labelKey)

const { templates, fieldsFor: placeholderFieldsFor, loading, error, load } = useWhatsappTemplates()
const search = ref('')
const statusFilter = ref<'all' | 'active' | 'inactive'>('all')
const expandedTemplateIds = ref<number[]>([])

const sampleRecordFor = (audience: WhatsappAudience) => whatsappSampleRecord(placeholderFieldsFor(audience))

const visibleTemplates = (audience: WhatsappAudience) => templates.value.filter((template) => {
  if (template.audience !== audience)
    return false
  if (statusFilter.value === 'active' && !template.active)
    return false
  if (statusFilter.value === 'inactive' && template.active)
    return false

  const query = search.value.trim().toLowerCase()
  if (!query)
    return true

  return template.title.toLowerCase().includes(query)
    || template.message.toLowerCase().includes(query)
    || template.labels.some(label => label.toLowerCase().includes(query))
})

const hasFilters = computed(() => Boolean(search.value.trim()) || statusFilter.value !== 'all')
const clearFilters = () => {
  search.value = ''
  statusFilter.value = 'all'
}

const isExpanded = (template: WhatsappTemplate) => expandedTemplateIds.value.includes(template.id)
const toggleExpanded = (template: WhatsappTemplate) => {
  expandedTemplateIds.value = isExpanded(template)
    ? expandedTemplateIds.value.filter(id => id !== template.id)
    : [...expandedTemplateIds.value, template.id]
}

const previewHtmlFor = (template: WhatsappTemplate) => whatsappPreviewHtml(
  renderWhatsappMessage(template.message, placeholderFieldsFor(template.audience), sampleRecordFor(template.audience)),
)

/** Tokens the message uses, so the author can see them before opening the editor. */
const tokensFor = (template: WhatsappTemplate) => whatsappTemplateTokens(template.message)

/**
 * A token as it is written in a message. Built here rather than inline in the
 * template: a literal `\`{{${token}}}\`` would close the interpolation at its own
 * braces.
 */
const tokenLabel = (token: string) => `{{${token}}}`

// --- editor ---------------------------------------------------------------

const editorDialog = ref(false)
const editing = ref<WhatsappTemplate | null>(null)
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string>>({})
const form = ref<{ audience: WhatsappAudience; title: string; labels: string; message: string; active: boolean; sort_order: number }>({
  audience: 'visitor',
  title: '',
  labels: '',
  message: '',
  active: true,
  sort_order: 0,
})

const formFields = computed(() => placeholderFieldsFor(form.value.audience).map(field => ({
  ...field,
  label: translateOrFallback(field.label_key, field.label),
})))
const formLabels = computed(() => parseWhatsappLabels(form.value.labels))

/** Tokens in the message this audience cannot fill; the API rejects these too. */
const unknownTokens = computed(() => {
  const known = new Set(formFields.value.map(field => field.token))

  return whatsappTemplateTokens(form.value.message).filter(token => !known.has(token))
})

const openEditor = (template: WhatsappTemplate | null = null) => {
  editing.value = template
  formError.value = ''
  fieldErrors.value = {}
  form.value = template
    ? {
        audience: template.audience,
        title: template.title,
        labels: template.labels.join(', '),
        message: template.message,
        active: template.active,
        sort_order: template.sort_order,
      }
    : {
        audience: activeTab.value,
        title: '',
        labels: '',
        message: '',
        active: true,
        sort_order: (templates.value.filter(row => row.audience === activeTab.value).length + 1),
      }
  editorDialog.value = true
}

const validate = () => {
  fieldErrors.value = {}
  formError.value = ''

  if (!form.value.title.trim()) {
    fieldErrors.value.title = t('pages.whatsapp.validation.titleRequired')

    return false
  }
  if (!form.value.message.trim()) {
    fieldErrors.value.message = t('pages.whatsapp.validation.messageRequired')

    return false
  }
  if (unknownTokens.value.length) {
    fieldErrors.value.message = t('pages.whatsapp.validation.unknownPlaceholder')

    return false
  }

  return true
}

const save = async () => {
  if (!validate())
    return

  const payload: WhatsappTemplatePayload = {
    audience: form.value.audience,
    title: form.value.title.trim(),
    labels: formLabels.value,
    message: form.value.message.trim(),
    active: form.value.active,
    sort_order: Number(form.value.sort_order) || 0,
  }

  saving.value = true
  try {
    if (editing.value)
      await updateWhatsappTemplate(editing.value.id, payload)
    else
      await createWhatsappTemplate(payload)

    toast.success(t(editing.value ? 'pages.whatsapp.updated' : 'pages.whatsapp.created'))
    editorDialog.value = false
    // The quick-contact menus read the same cache, so drop it before reloading.
    invalidateWhatsappTemplates()
    await load()
  }
  catch (error) {
    const errors = apiFieldErrors(error)
    fieldErrors.value = {
      ...(errors.title ? { title: errors.title } : {}),
      ...(errors.message ? { message: errors.message } : {}),
    }
    formError.value = apiErrorMessage(error, t('common.messages.unableToSaveChanges'))
  }
  finally {
    saving.value = false
  }
}

// --- delete ---------------------------------------------------------------

const deleteDialog = ref(false)
const deleting = ref<WhatsappTemplate | null>(null)

const askDelete = (template: WhatsappTemplate) => {
  deleting.value = template
  deleteDialog.value = true
}

const confirmDelete = async () => {
  if (!deleting.value)
    return

  saving.value = true
  try {
    await deleteWhatsappTemplate(deleting.value.id)
    toast.success(t('pages.whatsapp.deleted'))
    deleteDialog.value = false
    deleting.value = null
    invalidateWhatsappTemplates()
    await load()
  }
  catch (error) {
    toast.error(apiErrorMessage(error, t('pages.whatsapp.deleteFailed')))
  }
  finally {
    saving.value = false
  }
}

// The active subpage stays in `?tab=` so each audience is deep-linkable.
const audienceFromQuery = (): WhatsappAudience => {
  const requested = String(route.query.tab ?? '') as WhatsappAudience

  return availableAudiences.value.includes(requested) ? requested : (availableAudiences.value[0] ?? 'visitor')
}

watch(tab, value => {
  if (route.query.tab !== value)
    router.replace({ query: { ...route.query, tab: value } })
})

watch(() => route.query.tab, () => {
  const next = audienceFromQuery()
  if (next !== tab.value)
    tab.value = next
})

onMounted(async () => {
  tab.value = audienceFromQuery()
  if (route.query.tab !== tab.value)
    router.replace({ query: { ...route.query, tab: tab.value } })
  await load()
})
</script>

<template>
  <div class="whatsapp-page">
    <VCard
      class="whatsapp-page__workspace"
      :loading="loading"
    >
      <VCardText class="whatsapp-page__header">
        <div>
          <div class="text-h6">
            {{ $t('common.navigation.whatsappMessages') }}
          </div>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ $t('pages.whatsapp.subtitle') }}
          </p>
        </div>
        <div class="whatsapp-page__header-actions">
          <VBtn
            v-if="canManage(activeTab)"
            color="primary"
            size="small"
            :prepend-icon="'tabler-plus'"
            @click="openEditor()"
          >
            {{ $t('pages.whatsapp.addTemplate') }}
          </VBtn>
        </div>
      </VCardText>

      <VCardText class="pb-0">
        <VTabs
          v-model="tab"
          show-arrows
        >
          <VTab
            v-for="audience in availableAudiences"
            :key="audience"
            :value="audience"
          >
            {{ tabLabel(audience) }}
          </VTab>
        </VTabs>
      </VCardText>

      <VWindow
        v-model="tab"
        class="whatsapp-page__window"
      >
        <VWindowItem
          v-for="audience in availableAudiences"
          :key="audience"
          :value="audience"
        >
          <VCardText class="whatsapp-page__content">
            <div class="whatsapp-page__filters">
              <VTextField
                v-model="search"
                class="whatsapp-page__search"
                density="compact"
                hide-details
                clearable
                prepend-inner-icon="tabler-search"
                :placeholder="$t('pages.whatsapp.searchTemplates')"
              />
              <VBtnToggle
                v-model="statusFilter"
                class="whatsapp-page__status-filter"
                color="primary"
                density="compact"
                mandatory
                divided
              >
                <VBtn value="all">{{ $t('pages.whatsapp.filters.all') }}</VBtn>
                <VBtn value="active">{{ $t('common.statuses.active') }}</VBtn>
                <VBtn value="inactive">{{ $t('common.statuses.inactive') }}</VBtn>
              </VBtnToggle>
              <VBtn
                v-if="hasFilters"
                size="small"
                variant="text"
                :aria-label="$t('pages.whatsapp.clearFilters')"
                :title="$t('pages.whatsapp.clearFilters')"
                icon="tabler-x"
                @click="clearFilters"
              />
            </div>

            <VAlert
              v-if="error"
              type="error"
              variant="tonal"
              density="compact"
              class="mt-3"
            >
              <div class="d-flex align-center justify-space-between ga-3">
                <span>{{ $t('pages.whatsapp.loadFailed') }}</span>
                <VBtn
                  size="small"
                  variant="text"
                  :loading="loading"
                  @click="load"
                >
                  {{ $t('pages.whatsapp.retry') }}
                </VBtn>
              </div>
            </VAlert>

            <div
              v-if="!error"
              class="whatsapp-page__list"
            >
              <article
              v-for="template in visibleTemplates(audience)"
              :key="template.id"
              class="whatsapp-row"
            >
              <div class="whatsapp-row__header">
                <VChip
                  class="whatsapp-row__order"
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                  :aria-label="$t('pages.whatsapp.orderValue', { order: template.sort_order })"
                >
                  #{{ template.sort_order }}
                </VChip>
                <div class="whatsapp-row__identity">
                  <div class="whatsapp-row__title">
                  {{ template.title }}
                  </div>
                  <div
                    v-if="template.labels.length"
                    class="whatsapp-row__labels"
                  >
                  <VChip
                    v-for="label in template.labels"
                    :key="label"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                  >
                    {{ label }}
                  </VChip>
                  </div>
                </div>
                <div class="whatsapp-row__actions">
                  <VChip
                    size="x-small"
                    variant="tonal"
                    :color="template.active ? 'success' : 'default'"
                  >
                    {{ $t(template.active ? 'common.statuses.active' : 'common.statuses.inactive') }}
                  </VChip>
                  <VBtn
                    v-if="canManage(audience)"
                    icon="tabler-pencil"
                    variant="text"
                    size="small"
                    :aria-label="$t('common.actions.edit')"
                    @click="openEditor(template)"
                  />
                  <VBtn
                    v-if="canManage(audience)"
                    icon="tabler-trash"
                    variant="text"
                    size="small"
                    color="error"
                    :aria-label="$t('common.actions.delete')"
                    @click="askDelete(template)"
                  />
                </div>
              </div>

              <div class="whatsapp-row__content">
                <div
                  class="whatsapp-row__bubble"
                  :class="{ 'whatsapp-row__bubble--expanded': isExpanded(template) }"
                  dir="auto"
                  v-html="previewHtmlFor(template)"
                />
                <div class="whatsapp-row__meta">
                  <VChip
                    v-for="token in tokensFor(template)"
                    :key="token"
                    size="x-small"
                    variant="outlined"
                  >
                    {{ tokenLabel(token) }}
                  </VChip>
                  <VBtn
                    size="x-small"
                    variant="text"
                    @click="toggleExpanded(template)"
                  >
                    {{ $t(isExpanded(template) ? 'pages.whatsapp.showLess' : 'pages.whatsapp.showMore') }}
                  </VBtn>
                </div>
              </div>
              </article>

              <VAlert
                v-if="!loading && visibleTemplates(audience).length === 0"
                type="info"
                variant="tonal"
                density="compact"
              >
                {{ $t(hasFilters ? 'pages.whatsapp.noMatches' : 'pages.whatsapp.empty') }}
              </VAlert>
            </div>
          </VCardText>
        </VWindowItem>
      </VWindow>
    </VCard>

    <VDialog
      v-model="editorDialog"
      max-width="900"
      scrollable
    >
      <VCard
        :title="$t(editing ? 'pages.whatsapp.editTemplate' : 'pages.whatsapp.addTemplate')"
        :loading="saving"
      >
        <VCardText class="whatsapp-page__editor">
          <VAlert
            v-if="formError"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ formError }}
          </VAlert>

          <VRow dense>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.title"
                :label="$t('pages.whatsapp.titleLabel')"
                :error-messages="fieldErrors.title"
                maxlength="150"
                density="compact"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.labels"
                :label="$t('pages.whatsapp.labelsLabel')"
                :hint="$t('pages.whatsapp.labelsHint')"
                persistent-hint
                density="compact"
              />
              <div
                v-if="formLabels.length"
                class="d-flex flex-wrap ga-2 mt-2"
              >
                <VChip
                  v-for="label in formLabels"
                  :key="label"
                  size="small"
                  color="primary"
                  variant="tonal"
                >
                  {{ label }}
                </VChip>
              </div>
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model.number="form.sort_order"
                type="number"
                min="0"
                :label="$t('pages.whatsapp.sortOrder')"
                density="compact"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <div class="whatsapp-page__active-toggle">
                <VSwitch
                  v-model="form.active"
                  :label="$t('common.statuses.active')"
                  color="primary"
                  hide-details
                  density="compact"
                />
              </div>
            </VCol>

            <VCol cols="12">
              <WhatsappMessageEditor
                v-model="form.message"
                :fields="formFields"
                :disabled="saving"
              />
              <p
                v-if="fieldErrors.message"
                class="text-error text-caption mt-2"
              >
                {{ fieldErrors.message }}
              </p>
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="saving"
            @click="editorDialog = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            :prepend-icon="'tabler-device-floppy'"
            @click="save"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="deleteDialog"
      max-width="480"
    >
      <VCard :title="$t('common.actions.delete')">
        <VCardText>
          {{ $t('pages.whatsapp.confirmDelete', { title: deleting?.title ?? '' }) }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="saving"
            @click="deleteDialog = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            :loading="saving"
            @click="confirmDelete"
          >
            {{ $t('common.actions.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss" scoped>
.whatsapp-page {
  &__workspace {
    overflow: hidden;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 0.9rem;
  }

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-block: 1rem 0.875rem;
  }

  &__header-actions {
    flex: 0 0 auto;
  }

  &__window {
    overflow: visible;
  }

  &__content {
    padding-block: 1rem;
  }

  &__filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
  }

  &__search {
    flex: 0 1 24rem;
    inline-size: 100%;
    max-inline-size: 24rem;
    margin-inline-end: auto;
  }

  &__status-filter {
    flex: 0 1 auto;
    max-inline-size: 100%;
    overflow-x: auto;
  }

  &__status-filter :deep(.v-btn) {
    min-inline-size: max-content;
    padding-inline: 0.65rem;
    text-transform: none;
  }

  &__list {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    margin-block-start: 0.875rem;
  }

  &__editor :deep(.v-input__details) {
    min-block-size: 1.25rem;
  }

  &__active-toggle {
    display: flex;
    min-block-size: 40px;
    align-items: center;
  }
}

.whatsapp-row {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.75rem;
  background: rgb(var(--v-theme-surface));

  &__header {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
    padding: 0.75rem 0.875rem 0.625rem;
  }

  &__order {
    flex: 0 0 auto;
    margin-block-start: 0.15rem;
    font-variant-numeric: tabular-nums;
  }

  &__identity {
    min-inline-size: 0;
    flex: 1 1 auto;
  }

  &__title {
    color: rgb(var(--v-theme-on-surface));
    font-size: 0.9375rem;
    font-weight: 600;
    line-height: 1.35;
    overflow-wrap: anywhere;
  }

  &__labels {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-block-start: 0.35rem;
  }

  &__actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 0.125rem;
  }

  &__content {
    padding: 0 0.875rem 0.75rem;
  }

  &__bubble {
    display: -webkit-box;
    overflow: hidden;
    padding: 0.55rem 0.7rem;
    border-radius: 0.75rem;
    border-start-start-radius: 0.25rem;
    background: rgba(var(--v-theme-success), 0.12);
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    font-size: 0.9375rem;
    line-height: 1.5;
    overflow-wrap: anywhere;
  }

  &__bubble--expanded {
    display: block;
    overflow: visible;
  }

  &__bubble :deep(code) {
    padding-block: 0.1rem;
    padding-inline: 0.3rem;
    border-radius: 0.25rem;
    background: rgba(var(--v-theme-on-surface), 0.08);
    font-family: monospace;
  }

  &__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-block-start: 0.45rem;
  }

  &__meta :deep(.v-btn) {
    min-inline-size: 0;
    padding-inline: 0.25rem;
    text-transform: none;
  }
}

@media (max-width: 599px) {
  .whatsapp-page {
    &__header {
      align-items: flex-start;
      padding-block-start: 0.875rem;
    }

    &__header .text-body-2 {
      display: none;
    }

    &__header-actions :deep(.v-btn) {
      min-inline-size: 2.25rem;
      padding-inline: 0.5rem;
      font-size: 0;
    }

    &__header-actions :deep(.v-btn .v-icon) {
      margin: 0;
      font-size: 1.125rem;
    }

    &__search {
      flex-basis: 100%;
      max-inline-size: none;
    }

  }

  .whatsapp-row {
    &__header {
      gap: 0.45rem;
      padding-inline: 0.625rem;
    }

    &__actions :deep(.v-btn) {
      inline-size: 1.8rem;
      block-size: 1.8rem;
    }

    &__content {
      padding-inline: 0.625rem;
    }
  }
}
</style>
