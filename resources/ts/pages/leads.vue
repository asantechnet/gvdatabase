<script setup lang="ts">
import { useToast } from 'vue-toastification'
import LeadApi, { type CreateLeadData, type Lead, type LeadStage } from '@/api/lead'
import EmployeeApi, { type Employee } from '@/api/employee'
import type { Account } from '@/api/auth'
import { useCookie } from '@core/composable/useCookie'
import ContactActionMenu from '@/components/ContactActionMenu.vue'

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
const stageColor = (value: LeadStage) => ({ new: 'info', contacted: 'primary', qualified: 'success', proposal: 'warning', won: 'success', lost: 'error' }[value])
const isCurrentUserMarketer = computed(() => Boolean(userData.value?.is_marketer || userData.value?.employee?.is_marketer))

const currentMarketerName = computed(() => {
  const employeeId = userData.value?.employee?.id

  return marketers.value.find(marketer => marketer.id === employeeId)?.name ?? userData.value?.name ?? 'your marketer profile'
})

const emptyLeadForm = (): LeadForm => ({ name: '', company: '', contact: '', email: '', service_interest: '', notes: '', marketer_id: null })
const leadForm = ref<LeadForm>(emptyLeadForm())
const marketerLabel = (lead: Pick<Lead, 'marketer' | 'marketer_employee'>) => lead.marketer_employee?.name || lead.marketer || 'Unassigned'

const load = async () => {
  loading.value = true
  try { leads.value = (await LeadApi.list({ search: search.value || undefined, stage: stage.value, marketer_id: marketerId.value || undefined })).data }
  finally { loading.value = false }
}

const updateStage = async (lead: Lead, value: LeadStage) => { Object.assign(lead, await LeadApi.update(lead.id, { stage: value })); toast.success('Lead stage updated') }

const updateMarketer = async (lead: Lead, value: number | null) => {
  if (isCurrentUserMarketer.value)
    return

  try {
    Object.assign(lead, await LeadApi.update(lead.id, { marketer_id: value ?? null }))
    toast.success('Lead marketer updated')
  }
  catch {
    toast.error('Unable to update the lead marketer')
  }
}

const convert = async (lead: Lead) => { await LeadApi.convert(lead.id); await load(); selected.value = null; toast.success('Lead converted to customer') }

const openCreate = () => {
  leadForm.value = emptyLeadForm()
  createDialog.value = true
}

const createLead = async () => {
  if (!leadForm.value.name.trim() || !leadForm.value.contact.trim()) {
    toast.error('Name and contact are required to create a lead')

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
    toast.success('Lead created')
  }
  catch {
    toast.error('Unable to create the lead')
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
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-5">
      <div>
        <h1 class="text-h4 font-weight-bold">
          Leads
        </h1><p class="text-body-2 text-medium-emphasis mb-0">
          Move qualified opportunities toward a customer relationship.
        </p>
      </div>
      <VChip
        color="primary"
        variant="tonal"
        prepend-icon="tabler-funnel"
      >
        {{ leads.length }} opportunities
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
          label="Search leads"
          prepend-inner-icon="tabler-search"
          clearable
          hide-details
          style="max-width: 360px"
          @keyup.enter="load"
        />
        <VSelect
          v-model="stage"
          :items="stages"
          label="Pipeline stage"
          clearable
          hide-details
          style="max-width: 220px"
        />
        <VSelect
          v-model="marketerId"
          :items="marketers"
          item-title="name"
          item-value="id"
          label="Marketer"
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
          New lead
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
                  {{ lead.company || 'Independent' }}
                </div>
              </div><div class="d-flex align-center">
                <VChip
                  :color="stageColor(lead.stage)"
                  size="small"
                  variant="tonal"
                >
                  {{ lead.stage }}
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
              </VChip><VSpacer /><span class="text-caption">{{ lead.probability }}% confidence</span>
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
            <span class="text-caption text-medium-emphasis">Lead workspace</span>
            <VSpacer />
            <VBtn
              color="primary"
              variant="text"
              append-icon="tabler-arrow-right"
              @click="selected = lead"
            >
              View lead
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
          <VCardTitle>Create a lead</VCardTitle>
          <VCardSubtitle>Capture the opportunity and credit the relationship owner from the start.</VCardSubtitle>
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
                  label="Marketer"
                  prepend-inner-icon="tabler-user-star"
                  hint="Optional. Select the employee who persuaded this prospect to engage."
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
    <VNavigationDrawer
      v-model="isDetailsOpen"
      location="end"
      temporary
      width="460"
    >
      <template v-if="selected">
        <div class="pa-5">
          <div class="d-flex align-center">
            <div>
              <h2>{{ selected.name }}</h2><p class="text-medium-emphasis">
                {{ selected.company }}
              </p>
            </div><VSpacer /><VBtn
              icon="tabler-x"
              variant="text"
              @click="selected = null"
            />
          </div>
          <div class="d-flex flex-wrap ga-2 my-4">
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
          <VSelect
            :model-value="selected.stage"
            :items="stages"
            label="Pipeline stage"
            @update:model-value="updateStage(selected, $event)"
          />
          <VAlert
            v-if="isCurrentUserMarketer"
            class="mb-4"
            color="primary"
            variant="tonal"
            icon="tabler-lock"
          >
            Marketer attribution is locked to your marketer profile. The current relationship owner is {{ marketerLabel(selected) }}.
          </VAlert>
          <VAutocomplete
            v-else
            :model-value="selected.marketer_id"
            :items="marketers"
            item-title="name"
            item-value="id"
            label="Marketer"
            hint="Optional. This employee is credited with bringing in the lead."
            persistent-hint
            clearable
            class="mb-4"
            @update:model-value="updateMarketer(selected, $event)"
          />
          <VAlert
            v-if="selected.notes"
            color="info"
            variant="tonal"
            title="Relationship notes"
            class="mb-5"
          >
            {{ selected.notes }}
          </VAlert>
          <div class="text-subtitle-1 font-weight-bold mb-3">
            Conversation history
          </div>
          <VTimeline
            side="end"
            density="compact"
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
          <VBtn
            v-if="selected.stage !== 'won'"
            block
            color="success"
            prepend-icon="tabler-user-check"
            class="mt-5"
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

@media (hover: none) {
  .lead-card:hover {
    box-shadow: none;
    transform: none;
  }
}
</style>
