<script setup lang="ts">
import type { IncomePayerOption } from '@/api/incomes'
import ErpField from '@/components/erp/ErpField.vue'

defineProps<{
  modelValue: string | null | undefined
  payers: IncomePayerOption[]
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()
</script>

<template>
  <ErpField
    kind="autocomplete"
    :model-value="modelValue"
    :items="payers"
    item-title="name"
    item-value="key"
    :label="$t('common.fields.applicantOrStudent')"
    :placeholder="$t('common.fields.applicantOrStudent')"
    prepend-inner-icon="tabler-users"
    :disabled="disabled"
    clearable
    auto-select-first
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #selection="{ item }">
      <div class="payer-select__row">
        <strong>{{ item.raw.name }}</strong>
        <span class="payer-select__id">
          {{ item.raw.reference || `#${item.raw.id}` }}
        </span>
      </div>
    </template>

    <template #item="{ props: itemProps, item }">
      <VListItem v-bind="itemProps">
        <template #title>
          <div class="payer-select__row">
            <strong>{{ item.raw.name }}</strong>
            <VChip
              size="x-small"
              variant="tonal"
              :color="item.raw.type === 'student' ? 'info' : 'primary'"
            >
              {{ item.raw.type === 'student' ? $t('common.fields.student') : $t('common.fields.applicant') }}
            </VChip>
            <span class="payer-select__id">
              {{ item.raw.reference || `#${item.raw.id}` }}
            </span>
          </div>
        </template>
        <template #subtitle>
          <span>{{ item.raw.contact || $t('common.messages.notAvailable') }}</span>
        </template>
      </VListItem>
    </template>
  </ErpField>
</template>

<style scoped>
.payer-select__row {
  display: flex;
  min-inline-size: 0;
  align-items: center;
  gap: 0.5rem;
}

.payer-select__id {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
}
</style>
