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

const paymentMethodsData = [
  {
    title: translate('components.dialogs.addpaymentmethoddialog.visa'),
    type: 'Credit Card',
    img: visa,
  },
  {
    title: translate('components.dialogs.addpaymentmethoddialog.americanExpress'),
    type: 'Credit Card',
    img: americanEx,
  },
  {
    title: translate('components.dialogs.addpaymentmethoddialog.mastercard'),
    type: 'Credit Card',
    img: masterCard,
  },
  {
    title: translate('components.dialogs.addpaymentmethoddialog.jcb'),
    type: 'Credit Card',
    img: jcb,
  },
  {
    title: translate('components.dialogs.addpaymentmethoddialog.dinersClub'),
    type: 'Credit Card',
    img: dc,
  },

]
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    :width="$vuetify.display.smAndDown ? 'auto' : 750"
    @update:model-value="dialogVisibleUpdate"
  >
    <!-- 👉 dialog close btn -->
    <DialogCloseBtn @click="emit('update:isDialogVisible', false)" />

    <VCard class="pa-2 pa-sm-10">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          {{ $t('components.dialogs.addpaymentmethoddialog.addPaymentMethods') }}
        </h4>
        <p class="text-body-1 text-center mb-6">
          {{ $t('components.dialogs.addpaymentmethoddialog.supportedPaymentMethods') }}
        </p>

        <div
          v-for="(item, index) in paymentMethodsData"
          :key="index"
        >
          <div class="d-flex justify-space-between align-center py-4 gap-x-4">
            <div class="d-flex align-center">
              <VImg
                :src="item.img.value"
                height="30"
                width="50"
                class="me-4"
              />
              <h6 class="text-h6">
                {{ item.title }}
              </h6>
            </div>
            <div class="d-none d-sm-block text-body-1">
              {{ item.type }}
            </div>
          </div>
          <VDivider v-if="index !== paymentMethodsData.length - 1" />
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
