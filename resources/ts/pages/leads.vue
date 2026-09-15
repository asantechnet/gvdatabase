<script setup lang="ts">
import { useToast } from 'vue-toastification'
import LeadApi, { type CreateLeadData, type Lead, type LeadStage } from '@/api/lead'
import EmployeeApi, { type Employee } from '@/api/employee'
import type { Account } from '@/api/auth'
import { useCookie } from '@core/composable/useCookie'
import ContactActionMenu from '@/components/ContactActionMenu.vue'

const { t } = useI18n()

interface LeadForm {
  name: string
  company: string
  contact: string
  email: string
  service_interest: string
  notes: string
  marketer_id: number | null
}

const toast = useToast()
const leads = ref<Lead[]>([])
const loading = ref(false)
const search = ref('')
const stage = ref<LeadStage | null>(null)
const marketerId = ref<number | null>(null)
const selected = ref<Lead | null>(null)
const confidenceDraft = ref<number | null>(null)
const savingConfidence = ref(false)
const conversionDialog = ref(false)
const leadAwaitingConversion = ref<Lead | null>(null)
const conversionEmail = ref('')
const conversionEmailError = ref('')
const converting = ref(false)

const isDetailsOpen = computed({
  get: () => selected.value !== null,
  set: open => {
    if (!open)
      selected.value = null
  },
})

const createDialog = ref(false)
const creating = ref(false)
const marketers = ref<Employee[]>([])
const userData = useCookie<Account | null>('userData')
const stages: LeadStage[] = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost']
const stageItems = computed(() => stages.map(value => ({ title: t(`common.crmUi.stageLabels.${value}`), value })))
const stageColor = (value: LeadStage) => ({ new: 'info', contacted: 'primary', qualified: 'success', proposal: 'warning', won: 'success', lost: 'error' }[value])
const stageLabel = (value: LeadStage) => t(`common.crmUi.stageLabels.${value}`)
const isCurrentUserMarketer = computed(() => Boolean(userData.value?.is_marketer || userData.value?.employee?.is_marketer))

const currentMarketerName = computed(() => {
  const employeeId = userData.value?.employee?.id

  return marketers.value.find(marketer => marketer.id === employeeId)?.name ?? userData.value?.name ?? 'your marketer profile'
})

const emptyLeadForm = (): LeadForm => ({ name: '', company: '', contact: '', email: '', service_interest: '', notes: '', marketer_id: null })
const leadForm = ref<LeadForm>(emptyLeadForm())
const marketerLabel = (lead: Pick<Lead, 'marketer' | 'marketer_employee'>) => lead.marketer_employee?.name || lead.marketer || t('common.crmUi.unassigned')

const load = async () => {
  loading.value = true
  try { leads.value = (await LeadApi.list({ search: search.value || undefined, stage: stage.value, marketer_id: marketerId.value || undefined })).data }
  finally { loading.value = false }
}

const updateStage = async (lead: Lead, value: LeadStage) => { Object.assign(lead, await LeadApi.update(lead.id, { stage: value })); confidenceDraft.value = lead.probability; toast.success(t('common.crmUi.stageUpdated')) }

const saveConfidence = async (lead: Lead) => {
  const confidence = Number(confidenceDraft.value)
  if (!Number.isInteger(confidence) || confidence < 0 || confidence > 100) {
    toast.error(t('common.crmUi.confidenceRangeError'))

    return
  }

  savingConfidence.value = true
  try {
    Object.assign(lead, await LeadApi.update(lead.id, { probability: confidence }))
    confidenceDraft.value = lead.probability
    toast.success(t('common.crmUi.manualConfidenceSaved'))
  }
  catch {
    toast.error(t('common.crmUi.confidenceSaveFailed'))
  }
  finally {
    savingConfidence.value = false
  }
}

