<script setup lang="ts">
import { useTheme } from 'vuetify'
import { useI18n } from 'vue-i18n'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'

import ApiErrorNotifier from '@/components/ApiErrorNotifier.vue'
import ActionWarningDialog from '@/components/ActionWarningDialog.vue'
import FormWarningGuard from '@/components/FormWarningGuard.vue'
import PwaUpdateNotifier from '@/components/PwaUpdateNotifier.vue'
import { useAppVersion } from '@/composables/useAppVersion'

const { global } = useTheme()
const { locale } = useI18n({ useScope: 'global' })

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()

// Watches for a deployed build that no longer matches this bundle.
useAppVersion()
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <!-- ℹ️ This is required to set the background color of active nav link based on currently active global theme's primary -->
    <VApp
      :key="locale"
      :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`"
    >
      <RouterView />
      <FormWarningGuard />
      <ActionWarningDialog />
      <ApiErrorNotifier />
      <PwaUpdateNotifier />
      <ScrollToTop />
    </VApp>
  </VLocaleProvider>
</template>
