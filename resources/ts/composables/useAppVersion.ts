import { ref } from 'vue'
import { getAppVersion } from '@/api/appVersion'
import { purgeApiCache } from '@/utils/offlineCache'
import { pwaUpdateReady } from '@/plugins/pwa'
import { useVisiblePolling } from '@/composables/useVisiblePolling'

/**
 * Detects a deployed frontend build that no longer matches the running one.
 *
 * `__APP_VERSION__` is baked into the bundle at build time; `/api/version`
 * reports what the server currently serves. A mismatch means someone deployed
 * while this client stayed open, so the old-version caches are purged and the
 * reload toast is raised (never a silent reload, which would discard input).
 */

const VERSION_STORAGE_KEY = 'vuexy-app-version'
const VERSION_CHECK_INTERVAL = 15 * 60 * 1000

export function useAppVersion() {
  const deployedVersion = ref<string | null>(null)

  const flagNewDeploy = (version: string) => {
    if (deployedVersion.value === version)
      return

    deployedVersion.value = version

    if (version === __APP_VERSION__)
      return

    void purgeApiCache()
    pwaUpdateReady.value = true
  }

  const checkDeployedVersion = async () => {
    // Offline: the endpoint is NetworkOnly, so there is nothing to compare.
    if (!navigator.onLine)
      return

    try {
      const { version } = await getAppVersion()

      if (version)
        flagNewDeploy(version)
    }
    catch {
      // A transient failure keeps the current version; the next tick retries.
    }
  }

  // A hard reload after a deploy still carries the previous version's API
  // data until the worker activates, so purge it as soon as the app boots.
  const previousVersion = localStorage.getItem(VERSION_STORAGE_KEY)
  if (previousVersion && previousVersion !== __APP_VERSION__)
    void purgeApiCache()

  localStorage.setItem(VERSION_STORAGE_KEY, __APP_VERSION__)

  const polling = useVisiblePolling(checkDeployedVersion, VERSION_CHECK_INTERVAL)

  onMounted(() => {
    void checkDeployedVersion()
    polling.start()
  })

  return { deployedVersion, checkDeployedVersion }
}
