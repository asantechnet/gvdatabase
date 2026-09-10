<script setup lang="ts">
import { AppContentLayoutNav } from '@layouts/enums'
import { useLayoutConfigStore } from '@layouts/stores/config'

const layoutConfigStore = useLayoutConfigStore()

const toggleLayout = () => {
  layoutConfigStore.appContentLayoutNav = layoutConfigStore.appContentLayoutNav === AppContentLayoutNav.Vertical
    ? AppContentLayoutNav.Horizontal
    : AppContentLayoutNav.Vertical
}
</script>

<template>
  <IconBtn
    class="d-none d-md-inline-flex"
    @click="toggleLayout"
  >
    <VIcon :icon="layoutConfigStore.appContentLayoutNav === AppContentLayoutNav.Vertical ? 'tabler-layout-navbar' : 'tabler-layout-sidebar-left-expand'" />
    <VTooltip
      activator="parent"
      location="bottom"
    >
      {{ layoutConfigStore.appContentLayoutNav === AppContentLayoutNav.Vertical ? 'Use horizontal navigation' : 'Use vertical navigation' }}
    </VTooltip>
  </IconBtn>
</template>
