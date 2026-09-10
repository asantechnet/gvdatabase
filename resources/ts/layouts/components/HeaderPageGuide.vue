<script setup lang="ts">
import { useRoute } from 'vue-router'
import { startPageGuide } from '@/composables/usePageGuide'

const route = useRoute()

function openGuide() {
  startPageGuide(route)
}
</script>

<template>
  <IconBtn
    aria-label="Guide for this page"
    @click="openGuide"
  >
    <VIcon icon="tabler-help-circle" />
    <VTooltip activator="parent">
      Guide for this page
    </VTooltip>
  </IconBtn>
</template>
