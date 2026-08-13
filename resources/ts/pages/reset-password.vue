<script setup lang="ts">
import AuthApi from '@/api/auth'
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const token = typeof route.query.token === 'string' ? route.query.token : ''
const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref(token ? '' : translate('pages.auth.resetPassword.missingToken'))

const submit = async () => {
  error.value = ''
  if (!token || !email.value) {
    error.value = translate('pages.auth.resetPassword.resetPassword.thisPasswordResetLinkIsIncompleteOrInvalid')
    return
  }
  if (password.value.length < 8) {
    error.value = translate('common.messages.passwordMustContainAtLeast8Characters')
    return
  }
  if (password.value !== passwordConfirmation.value) {
    error.value = translate('common.messages.passwordConfirmationDoesNotMatch')
    return
  }

  loading.value = true
  try {
    await AuthApi.resetPassword({
      email: email.value,
      token,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    await router.replace({ name: 'login', query: { reset: '1' } })
  } catch (err: any) {
    error.value = err.errors?.password?.[0] || err.message || 'Unable to reset this password.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page d-flex align-center justify-center pa-4">
    <VCard width="448" class="pa-4">
      <VCardItem :title="$t('pages.auth.resetPassword.resetPassword.setANewPassword')">
        <template #prepend><VIcon icon="tabler-key" color="primary" /></template>
      </VCardItem>
      <VCardText>
        <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
        <VForm @submit.prevent="submit">
          <VTextField v-model="email" type="email" :label="$t('common.fields.email')" autocomplete="email" required class="mb-3" />
          <VTextField v-model="password" :type="showPassword ? 'text' : 'password'" :label="$t('pages.auth.resetPassword.resetPassword.newPassword')"
            autocomplete="new-password" :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
            required class="mb-3" @click:append-inner="showPassword = !showPassword" />
          <VTextField v-model="passwordConfirmation" :type="showPassword ? 'text' : 'password'"
            :label="$t('pages.auth.resetPassword.resetPassword.confirmNewPassword')" autocomplete="new-password" required class="mb-4" />
          <VBtn block type="submit" :loading="loading" :disabled="!token">{{ $t('pages.auth.resetPassword.resetPassword.resetPassword') }}</VBtn>
        </VForm>
        <div class="text-center mt-5"><RouterLink :to="{ name: 'login' }">{{ $t('common.messages.backToLogin') }}</RouterLink></div>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>.auth-page { min-height: 100vh; }</style>
