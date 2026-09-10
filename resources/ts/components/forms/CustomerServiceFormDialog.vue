<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{ modelValue: boolean; service: Record<string, any>; loading: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; submit: [value: Record<string, any>] }>()
const form = ref<Record<string, any>>({})

watch(() => props.modelValue, open => {
  if (open)
    form.value = { ...props.service }
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="640"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="app-form-dialog">
      <VCardTitle class="d-flex align-center ga-3">
        <VAvatar
          color="primary"
          variant="tonal"
          size="38"
        >
          <VIcon icon="tabler-calendar-due" />
        </VAvatar>
        <span>{{ $t('pages.contracts.allCustomers.allCustomers.editCustomerService') }}</span>
      </VCardTitle>
      <VForm
        class="app-form"
        @submit.prevent="emit('submit', { ...form })"
      >
        <VCardText>
          <VRow>
            <VCol cols="12">
              <p class="app-form__section">
                {{ $t('common.messages.details') }}
              </p>
              <p class="app-form__section-help">
                {{ $t('common.messages.serviceScheduleHint') }}
              </p>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.type"
                :label="$t('common.messages.serviceType')"
                prepend-inner-icon="tabler-tool"
                readonly
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.deadline"
                :label="$t('pages.contracts.allCustomers.allCustomers.deadline')"
                prepend-inner-icon="tabler-calendar"
                type="date"
                clearable
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer /><VBtn
            color="secondary"
            variant="tonal"
            @click="emit('update:modelValue', false)"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn><VBtn
            color="primary"
            prepend-icon="tabler-check"
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
