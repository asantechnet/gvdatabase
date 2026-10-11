<script setup lang="ts">
import laptopGirl from '@images/illustrations/laptop-girl.png'

const props = defineProps<{
  isDialogVisible: boolean
}>()

const emit = defineEmits<{
  (e: 'update:isDialogVisible', val: boolean): void
  (e: 'updatedData', val: unknown): void
}>()

const currentStep = ref(0)

const createApp = computed(() => [
  {
    icon: 'tabler-file-text',
    title: translate('components.dialogs.createappdialog.details'),
    subtitle: translate('components.dialogs.createappdialog.enterDetails'),
  },
  {
    icon: 'tabler-id',
    title: translate('components.dialogs.createappdialog.frameworks'),
    subtitle: translate('components.dialogs.createappdialog.selectFramework'),
  },
  {
    icon: 'tabler-database',
    title: translate('components.dialogs.createappdialog.database'),
    subtitle: translate('components.dialogs.createappdialog.selectDatabase'),
  },
  {
    icon: 'tabler-credit-card',
    title: translate('components.dialogs.createappdialog.billing'),
    subtitle: translate('components.dialogs.createappdialog.paymentSummary'),
  },
  {
    icon: 'tabler-check',
    title: translate('common.actions.submit3'),
    subtitle: translate('common.actions.submit2'),
  },
])

const categories = computed(() => [
  {
    icon: 'tabler-file-text',
    color: 'info',
    title: translate('components.dialogs.createappdialog.crmApplication'),
    subtitle: translate('components.dialogs.createappdialog.scalesWithAnyBusiness'),
    slug: 'crm-application',
  },
  {
    icon: 'tabler-shopping-cart',
    color: 'success',
    title: translate('components.dialogs.createappdialog.ecommercePlatforms'),
    subtitle: translate('components.dialogs.createappdialog.growYourBusinessWithApp'),
    slug: 'ecommerce-application',
  },
  {
    icon: 'tabler-device-laptop',
    color: 'error',
    title: translate('components.dialogs.createappdialog.onlineLearningPlatform'),
    subtitle: translate('components.dialogs.createappdialog.startLearningToday'),
    slug: 'online-learning-application',
  },
])

const frameworks = computed(() => [
  {
    icon: 'tabler-brand-react-native',
    color: 'info',
    title: translate('components.dialogs.createappdialog.reactNative'),
    subtitle: translate('components.dialogs.createappdialog.createTrulyNativeApps'),
    slug: 'react-framework',
  },
  {
    icon: 'tabler-brand-angular',
    color: 'error',
    title: translate('components.dialogs.createappdialog.angular'),
    subtitle: translate('components.dialogs.createappdialog.mostSuitedForYourApplication'),
    slug: 'angular-framework',
  },
  {
    icon: 'tabler-brand-vue',
    color: 'success',
    title: translate('components.dialogs.createappdialog.vue'),
    subtitle: translate('components.dialogs.createappdialog.jsWebFrameworks'),
    slug: 'js-framework',
  },
  {
    icon: 'tabler-brand-html5',
    color: 'warning',
    title: translate('components.dialogs.createappdialog.html'),
    subtitle: translate('components.dialogs.createappdialog.progressiveFramework'),
    slug: 'html-framework',
  },
])

const databases = computed(() => [
  {
    icon: 'tabler-brand-firebase',
    color: 'error',
    title: translate('components.dialogs.createappdialog.firebase'),
    subtitle: translate('components.dialogs.createappdialog.cloudFirestore'),
    slug: 'firebase-database',
  },
  {
    icon: 'tabler-brand-amazon',
    color: 'warning',
    title: translate('components.dialogs.createappdialog.aws'),
    subtitle: translate('components.dialogs.createappdialog.amazonFastNoSQLDatabase'),
    slug: 'aws-database',
  },
  {
    icon: 'tabler-database',
    color: 'info',
    title: translate('components.dialogs.createappdialog.mysql'),
    subtitle: translate('components.dialogs.createappdialog.basicMySQLDatabase'),
    slug: 'mysql-database',
  },
])

