<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="applicant-portal-skeleton" role="status" aria-live="polite" :aria-label="t('common.messages.loading')">
    <span class="applicant-portal-skeleton__sr-only">{{ t('common.messages.loading') }}</span>
    <section class="applicant-portal-skeleton__hero">
      <div class="applicant-portal-skeleton__hero-copy">
        <i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--eyebrow" />
        <i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--heading" />
        <i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--body" />
        <i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--body applicant-portal-skeleton__line--short" />
      </div>
      <div class="applicant-portal-skeleton__progress"><i class="applicant-portal-skeleton__number" /><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--progress-label" /><i class="applicant-portal-skeleton__bar" /></div>
    </section>

    <div class="applicant-portal-skeleton__grid">
      <div class="applicant-portal-skeleton__main">
        <section class="applicant-portal-skeleton__card applicant-portal-skeleton__next"><i class="applicant-portal-skeleton__avatar" /><div><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--eyebrow" /><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--title" /><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--body" /></div><i class="applicant-portal-skeleton__button" /></section>
        <section class="applicant-portal-skeleton__card applicant-portal-skeleton__journey"><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--title" /><i v-for="item in 4" :key="item" class="applicant-portal-skeleton__timeline-row"><b /><span><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--timeline-title" /><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--timeline-date" /></span></i></section>
        <section class="applicant-portal-skeleton__card applicant-portal-skeleton__table"><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--title" /><i v-for="item in 3" :key="item" class="applicant-portal-skeleton__table-row"><span /><span /><span /></i></section>
      </div>
      <aside class="applicant-portal-skeleton__side">
        <section v-for="card in 3" :key="card" class="applicant-portal-skeleton__card applicant-portal-skeleton__side-card"><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--title" /><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--body" /><i class="applicant-portal-skeleton__line applicant-portal-skeleton__line--body applicant-portal-skeleton__line--short" /><i v-if="card === 1" class="applicant-portal-skeleton__button applicant-portal-skeleton__button--wide" /></section>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.applicant-portal-skeleton { display: grid; gap: 1rem; pointer-events: none; }
.applicant-portal-skeleton__hero, .applicant-portal-skeleton__card { border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 16px; background: rgb(var(--v-theme-surface)); }
.applicant-portal-skeleton__hero { display: grid; min-block-size: 218px; padding: clamp(1.25rem, 3vw, 2.25rem); gap: 2rem; grid-template-columns: minmax(0, 1fr) minmax(220px, .32fr); }
.applicant-portal-skeleton__hero-copy, .applicant-portal-skeleton__progress, .applicant-portal-skeleton__side-card { display: grid; align-content: start; gap: .75rem; }
.applicant-portal-skeleton__progress { padding: 1rem; border: 1px solid rgba(var(--v-theme-primary), .14); border-radius: 14px; }
.applicant-portal-skeleton__grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr); }
.applicant-portal-skeleton__main, .applicant-portal-skeleton__side { display: grid; align-content: start; gap: 1rem; }
.applicant-portal-skeleton__card { padding: 1.25rem; }
.applicant-portal-skeleton__next { display: grid; align-items: center; min-block-size: 104px; gap: .9rem; grid-template-columns: auto minmax(0, 1fr) auto; }
.applicant-portal-skeleton__journey { display: grid; gap: 1rem; min-block-size: 270px; }
.applicant-portal-skeleton__table { display: grid; gap: .9rem; min-block-size: 174px; }
.applicant-portal-skeleton__side-card { min-block-size: 142px; }
.applicant-portal-skeleton__line, .applicant-portal-skeleton__number, .applicant-portal-skeleton__bar, .applicant-portal-skeleton__avatar, .applicant-portal-skeleton__button, .applicant-portal-skeleton__timeline-row b, .applicant-portal-skeleton__table-row span { display: block; overflow: hidden; border-radius: .4rem; background: rgba(var(--v-theme-on-surface), .08); position: relative; }
.applicant-portal-skeleton__line::after, .applicant-portal-skeleton__number::after, .applicant-portal-skeleton__bar::after, .applicant-portal-skeleton__avatar::after, .applicant-portal-skeleton__button::after, .applicant-portal-skeleton__timeline-row b::after, .applicant-portal-skeleton__table-row span::after { position: absolute; inset: 0; animation: applicant-portal-wave 1.5s ease-in-out infinite; background: linear-gradient(90deg, transparent, rgba(var(--v-theme-on-surface), .12), transparent); content: ''; transform: translateX(-100%); }
.applicant-portal-skeleton__line--eyebrow { block-size: .65rem; inline-size: 5rem; }.applicant-portal-skeleton__line--heading { block-size: 2rem; inline-size: min(100%, 19rem); }.applicant-portal-skeleton__line--title { block-size: 1rem; inline-size: min(100%, 11rem); }.applicant-portal-skeleton__line--body { block-size: .72rem; inline-size: min(100%, 28rem); }.applicant-portal-skeleton__line--short { inline-size: 62%; }.applicant-portal-skeleton__line--progress-label { inline-size: 6rem; }.applicant-portal-skeleton__number { block-size: 2.4rem; inline-size: 4.2rem; }.applicant-portal-skeleton__bar { block-size: .5rem; inline-size: 100%; }.applicant-portal-skeleton__avatar { inline-size: 44px; block-size: 44px; border-radius: 50%; }.applicant-portal-skeleton__button { inline-size: 5.5rem; block-size: 2rem; border-radius: .5rem; }.applicant-portal-skeleton__button--wide { inline-size: 100%; }.applicant-portal-skeleton__timeline-row { display: grid; align-items: center; gap: .75rem; grid-template-columns: 28px minmax(0, 1fr); }.applicant-portal-skeleton__timeline-row b { inline-size: 28px; block-size: 28px; border-radius: 50%; }.applicant-portal-skeleton__timeline-row span { display: grid; gap: .4rem; }.applicant-portal-skeleton__line--timeline-title { block-size: .8rem; inline-size: 45%; }.applicant-portal-skeleton__line--timeline-date { block-size: .62rem; inline-size: 25%; }.applicant-portal-skeleton__table-row { display: grid; gap: 1rem; grid-template-columns: 1.4fr .7fr .6fr; }.applicant-portal-skeleton__table-row span { block-size: .8rem; }
.applicant-portal-skeleton__sr-only { position: absolute; inline-size: 1px; block-size: 1px; overflow: hidden; clip: rect(0 0 0 0); clip-path: inset(50%); white-space: nowrap; }
@keyframes applicant-portal-wave { to { transform: translateX(100%); } }
@media (max-width: 960px) { .applicant-portal-skeleton__grid { grid-template-columns: 1fr; }.applicant-portal-skeleton__side { grid-template-columns: repeat(3, minmax(0, 1fr)); }.applicant-portal-skeleton__side-card { min-block-size: 128px; } }
@media (max-width: 700px) { .applicant-portal-skeleton__hero, .applicant-portal-skeleton__grid, .applicant-portal-skeleton__side { grid-template-columns: 1fr; }.applicant-portal-skeleton__hero { min-block-size: 270px; gap: 1rem; }.applicant-portal-skeleton__next { grid-template-columns: auto minmax(0, 1fr); }.applicant-portal-skeleton__next > .applicant-portal-skeleton__button { display: none; } }
@media (prefers-reduced-motion: reduce) { .applicant-portal-skeleton *::after { animation: none !important; } }
</style>
