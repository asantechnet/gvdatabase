<script lang="ts" setup>
import BillingHistoryTable from './BillingHistoryTable.vue'

import mastercard from '@images/icons/payments/mastercard.png'
import visa from '@images/icons/payments/visa.png'

interface CardDetails {
  name: string
  number: string
  expiry: string
  isPrimary: boolean
  type: string
  cvv: string
  image: string
}
const selectedPaymentMethod = ref('credit-debit-atm-card')

const isPricingPlanDialogVisible = ref(false)
const isConfirmDialogVisible = ref(false)
const isCardEditDialogVisible = ref(false)
const isCardDetailSaveBilling = ref(false)

const creditCards: CardDetails[] = [
  {
    name: 'Tom McBride',
    number: '5531234567899856',
    expiry: '12/24',
    isPrimary: true,
    type: 'visa',
    cvv: '456',
    image: mastercard,
  },
  {
    name: 'Mildred Wagner',
    number: '4851234567895896',
    expiry: '10/27',
    isPrimary: false,
    type: 'mastercard',
    cvv: '123',
    image: visa,
  },
]

const countryList = ['United States', 'Canada', 'United Kingdom', 'Australia', 'New Zealand', 'India', 'Russia', 'China', 'Japan']

const currentCardDetails = ref()

const openEditCardDialog = (cardDetails: CardDetails) => {
  currentCardDetails.value = cardDetails

  isCardEditDialogVisible.value = true
}

const cardNumber = ref(135632156548789)
const cardName = ref('john Doe')
const cardExpiryDate = ref('05/24')
const cardCvv = ref(420)

const resetPaymentForm = () => {
  cardNumber.value = 135632156548789
  cardName.value = 'john Doe'
  cardExpiryDate.value = '05/24'
  cardCvv.value = 420

  selectedPaymentMethod.value = 'credit-debit-atm-card'
}
</script>

