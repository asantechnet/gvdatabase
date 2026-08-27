<script lang="ts" setup>
import { useFullscreen } from '@vueuse/core'

const { isFullscreen, toggle } = useFullscreen()
</script>

<template>
  <IconBtn @click="toggle">
    <VIcon :icon="isFullscreen ? 'tabler-minimize' : 'tabler-maximize'" />
  </IconBtn>
</template>
