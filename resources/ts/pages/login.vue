<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useCookie } from '@core/composable/useCookie'
import AuthApi from '@/api/auth'
import AuthShell from '@/components/auth/AuthShell.vue'
import AuthStatus from '@/components/auth/AuthStatus.vue'
import LoginForm from '@/components/forms/LoginForm.vue'
import { storeAuthTokens } from '@/utils/authStorage'
import { applyPermissions } from '@/utils/authorization'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const form = ref({ email: '', password: '', remember: false, twoFactorCode: '', recoveryCode: '' })
const loading = ref(false)
const resendingVerification = ref(false)
const error = ref('')
const status = ref('')
const showPassword = ref(false)
const twoFactorRequired = ref(false)
const verificationRequired = ref(false)

onMounted(() => {
  const rememberedEmail = localStorage.getItem('remembered_login_email')
  if (rememberedEmail) {
    form.value.email = rememberedEmail
    form.value.remember = true
  }
  if (route.query.reset === '1') status.value = t('pages.auth.login.passwordResetSuccess')
  else if (route.query.invited === '1') status.value = t('pages.auth.login.invitationSuccess')
})

const normalizedEmail = () => form.value.email.trim()

const handleLogin = async () => {
  error.value = ''
  status.value = ''
  verificationRequired.value = false
  form.value.email = normalizedEmail()
  if (!form.value.email || !form.value.password) {
    error.value = t('pages.auth.login.emailAndPasswordRequired')
    return
  }
  try {
    loading.value = true
    const response = await AuthApi.login({
      email: form.value.email,
      password: form.value.password,
      remember: form.value.remember,
      two_factor_code: twoFactorRequired.value && form.value.twoFactorCode ? form.value.twoFactorCode : undefined,
      recovery_code: twoFactorRequired.value && form.value.recoveryCode ? form.value.recoveryCode : undefined,
    })
    const session = response.data.data
    storeAuthTokens(session, form.value.remember)
    useCookie<Record<string, unknown> | null>('userData').value = session.user
    applyPermissions(session.user.permissions ?? [])
    if (form.value.remember) localStorage.setItem('remembered_login_email', form.value.email)
    else localStorage.removeItem('remembered_login_email')
    await router.replace(
      session.user.roles?.includes('applicant') && session.user.permissions?.includes('view_own_applicant_portal') ? '/my-case'
        : session.user.permissions?.includes('view_dashboard') ? '/home'
          : session.user.permissions?.some((permission: string) => ['view_all_boards', 'view_assigned_boards', 'create_personal_tasks'].includes(permission)) ? '/tasks'
            : '/account-settings',
    )
  }
  catch (err: any) {
    verificationRequired.value = Boolean(err.email_verification_required)
    twoFactorRequired.value = Boolean(err.two_factor_required) || twoFactorRequired.value
    if (!twoFactorRequired.value && !verificationRequired.value)
      error.value = err.errors?.email?.[0] || err.errors?.password?.[0] || err.message || t('pages.auth.login.failed')
  }
  finally { loading.value = false }
}

const resendVerification = async () => {
  form.value.email = normalizedEmail()
  if (!form.value.email) {
    error.value = t('pages.auth.login.emailFirst')
    return
  }
  resendingVerification.value = true
  error.value = ''
  try {
    await AuthApi.resendEmailVerification(form.value.email)
    status.value = t('pages.auth.common.verificationEmailSent')
  }
  catch (err: any) { error.value = err.errors?.email?.[0] || err.message || t('pages.auth.common.requestFailed') }
  finally { resendingVerification.value = false }
}
</script>

<template>
  <AuthShell
    :title="$t(twoFactorRequired ? 'pages.auth.login.twoFactorTitle' : 'pages.auth.login.title')"
    :description="$t(twoFactorRequired ? 'pages.auth.login.twoFactorDescription' : 'pages.auth.login.description')"
  >
    <template #status>
      <AuthStatus v-if="status" :message="status" tone="success" @dismiss="status = ''" />
      <AuthStatus v-else-if="error" :message="error" tone="error" @dismiss="error = ''" />
      <div v-else-if="verificationRequired" class="auth-verification-state">
        <AuthStatus :message="$t('pages.auth.login.verificationRequired', { email: form.email })" tone="warning" :dismissible="false" />
        <VBtn block color="primary" variant="tonal" :loading="resendingVerification" @click="resendVerification">
          {{ $t('pages.auth.login.resendVerification') }}
        </VBtn>
      </div>
    </template>
    <LoginForm :form="form" :loading="loading" :show-password="showPassword" :two-factor-required="twoFactorRequired" @submit="handleLogin" @toggle-password="showPassword = !showPassword" />
  </AuthShell>
</template>

<style scoped>
.auth-verification-state { display: grid; gap: .75rem; }
</style>
