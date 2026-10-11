<script setup lang="ts">
const shortcuts = [
  {
    icon: 'tabler-calendar',
    title: translate('common.messages.calendar'),
    subtitle: translate('components.navigation.navbarshortcuts.appointments'),
    to: { name: 'apps-calendar' },
  },
  {
    icon: 'tabler-user',
    title: translate('common.messages.users'),
    subtitle: translate('components.navigation.navbarshortcuts.manageUsers'),
    to: { name: 'apps-user-list' },
  },
  {
    icon: 'tabler-users',
    title: translate('components.navigation.navbarshortcuts.roleManagement'),
    subtitle: translate('components.navigation.navbarshortcuts.permission'),
    to: { name: 'apps-roles' },
  },
  {
    icon: 'tabler-device-desktop-analytics',
    title: translate('components.navigation.navbarshortcuts.dashboard'),
    subtitle: translate('components.navigation.navbarshortcuts.dashboardAnalytics'),
    to: { name: 'dashboards-analytics' },
  },
  {
    icon: 'tabler-settings',
    title: translate('common.messages.settings'),
    subtitle: translate('components.navigation.navbarshortcuts.accountSettings'),
    to: { name: 'pages-account-settings-tab', params: { tab: 'account' } },
  },

]
</script>

<template>
  <Shortcuts :shortcuts="shortcuts" />
</template>
