<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import QrcodeVue from 'qrcode.vue'
import AuthApi, { type Account, type AccountSession } from '@/api/auth'
import ErpField from '@/components/erp/ErpField.vue'
import { confirmActionWarning } from '@/composables/useActionWarning'
import { apiFieldErrors, apiErrorMessage } from '@/utils/apiErrors'
import { formatLocalizedDate } from '@/utils/localization'

const currentPassword = ref('')
const logoutPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const toast = useToast()
const savingPassword = ref(false)
const signingOutOtherDevices = ref(false)
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showPasswordConfirmation = ref(false)
const showLogoutPassword = ref(false)
const currentPasswordError = ref('')
const logoutPasswordError = ref('')
const passwordError = ref('')
const passwordConfirmationError = ref('')
const twoFactorEnabled = ref(false)
const twoFactorSecret = ref('')
const twoFactorUri = ref('')
const twoFactorIssuer = ref('')
const twoFactorCode = ref('')
const recoveryCodes = ref<string[]>([])
const recoveryCodesDialog = ref(false)
const sessions = ref<AccountSession[]>([])
const loadingSessions = ref(false)
const sessionError = ref('')
const revokingSessionId = ref<number | null>(null)

const passwordPolicy = computed(() => 'Use at least 8 characters. Enter the same password in both fields.')

function accountFrom(response: any) { return response.data.data as Account }
function announce(message: string) { toast.success(message) }
function fail(e: any) { toast.error(apiErrorMessage(e, 'The request could not be completed.')) }

async function load() {
  try {
    const account = accountFrom(await AuthApi.me())

    twoFactorEnabled.value = account.two_factor_enabled
  }
  catch (e: any) { fail(e) }
}

async function loadSessions() {
  loadingSessions.value = true
  sessionError.value = ''
  try {
    sessions.value = (await AuthApi.sessions()).data.data as AccountSession[]
  }
  catch (e: any) { sessionError.value = apiErrorMessage(e) }
  finally { loadingSessions.value = false }
}

async function savePassword() {
  currentPasswordError.value = ''
  passwordError.value = ''
  passwordConfirmationError.value = ''
  if (password.value !== passwordConfirmation.value) {
    passwordConfirmationError.value = translate('components.account.accountsettingssecurity.newPasswordConfirmationDoesNotMatch')

    return
  }
  savingPassword.value = true
  try {
    await AuthApi.updatePassword({ current_password: currentPassword.value, password: password.value, password_confirmation: passwordConfirmation.value })
    currentPassword.value = ''; password.value = ''; passwordConfirmation.value = ''
    showCurrentPassword.value = false; showNewPassword.value = false; showPasswordConfirmation.value = false
    announce('Password updated. Other sessions have been signed out.')
    await loadSessions()
  }
  catch (e: any) {
    const errors = apiFieldErrors(e)

    currentPasswordError.value = errors.current_password ?? ''
    passwordError.value = errors.password ?? ''
    passwordConfirmationError.value = errors.password_confirmation ?? ''
    if (!currentPasswordError.value && !passwordError.value && !passwordConfirmationError.value)
      fail(e)
  }
  finally { savingPassword.value = false }
}

async function beginTwoFactor() {
  try {
    const data = (await AuthApi.beginTwoFactor()).data.data

    twoFactorSecret.value = data.secret
    twoFactorUri.value = data.otpauth_url
    twoFactorIssuer.value = data.issuer
    announce('Scan the QR code with your authenticator, then enter its six-digit code to confirm.')
  }
  catch (e: any) { fail(e) }
}

async function confirmTwoFactor() {
  try {
    const result = await AuthApi.confirmTwoFactor(twoFactorCode.value)

    recoveryCodes.value = result.data.data?.recovery_codes ?? []
    twoFactorEnabled.value = true
    twoFactorSecret.value = ''
    twoFactorUri.value = ''
    twoFactorCode.value = ''
    recoveryCodesDialog.value = recoveryCodes.value.length > 0
    announce('Two-factor authentication is enabled.')
    await loadSessions()
  }
  catch (e: any) { fail(e) }
}

