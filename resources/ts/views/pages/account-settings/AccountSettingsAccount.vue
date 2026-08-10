<script lang="ts" setup>
import AuthApi, { type Account, type AccountUpdatePayload } from '@/api/auth'

const fallbackAvatar = 'https://ui-avatars.com/api/?background=7367f0&color=fff&name=User'
const refInputEl = ref<HTMLInputElement>()
const loading = ref(true)
const saving = ref(false)
const message = ref('')
const error = ref('')
const deactivatePassword = ref('')
const isAccountDeactivated = ref(false)
const initial = ref<AccountUpdatePayload>({})
const account = ref<AccountUpdatePayload & { avatar: string | null }>({ name: '', email: '', phone: '', address: '', avatar: null, organization: '', state: '', zip: '', country: '', language: '', timezone: '', currency: '' })

const displayAvatar = computed(() => account.value.avatar || fallbackAvatar)
const timezones = Intl.supportedValuesOf?.('timeZone') ?? ['UTC']
const currencies = ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'INR']

function responseData(response: any) {
  return response.data.data as Account
}

function setAccount(data: Account) {
  const { avatar, ...values } = data
  account.value = { ...account.value, ...values, avatar }
  initial.value = { ...values }
}

async function loadAccount() {
  loading.value = true
  try { setAccount(responseData(await AuthApi.me())) }
  catch (e: any) { error.value = e.message || 'Unable to load account details.' }
  finally { loading.value = false }
}

async function saveAccount() {
  saving.value = true; message.value = ''; error.value = ''
  try { setAccount(responseData(await AuthApi.updateAccount(account.value))); message.value = 'Account details saved.' }
  catch (e: any) { error.value = e.errors?.email?.[0] || e.message || 'Unable to save account details.' }
  finally { saving.value = false }
}

async function changeAvatar(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  error.value = ''; message.value = ''
  try { account.value.avatar = (await AuthApi.uploadAvatar(file)).data.data.avatar; message.value = 'Profile photo updated.' }
  catch (e: any) { error.value = e.errors?.avatar?.[0] || e.message || 'Unable to upload this image.' }
}

async function resetAvatar() {
  try { await AuthApi.removeAvatar(); account.value.avatar = null; message.value = 'Profile photo removed.' }
  catch (e: any) { error.value = e.message || 'Unable to remove the profile photo.' }
}

function resetForm() { account.value = { ...account.value, ...initial.value } }

async function deactivateAccount() {
  if (!isAccountDeactivated.value || !deactivatePassword.value) return
  try {
    await AuthApi.deactivate(deactivatePassword.value)
    localStorage.removeItem('access_token'); localStorage.removeItem('refresh_token')
    window.location.assign('/login')
  } catch (e: any) { error.value = e.message || 'Unable to deactivate account.' }
}

onMounted(loadAccount)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VAlert v-if="message" type="success" class="mb-4">{{ message }}</VAlert>
      <VAlert v-if="error" type="error" class="mb-4">{{ error }}</VAlert>
      <VCard :loading="loading">
        <VCardText class="d-flex">
          <VAvatar rounded size="100" class="me-6" :image="displayAvatar" />
          <div class="d-flex flex-column justify-center gap-4">
            <div class="d-flex flex-wrap gap-4">
              <VBtn color="primary" size="small" :loading="saving" @click="refInputEl?.click()">Upload new photo</VBtn>
              <input ref="refInputEl" type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden @change="changeAvatar">
              <VBtn size="small" color="secondary" variant="tonal" @click="resetAvatar">Reset</VBtn>
            </div>
            <p class="text-body-1 mb-0">Allowed JPG, GIF, PNG or WebP. Maximum size 800 KB.</p>
          </div>
        </VCardText>

        <VCardText class="pt-2">
          <VForm class="mt-3" @submit.prevent="saveAccount">
            <VRow>
              <VCol md="6" cols="12"><AppTextField v-model="account.name" label="Full name" :disabled="loading" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.email" label="E-mail" type="email" :disabled="loading" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.organization" label="Organization" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.phone" label="Phone Number" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.address" label="Address" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.state" label="State" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.zip" label="Zip Code" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.country" label="Country" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.language" label="Language" /></VCol>
              <VCol md="6" cols="12"><AppSelect v-model="account.timezone" label="Timezone" :items="timezones" /></VCol>
              <VCol md="6" cols="12"><AppSelect v-model="account.currency" label="Currency" :items="currencies" /></VCol>
              <VCol cols="12" class="d-flex flex-wrap gap-4"><VBtn type="submit" :loading="saving">Save changes</VBtn><VBtn color="secondary" variant="tonal" @click="resetForm">Cancel</VBtn></VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <VCard title="Deactivate Account">
        <VCardText>
          <p>This signs you out everywhere and prevents further sign-ins until an administrator reactivates the account.</p>
          <AppTextField v-model="deactivatePassword" class="mb-4" type="password" label="Current password" autocomplete="current-password" />
          <VCheckbox v-model="isAccountDeactivated" label="I confirm my account deactivation" />
          <VBtn :disabled="!isAccountDeactivated || !deactivatePassword" color="error" class="mt-3" @click="deactivateAccount">Deactivate Account</VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
