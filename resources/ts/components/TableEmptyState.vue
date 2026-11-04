<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  description: string
  icon?: string
  minHeight?: number | string
  title?: string
}>(), {
  icon: 'tabler-database-off',
  minHeight: 240,
  title: undefined,
})

const minHeight = computed(() => typeof props.minHeight === 'number'
  ? `${props.minHeight}px`
  : props.minHeight)
</script>

<template>
  <div class="table-empty-state" :style="{ minBlockSize: minHeight }">
    <div class="table-empty-state__icon" aria-hidden="true">
      <VIcon :icon="icon" size="34" />
    </div>

    <div class="table-empty-state__content">
      <h3 v-if="title" class="table-empty-state__title">
        {{ title }}
      </h3>
      <p class="table-empty-state__description">
        {{ description }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.table-empty-state {
  align-items: center;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  display: flex;
  flex-direction: column;
  justify-content: center;
  margin-inline: auto;
  max-inline-size: 36rem;
  padding: clamp(1.5rem, 5vw, 3rem);
  text-align: center;
}

.table-empty-state__icon {
  align-items: center;
  background: rgba(var(--v-theme-primary), 0.1);
  border-radius: 999px;
  color: rgb(var(--v-theme-primary));
  display: inline-flex;
  flex: none;
  justify-content: center;
  block-size: 4rem;
  inline-size: 4rem;
  margin-block-end: 1rem;
}

.table-empty-state__content {
  display: grid;
  gap: 0.375rem;
}

.table-empty-state__title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.4;
  margin: 0;
}

.table-empty-state__description {
  font-size: 0.875rem;
  line-height: 1.55;
  margin: 0;
}

@media (max-width: 599px) {
  .table-empty-state {
    min-block-size: 12rem !important;
    padding: 1.5rem;
  }
}
</style>
