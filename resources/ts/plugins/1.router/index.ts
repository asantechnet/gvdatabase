import type { App } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'

import { redirects } from './additional-routes'
import { setupGuards } from './guards'
import { explicitRoutes } from './routes'

const router = createRouter({
  // Vite compiles assets into /build, but the SPA is served from the origin
  // root. Using BASE_URL here would mount the router at /build in production.
  history: createWebHistory('/'),
  routes: [...redirects, ...explicitRoutes],
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },
})

setupGuards(router)

export { router }

export default function (app: App) {
  app.use(router)
}