const usePipelineConfidence = async (lead: Lead) => {
  savingConfidence.value = true
  try {
    Object.assign(lead, await LeadApi.update(lead.id, { probability_is_manual: false }))
    confidenceDraft.value = lead.probability
    toast.success(t('common.crmUi.pipelineConfidenceRestored'))
  }
  catch {
    toast.error(t('common.crmUi.pipelineConfidenceRestoreFailed'))
  }
  finally {
    savingConfidence.value = false
  }
}

const updateMarketer = async (lead: Lead, value: number | null) => {
  if (isCurrentUserMarketer.value)
    return

  try {
    Object.assign(lead, await LeadApi.update(lead.id, { marketer_id: value ?? null }))
    toast.success(t('common.crmUi.marketerUpdated'))
  }
  catch {
    toast.error(t('common.crmUi.marketerUpdateFailed'))
  }
}

const hasValidEmail = (email: string | null | undefined) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email?.trim() ?? '')

const finishConversion = async (lead: Lead, email?: string) => {
  converting.value = true
  try {
    await LeadApi.convert(lead.id, email ? { email } : undefined)
    await load()
    selected.value = null
    conversionDialog.value = false
    leadAwaitingConversion.value = null
    toast.success(t('common.crmUi.convertedCustomer'))
  }
  catch {
    toast.error(t('common.crmUi.convertFailed'))
  }
  finally {
    converting.value = false
  }
}

const convert = async (lead: Lead) => {
  if (hasValidEmail(lead.email)) {
    await finishConversion(lead)

    return
  }

  leadAwaitingConversion.value = lead
  conversionEmail.value = lead.email?.trim() ?? ''
  conversionEmailError.value = ''
  conversionDialog.value = true
}

const confirmConversionEmail = async () => {
  const email = conversionEmail.value.trim()
  if (!hasValidEmail(email)) {
    conversionEmailError.value = t('common.crmUi.conversionEmailInvalid')

    return
  }

  conversionEmailError.value = ''
  if (leadAwaitingConversion.value)
    await finishConversion(leadAwaitingConversion.value, email)
}

const openCreate = () => {
  leadForm.value = emptyLeadForm()
  createDialog.value = true
}

const createLead = async () => {
  if (!leadForm.value.name.trim() || !leadForm.value.contact.trim()) {
    toast.error(t('common.crmUi.requiredLeadFields'))

    return
  }

  creating.value = true
  try {
    const data: CreateLeadData = {
      name: leadForm.value.name.trim(),
      company: leadForm.value.company.trim() || null,
      contact: leadForm.value.contact.trim(),
      email: leadForm.value.email.trim() || null,
      service_interest: leadForm.value.service_interest.trim() || null,
      notes: leadForm.value.notes.trim() || null,
      ...(isCurrentUserMarketer.value ? {} : { marketer_id: leadForm.value.marketer_id }),
    }

    const lead = await LeadApi.create(data)

    leads.value.unshift(lead)
    createDialog.value = false
    leadForm.value = emptyLeadForm()
    toast.success(t('common.crmUi.leadCreated'))
  }
  catch {
    toast.error(t('common.crmUi.leadCreateFailed'))
  }
  finally {
    creating.value = false
  }
}

