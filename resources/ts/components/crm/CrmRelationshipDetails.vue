<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { apiErrorMessage } from '@/utils/apiErrors'
import api from '@/api/axios'

type EntityType = 'visitor' | 'lead' | 'customer'
type Channel = 'call' | 'message' | 'email' | 'meeting' | 'note'

interface Interaction {
  id: number
  channel: Channel
  title: string
  notes: string
  contacted_at: string
  follow_up_at: string | null
  creator?: { id: number; name: string } | null
}

interface Relationship {
  id: number
  name: string
  company?: string | null
  contact?: string | null
  email?: string | null
  marketer?: string | null
  address?: string | null
  tax_number?: string | null
  service?: string | null
  service_interest?: string | null
  stage?: string
  probability?: number | null
  estimated_value?: number | string | null
  next_follow_up_at?: string | null
  date?: string | null
  owner?: { id: number; name: string } | null
  services?: Array<{ id: number; type?: string | null; status?: string | null }>
  notes?: string | null
  interactions?: Interaction[]
}

const props = defineProps<{ type: EntityType; id: number; title?: string }>()
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

const form = ref<{ channel: Channel; title: string; notes: string; contacted_at: string; follow_up_at: string | null }>({
  channel: 'note', title: '', notes: '', contacted_at: '', follow_up_at: null,
})

const endpoint = computed(() => `${props.type === 'customer' ? 'customers' : props.type === 'visitor' ? 'visitors' : 'leads'}/${props.id}`)

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
    { key: 'company', icon: 'tabler-building', label: t('common.crmDetails.fields.company'), value: item.company },
    { key: 'contact', icon: 'tabler-phone', label: t('common.crmDetails.fields.contact'), value: item.contact },
    { key: 'email', icon: 'tabler-mail', label: t('common.crmDetails.fields.email'), value: item.email },
    { key: 'marketer', icon: 'tabler-user-star', label: t('common.crmDetails.fields.marketer'), value: item.marketer },
    { key: 'service', icon: 'tabler-briefcase', label: t('common.crmDetails.fields.service'), value: item.service || item.service_interest },
    { key: 'owner', icon: 'tabler-user-check', label: t('common.crmDetails.fields.owner'), value: item.owner?.name },
    { key: 'stage', icon: 'tabler-progress-check', label: t('common.crmDetails.fields.stage'), value: item.stage },
    { key: 'probability', icon: 'tabler-percentage', label: t('common.crmDetails.fields.probability'), value: item.probability == null ? null : `${item.probability}%` },
    { key: 'estimated_value', icon: 'tabler-cash', label: t('common.crmDetails.fields.estimatedValue'), value: item.estimated_value },
    { key: 'next_follow_up_at', icon: 'tabler-calendar-event', label: t('common.crmDetails.fields.nextFollowUp'), value: item.next_follow_up_at ? formatDateTime(item.next_follow_up_at) : null },
    { key: 'tax_number', icon: 'tabler-receipt-tax', label: t('common.crmDetails.fields.taxNumber'), value: item.tax_number },
    { key: 'date', icon: 'tabler-calendar', label: t('common.crmDetails.fields.since'), value: item.date ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(new Date(item.date)) : null },
  ].filter(detail => detail.value !== null && detail.value !== undefined && detail.value !== '')
})

