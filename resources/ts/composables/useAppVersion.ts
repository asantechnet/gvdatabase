import { onMounted, ref } from 'vue'
import { getAppVersion } from '@/api/appVersion'
import { purgeApiCache } from '@/utils/offlineCache'
import { checkPwaUpdate } from '@/plugins/pwa'
import { useVisiblePolling } from '@/composables/useVisiblePolling'
import { isBackgroundActive } from '@/utils/backgroundActivity'

/**
 * Detects a deployed frontend build that no longer matches the running one.
 *
 * `__APP_VERSION__` is baked into the bundle at build time; `/api/version`
 * reports what the server currently serves. A mismatch means someone deployed
 * while this client stayed open. Only a verified, ready worker raises the
 * reload toast; API data is purged on applying the update or booting a new build.
 */

const VERSION_STORAGE_KEY = 'vuexy-app-version'
const VERSION_CHECK_INTERVAL = 15 * 60 * 1000

export function useAppVersion() {
  const deployedVersion = ref<string | null>(null)

  let checking = false

  const checkDeployedVersion = async () => {
    // Offline: the endpoint is NetworkOnly, so there is nothing to compare.
    if (!isBackgroundActive() || checking)
      return

    checking = true
    try {
      const { version } = await getAppVersion()

      deployedVersion.value = version
      if (isBackgroundActive())
        await checkPwaUpdate(version)
    }
    catch (error) {
      console.warn('[PWA] Could not check the deployed version; will retry.', error)
    }
    finally {
      checking = false
    }
  }

  // A hard reload after a deploy still carries the previous version's API
  // data until the worker activates, so purge it as soon as the app boots.
  try {
    const previousVersion = localStorage.getItem(VERSION_STORAGE_KEY)
    if (previousVersion && previousVersion !== __APP_VERSION__)
      void purgeApiCache()

    localStorage.setItem(VERSION_STORAGE_KEY, __APP_VERSION__)
  }
  catch { /* Restricted storage must not stop version checks. */ }

  const polling = useVisiblePolling(checkDeployedVersion, VERSION_CHECK_INTERVAL)

  onMounted(() => {
    polling.start(true)
  })

  return { deployedVersion, checkDeployedVersion }
}