const createAppData = ref({
  category: 'crm-application',
  framework: 'js-framework',
  database: 'firebase-database',
  cardNumber: null,
  cardName: '',
  cardExpiry: '',
  cardCvv: '',
  isSave: true,
})

const dialogVisibleUpdate = (val: boolean) => {
  emit('update:isDialogVisible', val)
  currentStep.value = 0
}

watch(() => props, () => {
  if (!props.isDialogVisible)
    currentStep.value = 0
})

const onSubmit = () => {
  // eslint-disable-next-line no-alert
  alert(translate('components.dialogs.createappdialog.submitted'))
  emit('updatedData', createAppData.value)
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="900"
    min-height="590"
    @update:model-value="dialogVisibleUpdate"
  >
    <!-- 👉 dialog close btn -->
    <DialogCloseBtn
      size="small"
      @click="emit('update:isDialogVisible', false)"
    />
    <VCard
      class="create-app-dialog"
      min-height="590"
    >
      <VCardText class="pa-5 pa-sm-16">
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          {{ $t('components.dialogs.createappdialog.createApp') }}
        </h4>
        <p class="text-body-1 text-center mb-6">
          {{ $t('components.dialogs.createappdialog.provideDataWithThisFormToCreateYourApp') }}
        </p>

        <VRow>
          <VCol
            cols="12"
            sm="5"
            md="4"
            lg="3"
          >
            <AppStepper
              v-model:current-step="currentStep"
              direction="vertical"
              :items="createApp"
              icon-size="22"
              class="stepper-icon-step-bg"
            />
          </VCol>

          <VCol
            cols="12"
            sm="7"
            md="8"
            lg="9"
          >
            <VWindow
              v-model="currentStep"
              class="disable-tab-transition stepper-content"
            >
              <!-- 👉 category -->
              <VWindowItem>
                <AppTextField
                  :label="$t('common.messages.applicationName')"
                  :placeholder="$t('common.messages.applicationName')"
                />

                <h5 class="text-h5 mt-6 mb-4">
                  {{ $t('common.fields.category') }}
                </h5>
                <VRadioGroup v-model="createAppData.category">
                  <VList class="card-list">
                    <VListItem
                      v-for="category in categories"
                      :key="category.title"
                      @click="createAppData.category = category.slug"
                    >
                      <template #prepend>
                        <VAvatar
                          size="46"
                          rounded
                          variant="tonal"
                          :color="category.color"
                        >
                          <VIcon
                            :icon="category.icon"
                            size="30"
                          />
                        </VAvatar>
                      </template>

                      <VListItemTitle>
                        <h6 class="text-h6 mb-1">
                          {{ category.title }}
                        </h6>
                      </VListItemTitle>
                      <VListItemSubtitle>
                        {{ category.subtitle }}
                      </VListItemSubtitle>

                      <template #append>
                        <VRadio :value="category.slug" />
                      </template>
                    </VListItem>
                  </VList>
                </VRadioGroup>
              </VWindowItem>

              <!-- 👉 Frameworks -->
              <VWindowItem>
                <h5 class="text-h5 mb-4">
                  {{ $t('components.dialogs.createappdialog.selectFramework') }}
                </h5>
                <VRadioGroup v-model="createAppData.framework">
                  <VList class="card-list">
                    <VListItem
                      v-for="framework in frameworks"
                      :key="framework.title"
                      @click="createAppData.framework = framework.slug"
                    >
                      <template #prepend>
                        <VAvatar
                          size="46"
                          rounded
                          variant="tonal"
                          :color="framework.color"
                        >
                          <VIcon
                            :icon="framework.icon"
                            size="30"
                          />
                        </VAvatar>
                      </template>
                      <VListItemTitle>
                        <h6 class="text-h6 mb-1">
                          {{ framework.title }}
                        </h6>
                      </VListItemTitle>
                      <VListItemSubtitle>
                        {{ framework.subtitle }}
                      </VListItemSubtitle>
                      <template #append>
                        <VRadio :value="framework.slug" />
                      </template>
                    </VListItem>
                  </VList>
                </VRadioGroup>
              </VWindowItem>

              <!-- 👉 Database Engine -->
              <VWindowItem>
                <AppTextField
                  :label="$t('components.dialogs.createappdialog.databaseName')"
                  :placeholder="$t('components.dialogs.createappdialog.userdb')"
                />

                <h5 class="text-h5 mt-6 mb-4">
                  {{ $t('components.dialogs.createappdialog.selectDatabaseEngine') }}
                </h5>
                <VRadioGroup v-model="createAppData.database">
                  <VList class="card-list">
                    <VListItem
                      v-for="database in databases"
                      :key="database.title"
                      @click="createAppData.database = database.slug"
                    >
                      <template #prepend>
                        <VAvatar
                          size="46"
                          rounded
                          variant="tonal"
                          :color="database.color"
                        >
                          <VIcon
                            :icon="database.icon"
                            size="30"
                          />
                        </VAvatar>
                      </template>
                      <VListItemTitle>
                        <h6 class="text-h6 mb-1">
                          {{ database.title }}
                        </h6>
                      </VListItemTitle>
                      <VListItemSubtitle>
                        {{ database.subtitle }}
                      </VListItemSubtitle>
                      <template #append>
                        <VRadio :value="database.slug" />
                      </template>
                    </VListItem>
                  </VList>
                </VRadioGroup>
              </VWindowItem>

              <!-- 👉 Billing form -->
              <VWindowItem>
                <h6 class="text-h6 mb-6">
                  {{ $t('components.dialogs.createappdialog.paymentSummary') }}
                </h6>

                <VForm>
                  <VRow>
                    <VCol cols="12">
                      <AppTextField
                        v-model="createAppData.cardNumber"
                        :label="$t('components.dialogs.createappdialog.cardNumber')"
                        placeholder="1234 1234 1234 1234"
                        type="number"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="createAppData.cardName"
                        :label="$t('components.dialogs.createappdialog.nameOnCard')"
                        :placeholder="$t('components.dialogs.createappdialog.johnDoe')"
                      />
                    </VCol>

                    <VCol
                      cols="6"
                      md="3"
                    >
                      <AppTextField
                        v-model="createAppData.cardExpiry"
                        :label="$t('components.dialogs.createappdialog.expiry')"
                        placeholder="MM/YY"
                      />
                    </VCol>

                    <VCol
                      cols="6"
                      md="3"
                    >
                      <AppTextField
                        v-model="createAppData.cardCvv"
                        label="CVV"
                        placeholder="123"
                      />
                    </VCol>

                    <VCol cols="12">
                      <VSwitch
                        v-model="createAppData.isSave"
                        :label="$t('components.dialogs.createappdialog.saveCardForFutureBilling')"
                      />
                    </VCol>
                  </VRow>
                </VForm>
              </VWindowItem>

              <VWindowItem class="text-center">
                <h5 class="text-h5 mb-1">
                  {{ $t('common.actions.submit') }}
                </h5>
                <p class="text-sm mb-4">
                  {{ $t('components.dialogs.createappdialog.submitToKickstartYourProject') }}
                </p>

                <VImg
                  :src="laptopGirl"
                  width="176"
                  class="mx-auto"
                />
              </VWindowItem>
            </VWindow>

            <div class="d-flex justify-space-between mt-6">
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="currentStep === 0"
                @click="currentStep--"
              >
                <VIcon
                  icon="tabler-arrow-left"
                  start
                  class="flip-in-rtl"
                />
                {{ $t('components.dialogs.createappdialog.previous') }}
              </VBtn>

              <VBtn
                v-if="createApp.length - 1 === currentStep"
                color="success"
                @click="onSubmit"
              >
                {{ $t('common.actions.submit4') }}
              </VBtn>

              <VBtn
                v-else
                @click="currentStep++"
              >
                {{ $t('common.actions.next') }}

                <VIcon
                  icon="tabler-arrow-right"
                  end
                  class="flip-in-rtl"
                />
              </VBtn>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.stepper-content .card-list {
  --v-card-list-gap: 16px;
}
</style>
