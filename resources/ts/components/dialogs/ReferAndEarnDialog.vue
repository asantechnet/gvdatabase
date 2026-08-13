<script setup lang="ts">
import keyboard from '@images/svg/keyboard.svg'
import paper from '@images/svg/paper-send.svg'
import rocket from '@images/svg/rocket.svg'
import { themeConfig } from '@themeConfig'

interface Props {
  isDialogVisible: boolean
}

interface Emit {
  (e: 'update:isDialogVisible', val: boolean): void
}

const props = defineProps<Props>()

const emit = defineEmits<Emit>()

const dialogVisibleUpdate = (val: boolean) => {
  emit('update:isDialogVisible', val)
}

const referAndEarnSteps = [
  {
    icon: paper,
    title: translate('components.dialogs.referandearndialog.sendInvitation'),
    subtitle: translate('components.dialogs.referandearndialog.sendYourReferralLinkToYourFriend'),
  },
  {
    icon: keyboard,
    title: translate('components.dialogs.referandearndialog.registration'),
    subtitle: translate('components.dialogs.referandearndialog.letThemRegisterToOurServices'),
  },
  {
    icon: rocket,
    title: translate('components.dialogs.referandearndialog.freeTrial'),
    subtitle: translate('components.dialogs.referandearndialog.yourFriendWillGet30DaysFreeTrial'),
  },
]
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    :width="$vuetify.display.smAndDown ? 'auto' : 800"
    @update:model-value="dialogVisibleUpdate"
  >
    <!-- 👉 Dialog close btn -->
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard class="pa-2 pa-sm-10">
      <VCardText>
        <h4 class="text-h4 text-center mb-2">
          {{ $t('components.dialogs.referandearndialog.referEarn') }}
        </h4>
        <p class="text-body-1 mb-6 text-center">
          {{ $t('components.dialogs.referandearndialog.inviteYourFriendTo') }} <span class="text-capitalize">{{ themeConfig.app.title }}</span>{{ $t('components.dialogs.referandearndialog.ifTheySignUpYouAndYourFriendWillGet30DaysFreeTrial') }}
        </p>

        <VRow class="text-center mt-8">
          <VCol
            v-for="step in referAndEarnSteps"
            :key="step.title"
            cols="12"
            sm="4"
          >
            <VAvatar
              variant="tonal"
              size="88"
              color="primary"
              rounded
            >
              <VIcon
                size="40"
                :icon="step.icon"
              />
            </VAvatar>

            <h5 class="text-h5 mt-4 mb-2">
              {{ step.title }}
            </h5>
            <div>{{ step.subtitle }}</div>
          </VCol>
        </VRow>

        <VDivider class="mt-12 mb-6" />

        <h5 class="text-h5 mb-6">
          {{ $t('components.dialogs.referandearndialog.inviteYourFriends') }}
        </h5>

        <VForm
          class="d-flex align-center flex-wrap gap-4"
          @submit.prevent="() => {}"
        >
          <AppTextField
            placeholder="johnDoe@gmail.com"
            :label="$t('components.dialogs.referandearndialog.enterYourFriendSEmailAddressAndInviteThemToJoinVuexy')"
          />

          <VBtn
            class="align-self-end"
            type="submit"
          >
            {{ $t('components.dialogs.referandearndialog.send') }}
          </VBtn>
        </VForm>

        <h5 class="text-h5 my-6">
          {{ $t('components.dialogs.referandearndialog.shareTheReferralLink') }}
        </h5>

        <VForm
          class="d-flex align-center flex-wrap gap-4"
          @submit.prevent="() => {}"
        >
          <AppTextField
            :placeholder="$t('components.dialogs.referandearndialog.httpPixinventLink')"
            :label="$t('components.dialogs.referandearndialog.youCanAlsoCopyAndSendItOrShareItOnYourSocialMedia')"
            class="refer-link-input"
          >
            <template #append-inner>
              <VBtn variant="text">
                {{ $t('components.dialogs.referandearndialog.copyLink') }}
              </VBtn>
            </template>
          </AppTextField>

          <div class="d-flex align-self-end gap-1">
            <VBtn
              icon
              class="rounded"
              color="#3B5998"
              size="38"
            >
              <VIcon
                color="white"
                icon="tabler-brand-facebook"
                size="22"
              />
            </VBtn>

            <VBtn
              icon
              class="rounded"
              color="#55ACEE"
              size="38"
            >
              <VIcon
                color="white"
                icon="tabler-brand-twitter"
                size="22"
              />
            </VBtn>

            <VBtn
              icon
              class="rounded"
              color="#007BB6"
              size="38"
            >
              <VIcon
                color="white"
                icon="tabler-brand-linkedin"
                size="22"
              />
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.refer-link-input {
  .v-field--appended {
    padding-inline-end: 0;
  }
}
</style>
