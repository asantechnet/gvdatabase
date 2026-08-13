<script setup lang="ts">
interface UserData {
  id: number | null
  fullName: string
  company: string
  username: string
  role: string
  country: string
  contact: string | undefined
  email: string | undefined
  currentPlan: string
  status: string | undefined
  avatar: string
  taskDone: number | null
  projectDone: number | null
  taxId: string
  language: string
}

interface Props {
  userData?: UserData
  isDialogVisible: boolean
}

interface Emit {
  (e: 'submit', value: UserData): void
  (e: 'update:isDialogVisible', val: boolean): void
}

const props = withDefaults(defineProps<Props>(), {
  userData: () => ({
    id: 0,
    fullName: '',
    company: '',
    role: '',
    username: '',
    country: '',
    contact: '',
    email: '',
    currentPlan: '',
    status: '',
    avatar: '',
    taskDone: null,
    projectDone: null,
    taxId: '',
    language: '',
  }),
})

const emit = defineEmits<Emit>()

const userData = ref<UserData>(structuredClone(toRaw(props.userData)))
const isUseAsBillingAddress = ref(false)

watch(() => props, () => {
  userData.value = structuredClone(toRaw(props.userData))
})

const onFormSubmit = () => {
  emit('update:isDialogVisible', false)
  emit('submit', userData.value)
}

const onFormReset = () => {
  userData.value = structuredClone(toRaw(props.userData))

  emit('update:isDialogVisible', false)
}

const dialogModelValueUpdate = (val: boolean) => {
  emit('update:isDialogVisible', val)
}
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    @update:model-value="dialogModelValueUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-sm-10 pa-2">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          {{ $t('components.dialogs.userinfoeditdialog.editUserInformation') }}
        </h4>
        <p class="text-body-1 text-center mb-6">
          {{ $t('components.dialogs.userinfoeditdialog.updatingUserDetailsWillReceiveAPrivacyAudit') }}
        </p>

        <!-- 👉 Form -->
        <VForm
          class="mt-6"
          @submit.prevent="onFormSubmit"
        >
          <VRow>
            <!-- 👉 First Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="userData.fullName.split(' ')[0]"
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
                v-model="userData.fullName.split(' ')[1]"
                :label="$t('common.messages.lastName')"
                :placeholder="$t('common.messages.doe')"
              />
            </VCol>

            <!-- 👉 Username -->
            <VCol cols="12">
              <AppTextField
                v-model="userData.username"
                :label="$t('components.dialogs.userinfoeditdialog.username')"
                :placeholder="$t('components.dialogs.userinfoeditdialog.johnDoe007')"
              />
            </VCol>

            <!-- 👉 Billing Email -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="userData.email"
                :label="$t('common.fields.email')"
                placeholder="johndoe@email.com"
              />
            </VCol>

            <!-- 👉 Status -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="userData.status"
                :label="$t('common.fields.status')"
                :placeholder="$t('common.statuses.active')"
                :items="['Active', 'Inactive', 'Pending']"
              />
            </VCol>

            <!-- 👉 Tax Id -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="userData.taxId"
                :label="$t('components.dialogs.userinfoeditdialog.taxID')"
                placeholder="123456789"
              />
            </VCol>

            <!-- 👉 Contact -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="userData.contact"
                :label="$t('common.messages.phoneNumber')"
                placeholder="+1 9876543210"
              />
            </VCol>

            <!-- 👉 Language -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="userData.language"
                closable-chips
                chips
                multiple
                :label="$t('components.dialogs.userinfoeditdialog.language')"
                :placeholder="$t('components.dialogs.userinfoeditdialog.english')"
                :items="['English', 'Spanish', 'French']"
              />
            </VCol>

            <!-- 👉 Country -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="userData.country"
                :label="$t('components.dialogs.userinfoeditdialog.country')"
                :placeholder="$t('components.dialogs.userinfoeditdialog.unitedStates')"
                :items="['United States', 'United Kingdom', 'France']"
              />
            </VCol>

            <!-- 👉 Switch -->
            <VCol cols="12">
              <VSwitch
                v-model="isUseAsBillingAddress"
                density="compact"
                :label="$t('common.messages.useAsABillingAddress')"
              />
            </VCol>

            <!-- 👉 Submit and Cancel -->
            <VCol
              cols="12"
              class="d-flex flex-wrap justify-center gap-4"
            >
              <VBtn type="submit">
                {{ $t('common.actions.submit2') }}
              </VBtn>

              <VBtn
                color="secondary"
                variant="tonal"
                @click="onFormReset"
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
