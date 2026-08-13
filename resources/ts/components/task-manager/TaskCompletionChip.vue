<script setup lang="ts">
defineProps<{ progress: number }>()
const { t } = useI18n()
</script>

<template>
  <VChip v-if="progress === 100" color="success" size="small" variant="flat" label>
    <VIcon start icon="tabler-circle-check-filled" size="16" />
    {{ t('common.statuses.completed') }}
  </VChip>
</template>
