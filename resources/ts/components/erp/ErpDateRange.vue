<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
const from = defineModel<string>('from', { default: '' })
const to = defineModel<string>('to', { default: '' })
const emit = defineEmits<{ apply: [] }>()
withDefaults(defineProps<{ showApply?: boolean }>(), { showApply: true })
const { t } = useI18n()
const invalid = computed(() => !!from.value && !!to.value && from.value > to.value)
</script>

<template>
  <div class="erp-date-range">
    <VTextField v-model="from" type="date" :label="t('erp.fromDate')" :max="to || undefined" density="compact" hide-details="auto" />
    <VTextField v-model="to" type="date" :label="t('erp.toDate')" :min="from || undefined" density="compact" hide-details="auto" :error-messages="invalid ? t('erp.invalidRange') : []" />
    <VBtn v-if="showApply" variant="tonal" color="primary" :disabled="invalid" @click="emit('apply')">{{ t('educational.apply') }}</VBtn>
  </div>
</template>
