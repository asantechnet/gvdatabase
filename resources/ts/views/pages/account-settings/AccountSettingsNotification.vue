<script lang="ts" setup>
import AuthApi, { type Account, type NotificationPreferences } from '@/api/auth'

type NotificationEvent = { key: string; title: string; description: string; in_app: boolean }

const notificationEvents = ref<NotificationEvent[]>([
  { key: 'audit_activity', title: translate('components.account.accountsettingsnotification.auditActivity'), description: translate('components.account.accountsettingsnotification.recordEditsDeletionsRestoresAndReviewActions'), in_app: true },
  { key: 'finance_updates', title: translate('components.account.accountsettingsnotification.financeUpdates'), description: translate('components.account.accountsettingsnotification.invoicesIncomeExpensesAndPaymentActivity'), in_app: true },
  { key: 'work_updates', title: translate('components.account.accountsettingsnotification.workUpdates'), description: translate('components.account.accountsettingsnotification.tasksBoardsCommentsAndContractActivity'), in_app: true },
  { key: 'staff_updates', title: translate('components.account.accountsettingsnotification.staffUpdates'), description: translate('components.account.accountsettingsnotification.employeeAttendanceSalaryAndBonusActivity'), in_app: true },
])

const initialPreferences = ref<NotificationPreferences>()
const saving = ref(false)
const message = ref('')
const error = ref('')

const preferences = (): NotificationPreferences => Object.fromEntries(notificationEvents.value.map(event => [event.key, { in_app: event.in_app }]))

function applyPreferences(savedPreferences?: NotificationPreferences) {
  notificationEvents.value.forEach(event => {
    const saved = savedPreferences?.[event.key] as { in_app?: boolean; browser?: boolean } | undefined
    // Migrate the old browser preference to the new in-app preference.
    event.in_app = saved?.in_app ?? saved?.browser ?? true
  })
}

async function load() {
  try {
    const account = (await AuthApi.me()).data.data as Account
    applyPreferences(account.notifications)
    initialPreferences.value = structuredClone(preferences())
  } catch (exception: any) {
    error.value = exception.message || 'Unable to load notification preferences.'
  }
}

async function save() {
  saving.value = true
  try {
    const notifications = preferences()
    await AuthApi.updateNotifications({ notifications })
    initialPreferences.value = structuredClone(notifications)
    message.value = translate('components.account.accountsettingsnotification.notificationPreferencesSaved')
    error.value = ''
  } catch (exception: any) {
    error.value = exception.message || 'Unable to save notification preferences.'
  } finally {
    saving.value = false
  }
}

function discard() {
  applyPreferences(initialPreferences.value)
}

onMounted(load)
</script>

<template>
  <VCard>
    <VAlert v-if="message" type="success" class="ma-4">{{ message }}</VAlert>
    <VAlert v-if="error" type="error" class="ma-4">{{ error }}</VAlert>

    <VCardItem :title="$t('components.account.accountsettingsnotification.notificationPreferences')" subtitle="Choose which Asantech Mini Management events appear in your in-app inbox." />

    <VCardText class="px-0">
      <VTable class="text-no-wrap rounded">
        <thead><tr><th scope="col">{{ $t('components.account.accountsettingsnotification.event') }}</th><th scope="col" class="text-center">{{ $t('components.account.accountsettingsnotification.inAPP') }}</th></tr></thead>
        <tbody>
          <tr v-for="event in notificationEvents" :key="event.key">
            <td><div class="text-body-1 text-high-emphasis">{{ event.title }}</div><div class="text-caption text-medium-emphasis text-wrap">{{ event.description }}</div></td>
            <td class="text-center"><VCheckbox v-model="event.in_app" hide-details /></td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>

    <VCardText>
      <div class="d-flex flex-wrap gap-4">
        <VBtn :loading="saving" @click="save">{{ $t('common.messages.saveChanges2') }}</VBtn>
        <VBtn color="secondary" variant="tonal" :disabled="saving" @click="discard">{{ $t('common.messages.discard') }}</VBtn>
      </div>
    </VCardText>
  </VCard>
</template>
