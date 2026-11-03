<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const emit = defineEmits<{ submit: []; togglePassword: []; 'update:password': [value: string]; 'update:passwordConfirmation': [value: string] }>()
const props = defineProps<{ email: string; password: string; passwordConfirmation: string; loading: boolean; showPassword: boolean; disabled: boolean }>()
const { t } = useI18n()
const confirmationError = computed(() => props.passwordConfirmation && props.password !== props.passwordConfirmation ? t('pages.auth.common.passwordsDoNotMatch') : '')
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
      readonly
      class="mb-3"
    />
    <VTextField
      :model-value="password"
      :type="showPassword ? 'text' : 'password'"
      :label="$t('common.fields.password')"
      autocomplete="new-password"
      :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
      :hint="$t('pages.auth.common.passwordRequirement')"
      persistent-hint
      class="mb-3"
      @update:model-value="emit('update:password', $event)"
      @click:append-inner="emit('togglePassword')"
    />
    <VTextField
      :model-value="passwordConfirmation"
      :type="showPassword ? 'text' : 'password'"
      :label="$t('pages.auth.createPassword.createPassword.confirmPassword')"
      autocomplete="new-password"
      :error="Boolean(confirmationError)"
      :error-messages="confirmationError"
      validate-on="blur"
      class="mb-4"
      @update:model-value="emit('update:passwordConfirmation', $event)"
    />
    <VBtn
      block
      type="submit"
      :loading="loading"
      :disabled="disabled"
    >
      {{ $t('pages.auth.createPassword.createPassword.createPassword') }}
    </VBtn>
  </VForm>
</template>
