<script lang="ts" setup>
import AuthApi, { type Account, type AccountUpdatePayload } from '@/api/auth'
import {
  cancelAccountDeactivation,
  getMyDeactivationRequests,
  requestAccountDeactivation,
  type AccountDeactivationRequest,
} from '@/api/accountDeactivation'
import { canAccess } from '@/utils/authorization'
import { useToast } from 'vue-toastification'

const fallbackAvatar = 'https://ui-avatars.com/api/?background=7367f0&color=fff&name=User'
const toast = useToast()
const refInputEl = ref<HTMLInputElement>()
const loading = ref(true)
const saving = ref(false)
const message = ref('')
const error = ref('')
const deactivationDialogOpen = ref(false)
const deactivationSubmitting = ref(false)
const deactivationCancelling = ref(false)
const deactivationPassword = ref('')
const deactivationReason = ref('')
const passwordFieldGuarded = ref(true)
const deactivationRequests = ref<AccountDeactivationRequest[]>([])
const emailVerified = ref(false)
const initial = ref<AccountUpdatePayload>({})
const account = ref<AccountUpdatePayload & { avatar: string | null }>({ name: '', email: '', phone: '', address: '', avatar: null, organization: '', state: '', zip: '', country: '', language: '', timezone: '', currency: '' })
const userData = useCookie<Record<string, unknown> | null>('userData')

watch(message, value => {
  if (!value) return
  toast.success(value)
  message.value = ''
})
watch(error, value => {
  if (!value) return
  toast.error(value)
  error.value = ''
})

const displayAvatar = computed(() => account.value.avatar || fallbackAvatar)
const timezones = Intl.supportedValuesOf?.('timeZone') ?? ['UTC']
const currencies = ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'INR']
const pendingDeactivation = computed(() => deactivationRequests.value.find(item => item.status === 'pending') ?? null)
const canRequestDeactivation = computed(() => canAccess('request_account_deactivation'))
const canCancelDeactivation = computed(() => canAccess('cancel_own_deactivation_request'))

function responseData(response: any) {
  return response.data.data as Account
}

function setAccount(data: Account) {
  const { avatar, ...values } = data
  emailVerified.value = data.email_verified
  account.value = { ...account.value, ...values, avatar }
  initial.value = { ...values }
}

async function resendVerification() {
  if (!account.value.email) return
  try {
    await AuthApi.resendEmailVerification(account.value.email)
    message.value = translate('common.messages.ifVerificationIsRequiredANewEmailHasBeenSent')
  }
  catch (e: any) { error.value = e.message || 'Unable to send a verification email.' }
}

async function loadAccount() {
  loading.value = true
  try { setAccount(responseData(await AuthApi.me())) }
  catch (e: any) { error.value = e.message || 'Unable to load account details.' }
  finally { loading.value = false }
}

async function saveAccount() {
  saving.value = true; message.value = ''; error.value = ''
  try { setAccount(responseData(await AuthApi.updateAccount(account.value))); message.value = translate('components.account.accountsettingsaccount.accountDetailsSaved') }
  catch (e: any) { error.value = e.errors?.email?.[0] || e.message || 'Unable to save account details.' }
  finally { saving.value = false }
}

async function changeAvatar(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  error.value = ''; message.value = ''
  try {
    account.value.avatar = (await AuthApi.uploadAvatar(file)).data.data.avatar
    userData.value = { ...(userData.value ?? {}), avatar: account.value.avatar }
    message.value = translate('components.account.accountsettingsaccount.profilePhotoUpdated')
  }
  catch (e: any) { error.value = e.errors?.avatar?.[0] || e.message || 'Unable to upload this image.' }
}

async function resetAvatar() {
  try {
    await AuthApi.removeAvatar(); account.value.avatar = null
    userData.value = { ...(userData.value ?? {}), avatar: null }
    message.value = translate('components.account.accountsettingsaccount.profilePhotoRemoved')
  }
  catch (e: any) { error.value = e.message || 'Unable to remove the profile photo.' }
}

