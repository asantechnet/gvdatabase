<script lang="ts" setup>
import AuthApi, { type Account } from '@/api/auth'
import { useToast } from 'vue-toastification'

const currentPassword = ref('')
const logoutPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const toast = useToast()
const savingPassword = ref(false)
const twoFactorEnabled = ref(false)
const twoFactorSecret = ref('')
const twoFactorUri = ref('')
const twoFactorCode = ref('')

function accountFrom(response: any) { return response.data.data as Account }
function announce(message: string) { toast.success(message) }
function fail(e: any) { toast.error(e.errors?.current_password?.[0] || e.message || 'The request could not be completed.') }

async function load() {
  try {
    const account = accountFrom(await AuthApi.me())
    twoFactorEnabled.value = account.two_factor_enabled
  } catch (e: any) { fail(e) }
}

async function savePassword() {
  if (password.value !== passwordConfirmation.value) { toast.error(translate('components.account.accountsettingssecurity.newPasswordConfirmationDoesNotMatch')); return }
  savingPassword.value = true
  try {
    await AuthApi.updatePassword({ current_password: currentPassword.value, password: password.value, password_confirmation: passwordConfirmation.value })
    currentPassword.value = ''; password.value = ''; passwordConfirmation.value = ''; announce('Password updated. Other sessions have been signed out.')
  } catch (e: any) { fail(e) }
  finally { savingPassword.value = false }
}

async function beginTwoFactor() {
  try {
    const data = (await AuthApi.beginTwoFactor()).data.data
    twoFactorSecret.value = data.secret; twoFactorUri.value = data.otpauth_url
    announce('Add this secret to your authenticator, then enter its six-digit code to confirm.')
  } catch (e: any) { fail(e) }
}

async function confirmTwoFactor() {
  try { await AuthApi.confirmTwoFactor(twoFactorCode.value); twoFactorEnabled.value = true; twoFactorSecret.value = ''; twoFactorUri.value = ''; twoFactorCode.value = ''; announce('Two-factor authentication is enabled.') }
  catch (e: any) { fail(e) }
}

async function disableTwoFactor() {
  if (!currentPassword.value) { toast.error(translate('components.account.accountsettingssecurity.enterYourCurrentPasswordToDisableTwoFactorAuthentication')); return }
  try { await AuthApi.disableTwoFactor(currentPassword.value); twoFactorEnabled.value = false; announce('Two-factor authentication is disabled.') }
  catch (e: any) { fail(e) }
}

async function logoutOtherDevices() {
  if (!logoutPassword.value) { toast.error(translate('components.account.accountsettingssecurity.enterYourCurrentPasswordToSignOutOtherDevices')); return }
  try { await AuthApi.logoutOtherDevices(logoutPassword.value); logoutPassword.value = ''; announce('Other devices have been signed out.') }
  catch (e: any) { fail(e) }
}

onMounted(load)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard :title="$t('common.messages.changePassword')"><VForm @submit.prevent="savePassword"><VCardText><VRow>
        <VCol cols="12" md="6"><AppTextField v-model="currentPassword" type="password" :label="$t('components.account.accountsettingssecurity.currentPassword')" name="account-security-current-password" autocomplete="new-password" data-1p-ignore data-lpignore="true" data-form-type="other" /></VCol>
        <VCol cols="12" md="6"><AppTextField v-model="password" type="password" :label="$t('common.messages.newPassword')" autocomplete="new-password" /></VCol>
        <VCol cols="12" md="6"><AppTextField v-model="passwordConfirmation" type="password" :label="$t('components.account.accountsettingssecurity.confirmNewPassword')" autocomplete="new-password" /></VCol>
      </VRow></VCardText><VCardText><VBtn type="submit" :loading="savingPassword">{{ $t('common.messages.saveChanges') }}</VBtn></VCardText></VForm></VCard>
    </VCol>
    <VCol cols="12">
      <VCard :title="$t('components.account.accountsettingssecurity.twoStepVerification')"><VCardText>
        <p v-if="twoFactorEnabled">{{ $t('components.account.accountsettingssecurity.twoFactorAuthenticationIsEnabledForThisAccount') }}</p><p v-else>{{ $t('components.account.accountsettingssecurity.protectYourAccountWithAnAuthenticatorApp') }}</p>
        <VBtn v-if="!twoFactorEnabled && !twoFactorSecret" @click="beginTwoFactor">{{ $t('components.account.accountsettingssecurity.enableTwoFactorAuthentication') }}</VBtn>
        <template v-if="twoFactorSecret"><VAlert type="info" class="my-4">{{ $t('components.account.accountsettingssecurity.authenticatorSecret') }} <code>{{ twoFactorSecret }}</code></VAlert><a :href="twoFactorUri">{{ $t('components.account.accountsettingssecurity.openAuthenticatorApp') }}</a><AppTextField v-model="twoFactorCode" class="mt-4" :label="$t('components.account.accountsettingssecurity.authenticatorCode')" maxlength="6" /><VBtn class="mt-3" @click="confirmTwoFactor">{{ $t('components.account.accountsettingssecurity.confirmAndEnable') }}</VBtn></template>
        <VBtn v-if="twoFactorEnabled" color="error" variant="tonal" @click="disableTwoFactor">{{ $t('components.account.accountsettingssecurity.disableTwoFactorAuthentication') }}</VBtn>
      </VCardText></VCard>
    </VCol>
    <VCol cols="12"><VCard :title="$t('components.account.accountsettingssecurity.sessions')"><VForm @submit.prevent="logoutOtherDevices"><VCardText>
      <p>{{ $t('components.account.accountsettingssecurity.enterYourCurrentPasswordToSignOutOtherDevices') }}</p>
      <AppTextField v-model="logoutPassword" type="password" :label="$t('components.account.accountsettingssecurity.currentPassword')" name="account-security-logout-password" autocomplete="current-password" />
      <VBtn class="mt-4" type="submit" variant="tonal">{{ $t('components.account.accountsettingssecurity.signOutOtherDevices') }}</VBtn>
    </VCardText></VForm></VCard></VCol>
  </VRow>
</template>
