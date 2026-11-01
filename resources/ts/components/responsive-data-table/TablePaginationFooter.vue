<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(defineProps<{
  page: number
  itemsPerPage: number
  pageCount: number
  itemsLength: number
  pageSizes?: number[]
}>(), {
  pageSizes: () => [10, 25, 50, 100],
})

const emit = defineEmits<{
  'update:page': [value: number]
  'update:itemsPerPage': [value: number]
}>()

const { t, te } = useI18n()

const localized = (key: string, fallback: string) => {
  if (!te(key))
    return fallback
  const value = String(t(key))

  return value === key || value.startsWith('[missing:') ? fallback : value
}

const start = computed(() => props.itemsLength === 0 ? 0 : (props.page - 1) * props.itemsPerPage + 1)
const stop = computed(() => Math.min(props.page * props.itemsPerPage, props.itemsLength))

const rangeText = computed(() => {
  if (!te('$vuetify.dataFooter.pageText'))
    return `${start.value}-${stop.value} / ${props.itemsLength}`

  const text = String(t('$vuetify.dataFooter.pageText', [start.value, stop.value, props.itemsLength]))

  return text.includes('$vuetify') ? `${start.value}-${stop.value} / ${props.itemsLength}` : text
})

const itemsPerPageLabel = computed(() => localized('$vuetify.dataFooter.itemsPerPageText', 'Items per page'))
const firstLabel = computed(() => localized('$vuetify.dataFooter.firstPage', 'First page'))
const previousLabel = computed(() => localized('$vuetify.dataFooter.prevPage', 'Previous page'))
const nextLabel = computed(() => localized('$vuetify.dataFooter.nextPage', 'Next page'))
const lastLabel = computed(() => localized('$vuetify.dataFooter.lastPage', 'Last page'))

const pageSizeItems = computed(() => props.pageSizes.map(size => ({ title: String(size), value: size })))
const canGoPrevious = computed(() => props.page > 1)
const canGoNext = computed(() => props.page < props.pageCount)
const maxPage = computed(() => Math.max(1, props.pageCount))

const goTo = (value: number) => emit('update:page', Math.min(Math.max(1, value), maxPage.value))
const changePageSize = (value: number | null) => {
  if (typeof value === 'number' && value > 0)
    emit('update:itemsPerPage', value)
}
</script>

<template>
  <div class="table-pagination-footer">
    <VSelect
      class="table-pagination-footer__size"
      density="compact"
      hide-details
      variant="plain"
      :aria-label="itemsPerPageLabel"
      :items="pageSizeItems"
      :model-value="itemsPerPage"
      @update:model-value="changePageSize"
    />

    <span class="table-pagination-footer__range text-caption text-medium-emphasis">{{ rangeText }}</span>

    <div class="table-pagination-footer__controls">
      <VBtn
        icon
        size="small"
        variant="text"
        :disabled="!canGoPrevious"
        :aria-label="firstLabel"
        @click="goTo(1)"
      >
        <VIcon icon="tabler-chevrons-left" size="18" />
      </VBtn>
      <VBtn
        icon
        size="small"
        variant="text"
        :disabled="!canGoPrevious"
        :aria-label="previousLabel"
        @click="goTo(page - 1)"
      >
        <VIcon icon="tabler-chevron-left" size="18" />
      </VBtn>
      <VBtn
        icon
        size="small"
        variant="text"
        :disabled="!canGoNext"
        :aria-label="nextLabel"
        @click="goTo(page + 1)"
      >
        <VIcon icon="tabler-chevron-right" size="18" />
      </VBtn>
      <VBtn
        icon
        size="small"
        variant="text"
        :disabled="!canGoNext"
        :aria-label="lastLabel"
        @click="goTo(maxPage)"
      >
        <VIcon icon="tabler-chevrons-right" size="18" />
      </VBtn>
    </div>
  </div>
</template>

<style scoped>
.table-pagination-footer {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  justify-content: flex-end;
  padding: 0.5rem 0.75rem;
}

.table-pagination-footer__size {
  flex: 0 1 7rem;
  max-inline-size: 7rem;
  min-inline-size: 5rem;
}

.table-pagination-footer__range {
  white-space: nowrap;
}

.table-pagination-footer__controls {
  align-items: center;
  display: flex;
  gap: 0.125rem;
}

:global([dir='rtl']) .table-pagination-footer__controls {
  flex-direction: row-reverse;
}

:global([dir='rtl']) .table-pagination-footer__controls .v-icon {
  transform: scaleX(-1);
}

@media (max-width: 599px) {
  .table-pagination-footer {
    justify-content: center;
  }
}
</style>
