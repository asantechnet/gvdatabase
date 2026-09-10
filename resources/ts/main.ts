import { createApp } from 'vue'

import Toast from 'vue-toastification'
import App from '@/App.vue'

// import ckeditor from "@ckeditor/ckeditor5-vue";
import * as Api from '@/api'
import { registerPlugins } from '@core/utils/plugins'

// Styles
import '@core-scss/template/index.scss'
import '@styles/styles.scss'
import 'vue-toastification/dist/index.css'

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

// app.use(ckeditor);

// Register plugins
registerPlugins(app)
app.use(Toast, { timeout: 5000, closeOnClick: true })

// Register API modules globally
app.config.globalProperties.$api = Api

// Mount vue app
app.mount('#app')