async function disableTwoFactor() {
  if (!currentPassword.value) {
    toast.error(translate('components.account.accountsettingssecurity.enterYourCurrentPasswordToDisableTwoFactorAuthentication'))

    return
  }
  try {
    await AuthApi.disableTwoFactor(currentPassword.value)
    twoFactorEnabled.value = false
    recoveryCodes.value = []
    announce('Two-factor authentication is disabled.')
  }
  catch (e: any) { fail(e) }
}

async function revokeSession(id: number) {
  const session = sessions.value.find(item => item.id === id)
  const confirmed = await confirmActionWarning({
    key: `revoke-account-session-${id}`,
    title: 'Revoke this device?',
    message: `This will sign out ${session?.device || 'this device'} and remove its active session.`,
    confirmText: 'Revoke device',
    cancelText: translate('common.actions.cancel'),
    icon: 'tabler-trash',
    tone: 'error',
  })
  if (!confirmed)
    return

  revokingSessionId.value = id
  try {
    await AuthApi.revokeSession(id)
    sessions.value = sessions.value.filter(session => session.id !== id)
    announce('Session revoked.')
  }
  catch (e: any) { fail(e) }
  finally { revokingSessionId.value = null }
}

async function logoutOtherDevices() {
  logoutPasswordError.value = ''
  if (!logoutPassword.value) {
    toast.error(translate('components.account.accountsettingssecurity.enterYourCurrentPasswordToSignOutOtherDevices'))

    return
  }
  const confirmed = await confirmActionWarning({
    key: 'sign-out-other-devices',
    title: 'Sign out other devices?',
    message: 'This will end every other active session for your account. Your current browser will stay signed in.',
    confirmText: translate('components.account.accountsettingssecurity.signOutOtherDevices'),
    cancelText: translate('common.actions.cancel'),
    icon: 'tabler-logout-2',
    tone: 'error',
  })
  if (confirmed)
    await confirmLogoutOtherDevices()
}

async function confirmLogoutOtherDevices() {
  signingOutOtherDevices.value = true
  try {
    await AuthApi.logoutOtherDevices(logoutPassword.value)
    logoutPassword.value = ''
    showLogoutPassword.value = false
    announce('Other devices have been signed out.')
    await loadSessions()
  }
  catch (e: any) {
    logoutPasswordError.value = apiFieldErrors(e).logout_password ?? ''
    if (!logoutPasswordError.value)
      fail(e)
  }
  finally { signingOutOtherDevices.value = false }
}

