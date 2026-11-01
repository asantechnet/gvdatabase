<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(defineProps<{
  visible?: boolean
  canScrollStart?: boolean
  canScrollEnd?: boolean
  label?: string
}>(), {
  visible: false,
  canScrollStart: false,
  canScrollEnd: false,
  label: undefined,
})

defineEmits<{
  scrollStart: []
  scrollEnd: []
}>()

const { t, te } = useI18n()

const localized = (key: string, fallback: string) => {
  if (!te(key))
    return fallback
  const value = t(key)

  return value === key || value.startsWith('[missing:') ? fallback : value
}

const text = computed(() => props.label || localized('common.messages.scrollForMoreColumns', 'Scroll horizontally for more columns'))
const startLabel = computed(() => localized('$vuetify.pagination.ariaLabel.previous', 'Scroll columns back'))
const endLabel = computed(() => localized('$vuetify.pagination.ariaLabel.next', 'Scroll columns forward'))
</script>

<template>
  <div
    v-if="visible"
    class="table-scroll-affordance"
    role="group"
    :aria-label="text"
  >
    <VBtn
      class="table-scroll-affordance__control"
      icon
      size="x-small"
      variant="text"
      :disabled="!canScrollStart"
      :aria-label="startLabel"
      @click="$emit('scrollStart')"
    >
      <VIcon icon="tabler-chevron-left" size="18" />
    </VBtn>

    <span class="table-scroll-affordance__label text-caption">{{ text }}</span>

    <VBtn
      class="table-scroll-affordance__control"
      icon
      size="x-small"
      variant="text"
      :disabled="!canScrollEnd"
      :aria-label="endLabel"
      @click="$emit('scrollEnd')"
    >
      <VIcon icon="tabler-chevron-right" size="18" />
    </VBtn>
  </div>
</template>

<style scoped>
.table-scroll-affordance {
  align-items: center;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  display: flex;
  gap: 0.25rem;
  justify-content: flex-end;
  padding-block: 0.25rem 0.5rem;
}

.table-scroll-affordance__label {
  white-space: nowrap;
}

:global([dir='rtl']) .table-scroll-affordance {
  flex-direction: row-reverse;
}

:global([dir='rtl']) .table-scroll-affordance__control .v-icon {
  transform: scaleX(-1);
}
</style>
