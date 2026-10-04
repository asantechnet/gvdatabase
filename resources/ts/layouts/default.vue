<script lang="ts" setup>
import { onMounted } from 'vue'
import { useCookie } from '@core/composable/useCookie'
import { useLayoutConfigStore } from '@layouts/stores/config'
import AutomaticAttendanceTracker from '@/components/attendance/AutomaticAttendanceTracker.vue'
import { useBranding } from '@/composables/useBranding'
import { useAutomaticAttendance } from '@/composables/useAutomaticAttendance'

const ApplicantPortalLayout = defineAsyncComponent(() => import('./components/ApplicantPortalLayout.vue'))
const DefaultLayoutWithVerticalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithVerticalNav.vue'))
const DefaultLayoutWithHorizontalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithHorizontalNav.vue'))
const layoutConfigStore = useLayoutConfigStore()
const userData = useCookie<{ roles?: Array<string | { name?: string }> } | null>('userData')
const isApplicantPortalUser = computed(() => userData.value?.roles?.some(role =>
  (typeof role === 'string' ? role : role.name) === 'applicant') ?? false)
const { loadBranding } = useBranding()
const { enabled: automaticAttendanceEnabled, load: loadAutomaticAttendance } = useAutomaticAttendance()

const { layoutAttrs, injectSkinClasses } = useSkins()

injectSkinClasses()
onMounted(() => {
  loadBranding()
  loadAutomaticAttendance()
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
    <AutomaticAttendanceTracker v-if="automaticAttendanceEnabled" />

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

/* Navigation remains physically stable in RTL: controls retain their LTR
   positions while each label can still render in its own writing direction. */
[dir='rtl'] .layout-navbar .navbar-content-container {
  direction: ltr;
}

[dir='rtl'] .layout-navbar .navbar-content-container input,
[dir='rtl'] .layout-navbar .navbar-content-container .v-btn__content {
  direction: rtl;
}
</style>
