<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { apiErrorMessage } from '@/utils/apiErrors'
import CrmApi, { type EntityType, type Channel, type Interaction, type Relationship } from '@/api/crmRelationships'
import ErpField from '@/components/erp/ErpField.vue'
import { confirmActionWarning } from '@/composables/useActionWarning'

const props = withDefaults(defineProps<{
  type: EntityType
  id: number
  title?: string
  mobileAction?: boolean
  menuAction?: boolean
}>(), {
  mobileAction: false,
  menuAction: false,
})
const { t, locale } = useI18n()
const toast = useToast()
const open = ref(false)
const loading = ref(false)
const saving = ref(false)
const record = ref<Relationship | null>(null)
const activeTab = ref<'details' | 'overview' | 'history'>('details')
const channelFilter = ref<Channel | 'all'>('all')
const interactionDialog = ref(false)
const editingInteractionId = ref<number | null>(null)
const notesDraft = ref('')
const interactionErrors = ref({ title: '' })

const form = ref<{ channel: Channel; title: string; notes: string; contacted_at: string; follow_up_at: string | null }>({
  channel: 'note', title: '', notes: '', contacted_at: '', follow_up_at: null,
})


const channels = computed<Array<{ value: Channel; label: string; icon: string; color: string }>>(() => [
  { value: 'call', label: t('common.crmDetails.channels.calls'), icon: 'tabler-phone', color: 'success' },
  { value: 'message', label: t('common.crmDetails.channels.messages'), icon: 'tabler-message', color: 'primary' },
  { value: 'email', label: t('common.crmDetails.channels.emails'), icon: 'tabler-mail', color: 'info' },
  { value: 'meeting', label: t('common.crmDetails.channels.meetings'), icon: 'tabler-users', color: 'warning' },
  { value: 'note', label: t('common.crmDetails.channels.notes'), icon: 'tabler-note', color: 'secondary' },
])

const channelMeta = (channel: Channel) => channels.value.find(item => item.value === channel) ?? channels.value[4]
const filteredInteractions = computed(() => (record.value?.interactions ?? []).filter(item => channelFilter.value === 'all' || item.channel === channelFilter.value))

const groupedInteractions = computed(() => {
  const groups = new Map<string, Interaction[]>()

  filteredInteractions.value.forEach(item => {
    const key = new Intl.DateTimeFormat(locale.value, { year: 'numeric', month: 'long', day: 'numeric' }).format(new Date(item.contacted_at))

    groups.set(key, [...(groups.get(key) ?? []), item])
  })

  return [...groups.entries()]
})

const interactionCounts = computed<Record<Channel, number>>(() => Object.fromEntries(
  channels.value.map(channel => [channel.value, (record.value?.interactions ?? []).filter(item => item.channel === channel.value).length]),
) as Record<Channel, number>)

const notesDirty = computed(() => notesDraft.value.trim() !== (record.value?.notes ?? '').trim())

