import { execSync } from 'node:child_process'
import { readFileSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import laravel from 'laravel-vite-plugin'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { defineConfig, type Plugin } from 'vite'
import MetaLayouts from 'vite-plugin-vue-meta-layouts'
import { VitePWA } from 'vite-plugin-pwa'
import vuetify from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'

/**
 * Deployed build identity: package version plus the commit being built. The
 * client compares it with `GET /api/version` to notice a new deploy.
 */
const packageJson = JSON.parse(readFileSync(path.resolve(__dirname, 'package.json'), 'utf8')) as { version: string }

const gitSha = (() => {
  try {
    return execSync('git rev-parse --short HEAD', { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim()
  }
  catch {
    return null
  }
})()

const buildVersion = gitSha ? `${packageJson.version}+${gitSha}` : `${packageJson.version}+${Date.now()}`

/** Writes `public/version.json`, the source of `GET /api/version`. */
const buildVersionFile = (): Plugin => ({
  name: 'app-build-version',
  apply: 'build',
  writeBundle: {
    order: 'post',
    handler: () => {
      writeFileSync(
        path.resolve(__dirname, 'public/version.json'),
        `${JSON.stringify({ version: buildVersion, built_at: new Date().toISOString() }, null, 2)}\n`,
      )
    },
  },
})

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue({
      template: {
        compilerOptions: {
          isCustomElement: tag =>
            tag === 'swiper-container' || tag === 'swiper-slide',
        },

        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    laravel({
      input: ['resources/ts/main.ts'],
      refresh: true,
    }),
    vueJsx(), // Docs: https://github.com/vuetifyjs/vuetify-loader/tree/master/packages/vite-plugin
    vuetify({
      styles: {
        configFile: 'resources/styles/variables/_vuetify.scss',
      },
    }), // Docs: https://github.com/dishait/vite-plugin-vue-meta-layouts?tab=readme-ov-file
    MetaLayouts({
      target: './resources/ts/layouts',
      defaultLayout: 'default',
    }), // Docs: https://github.com/antfu/unplugin-vue-components#unplugin-vue-components
    Components({
      dirs: [
        'resources/ts/@core/components',
        'resources/ts/components',
      ],
      dts: true,
      resolvers: [
        componentName => {
          // Auto import `VueApexCharts`
          if (componentName === 'VueApexCharts') {
            return {
              name: 'default',
              from: 'vue3-apexcharts',
              as: 'VueApexCharts',
            }
          }
        },
      ],
    }), // Docs: https://github.com/antfu/unplugin-auto-import#unplugin-auto-import
    AutoImport({
      imports: ['vue', '@vueuse/core', '@vueuse/math', 'vue-i18n', 'pinia'],
      dirs: [
        './resources/ts/@core/utils',
        './resources/ts/@core/composable/',
        './resources/ts/composables/',
        './resources/ts/utils/',
        './resources/ts/plugins/*/composables/*',
      ],
      vueTemplate: true,

      // ℹ️ Disabled to avoid confusion & accidental usage
      ignore: ['useAppApi', 'useCookies', 'useStorage'],
    }), // Docs: https://github.com/intlify/bundle-tools/tree/main/packages/unplugin-vue-i18n#intlifyunplugin-vue-i18n
    VueI18nPlugin({
      runtimeOnly: true,
      compositionOnly: true,
      include: [
        fileURLToPath(
          new URL('./resources/ts/plugins/i18n/locales/**', import.meta.url),
        ),
      ],
    }),
    svgLoader(),
    VitePWA({
      strategies: 'injectManifest',
      srcDir: 'resources/ts/service-worker',
      filename: 'sw.ts',
      // Emit the worker at the origin root (`/sw.js`) so its scope is `/`
      // without needing a `Service-Worker-Allowed` header.
      outDir: 'public',
      // Activation is user-consented: the worker waits until the reload toast
      // is clicked (see `plugins/pwa`).
      registerType: 'prompt',
      injectRegister: null,
      // Laravel serves the manifest dynamically from Settings.
      manifest: false,
      devOptions: { enabled: false },
      injectManifest: {
        // No HTML: the offline fallback lives at the origin root and is added
        // explicitly by the worker with the build version as its revision.
        globPatterns: ['**/*.{js,css,svg,png,ico,woff,woff2,ttf,webmanifest}'],
        maximumFileSizeToCacheInBytes: 4 * 1024 * 1024,
      },
    }),
    buildVersionFile(),
  ],
  define: {
    'process.env': {},
    __APP_VERSION__: JSON.stringify(buildVersion),
  },
  resolve: {
    alias: {
      '@core-scss': fileURLToPath(
        new URL('./resources/styles/@core', import.meta.url),
      ),
      '@': fileURLToPath(new URL('./resources/ts', import.meta.url)),
      '@themeConfig': fileURLToPath(
        new URL('./themeConfig.ts', import.meta.url),
      ),
      '@core': fileURLToPath(new URL('./resources/ts/@core', import.meta.url)),
      '@layouts': fileURLToPath(
        new URL('./resources/ts/@layouts', import.meta.url),
      ),
      '@images': fileURLToPath(new URL('./resources/images/', import.meta.url)),
      '@styles': fileURLToPath(new URL('./resources/styles/', import.meta.url)),
      '@configured-variables': fileURLToPath(
        new URL('./resources/styles/variables/_template.scss', import.meta.url),
      ),
      'frappe-gantt/dist/frappe-gantt.css': path.resolve(
        __dirname,
        'node_modules/frappe-gantt/dist/frappe-gantt.css',
      ),
    },
  },
  build: {
    // 5000 (kB) was high enough to hide the 1.67 MB entry chunk that used to ride
    // along with all three locales. 1500 flags that regression — the entry warned
    // at 1630 kB — while staying quiet for legitimately large lazy document
    // chunks, so a warning here means something actually grew.
    chunkSizeWarningLimit: 1500,
  },
  optimizeDeps: {
    exclude: ['vuetify'],
  },
})
