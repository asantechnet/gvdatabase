<script setup lang="ts">
interface Emit {
  (e: 'update:isDialogVisible', value: boolean): void
}
interface Props {
  isDialogVisible: boolean
  smsCode?: string
  authAppCode?: string
}

const props = withDefaults(defineProps<Props>(), {
  isDialogVisible: false,
  smsCode: '',
  authAppCode: '',
})

const emit = defineEmits<Emit>()

const authMethods = [
  {
    icon: 'tabler-settings',
    title: translate('common.messages.authenticatorApps'),
    desc: translate('components.dialogs.twofactorauthdialog.authenticatorDescription'),
    value: 'authApp',
  },
  {
    icon: 'tabler-message',
    title: translate('components.dialogs.twofactorauthdialog.sms'),
    desc: translate('components.dialogs.twofactorauthdialog.smsDescription'),
    value: 'sms',
  },
]

const selectedMethod = ref('authApp')
const isAuthAppDialogVisible = ref(false)
const isSmsDialogVisible = ref(false)

const openSelectedMethodDialog = () => {
  if (selectedMethod.value === 'authApp') {
    isAuthAppDialogVisible.value = true
    isSmsDialogVisible.value = false
    emit('update:isDialogVisible', false)
  }
  if (selectedMethod.value === 'sms') {
    isAuthAppDialogVisible.value = false
    isSmsDialogVisible.value = true
    emit('update:isDialogVisible', false)
  }
}
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 800"
    :model-value="props.isDialogVisible"
    @update:model-value="(val) => $emit('update:isDialogVisible', val)"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard class="pa-2 pa-sm-10">
      <VCardText>
        <!-- 👉 Title -->
        <div class="mb-6">
          <h4 class="text-h4 text-center mb-2">
            {{ $t('components.dialogs.twofactorauthdialog.selectAuthenticationMethod') }}
          </h4>
          <p class="text-body-1 text-center mb-6">
            {{ $t('components.dialogs.twofactorauthdialog.youAlsoNeedToSelectAMethodByWhichTheProxyAuthenticatesToTheDirectorySe') }}
          </p>
          <CustomRadios
            v-model:selected-radio="selectedMethod"
            :radio-content="authMethods"
            :grid-column="{ cols: '12' }"
          >
            <template #default="items">
              <div class="d-flex flex-column">
                <div class="d-flex gap-1 mb-2">
                  <VIcon
                    :icon="items.item.icon"
                    size="20"
                    class="text-high-emphasis"
                  />
                  <h6 class="text-h6">
                    {{ items.item.title }}
                  </h6>
                </div>
                <p class="text-body-2 mb-0">
                  {{ items.item.desc }}
                </p>
              </div>
            </template>
          </CustomRadios>
        </div>

        <div class="d-flex gap-4 justify-center">
          <VBtn @click="openSelectedMethodDialog">
            {{ $t('common.actions.submit4') }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            @click="$emit('update:isDialogVisible', false)"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </VDialog>

  <AddAuthenticatorAppDialog
    v-model:is-dialog-visible="isAuthAppDialogVisible"
    :auth-code="props.authAppCode"
  />
  <EnableOneTimePasswordDialog
    v-model:is-dialog-visible="isSmsDialogVisible"
    :mobile-number="props.smsCode"
  />
</template>
