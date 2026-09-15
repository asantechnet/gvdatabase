<script setup lang="ts">
import { useRoute } from 'vue-router'
import { startPageGuide } from '@/composables/usePageGuide'

const route = useRoute()
const { t } = useI18n()

function openGuide() {
  startPageGuide(route)
}
</script>

<template>
  <IconBtn
    :aria-label="t('common.pageGuide.open')"
    @click="openGuide"
  >
    <VIcon icon="tabler-help-circle" />
    <VTooltip activator="parent">
      {{ t('common.pageGuide.open') }}
    </VTooltip>
  </IconBtn>
</template>
