<script setup lang="ts">
withDefaults(defineProps<{ panelHeights?: number[]; summaryHeight?: number }>(), { panelHeights: () => [] })
</script>

<template>
  <div class="overview-skeleton" aria-hidden="true">
    <div class="skeleton-caption mb-2" />
    <div class="summary-grid" :style="summaryHeight ? { blockSize: `${summaryHeight}px` } : undefined">
      <VCard v-for="index in 4" :key="index" class="skeleton-kpi">
        <div class="skeleton-line w-50" />
        <div class="d-flex align-center gap-2 my-2"><div class="skeleton-icon" /><div class="skeleton-value" /></div>
        <div class="skeleton-line" />
      </VCard>
    </div>
    <div class="analytics-grid">
      <VCard v-for="(rows, index) in [6, 6, 7, 7, 7]" :key="index" class="skeleton-panel" :style="{ blockSize: `${panelHeights[index] || (index < 2 ? 440 : 420)}px` }">
        <div class="skeleton-title" /><div class="skeleton-line w-75 mb-6" />
        <div :class="index === 0 ? 'skeleton-pipeline' : 'skeleton-rows'">
          <div v-for="row in rows" :key="row" class="skeleton-row"><div class="skeleton-line" /><div class="skeleton-line w-75 mt-2" /></div>
        </div>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.overview-skeleton { pointer-events: none; }
.skeleton-kpi { padding: 14px 16px; min-block-size: 112px; }
.skeleton-panel { padding: 20px; overflow: hidden; }
.skeleton-caption, .skeleton-line, .skeleton-icon, .skeleton-value, .skeleton-title {
  position: relative;
  overflow: hidden;
  border-radius: 4px;
  background: rgba(var(--v-theme-on-surface), .08);
}
.skeleton-caption { block-size: 18px; inline-size: 140px; }
.skeleton-line { block-size: 14px; }
.skeleton-title { block-size: 22px; inline-size: 55%; margin-block-end: 10px; }
.skeleton-icon { inline-size: 24px; block-size: 24px; }
.skeleton-value { inline-size: 80px; block-size: 30px; }
.skeleton-rows { display: grid; gap: 24px; }
.skeleton-pipeline { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
.skeleton-caption::after, .skeleton-line::after, .skeleton-icon::after, .skeleton-value::after, .skeleton-title::after {
  position: absolute;
  inset: 0;
  content: '';
  background: linear-gradient(90deg, transparent, rgba(var(--v-theme-on-surface), .09), transparent);
  animation: overview-wave 1.5s linear infinite;
}
@keyframes overview-wave { from { transform: translateX(-100%); } to { transform: translateX(100%); } }
@media (max-width: 599px) { .skeleton-pipeline { grid-template-columns: 1fr; } .skeleton-kpi { padding: 12px; } }
@media (prefers-reduced-motion: reduce) {
  .skeleton-caption::after, .skeleton-line::after, .skeleton-icon::after, .skeleton-value::after, .skeleton-title::after { animation: none; }
}
</style>