onMounted(async () => {
  await Promise.all([
    load(),
    EmployeeApi.getMarketerOptions().then(data => { marketers.value = data }),
  ])
})
watch([stage, marketerId], load)
watch(selected, lead => { confidenceDraft.value = lead?.probability ?? null })
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-5">
      <div>
        <h1 class="text-h4 font-weight-bold">
          {{ t('common.crmUi.leads') }}
        </h1><p class="text-body-2 text-medium-emphasis mb-0">
          {{ t('common.crmUi.leadSubtitle') }}
        </p>
      </div>
      <VChip
        color="primary"
        variant="tonal"
        prepend-icon="tabler-target-arrow"
      >
        {{ t('common.crmUi.opportunities', { count: leads.length }) }}
      </VChip>
    </div>
    <VCard
      class="mb-5"
      variant="flat"
      border
    >
      <VCardText class="d-flex flex-wrap align-center gap-3">
        <VTextField
          v-model="search"
          :label="t('common.crmUi.searchLeads')"
          prepend-inner-icon="tabler-search"
          clearable
          hide-details
          style="max-width: 360px"
          @keyup.enter="load"
        />
        <VSelect
          v-model="stage"
          :items="stageItems"
          :label="t('common.crmUi.pipelineStage')"
          clearable
          hide-details
          style="max-width: 220px"
        />
        <VSelect
          v-model="marketerId"
          :items="marketers"
          item-title="name"
          item-value="id"
          :label="t('common.crmUi.marketer')"
          clearable
          hide-details
          style="max-width: 220px"
        />
        <VSpacer />
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          {{ t('common.crmUi.newLead') }}
        </VBtn>
      </VCardText>
    </VCard>
    <VRow>
      <VCol
        v-for="lead in leads"
        :key="lead.id"
        cols="12"
        md="6"
        xl="4"
      >
        <VCard
          border
          class="lead-card"
        >
          <VCardText>
            <div class="d-flex justify-space-between align-start">
              <div>
                <div class="text-h6">
                  {{ lead.name }}
                </div><div class="text-body-2 text-medium-emphasis">
                  {{ lead.company || t('common.crmUi.independent') }}
                </div>
              </div><div class="d-flex align-center">
                <VChip
                  :color="stageColor(lead.stage)"
                  size="small"
                  variant="tonal"
                >
                  {{ stageLabel(lead.stage) }}
                </VChip><CrmRelationshipDetails
                  :id="lead.id"
                  type="lead"
                  :title="lead.name"
                />
              </div>
            </div>
            <div class="d-flex flex-wrap align-center gap-2 mt-4">
              <ContactActionMenu
                kind="phone"
                :value="lead.contact"
                contactable-type="lead"
                :contactable-id="lead.id"
              /><ContactActionMenu
                v-if="lead.email"
                kind="email"
                :value="lead.email"
                contactable-type="lead"
                :contactable-id="lead.id"
              /><VChip
                size="x-small"
                variant="tonal"
                prepend-icon="tabler-user-star"
              >
                {{ marketerLabel(lead) }}
              </VChip><VSpacer /><span class="text-caption">{{ t('common.crmUi.confidence', { value: lead.probability }) }}</span>
            </div>
            <VProgressLinear
              :model-value="lead.probability"
              :color="stageColor(lead.stage)"
              rounded
              class="mt-2"
            />
            <p
              v-if="lead.notes"
              class="text-body-2 mt-4 mb-0 text-truncate"
            >
              {{ lead.notes }}
            </p>
          </VCardText>
          <VDivider />
          <VCardActions class="px-4 py-3">
            <span class="text-caption text-medium-emphasis">{{ t('common.crmUi.leadWorkspace') }}</span>
            <VSpacer />
            <VBtn
              color="primary"
              variant="text"
              append-icon="tabler-arrow-right"
              @click="selected = lead"
            >
              {{ t('common.crmUi.viewLead') }}
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>
    <VDialog
      v-model="createDialog"
      max-width="720"
    >
      <VCard>
        <VCardItem>
          <template #prepend>
            <VAvatar
              color="primary"
              variant="tonal"
            >
              <VIcon icon="tabler-target-arrow" />
            </VAvatar>
          </template>
          <VCardTitle>{{ t('common.crmUi.createLead') }}</VCardTitle>
          <VCardSubtitle>{{ t('common.crmUi.createLeadSubtitle') }}</VCardSubtitle>
        </VCardItem>
        <VDivider />
        <VCardText class="pt-5">
          <VForm @submit.prevent="createLead">
            <VRow>
              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model="leadForm.name"
                  label="Lead name"
                  prepend-inner-icon="tabler-user"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model="leadForm.company"
                  label="Company"
                  prepend-inner-icon="tabler-building"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model="leadForm.contact"
                  label="Phone or contact"
                  prepend-inner-icon="tabler-phone"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model="leadForm.email"
                  label="Email"
                  type="email"
                  prepend-inner-icon="tabler-mail"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="leadForm.service_interest"
                  label="Service interest"
                  prepend-inner-icon="tabler-briefcase"
                  hint="Optional — this can be refined as the opportunity qualifies."
                  persistent-hint
                />
              </VCol>
              <VCol cols="12">
                <VAlert
                  v-if="isCurrentUserMarketer"
                  color="primary"
                  variant="tonal"
                  icon="tabler-lock"
                >
                  Attribution is assigned automatically to {{ currentMarketerName }}. You cannot choose another marketer for a lead you create.
                </VAlert>
                <VAutocomplete
                  v-else
                  v-model="leadForm.marketer_id"
                  :items="marketers"
                  item-title="name"
                  item-value="id"
                  :label="t('common.crmUi.marketer')"
                  prepend-inner-icon="tabler-user-star"
                  :hint="t('common.crmUi.createMarketerHint')"
                  persistent-hint
                  clearable
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="leadForm.notes"
                  label="Context for the team"
                  rows="3"
                  hint="Record the need, origin, or next useful action."
                  persistent-hint
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions class="px-6 pb-5">
          <VSpacer />
          <VBtn
            variant="text"
            :disabled="creating"
            @click="createDialog = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            :loading="creating"
            @click="createLead"
          >
            Create lead
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
    <VDialog
      v-model="conversionDialog"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardItem>
          <template #prepend>
            <VAvatar
              color="warning"
              variant="tonal"
            >
              <VIcon icon="tabler-mail-exclamation" />
            </VAvatar>
          </template>
          <VCardTitle>Customer email needed</VCardTitle>
          <VCardSubtitle>
            A valid email keeps the new customer record ready for invoices, updates, and future communication.
          </VCardSubtitle>
        </VCardItem>
        <VDivider />
        <VCardText class="pt-5">
          <VAlert
            color="warning"
            variant="tonal"
            icon="tabler-info-circle"
            class="mb-4"
          >
            This email will be saved to {{ leadAwaitingConversion?.name || 'the lead' }} and copied to the new customer.
          </VAlert>
          <VForm @submit.prevent="confirmConversionEmail">
            <VTextField
              v-model="conversionEmail"
              label="Customer email"
              type="email"
              autocomplete="email"
              autofocus
              prepend-inner-icon="tabler-mail"
              :error-messages="conversionEmailError"
              :disabled="converting"
              @update:model-value="conversionEmailError = ''"
            />
          </VForm>
        </VCardText>
        <VCardActions class="px-6 pb-5">
          <VSpacer />
          <VBtn
            variant="text"
            :disabled="converting"
            @click="conversionDialog = false"
          >
            Keep as lead
          </VBtn>
          <VBtn
            color="success"
            prepend-icon="tabler-user-check"
            :loading="converting"
            @click="confirmConversionEmail"
          >
            Save email & convert
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
    <VNavigationDrawer
      v-model="isDetailsOpen"
      location="end"
      temporary
      width="460"
      class="lead-details-drawer"
    >
      <template v-if="selected">
        <div class="lead-details-drawer__content">
          <div class="lead-details-drawer__header">
            <div class="lead-details-drawer__identity">
              <VAvatar
                color="primary"
                variant="tonal"
                size="48"
                class="lead-details-drawer__avatar"
              >
                {{ selected.name.charAt(0).toUpperCase() }}
              </VAvatar>
              <div>
                <h2 class="text-h5 font-weight-bold mb-1">
                  {{ selected.name }}
                </h2><p class="text-body-2 text-medium-emphasis mb-0">
                  {{ selected.company || 'Independent lead' }}
                </p>
              </div>
            </div><VBtn
              icon="tabler-x"
              variant="text"
              density="comfortable"
              aria-label="Close lead details"
              @click="selected = null"
            />
          </div>

          <div class="lead-details-drawer__contacts">
            <ContactActionMenu
              kind="phone"
              :value="selected.contact"
              contactable-type="lead"
              :contactable-id="selected.id"
            /><ContactActionMenu
              v-if="selected.email"
              kind="email"
              :value="selected.email"
              contactable-type="lead"
              :contactable-id="selected.id"
            />
          </div>

          <section class="lead-details-drawer__section lead-details-drawer__fields">
            <div class="lead-details-drawer__field-group">
            <div class="lead-details-drawer__field-heading">
              <span>{{ t('common.crmUi.pipeline') }}</span>
              <VChip
                :color="stageColor(selected.stage)"
                size="x-small"
                variant="tonal"
              >
                {{ stageLabel(selected.stage) }}
              </VChip>
            </div>
            <VSelect
              :model-value="selected.stage"
              :items="stageItems"
              :aria-label="t('common.crmUi.pipelineStage')"
              hide-details
              @update:model-value="updateStage(selected, $event)"
            />
            </div>
            <div class="lead-details-drawer__confidence">
              <div class="lead-details-drawer__field-heading">
                <span>{{ t('common.crmUi.confidenceLabel') }}</span>
                <VChip
                  :color="selected.probability_is_manual ? 'primary' : 'secondary'"
                  size="x-small"
                  variant="tonal"
                  :prepend-icon="selected.probability_is_manual ? 'tabler-edit' : 'tabler-chart-line'"
                >
                  {{ selected.probability_is_manual ? t('common.crmUi.manualOverride') : t('common.crmUi.pipelineScore') }}
                </VChip>
              </div>
              <div class="d-flex align-start gap-2">
                <VTextField
                  v-model.number="confidenceDraft"
                  type="number"
                  min="0"
                  max="100"
                  step="1"
                  suffix="%"
                  :aria-label="t('common.crmUi.confidenceLabel')"
                  hide-details
                  class="flex-grow-1"
                  :disabled="selected.stage === 'won' || selected.stage === 'lost' || savingConfidence"
                  @keyup.enter="saveConfidence(selected)"
                />
                <VBtn
                  color="primary"
                  variant="tonal"
                  icon="tabler-device-floppy"
                  :aria-label="t('common.crmUi.saveConfidence')"
                  class="lead-details-drawer__confidence-save"
                  :loading="savingConfidence"
                  :disabled="selected.stage === 'won' || selected.stage === 'lost'"
                  @click="saveConfidence(selected)"
                />
              </div>
              <div class="d-flex align-center justify-space-between gap-2 mt-2">
                <span class="text-caption text-medium-emphasis">
                  {{ selected.stage === 'won' || selected.stage === 'lost' ? t('common.crmUi.closedConfidenceHint') : selected.probability_is_manual ? t('common.crmUi.manualConfidenceHint') : t('common.crmUi.pipelineConfidenceHint') }}
                </span>
                <VBtn
                  v-if="selected.probability_is_manual && selected.stage !== 'won' && selected.stage !== 'lost'"
                  size="small"
                  variant="text"
                  :loading="savingConfidence"
                  @click="usePipelineConfidence(selected)"
                >
                  {{ t('common.crmUi.usePipelineScore') }}
                </VBtn>
              </div>
            </div>
          <VAlert
            v-if="isCurrentUserMarketer"
            color="primary"
            variant="tonal"
            icon="tabler-lock"
            density="compact"
          >
            {{ t('common.crmUi.marketerLocked', { name: marketerLabel(selected) }) }}
          </VAlert>
          <VAutocomplete
            v-else
            :model-value="selected.marketer_id"
            :items="marketers"
            item-title="name"
            item-value="id"
            :label="t('common.crmUi.marketer')"
            :hint="t('common.crmUi.marketerHint')"
            persistent-hint
            clearable
            @update:model-value="updateMarketer(selected, $event)"
          />
          </section>

          <VAlert
            v-if="selected.notes"
            color="info"
            variant="tonal"
            icon="tabler-notes"
            class="lead-details-drawer__notes"
          >
            <template #title>
              Relationship notes
            </template>
            {{ selected.notes }}
          </VAlert>

          <div class="lead-details-drawer__section-title">
            Conversation history
          </div>
          <VTimeline
            v-if="selected.interactions.length"
            side="end"
            density="compact"
            class="lead-details-drawer__timeline"
          >
            <VTimelineItem
              v-for="item in selected.interactions"
              :key="item.id"
              dot-color="primary"
              size="small"
            >
              <div class="font-weight-medium">
                {{ item.title }}
              </div><div class="text-caption text-medium-emphasis">
                {{ item.channel }} · {{ new Date(item.contacted_at).toLocaleString() }}
              </div><p
                class="text-body-2 mt-1"
                style="white-space: pre-wrap"
              >
                {{ item.notes }}
              </p>
            </VTimelineItem>
          </VTimeline>
          <div
            v-else
            class="lead-details-drawer__empty-history"
          >
            <VIcon icon="tabler-message-circle" size="20" />
            No conversations logged yet.
          </div>
        </div>

        <div class="lead-details-drawer__footer">
          <VBtn
            v-if="selected.stage !== 'won'"
            block
            color="success"
            prepend-icon="tabler-user-check"
            @click="convert(selected)"
          >
            Convert to customer
          </VBtn>
        </div>
      </template>
    </VNavigationDrawer>
  </div>
