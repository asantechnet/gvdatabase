<script setup lang="ts">
defineProps<{ email: string; loading: boolean }>()

const emit = defineEmits<{ submit: []; 'update:email': [value: string] }>()
</script>

<template>
  <VForm
    class="app-form"
    @submit.prevent="emit('submit')"
  >
    <VTextField
      :model-value="email"
      type="email"
      :label="$t('common.fields.email')"
      autocomplete="email"
      required
      class="mb-4"
      @update:model-value="emit('update:email', $event)"
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
