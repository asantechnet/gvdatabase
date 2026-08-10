<script setup lang="ts">
import { ref, watch } from 'vue'

interface ContractFormData {
  template_id: number
  status: 'Active' | 'Expired' | 'Pending'
  start_date: string
  counterparty: string
}

interface ContractTemplate {
  id: number
  name: string
  type: string
}

const props = defineProps<{
  contract?: {
    id: number
    contract: string
    status: 'Active' | 'Expired' | 'Pending'
    start_date: string
    counterparty: string
    template_id: number
  }
  templates: ContractTemplate[]
}>()

const emit = defineEmits(['submit', 'cancel'])

const form = ref<ContractFormData>({
  template_id: props.templates[0]?.id || 0,
  status: props.contract?.status || 'Active',
  start_date: props.contract?.start_date || new Date().toISOString().substr(0, 10),
  counterparty: props.contract?.counterparty || ''
})

const statusOptions = [
  { title: 'Active', value: 'Active' },
  { title: 'Expired', value: 'Expired' },
  { title: 'Pending', value: 'Pending' }
]

watch(() => props.contract, (newVal) => {
  if (newVal) {
    form.value = {
      template_id: newVal.template_id,
      status: newVal.status,
      start_date: newVal.start_date,
      counterparty: newVal.counterparty
    }
  }
}, { immediate: true })

const submit = () => {
  emit('submit', form.value)
}

const cancel = () => {
  emit('cancel')
}
</script>

<template>
  <VForm @submit.prevent="submit">
    <VRow>
      <VCol cols="12">
        <VSelect
          v-model="form.template_id"
          :items="templates"
          item-title="name"
          item-value="id"
          label="Contract Template"
          :rules="[v => !!v || 'Template is required']"
          required
        />
      </VCol>

      <VCol cols="12">
        <VTextField
          v-model="form.counterparty"
          label="Counterparty"
          :rules="[v => !!v || 'Counterparty is required']"
          required
        />
      </VCol>

      <VCol cols="12" md="6">
        <VSelect
          v-model="form.status"
          :items="statusOptions"
          label="Status"
          :rules="[v => !!v || 'Status is required']"
          required
        />
      </VCol>

      <VCol cols="12" md="6">
        <VTextField
          v-model="form.start_date"
          label="Start Date"
          type="date"
          :rules="[v => !!v || 'Start date is required']"
          required
        />
      </VCol>
    </VRow>

    <VCardActions class="justify-end pt-4">
      <VBtn variant="outlined" color="secondary" @click="cancel">
        Cancel
      </VBtn>
      <VBtn type="submit" color="primary">
        Save
      </VBtn>
    </VCardActions>
  </VForm>
</template>
