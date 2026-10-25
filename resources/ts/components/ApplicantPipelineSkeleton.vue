<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(defineProps<{
  columns?: number
  cardsPerColumn?: number
}>(), {
  columns: 4,
  cardsPerColumn: 3,
})

const { t } = useI18n()
const columnIndexes = computed(() => Array.from({ length: Math.max(1, props.columns) }, (_, index) => index))
const cardIndexes = computed(() => Array.from({ length: Math.max(1, props.cardsPerColumn) }, (_, index) => index))
</script>

<template>
  <div class="applicant-pipeline-skeleton" role="status" aria-live="polite" :aria-label="t('common.messages.loading')">
    <span class="applicant-pipeline-skeleton__sr-only">{{ t('common.messages.loading') }}</span>
    <div class="applicant-pipeline-skeleton__scroller">
      <VCard v-for="column in columnIndexes" :key="column" variant="outlined" class="applicant-pipeline-skeleton__column">
        <VCardItem>
          <template #title><span class="applicant-pipeline-skeleton__line applicant-pipeline-skeleton__line--title" /></template>
          <template #subtitle><span class="applicant-pipeline-skeleton__line applicant-pipeline-skeleton__line--subtitle" /></template>
          <template #append><span class="applicant-pipeline-skeleton__chip" /></template>
        </VCardItem>
        <VCardText class="d-flex flex-column gap-2">
          <VCard v-for="card in cardIndexes" :key="card" variant="tonal" class="applicant-pipeline-skeleton__card">
            <VCardText class="py-2">
              <span class="applicant-pipeline-skeleton__line applicant-pipeline-skeleton__line--name" />
              <span class="applicant-pipeline-skeleton__line applicant-pipeline-skeleton__line--meta" />
              <span class="applicant-pipeline-skeleton__line applicant-pipeline-skeleton__line--meta applicant-pipeline-skeleton__line--short" />
              <span class="applicant-pipeline-skeleton__actions"><span /><span /></span>
            </VCardText>
          </VCard>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.applicant-pipeline-skeleton { pointer-events: none; }

.applicant-pipeline-skeleton__scroller { display: flex; gap: 0.75rem; overflow-x: auto; padding-block-end: 0.5rem; }

.applicant-pipeline-skeleton__column { flex: 0 0 260px; inline-size: 260px; }

.applicant-pipeline-skeleton__card { min-block-size: 8rem; }

.applicant-pipeline-skeleton__line,
.applicant-pipeline-skeleton__chip,
.applicant-pipeline-skeleton__actions span {
  background: rgb(var(--v-theme-on-surface) / 0.08);
  border-radius: 0.375rem;
  display: block;
  overflow: hidden;
  position: relative;
}

.applicant-pipeline-skeleton__line::after,
.applicant-pipeline-skeleton__chip::after,
.applicant-pipeline-skeleton__actions span::after {
  animation: applicant-pipeline-skeleton-shimmer 1.5s ease-in-out infinite;
  background: linear-gradient(90deg, transparent, rgb(var(--v-theme-on-surface) / 0.1), transparent);
  content: '';
  inset: 0;
  position: absolute;
  transform: translateX(-100%);
}

.applicant-pipeline-skeleton__line--title { block-size: 0.95rem; inline-size: 62%; }
.applicant-pipeline-skeleton__line--subtitle { block-size: 0.65rem; inline-size: 42%; margin-block-start: 0.5rem; }
.applicant-pipeline-skeleton__chip { block-size: 1.5rem; border-radius: 999px; inline-size: 2rem; }
.applicant-pipeline-skeleton__line--name { block-size: 0.9rem; inline-size: 66%; }
.applicant-pipeline-skeleton__line--meta { block-size: 0.65rem; inline-size: 88%; margin-block-start: 0.6rem; }
.applicant-pipeline-skeleton__line--short { inline-size: 56%; }

.applicant-pipeline-skeleton__actions { display: flex; gap: 0.5rem; margin-block-start: 0.85rem; }
.applicant-pipeline-skeleton__actions span { block-size: 0.65rem; inline-size: 2.75rem; }

.applicant-pipeline-skeleton__sr-only { block-size: 1px; clip: rect(0 0 0 0); clip-path: inset(50%); inline-size: 1px; overflow: hidden; position: absolute; white-space: nowrap; }

@keyframes applicant-pipeline-skeleton-shimmer { to { transform: translateX(100%); } }

@media (prefers-reduced-motion: reduce) {
  .applicant-pipeline-skeleton__line,
  .applicant-pipeline-skeleton__chip,
  .applicant-pipeline-skeleton__actions span,
  .applicant-pipeline-skeleton__line::after,
  .applicant-pipeline-skeleton__chip::after,
  .applicant-pipeline-skeleton__actions span::after { animation: none; }
}
</style>
