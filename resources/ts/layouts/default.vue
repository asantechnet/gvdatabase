<script lang="ts" setup>
const DefaultLayoutWithVerticalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithVerticalNav.vue'))

const { layoutAttrs, injectSkinClasses } = useSkins()

injectSkinClasses()

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
  <Component v-bind="layoutAttrs" :is="DefaultLayoutWithVerticalNav">
    <AppLoadingIndicator ref="refLoadingIndicator" />

    <RouterView v-slot="{ Component }">
      <Suspense :timeout="0" @fallback="isFallbackStateActive = true" @resolve="isFallbackStateActive = false">
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
  transition: background-color 0.18s ease;
}

.layout-footer .footer-content-container {
  background-color: var(--app-footer-color, rgb(var(--v-theme-surface))) !important;
  transition: background-color 0.18s ease;
}
</style>
