<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  form: { email: string; password: string; remember: boolean; twoFactorCode: string; recoveryCode: string }
  loading: boolean
  showPassword: boolean
  twoFactorRequired: boolean
}>()

const emit = defineEmits<{ togglePassword: []; submit: [] }>()

// A lost authenticator must not lock the account out; the recovery code is
// accepted by the same login request.
const usingRecoveryCode = ref(false)
</script>

<template>
  <VForm
    class="app-form login-form"
    @submit.prevent="emit('submit')"
  >
    <VRow>
      <VCol cols="12">
        <VTextField
          v-model="form.email"
          name="email"
          :label="$t('common.fields.email')"
          type="email"
          autocomplete="email"
          :placeholder="$t('pages.auth.login.login.enterYourEmail')"
          :aria-label="$t('common.fields.email')"
          required
        />
      </VCol>
      <VCol cols="12">
        <VTextField
          v-model="form.password"
          name="password"
          :label="$t('common.fields.password')"
          :placeholder="$t('pages.auth.login.login.enterYourPassword')"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="current-password"
          :aria-label="$t('common.fields.password')"
          required
        >
          <template #append-inner>
            <VBtn
              icon
              size="small"
              variant="text"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
              @click="emit('togglePassword')"
            >
              <VIcon :icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'" />
            </VBtn>
          </template>
        </VTextField>
      </VCol>
      <VCol
        v-if="twoFactorRequired"
        cols="12"
      >
        <VTextField
          v-if="!usingRecoveryCode"
          v-model="form.twoFactorCode"
          name="two_factor_code"
          :label="$t('pages.auth.login.login.authenticatorCode')"
          inputmode="numeric"
          autocomplete="one-time-code"
          maxlength="6"
          prepend-inner-icon="tabler-shield-lock"
          required
        />
        <VTextField
          v-else
          v-model="form.recoveryCode"
          name="recovery_code"
          label="Recovery code"
          autocomplete="one-time-code"
          maxlength="64"
          prepend-inner-icon="tabler-key"
          required
        />
        <VBtn
          variant="text"
          size="small"
          class="px-0 mt-1"
          @click="usingRecoveryCode = !usingRecoveryCode"
        >
          {{ usingRecoveryCode ? 'Use your authenticator code' : 'Use a recovery code' }}
        </VBtn>
      </VCol>
      <VCol cols="12">
        <div class="d-flex align-center justify-space-between flex-wrap gap-3">
          <VCheckbox
            v-model="form.remember"
            :label="$t('pages.auth.login.login.rememberMe')"
            hide-details
          /><RouterLink
            class="text-primary"
            :to="{ name: 'forgot-password', query: { email: form.email || undefined } }"
          >
            {{ $t('pages.auth.login.login.forgotPassword') }}
          </RouterLink>
        </div>
      </VCol>
      <VCol cols="12">
        <VBtn
          block
          type="submit"
          :loading="loading"
        >
          {{ twoFactorRequired ? 'Verify and sign in' : 'Login' }}
        </VBtn>
      </VCol>
    </VRow>
  </VForm>
</template>

<style scoped>
.login-form :deep(.v-field__field),
.login-form :deep(.v-field__input) {
  align-items: center;
}
</style>
