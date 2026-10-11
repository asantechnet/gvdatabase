<script setup lang="ts">
import { useToast } from 'vue-toastification'
import {
  type Announcement,
  type AnnouncementAudienceOptions,
  type AnnouncementScope,
  type AnnouncementScopeType,
  createAnnouncement,
  getAnnouncementAudienceOptions,
  getAnnouncements,
} from '@/api/announcements'
import { apiErrorMessage } from '@/utils/apiErrors'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const { t } = useI18n()

const announcements = ref<Announcement[]>([])
const loading = ref(true)
const sending = ref(false)
const showComposer = ref(false)

const canViewAll = computed(() => canAccess('view_all_announcements'))
const authorFilter = ref<'mine' | 'everyone'>('mine')

const audienceOptions = ref<AnnouncementAudienceOptions | null>(null)
const audienceLoading = ref(false)

const form = ref<{ title: string; message: string; labelsText: string; scopes: Array<{ type: AnnouncementScopeType; id?: number }> }>({
  title: '',
  message: '',
  labelsText: '',
  scopes: [],
})

const formError = ref('')

const labels = computed(() => form.value.labelsText
  .split(',')
  .map(label => label.trim())
  .filter(label => label !== '')
  .filter((label, index, all) => all.indexOf(label) === index)
  .slice(0, 20))

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

const targetLabel = (scope: AnnouncementScope): string => {
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
    // A stored announcement always carries an id, so fall back to the raw
    // value rather than showing the composer's "pick one" placeholder.
    return scope.id ? `#${scope.id}` : t('pages.announcements.composer.chooseTargetLabel')
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
  .map(scope => targetLabel(scope))
  .join(' · ')

const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))

const loadAnnouncements = async () => {
  loading.value = true
  try {
    announcements.value = await getAnnouncements()
  }
  catch (error: any) {
    toast.error(apiErrorMessage(error, translate('pages.announcements.couldNotLoad')))
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
    toast.error(apiErrorMessage(error, translate('pages.announcements.couldNotLoad')))
  }
  finally {
    audienceLoading.value = false
  }
}

const toggleComposer = async () => {
  showComposer.value = !showComposer.value
  if (showComposer.value && !audienceOptions.value)
    await loadAudienceOptions()
}

const resetForm = () => {
  form.value = { title: '', message: '', labelsText: '', scopes: [] }
  formError.value = ''
  selectedScopeType.value = null
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
    await createAnnouncement({
      title: form.value.title.trim() || null,
      message: form.value.message.trim(),
      labels: labels.value,
      audience,
    })

    toast.success(t('pages.announcements.sentToast'))
    resetForm()
    showComposer.value = false
    await loadAnnouncements()
  }
  catch (error: any) {
    formError.value = apiErrorMessage(error, translate('common.messages.unableToSaveChanges'))
  }
  finally {
    sending.value = false
  }
}

onMounted(loadAnnouncements)
</script>

