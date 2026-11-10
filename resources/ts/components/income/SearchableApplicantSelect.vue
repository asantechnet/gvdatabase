<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { getIncomeApplicants, type IncomeApplicantOption } from '@/api/incomes'
import { apiErrorMessage } from '@/utils/apiErrors'
import ErpField from '@/components/erp/ErpField.vue'

const props = defineProps<{ modelValue: number | null; active: boolean; disabled?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>()
const applicants = ref<IncomeApplicantOption[]>([])
const loading = ref(false)
const error = ref('')
let generation = 0
let timer: ReturnType<typeof setTimeout> | undefined

async function load(search = '') {
  const request = ++generation
  loading.value = true
  error.value = ''
  try {
    const response = await getIncomeApplicants(search)
    if (request !== generation || !props.active)
      return
    const selected = applicants.value.find(item => item.id === props.modelValue)
    applicants.value = selected && !response.some(item => item.id === selected.id) ? [selected, ...response] : response
  }
  catch (cause) {
    if (request === generation)
      error.value = apiErrorMessage(cause)
  }
  finally {
    if (request === generation)
      loading.value = false
  }
}

function search(value: string) {
  clearTimeout(timer)
  ++generation
  if (props.active)
    timer = setTimeout(() => load(value), 250)
}

watch(() => props.active, active => {
  clearTimeout(timer)
  ++generation
  loading.value = false
  if (active)
    void load()
}, { immediate: true })

onBeforeUnmount(() => {
  clearTimeout(timer)
  ++generation
})
</script>

<template>
  <ErpField
    :model-value="modelValue" kind="autocomplete" :items="applicants" item-title="name" item-value="id"
    :label="$t('common.fields.applicant')" :placeholder="$t('common.fields.applicant')" :loading="loading" :disabled="disabled"
    :error-messages="error" no-filter clearable
    @update:model-value="emit('update:modelValue', $event)" @update:search="search"
  />
</template>
