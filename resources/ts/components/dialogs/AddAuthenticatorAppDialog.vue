<script setup lang="ts">
import themeselectionQr from '@images/pages/themeselection-qr.png'

interface Emit {
  (e: 'update:isDialogVisible', value: boolean): void
  (e: 'submit', value: string): void
}
interface Props {
  authCode?: string
  isDialogVisible: boolean
}
const props = defineProps<Props>()
const emit = defineEmits<Emit>()

const authCode = ref(structuredClone(toRaw(props.authCode)))

const formSubmit = () => {
  if (authCode.value) {
    emit('submit', authCode.value)
    emit('update:isDialogVisible', false)
  }
}

const resetAuthCode = () => {
  authCode.value = structuredClone(toRaw(props.authCode))
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    @update:model-value="(val) => $emit('update:isDialogVisible', val)"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard class="pa-2 pa-sm-10">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-6">
          {{ $t('components.dialogs.addauthenticatorappdialog.addAuthenticatorApp') }}
        </h4>
        <h5 class="text-h5 mb-2">
          {{ $t('common.messages.authenticatorApps') }}
        </h5>

        <p class="text-body-1 mb-6">
          {{ $t('components.dialogs.addauthenticatorappdialog.usingAnAuthenticatorAppLikeGoogleAuthenticatorMicrosoftAuthenticatorAu') }}
        </p>

        <div class="mb-6">
          <VImg
            width="150"
            :src="themeselectionQr"
            class="mx-auto"
          />
        </div>

        <VAlert
          :title="$t('components.dialogs.addauthenticatorappdialog.asdlknasda9ahs678dghasd78ab')"
          :text="$t('components.dialogs.addauthenticatorappdialog.ifYouAreUnableToScanTheQRCodeYouCanManuallyEnterTheSecretKeyBelow')"
          variant="tonal"
          color="warning"
        />
        <VForm @submit.prevent="() => {}">
          <AppTextField
            v-model="authCode"
            name="auth-code"
            :label="$t('components.dialogs.addauthenticatorappdialog.enterAuthenticationCode')"
            placeholder="123 456"
            class="mt-4 mb-6"
          />

          <div class="d-flex justify-end flex-wrap gap-4">
            <VBtn
              color="secondary"
              variant="tonal"
              @click="resetAuthCode"
            >
              {{ $t('common.actions.cancel') }}
            </VBtn>

            <VBtn
              type="submit"
              @click="formSubmit"
            >
              {{ $t('common.actions.continue') }}
              <VIcon
                end
                icon="tabler-arrow-right"
                class="flip-in-rtl"
              />
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