</template>

<style scoped>
.lead-card {
  block-size: 100%;
  transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}

.lead-card:hover {
  border-color: rgba(var(--v-theme-primary), .4) !important;
  box-shadow: 0 8px 24px rgba(var(--v-theme-on-surface), .08);
  transform: translateY(-2px);
}

.lead-details-drawer :deep(.v-navigation-drawer__content) {
  display: flex;
  overflow: hidden;
  flex-direction: column;
  background:
    radial-gradient(circle at 100% 0, rgba(var(--v-theme-primary), .11), transparent 30%),
    rgb(var(--v-theme-surface));
}

.lead-details-drawer__content {
  overflow-y: auto;
  padding: 1.5rem 1.25rem;
  scrollbar-width: none;
}

.lead-details-drawer__content::-webkit-scrollbar {
  display: none;
}

.lead-details-drawer__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid rgba(var(--v-theme-primary), .18);
  border-radius: .875rem;
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), .12), rgba(var(--v-theme-surface), .4));
}

.lead-details-drawer__identity {
  display: flex;
  min-inline-size: 0;
  align-items: center;
  gap: .75rem;
}

.lead-details-drawer__avatar {
  flex: 0 0 auto;
  font-weight: 700;
}

.lead-details-drawer__contacts {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  margin-block: 1.25rem;
}

