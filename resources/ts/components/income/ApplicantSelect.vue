<script setup lang="ts">
import type { Applicant } from '@/api/applicants'

defineProps<{
  modelValue: number | null | undefined
  applicants: Applicant[]
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>()
</script>

<template>
  <VAutocomplete
    :model-value="modelValue"
    :items="applicants"
    item-title="name"
    item-value="id"
    :label="$t('common.fields.applicant')"
    prepend-inner-icon="tabler-user"
    :disabled="disabled"
    clearable
    auto-select-first
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #selection="{ item }">
      <div class="applicant-select__row">
        <strong>{{ item.raw.name }}</strong>
        <span class="applicant-select__id">#{{ item.raw.id }}</span>
      </div>
    </template>

    <template #item="{ props: itemProps, item }">
      <VListItem v-bind="itemProps">
        <template #title>
          <div class="applicant-select__row">
            <strong>{{ item.raw.name }}</strong>
            <VChip
              size="x-small"
              variant="tonal"
            >
              #{{ item.raw.id }}
            </VChip>
          </div>
        </template>
        <template #subtitle>
          <span>{{ item.raw.contact || item.raw.email || item.raw.details?.country?.name || $t('common.messages.notAvailable') }}</span>
        </template>
      </VListItem>
    </template>
  </VAutocomplete>
</template>

<style scoped>
.applicant-select__row {
  display: flex;
  min-inline-size: 0;
  align-items: center;
  gap: 0.5rem;
}

.applicant-select__id {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
}
</style>
