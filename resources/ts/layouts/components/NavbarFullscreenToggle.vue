<script lang="ts" setup>
import { useFullscreen } from '@vueuse/core'
import { Icon } from '@iconify/vue'

const { isFullscreen, toggle } = useFullscreen()
</script>

<template>
  <VBtn
    icon
    variant="text"
    size="default"
    @click="toggle"
  >
    <Icon
      :icon="isFullscreen ? 'mdi:fullscreen-exit' : 'mdi:fullscreen'"
      :inline="true"
      width="40"
      height="40"
    />
  </VBtn>
</template>
