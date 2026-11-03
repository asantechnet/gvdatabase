<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AuthApi from '@/api/auth'
import AuthShell from '@/components/auth/AuthShell.vue'
import AuthStatus from '@/components/auth/AuthStatus.vue'
import ResetPasswordForm from '@/components/forms/ResetPasswordForm.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const email = ref(typeof route.query.email === 'string' ? route.query.email.trim() : '')
const token = typeof route.query.token === 'string' ? route.query.token : ''
const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')
const invalidLink = ref(!token || !email.value)
const canSubmit = computed(() => !invalidLink.value && !loading.value)

const submit = async () => {
  email.value = email.value.trim()
  error.value = ''
  if (password.value.length < 8) { error.value = t('pages.auth.common.passwordRequirement'); return }
  if (password.value !== passwordConfirmation.value) { error.value = t('pages.auth.common.passwordsDoNotMatch'); return }
  loading.value = true
  try {
    await AuthApi.resetPassword({ email: email.value, token, password: password.value, password_confirmation: passwordConfirmation.value })
    await router.replace({ name: 'login', query: { reset: '1' } })
  }
  catch (err: any) {
    if (err.code === 'business.reset_token_invalid' || err.status === 422) invalidLink.value = true
    else error.value = err.errors?.password?.[0] || err.message || t('pages.auth.common.requestFailed')
  }
  finally { loading.value = false }
}
</script>

<template>
  <AuthShell :title="$t('pages.auth.resetPassword.title')" :description="$t('pages.auth.resetPassword.description')">
    <template #status>
      <AuthStatus v-if="error" :message="error" tone="error" @dismiss="error = ''" />
    </template>
    <template v-if="invalidLink">
      <AuthStatus :message="$t('pages.auth.resetPassword.invalidLink')" tone="warning" :dismissible="false" />
      <VBtn block color="primary" class="mt-4" :to="{ name: 'forgot-password', query: { email: email || undefined } }">
        {{ $t('pages.auth.resetPassword.requestNewLink') }}
      </VBtn>
    </template>
    <ResetPasswordForm v-else v-model:email="email" v-model:password="password" v-model:password-confirmation="passwordConfirmation" :loading="loading" :show-password="showPassword" :disabled="!canSubmit" @submit="submit" @toggle-password="showPassword = !showPassword" />
    <template #footer><RouterLink :to="{ name: 'login' }">{{ $t('pages.auth.common.backToLogin') }}</RouterLink></template>
  </AuthShell>
</template>
