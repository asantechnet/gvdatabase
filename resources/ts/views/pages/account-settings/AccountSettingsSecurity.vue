<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import QrcodeVue from 'qrcode.vue'
import AuthApi, { type Account, type AccountSession } from '@/api/auth'
import { apiFieldErrors, apiErrorMessage } from '@/utils/apiErrors'
import { formatLocalizedDate } from '@/utils/localization'

const currentPassword = ref('')
const logoutPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const toast = useToast()
const savingPassword = ref(false)
const signingOutOtherDevices = ref(false)
const signOutOtherDevicesDialog = ref(false)
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
  signOutOtherDevicesDialog.value = true
}

async function confirmLogoutOtherDevices() {
  signingOutOtherDevices.value = true
  try {
    await AuthApi.logoutOtherDevices(logoutPassword.value)
    logoutPassword.value = ''
    signOutOtherDevicesDialog.value = false
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
      <VCard
        :title="$t('common.messages.changePassword')"
        data-account-guide="change-password"
      >
        <VForm @submit.prevent="savePassword">
          <VCardText>
            <p class="text-body-2 text-medium-emphasis">
              {{ passwordPolicy }}
            </p>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="currentPassword"
                  type="password"
                  :label="$t('components.account.accountsettingssecurity.currentPassword')"
                  name="current_password"
                  autocomplete="new-password"
                  :error-messages="currentPasswordError"
                  @update:model-value="currentPasswordError = ''"
                  data-1p-ignore
                  data-lpignore="true"
                  data-form-type="other"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="password"
                  type="password"
                  :label="$t('common.messages.newPassword')"
                  autocomplete="new-password"
                  :error-messages="passwordError"
                  @update:model-value="passwordError = ''"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="passwordConfirmation"
                  type="password"
                  :label="$t('components.account.accountsettingssecurity.confirmNewPassword')"
                  autocomplete="new-password"
                  :error-messages="passwordConfirmationError"
                  @update:model-value="passwordConfirmationError = ''"
                />
              </VCol>
            </VRow>
          </VCardText><VCardText>
            <VBtn
              type="submit"
              :loading="savingPassword"
            >
              {{ $t('common.messages.saveChanges') }}
            </VBtn>
          </VCardText>
        </VForm>
      </VCard>
    </VCol>
    <VCol cols="12">
      <VCard
        :title="$t('components.account.accountsettingssecurity.twoStepVerification')"
        data-account-guide="two-factor"
      >
        <VCardText>
          <p v-if="twoFactorEnabled">
            {{ $t('components.account.accountsettingssecurity.twoFactorAuthenticationIsEnabledForThisAccount') }}
          </p><p v-else>
            {{ $t('components.account.accountsettingssecurity.protectYourAccountWithAnAuthenticatorApp') }}
          </p>
          <VBtn
            v-if="!twoFactorEnabled && !twoFactorSecret"
            @click="beginTwoFactor"
          >
            {{ $t('components.account.accountsettingssecurity.enableTwoFactorAuthentication') }}
          </VBtn>
          <template v-if="twoFactorSecret">
            <VAlert
              type="info"
              class="my-4"
            >
              Scan this QR code with your authenticator app, or add the secret manually.
            </VAlert>
            <div class="two-factor-setup">
              <QrcodeVue
                :value="twoFactorUri"
                :size="180"
                level="M"
                render-as="svg"
                class="two-factor-setup__qr"
              />
              <div class="two-factor-setup__manual">
                <p class="text-caption text-medium-emphasis mb-1">
                  {{ twoFactorIssuer }} — {{ $t('components.account.accountsettingssecurity.authenticatorSecret') }}
                </p>
                <code class="two-factor-setup__secret">{{ twoFactorSecret }}</code>
                <p class="text-caption text-medium-emphasis mt-4">
                  {{ $t('components.account.accountsettingssecurity.ifYouAreUnableToScanTheQrCodeYouCanManuallyEnterTheSecretKeyBelow') }}
                </p>
              </div>
            </div>
            <AppTextField
              v-model="twoFactorCode"
              class="mt-4"
              :label="$t('components.account.accountsettingssecurity.authenticatorCode')"
              maxlength="6"
              inputmode="numeric"
              autocomplete="one-time-code"
            /><VBtn
              class="mt-3"
              @click="confirmTwoFactor"
            >
              {{ $t('components.account.accountsettingssecurity.confirmAndEnable') }}
            </VBtn>
          </template>
          <VBtn
            v-if="twoFactorEnabled"
            color="error"
            variant="tonal"
            @click="disableTwoFactor"
          >
            {{ $t('components.account.accountsettingssecurity.disableTwoFactorAuthentication') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
    <VCol cols="12">
      <VCard
        :title="$t('components.account.accountsettingssecurity.sessions')"
        data-account-guide="sessions"
      >
        <VCardText>
          <div
            v-if="loadingSessions"
            class="text-medium-emphasis"
          >
            Loading sessions…
          </div>
          <VAlert
            v-else-if="sessionError"
            type="error"
            variant="tonal"
          >
            {{ sessionError }}
          </VAlert>
          <div
            v-else
            class="session-list"
          >
            <div
              v-for="session in sessions"
              :key="session.id"
              class="session-list__row"
            >
              <div class="session-list__details">
                <div class="font-weight-medium">
                  {{ session.device || 'Unknown device' }}
                  <VChip
                    v-if="session.current"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                  >
                    This device
                  </VChip>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ session.type === 'refresh' ? 'Refresh session' : 'Access session' }}
                  · Created {{ formatLocalizedDate(session.created_at, 'short') }}
                  · Last used {{ session.last_used_at ? formatLocalizedDate(session.last_used_at, 'short') : 'never' }}
                </div>
              </div>
              <VBtn
                v-if="!session.current"
                size="small"
                variant="text"
                color="error"
                :loading="revokingSessionId === session.id"
                :aria-label="`Revoke session ${session.device || session.id}`"
                @click="revokeSession(session.id)"
              >
                Revoke
              </VBtn>
            </div>
            <p
              v-if="!sessions.length"
              class="text-medium-emphasis mb-0"
            >
              No active sessions.
            </p>
          </div>
        </VCardText>
        <VForm @submit.prevent="logoutOtherDevices">
          <VCardText>
            <p>{{ $t('components.account.accountsettingssecurity.enterYourCurrentPasswordToSignOutOtherDevices') }}</p>
            <AppTextField
              v-model="logoutPassword"
              type="password"
              :label="$t('components.account.accountsettingssecurity.currentPassword')"
              name="logout_password"
              autocomplete="current-password"
              :error-messages="logoutPasswordError"
              @update:model-value="logoutPasswordError = ''"
            />
            <VBtn
              class="mt-4"
              type="submit"
              variant="tonal"
              :disabled="!logoutPassword"
            >
              {{ $t('components.account.accountsettingssecurity.signOutOtherDevices') }}
            </VBtn>
          </VCardText>
        </VForm>
      </VCard>
    </VCol>
  </VRow>
  <VDialog
    v-model="recoveryCodesDialog"
    max-width="560"
    persistent
  >
    <VCard title="Save your recovery codes">
      <VCardText>
        <VAlert
          type="warning"
          variant="tonal"
          class="mb-4"
        >
          These codes are shown once. Store them somewhere safe; each one works a single time.
        </VAlert>
        <div class="recovery-codes">
          <code
            v-for="code in recoveryCodes"
            :key="code"
            class="recovery-codes__item"
          >{{ code }}</code>
        </div>
      </VCardText>
      <VCardActions class="justify-end px-6 pb-5">
        <VBtn
          color="primary"
          @click="recoveryCodesDialog = false"
        >
          I have saved these codes
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog
    v-model="signOutOtherDevicesDialog"
    max-width="480"
  >
    <VCard>
      <VCardTitle>Sign out other devices?</VCardTitle>
      <VCardText>
        This will end every other active session for your account. Your current browser will stay signed in.
      </VCardText>
      <VCardActions class="justify-end px-6 pb-5">
        <VBtn
          variant="text"
          :disabled="signingOutOtherDevices"
          @click="signOutOtherDevicesDialog = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="error"
          :loading="signingOutOtherDevices"
          @click="confirmLogoutOtherDevices"
        >
          Sign out other devices
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.two-factor-setup {
  align-items: flex-start;
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
}

.two-factor-setup__qr {
  background: #fff;
  border-radius: 0.5rem;
  padding: 0.75rem;
}

.two-factor-setup__manual {
  flex: 1 1 16rem;
  min-inline-size: 0;
}

.two-factor-setup__secret {
  overflow-wrap: anywhere;
}

.session-list {
  display: grid;
  gap: 0.75rem;
}

.session-list__row {
  align-items: center;
  border-block-end: thin solid rgb(var(--v-border-color) / var(--v-border-opacity));
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding-block-end: 0.75rem;
}

.session-list__details {
  min-inline-size: 0;
}

.session-list__row:last-child {
  border-block-end: 0;
  padding-block-end: 0;
}

.recovery-codes {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
}

.recovery-codes__item {
  background: rgb(var(--v-theme-on-surface) / 0.06);
  border-radius: 0.375rem;
  padding: 0.5rem 0.75rem;
  text-align: center;
}

@media (max-width: 599px) {
  .session-list__row {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
