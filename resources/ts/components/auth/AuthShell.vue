<script setup lang="ts">
import { onMounted } from 'vue'
import { useBranding } from '@/composables/useBranding'

defineProps<{
  title: string
  description?: string
}>()

const { logoUrl, hasCompanyLogo, logoFailed, shortCompanyName, originalCompanyName, handleLogoLoad, handleLogoError, loadBranding } = useBranding()

onMounted(() => { void loadBranding() })
</script>

<template>
  <main class="auth-shell">
    <section class="auth-shell__content" :aria-label="title">
      <VCard class="auth-shell__card" elevation="0">
        <header class="auth-shell__header">
          <p v-if="!hasCompanyLogo || logoFailed" class="auth-shell__brand-name">{{ originalCompanyName }}</p>
          <img
            v-else
            :src="logoUrl || undefined"
            :alt="shortCompanyName"
            class="auth-shell__logo"
            @load="handleLogoLoad"
            @error="handleLogoError"
          >
          <h1 class="text-h4 font-weight-bold">{{ title }}</h1>
          <p v-if="description" class="auth-shell__description">{{ description }}</p>
        </header>

        <div v-if="$slots.status" class="auth-shell__status"><slot name="status" /></div>
        <div class="auth-shell__body"><slot /></div>
        <footer v-if="$slots.footer" class="auth-shell__footer"><slot name="footer" /></footer>
      </VCard>
    </section>
  </main>
</template>

<style scoped lang="scss">
.auth-shell {
  display: flex;
  position: relative;
  isolation: isolate;
  min-block-size: 100vh;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  padding: clamp(1rem, 4vw, 3rem);
  background:
    linear-gradient(135deg, rgb(var(--v-theme-primary) / 8%) 0%, rgb(var(--v-theme-background) / 96%) 42%, rgb(var(--v-theme-secondary) / 7%) 100%),
    rgb(var(--v-theme-background));
}

.auth-shell::before,
.auth-shell::after {
  position: absolute;
  z-index: 0;
  border-radius: 999px;
  content: '';
  filter: blur(54px);
  opacity: .5;
  pointer-events: none;
}

.auth-shell::before {
  inset-inline-start: -10rem;
  inset-block-start: -11rem;
  inline-size: 31rem;
  block-size: 31rem;
  background: radial-gradient(circle, rgb(var(--v-theme-primary) / 34%) 0%, rgb(var(--v-theme-primary) / 10%) 46%, transparent 72%);
}

.auth-shell::after {
  inset-inline-end: -10rem;
  inset-block-end: -11rem;
  inline-size: 29rem;
  block-size: 29rem;
  background: radial-gradient(circle, rgb(var(--v-theme-secondary) / 30%) 0%, rgb(var(--v-theme-secondary) / 8%) 48%, transparent 72%);
}

.auth-shell__content {
  position: relative;
  z-index: 1;
  inline-size: min(100%, 28rem);
  margin: 0;
}

.auth-shell__card {
  padding: clamp(1.5rem, 4vw, 2.25rem);
  border: 1px solid rgba(var(--v-border-color), .22);
  border-radius: 1.25rem;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 24px 48px -24px rgb(0 0 0 / 18%), 0 12px 24px -16px rgb(0 0 0 / 8%);
}

.auth-shell__header {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.auth-shell__logo {
  flex: 0 0 auto;
  margin-block-end: 1.5rem;
  object-fit: contain;
  inline-size: auto;
  block-size: 3.25rem;
  max-inline-size: min(100%, 14rem);
  max-block-size: 3.25rem;
}

.auth-shell__brand-name {
  margin: 0 0 1.5rem;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: -.01em;
  line-height: 1.35;
}

.auth-shell__description { margin-block: .75rem 0 0; color: rgb(var(--v-theme-on-surface) / 78%); line-height: 1.6; }
.auth-shell__status { margin-block-start: 1.5rem; }
.auth-shell__status:empty { display: none; }
.auth-shell__body { margin-block-start: 1.5rem; }
.auth-shell__footer { margin-block-start: 1.5rem; text-align: center; }

:global(.v-theme--dark .auth-shell__card) {
  border-color: rgba(var(--v-border-color), .28);
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 24px 48px -24px rgb(0 0 0 / 58%), 0 12px 24px -16px rgb(0 0 0 / 30%);
}

@media (max-width: 599px) {
  .auth-shell { padding: 1rem; }
  .auth-shell__card { border-radius: 1rem; }
}
</style>
