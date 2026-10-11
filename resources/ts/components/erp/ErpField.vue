<script setup lang="ts">
import { computed } from 'vue'
import { VTextField, VSelect, VTextarea } from 'vuetify/components'
import { useI18n } from 'vue-i18n'

defineOptions({ inheritAttrs: false })
const props = defineProps<{ label: string; kind?: 'text' | 'select' | 'textarea'; required?: boolean; rules?: Array<(value: any) => boolean | string> }>()
const model = defineModel<any>()
const { t } = useI18n()
const input = computed(() => props.kind === 'select' ? VSelect : props.kind === 'textarea' ? VTextarea : VTextField)
const requiredRule = (value: unknown) => (Array.isArray(value) ? value.length > 0 : value !== null && value !== undefined && String(value).trim() !== '') || t('erp.required')
</script>

<template>
  <div class="erp-field">
    <label class="erp-field-label">{{ label }} <span v-if="required" class="text-error" aria-hidden="true">*</span>
      <component :is="input" v-model="model" v-bind="$attrs" :aria-label="label" :aria-required="required || undefined" :required="required" :rules="[...(rules ?? []), ...(required ? [requiredRule] : [])]" variant="outlined" density="compact" hide-details="auto" class="mt-1" />
    </label>
  </div>
</template>
