<script setup lang="ts">
import { useI18n } from 'vue-i18n'

withDefaults(defineProps<{
  active?: boolean
  showReset?: boolean
  persistent?: boolean
}>(), {
  active: false,
  showReset: true,
  persistent: false,
})

defineEmits<{ reset: [] }>()

const { t } = useI18n()
const open = defineModel<boolean>({ default: false })
</script>

<template>
  <VMenu
    v-model="open"
    :close-on-content-click="false"
    :persistent="persistent"
    location="bottom end"
  >
    <template #activator="{ props: menuProps }">
      <VBtn
        v-bind="menuProps"
        icon="tabler-filter"
        variant="tonal"
        size="small"
        class="data-table-filter-menu__trigger"
        :color="active ? 'primary' : undefined"
        :aria-label="t('common.messages.filters')"
      >
        <VIcon icon="tabler-filter" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ t('common.messages.filters') }}
        </VTooltip>
      </VBtn>
    </template>

    <VCard class="data-table-filter-menu">
      <VCardText>
        <div class="d-flex flex-column ga-3">
          <slot />
        </div>
        <div
          v-if="$slots.actions || showReset"
          class="data-table-filter-menu__actions"
        >
          <slot name="actions">
            <VBtn
              variant="tonal"
              color="primary"
              :disabled="!active"
              @click="$emit('reset')"
            >
              {{ t('common.messages.reset') }}
            </VBtn>
          </slot>
        </div>
      </VCardText>
    </VCard>
  </VMenu>
</template>

<style scoped>
.data-table-filter-menu {
  inline-size: min(390px, calc(100vw - 24px));
  max-block-size: 75vh;
  overflow-y: auto;
}

.data-table-filter-menu__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-block-start: 1rem;
}
</style>
