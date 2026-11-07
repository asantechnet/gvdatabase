<script setup lang="ts">
import ErpField from '@/components/erp/ErpField.vue'

defineProps<{ email: string; loading: boolean }>()

const emit = defineEmits<{ submit: []; 'update:email': [value: string] }>()
</script>

<template>
  <VForm
    class="app-form"
    @submit.prevent="emit('submit')"
  >
    <ErpField
      :model-value="email"
      type="email"
      :label="$t('common.fields.email')"
      autocomplete="email"
      required
      validate-on="blur"
      class="mb-4"
      @update:model-value="emit('update:email', $event.trimStart())"
      @blur="emit('update:email', email.trim())"
    />
    <VBtn
      block
      type="submit"
      :loading="loading"
    >
      {{ $t('pages.auth.forgotPassword.forgotPassword.sendResetLink') }}
    </VBtn>
  </VForm>
</template>
