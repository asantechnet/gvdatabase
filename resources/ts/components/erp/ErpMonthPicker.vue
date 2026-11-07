<script setup lang="ts">
import { useId } from 'vue'

defineOptions({ inheritAttrs: false })

defineProps<{
  label: string
}>()

const model = defineModel<string>({ default: '' })
const fieldId = useId()
</script>

<template>
  <div class="erp-field">
    <label
      class="erp-field-label"
      :for="fieldId"
    >
      {{ label }}
    </label>
    <VTextField
      :id="fieldId"
      v-model="model"
      type="month"
      v-bind="$attrs"
      :aria-label="label"
      variant="outlined"
      density="compact"
      hide-details="auto"
      class="erp-field-control"
    />
  </div>
</template>
