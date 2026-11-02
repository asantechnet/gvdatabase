<script setup lang="ts">
withDefaults(
  defineProps<{ panelHeights?: number[]; summaryHeight?: number }>(),
  { panelHeights: () => [] },
);
</script>

<template>
  <div class="dashboard-skeleton" aria-hidden="true">
    <section
      class="dashboard-skeleton__kpis"
      :style="summaryHeight ? { blockSize: `${summaryHeight}px` } : undefined"
    >
      <div v-for="index in 4" :key="index" class="skeleton-card skeleton-kpi">
        <i /><span><b /><em /></span>
      </div>
    </section>
    <section class="dashboard-skeleton__primary">
      <div
        v-for="index in 2"
        :key="index"
        class="skeleton-card skeleton-panel"
        :style="{ blockSize: `${panelHeights[index - 1] || 420}px` }"
      >
        <b class="skeleton-title" />
        <div v-if="index === 1" class="skeleton-stages">
          <i v-for="bar in 6" :key="bar" />
        </div>
        <div v-else class="skeleton-alerts">
          <i v-for="row in 5" :key="row" />
        </div>
      </div>
    </section>
    <section class="dashboard-skeleton__secondary">
      <div
        v-for="index in 3"
        :key="index"
        class="skeleton-card skeleton-panel"
        :style="{ blockSize: `${panelHeights[index + 1] || 324}px` }"
      >
        <b class="skeleton-title" />
        <div v-if="index === 1" class="skeleton-metrics">
          <i v-for="row in 3" :key="row" />
        </div>
        <div v-else class="skeleton-bars">
          <i v-for="bar in 6" :key="bar" />
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped lang="scss">
.dashboard-skeleton,
.dashboard-skeleton__kpis,
.dashboard-skeleton__primary,
.dashboard-skeleton__secondary {
  display: grid;
  gap: 16px;
}
.dashboard-skeleton__kpis {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.dashboard-skeleton__primary {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.dashboard-skeleton__secondary {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.skeleton-card {
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 1px 2px rgb(0 0 0 / 5%);
}
.skeleton-kpi {
  display: flex;
  align-items: center;
  gap: 14px;
  min-block-size: 112px;
  padding: calc(1.5rem + 4px) 1.25rem 1.5rem;
}
.skeleton-kpi > i,
.skeleton-kpi b,
.skeleton-kpi em,
.skeleton-title,
.skeleton-bars i,
.skeleton-stages i,
.skeleton-alerts i,
.skeleton-metrics i {
  position: relative;
  overflow: hidden;
  border-radius: 4px;
  background: rgba(var(--v-theme-on-surface), 0.08);
}
.skeleton-kpi > i {
  inline-size: 48px;
  block-size: 48px;
  border-radius: 10px;
}
.skeleton-kpi span {
  display: grid;
  flex: 1;
  gap: 7px;
}
.skeleton-kpi b {
  inline-size: 52%;
  block-size: 18px;
}
.skeleton-kpi em {
  inline-size: 74%;
  block-size: 12px;
}
.skeleton-panel {
  padding: 20px;
}
.skeleton-title {
  display: block;
  inline-size: 42%;
  block-size: 18px;
  margin-block-end: 24px;
}
.skeleton-bars {
  display: grid;
  gap: 19px;
}
.skeleton-bars i {
  display: block;
  block-size: 18px;
}
.skeleton-bars i:nth-child(odd) {
  inline-size: 72%;
}
.skeleton-bars i:nth-child(even) {
  inline-size: 88%;
}
.skeleton-stages {
  display: grid;
  gap: 12px;
}
.skeleton-stages i {
  display: block;
  block-size: 56px;
}
.skeleton-alerts,
.skeleton-metrics {
  display: grid;
  gap: 12px;
}
.skeleton-alerts i {
  display: block;
  block-size: 52px;
}
.skeleton-metrics i {
  display: block;
  block-size: 46px;
}
.skeleton-kpi > i::after,
.skeleton-kpi b::after,
.skeleton-kpi em::after,
.skeleton-title::after,
.skeleton-bars i::after,
.skeleton-stages i::after,
.skeleton-alerts i::after,
.skeleton-metrics i::after {
  position: absolute;
  inset: 0;
  content: "";
  background: linear-gradient(
    90deg,
    transparent,
    rgba(var(--v-theme-on-surface), 0.1),
    transparent
  );
  animation: skeleton-wave 1.5s linear infinite;
}
@keyframes skeleton-wave {
  from {
    transform: translateX(-100%);
  }
  to {
    transform: translateX(100%);
  }
}
@media (max-width: 1279px) {
  .dashboard-skeleton__secondary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .dashboard-skeleton__secondary > :last-child {
    grid-column: span 2;
  }
  .dashboard-skeleton__kpis {
    display: flex;
    overflow-x: auto;
    padding-block: 2px;
    scrollbar-width: none;
  }
  .dashboard-skeleton__kpis::-webkit-scrollbar {
    display: none;
  }
  .dashboard-skeleton__kpis .skeleton-kpi {
    flex: 0 0 260px;
  }
}
@media (max-width: 959px) {
  .dashboard-skeleton__primary {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 599px) {
  .dashboard-skeleton__kpis .skeleton-kpi {
    flex: 0 0 min(82vw, 300px);
  }
  .dashboard-skeleton__secondary {
    grid-template-columns: 1fr;
  }
  .dashboard-skeleton__secondary > :last-child {
    grid-column: auto;
  }
  .skeleton-kpi,
  .skeleton-panel {
    padding: 16px;
  }
  .skeleton-kpi {
    min-block-size: 104px;
    padding: 1.25rem 1rem;
  }
  .skeleton-kpi > i {
    inline-size: 44px;
    block-size: 44px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .skeleton-kpi > i::after,
  .skeleton-kpi b::after,
  .skeleton-kpi em::after,
  .skeleton-title::after,
  .skeleton-bars i::after,
  .skeleton-stages i::after,
  .skeleton-alerts i::after,
  .skeleton-metrics i::after {
    animation: none;
  }
}
</style>
