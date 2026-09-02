<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const props = defineProps<{ titleKey: string; descriptionKey?: string; icon?: string }>()
const { t } = useI18n()
</script>

<template>
  <VCol cols="12" class="form-section pt-5 pb-1">
    <div class="d-flex align-center ga-3">
      <VAvatar size="36" color="primary" variant="tonal"><VIcon :icon="icon || 'tabler-forms'" size="19" /></VAvatar>
      <div><div class="text-subtitle-1 font-weight-bold">{{ t(props.titleKey) }}</div><div v-if="descriptionKey" class="text-caption text-medium-emphasis">{{ t(props.descriptionKey) }}</div></div>
    </div>
    <VDivider class="mt-3" />
  </VCol>
</template>
