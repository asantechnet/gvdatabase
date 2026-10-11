<script setup lang="ts">
const props = defineProps<{
  selectedCount: number
  label?: string
}>()
const { t } = useI18n()
const selectionLabel = computed(() => props.label ?? t('common.ui.selected'))

defineEmits<{
  clear: []
}>()
</script>

<template>
  <VSlideYReverseTransition>
    <VSheet
      v-if="selectedCount > 0"
      class="bulk-action-bar d-flex flex-wrap align-center ga-2"
      border
      elevation="4"
      role="region"
      :aria-label="`${selectedCount} ${selectionLabel}`"
    >
      <div
        class="bulk-action-bar__count"
        aria-live="polite"
      >
        <strong>{{ selectedCount }}</strong>
        <span>{{ selectionLabel }}</span>
      </div>

      <VDivider
        vertical
        class="bulk-action-bar__divider"
      />

      <div class="d-flex flex-wrap align-center ga-1">
        <slot />
      </div>

      <VSpacer />

      <IconBtn
        :aria-label="$t('pages.audits.bulk.clear')"
        @click="$emit('clear')"
      >
        <VIcon icon="tabler-x" />
        <VTooltip activator="parent">
          {{ $t('pages.audits.bulk.clear') }}
        </VTooltip>
      </IconBtn>
    </VSheet>
  </VSlideYReverseTransition>
</template>

<style scoped>
.bulk-action-bar {
  background: rgb(var(--v-theme-surface));
  border-radius: 6px;
  inset-block-end: 0.75rem;
  margin: 0.75rem;
  min-block-size: 2.75rem;
  padding: 0.375rem 0.5rem 0.375rem 0.875rem;
  position: sticky;
  z-index: 3;
}

.bulk-action-bar__count {
  align-items: baseline;
  display: flex;
  font-size: 0.8125rem;
  gap: 0.25rem;
  white-space: nowrap;
}

.bulk-action-bar__count strong {
  font-variant-numeric: tabular-nums;
}

@media (max-width: 599px) {
  .bulk-action-bar {
    margin: 0.5rem;
    padding-inline-start: 0.75rem;
  }

  .bulk-action-bar__divider {
    display: none;
  }
}
</style>
