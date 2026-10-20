import { ref } from 'vue'
import { purgeApiCache } from '@/utils/offlineCache'
import { PwaUpdates } from '@/utils/pwaUpdates'

export const pwaUpdateReady = ref(false)
export const pwaUpdateApplying = ref(false)
export const pwaUpdateFailed = ref(0)
export const pwaOfflineReady = ref(false)

const OFFLINE_READY_STORAGE_KEY = 'vuexy-pwa-offline-ready'
let updates: Promise<PwaUpdates | null> | null = null

const failed = (error: unknown) => {
  console.warn('[PWA] Update check or activation failed; a later check can retry.', error)
}

export const checkPwaUpdate = async (version?: string) => {
  if (!updates)
    registerPwa()

  const coordinator = await updates

  await coordinator?.check(version)
}

export const reloadToUpdatedApp = async () => {
  const coordinator = await updates

  await coordinator?.apply()
}

export default function registerPwa() {
  if (updates || typeof window === 'undefined' || !('serviceWorker' in navigator) || import.meta.env.DEV)
    return

  updates = navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' })
    .then(registration => new PwaUpdates({
      version: __APP_VERSION__,
      registration,
      container: navigator.serviceWorker,
      ready: ready => { pwaUpdateReady.value = ready },
      applying: applying => { pwaUpdateApplying.value = applying },
      offlineReady: () => {
        try {
          if (localStorage.getItem(OFFLINE_READY_STORAGE_KEY))
            return
          localStorage.setItem(OFFLINE_READY_STORAGE_KEY, 'true')
        }
        catch { /* Storage restrictions must not break registration. */ }
        pwaOfflineReady.value = true
      },
      failed: error => {
        failed(error)
        if (pwaUpdateApplying.value)
          pwaUpdateFailed.value++
      },
      purge: purgeApiCache,
      reload: () => window.location.reload(),
    }))
    .catch(error => {
      failed(error)
      updates = null

      return null
    })
}
