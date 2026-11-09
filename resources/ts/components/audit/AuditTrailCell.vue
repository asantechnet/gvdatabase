<script setup lang="ts">
import { computed } from 'vue'
import type { AuditTrailChange } from '@/api/auditTrail'
import { auditChangeText } from '@/utils/auditTrail'

const props = defineProps<{
  change: AuditTrailChange
}>()

const label = computed(() => auditChangeText(props.change))
</script>

<template>
  <span
    class="audit-trail-cell audit-trail-cell--changed"
    tabindex="0"
    :title="label"
    :aria-label="label"
    role="note"
  >
    <slot />
  </span>
</template>