const detailItems = computed(() => {
  const item = record.value
  if (!item)
    return []

  return [
    { key: 'contact', icon: 'tabler-phone', label: t('common.crmDetails.fields.contact'), value: item.contact },
    { key: 'email', icon: 'tabler-mail', label: t('common.crmDetails.fields.email'), value: item.email },
    { key: 'service', icon: 'tabler-briefcase', label: t('common.crmDetails.fields.service'), value: item.service || item.service_interest },
    { key: 'country', icon: 'tabler-world', label: t('common.fields.country'), value: item.country?.name },
    { key: 'education-level', icon: 'tabler-school', label: t('educational.educationLevel'), value: item.education_level?.name },
    { key: 'age', icon: 'tabler-user', label: t('pages.contracts.visitor.visitor.age'), value: item.age },
    { key: 'grade-score', icon: 'tabler-percentage', label: t('pages.contracts.visitor.visitor.gradeScoreLabel'), value: item.grade_score == null ? null : `${item.grade_score}%` },
    { key: 'province', icon: 'tabler-map-pin', label: t('pages.contracts.visitor.visitor.province'), value: item.province?.name },
    { key: 'english-level', icon: 'tabler-language', label: t('pages.contracts.visitor.visitor.englishLevel'), value: item.english_level?.name },
    { key: 'acquisition-route', icon: 'tabler-speakerphone', label: t('pages.contracts.visitor.visitor.acquisitionRoute'), value: item.acquisition_route?.name },
    { key: 'consultant', icon: 'tabler-user-star', label: t('pages.contracts.visitor.visitor.consultant'), value: item.consultant?.name },
    { key: 'consultant-review', icon: 'tabler-message', label: t('pages.contracts.visitor.visitor.consultantReview'), value: item.consultant_review },
    { key: 'owner', icon: 'tabler-user-check', label: t('common.crmDetails.fields.owner'), value: item.responsible_employee?.name ?? item.owner?.name },
    { key: 'stage', icon: 'tabler-progress-check', label: t('common.crmDetails.fields.stage'), value: item.current_stage?.name ?? item.stage },
    { key: 'probability', icon: 'tabler-percentage', label: t('common.crmDetails.fields.probability'), value: item.probability == null ? null : `${item.probability}%` },
    { key: 'estimated_value', icon: 'tabler-cash', label: t('common.crmDetails.fields.estimatedValue'), value: item.estimated_value },
    { key: 'next_follow_up_at', icon: 'tabler-calendar-event', label: t('common.crmDetails.fields.nextFollowUp'), value: item.next_follow_up_at ? formatDateTime(item.next_follow_up_at) : null },
    { key: 'tax_number', icon: 'tabler-receipt-tax', label: t('common.crmDetails.fields.taxNumber'), value: item.tax_number },
    { key: 'date', icon: 'tabler-calendar', label: t('common.crmDetails.fields.since'), value: item.date ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(new Date(item.date)) : null },
  ].filter(detail => detail.value !== null && detail.value !== undefined && detail.value !== '')
})

const formatDateTime = (value: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
const pickerDate = (value: string) => value.slice(0, 16).replace('T', ' ')

// Keep the calendar compact inside the relationship dialog without adding a
// visual offset away from the field that opened it.
const compactCalendar = (_selectedDates: Date[], _dateString: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.setProperty('transform', 'translateY(0) scale(0.86)', 'important')
    instance.calendarContainer.style.setProperty('transform-origin', 'top left', 'important')
  })
}

const load = async () => {
  loading.value = true
  try {
    record.value = await CrmApi.get(props.type, props.id)
    notesDraft.value = record.value?.notes ?? ''
  }
  catch (error) {
    toast.error(apiErrorMessage(error, t('common.crmDetails.loadFailed')))
  }
  finally {
    loading.value = false
  }
}

const show = () => {
  open.value = true
  void load()
}

const saveProfileNotes = async () => {
  saving.value = true
  try {
    const savedNotes = await CrmApi.saveNotes(props.type, props.id, notesDraft.value.trim() || null)
    if (record.value)
      record.value.notes = savedNotes
    notesDraft.value = savedNotes ?? ''
    toast.success(t('common.crmDetails.notesSaved'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error, t('common.crmDetails.notesSaveFailed')))
  }
  finally {
    saving.value = false
  }
}

const startInteraction = (interaction?: Interaction) => {
  interactionErrors.value.title = ''
  editingInteractionId.value = interaction?.id ?? null
  form.value = interaction
    ? {
        channel: interaction.channel,
        title: interaction.title,
        notes: interaction.notes ?? '',
        contacted_at: pickerDate(interaction.contacted_at),
        follow_up_at: interaction.follow_up_at ? pickerDate(interaction.follow_up_at) : null,
      }
    : {
        channel: 'note',
        title: '',
        notes: '',
        contacted_at: pickerDate(new Date().toISOString()),
        follow_up_at: null,
      }
  interactionDialog.value = true
}

const saveInteraction = async () => {
  interactionErrors.value.title = form.value.title.trim() ? '' : t('erp.required')
  if (interactionErrors.value.title)
    return

  saving.value = true
  try {
    const payload = { ...form.value, title: form.value.title.trim(), notes: form.value.notes.trim() }
    if (editingInteractionId.value)
      await CrmApi.updateInteraction(editingInteractionId.value, payload)
    else
      await CrmApi.createInteraction(props.type, props.id, payload)

    interactionDialog.value = false
    await load()
    activeTab.value = 'history'
    toast.success(t(editingInteractionId.value ? 'common.crmDetails.conversationUpdated' : 'common.crmDetails.conversationAdded'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error, t('common.crmDetails.conversationSaveFailed')))
  }
  finally {
    saving.value = false
  }
}

