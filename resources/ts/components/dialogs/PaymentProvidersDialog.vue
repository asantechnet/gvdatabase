<script setup lang="ts">
import americanExDark from '@images/icons/payments/img/ae-dark.png'
import americanExLight from '@images/icons/payments/img/american-express.png'
import dcDark from '@images/icons/payments/img/dc-dark.png'
import dcLight from '@images/icons/payments/img/dc-light.png'
import jcbDark from '@images/icons/payments/img/jcb-dark.png'
import jcbLight from '@images/icons/payments/img/jcb-light.png'
import masterCardDark from '@images/icons/payments/img/master-dark.png'
import masterCardLight from '@images/icons/payments/img/mastercard.png'
import visaDark from '@images/icons/payments/img/visa-dark.png'
import visaLight from '@images/icons/payments/img/visa-light.png'

const props = defineProps<Props>()
const emit = defineEmits<Emit>()
const visa = useGenerateImageVariant(visaLight, visaDark)
const masterCard = useGenerateImageVariant(masterCardLight, masterCardDark)
const americanEx = useGenerateImageVariant(americanExLight, americanExDark)
const jcb = useGenerateImageVariant(jcbLight, jcbDark)
const dc = useGenerateImageVariant(dcLight, dcDark)

interface Props {
  isDialogVisible: boolean
}

interface Emit {
  (e: 'update:isDialogVisible', val: boolean): void
}

const dialogVisibleUpdate = (val: boolean) => {
  emit('update:isDialogVisible', val)
}

const paymentProvidersData = computed(() => [
  {
    title: translate('components.dialogs.paymentprovidersdialog.adyen'),
    providers: [visa, masterCard, americanEx, jcb, dc],
  },
  {
    title: translate('components.dialogs.paymentprovidersdialog.n2checkout'),
    providers: [visa, americanEx, jcb, dc],
  },
  {
    title: translate('components.dialogs.paymentprovidersdialog.airpay'),
    providers: [visa, americanEx, masterCard, jcb],
  },
  {
    title: translate('components.dialogs.paymentprovidersdialog.authorizeNet'),
    providers: [americanEx, jcb, dc],
  },
  {
    title: translate('common.messages.bambora'),
    providers: [masterCard, americanEx, jcb],
  },
  {
    title: translate('common.messages.bambora'),
    providers: [visa, masterCard, americanEx, jcb, dc],
  },
  {
    title: translate('components.dialogs.paymentprovidersdialog.chasePaymentechOrbital'),
    providers: [visa, americanEx, jcb, dc],
  },
  {
    title: translate('components.dialogs.paymentprovidersdialog.checkoutCom'),
    providers: [visa, masterCard],
  },
])
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    @update:model-value="dialogVisibleUpdate"
  >
    <DialogCloseBtn @click="emit('update:isDialogVisible', false)" />

    <VCard class="pa-2 pa-sm-10">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          {{ $t('components.dialogs.paymentprovidersdialog.selectPaymentProviders') }}
        </h4>
        <p class="text-body-1 text-center mb-6">
          {{ $t('components.dialogs.paymentprovidersdialog.thirdPartyPaymentProviders') }}
        </p>

        <div
          v-for="(item, index) in paymentProvidersData"
          :key="index"
        >
          <div class="d-flex flex-column flex-sm-row justify-space-between gap-4 flex-wrap py-4">
            <h6 class="text-h6">
              {{ item.title }}
            </h6>
            <div class="d-flex gap-4 flex-wrap">
              <img
                v-for="(img, iterator) in item.providers"
                :key="iterator"
                :src="img.value"
                height="30"
                width="50"
              >
            </div>
          </div>
          <VDivider v-if="index !== paymentProvidersData.length - 1" />
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.refer-link-input {
  .v-field--appended {
    padding-inline-end: 0;
  }

  .v-field__append-inner {
    padding-block-start: 0.125rem;
  }
}
</style>
