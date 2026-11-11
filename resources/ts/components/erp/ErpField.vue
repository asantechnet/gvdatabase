<script setup lang="ts">
import { computed, ref, useId } from 'vue'
import { VAutocomplete, VCombobox, VSelect, VTextField, VTextarea } from 'vuetify/components'
import { useI18n } from 'vue-i18n'

defineOptions({ inheritAttrs: false })
const props = withDefaults(defineProps<{
  label: string
  kind?: 'text' | 'select' | 'autocomplete' | 'combobox' | 'textarea' | 'date'
  required?: boolean
  rules?: Array<(value: unknown) => boolean | string>
  /**
   * Some forms show their own errors after Save. Keep the required marker in
   * those forms without asking Vuetify to validate while the user is typing.
   */
  showRequiredRule?: boolean
  validateOn?: string
}>(), {
  kind: 'text',
  required: false,
  showRequiredRule: true,
  // Keep a quiet form while people are entering information. Forms can still
  // validate on their Save/submit action, without showing random empty-field
  // messages simply because a control lost focus.
  validateOn: 'submit',
})
const model = defineModel<any>()
const { t } = useI18n()
const fieldId = useId()
const inputRef = ref<{ focus?: () => void } | null>(null)
const input = computed(() => {
  if (props.kind === 'select')
    return VSelect
  if (props.kind === 'autocomplete')
    return VAutocomplete
  if (props.kind === 'combobox')
    return VCombobox
  if (props.kind === 'textarea')
    return VTextarea

  return VTextField
})
const requiredRule = (value: unknown) => (Array.isArray(value) ? value.length > 0 : value !== null && value !== undefined && String(value).trim() !== '') || t('erp.required')

defineExpose({
  focus: () => inputRef.value?.focus?.(),
})
</script>

<template>
  <div class="erp-field">
    <label
      class="erp-field-label"
      :for="fieldId"
    >
      {{ label }} <span
        v-if="required"
        class="erp-field-required"
        aria-hidden="true"
      >*</span>
    </label>
    <AppDateTimePicker
      v-if="kind === 'date'"
      :id="fieldId"
      ref="inputRef"
      v-model="model"
      v-bind="$attrs"
      :aria-label="label"
      :aria-required="required || undefined"
      class="erp-field-control"
    />
    <component
      v-else
      :is="input"
      :id="fieldId"
      ref="inputRef"
      v-model="model"
      v-bind="$attrs"
      :aria-label="label"
      :aria-required="required || undefined"
      :required="required && showRequiredRule"
      :rules="[...(rules ?? []), ...(required && showRequiredRule ? [requiredRule] : [])]"
      :validate-on="validateOn"
      variant="outlined"
      density="compact"
      hide-details="auto"
      class="erp-field-control"
    >
      <slot />
      <template
        v-if="$slots.selection"
        #selection="slotProps"
      >
        <slot name="selection" v-bind="slotProps" />
      </template>
      <template
        v-if="$slots.item"
        #item="slotProps"
      >
        <slot name="item" v-bind="slotProps" />
      </template>
    </component>
  </div>
</template>

<style>
.erp-field .v-input {
  /* Vuetify sets 40px directly on .v-input--density-compact. Override that
     local variable here; setting it only on the outer wrapper has no effect. */
  --v-input-control-height: 38px;
}

.erp-field .v-input.v-input--density-compact {
  --v-input-control-height: 38px !important;
}

.erp-field-label {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  margin-block-end: 6px;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.25;
}

.erp-field-required {
  color: rgb(var(--v-theme-error));
}

.erp-field .v-field {
  --v-field-input-padding-top: 4px;
  --v-field-input-padding-bottom: 4px;
  /* `.app-form .v-field` previously forced these visitor controls to 48px.
     Keep the reusable single-line control at its intended compact height. */
  min-block-size: 38px !important;
  block-size: 38px;
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
  outline: 3px solid transparent;
  outline-offset: 0;
  transition: background-color 0.2s ease, outline-color 0.2s ease;
}

.erp-field .v-field__outline {
  --v-field-border-opacity: 0.44;
}

.erp-field .v-field--focused {
  outline-color: rgba(var(--v-theme-primary), 0.2);
}

.erp-field .v-field--focused .v-field__outline {
  --v-field-border-opacity: 1;
  color: rgb(var(--v-theme-primary));
  transition: color 0.2s ease;
}

.erp-field .v-field__input {
  align-items: center;
  color: inherit;
  column-gap: 2px;
  display: flex;
  flex-wrap: wrap;
  letter-spacing: 0.009375em;
  opacity: var(--v-high-emphasis-opacity);
  min-height: 38px !important;
  min-width: 0;
  padding-block: var(--v-field-input-padding-top) var(--v-field-input-padding-bottom);
  padding-inline: var(--v-field-padding-start) var(--v-field-padding-end);
  position: relative;
  width: 100%;
}

/* Textareas use the same border, label and focus treatment while preserving
   their requested multi-line height instead of inheriting the 38px control. */
.erp-field .v-textarea .v-field {
  block-size: auto;
  min-block-size: 150px !important;
}

.erp-field .v-textarea .v-field__input {
  align-items: flex-start;
  min-height: 150px !important;
}

.erp-field .v-field--error {
  outline-color: rgba(var(--v-theme-error), 0.24);
}

@media (max-width: 600px) {
  .erp-field .v-input {
    --v-input-control-height: 36px;
  }

  .erp-field .v-input.v-input--density-compact {
    --v-input-control-height: 36px !important;
  }

  .erp-field .v-field__input {
    min-height: 36px !important;
  }

  .erp-field .v-field {
    min-block-size: 36px !important;
    block-size: 36px;
    border-radius: 8px;
  }

  .erp-field .v-textarea .v-field,
  .erp-field .v-textarea .v-field__input {
    min-block-size: 132px !important;
  }
}
</style>
