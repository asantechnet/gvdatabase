<template>
  <div
    class="overview-skeleton"
    aria-hidden="true"
  >
    <section class="skeleton-metrics">
      <div
        v-for="index in 4"
        :key="index"
        class="skeleton-card skeleton-metric"
      >
        <i /><span><b /><em /><small /></span>
      </div>
    </section>

    <section class="skeleton-hero">
      <div class="skeleton-card skeleton-pipeline">
        <b class="skeleton-title" />
        <div class="skeleton-columns">
          <i
            v-for="index in 6"
            :key="index"
          />
        </div>
      </div>
      <div class="skeleton-card skeleton-actions">
        <b class="skeleton-title" />
        <i
          v-for="index in 5"
          :key="index"
        />
      </div>
    </section>

    <section class="skeleton-insights">
      <div
        v-for="index in 3"
        :key="index"
        class="skeleton-card skeleton-panel"
      >
        <b class="skeleton-title" />
        <i
          v-for="row in 5"
          :key="row"
        />
      </div>
    </section>

    <section class="skeleton-market">
      <div
        v-for="index in 2"
        :key="index"
        class="skeleton-card skeleton-panel"
      >
        <b class="skeleton-title" />
        <i
          v-for="row in 5"
          :key="row"
        />
      </div>
    </section>
  </div>
</template>

<style scoped lang="scss">
.overview-skeleton,
.skeleton-metrics,
.skeleton-hero,
.skeleton-insights,
.skeleton-market { display: grid; gap: 16px; }
.overview-skeleton { gap: 20px; }
.skeleton-metrics { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.skeleton-hero { grid-template-columns: minmax(0, 2fr) minmax(310px, 1fr); }
.skeleton-insights { grid-template-columns: 3fr 5fr 4fr; }
.skeleton-market { grid-template-columns: 5fr 7fr; }
.skeleton-card { overflow: hidden; padding: 22px; border: 1px solid rgba(var(--v-theme-on-surface), .09); border-radius: 20px; background: rgb(var(--v-theme-surface)); box-shadow: 0 10px 34px rgba(35, 31, 60, .045); }
.skeleton-metric { display: flex; align-items: center; gap: 14px; min-block-size: 126px; padding: 20px; }
.skeleton-metric > i { flex: 0 0 46px; inline-size: 46px; block-size: 46px; border-radius: 14px; }
.skeleton-metric > span { display: grid; flex: 1; gap: 7px; }
.skeleton-metric b { inline-size: 48%; block-size: 10px; }
.skeleton-metric em { inline-size: 75%; block-size: 20px; }
.skeleton-metric small { inline-size: 86%; block-size: 8px; }
.skeleton-pipeline,
.skeleton-actions { min-block-size: 430px; }
.skeleton-title { display: block; inline-size: 32%; block-size: 16px; margin-block-end: 28px; }
.skeleton-columns { display: flex; align-items: flex-end; gap: 12px; block-size: 280px; }
.skeleton-columns i { flex: 1; block-size: 48%; border-radius: 9px 9px 3px 3px; }
.skeleton-columns i:nth-child(2),
.skeleton-columns i:nth-child(5) { block-size: 78%; }
.skeleton-columns i:nth-child(3) { block-size: 62%; }
.skeleton-columns i:nth-child(4) { block-size: 88%; }
.skeleton-actions,
.skeleton-panel { display: grid; align-content: start; gap: 10px; }
.skeleton-actions > i { block-size: 60px; border-radius: 13px; }
.skeleton-panel { min-block-size: 390px; }
.skeleton-panel > i { block-size: 40px; border-radius: 9px; }
.skeleton-metric > i,
.skeleton-metric b,
.skeleton-metric em,
.skeleton-metric small,
.skeleton-title,
.skeleton-columns i,
.skeleton-actions > i,
.skeleton-panel > i { position: relative; overflow: hidden; background: rgba(var(--v-theme-on-surface), .075); }
.skeleton-metric > i::after,
.skeleton-metric b::after,
.skeleton-metric em::after,
.skeleton-metric small::after,
.skeleton-title::after,
.skeleton-columns i::after,
.skeleton-actions > i::after,
.skeleton-panel > i::after { position: absolute; inset: 0; content: ""; background: linear-gradient(90deg, transparent, rgba(var(--v-theme-on-surface), .1), transparent); animation: wave 1.45s linear infinite; }
@keyframes wave { from { transform: translateX(-100%); } to { transform: translateX(100%); } }
@media (max-width: 1279px) {
  .skeleton-metrics { display: flex; overflow-x: auto; padding-block: 2px; scrollbar-width: none; }
  .skeleton-metrics::-webkit-scrollbar { display: none; }
  .skeleton-metric { flex: 0 0 280px; }
  .skeleton-insights { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .skeleton-insights > :nth-child(2) { grid-column: span 2; grid-row: 1; }
  .skeleton-market { grid-template-columns: 5fr 7fr; }
}
@media (max-width: 959px) {
  .skeleton-hero,
  .skeleton-market { grid-template-columns: 1fr; }
  .skeleton-actions { grid-row: 1; }
}
@media (max-width: 599px) {
  .overview-skeleton { gap: 14px; }
  .skeleton-insights { grid-template-columns: 1fr; gap: 12px; }
  .skeleton-insights > :nth-child(2) { grid-column: auto; grid-row: auto; }
  .skeleton-card { min-block-size: 260px; padding: 17px; border-radius: 17px; }
  .skeleton-metric { flex-basis: min(84vw, 300px); min-block-size: 108px; }
}
@media (prefers-reduced-motion: reduce) {
  .skeleton-metric > i::after,
  .skeleton-metric b::after,
  .skeleton-metric em::after,
  .skeleton-metric small::after,
  .skeleton-title::after,
  .skeleton-columns i::after,
  .skeleton-actions > i::after,
  .skeleton-panel > i::after { animation: none; }
}
</style>