<template>
  <div class="announcements-page">
    <VCard
      class="mb-6"
      :loading="loading"
    >
      <VCardItem>
        <VCardTitle>{{ $t('pages.announcements.title') }}</VCardTitle>
        <VCardSubtitle>{{ $t('pages.announcements.subtitle') }}</VCardSubtitle>
        <template #append>
          <VBtn
            color="primary"
            prepend-icon="tabler-speakerphone"
            @click="toggleComposer"
          >
            {{ $t('pages.announcements.compose') }}
          </VBtn>
        </template>
      </VCardItem>
    </VCard>

    <VDialog
      v-model="showComposer"
      max-width="900"
      scrollable
    >
      <VCard
        class="composer-card"
        :loading="audienceLoading"
      >
      <VCardItem>
        <VCardTitle>{{ $t('pages.announcements.composer.title') }}</VCardTitle>
        <VCardSubtitle>{{ $t('pages.announcements.composer.subtitle') }}</VCardSubtitle>
      </VCardItem>
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ $t('pages.announcements.composer.immutableNotice') }}
        </VAlert>

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
              <VTextField
                v-model="form.title"
                :label="$t('pages.announcements.composer.titleLabel')"
                :placeholder="$t('pages.announcements.composer.titlePlaceholder')"
                maxlength="255"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.labelsText"
                :label="$t('pages.announcements.composer.labelsLabel')"
                :placeholder="$t('pages.announcements.composer.labelsPlaceholder')"
                :hint="$t('pages.announcements.composer.labelsHint')"
                persistent-hint
              />
              <div
                v-if="labels.length"
                class="d-flex flex-wrap ga-2 mt-2"
              >
                <VChip
                  v-for="label in labels"
                  :key="label"
                  size="small"
                  color="primary"
                  variant="tonal"
                >
                  {{ label }}
                </VChip>
              </div>
            </VCol>
            <VCol cols="12">
              <VLabel class="mb-1">
                {{ $t('pages.announcements.composer.audienceLabel') }}
              </VLabel>
              <VSelect
                v-model="selectedScopeType"
                :items="scopeItems"
                item-title="title"
                item-value="value"
                :label="$t('pages.announcements.composer.addAudienceLabel')"
                :hint="$t('pages.announcements.composer.audienceHint')"
                density="compact"
                persistent-hint
                class="announcement-scope-select"
                @update:model-value="addScope"
              />

              <div
                v-if="selectedScopeTargets.length"
                class="d-flex flex-column ga-3 mt-3"
              >
                <div
                  v-for="target in selectedScopeTargets"
                  :key="target.scope.type"
                  class="announcement-scope-row"
                >
                  <VChip
                    closable
                    :color="target.incomplete ? 'warning' : 'primary'"
                    variant="tonal"
                    @click:close="removeScope(target.index)"
                  >
                    {{ target.label }}
                    <span
                      v-if="target.count !== undefined"
                      class="ms-1 opacity-70"
                    >({{ target.count }})</span>
                  </VChip>

                  <VSelect
                    v-if="target.needsTarget"
                    v-model="target.scope.id"
                    :items="targetOptionsFor(target.scope.type)"
                    item-title="title"
                    item-value="value"
                    :label="$t('pages.announcements.composer.chooseTargetLabel')"
                    density="compact"
                    hide-details
                    class="announcement-scope-select"
                  />
                </div>
              </div>
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="form.message"
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
      <VCardActions class="px-6 pb-5">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="resetForm(); showComposer = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-send"
          :loading="sending"
          @click="send"
        >
          {{ $t('pages.announcements.composer.send') }}
        </VBtn>
      </VCardActions>
      </VCard>
    </VDialog>

    <VCard>
      <VCardItem class="announcement-list-header">
        <VCardTitle>{{ canViewAll && authorFilter === 'everyone' ? $t('pages.announcements.list.everyones') : $t('pages.announcements.list.mine') }}</VCardTitle>
        <template #append>
          <VBtnToggle
            v-if="canViewAll"
            v-model="authorFilter"
            mandatory
            density="compact"
            color="primary"
            class="announcement-filter"
          >
            <VBtn value="mine">
              {{ $t('pages.announcements.filters.mine') }}
            </VBtn>
            <VBtn value="everyone">
              {{ $t('pages.announcements.filters.everyone') }}
            </VBtn>
          </VBtnToggle>
        </template>
      </VCardItem>
      <VDivider />
      <VList
        :lines="false"
        class="announcement-list"
      >
        <template
          v-for="(announcement, index) in announcements"
          :key="announcement.id"
        >
          <VDivider v-if="index > 0" />
          <VListItem class="announcement-row">
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
              >
                <VIcon icon="tabler-speakerphone" />
              </VAvatar>
            </template>
            <VListItemTitle class="announcement-title">
              {{ announcement.title || $t('pages.announcements.list.untitled') }}
            </VListItemTitle>
            <VListItemSubtitle class="announcement-body">
              <span class="announcement-message">{{ announcement.message }}</span>
            </VListItemSubtitle>
            <div class="announcement-meta">
              <VChip
                size="x-small"
                variant="outlined"
                prepend-icon="tabler-user"
              >
                {{ announcement.sender?.name || '—' }}
              </VChip>
              <VChip
                size="x-small"
                variant="outlined"
                prepend-icon="tabler-target"
              >
                {{ audienceDescription(announcement) }}
              </VChip>
              <VChip
                v-for="label in announcement.labels"
                :key="label"
                size="x-small"
                color="primary"
                variant="tonal"
              >
                {{ label }}
              </VChip>
              <span class="text-caption text-disabled">{{ formatDate(announcement.created_at) }}</span>
            </div>
          </VListItem>
        </template>
        <VListItem
          v-if="!loading && !announcements.length"
          :title="$t('pages.announcements.list.empty')"
          :subtitle="$t('pages.announcements.list.emptyHint')"
          class="announcement-row"
        />
      </VList>
    </VCard>
  </div>
</template>

<style scoped>
.announcements-page {
  margin-inline: auto;
  max-inline-size: 1100px;
}

.composer-card {
  border: 1px solid rgb(var(--v-theme-primary) / 25%);
}

/* The audience picker keeps a readable measure instead of stretching the
   select across the full column on wide screens. */
.announcement-scope-select {
  max-inline-size: 20rem;
}

/* One chip plus its target picker per row; wraps on narrow viewports. */
.announcement-scope-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.announcement-list-header {
  gap: 1rem;
}

/* Vuetify's .v-btn sets `flex: 1 0 auto`, which stretches every option to the
   width of the widest label. Because "Mine" is much shorter than "Everyone",
   that inflated "Mine" into a mostly-empty button while "Everyone" stayed
   cramped. Opting out of the grow lets each option size to its own text, and
   a fixed inline padding keeps that padding identical on both. */
.announcement-filter :deep(.v-btn) {
  flex: 0 0 auto;
  min-inline-size: max-content;
  padding-inline: 1rem;
  white-space: nowrap;
}

/* Vuetify's default list-item padding is tight for rows that carry a title,
   a wrapping body and a chip row. Restore breathing room and separate
   consecutive announcements with dividers. */
.announcement-row {
  padding-block: 0.875rem;
}

.announcement-list {
  padding-block: 0;
}

.announcement-title {
  font-weight: 700;
  line-height: 1.4;
  white-space: normal;
}

/* Long announcement bodies must wrap instead of being clipped by the
   single-line truncation VListItemSubtitle applies by default. */
.announcement-body,
.announcement-message {
  display: block;
  overflow-wrap: anywhere;
  white-space: normal;
}

.announcement-body {
  margin-block-start: 0.25rem;
  opacity: 1;
}

.announcement-message {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  line-height: 1.5;
}

.announcement-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-block-start: 0.625rem;
}
</style>
