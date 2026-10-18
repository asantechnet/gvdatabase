/**
 * Cache Storage helpers shared by the app and its service worker.
 *
 * API data is cached per deployment version (`api-<version>`). Purging is
 * identity-driven: every login, logout and 401 goes through
 * `clearAuthTokens()`, which calls `purgeApiCache()` so one user's data can
 * never be served to another.
 */

const API_CACHE_PREFIX = 'api-'

export const purgeApiCache = async (): Promise<void> => {
  if (typeof window === 'undefined')
    return

  if (typeof caches !== 'undefined') {
    try {
      const names = await caches.keys()

      await Promise.all(
        names
          .filter(name => name.startsWith(API_CACHE_PREFIX))
          .map(name => caches.delete(name)),
      )
    }
    catch {
      // Storage can be unavailable (private mode, eviction); the worker still
      // receives the purge message below.
    }
  }

  if ('serviceWorker' in navigator)
    navigator.serviceWorker.controller?.postMessage({ type: 'PURGE_API_CACHE' })
}
