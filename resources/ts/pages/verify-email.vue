<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import AuthApi from '@/api/auth'

const route = useRoute()
const verified = route.query.verified === '1'
const email = ref('')
const loading = ref(false)
const status = ref('')
const error = ref(verified ? '' : translate('pages.auth.verifyEmail.invalidLink'))

const resend = async () => {
  if (!email.value) {
    error.value = translate('common.messages.enterYourEmailAddress')

    return
  }
  loading.value = true
  error.value = ''
  try {
    await AuthApi.resendEmailVerification(email.value)
    status.value = translate('common.messages.ifVerificationIsRequiredANewEmailHasBeenSent')
  }
  catch (err: any) {
    error.value = err.errors?.email?.[0] || err.message || 'Unable to resend verification.'
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page d-flex align-center justify-center pa-4">
    <VCard
      width="448"
      class="pa-4 text-center"
    >
      <VCardText>
        <VAvatar
          :color="verified ? 'success' : 'warning'"
          variant="tonal"
          size="72"
          class="mb-4"
        >
          <VIcon
            :icon="verified ? 'tabler-mail-check' : 'tabler-mail-exclamation'"
            size="40"
          />
        </VAvatar>
        <h1 class="text-h5 mb-2">
          {{ verified ? 'Email verified' : 'Verification needed' }}
        </h1>
        <p class="text-medium-emphasis mb-5">
          {{ $t(verified ? 'pages.auth.verifyEmail.verified' : 'pages.auth.verifyEmail.requestNew') }}
        </p>
        <VAlert
          v-if="status"
          type="success"
          variant="tonal"
          class="mb-4 text-start"
        >
          {{ status }}
        </VAlert>
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mb-4 text-start"
        >
          {{ error }}
        </VAlert>
        <template v-if="!verified">
          <VTextField
            v-model="email"
            type="email"
            :label="$t('common.fields.email')"
            autocomplete="email"
            class="mb-3"
          />
          <VBtn
            block
            :loading="loading"
            variant="tonal"
            class="mb-3"
            @click="resend"
          >
            {{ $t('pages.auth.verifyEmail.verifyEmail.resendVerificationEmail') }}
          </VBtn>
        </template>
        <VBtn
          block
          :to="{ name: 'login' }"
        >
          {{ $t('pages.auth.verifyEmail.verifyEmail.goToLogin') }}
        </VBtn>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.auth-page { min-height: 100vh; }
</style>
