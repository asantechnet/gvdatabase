<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import AuthApi from '@/api/auth'
import AuthShell from '@/components/auth/AuthShell.vue'
import AuthStatus from '@/components/auth/AuthStatus.vue'
import ErpField from '@/components/erp/ErpField.vue'

const { t } = useI18n()
const route = useRoute()
const verified = route.query.verified === '1'
const invalidReason = typeof route.query.reason === 'string' ? route.query.reason : ''
const state = computed<'verified' | 'expired' | 'invalid' | 'pending'>(() => verified ? 'verified' : invalidReason === 'expired' ? 'expired' : invalidReason ? 'invalid' : 'pending')
const email = ref(typeof route.query.email === 'string' ? route.query.email.trim() : '')
const loading = ref(false)
const status = ref('')
const error = ref('')

const resend = async () => {
  email.value = email.value.trim()
  if (!email.value) { error.value = t('pages.auth.common.emailRequired'); return }
  loading.value = true
  error.value = ''
  try {
    await AuthApi.resendEmailVerification(email.value)
    status.value = t('pages.auth.common.verificationEmailSent')
  }
  catch (err: any) { error.value = err.errors?.email?.[0] || err.message || t('pages.auth.common.requestFailed') }
  finally { loading.value = false }
}
</script>

<template>
  <AuthShell
    :title="$t(`pages.auth.verifyEmail.${state}.title`)"
    :description="$t(`pages.auth.verifyEmail.${state}.description`)"
  >
    <template #status>
      <AuthStatus v-if="status" :message="status" tone="success" @dismiss="status = ''" />
      <AuthStatus v-else-if="error" :message="error" tone="error" @dismiss="error = ''" />
      <AuthStatus v-else-if="state === 'expired'" :message="$t('pages.auth.verifyEmail.expired.notice')" tone="warning" :dismissible="false" />
      <AuthStatus v-else-if="state === 'invalid'" :message="$t('pages.auth.verifyEmail.invalid.notice')" tone="error" :dismissible="false" />
    </template>
    <template v-if="state === 'verified'">
      <VBtn block :to="{ name: 'login' }">{{ $t('pages.auth.common.goToLogin') }}</VBtn>
    </template>
    <template v-else>
      <ErpField v-model="email" type="email" :label="$t('common.fields.email')" autocomplete="email" validate-on="blur" @blur="email = email.trim()" />
      <VBtn block color="primary" class="mt-4" :loading="loading" @click="resend">{{ $t('pages.auth.verifyEmail.resend') }}</VBtn>
    </template>
    <template #footer><RouterLink v-if="state !== 'verified'" :to="{ name: 'login' }">{{ $t('pages.auth.common.backToLogin') }}</RouterLink></template>
  </AuthShell>
</template>
