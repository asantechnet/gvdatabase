<script setup lang="ts">
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'

import { useTheme } from 'vuetify'
import ApiErrorNotifier from '@/components/ApiErrorNotifier.vue'
import { useI18n } from 'vue-i18n'

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
    <VApp :key="locale" :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`">
      <RouterView />
      <ApiErrorNotifier />
      <ScrollToTop />
    </VApp>
  </VLocaleProvider>
</template>
