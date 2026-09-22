<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthApi from '@/api/auth'
import { storeAuthTokens } from '@/utils/authStorage'
import { applyPermissions } from '@/utils/authorization'
import { useCookie } from '@core/composable/useCookie'
import LoginForm from '@/components/forms/LoginForm.vue'

const router = useRouter()
const route = useRoute()

const form = ref({
  email: '',
  password: '',
  remember: false,
  twoFactorCode: '',
})

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

  if (route.query.reset === '1')
    status.value = translate('pages.auth.login.login.yourPasswordWasResetYouCanNowSignIn')
  else if (route.query.invited === '1')
    status.value = translate('pages.auth.login.login.yourAccountIsReadySignInWithThePasswordYouCreated')
})

const handleLogin = async () => {
  error.value = ''
  status.value = ''

  if (!form.value.email || !form.value.password) {
    error.value = translate('pages.auth.login.login.emailAndPasswordAreRequired')

    return
  }

  try {
    loading.value = true

    const response = await AuthApi.login({
      email: form.value.email,
      password: form.value.password,
      remember: form.value.remember,
      two_factor_code: twoFactorRequired.value ? form.value.twoFactorCode : undefined,
    })

    const session = response.data.data

    storeAuthTokens(session, form.value.remember)
    useCookie<Record<string, unknown> | null>('userData').value = session.user
    applyPermissions(session.user.permissions ?? [])

    if (form.value.remember)
      localStorage.setItem('remembered_login_email', form.value.email)
    else
      localStorage.removeItem('remembered_login_email')

    await router.replace(
      session.user.roles?.includes('applicant') && session.user.permissions?.includes('view_own_applicant_portal')
        ? '/my-case'
        : session.user.permissions?.includes('view_dashboard')
        ? '/home'
        : session.user.permissions?.some((permission: string) => ['view_all_boards', 'view_assigned_boards'].includes(permission))
          ? '/projects'
          : '/account-settings',
    )
  }
  catch (err: any) {
    twoFactorRequired.value = Boolean(err.two_factor_required) || twoFactorRequired.value
    verificationRequired.value = Boolean(err.email_verification_required)
    error.value = err.errors?.email?.[0]
      || err.errors?.password?.[0]
      || err.message
      || 'Login failed. Please check your credentials.'
  }
  finally {
    loading.value = false
  }
}

const resendVerification = async () => {
  if (!form.value.email) {
    error.value = translate('pages.auth.login.login.enterYourEmailAddressFirst')

    return
  }

  resendingVerification.value = true
  error.value = ''
  try {
    await AuthApi.resendEmailVerification(form.value.email)
    status.value = translate('common.messages.ifVerificationIsRequiredANewEmailHasBeenSent')
  }
  catch (err: any) {
    error.value = err.message || 'Unable to send a verification email right now.'
  }
  finally {
    resendingVerification.value = false
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard
      class="auth-card pa-4 pt-7"
      max-width="448"
    >
      <VCardItem class="justify-center">
        <template #prepend>
          <VImg
            src="/logo.svg"
            width="32"
            height="32"
          />
        </template>
        <VCardTitle class="text-h5 font-weight-bold">
          {{ $t('pages.auth.login.login.asantechMiniManagement') }}
        </VCardTitle>
      </VCardItem>

      <VCardText class="pt-2">
        <h5 class="text-h5 mb-1">
          {{ $t('pages.auth.login.login.welcome') }}
        </h5>
        <p class="mb-0">
          {{ $t('pages.auth.login.login.pleaseSignInToYourAccount') }}
        </p>
      </VCardText>

      <VCardText>
        <VAlert
          v-if="status"
          type="success"
          variant="tonal"
          class="mb-4"
          closable
          @click:close="status = ''"
        >
          {{ status }}
        </VAlert>
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ error }}
          <template
            v-if="verificationRequired"
            #append
          >
            <VBtn
              size="small"
              variant="text"
              :loading="resendingVerification"
              @click="resendVerification"
            >
              {{ $t('pages.auth.login.login.resendEmail') }}
            </VBtn>
          </template>
        </VAlert>

        <LoginForm
          :form="form"
          :loading="loading"
          :show-password="showPassword"
          :two-factor-required="twoFactorRequired"
          @submit="handleLogin"
          @toggle-password="showPassword = !showPassword"
        />
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.auth-wrapper {
  min-block-size: 100vh;

  .auth-card {
    width: 100%;
    max-inline-size: 448px;
  }
}
</style>
