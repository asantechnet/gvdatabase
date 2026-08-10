<script lang="ts" setup>
import AuthApi, { type Account, type ApiKey, type ApiKeyPermission } from '@/api/auth'

const currentPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const status = ref('')
const error = ref('')
const savingPassword = ref(false)
const twoFactorEnabled = ref(false)
const twoFactorSecret = ref('')
const twoFactorUri = ref('')
const twoFactorCode = ref('')
const apiKeys = ref<ApiKey[]>([])
const apiKeyName = ref('')
const apiKeyPermission = ref<ApiKeyPermission>('read-only')
const revealedApiKey = ref('')

function accountFrom(response: any) { return response.data.data as Account }
function announce(message: string) { status.value = message; error.value = '' }
function fail(e: any) { error.value = e.errors?.current_password?.[0] || e.message || 'The request could not be completed.'; status.value = '' }

async function load() {
  try {
    const account = accountFrom(await AuthApi.me())
    twoFactorEnabled.value = account.two_factor_enabled
    apiKeys.value = account.api_keys
  } catch (e: any) { fail(e) }
}

async function savePassword() {
  if (password.value !== passwordConfirmation.value) { error.value = 'New password confirmation does not match.'; return }
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
  if (!currentPassword.value) { error.value = 'Enter your current password to disable two-factor authentication.'; return }
  try { await AuthApi.disableTwoFactor(currentPassword.value); twoFactorEnabled.value = false; announce('Two-factor authentication is disabled.') }
  catch (e: any) { fail(e) }
}

async function createApiKey() {
  try {
    const data = (await AuthApi.createApiKey({ name: apiKeyName.value, permission: apiKeyPermission.value })).data.data
    apiKeys.value.push({ id: data.id, name: data.name, permission: data.permission, created_at: new Date().toISOString(), last_used_at: null })
    revealedApiKey.value = data.token; apiKeyName.value = ''; announce('Copy the new API key now. It will not be shown again.')
  } catch (e: any) { fail(e) }
}

async function revokeApiKey(id: number) {
  try { await AuthApi.revokeApiKey(id); apiKeys.value = apiKeys.value.filter(key => key.id !== id); announce('API key revoked.') }
  catch (e: any) { fail(e) }
}

async function logoutOtherDevices() {
  if (!currentPassword.value) { error.value = 'Enter your current password to sign out other devices.'; return }
  try { await AuthApi.logoutOtherDevices(currentPassword.value); announce('Other devices have been signed out.') }
  catch (e: any) { fail(e) }
}

onMounted(load)
</script>

<template>
  <VRow>
    <VCol cols="12"><VAlert v-if="status" type="success">{{ status }}</VAlert><VAlert v-if="error" type="error">{{ error }}</VAlert></VCol>
    <VCol cols="12">
      <VCard title="Change Password"><VForm @submit.prevent="savePassword"><VCardText><VRow>
        <VCol cols="12" md="6"><AppTextField v-model="currentPassword" type="password" label="Current Password" autocomplete="current-password" /></VCol>
        <VCol cols="12" md="6"><AppTextField v-model="password" type="password" label="New Password" autocomplete="new-password" /></VCol>
        <VCol cols="12" md="6"><AppTextField v-model="passwordConfirmation" type="password" label="Confirm New Password" autocomplete="new-password" /></VCol>
      </VRow></VCardText><VCardText><VBtn type="submit" :loading="savingPassword">Save changes</VBtn></VCardText></VForm></VCard>
    </VCol>
    <VCol cols="12">
      <VCard title="Two-step verification"><VCardText>
        <p v-if="twoFactorEnabled">Two-factor authentication is enabled for this account.</p><p v-else>Protect your account with an authenticator app.</p>
        <VBtn v-if="!twoFactorEnabled && !twoFactorSecret" @click="beginTwoFactor">Enable two-factor authentication</VBtn>
        <template v-if="twoFactorSecret"><VAlert type="info" class="my-4">Authenticator secret: <code>{{ twoFactorSecret }}</code></VAlert><a :href="twoFactorUri">Open authenticator app</a><AppTextField v-model="twoFactorCode" class="mt-4" label="Authenticator code" maxlength="6" /><VBtn class="mt-3" @click="confirmTwoFactor">Confirm and enable</VBtn></template>
        <VBtn v-if="twoFactorEnabled" color="error" variant="tonal" @click="disableTwoFactor">Disable two-factor authentication</VBtn>
      </VCardText></VCard>
    </VCol>
    <VCol cols="12">
      <VCard title="Create an API key"><VCardText><VRow><VCol cols="12" md="5"><AppTextField v-model="apiKeyName" label="Name" /><AppSelect v-model="apiKeyPermission" class="mt-4" label="Permission" :items="['full-control', 'modify', 'read-execute', 'list-folders', 'read-only', 'read-write']" /><VBtn class="mt-4" :disabled="!apiKeyName" @click="createApiKey">Create key</VBtn></VCol></VRow><VAlert v-if="revealedApiKey" type="warning" class="mt-4">Copy this key now: <code>{{ revealedApiKey }}</code></VAlert></VCardText></VCard>
    </VCol>
    <VCol cols="12"><VCard title="API Key List & Access"><VList><VListItem v-for="key in apiKeys" :key="key.id" :title="key.name" :subtitle="`${key.permission} · Created ${new Date(key.created_at).toLocaleDateString()}`"><template #append><VBtn size="small" color="error" variant="tonal" @click="revokeApiKey(key.id)">Revoke</VBtn></template></VListItem><VListItem v-if="!apiKeys.length" title="No API keys created." /></VList></VCard></VCol>
    <VCol cols="12"><VCard title="Sessions"><VCardText><p>Use your current password above to sign out all other devices.</p><VBtn variant="tonal" @click="logoutOtherDevices">Sign out other devices</VBtn></VCardText></VCard></VCol>
  </VRow>
</template>