.lead-details-drawer__section {
  display: grid;
  gap: .75rem;
}

.lead-details-drawer__fields {
  padding: 1rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: .75rem;
  background: rgba(var(--v-theme-on-surface), .025);
  gap: 1.125rem;
}

.lead-details-drawer__field-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  min-block-size: 1.5rem;
  font-size: .8125rem;
  font-weight: 700;
  letter-spacing: .01em;
}

.lead-details-drawer__field-group,
.lead-details-drawer__confidence {
  display: grid;
  gap: .5rem;
}

.lead-details-drawer__field-heading :deep(.v-chip) {
  flex: 0 0 auto;
  max-inline-size: 58%;
}

.lead-details-drawer__confidence-save {
  flex: 0 0 40px;
  min-inline-size: 40px;
  block-size: 40px;
}

.lead-details-drawer__notes {
  margin-block: 1.25rem;
  white-space: pre-wrap;
}

.lead-details-drawer__section-title {
  margin-block-end: .75rem;
  color: rgb(var(--v-theme-on-surface));
  font-size: .9375rem;
  font-weight: 700;
}

.lead-details-drawer__timeline {
  padding-inline-start: .25rem;
}

.lead-details-drawer__empty-history {
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: 1rem;
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: .5rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: .875rem;
}

.lead-details-drawer__footer {
  padding: 1rem 1.25rem;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: linear-gradient(to bottom, rgba(var(--v-theme-surface), .88), rgb(var(--v-theme-surface)) 45%);
  backdrop-filter: blur(8px);
}

@media (hover: none) {
  .lead-card:hover {
    box-shadow: none;
    transform: none;
  }
}
</style>
