<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
const from = defineModel<string>('from', { default: '' })
const to = defineModel<string>('to', { default: '' })
const emit = defineEmits<{ apply: [] }>()
withDefaults(defineProps<{ showApply?: boolean; inline?: boolean }>(), { showApply: true, inline: true })
const { t } = useI18n()
const invalid = computed(() => !!from.value && !!to.value && from.value > to.value)
</script>

<template>
  <div class="erp-date-range" :class="{ 'erp-date-range--inline': inline }">
    <div class="erp-field erp-date-range__field">
      <label class="erp-field-label">{{ t('erp.fromDate') }}</label>
      <AppDateTimePicker
        v-model="from"
        class="erp-field-control"
        :placeholder="t('erp.fromDate')"
        persistent-placeholder
        hide-details="auto"
        :config="{
          dateFormat: 'Y-m-d',
          maxDate: to || undefined,
          closeOnSelect: false,
        }"
      />
    </div>
    <div class="erp-field erp-date-range__field">
      <label class="erp-field-label">{{ t('erp.toDate') }}</label>
      <AppDateTimePicker
        v-model="to"
        class="erp-field-control"
        :placeholder="t('erp.toDate')"
        persistent-placeholder
        hide-details="auto"
        :error-messages="invalid ? t('erp.invalidRange') : []"
        :config="{
          dateFormat: 'Y-m-d',
          minDate: from || undefined,
          closeOnSelect: false,
        }"
      />
    </div>
    <div v-if="showApply" class="erp-date-range__actions">
      <VBtn variant="flat" color="primary" :disabled="invalid" @click="emit('apply')">{{ t('educational.apply') }}</VBtn>
    </div>
  </div>
</template>

<style scoped>
.erp-date-range {
  display: grid;
  gap: 0.75rem;
}

.erp-date-range--inline {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.erp-date-range__actions {
  display: flex;
  grid-column: 1 / -1;
  justify-content: flex-end;
}

@media (max-width: 420px) {
  .erp-date-range--inline {
    grid-template-columns: 1fr;
  }
}
</style>
