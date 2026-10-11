<script lang="ts" setup>
import { useToast } from 'vue-toastification'
import AuthApi, { type Account, type NotificationPreferences } from '@/api/auth'

interface NotificationEvent { key: string; title: string; description: string; in_app: boolean }

const notificationEvents = ref<NotificationEvent[]>([
  { key: 'audit_activity', title: translate('components.account.accountsettingsnotification.auditActivity'), description: translate('components.account.accountsettingsnotification.recordEditsDeletionsRestoresAndReviewActions'), in_app: true },
  { key: 'finance_updates', title: translate('components.account.accountsettingsnotification.financeUpdates'), description: translate('components.account.accountsettingsnotification.incomeExpensesAndPaymentActivity'), in_app: true },
  { key: 'work_updates', title: translate('components.account.accountsettingsnotification.workUpdates'), description: translate('components.account.accountsettingsnotification.tasksBoardsCommentsAndContractActivity'), in_app: true },
  { key: 'applicant_updates', title: translate('notificationPreferences.applicantUpdates'), description: translate('notificationPreferences.applicantCaseAssignmentsStagesAndReminders'), in_app: true },
  { key: 'staff_updates', title: translate('components.account.accountsettingsnotification.staffUpdates'), description: translate('components.account.accountsettingsnotification.employeeAttendanceSalaryAndBonusActivity'), in_app: true },
])

const initialPreferences = ref<NotificationPreferences>()
const saving = ref(false)
const toast = useToast()

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
  }
  catch (exception: any) {
    toast.error(exception.message || translate('notificationPreferences.loadFailed'))
  }
}

async function save() {
  saving.value = true
  try {
    const notifications = preferences()

    await AuthApi.updateNotifications({ notifications })
    initialPreferences.value = structuredClone(notifications)
    toast.success(translate('components.account.accountsettingsnotification.notificationPreferencesSaved'))
  }
  catch (exception: any) {
    toast.error(exception.message || translate('notificationPreferences.saveFailed'))
  }
  finally {
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
    <VCardItem
      :title="$t('components.account.accountsettingsnotification.notificationPreferences')"
      :subtitle="$t('notificationPreferences.subtitle')"
    />

    <VCardText
      class="px-0"
      data-account-guide="notification-events"
    >
      <VTable class="text-no-wrap rounded">
        <thead>
          <tr>
            <th scope="col">
              {{ $t('components.account.accountsettingsnotification.event') }}
            </th><th
              scope="col"
              class="text-center"
            >
              {{ $t('components.account.accountsettingsnotification.inAPP') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="event in notificationEvents"
            :key="event.key"
          >
            <td>
              <div class="text-body-1 text-high-emphasis">
                {{ event.title }}
              </div><div class="text-caption text-medium-emphasis text-wrap">
                {{ event.description }}
              </div>
            </td>
            <td class="text-center">
              <VCheckbox
                v-model="event.in_app"
                hide-details
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>

    <VCardText data-account-guide="notification-actions">
      <div class="d-flex flex-wrap gap-4">
        <VBtn
          :loading="saving"
          @click="save"
        >
          {{ $t('common.messages.saveChanges2') }}
        </VBtn>
        <VBtn
          color="secondary"
          variant="tonal"
          :disabled="saving"
          @click="discard"
        >
          {{ $t('common.messages.discard') }}
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>
