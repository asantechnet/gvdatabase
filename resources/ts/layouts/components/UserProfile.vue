<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useRouter } from 'vue-router'
import AuthApi from '@/api/auth'
import { clearAuthTokens, getRefreshToken } from '@/utils/authStorage'
import { applyPermissions, canAccess, clearPermissions } from '@/utils/authorization'
import WorkdayControl from '@/components/workday/WorkdayControl.vue'
import EndDayDialog from '@/components/workday/EndDayDialog.vue'
import MyPaidTimeOffDialog from '@/components/paid-time-off/MyPaidTimeOffDialog.vue'
import WorkdayApi, { WorkdayLocationError } from '@/api/workday'
import { setNotificationSoundEnabled } from '@/composables/useNotificationHeartbeat'
import { useToast } from 'vue-toastification'
import { confirmActionWarning } from '@/composables/useActionWarning'

const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const userData = useCookie<any>('userData')
const paidTimeOffDialog = ref(false)
const startDayDialog = ref(false)
const endDayDialog = ref(false)
const startLoading = ref(false)

const userProfileList = [
  { type: 'navItem', icon: 'tabler-user', title: translate('components.navigation.userprofile.myAccount'), to: { name: 'account-settings', params: { tab: 'account' } } },
  { type: 'navItem', icon: 'tabler-lock', title: translate('common.messages.security'), to: { name: 'account-settings', params: { tab: 'security' } } },
  { type: 'navItem', icon: 'tabler-bell', title: translate('components.navigation.userprofile.notificationPreferences'), to: { name: 'account-settings', params: { tab: 'notifications' } } },
  { type: 'navItem', icon: 'tabler-calendar-stats', title: translate('pages.settings.accountSettings.accountSettings.workHistory'), to: { name: 'account-settings', params: { tab: 'work-history' } } },
  ...(canAccess('view_recurring_events')
    ? [{ type: 'navItem', icon: 'tabler-repeat', title: translate('common.navigation.recurringEvents'), to: { name: 'recurring-events' } }]
    : []),
  { type: 'divider' },
  { type: 'navItem', icon: 'tabler-bell-ringing', title: translate('components.navigation.userprofile.myNotifications'), to: { name: 'notifications' } },
]

async function refreshUser() {
  try {
    userData.value = (await AuthApi.me()).data.data
    applyPermissions(userData.value.permissions ?? [])
    setNotificationSoundEnabled(userData.value.notification_sound === true)
  }
  catch { /* The auth guard handles an expired session. */ }
}

async function logout() {
  const confirmed = await confirmActionWarning({
    key: 'logout-current-session',
    title: t('common.messages.areYouSure'),
    message: 'You will be signed out of this device and returned to the sign-in screen.',
    confirmText: t('components.navigation.userprofile.logout'),
    cancelText: t('common.actions.cancel'),
    icon: 'tabler-logout',
    tone: 'error',
  })
  if (!confirmed)
    return

  try { await AuthApi.logout(getRefreshToken()) }
  catch { /* Clear local state even if the token has already expired. */ }
  useCookie('accessToken').value = null
  userData.value = null
  clearAuthTokens()
  clearPermissions()
  await router.replace('/login')
}

async function handleStartDay() {
  if (startLoading.value) return
  startLoading.value = true
  try {
    await WorkdayApi.start()
    startDayDialog.value = false
    window.dispatchEvent(new CustomEvent('workday-status-changed'))
  }
  catch (error) {
    if (error instanceof WorkdayLocationError)
      toast.warning(t(error.reason === 'session_changed' ? 'backend.errors.business.workday_session_changed' : error.reason === 'unsupported' ? 'pages.settings.geofence.notSupported' : 'components.account.geofence.permissionRequired'))
    // Server validation and business messages come from the shared API notifier.
  }
  finally {
    startLoading.value = false
  }
}

onMounted(() => {
  refreshUser()
})
</script>

<template>
  <VBadge
    v-if="userData"
    dot
    bordered
    location="bottom right"
    offset-x="1"
    offset-y="2"
    color="success"
  >
    <VAvatar
      size="38"
      class="cursor-pointer"
      :color="!userData.avatar ? 'primary' : undefined"
      :variant="!userData.avatar ? 'tonal' : undefined"
    >
      <VImg
        v-if="userData.avatar"
        :src="userData.avatar"
      /><VIcon
        v-else
        icon="tabler-user"
      />
      <VMenu
        activator="parent"
        width="280"
        location="bottom end"
        offset="12px"
      >
        <VList>
          <VListItem>
            <div class="d-flex gap-3 align-center">
              <VAvatar
                :color="!userData.avatar ? 'primary' : undefined"
                :variant="!userData.avatar ? 'tonal' : undefined"
              >
                <VImg
                  v-if="userData.avatar"
                  :src="userData.avatar"
                /><VIcon
                  v-else
                  icon="tabler-user"
                />
              </VAvatar><div>
                <h6 class="text-h6 font-weight-medium">
                  {{ userData.name }}
                </h6><VListItemSubtitle class="text-disabled">
                  {{ userData.email }}
                </VListItemSubtitle>
              </div>
            </div>
          </VListItem>
          <VDivider class="my-2" />
          <PerfectScrollbar :options="{ wheelPropagation: false }">
            <template
              v-for="item in userProfileList"
              :key="item.title"
            >
              <VListItem
                v-if="item.type === 'navItem'"
                :to="item.to"
              >
                <template #prepend>
                  <VIcon
                    :icon="item.icon"
                    size="22"
                  />
                </template><VListItemTitle>{{ item.title }}</VListItemTitle>
              </VListItem>
              <VDivider
                v-else
                class="my-2"
              />
            </template>
            <VListItem
              prepend-icon="tabler-beach"
              @click="paidTimeOffDialog = true"
            >
              <VListItemTitle>{{ $t('pages.leaveRequests.myTitle') }}</VListItemTitle>
            </VListItem>
            <WorkdayControl
              :key="userData?.id"
              @open-start-dialog="startDayDialog = true"
              @open-end-dialog="endDayDialog = true"
            />
            <div class="px-4 py-2">
              <VBtn
                block
                size="small"
                color="error"
                append-icon="tabler-logout"
                @click="logout"
              >
                {{ $t('components.navigation.userprofile.logout') }}
              </VBtn>
            </div>
          </PerfectScrollbar>
        </VList>
      </VMenu>
    </VAvatar>
  </VBadge>
  <MyPaidTimeOffDialog v-model="paidTimeOffDialog" />
  <EndDayDialog
    :key="userData?.id"
    v-model="endDayDialog"
  />
  <VDialog
    v-model="startDayDialog"
    :persistent="startLoading"
    max-width="560"
  >
    <VCard>
      <VCardText class="pa-8 text-center">
        <VAvatar
          color="primary"
          variant="tonal"
          size="72"
          class="mb-5"
        >
          <VIcon
            icon="tabler-sunrise"
            size="38"
          />
        </VAvatar>
        <h2 class="text-h4 mb-2">
          {{ $t('components.account.workday.startTitle') }}
        </h2>
        <p class="text-body-1 text-medium-emphasis mb-6">
          {{ $t('components.account.workday.startDescription') }}
        </p>
        <VBtn
          size="large"
          color="primary"
          prepend-icon="tabler-player-play"
          :loading="startLoading"
          @click="handleStartDay"
        >
          {{ $t('components.account.workday.startMyDay') }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
