<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AccountSettingsAccount from '@/views/pages/account-settings/AccountSettingsAccount.vue'
import AccountSettingsNotification from '@/views/pages/account-settings/AccountSettingsNotification.vue'
import AccountSettingsSecurity from '@/views/pages/account-settings/AccountSettingsSecurity.vue'
import AccountDeactivationRequests from '@/views/pages/account-settings/AccountDeactivationRequests.vue'
import AccountSettingsWorkHistory from '@/views/pages/account-settings/AccountSettingsWorkHistory.vue'
import SupportPage from '@/pages/support.vue'
import { canAccess } from '@/utils/authorization'

const route = useRoute()
const router = useRouter()

const tabs = computed(() => [
  { value: 'account', title: translate('pages.settings.accountSettings.accountSettings.account'), icon: 'tabler-user', component: AccountSettingsAccount },
  { value: 'security', title: translate('common.messages.security'), icon: 'tabler-lock', component: AccountSettingsSecurity },
  { value: 'notifications', title: translate('common.messages.notifications'), icon: 'tabler-bell', component: AccountSettingsNotification },
  { value: 'work-history', title: translate('pages.settings.accountSettings.accountSettings.workHistory'), icon: 'tabler-calendar-stats', component: AccountSettingsWorkHistory },
  { value: 'support', title: translate('common.navigation.support'), icon: 'tabler-lifebuoy', component: SupportPage },
  ...(canAccess('view_account_deactivation_requests')
    ? [{ value: 'deactivation-requests', title: translate('pages.settings.accountSettings.accountSettings.deactivationRequests'), icon: 'tabler-user-cancel', component: AccountDeactivationRequests }]
    : []),
])

const activeTab = computed(() => tabs.value.find(tab => tab.value === route.params.tab)?.value ?? 'account')
const activeComponent = computed(() => tabs.value.find(tab => tab.value === activeTab.value)?.component ?? AccountSettingsAccount)

function selectTab(tab: unknown) {
  if (typeof tab === 'string')
    router.push({ name: 'account-settings', params: { tab } })
}

</script>

<template>
  <VRow>
    <VCol
      cols="12"
      md="3"
      lg="2"
    >
      <VCard>
        <VTabs
          data-account-guide="navigation"
          direction="vertical"
          :model-value="activeTab"
          color="primary"
          @update:model-value="selectTab"
        >
          <VTab
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
          >
            <VIcon
              start
              :icon="tab.icon"
            />{{ tab.title }}
          </VTab>
        </VTabs>
      </VCard>
    </VCol>
    <VCol
      cols="12"
      md="9"
      lg="10"
    >
      <component
        :is="activeComponent"
        v-bind="activeTab === 'support' ? { embedded: true } : {}"
      />
    </VCol>
  </VRow>
</template>

<style scoped>
@media (max-width: 959px) {
  .v-tabs { flex-direction: row !important; overflow-x: auto; }
}
</style>