onMounted(async () => {
  await load()
  await loadSessions()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard :title="$t('common.messages.changePassword')" data-account-guide="change-password">
        <VForm @submit.prevent="savePassword">
          <VCardText>
            <p class="text-body-2 text-medium-emphasis">{{ passwordPolicy }}</p>
            <VRow>
              <VCol cols="12" md="4">
                <ErpField v-model="currentPassword" :type="showCurrentPassword ? 'text' : 'password'" :label="$t('components.account.accountsettingssecurity.currentPassword')" name="current_password" autocomplete="new-password" :error-messages="currentPasswordError" :append-inner-icon="showCurrentPassword ? 'tabler-eye-off' : 'tabler-eye'" data-1p-ignore data-lpignore="true" data-form-type="other" @update:model-value="currentPasswordError = ''" @click:append-inner="showCurrentPassword = !showCurrentPassword" />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField v-model="password" :type="showNewPassword ? 'text' : 'password'" :label="$t('common.messages.newPassword')" autocomplete="new-password" :error-messages="passwordError" :append-inner-icon="showNewPassword ? 'tabler-eye-off' : 'tabler-eye'" @update:model-value="passwordError = ''" @click:append-inner="showNewPassword = !showNewPassword" />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField v-model="passwordConfirmation" :type="showPasswordConfirmation ? 'text' : 'password'" :label="$t('components.account.accountsettingssecurity.confirmNewPassword')" autocomplete="new-password" :error-messages="passwordConfirmationError" :append-inner-icon="showPasswordConfirmation ? 'tabler-eye-off' : 'tabler-eye'" @update:model-value="passwordConfirmationError = ''" @click:append-inner="showPasswordConfirmation = !showPasswordConfirmation" />
              </VCol>
            </VRow>
          </VCardText>
          <VCardText><VBtn type="submit" :loading="savingPassword">{{ $t('common.messages.saveChanges') }}</VBtn></VCardText>
        </VForm>
      </VCard>
    </VCol>

    <VCol cols="12">
      <VCard :title="$t('components.account.accountsettingssecurity.twoStepVerification')" data-account-guide="two-factor">
        <VCardText>
          <p v-if="twoFactorEnabled">{{ $t('components.account.accountsettingssecurity.twoFactorAuthenticationIsEnabledForThisAccount') }}</p>
          <p v-else>{{ $t('components.account.accountsettingssecurity.protectYourAccountWithAnAuthenticatorApp') }}</p>
          <VBtn v-if="!twoFactorEnabled && !twoFactorSecret" @click="beginTwoFactor">{{ $t('components.account.accountsettingssecurity.enableTwoFactorAuthentication') }}</VBtn>
          <template v-if="twoFactorSecret">
            <VAlert type="info" class="my-4">Scan this QR code with your authenticator app, or add the secret manually.</VAlert>
            <VRow>
              <VCol cols="12" md="4"><QrcodeVue :value="twoFactorUri" :size="180" level="M" render-as="svg" class="two-factor-setup__qr" /></VCol>
              <VCol cols="12" md="8" class="two-factor-setup__manual">
                <p class="text-caption text-medium-emphasis mb-1">{{ twoFactorIssuer }} — {{ $t('components.account.accountsettingssecurity.authenticatorSecret') }}</p>
                <code class="two-factor-setup__secret">{{ twoFactorSecret }}</code>
                <p class="text-caption text-medium-emphasis mt-4 mb-0">{{ $t('components.account.accountsettingssecurity.ifYouAreUnableToScanTheQrCodeYouCanManuallyEnterTheSecretKeyBelow') }}</p>
              </VCol>
              <VCol cols="12" md="4"><ErpField v-model="twoFactorCode" :label="$t('components.account.accountsettingssecurity.authenticatorCode')" :placeholder="$t('components.account.accountsettingssecurity.authenticatorCode')" maxlength="6" inputmode="numeric" autocomplete="one-time-code" /></VCol>
              <VCol cols="12" md="8" class="d-flex align-end"><VBtn @click="confirmTwoFactor">{{ $t('components.account.accountsettingssecurity.confirmAndEnable') }}</VBtn></VCol>
            </VRow>
          </template>
          <VBtn v-if="twoFactorEnabled" color="error" variant="tonal" @click="disableTwoFactor">{{ $t('components.account.accountsettingssecurity.disableTwoFactorAuthentication') }}</VBtn>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <VCard :title="$t('components.account.accountsettingssecurity.sessions')" data-account-guide="sessions">
        <VCardText>
          <div v-if="loadingSessions" class="session-list" aria-busy="true">
            <VSkeletonLoader v-for="index in 3" :key="index" type="list-item-two-line" class="session-list__skeleton" />
          </div>
          <VAlert v-else-if="sessionError" type="error" variant="tonal">{{ sessionError }}</VAlert>
          <div v-else class="session-list">
            <div v-for="session in sessions" :key="session.id" class="session-list__row">
              <div class="session-list__details">
                <div class="font-weight-medium">
                  {{ session.device || 'Unknown device' }}
                  <VChip v-if="session.current" size="x-small" color="primary" variant="tonal">This device</VChip>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ session.type === 'refresh' ? 'Refresh session' : 'Access session' }}
                  · Created {{ formatLocalizedDate(session.created_at, 'short') }}
                  · Last used {{ session.last_used_at ? formatLocalizedDate(session.last_used_at, 'short') : 'never' }}
                </div>
              </div>
              <VBtn v-if="!session.current" size="small" variant="text" color="error" :loading="revokingSessionId === session.id" :aria-label="`Revoke session ${session.device || session.id}`" @click="revokeSession(session.id)">Revoke</VBtn>
            </div>
            <div v-if="!sessions.length" class="account-empty-state">
              <VIcon icon="tabler-devices-off" size="30" />
              <div><div class="font-weight-medium">No active sessions</div><p class="text-body-2 text-medium-emphasis mb-0">This browser is currently the only signed-in device.</p></div>
            </div>
          </div>
        </VCardText>
        <VForm @submit.prevent="logoutOtherDevices">
          <VCardText>
            <p>{{ $t('components.account.accountsettingssecurity.enterYourCurrentPasswordToSignOutOtherDevices') }}</p>
            <VRow>
              <VCol cols="12" md="4"><ErpField v-model="logoutPassword" :type="showLogoutPassword ? 'text' : 'password'" :label="$t('components.account.accountsettingssecurity.currentPassword')" name="logout_password" autocomplete="current-password" :error-messages="logoutPasswordError" :append-inner-icon="showLogoutPassword ? 'tabler-eye-off' : 'tabler-eye'" @update:model-value="logoutPasswordError = ''" @click:append-inner="showLogoutPassword = !showLogoutPassword" /></VCol>
              <VCol cols="12" md="8" class="d-flex align-end"><VBtn type="submit" variant="tonal" :disabled="!logoutPassword || signingOutOtherDevices" :loading="signingOutOtherDevices">{{ $t('components.account.accountsettingssecurity.signOutOtherDevices') }}</VBtn></VCol>
            </VRow>
          </VCardText>
        </VForm>
      </VCard>
    </VCol>
  </VRow>

  <VDialog v-model="recoveryCodesDialog" max-width="560" persistent>
    <VCard title="Save your recovery codes">
      <VCardText>
        <VAlert type="warning" variant="tonal" class="mb-4">These codes are shown once. Store them somewhere safe; each one works a single time.</VAlert>
        <div class="recovery-codes"><code v-for="code in recoveryCodes" :key="code" class="recovery-codes__item">{{ code }}</code></div>
      </VCardText>
      <VCardActions class="justify-end px-6 pb-5"><VBtn color="primary" @click="recoveryCodesDialog = false">I have saved these codes</VBtn></VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.two-factor-setup__qr { background: #fff; border-radius: 0.5rem; padding: 0.75rem; }
.two-factor-setup__manual { min-inline-size: 0; }
.two-factor-setup__secret { overflow-wrap: anywhere; }
.session-list { display: grid; gap: 0.75rem; }
.session-list__row { align-items: center; background: rgb(var(--v-theme-on-surface) / 0.025); border: thin solid rgb(var(--v-border-color) / var(--v-border-opacity)); border-radius: 0.75rem; display: flex; gap: 1rem; justify-content: space-between; padding: 0.875rem 1rem; }
.session-list__details { min-inline-size: 0; }
.session-list__row:nth-child(even) { background: rgb(var(--v-theme-surface)); }
.session-list__skeleton + .session-list__skeleton { border-block-start: thin solid rgb(var(--v-border-color) / var(--v-border-opacity)); padding-block-start: 0.75rem; }
.account-empty-state { align-items: center; color: rgb(var(--v-theme-primary)); display: flex; gap: 0.875rem; justify-content: center; min-block-size: 8rem; padding: 1rem; text-align: start; }
.recovery-codes { display: grid; gap: 0.5rem; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); }
.recovery-codes__item { background: rgb(var(--v-theme-on-surface) / 0.06); border-radius: 0.375rem; padding: 0.5rem 0.75rem; text-align: center; }
@media (max-width: 599px) { .session-list__row { align-items: flex-start; flex-direction: column; } .account-empty-state { align-items: flex-start; justify-content: flex-start; } }
</style>
