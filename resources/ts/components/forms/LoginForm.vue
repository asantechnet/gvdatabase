<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import ErpField from '@/components/erp/ErpField.vue'

const props = defineProps<{
  form: { email: string; password: string; remember: boolean; twoFactorCode: string; recoveryCode: string }
  loading: boolean
  showPassword: boolean
  twoFactorRequired: boolean
}>()

const emit = defineEmits<{ togglePassword: []; submit: [] }>()

// A lost authenticator must not lock the account out; the recovery code is
// accepted by the same login request.
const usingRecoveryCode = ref(false)
const twoFactorInput = ref<{ focus: () => void } | null>(null)

watch(() => props.twoFactorRequired, async required => {
  if (required) {
    await nextTick()
    twoFactorInput.value?.focus()
  }
})
</script>

<template>
  <VForm
    class="app-form login-form"
    @submit.prevent="emit('submit')"
  >
    <VRow>
      <VCol cols="12">
        <ErpField
          v-model="form.email"
          name="email"
          :label="$t('common.fields.email')"
          type="email"
          autocomplete="email"
          :placeholder="$t('pages.auth.login.login.enterYourEmail')"
          required
          validate-on="blur"
          @blur="form.email = form.email.trim()"
        />
      </VCol>
      <VCol cols="12">
        <ErpField
          v-model="form.password"
          name="password"
          :label="$t('common.fields.password')"
          :placeholder="$t('pages.auth.login.login.enterYourPassword')"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="current-password"
          :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
          required
          @click:append-inner="emit('togglePassword')"
        />
      </VCol>
      <VCol
        v-if="twoFactorRequired"
        cols="12"
      >
        <p class="text-body-2 text-medium-emphasis mb-3">
          {{ $t('pages.auth.login.passwordAccepted') }}
        </p>
        <ErpField
          v-if="!usingRecoveryCode"
          ref="twoFactorInput"
          v-model="form.twoFactorCode"
          name="two_factor_code"
          :label="$t('pages.auth.login.login.authenticatorCode')"
          inputmode="numeric"
          autocomplete="one-time-code"
          maxlength="6"
          prepend-inner-icon="tabler-shield-lock"
          required
        />
        <ErpField
          v-else
          ref="twoFactorInput"
          v-model="form.recoveryCode"
          name="recovery_code"
          :label="$t('pages.auth.login.recoveryCode')"
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
          {{ $t(usingRecoveryCode ? 'pages.auth.login.useAuthenticatorCode' : 'pages.auth.login.useRecoveryCode') }}
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
          {{ $t(twoFactorRequired ? 'pages.auth.login.verifyAndSignIn' : 'pages.auth.login.signIn') }}
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
