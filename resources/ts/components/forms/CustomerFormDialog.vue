<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{ modelValue: boolean; customer: Record<string, any>; marketers: unknown[]; editing: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; submit: [value: Record<string, any>] }>()
const form = ref<Record<string, any>>({})

watch(() => props.modelValue, open => {
  if (open)
    form.value = { ...props.customer }
})
</script>

<template>
  <VDialog :model-value="modelValue" max-width="800px" @update:model-value="emit('update:modelValue', $event)">
    <VCard class="app-form-dialog">
      <VCardTitle><span class="text-h5">{{ editing ? 'Edit Customer' : 'Add New Customer' }}</span><div class="text-caption text-medium-emphasis mt-1">Keep the customer profile complete so contracts can be prepared faster.</div></VCardTitle>
      <VForm class="app-form" @submit.prevent="emit('submit', { ...form })"><VCardText><VRow>
        <VCol cols="12"><p class="app-form__section">Primary contact</p><p class="app-form__section-help">Use the organisation name and the best day-to-day contact method.</p></VCol>
        <VCol cols="12" md="6"><VTextField v-model="form.name" :label="$t('pages.contracts.allCustomers.allCustomers.fullName')" required /></VCol>
        <VCol cols="12" md="6"><VTextField v-model="form.company" :label="$t('common.fields.company')" required /></VCol>
        <VCol cols="12" md="6"><VTextField v-model="form.contact" :label="$t('common.fields.phone')" required /></VCol>
        <VCol cols="12"><VTextarea v-model="form.address" label="Address" rows="2" /></VCol>
        <VCol cols="12" md="6"><VTextField v-model="form.tax_number" label="Tax number" /></VCol>
        <VCol cols="12" md="6"><VAutocomplete v-model="form.marketer" :items="marketers" item-title="name" item-value="name" :label="$t('common.messages.marketer')" :placeholder="$t('common.messages.selectAMarketer')" :no-data-text="$t('pages.contracts.allCustomers.allCustomers.addMarketersFromSetupMarketers')" clearable /></VCol>
        <VCol cols="12" md="6"><VTextField v-model="form.date" :label="$t('common.fields.date')" type="date" required /></VCol>
      </VRow></VCardText><VCardActions><VSpacer /><VBtn color="secondary" variant="tonal" @click="emit('update:modelValue', false)">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" type="submit">{{ $t('common.actions.save') }}</VBtn></VCardActions></VForm>
    </VCard>
  </VDialog>
</template>