const removeInteraction = async (interaction: Interaction) => {
  const confirmed = await confirmActionWarning({
    key: 'delete-crm-interaction',
    title: t('common.crmDetails.deleteConversationTitle'),
    message: t('common.crmDetails.deleteConversationMessage'),
    confirmText: t('common.actions.delete'),
    cancelText: t('common.actions.cancel'),
    icon: 'tabler-trash',
    tone: 'error',
  })
  if (!confirmed)
    return

  try {
    await CrmApi.removeInteraction(interaction.id)
    await load()
    toast.success(t('common.crmDetails.conversationRemoved'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error, t('common.crmDetails.conversationDeleteFailed')))
  }
}
</script>

<template>
  <VListItem
    v-if="props.menuAction"
    class="crm-relationship-details__menu-action"
    prepend-icon="tabler-address-book"
    :title="$t('common.crmDetails.relationshipDetails')"
    @click.stop="show"
  />
  <VBtn
    v-else
    :icon="!props.mobileAction && !props.menuAction"
    :block="props.mobileAction"
    variant="text"
    size="small"
    color="primary"
    :class="{ 'crm-relationship-details__mobile-action': props.mobileAction }"
    :aria-label="$t('common.crmDetails.relationshipDetails')"
    @click.stop="show"
  >
    <VIcon icon="tabler-address-book" />
    <span v-if="props.mobileAction" class="ms-2">
      {{ $t('common.crmDetails.relationshipDetails') }}
    </span>
    <VTooltip
      activator="parent"
      location="top"
    >
      {{ $t('common.crmDetails.relationshipDetails') }}
    </VTooltip>
  </VBtn>

  <VDialog
    v-model="open"
    persistent
    max-width="1180"
    class="relationship-dialog-overlay"
  >
    <VCard class="relationship-dialog">
      <VCardText class="relationship-hero pa-5 pa-sm-6">
        <div class="d-flex align-start ga-4">
          <VAvatar
            color="primary"
            variant="tonal"
            size="52"
          >
            <VIcon
              icon="tabler-user-heart"
              size="27"
            />
          </VAvatar>
          <div class="min-w-0">
            <div class="text-overline text-primary">
              {{ $t('common.crmDetails.entityRelationship', { entity: $t(`common.crmDetails.entities.${type}`) }) }}
            </div>
            <h2 class="text-h5 font-weight-bold text-truncate">
              {{ record?.name || title }}
            </h2>
          </div>
          <VSpacer />
          <VChip
            v-if="record?.stage"
            color="primary"
            variant="tonal"
          >
            {{ record.stage }}
          </VChip>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="error"
            class="relationship-dialog__close"
            :aria-label="$t('common.actions.close')"
            @click="open = false"
          />
        </div>
      </VCardText>
      <VDivider />
      <div class="relationship-nav">
        <VTabs
          v-model="activeTab"
          color="primary"
          height="56"
          class="px-4 relationship-tabs"
        >
          <VTab
            value="details"
            prepend-icon="tabler-id"
          >
            {{ $t('common.crmDetails.details') }}
          </VTab>
          <VTab
            value="overview"
            prepend-icon="tabler-notes"
          >
            {{ $t('common.crmDetails.overview') }}
          </VTab>
          <VTab
            value="history"
            prepend-icon="tabler-history"
          >
            {{ $t('common.crmUi.conversationHistory') }}
            <VBadge
              :content="record?.interactions?.length || 0"
              inline
              color="primary"
            />
          </VTab>
        </VTabs>
      </div>
      <VDivider />
      <VCardText class="pa-5 pa-sm-6 relationship-content">
        <div v-if="loading" class="relationship-skeleton" aria-busy="true">
          <div class="relationship-skeleton__intro">
            <div class="relationship-skeleton__intro-copy">
              <VSkeletonLoader type="heading" />
              <VSkeletonLoader type="text" />
            </div>
            <VSkeletonLoader type="chip" />
          </div>
          <VRow>
            <VCol
              v-for="index in 9"
              :key="index"
              cols="12"
              sm="6"
              md="4"
            >
              <VCard border variant="flat" height="100%" class="detail-tile relationship-skeleton__tile">
                <VCardText class="d-flex align-start ga-3">
                  <div class="relationship-skeleton__avatar" />
                  <div class="relationship-skeleton__copy flex-grow-1">
                    <VSkeletonLoader class="relationship-skeleton__label" type="text" />
                    <VSkeletonLoader class="relationship-skeleton__value" type="text" />
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>
        <VWindow
          v-else
          v-model="activeTab"
        >
          <VWindowItem value="details">
            <div class="details-intro mb-5">
              <div>
                <div class="text-h6 font-weight-bold">
                  {{ $t('common.crmDetails.profileTitle') }}
                </div>
                <div class="text-body-2 text-medium-emphasis">
                  {{ $t('common.crmDetails.profileHint') }}
                </div>
              </div>
              <VChip
                :prepend-icon="type === 'applicant' ? 'tabler-user-check' : 'tabler-walk'"
                color="primary"
                variant="tonal"
              >
                {{ $t(`common.crmDetails.entities.${type}`) }}
              </VChip>
            </div>

            <VRow>
              <VCol
                v-for="item in detailItems"
                :key="item.key"
                cols="12"
                sm="6"
                md="4"
              >
                <VCard
                  border
                  variant="flat"
                  height="100%"
                  class="detail-tile"
                >
                  <VCardText class="d-flex align-start ga-3">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      size="38"
                    >
                      <VIcon
                        :icon="item.icon"
                        size="19"
                      />
                    </VAvatar>
                    <div class="min-w-0">
                      <div class="text-caption text-medium-emphasis">
                        {{ item.label }}
                      </div>
                      <div class="text-body-1 font-weight-medium text-break">
                        {{ item.value }}
                      </div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>

            <VCard
              v-if="record?.address"
              border
              variant="flat"
              class="mt-4"
            >
              <VCardText class="d-flex align-start ga-3">
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="38"
                >
                  <VIcon
                    icon="tabler-map-pin"
                    size="19"
                  />
                </VAvatar>
                <div>
                  <div class="text-caption text-medium-emphasis">
                    {{ $t('common.crmDetails.fields.address') }}
                  </div><div class="relationship-note">
                    {{ record.address }}
                  </div>
                </div>
              </VCardText>
            </VCard>

            <VCard
              v-if="record?.services?.length"
              border
              variant="flat"
              class="mt-4"
            >
              <VCardText>
                <div class="d-flex align-center ga-2 mb-3">
                  <VIcon
                    icon="tabler-packages"
                    color="primary"
                  /><span class="font-weight-bold">{{ $t('common.crmDetails.contractedServices') }}</span>
                </div>
                <div class="d-flex flex-wrap ga-2">
                  <VChip
                    v-for="serviceItem in record.services"
                    :key="serviceItem.id"
                    color="primary"
                    variant="tonal"
                  >
                    {{ serviceItem.type }}<span v-if="serviceItem.status"> · {{ serviceItem.status }}</span>
                  </VChip>
                </div>
              </VCardText>
            </VCard>
          </VWindowItem>

          <VWindowItem value="overview">
            <VRow>
              <VCol
                cols="12"
                md="7"
              >
                <VCard
                  border
                  variant="flat"
                >
                  <VCardText>
                    <div class="d-flex align-center justify-space-between mb-3">
                      <div>
                        <div class="text-subtitle-1 font-weight-bold">
                          {{ $t('common.crmUi.relationshipNotes') }}
                        </div>
                        <div class="text-caption">
                          {{ $t('common.crmDetails.contextHint') }}
                        </div>
                      </div>
                      <VIcon icon="tabler-notebook" />
                    </div>
                    <VTextarea
                      v-model="notesDraft"
                      bg-color="surface"
                      rows="7"
                      auto-grow
                      :placeholder="$t('common.crmDetails.notesPlaceholder')"
                    />
                    <div class="d-flex justify-end mt-3">
                      <VBtn
                        type="button"
                        :loading="saving"
                        :disabled="!record || !notesDirty"
                        prepend-icon="tabler-device-floppy"
                        @click.stop="saveProfileNotes"
                      >
                        {{ $t('common.crmDetails.saveNotes') }}
                      </VBtn>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol
                cols="12"
                md="5"
              >
                <VCard
                  border
                  height="100%"
                >
                  <VCardText>
                    <div class="text-subtitle-1 font-weight-bold mb-4">
                      {{ $t('common.crmDetails.activityMix') }}
                    </div>
                    <div
                      v-for="item in channels"
                      :key="item.value"
                      class="d-flex align-center ga-3 mb-4"
                    >
                      <VAvatar
                        :color="item.color"
                        variant="tonal"
                        size="34"
                      >
                        <VIcon
                          :icon="item.icon"
                          size="18"
                        />
                      </VAvatar>
                      <span>{{ item.label }}</span><VSpacer /><span class="text-h6">{{ interactionCounts[item.value] }}</span>
                    </div>
                    <VBtn
                      block
                      color="primary"
                      variant="flat"
                      prepend-icon="tabler-plus"
                      @click="startInteraction()"
                    >
                      {{ $t('common.crmDetails.addConversation') }}
                    </VBtn>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VWindowItem>

          <VWindowItem value="history">
            <div class="d-flex flex-wrap align-center ga-2 mb-5">
              <VChip
                :variant="channelFilter === 'all' ? 'flat' : 'outlined'"
                color="primary"
                @click="channelFilter = 'all'"
              >
                {{ $t('common.crmDetails.all') }} {{ record?.interactions?.length || 0 }}
              </VChip>
              <VChip
                v-for="item in channels"
                :key="item.value"
                :prepend-icon="item.icon"
                :color="item.color"
                :variant="channelFilter === item.value ? 'flat' : 'outlined'"
                @click="channelFilter = item.value"
              >
                {{ item.label }} {{ interactionCounts[item.value] }}
              </VChip>
              <VSpacer />
              <VBtn
                prepend-icon="tabler-plus"
                @click="startInteraction()"
              >
                {{ $t('common.crmDetails.addConversation') }}
              </VBtn>
            </div>

            <VAlert
              v-if="!filteredInteractions.length"
              color="info"
              variant="tonal"
              icon="tabler-message-off"
              :title="$t('common.crmDetails.emptyTitle')"
            >
              {{ $t('common.crmDetails.emptyHint') }}
            </VAlert>

            <section
              v-for="[date, interactions] in groupedInteractions"
              :key="date"
              class="mb-6"
            >
              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-3">
                {{ date }}
              </div>
              <VCard
                v-for="item in interactions"
                :key="item.id"
                border
                variant="flat"
                class="mb-3 interaction-card"
              >
                <VCardText>
                  <div class="d-flex align-start ga-3">
                    <VAvatar
                      :color="channelMeta(item.channel).color"
                      variant="tonal"
                      size="38"
                    >
                      <VIcon
                        :icon="channelMeta(item.channel).icon"
                        size="19"
                      />
                    </VAvatar>
                    <div class="flex-grow-1 min-w-0">
                      <div class="d-flex flex-wrap align-center ga-2">
                        <div class="font-weight-bold">
                          {{ item.title }}
                        </div>
                        <VChip
                          size="x-small"
                          :color="channelMeta(item.channel).color"
                          variant="tonal"
                        >
                          {{ channelMeta(item.channel).label }}
                        </VChip>
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ formatDateTime(item.contacted_at) }}<span v-if="item.creator"> · {{ item.creator.name }}</span>
                      </div>
                      <p class="text-body-2 mt-3 mb-0 relationship-note">
                        {{ item.notes }}
                      </p>
                      <VAlert
                        v-if="item.follow_up_at"
                        density="compact"
                        variant="tonal"
                        color="warning"
                        icon="tabler-bell"
                        class="mt-3"
                      >
                        {{ $t('common.crmDetails.followUpAt', { date: formatDateTime(item.follow_up_at) }) }}
                      </VAlert>
                    </div>
                    <VMenu>
                      <template #activator="{ props: menuProps }">
                        <VBtn
                          v-bind="menuProps"
                          icon="tabler-dots-vertical"
                          variant="text"
                          size="small"
                        />
                      </template>
                      <VList density="compact">
                        <VListItem
                          prepend-icon="tabler-edit"
                          :title="$t('common.actions.edit')"
                          @click.stop="startInteraction(item)"
                        />
                        <VListItem
                          prepend-icon="tabler-trash"
                          :title="$t('common.actions.delete')"
                          class="text-error"
                          @click.stop="removeInteraction(item)"
                        />
                      </VList>
                    </VMenu>
                  </div>
                </VCardText>
              </VCard>
            </section>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
  </VDialog>

  <VDialog
    v-model="interactionDialog"
    max-width="620"
    class="relationship-interaction-dialog"
  >
    <VCard class="relationship-interaction-card">
      <VCardTitle class="relationship-interaction-card__title px-5 py-5">
        <span class="relationship-interaction-card__heading">{{ $t(editingInteractionId ? 'common.crmDetails.editConversation' : 'common.crmDetails.addConversation') }}</span>
        <VSpacer />
        <VBtn icon="tabler-x" variant="tonal" color="error" class="relationship-interaction-card__close" :aria-label="$t('common.actions.close')" :disabled="saving" @click="interactionDialog = false" />
      </VCardTitle>
      <VCardText class="relationship-interaction-card__body pa-5 pa-sm-6">
        <div class="relationship-interaction-card__fields">
          <ErpField
          v-model="form.channel"
          kind="select"
          :items="channels"
          item-title="label"
          item-value="value"
          :label="$t('common.crmDetails.category')"
          :placeholder="$t('common.crmDetails.conversationPlaceholders.category')"
          required
          :show-required-rule="false"
        />
        <ErpField
          v-model="form.title"
          :label="$t('common.crmUi.conversationTitle')"
          :placeholder="$t('common.crmDetails.conversationPlaceholders.title')"
          :error-messages="interactionErrors.title"
          required
          :show-required-rule="false"
          autofocus
        />
        <ErpField
          v-model="form.notes"
          kind="textarea"
          :label="$t('common.crmDetails.notesOutcome')"
          :placeholder="$t('common.crmDetails.conversationPlaceholders.notes')"
          rows="5"
          auto-grow
        />
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <div class="erp-field">
              <label class="erp-field-label">{{ $t('common.crmDetails.contactedAt') }}</label>
              <AppDateTimePicker v-model="form.contacted_at" class="erp-field-control" :placeholder="$t('common.crmDetails.conversationPlaceholders.contactedAt')" :config="{ enableTime: true, dateFormat: 'Y-m-d H:i', static: false, position: 'auto', onOpen: compactCalendar }" />
            </div>
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <div class="erp-field">
              <label class="erp-field-label">{{ $t('common.crmUi.followUp') }}</label>
              <AppDateTimePicker v-model="form.follow_up_at" clearable class="erp-field-control" :placeholder="$t('common.crmDetails.conversationPlaceholders.followUp')" :config="{ enableTime: true, dateFormat: 'Y-m-d H:i', static: false, position: 'auto', onOpen: compactCalendar }" />
            </div>
          </VCol>
        </VRow>
        </div>
      </VCardText>
      <VCardActions class="relationship-interaction-card__actions px-5 py-4">
        <VSpacer />
        <VBtn
          variant="flat"
          color="error"
          class="relationship-interaction-card__cancel"
          :disabled="saving"
          @click="interactionDialog = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="relationship-interaction-card__save"
          :loading="saving"
          @click="saveInteraction"
        >
          {{ $t('common.crmDetails.saveConversation') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style>
.relationship-dialog-overlay .v-overlay__scrim,
.relationship-interaction-dialog .v-overlay__scrim {
  background: rgba(var(--v-theme-on-surface), 0.26) !important;
  backdrop-filter: blur(7px);
  opacity: 1 !important;
}
</style>

<style scoped>
.relationship-dialog {
  display: flex;
  overflow: hidden;
  max-block-size: calc(100dvh - 32px);
  flex-direction: column;
}
.relationship-hero {
  flex: 0 0 auto;
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), .11), rgb(var(--v-theme-surface)) 62%);
}
.relationship-nav {
  position: relative;
  z-index: 2;
  flex: 0 0 auto;
  background: rgb(var(--v-theme-surface));
}
.relationship-tabs { overflow: visible; }
.relationship-content {
  flex: 1 1 auto;
  overflow-y: auto;
  min-block-size: 0;
  overscroll-behavior: contain;
}
.relationship-note { white-space: pre-wrap; line-height: 1.65; }
.details-intro { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.detail-tile { background: linear-gradient(145deg, rgba(var(--v-theme-primary), .045), rgb(var(--v-theme-surface))); }
.relationship-skeleton { display: grid; gap: 1rem; }
.relationship-skeleton__intro { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-block-size: 54px; margin-block-end: 20px; padding: 0; }
.relationship-skeleton__intro-copy { display: grid; gap: .35rem; inline-size: min(100%, 15rem); }
.relationship-skeleton__intro-copy :deep(.v-skeleton-loader:first-child) { inline-size: 11rem; }
.relationship-skeleton__intro-copy :deep(.v-skeleton-loader:last-child) { inline-size: 15rem; }
.relationship-skeleton__intro > :deep(.v-skeleton-loader:last-child) { inline-size: 5rem; }
.relationship-skeleton__tile { min-block-size: 76px; }
.relationship-skeleton__avatar { flex: 0 0 38px; inline-size: 38px; block-size: 38px; border-radius: 50%; background: rgba(var(--v-theme-primary), .1); }
.relationship-skeleton__copy { display: grid; min-inline-size: 0; gap: .2rem; padding-block-start: .05rem; }
.relationship-skeleton__label { inline-size: min(100%, 6.5rem); }
.relationship-skeleton__value { inline-size: min(100%, 11rem); }
.relationship-dialog__close { inline-size: 36px; block-size: 36px; border: 1px solid rgba(var(--v-theme-error), .38) !important; border-radius: 50% !important; color: rgb(var(--v-theme-error)) !important; }
.relationship-dialog__close:hover { background: rgba(var(--v-theme-error), .18) !important; }
.interaction-card { transition: border-color .18s ease, transform .18s ease; }
.interaction-card:hover { border-color: rgba(var(--v-theme-primary), .45) !important; transform: translateY(-1px); }
.crm-relationship-details__mobile-action { justify-content: flex-start; }
.crm-relationship-details__menu-action {
  box-sizing: border-box;
  max-inline-size: 100%;
  min-block-size: 40px;
  min-inline-size: 0;
  overflow: hidden;
  padding-inline: 16px !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: .875rem !important;
  font-weight: 500 !important;
}

.crm-relationship-details__menu-action :deep(.v-icon) { color: inherit !important; font-size: 20px; }
.crm-relationship-details__menu-action :deep(.v-list-item-title) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.min-w-0 { min-width: 0; }

.relationship-interaction-card { overflow: hidden; }
.relationship-interaction-card__title,
.relationship-interaction-card__actions {
  display: flex;
  align-items: center;
  background-color: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%);
  border-color: rgba(var(--v-theme-on-surface), 0.035);
  border-style: solid;
  border-width: 0;
}
.relationship-interaction-card__title { border-block-end-width: 1px; }
.relationship-interaction-card__actions { border-block-start-width: 1px; }
.relationship-interaction-card__heading { font-size: 1.35rem; font-weight: 700; }
.relationship-interaction-card__close { border: 1px solid rgba(var(--v-theme-error), .34) !important; border-radius: 50% !important; }
.relationship-interaction-card__fields { display: grid; gap: 12px; }
.relationship-interaction-card__cancel {
  border: 1px solid rgb(var(--v-theme-error)) !important;
  background: rgba(var(--v-theme-error), .12) !important;
  color: rgb(var(--v-theme-error)) !important;
}
.relationship-interaction-card__cancel:hover,
.relationship-interaction-card__cancel:focus-visible,
.relationship-interaction-card__cancel.v-btn--active,
.relationship-interaction-card__cancel:active { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.relationship-interaction-card__save { color: rgb(var(--v-theme-on-primary)) !important; }

@media (max-height: 650px) {
  .relationship-dialog { max-block-size: calc(100dvh - 16px); }
  .relationship-hero { padding-block: 1rem !important; }
}
</style>
