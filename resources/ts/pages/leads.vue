<script setup lang="ts">
import LeadApi, { type Lead, type LeadStage } from '@/api/lead'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import { useToast } from 'vue-toastification'

const toast = useToast()
const leads = ref<Lead[]>([])
const loading = ref(false)
const search = ref('')
const stage = ref<LeadStage | null>(null)
const selected = ref<Lead | null>(null)
const stages: LeadStage[] = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost']
const stageColor = (value: LeadStage) => ({ new: 'info', contacted: 'primary', qualified: 'success', proposal: 'warning', won: 'success', lost: 'error' }[value])

const load = async () => {
  loading.value = true
  try { leads.value = (await LeadApi.list({ search: search.value || undefined, stage: stage.value })).data }
  finally { loading.value = false }
}
const updateStage = async (lead: Lead, value: LeadStage) => { Object.assign(lead, await LeadApi.update(lead.id, { stage: value })); toast.success('Lead stage updated') }
const convert = async (lead: Lead) => { await LeadApi.convert(lead.id); await load(); selected.value = null; toast.success('Lead converted to customer') }
onMounted(load)
watch(stage, load)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-5">
      <div><h1 class="text-h4 font-weight-bold">Leads</h1><p class="text-body-2 text-medium-emphasis mb-0">Move qualified opportunities toward a customer relationship.</p></div>
      <VChip color="primary" variant="tonal" prepend-icon="tabler-funnel">{{ leads.length }} opportunities</VChip>
    </div>
    <VCard class="mb-5" variant="flat" border><VCardText class="d-flex flex-wrap gap-3">
      <VTextField v-model="search" label="Search leads" prepend-inner-icon="tabler-search" clearable hide-details style="max-width: 360px" @keyup.enter="load" />
      <VSelect v-model="stage" :items="stages" label="Pipeline stage" clearable hide-details style="max-width: 220px" />
    </VCardText></VCard>
    <VRow>
      <VCol v-for="lead in leads" :key="lead.id" cols="12" md="6" xl="4">
        <VCard border class="lead-card">
          <VCardText><div class="d-flex justify-space-between align-start"><div><div class="text-h6">{{ lead.name }}</div><div class="text-body-2 text-medium-emphasis">{{ lead.company || 'Independent' }}</div></div><div class="d-flex align-center"><VChip :color="stageColor(lead.stage)" size="small" variant="tonal">{{ lead.stage }}</VChip><CrmRelationshipDetails type="lead" :id="lead.id" :title="lead.name" /></div></div>
            <div class="d-flex flex-wrap align-center gap-2 mt-4"><ContactActionMenu kind="phone" :value="lead.contact" contactable-type="lead" :contactable-id="lead.id" /><ContactActionMenu v-if="lead.email" kind="email" :value="lead.email" contactable-type="lead" :contactable-id="lead.id" /><VSpacer /><span class="text-caption">{{ lead.probability }}% confidence</span></div>
            <VProgressLinear :model-value="lead.probability" :color="stageColor(lead.stage)" rounded class="mt-2" />
            <p v-if="lead.notes" class="text-body-2 mt-4 mb-0 text-truncate">{{ lead.notes }}</p>
          </VCardText>
          <VDivider />
          <VCardActions class="px-4 py-3">
            <span class="text-caption text-medium-emphasis">Lead workspace</span>
            <VSpacer />
            <VBtn color="primary" variant="text" append-icon="tabler-arrow-right" @click="selected = lead">
              View lead
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>
    <VNavigationDrawer v-model="selected" location="end" temporary width="460">
      <template v-if="selected"><div class="pa-5"><div class="d-flex align-center"><div><h2>{{ selected.name }}</h2><p class="text-medium-emphasis">{{ selected.company }}</p></div><VSpacer /><VBtn icon="tabler-x" variant="text" @click="selected = null" /></div>
        <div class="d-flex flex-wrap ga-2 my-4"><ContactActionMenu kind="phone" :value="selected.contact" contactable-type="lead" :contactable-id="selected.id" /><ContactActionMenu v-if="selected.email" kind="email" :value="selected.email" contactable-type="lead" :contactable-id="selected.id" /></div>
        <VSelect :model-value="selected.stage" :items="stages" label="Pipeline stage" @update:model-value="updateStage(selected, $event)" />
        <VAlert v-if="selected.notes" color="info" variant="tonal" title="Relationship notes" class="mb-5">{{ selected.notes }}</VAlert>
        <div class="text-subtitle-1 font-weight-bold mb-3">Conversation history</div>
        <VTimeline side="end" density="compact"><VTimelineItem v-for="item in selected.interactions" :key="item.id" dot-color="primary" size="small"><div class="font-weight-medium">{{ item.title }}</div><div class="text-caption text-medium-emphasis">{{ item.channel }} · {{ new Date(item.contacted_at).toLocaleString() }}</div><p class="text-body-2 mt-1" style="white-space: pre-wrap">{{ item.notes }}</p></VTimelineItem></VTimeline>
        <VBtn v-if="selected.stage !== 'won'" block color="success" prepend-icon="tabler-user-check" class="mt-5" @click="convert(selected)">Convert to customer</VBtn>
      </div></template>
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
