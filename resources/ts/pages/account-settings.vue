<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AccountSettingsAccount from '@/views/pages/account-settings/AccountSettingsAccount.vue'
import AccountSettingsConnections from '@/views/pages/account-settings/AccountSettingsConnections.vue'
import AccountSettingsNotification from '@/views/pages/account-settings/AccountSettingsNotification.vue'
import AccountSettingsSecurity from '@/views/pages/account-settings/AccountSettingsSecurity.vue'

const route = useRoute()
const router = useRouter()
const tabs = [
  { value: 'account', title: 'Account', icon: 'tabler-user', component: AccountSettingsAccount },
  { value: 'security', title: 'Security', icon: 'tabler-lock', component: AccountSettingsSecurity },
  { value: 'notifications', title: 'Notifications', icon: 'tabler-bell', component: AccountSettingsNotification },
  { value: 'connections', title: 'Connections', icon: 'tabler-link', component: AccountSettingsConnections },
]
const activeTab = computed(() => tabs.find(tab => tab.value === route.params.tab)?.value ?? 'account')
const activeComponent = computed(() => tabs.find(tab => tab.value === activeTab.value)?.component ?? AccountSettingsAccount)

function selectTab(tab: string) {
  router.push({ name: 'account-settings', params: { tab } })
}
</script>

<template>
  <VRow>
    <VCol cols="12" md="3" lg="2">
      <VCard>
        <VTabs direction="vertical" :model-value="activeTab" color="primary" @update:model-value="selectTab">
          <VTab v-for="tab in tabs" :key="tab.value" :value="tab.value"><VIcon start :icon="tab.icon" />{{ tab.title }}</VTab>
        </VTabs>
      </VCard>
    </VCol>
    <VCol cols="12" md="9" lg="10"><component :is="activeComponent" /></VCol>
  </VRow>
</template>

<style scoped>
@media (max-width: 959px) {
  .v-tabs { flex-direction: row !important; overflow-x: auto; }
}
</style>
