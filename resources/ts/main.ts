import { createApp } from 'vue'

import Toast from 'vue-toastification'
import App from '@/App.vue'

import { registerPlugins } from '@core/utils/plugins'
import { getInitialLocale, loadLocale } from '@/plugins/i18n'

// Styles
import '@core-scss/template/index.scss'
import 'vue-toastification/dist/index.css'
import '@styles/styles.scss'
import { restoreUiScale } from '@/utils/uiScale'

restoreUiScale()

// Browsers do not expose reliable hover selectors for viewport scrollbars.
// Track the narrow scrollbar gutter so the full rail is only shown on intent.
const updateViewportScrollbarState = (event: PointerEvent) => {
  const activationZone = 14
  const root = document.documentElement

  const isNearVerticalScrollbar = root.scrollHeight > window.innerHeight
    && event.clientX >= root.clientWidth - activationZone

  const isNearHorizontalScrollbar = root.scrollWidth > window.innerWidth
    && event.clientY >= root.clientHeight - activationZone

  root.classList.toggle('is-scrollbar-hovered', isNearVerticalScrollbar || isNearHorizontalScrollbar)
}

window.addEventListener('pointermove', updateViewportScrollbarState, { passive: true })
window.addEventListener('blur', () => document.documentElement.classList.remove('is-scrollbar-hovered'))

// Create vue app
const app = createApp(App)

// Register plugins
registerPlugins(app)
app.use(Toast, {
  timeout: 3000,
  closeOnClick: true,
  transition: 'Vue-Toastification__slideBlurred',
})

// Every locale except `en` is loaded on demand, so make sure the persisted
// locale and its fallback chain are present before the first paint. If a chunk
// fails we still mount; the eager `en` messages carry the view.
loadLocale(getInitialLocale())
  .catch(() => false)
  .finally(() => app.mount('#app'))
