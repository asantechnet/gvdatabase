import type { App } from 'vue'
import { ref } from 'vue'
import { Workbox } from 'workbox-window'

/**
 * PWA registration and update orchestration.
 *
 * The Blade shell bypasses Vite's HTML pipeline, so the worker is registered
 * here (from `main.ts` via the plugin glob) instead of through an injected
 * script. The worker is emitted at the origin root and controls `/`.
 *
 * Activation is never automatic: a waiting worker only takes over after the
 * user accepts the reload toast, so unsaved forms are not discarded silently.
 */

export const pwaUpdateReady = ref(false)
export const pwaOfflineReady = ref(false)

const OFFLINE_READY_STORAGE_KEY = 'vuexy-pwa-offline-ready'
const SW_URL = '/sw.js'

let workbox: Workbox | null = null
let reloading = false

const reload = () => {
  if (reloading)
    return

  reloading = true
  window.location.reload()
}

/** Applies the waiting worker, then reloads once it controls the page. */
export const reloadToUpdatedApp = async () => {
  const waiting = async () => (await navigator.serviceWorker.getRegistration())?.waiting

  if (workbox) {
    let worker = await waiting().catch(() => undefined)

    // A deploy without a known waiting worker (version mismatch) may still
    // have one after an update check.
    if (!worker) {
      await workbox.update().catch(() => undefined)
      worker = await waiting().catch(() => undefined)
    }

    if (worker) {
      // The worker's `SKIP_WAITING` handler activates it; `controlling` reloads.
      workbox.messageSkipWaiting()
      window.setTimeout(reload, 1500)

      return
    }
  }

  reload()
}

export default function () {
  if (typeof window === 'undefined' || !('serviceWorker' in navigator) || import.meta.env.DEV)
    return

  workbox = new Workbox(SW_URL, { scope: '/' })

  workbox.addEventListener('waiting', () => {
    pwaUpdateReady.value = true
  })
  workbox.addEventListener('controlling', reload)

  void workbox.register().then(() => {
    // Shown once per install, not on every launch.
    if (localStorage.getItem(OFFLINE_READY_STORAGE_KEY))
      return

    pwaOfflineReady.value = true
    localStorage.setItem(OFFLINE_READY_STORAGE_KEY, 'true')
  }).catch(() => {
    // Registration fails on insecure origins; the app keeps working online.
  })
}
