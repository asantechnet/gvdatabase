<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  enabled: boolean
  options: Array<{ value: string; title: string }>
  rate: (service: string) => number | null
}>()
const emit = defineEmits<{
  (event: 'update:enabled', value: boolean): void
  (event: 'update:rate', service: string, value: unknown): void
}>()
const { t } = useI18n()
</script>

<template>
  <VCol cols="12">
    <VSwitch
      :model-value="props.enabled"
      color="primary"
      :label="t('pages.settings.staffList.staffList.commissionBased')"
      :hint="t('pages.settings.staffList.staffList.commissionBasedHint')"
      persistent-hint
      @update:model-value="emit('update:enabled', Boolean($event))"
    />
  </VCol>
  <VCol v-if="props.enabled" cols="12">
    <VCard variant="outlined" class="pa-4">
      <div class="text-subtitle-2 mb-3">
        {{ t('pages.settings.staffList.staffList.commissionPerService') }}
      </div>
      <VRow>
        <VCol
          v-for="service in props.options"
          :key="service.value"
          cols="12"
          sm="6"
          md="3"
        >
          <VTextField
            :model-value="props.rate(service.value)"
            type="number"
            min="0"
            max="100"
            step="0.01"
            suffix="%"
            :label="service.title"
            @update:model-value="emit('update:rate', service.value, $event)"
          />
        </VCol>
      </VRow>
    </VCard>
  </VCol>
</template>