const formatDateTime = (value: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
const pickerDate = (value: string) => value.slice(0, 16).replace('T', ' ')

const load = async () => {
  loading.value = true
  try {
    record.value = (await api.get(`/${endpoint.value}`)).data.data
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
    const savedNotes = (await api.patch(`/crm/${props.type}/${props.id}/notes`, { notes: notesDraft.value.trim() || null })).data.data.notes as string | null
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
  editingInteractionId.value = interaction?.id ?? null
  form.value = interaction
    ? {
        channel: interaction.channel,
        title: interaction.title,
        notes: interaction.notes,
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
  if (!form.value.title.trim() || !form.value.notes.trim())
    return

  saving.value = true
  try {
    const payload = { ...form.value, title: form.value.title.trim(), notes: form.value.notes.trim() }
    if (editingInteractionId.value)
      await api.put(`/crm/interactions/${editingInteractionId.value}`, payload)
    else
      await api.post(`/crm/${props.type}/${props.id}/interactions`, payload)

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
  if (!window.confirm(t('common.crmDetails.deleteConfirm', { title: interaction.title })))
    return

  try {
    await api.delete(`/crm/interactions/${interaction.id}`)
    await load()
    toast.success(t('common.crmDetails.conversationRemoved'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error, t('common.crmDetails.conversationDeleteFailed')))
  }
}
</script>

<template>
  <VBtn
    icon="tabler-address-book"
    variant="text"
    size="small"
    color="primary"
    :aria-label="$t('common.crmDetails.relationshipDetails')"
    @click.stop="show"
  >
    <VIcon icon="tabler-address-book" />
    <VTooltip
      activator="parent"
      location="top"
    >
      {{ $t('common.crmDetails.relationshipDetails') }}
    </VTooltip>
  </VBtn>

  <VDialog
    v-model="open"
    max-width="980"
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
            <div class="text-body-2 text-medium-emphasis">
              {{ record?.company }}
            </div>
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
            variant="text"
            :aria-label="$t('common.actions.close')"
            @click="open = false"
          />
        </div>
        <div class="d-flex flex-wrap ga-2 mt-5">
          <VChip
            v-if="record?.contact"
            prepend-icon="tabler-phone"
            variant="outlined"
          >
            {{ record.contact }}
          </VChip>
          <VChip
            v-if="record?.email"
            prepend-icon="tabler-mail"
            variant="outlined"
          >
            {{ record.email }}
          </VChip>
          <VChip
            v-if="record?.service || record?.service_interest"
            prepend-icon="tabler-briefcase"
            variant="outlined"
          >
            {{ record.service || record.service_interest }}
          </VChip>
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
        <VSkeletonLoader
          v-if="loading"
          type="article, list-item-three-line, list-item-three-line"
        />
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
                :prepend-icon="type === 'customer' ? 'tabler-user-check' : type === 'lead' ? 'tabler-target-arrow' : 'tabler-walk'"
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
                  /><span class="font-weight-bold">{{ $t('common.crmDetails.customerServices') }}</span>
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
                  variant="tonal"
                  color="primary"
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
                      @click="startInteraction"
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
                @click="startInteraction"
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
                          @click="startInteraction(item)"
                        />
                        <VListItem
                          prepend-icon="tabler-trash"
                          :title="$t('common.actions.delete')"
                          class="text-error"
                          @click="removeInteraction(item)"
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
  >
    <VCard :title="$t(editingInteractionId ? 'common.crmDetails.editConversation' : 'common.crmDetails.addConversation')">
      <VCardText>
        <VSelect
          v-model="form.channel"
          :items="channels"
          item-title="label"
          item-value="value"
          :label="$t('common.crmDetails.category')"
          class="mb-3"
        />
        <VTextField
          v-model="form.title"
          :label="$t('common.crmUi.conversationTitle')"
          class="mb-3"
          autofocus
        />
        <VTextarea
          v-model="form.notes"
          :label="$t('common.crmDetails.notesOutcome')"
          rows="5"
          class="mb-3"
        />
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.contacted_at"
              :label="$t('common.crmDetails.contactedAt')"
              :config="{ dateFormat: 'Y-m-d' }"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.follow_up_at"
              :label="$t('common.crmUi.followUp')"
              clearable
              :config="{ enableTime: true, dateFormat: 'Y-m-d H:i' }"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="interactionDialog = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          :loading="saving"
          :disabled="!form.title.trim() || !form.notes.trim()"
          @click="saveInteraction"
        >
          {{ $t('common.crmDetails.saveConversation') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

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
.interaction-card { transition: border-color .18s ease, transform .18s ease; }
.interaction-card:hover { border-color: rgba(var(--v-theme-primary), .45) !important; transform: translateY(-1px); }
.min-w-0 { min-width: 0; }

@media (max-height: 650px) {
  .relationship-dialog { max-block-size: calc(100dvh - 16px); }
  .relationship-hero { padding-block: 1rem !important; }
}
</style>