function resetForm() { account.value = { ...account.value, ...initial.value } }

function clearDeactivationSecrets() {
  deactivationPassword.value = ''
  passwordFieldGuarded.value = true
}

function openDeactivationDialog() {
  clearDeactivationSecrets()
  deactivationReason.value = ''
  error.value = ''
  deactivationDialogOpen.value = true
}

function closeDeactivationDialog() {
  deactivationDialogOpen.value = false
  clearDeactivationSecrets()
  deactivationReason.value = ''
}

async function loadDeactivationRequests() {
  try { deactivationRequests.value = await getMyDeactivationRequests() }
  catch (e: any) { error.value = e.message || 'Unable to load deactivation request history.' }
}

async function submitDeactivationRequest() {
  if (!deactivationPassword.value || deactivationSubmitting.value) return
  deactivationSubmitting.value = true
  error.value = ''; message.value = ''
  try {
    const created = await requestAccountDeactivation(deactivationPassword.value, deactivationReason.value)
    deactivationRequests.value.unshift(created)
    message.value = translate('components.account.accountsettingsaccount.yourDeactivationRequestWasSentForApproval')
    closeDeactivationDialog()
  }
  catch (e: any) { error.value = e.message || 'Unable to request account deactivation.' }
  finally {
    clearDeactivationSecrets()
    deactivationSubmitting.value = false
  }
}

async function cancelPendingDeactivation() {
  if (!pendingDeactivation.value || deactivationCancelling.value) return
  deactivationCancelling.value = true
  try {
    const updated = await cancelAccountDeactivation(pendingDeactivation.value.id)
    const index = deactivationRequests.value.findIndex(item => item.id === updated.id)
    if (index >= 0) deactivationRequests.value[index] = updated
    message.value = translate('components.account.accountsettingsaccount.yourPendingDeactivationRequestWasCancelled')
  }
  catch (e: any) { error.value = e.message || 'Unable to cancel the request.' }
  finally { deactivationCancelling.value = false }
}

watch(deactivationDialogOpen, open => {
  if (!open) clearDeactivationSecrets()
})

