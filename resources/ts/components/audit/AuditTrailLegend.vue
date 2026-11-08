<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AuditTrailState } from '@/utils/auditTrail'

const props = defineProps<{
  states?: AuditTrailState[]
}>()

const { t } = useI18n()

const legendEntries = computed(() => {
  const states = (props.states?.length ? props.states : ['created', 'updated', 'deleted'])
    .filter((state): state is Exclude<AuditTrailState, 'neutral'> => state !== 'neutral')

  return states.map(state => ({
    state,
    label: t(`pages.audits.events.${state}`),
    icon: state === 'created'
      ? 'tabler-circle-plus'
      : state === 'updated'
        ? 'tabler-pencil'
        : 'tabler-trash',
  }))
})
</script>

<template>
  <div
    class="audit-trail-legend"
    role="note"
    :aria-label="t('pages.audits.title')"
  >
    <span
      v-for="entry in legendEntries"
      :key="entry.state"
      class="audit-trail-legend__item"
      :class="`audit-trail-legend__item--${entry.state}`"
    >
      <span
        class="audit-trail-legend__swatch"
        aria-hidden="true"
      />
      <VIcon
        :icon="entry.icon"
        size="14"
        aria-hidden="true"
      />
      <span>{{ entry.label }}</span>
    </span>
  </div>
</template>
