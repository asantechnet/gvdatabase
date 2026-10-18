<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { pwaOfflineReady, pwaUpdateReady, reloadToUpdatedApp } from '@/plugins/pwa'

const { t } = useI18n()
const toast = useToast()

// Persistent until clicked: reloading is the user's decision.
watch(pwaUpdateReady, ready => {
  if (!ready)
    return

  toast.info(`${t('pwa.updateAvailable')} ${t('pwa.reloadNow')}`, {
    id: 'pwa-update-available',
    closeOnClick: false,
    onClick: reloadToUpdatedApp,
    timeout: false,
  })
}, { immediate: true })

watch(pwaOfflineReady, ready => {
  if (ready)
    toast.success(t('pwa.offlineReady'), { id: 'pwa-offline-ready' })
}, { immediate: true })
</script>

<template>
  <span
    class="d-none"
    aria-hidden="true"
  />
</template>
