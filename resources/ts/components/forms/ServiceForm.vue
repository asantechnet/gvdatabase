<script setup lang="ts">
import { ref, watch } from 'vue'

interface ServiceFormData {
  type: string
  description?: string | null
  fee: number
  currency_id: number
  status: 'active' | 'inactive'
}

const props = defineProps<{
  modelValue: boolean
  initialItem: ServiceFormData
  currencyOptions: Array<{ title: string; value: number }>
  loading: boolean
  title: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: [value: ServiceFormData]
}>()

const form = ref<ServiceFormData>({ ...props.initialItem })

watch(() => props.modelValue, isOpen => {
  if (isOpen)
    form.value = { ...props.initialItem }
})

function close() {
  if (!props.loading)
    emit('update:modelValue', false)
}

function submit() {
  emit('submit', { ...form.value, fee: Number(form.value.fee) })
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="600"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      class="app-form-dialog"
      :title="title"
    >
      <VForm
        class="app-form"
        @submit.prevent="submit"
      >
        <VCardText>
          <VRow>
            <VCol cols="12">
              <p class="app-form__section">
                {{ $t('pages.contracts.services.services.serviceDefinition') }}
              </p><p class="app-form__section-help">
                {{ $t('pages.contracts.services.services.serviceDefinitionHint') }}
              </p>
            </VCol>
            <VCol
              cols="12"
              md="8"
            >
              <VTextField
                v-model="form.type"
                label="Service name"
                :placeholder="$t('pages.contracts.services.services.eGWebsiteMaintenance')"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VSelect
                v-model="form.currency_id"
                :items="currencyOptions"
                :label="$t('common.fields.currency')"
                item-title="title"
                item-value="value"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.description" label="Description" rows="2" />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model.number="form.fee"
                :label="$t('pages.contracts.services.services.fee')"
                type="number"
                min="0"
                step="0.01"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.status"
                :items="['active', 'inactive']"
                :label="$t('common.fields.status')"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="justify-end gap-2 pa-4">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="loading"
            @click="close"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            type="submit"
            :loading="loading"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VForm>
    </VCard>
  </VDialog>
</template>
