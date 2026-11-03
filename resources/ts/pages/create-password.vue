<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AuthApi from '@/api/auth'
import AuthShell from '@/components/auth/AuthShell.vue'
import AuthStatus from '@/components/auth/AuthStatus.vue'
import CreatePasswordForm from '@/components/forms/CreatePasswordForm.vue'

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
  error.value = ''
  if (password.value.length < 8) { error.value = t('pages.auth.common.passwordRequirement'); return }
  if (password.value !== passwordConfirmation.value) { error.value = t('pages.auth.common.passwordsDoNotMatch'); return }
  loading.value = true
  try {
    await AuthApi.completeInvitation({ email: email.value, token, password: password.value, password_confirmation: passwordConfirmation.value })
    await router.replace({ name: 'login', query: { invited: '1' } })
  }
  catch (err: any) {
    if (err.code === 'business.invitation_invalid' || err.status === 422) invalidLink.value = true
    else error.value = err.errors?.password?.[0] || err.message || t('pages.auth.common.requestFailed')
  }
  finally { loading.value = false }
}
</script>

<template>
  <AuthShell :title="$t('pages.auth.invitation.title')" :description="$t('pages.auth.invitation.description')">
    <template #status><AuthStatus v-if="error" :message="error" tone="error" @dismiss="error = ''" /></template>
    <template v-if="invalidLink">
      <AuthStatus :message="$t('pages.auth.invitation.invalidLink')" tone="warning" :dismissible="false" />
      <VBtn block color="primary" class="mt-4" :to="{ name: 'login' }">
        {{ $t('pages.auth.common.goToLogin') }}
      </VBtn>
    </template>
    <CreatePasswordForm v-else v-model:password="password" v-model:password-confirmation="passwordConfirmation" :email="email" :loading="loading" :show-password="showPassword" :disabled="!canSubmit" @submit="submit" @toggle-password="showPassword = !showPassword" />
    <template #footer><RouterLink :to="{ name: 'login' }">{{ $t('pages.auth.common.backToLogin') }}</RouterLink></template>
  </AuthShell>
</template>
