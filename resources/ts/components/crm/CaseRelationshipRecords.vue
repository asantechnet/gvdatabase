<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import CrmApi, { type ApplicantRelationshipRecord } from '@/api/crmRelationships'
import { apiErrorMessage } from '@/utils/apiErrors'
import { formatLocalizedDate } from '@/utils/localization'

const props = defineProps<{ applicantId: number }>()
const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const records = ref<ApplicantRelationshipRecord[]>([])

const typeLabel = (type: ApplicantRelationshipRecord['target_type']) => type === 'country'
  ? t('educational.masterData.countries')
  : t('educational.masterData.universities')

const channelLabel = (channel: ApplicantRelationshipRecord['channel']) => ({
  call: t('common.crmDetails.channels.calls'),
  message: t('common.crmDetails.channels.messages'),
  email: t('common.crmDetails.channels.emails'),
  meeting: t('common.crmDetails.channels.meetings'),
  note: t('common.crmDetails.channels.notes'),
}[channel])

const formatDateTime = (value: string) => formatLocalizedDate(value, 'long')
const orderedRecords = computed(() => [...records.value].sort((a, b) => new Date(b.contacted_at).getTime() - new Date(a.contacted_at).getTime()))

onMounted(async () => {
  try {
    records.value = (await CrmApi.getApplicantRelationships(props.applicantId)).records
  }
  catch (error) {
    toast.error(apiErrorMessage(error, t('common.crmDetails.loadFailed')))
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="case-section case-section--relationships">
    <div class="case-section__heading">
      <div>
        <p class="case-section__eyebrow">{{ $t('common.crmDetails.relationshipDetails') }}</p>
        <h3>{{ $t('common.crmDetails.entityRelationship', { entity: $t('common.crmDetails.entities.applicant') }) }}</h3>
      </div>
    </div>
    <div v-if="loading" class="d-flex justify-center py-4">
      <VProgressCircular indeterminate color="primary" size="24" />
    </div>
    <div v-else-if="orderedRecords.length" class="case-relationship-list">
      <VCard
        v-for="record in orderedRecords"
        :key="record.id"
        border
        variant="flat"
        class="case-relationship-record"
      >
        <VCardText>
          <div class="d-flex align-start ga-3">
            <VAvatar color="primary" variant="tonal" size="34">
              <VIcon
                :icon="record.target_type === 'country' ? 'tabler-world' : 'tabler-school'"
                size="18"
              />
            </VAvatar>
            <div class="min-w-0 flex-grow-1">
              <div class="d-flex flex-wrap align-center ga-2">
                <span class="font-weight-bold">{{ record.target_name || $t('common.messages.notAvailable') }}</span>
                <VChip size="x-small" variant="tonal">{{ typeLabel(record.target_type) }}</VChip>
                <VChip size="x-small" color="primary" variant="tonal">{{ channelLabel(record.channel) }}</VChip>
              </div>
              <div class="text-caption text-medium-emphasis mt-1">
                {{ formatDateTime(record.contacted_at) }}
                <span v-if="record.creator"> · {{ record.creator.name }}</span>
              </div>
              <div class="text-body-2 font-weight-medium mt-2">{{ record.title }}</div>
              <p v-if="record.notes" class="text-body-2 mt-1 mb-0 relationship-note">{{ record.notes }}</p>
              <VAlert
                v-if="record.follow_up_at"
                density="compact"
                variant="tonal"
                color="warning"
                icon="tabler-bell"
                class="mt-2"
              >
                {{ $t('common.crmDetails.followUpAt', { date: formatDateTime(record.follow_up_at) }) }}
              </VAlert>
            </div>
          </div>
        </VCardText>
      </VCard>
    </div>
    <p v-else class="text-caption text-medium-emphasis mb-0">{{ $t('common.crmDetails.emptyHint') }}</p>
  </section>
</template>

<style scoped>
.case-section {
  background: rgb(var(--v-theme-on-surface) / 0.035);
  border: 1px solid rgb(var(--v-border-color) / var(--v-border-opacity));
  border-radius: 0.75rem;
  padding: clamp(1rem, 2vw, 1.5rem);
}

.case-section--relationships { background: rgba(var(--v-theme-info), .045); }

.case-section__heading {
  align-items: flex-start;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: space-between;
  margin-block-end: 1rem;
}

.case-section__heading h3 {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  line-height: 1.4;
  margin: 0;
}

.case-section__eyebrow {
  color: rgb(var(--v-theme-on-surface) / var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  margin: 0 0 0.25rem;
  text-transform: uppercase;
}

.case-relationship-list { display: grid; gap: 0.75rem; }
.case-relationship-record { background: rgb(var(--v-theme-surface)); }
.relationship-note { white-space: pre-wrap; line-height: 1.55; }
.min-w-0 { min-inline-size: 0; }
</style>
