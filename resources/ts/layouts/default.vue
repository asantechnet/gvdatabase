<script lang="ts" setup>
import { onMounted } from 'vue'
import { useCookie } from '@core/composable/useCookie'
import { useLayoutConfigStore } from '@layouts/stores/config'
import { useBranding } from '@/composables/useBranding'

const ApplicantPortalLayout = defineAsyncComponent(() => import('./components/ApplicantPortalLayout.vue'))
const DefaultLayoutWithVerticalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithVerticalNav.vue'))
const DefaultLayoutWithHorizontalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithHorizontalNav.vue'))
const layoutConfigStore = useLayoutConfigStore()
const userData = useCookie<{ id?: number; roles?: Array<string | { name?: string }> } | null>('userData')
const isApplicantPortalUser = computed(() => userData.value?.roles?.some(role =>
  (typeof role === 'string' ? role : role.name) === 'applicant') ?? false)
const { loadBranding } = useBranding()

const { layoutAttrs, injectSkinClasses } = useSkins()

injectSkinClasses()
onMounted(() => {
  loadBranding()
})

// SECTION: Loading Indicator
const isFallbackStateActive = ref(false)
const refLoadingIndicator = ref<any>(null)

// watching if the fallback state is active and the refLoadingIndicator component is available
watch([isFallbackStateActive, refLoadingIndicator], () => {
  if (isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.fallbackHandle()

  if (!isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.resolveHandle()
}, { immediate: true })
// !SECTION
</script>

<template>
  <Component
    v-bind="layoutAttrs"
    :is="isApplicantPortalUser
      ? ApplicantPortalLayout
      : layoutConfigStore.appContentLayoutNav === 'horizontal'
        ? DefaultLayoutWithHorizontalNav
        : DefaultLayoutWithVerticalNav"
  >
    <AppLoadingIndicator ref="refLoadingIndicator" />

    <RouterView v-slot="{ Component }">
      <Suspense
        :timeout="0"
        @fallback="isFallbackStateActive = true"
        @resolve="isFallbackStateActive = false"
      >
        <Component :is="Component" />
      </Suspense>
    </RouterView>
  </Component>
</template>

<style lang="scss">
// As we are using `layouts` plugin we need its styles to be imported
@use "@layouts/styles/default-layout";

.layout-navbar .navbar-content-container,
.layout-vertical-nav {
  background-color: var(--app-navigation-color, rgb(var(--v-theme-surface))) !important;
  color: var(--app-navigation-on-color, rgb(var(--v-theme-on-surface))) !important;
  transition: background-color 0.18s ease;
}

.layout-footer .footer-content-container {
  background-color: var(--app-footer-color, rgb(var(--v-theme-surface))) !important;
  color: var(--app-footer-on-color, rgb(var(--v-theme-on-surface))) !important;
  transition: background-color 0.18s ease;
}

/* The application header is physically LTR in every locale and must never
   inherit the document direction (RTL for Dari/Pashto). Text inside it keeps
   its own writing direction, so Persian inputs and button labels still read
   right-to-left. See docs/facts/rtl-direction.md before changing this. */
.layout-navbar .navbar-content-container {
  direction: ltr;
}

[dir='rtl'] .layout-navbar .navbar-content-container input,
[dir='rtl'] .layout-navbar .navbar-content-container textarea,
[dir='rtl'] .layout-navbar .navbar-content-container .v-btn__content {
  direction: rtl;
}
</style>
