<script setup lang="ts">
import { useI18n } from 'vue-i18n'

withDefaults(defineProps<{
  active?: boolean
}>(), {
  active: false,
})

defineEmits<{ reset: [] }>()

const { t } = useI18n()
</script>

<template>
  <VMenu :close-on-content-click="false" location="bottom end">
    <template #activator="{ props: menuProps }">
      <VBtn v-bind="menuProps" icon="tabler-filter" variant="text" size="small"
        :color="active ? 'primary' : undefined" :aria-label="t('common.messages.filters')">
        <VIcon icon="tabler-filter" />
        <VTooltip activator="parent" location="top">{{ t('common.messages.filters') }}</VTooltip>
      </VBtn>
    </template>

    <VCard min-width="280">
      <VCardItem>
        <VCardTitle class="text-body-1 font-weight-medium">{{ t('common.messages.filters') }}</VCardTitle>
        <template #append>
          <VBtn variant="text" size="small" :disabled="!active" @click="$emit('reset')">
            {{ t('common.messages.reset') }}
          </VBtn>
        </template>
      </VCardItem>

      <VCardText class="pt-0">
        <div class="d-flex flex-column ga-3">
          <slot />
        </div>
      </VCardText>
    </VCard>
  </VMenu>
</template>
