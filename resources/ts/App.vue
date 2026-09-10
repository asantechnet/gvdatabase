<script setup lang="ts">
import { useTheme } from 'vuetify'
import { useI18n } from 'vue-i18n'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'

import ApiErrorNotifier from '@/components/ApiErrorNotifier.vue'
import ActionWarningDialog from '@/components/ActionWarningDialog.vue'
import ProjectFormWarningGuard from '@/components/ProjectFormWarningGuard.vue'

const { global } = useTheme()
const { locale } = useI18n({ useScope: 'global' })

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <!-- ℹ️ This is required to set the background color of active nav link based on currently active global theme's primary -->
    <VApp
      :key="locale"
      :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`"
    >
      <RouterView />
      <ProjectFormWarningGuard />
      <ActionWarningDialog />
      <ApiErrorNotifier />
      <ScrollToTop />
    </VApp>
  </VLocaleProvider>
</template>
