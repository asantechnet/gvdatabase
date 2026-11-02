<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import AuthApi from '@/api/auth'
import AuthShell from '@/components/auth/AuthShell.vue'
import AuthStatus from '@/components/auth/AuthStatus.vue'
import ForgotPasswordForm from '@/components/forms/ForgotPasswordForm.vue'

const { t } = useI18n()
const route = useRoute()
const email = ref(typeof route.query.email === 'string' ? route.query.email.trim() : '')
const loading = ref(false)
const status = ref('')
const error = ref('')

const submit = async () => {
  email.value = email.value.trim()
  if (!email.value) { error.value = t('pages.auth.common.emailRequired'); return }
  loading.value = true
  error.value = ''
  try {
    await AuthApi.forgotPassword(email.value)
    status.value = t('pages.auth.forgotPassword.sent')
  }
  catch (err: any) { error.value = err.errors?.email?.[0] || err.message || t('pages.auth.common.requestFailed') }
  finally { loading.value = false }
}
</script>

<template>
  <AuthShell :title="$t('pages.auth.forgotPassword.title')" :description="$t('pages.auth.forgotPassword.description')">
    <template #status>
      <AuthStatus v-if="status" :message="status" tone="success" @dismiss="status = ''" />
      <AuthStatus v-else-if="error" :message="error" tone="error" @dismiss="error = ''" />
    </template>
    <ForgotPasswordForm v-model:email="email" :loading="loading" @submit="submit" />
    <template #footer><RouterLink :to="{ name: 'login' }">{{ $t('pages.auth.common.backToLogin') }}</RouterLink></template>
  </AuthShell>
</template>
