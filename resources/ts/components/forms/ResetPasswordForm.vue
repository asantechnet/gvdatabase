<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ErpField from '@/components/erp/ErpField.vue'

const emit = defineEmits<{ togglePassword: []; submit: []; 'update:email': [value: string]; 'update:password': [value: string]; 'update:passwordConfirmation': [value: string] }>()
const props = defineProps<{ email: string; password: string; passwordConfirmation: string; loading: boolean; showPassword: boolean; disabled: boolean }>()
const { t } = useI18n()
const confirmationError = computed(() => props.passwordConfirmation && props.password !== props.passwordConfirmation ? t('pages.auth.common.passwordsDoNotMatch') : '')
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
      class="mb-3"
      @update:model-value="emit('update:email', $event.trimStart())"
      @blur="emit('update:email', email.trim())"
    />
    <ErpField
      :model-value="password"
      :type="showPassword ? 'text' : 'password'"
      :label="$t('pages.auth.resetPassword.resetPassword.newPassword')"
      autocomplete="new-password"
      :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
      required
      :hint="$t('pages.auth.common.passwordRequirement')"
      persistent-hint
      class="mb-3"
      @update:model-value="emit('update:password', $event)"
      @click:append-inner="emit('togglePassword')"
    />
    <ErpField
      :model-value="passwordConfirmation"
      :type="showPassword ? 'text' : 'password'"
      :label="$t('pages.auth.resetPassword.resetPassword.confirmNewPassword')"
      autocomplete="new-password"
      required
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
      {{ $t('pages.auth.resetPassword.resetPassword.resetPassword') }}
    </VBtn>
  </VForm>
</template>
