<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { pwaOfflineReady, pwaUpdateFailed, pwaUpdateReady, reloadToUpdatedApp } from '@/plugins/pwa'

const { t } = useI18n()
const toast = useToast()

// Persistent until clicked: reloading is the user's decision.
watch(pwaUpdateReady, ready => {
  if (!ready) {
    toast.dismiss('pwa-update-available')

    return
  }

  toast.info(`${t('pwa.updateAvailable')} ${t('pwa.reloadNow')}`, {
    id: 'pwa-update-available',
    closeOnClick: false,
    closeButton: false,
    draggable: false,
    onClick: reloadToUpdatedApp,
    toastClassName: 'pwa-update-toast',
    timeout: false,
  })
}, { immediate: true })

watch(pwaUpdateFailed, () => {
  toast.error(t('pwa.updateFailed'), { id: 'pwa-update-failed' })
})

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

<style>
.Vue-Toastification__toast.pwa-update-toast {
  cursor: pointer;
}
</style>