onMounted(() => Promise.all([loadAccount(), loadDeactivationRequests()]))
onBeforeUnmount(clearDeactivationSecrets)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard :loading="loading">
        <VCardText class="d-flex">
          <VAvatar rounded size="100" class="me-6" :image="displayAvatar" />
          <div class="d-flex flex-column justify-center gap-4">
            <div class="d-flex flex-wrap gap-4">
              <VBtn color="primary" size="small" :loading="saving" @click="refInputEl?.click()">{{ $t('components.account.accountsettingsaccount.uploadNewPhoto') }}</VBtn>
              <input ref="refInputEl" type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden @change="changeAvatar">
              <VBtn size="small" color="secondary" variant="tonal" @click="resetAvatar">{{ $t('common.messages.reset') }}</VBtn>
            </div>
            <p class="text-body-1 mb-0">{{ $t('components.account.accountsettingsaccount.allowedJPGGIFPNGOrWebPMaximumSize800KB') }}</p>
          </div>
        </VCardText>

        <VCardText class="pt-2">
          <VForm class="mt-3" @submit.prevent="saveAccount">
            <VRow>
              <VCol md="6" cols="12"><AppTextField v-model="account.name" :label="$t('common.messages.fullName')" :disabled="loading" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.email" :label="$t('common.messages.eMail')" type="email" :disabled="loading" /></VCol>
              <VCol v-if="!emailVerified" cols="12">
                <VAlert type="warning" variant="tonal">
                  {{ $t('components.account.accountsettingsaccount.thisEmailAddressIsNotVerifiedSignInWillRemainBlockedUntilVerificationI') }}
                  <template #append><VBtn size="small" variant="text" @click="resendVerification">{{ $t('components.account.accountsettingsaccount.resendVerification') }}</VBtn></template>
                </VAlert>
              </VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.organization" :label="$t('components.account.accountsettingsaccount.organization')" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.phone" :label="$t('components.account.accountsettingsaccount.phoneNumber')" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.address" :label="$t('common.fields.address')" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.state" :label="$t('components.account.accountsettingsaccount.state')" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.zip" :label="$t('components.account.accountsettingsaccount.zipCode')" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.country" :label="$t('components.account.accountsettingsaccount.country')" /></VCol>
              <VCol md="6" cols="12"><AppTextField v-model="account.language" :label="$t('components.account.accountsettingsaccount.language')" /></VCol>
              <VCol md="6" cols="12"><AppSelect v-model="account.timezone" :label="$t('components.account.accountsettingsaccount.timezone')" :items="timezones" /></VCol>
              <VCol md="6" cols="12"><AppSelect v-model="account.currency" :label="$t('common.fields.currency')" :items="currencies" /></VCol>
              <VCol cols="12" class="d-flex flex-wrap gap-4"><VBtn type="submit" :loading="saving">{{ $t('common.messages.saveChanges4') }}</VBtn><VBtn color="secondary" variant="tonal" @click="resetForm">{{ $t('common.actions.cancel') }}</VBtn></VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <VCol v-if="canRequestDeactivation" cols="12">
      <VCard :title="$t('components.account.accountsettingsaccount.deactivateAccount')">
        <VCardText>
          <VAlert v-if="pendingDeactivation" type="warning" variant="tonal" class="mb-4">
          {{ $t('components.account.deactivation.pendingSince', { date: formatLocalizedDate(pendingDeactivation.requested_at, 'long') }) }}
          </VAlert>
          <p class="mb-4">{{ $t('components.account.accountsettingsaccount.aHigherAuthorityReviewerMustApproveTheRequestBeforeYourAccountIsDeacti') }}</p>
          <VBtn
            v-if="pendingDeactivation && canCancelDeactivation"
            color="secondary"
            variant="tonal"
            :loading="deactivationCancelling"
            @click="cancelPendingDeactivation"
          >{{ $t('components.account.accountsettingsaccount.cancelPendingRequest') }}</VBtn>
          <VBtn v-else color="error" variant="tonal" @click="openDeactivationDialog">{{ $t('components.account.accountsettingsaccount.requestAccountDeactivation') }}</VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <VDialog :model-value="deactivationDialogOpen" max-width="520" @update:model-value="value => value ? undefined : closeDeactivationDialog()">
    <VCard :title="$t('components.account.accountsettingsaccount.confirmDeactivationRequest')">
      <VCardText>
        <VAlert type="warning" variant="tonal" class="mb-5">
          {{ $t('components.account.accountsettingsaccount.thisSubmitsARequestForReviewYourAccountRemainsActiveUntilItIsApproved') }}
        </VAlert>
        <VForm autocomplete="off" @submit.prevent="submitDeactivationRequest">
          <VTextField
            v-model="deactivationPassword"
            name="account-deactivation-confirmation-password"
            :label="$t('components.account.accountsettingsaccount.currentPassword')"
            type="password"
            autocomplete="new-password"
            :readonly="passwordFieldGuarded"
            data-lpignore="true"
            data-1p-ignore="true"
            autocapitalize="none"
            spellcheck="false"
            class="mb-4"
            @pointerdown="passwordFieldGuarded = false"
            @focus="passwordFieldGuarded = false"
          />
          <VTextarea v-model="deactivationReason" :label="$t('components.account.accountsettingsaccount.reasonOptional')" maxlength="2000" counter rows="3" />
          <div class="d-flex justify-end ga-3 mt-5">
            <VBtn variant="tonal" :disabled="deactivationSubmitting" @click="closeDeactivationDialog">{{ $t('common.actions.cancel') }}</VBtn>
            <VBtn color="error" type="submit" :disabled="!deactivationPassword" :loading="deactivationSubmitting">{{ $t('components.account.accountsettingsaccount.sendForApproval') }}</VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
