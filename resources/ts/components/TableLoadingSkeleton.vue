<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(defineProps<{
  columns?: number
  rows?: number
  label?: string
}>(), {
  columns: 5,
  rows: 5,
})

const { t } = useI18n()
const normalizedColumns = computed(() => Math.max(1, Math.floor(props.columns)))
const normalizedRows = computed(() => Math.max(1, Math.floor(props.rows)))
const columnIndexes = computed(() => Array.from({ length: normalizedColumns.value }, (_, index) => index))
const rowIndexes = computed(() => Array.from({ length: normalizedRows.value }, (_, index) => index))
const accessibleLabel = computed(() => props.label || t('common.messages.loading'))

const headerWidth = (index: number) => `${52 + (index % 3) * 14}%`
const primaryCellWidth = (index: number) => `${62 + (index % 3) * 12}%`
</script>

<template>
  <div
    class="table-loading-skeleton"
    role="status"
    aria-live="polite"
    aria-busy="true"
    :aria-label="accessibleLabel"
  >
    <span class="table-loading-skeleton__sr-only">{{ accessibleLabel }}</span>

    <div class="table-loading-skeleton__scroll">
      <table class="table-loading-skeleton__table">
        <caption class="table-loading-skeleton__sr-only">
          {{ accessibleLabel }}
        </caption>
        <thead>
          <tr>
            <th v-for="column in columnIndexes" :key="`header-${column}`" scope="col">
              <span
                class="table-loading-skeleton__placeholder table-loading-skeleton__placeholder--header"
                :style="{ inlineSize: headerWidth(column) }"
              />
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rowIndexes" :key="`row-${row}`">
            <td v-for="column in columnIndexes" :key="`cell-${row}-${column}`">
              <span
                class="table-loading-skeleton__placeholder table-loading-skeleton__placeholder--primary"
                :style="{ inlineSize: primaryCellWidth(column) }"
              />
              <span
                v-if="column % 2 === 0"
                class="table-loading-skeleton__placeholder table-loading-skeleton__placeholder--secondary"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.table-loading-skeleton {
  inline-size: 100%;
  pointer-events: none;
  min-block-size: 22rem;
  overflow: hidden;
  /* A framed empty panel resembles a no-data state before the request ends. */
  border: 0;
  border-radius: .7rem;
  background: transparent;
}

.table-loading-skeleton__scroll {
  max-inline-size: 100%;
  overflow-x: auto;
}

.table-loading-skeleton__table {
  border-collapse: collapse;
  inline-size: 100%;
  min-inline-size: 44rem;
  table-layout: fixed;
}

.table-loading-skeleton__table th,
.table-loading-skeleton__table td {
  border-block-end: thin solid rgb(var(--v-border-color) / var(--v-border-opacity));
  padding: 1rem;
  text-align: start;
  vertical-align: middle;
}

.table-loading-skeleton__table th {
  background: rgb(var(--v-theme-surface));
}

.table-loading-skeleton__table td {
  block-size: 4.75rem;
}

.table-loading-skeleton__placeholder {
  background: rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 0.375rem;
  display: block;
  overflow: hidden;
  position: relative;
}

.table-loading-skeleton__placeholder::after {
  animation: table-loading-skeleton-wave 1.5s ease-in-out infinite;
  background: linear-gradient(90deg, transparent, rgba(var(--v-theme-on-surface), 0.24), transparent);
  content: '';
  inset: 0;
  position: absolute;
  transform: translateX(-100%);
}

.table-loading-skeleton__placeholder--header {
  block-size: 0.75rem;
}

.table-loading-skeleton__placeholder--primary {
  block-size: 0.875rem;
}

.table-loading-skeleton__placeholder--secondary {
  block-size: 0.625rem;
  inline-size: 42%;
  margin-block-start: 0.5rem;
}

.table-loading-skeleton__sr-only {
  block-size: 1px;
  clip: rect(0 0 0 0);
  clip-path: inset(50%);
  inline-size: 1px;
  overflow: hidden;
  position: absolute;
  white-space: nowrap;
}

@keyframes table-loading-skeleton-wave {
  to {
    transform: translateX(100%);
  }
}

@media (max-width: 599px) {
  .table-loading-skeleton__scroll {
    overflow-x: hidden;
  }

  .table-loading-skeleton__table {
    min-inline-size: 100%;
  }

  .table-loading-skeleton__table th,
  .table-loading-skeleton__table td {
    padding: 0.75rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .table-loading-skeleton__placeholder::after {
    animation: none;
  }
}
</style>
