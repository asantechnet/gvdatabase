/**
 * Application service worker (Workbox `injectManifest`).
 *
 * Served from the origin root (`/sw.js`) so its default scope is `/`.
 * `__APP_VERSION__` and the precache manifest are injected at build time.
 *
 * Caching rules, in registration order (first match wins):
 *  - never cache auth, heartbeat, version, manifest or file downloads/exports;
 *  - GET /api/** -> NetworkFirst, 5s timeout, 500 entries / 24h, status 200 only;
 *  - navigations -> NetworkFirst so the SPA shell boots for every route offline;
 *  - images and fonts -> StaleWhileRevalidate (branding logos live under /storage).
 *
 * Every data cache is version-namespaced. On activate, caches from any other
 * version are deleted, so a deploy reclaims both old assets and old API data.
 * Stylesheet content is never rewritten, so print CSS order stays untouched.
 */
import type { PrecacheEntry } from 'workbox-precaching'
import { cleanupOutdatedCaches, matchPrecache, precacheAndRoute } from 'workbox-precaching'
import { registerRoute, setCatchHandler } from 'workbox-routing'
import { NetworkFirst, NetworkOnly, StaleWhileRevalidate } from 'workbox-strategies'
import { ExpirationPlugin } from 'workbox-expiration'
import { CacheableResponsePlugin } from 'workbox-cacheable-response'

/**
 * Minimal worker globals. `lib.webworker` cannot be enabled project-wide
 * (it conflicts with `lib.dom` for the rest of the SPA), so only the members
 * this worker uses are declared locally.
 */
interface ServiceWorkerHost extends EventTarget {
  readonly location: Location
  readonly clients: { claim: () => Promise<void> }
  readonly __WB_MANIFEST: Array<PrecacheEntry | string>
  skipWaiting: () => Promise<void>
}

type WaitUntilEvent = Event & { waitUntil?: (promise: Promise<unknown>) => void }

declare const self: ServiceWorkerHost

const VERSION = __APP_VERSION__
const API_CACHE = `api-${VERSION}`
const PAGES_CACHE = `pages-${VERSION}`
const ASSET_CACHE = `assets-${VERSION}`
const OFFLINE_URL = '/offline.html'
// The notification chime is a `public/` asset, so it is not part of the Vite
// manifest. It is precached explicitly so a heartbeat batch still rings when
// the app is launched offline.
const DING_URL = '/sounds/notification-ding.mp3'
const DAY = 24 * 60 * 60

// The built SPA plus the static offline fallback. `public/` files are not part
// of the Vite bundle, so the fallback is added explicitly.
precacheAndRoute([
  ...self.__WB_MANIFEST,
  { url: OFFLINE_URL, revision: VERSION },
  { url: DING_URL, revision: VERSION },
])
cleanupOutdatedCaches()

const isSameOrigin = (url: URL) => url.origin === self.location.origin

/**
 * Endpoints that must always hit the network. The heartbeat clears a
 * server-side flag, so a cached response would silently stop notification
 * delivery; auth/refresh tokens are single-use; downloads and exports must
 * never be served stale.
 */
const isNeverCached = (url: URL) =>
  url.pathname.startsWith('/api/auth/')
  || url.pathname === '/api/notifications/heartbeat'
  || url.pathname === '/api/version'
  || url.pathname === '/manifest.webmanifest'
  || url.pathname.startsWith('/api/files/download')
  || /\/api\/.*\/(?:download|export)$/.test(url.pathname)
  || /^\/api\/(?:reports|educational-reports)\/[^/]+\/export$/.test(url.pathname)

registerRoute(
  ({ url }) => isSameOrigin(url) && isNeverCached(url),
  new NetworkOnly(),
)

registerRoute(
  ({ request, url }) =>
    isSameOrigin(url)
    && request.method === 'GET'
    && url.pathname.startsWith('/api/')
    && !isNeverCached(url),
  new NetworkFirst({
    cacheName: API_CACHE,
    networkTimeoutSeconds: 5,
    plugins: [
      new CacheableResponsePlugin({ statuses: [200] }),
      new ExpirationPlugin({ maxEntries: 500, maxAgeSeconds: DAY, purgeOnQuotaError: true }),
    ],
  }),
)

registerRoute(
  ({ request }) => request.mode === 'navigate',
  new NetworkFirst({
    cacheName: PAGES_CACHE,
    networkTimeoutSeconds: 4,
    plugins: [new CacheableResponsePlugin({ statuses: [200] })],
  }),
)

registerRoute(
  ({ request, url }) =>
    isSameOrigin(url)
    && (request.destination === 'image'
      || request.destination === 'font'
      || url.pathname.startsWith('/storage/')),
  new StaleWhileRevalidate({
    cacheName: ASSET_CACHE,
    plugins: [
      new CacheableResponsePlugin({ statuses: [200] }),
      new ExpirationPlugin({ maxEntries: 200, maxAgeSeconds: 30 * DAY, purgeOnQuotaError: true }),
    ],
  }),
)

// First-ever offline navigation has no cached shell yet.
setCatchHandler(async ({ request }) => {
  if (request.mode === 'navigate') {
    const offline = await matchPrecache(OFFLINE_URL)
    if (offline)
      return offline
  }

  return Response.error()
})

self.addEventListener('activate', (event: Event) => {
  (event as WaitUntilEvent).waitUntil?.((async () => {
    const keep = new Set([API_CACHE, PAGES_CACHE, ASSET_CACHE])
    const names = await caches.keys()

    await Promise.all(names.map(name => {
      // Workbox owns its precache caches and prunes superseded revisions
      // itself; every other app cache must belong to this version.
      if (keep.has(name) || name.startsWith('workbox-precache'))
        return undefined

      return caches.delete(name)
    }))
  })())
})

self.addEventListener('message', (event: Event) => {
  const data = (event as MessageEvent).data as { type?: string } | undefined
  const waitUntil = (event as WaitUntilEvent).waitUntil

  if (data?.type === 'SKIP_WAITING') {
    waitUntil?.((async () => {
      // Only the user-consented reload activates the waiting worker, so an
      // open form is never discarded silently.
      await self.skipWaiting()
      await self.clients.claim()
    })())
  }

  if (data?.type === 'PURGE_API_CACHE') {
    waitUntil?.((async () => {
      const names = await caches.keys()

      await Promise.all(
        names.filter(name => name.startsWith('api-')).map(name => caches.delete(name)),
      )
    })())
  }
})
