<script setup lang="ts">
import home from '@images/svg/home.svg'
import office from '@images/svg/office.svg'

interface BillingAddress {
  firstName: string | undefined
  lastName: string | undefined
  selectedCountry: string | null
  addressLine1: string
  addressLine2: string
  landmark: string
  contact: string
  country: string | null
  city: string
  state: string
  zipCode: number | null
}
interface Props {
  billingAddress?: BillingAddress
  isDialogVisible: boolean
}
interface Emit {
  (e: 'update:isDialogVisible', value: boolean): void
  (e: 'submit', value: BillingAddress): void
}

const props = withDefaults(defineProps<Props>(), {
  billingAddress: () => ({
    firstName: '',
    lastName: '',
    selectedCountry: null,
    addressLine1: '',
    addressLine2: '',
    landmark: '',
    contact: '',
    country: null,
    city: '',
    state: '',
    zipCode: null,
  }),
})

const emit = defineEmits<Emit>()

const billingAddress = ref<BillingAddress>(structuredClone(toRaw(props.billingAddress)))

const resetForm = () => {
  emit('update:isDialogVisible', false)
  billingAddress.value = structuredClone(toRaw(props.billingAddress))
}

const onFormSubmit = () => {
  emit('update:isDialogVisible', false)
  emit('submit', billingAddress.value)
}

const selectedAddress = ref('Home')

const addressTypes = [
  {
    icon: { icon: home, size: '28' },
    title: translate('components.dialogs.addeditaddressdialog.home'),
    desc: 'Delivery Time (9am - 9pm)',
    value: 'Home',
  },
  {
    icon: { icon: office, size: '28' },
    title: translate('common.fields.office'),
    desc: 'Delivery Time (9am - 5pm)',
    value: 'Office',
  },
]
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900 "
    :model-value="props.isDialogVisible"
    @update:model-value="val => $emit('update:isDialogVisible', val)"
  >
    <!-- 👉 Dialog close btn -->
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard
      v-if="props.billingAddress"
      class="pa-sm-10 pa-2"
    >
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          {{ $t((props.billingAddress.addressLine1 || props.billingAddress.addressLine2)
            ? 'components.dialogs.addeditaddressdialog.editAddress'
            : 'components.dialogs.addeditaddressdialog.addAddress') }}
        </h4>
        <p class="text-body-1 text-center mb-6">
          {{ $t('components.dialogs.addeditaddressdialog.addNewAddressForExpressDelivery') }}
        </p>

        <div class="d-flex mb-6">
          <CustomRadiosWithIcon
            v-model:selected-radio="selectedAddress"
            :radio-content="addressTypes"
            :grid-column="{ sm: '6', cols: '12' }"
          />
        </div>

        <!-- 👉 Form -->
        <VForm @submit.prevent="onFormSubmit">
          <VRow>
            <!-- 👉 First Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="billingAddress.firstName"
                :label="$t('common.messages.firstName')"
                :placeholder="$t('common.messages.john')"
              />
            </VCol>

            <!-- 👉 Last Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="billingAddress.lastName"
                :label="$t('common.messages.lastName')"
                :placeholder="$t('common.messages.doe')"
              />
            </VCol>

            <!-- 👉 Select Country -->
            <VCol cols="12">
              <AppSelect
                v-model="billingAddress.selectedCountry"
                :label="$t('common.messages.selectCountry')"
                :placeholder="$t('common.messages.selectCountry')"
                :items="['USA', 'Aus', 'Canada', 'NZ']"
              />
            </VCol>

            <!-- 👉 Address Line 1 -->
            <VCol cols="12">
              <AppTextField
                v-model="billingAddress.addressLine1"
                :label="$t('components.dialogs.addeditaddressdialog.addressLine1')"
                :placeholder="$t('components.dialogs.addeditaddressdialog.n12BusinessPark')"
              />
            </VCol>

            <!-- 👉 Address Line 2 -->
            <VCol cols="12">
              <AppTextField
                v-model="billingAddress.addressLine2"
                :label="$t('components.dialogs.addeditaddressdialog.addressLine2')"
                :placeholder="$t('components.dialogs.addeditaddressdialog.mallRoad')"
              />
            </VCol>

            <!-- 👉 Landmark -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="billingAddress.landmark"
                :label="$t('components.dialogs.addeditaddressdialog.landmark')"
                :placeholder="$t('components.dialogs.addeditaddressdialog.nrHardRockCafe')"
              />
            </VCol>

            <!-- 👉 City -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="billingAddress.city"
                :label="$t('components.dialogs.addeditaddressdialog.city')"
                :placeholder="$t('components.dialogs.addeditaddressdialog.losAngeles')"
              />
            </VCol>

            <!-- 👉 State -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="billingAddress.state"
                :label="$t('components.dialogs.addeditaddressdialog.state')"
                :placeholder="$t('components.dialogs.addeditaddressdialog.california')"
              />
            </VCol>

            <!-- 👉 Zip Code -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="billingAddress.zipCode"
                :label="$t('components.dialogs.addeditaddressdialog.zipCode')"
                placeholder="99950"
                type="number"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch :label="$t('common.messages.useAsABillingAddress')" />
            </VCol>

            <!-- 👉 Submit and Cancel button -->
            <VCol
              cols="12"
              class="text-center"
            >
              <VBtn
                type="submit"
                class="me-3"
              >
                {{ $t('common.actions.submit') }}
              </VBtn>

              <VBtn
                variant="tonal"
                color="secondary"
                @click="resetForm"
              >
                {{ $t('common.actions.cancel') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