<template>
  <VRow>
    <!-- 👉 Current Plan -->
    <VCol cols="12">
      <VCard :title="$t('components.account.accountsettingsbillingandplans.currentPlan')">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <div>
                <div class="mb-6">
                  <h3 class="text-body-1 text-high-emphasis font-weight-medium mb-1">
                    {{ $t('components.account.accountsettingsbillingandplans.yourCurrentPlanIsBasic') }}
                  </h3>
                  <p class="text-body-1">
                    {{ $t('components.account.accountsettingsbillingandplans.aSimpleStartForEveryone') }}
                  </p>
                </div>

                <div class="mb-6">
                  <h3 class="text-body-1 text-high-emphasis font-weight-medium mb-1">
                    {{ $t('components.account.accountsettingsbillingandplans.activeUntilDec092021') }}
                  </h3>
                  <p class="text-body-1">
                    {{ $t('components.account.accountsettingsbillingandplans.weWillSendYouANotificationUponSubscriptionExpiration') }}
                  </p>
                </div>

                <div>
                  <h3 class="text-body-1 text-high-emphasis font-weight-medium mb-1">
                    <span class="me-2">{{ $t('components.account.accountsettingsbillingandplans.n199PerMonth2') }}</span>
                    <VChip
                      color="primary"
                      size="small"
                      label
                    >
                      {{ $t('components.account.accountsettingsbillingandplans.popular') }}
                    </VChip>
                  </h3>
                  <p class="text-base mb-0">
                    {{ $t('components.account.accountsettingsbillingandplans.standardPlanForSmallToMediumBusinesses') }}
                  </p>
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VAlert
                icon="tabler-alert-triangle"
                type="warning"
                variant="tonal"
              >
                <VAlertTitle class="mb-1">
                  {{ $t('components.account.accountsettingsbillingandplans.weNeedYourAttention') }}
                </VAlertTitle>

                <span>{{ $t('components.account.accountsettingsbillingandplans.yourPlanRequiresUpdate') }}</span>
              </VAlert>

              <!-- progress -->
              <h6 class="d-flex font-weight-medium text-body-1 text-high-emphasis mt-6 mb-1">
                <span>{{ $t('components.account.accountsettingsbillingandplans.days') }}</span>
                <VSpacer />
                <span>{{ $t('components.account.accountsettingsbillingandplans.n12Of30Days2') }}</span>
              </h6>

              <VProgressLinear
                color="primary"
                rounded
                model-value="15"
              />

              <p class="text-body-2 mt-1 mb-0">
                {{ $t('components.account.accountsettingsbillingandplans.n18DaysRemainingUntilYourPlanRequiresUpdate2') }}
              </p>
            </VCol>

            <VCol cols="12">
              <div class="d-flex flex-wrap gap-4">
                <VBtn @click="isPricingPlanDialogVisible = true">
                  {{ $t('components.account.accountsettingsbillingandplans.upgradePlan') }}
                </VBtn>

                <VBtn
                  color="error"
                  variant="tonal"
                  @click="isConfirmDialogVisible = true"
                >
                  {{ $t('components.account.accountsettingsbillingandplans.cancelSubscription') }}
                </VBtn>
              </div>
            </VCol>
          </VRow>

          <!-- 👉 Confirm Dialog -->
          <ConfirmDialog
            v-model:is-dialog-visible="isConfirmDialogVisible"
            confirmation-question="Are you sure to cancel your subscription?"
            cancel-msg="Unsubscription Cancelled!!"
            cancel-title="Cancelled"
            confirm-msg="Your subscription cancelled successfully."
            confirm-title="Unsubscribed!"
          />

          <!-- 👉 plan and pricing dialog -->
          <PricingPlanDialog v-model:is-dialog-visible="isPricingPlanDialogVisible" />
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Payment Methods -->
    <VCol cols="12">
      <VCard :title="$t('components.account.accountsettingsbillingandplans.paymentMethods')">
        <VCardText>
          <VForm @submit.prevent="() => {}">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <VRow>
                  <!-- 👉 card type switch -->
                  <VCol cols="12">
                    <VRadioGroup
                      v-model="selectedPaymentMethod"
                      inline
                    >
                      <VRadio
                        value="credit-debit-atm-card"
                        :label="$t('components.account.accountsettingsbillingandplans.creditDebitATMCard2')"
                        color="primary"
                        class="me-6"
                      />
                      <VRadio
                        value="paypal-account"
                        :label="$t('components.account.accountsettingsbillingandplans.paypalAccount2')"
                        color="primary"
                      />
                    </VRadioGroup>
                  </VCol>

                  <VCol cols="12">
                    <VRow>
                      <!-- 👉 Card Number -->
                      <VCol cols="12">
                        <AppTextField
                          v-model="cardNumber"
                          :label="$t('components.account.accountsettingsbillingandplans.cardNumber')"
                          placeholder="1234 1234 1234 1234"
                          type="number"
                        />
                      </VCol>

                      <!-- 👉 Name -->
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          v-model="cardName"
                          :label="$t('common.fields.name')"
                          :placeholder="$t('components.account.accountsettingsbillingandplans.johnDoe')"
                        />
                      </VCol>

                      <!-- 👉 Expiry date -->
                      <VCol
                        cols="6"
                        md="3"
                      >
                        <AppTextField
                          v-model="cardExpiryDate"
                          :label="$t('components.account.accountsettingsbillingandplans.expiryDate')"
                          :placeholder="$t('components.account.accountsettingsbillingandplans.mmYY')"
                        />
                      </VCol>

                      <!-- 👉 Cvv code -->
                      <VCol
                        cols="6"
                        md="3"
                      >
                        <AppTextField
                          v-model="cardCvv"
                          type="number"
                          :label="$t('components.account.accountsettingsbillingandplans.cvvCode')"
                          placeholder="123"
                        />
                      </VCol>

                      <!-- 👉 Future Billing switch -->
                      <VCol cols="12">
                        <VSwitch
                          v-model="isCardDetailSaveBilling"
                          density="compact"
                          :label="$t('components.account.accountsettingsbillingandplans.saveCardForFutureBilling2')"
                        />
                      </VCol>
                    </VRow>
                  </VCol>
                  <VCol
                    cols="12"
                    class="d-flex flex-wrap gap-4"
                  >
                    <VBtn type="submit">
                      {{ $t('common.messages.saveChanges3') }}
                    </VBtn>
                    <VBtn
                      color="secondary"
                      variant="tonal"
                      @click="resetPaymentForm"
                    >
                      {{ $t('common.actions.cancel') }}
                    </VBtn>
                  </VCol>
                </VRow>
              </VCol>

              <!-- 👉 Saved Cards -->
              <VCol
                cols="12"
                md="6"
              >
                <h6 class="text-body-1 text-high-emphasis font-weight-medium mb-6">
                  {{ $t('components.account.accountsettingsbillingandplans.myCards') }}
                </h6>

                <div class="d-flex flex-column gap-y-6">
                  <VCard
                    v-for="card in creditCards"
                    :key="card.name"
                    flat
                    color="rgba(var(--v-theme-on-surface),var(--v-hover-opacity))"
                  >
                    <VCardText class="d-flex flex-sm-row flex-column">
                      <div class="text-no-wrap">
                        <img
                          :src="card.image"
                          height="25"
                        >
                        <h4 class="my-2 text-body-1 text-high-emphasis d-flex align-center">
                          <div class="me-4 font-weight-medium">
                            {{ card.name }}
                          </div>
                          <VChip
                            v-if="card.isPrimary"
                            label
                            color="primary"
                            size="small"
                          >
                            {{ $t('components.account.accountsettingsbillingandplans.primary') }}
                          </VChip>
                        </h4>
                        <div class="text-body-1">
                          **** **** **** {{ card.number.substring(card.number.length - 4) }}
                        </div>
                      </div>

                      <VSpacer />

                      <div class="d-flex flex-column text-sm-end">
                        <div class="d-flex flex-wrap gap-4 order-sm-0 order-1">
                          <VBtn
                            variant="tonal"
                            size="small"
                            @click="openEditCardDialog(card)"
                          >
                            {{ $t('common.actions.edit') }}
                          </VBtn>
                          <VBtn
                            color="error"
                            size="small"
                            variant="tonal"
                          >
                            {{ $t('common.actions.delete') }}
                          </VBtn>
                        </div>
                        <span class="text-body-2 my-4 order-sm-1 order-0">Card expires at {{ card.expiry }}</span>
                      </div>
                    </VCardText>
                  </VCard>
                </div>

                <!-- 👉 Add Edit Card Dialog -->
                <CardAddEditDialog
                  v-model:is-dialog-visible="isCardEditDialogVisible"
                  :card-details="currentCardDetails"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Billing Address -->
    <VCol cols="12">
      <VCard :title="$t('common.messages.billingAddress')">
        <VCardText>
          <VForm @submit.prevent="() => {}">
            <VRow>
              <!-- 👉 Company name -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  :label="$t('components.account.accountsettingsbillingandplans.companyName')"
                  :placeholder="$t('components.account.accountsettingsbillingandplans.pixinvent')"
                />
              </VCol>

              <!-- 👉 Billing Email -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  :label="$t('components.account.accountsettingsbillingandplans.billingEmail')"
                  placeholder="pixinvent@email.com"
                />
              </VCol>

              <!-- 👉 Tax ID -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  :label="$t('components.account.accountsettingsbillingandplans.taxID')"
                  placeholder="123 123 1233"
                />
              </VCol>

              <!-- 👉 Vat Number -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  :label="$t('components.account.accountsettingsbillingandplans.vatNumber2')"
                  placeholder="121212"
                />
              </VCol>

              <!-- 👉 Mobile -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  dirty
                  :label="$t('components.account.accountsettingsbillingandplans.phoneNumber')"
                  type="number"
                  prefix="US (+1)"
                  placeholder="+1 123 456 7890"
                />
              </VCol>

              <!-- 👉 Country -->
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  :label="$t('components.account.accountsettingsbillingandplans.country')"
                  :items="countryList"
                  :placeholder="$t('components.account.accountsettingsbillingandplans.selectCountry')"
                />
              </VCol>

              <!-- 👉 Billing Address -->
              <VCol cols="12">
                <AppTextField
                  :label="$t('common.messages.billingAddress')"
                  :placeholder="$t('components.account.accountsettingsbillingandplans.n1234MainSt2')"
                />
              </VCol>

              <!-- 👉 State -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  :label="$t('components.account.accountsettingsbillingandplans.state')"
                  :placeholder="$t('components.account.accountsettingsbillingandplans.newYork')"
                />
              </VCol>

              <!-- 👉 Zip Code -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  :label="$t('components.account.accountsettingsbillingandplans.zipCode')"
                  type="number"
                  placeholder="100006"
                />
              </VCol>

              <!-- 👉 Actions Button -->
              <VCol
                cols="12"
                class="d-flex flex-wrap gap-4"
              >
                <VBtn type="submit">
                  {{ $t('common.messages.saveChanges3') }}
                </VBtn>
                <VBtn
                  type="reset"
                  color="secondary"
                  variant="tonal"
                >
                  {{ $t('components.account.accountsettingsbillingandplans.discard') }}
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Billing History -->
    <VCol cols="12">
      <BillingHistoryTable />
    </VCol>
  </VRow>
</template>

<style lang="scss">
.pricing-dialog {
  .pricing-title {
    font-size: 1.5rem !important;
  }

  .v-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    box-shadow: none;
  }
}
</style>
