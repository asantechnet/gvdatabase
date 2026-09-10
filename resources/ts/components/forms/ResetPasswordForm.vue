<script setup lang="ts">
defineProps<{ email: string; password: string; passwordConfirmation: string; loading: boolean; showPassword: boolean; disabled: boolean }>()

const emit = defineEmits<{ togglePassword: []; submit: []; 'update:email': [value: string]; 'update:password': [value: string]; 'update:passwordConfirmation': [value: string] }>()
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
      class="mb-3"
      @update:model-value="emit('update:email', $event)"
    />
    <VTextField
      :model-value="password"
      :type="showPassword ? 'text' : 'password'"
      :label="$t('pages.auth.resetPassword.resetPassword.newPassword')"
      autocomplete="new-password"
      :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
      required
      class="mb-3"
      @update:model-value="emit('update:password', $event)"
      @click:append-inner="emit('togglePassword')"
    />
    <VTextField
      :model-value="passwordConfirmation"
      :type="showPassword ? 'text' : 'password'"
      :label="$t('pages.auth.resetPassword.resetPassword.confirmNewPassword')"
      autocomplete="new-password"
      required
      class="mb-4"
      @update:model-value="emit('update:passwordConfirmation', $event)"
    />
    <VBtn
      block
      type="submit"
      :loading="loading"
      :disabled="disabled"
    >
      {{ $t('pages.auth.resetPassword.resetPassword.resetPassword') }}
    </VBtn>
  </VForm>
</template>
