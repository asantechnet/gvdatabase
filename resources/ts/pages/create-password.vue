<script setup lang="ts">
import AuthApi from '@/api/auth'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const token = typeof route.query.token === 'string' ? route.query.token : ''
const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref(token ? '' : translate('pages.auth.createPassword.invalidInvitation'))

const submit = async () => {
  error.value = ''
  if (!token || !email.value) return void (error.value = translate('pages.auth.createPassword.createPassword.thisInvitationLinkIsIncompleteOrInvalid'))
  if (password.value.length < 8) return void (error.value = translate('common.messages.passwordMustContainAtLeast8Characters'))
  if (password.value !== passwordConfirmation.value) return void (error.value = translate('common.messages.passwordConfirmationDoesNotMatch'))

  loading.value = true
  try {
    await AuthApi.completeInvitation({ email: email.value, token, password: password.value, password_confirmation: passwordConfirmation.value })
    await router.replace({ name: 'login', query: { invited: '1' } })
  } catch (err: any) {
    error.value = err.errors?.password?.[0] || err.message || 'Unable to complete this invitation.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page d-flex align-center justify-center pa-4">
    <VCard width="448" class="pa-4">
      <VCardItem :title="$t('pages.auth.createPassword.createPassword.createYourPassword')"><template #prepend><VIcon icon="tabler-user-check" color="primary" /></template></VCardItem>
      <VCardText>
        <p class="text-body-2 text-medium-emphasis mb-4">{{ $t('pages.auth.createPassword.createPassword.yourEmailIsVerifiedChooseAPasswordToFinishSettingUpYourAccount') }}</p>
        <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
        <VForm @submit.prevent="submit">
          <VTextField v-model="email" type="email" :label="$t('common.fields.email')" readonly class="mb-3" />
          <VTextField v-model="password" :type="showPassword ? 'text' : 'password'" :label="$t('common.fields.password')" autocomplete="new-password" :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'" class="mb-3" @click:append-inner="showPassword = !showPassword" />
          <VTextField v-model="passwordConfirmation" :type="showPassword ? 'text' : 'password'" :label="$t('pages.auth.createPassword.createPassword.confirmPassword')" autocomplete="new-password" class="mb-4" />
          <VBtn block type="submit" :loading="loading" :disabled="!token">{{ $t('pages.auth.createPassword.createPassword.createPassword') }}</VBtn>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>.auth-page { min-height: 100vh; }</style>
