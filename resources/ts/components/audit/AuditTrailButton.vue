<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AuditTrailRow } from '@/api/auditTrail'
import { auditChangeSummary, auditEventKey } from '@/utils/auditTrail'

const props = defineProps<{
  trail: AuditTrailRow
  subject?: string
}>()

const { t } = useI18n()

const label = computed(() => {
  const event = t(`pages.audits.events.${auditEventKey(props.trail.event)}`)
  const summary = auditChangeSummary(props.trail)
  const subject = props.subject?.trim()

  return [subject, event, summary].filter(Boolean).join(' · ')
})

const accessibleLabel = computed(() => `${t('pages.audits.actions.view')}: ${label.value}`)
</script>

<template>
  <VBtn
    :to="{ name: 'audits', query: { audit: String(trail.audit_id) } }"
    icon
    size="small"
    variant="text"
    color="medium-emphasis"
    class="audit-trail-button"
    :aria-label="accessibleLabel"
    :title="accessibleLabel"
  >
    <VIcon icon="tabler-history" />
    <VTooltip
      activator="parent"
      location="top"
      max-width="320"
    >
      {{ accessibleLabel }}
    </VTooltip>
  </VBtn>
</template>
