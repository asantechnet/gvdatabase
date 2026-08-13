<script setup lang="ts">
import AuthApi from '@/api/auth'
import { ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const loading = ref(false)
const status = ref('')
const error = ref('')

const submit = async () => {
  if (!email.value) {
    error.value = translate('common.messages.enterYourEmailAddress')
    return
  }

  loading.value = true
  error.value = ''
  try {
    await AuthApi.forgotPassword(email.value)
    status.value = translate('pages.auth.forgotPassword.forgotPassword.ifAnAccountExistsForThatEmailAPasswordResetLinkHasBeenSent')
  } catch (err: any) {
    error.value = err.errors?.email?.[0] || err.message || 'Unable to request a password reset.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page d-flex align-center justify-center pa-4">
    <VCard width="448" class="pa-4">
      <VCardItem :title="$t('pages.auth.forgotPassword.forgotPassword.forgotPassword')">
        <template #prepend><VIcon icon="tabler-lock-question" color="primary" /></template>
      </VCardItem>
      <VCardText>
        <p class="text-medium-emphasis">{{ $t('pages.auth.forgotPassword.forgotPassword.enterYourAccountEmailAndWeLlSendYouASecureResetLink') }}</p>
        <VAlert v-if="status" type="success" variant="tonal" class="mb-4">{{ status }}</VAlert>
        <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
        <VForm @submit.prevent="submit">
          <VTextField v-model="email" type="email" :label="$t('common.fields.email')" autocomplete="email" required class="mb-4" />
          <VBtn block type="submit" :loading="loading">{{ $t('pages.auth.forgotPassword.forgotPassword.sendResetLink') }}</VBtn>
        </VForm>
        <div class="text-center mt-5"><RouterLink :to="{ name: 'login' }">{{ $t('common.messages.backToLogin') }}</RouterLink></div>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>.auth-page { min-height: 100vh; }</style>
