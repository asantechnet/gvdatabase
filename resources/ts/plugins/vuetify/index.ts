import { deepMerge } from '@antfu/utils'
import type { App } from 'vue'
import { useI18n } from 'vue-i18n'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components/VBtn'
import { VVideo } from 'vuetify/labs/VVideo'
import { createVueI18nAdapter } from 'vuetify/locale/adapters/vue-i18n'
import defaults from './defaults'
import { icons } from './icons'
import { staticPrimaryColor, staticPrimaryDarkenColor, themes } from './theme'
import { themeConfig } from '@themeConfig'
import { getI18n } from '@/plugins/i18n/index'
import { readStoredThemeColors } from '@/utils/themePersistence'
import { contrastingTextColor } from '@/utils/themeContrast'
import { ResponsiveDataTable } from '@/components/responsive-data-table/responsiveDataTable'
import { ResponsiveDataTableServer } from '@/components/responsive-data-table/ResponsiveDataTableServer'

// Styles
import { cookieRef } from '@/@layouts/stores/config'
import '@core-scss/template/libs/vuetify/index.scss'
import 'vuetify/styles'

export default function (app: App) {
  const storedThemeColors = readStoredThemeColors()

  const cookieThemeValues = {
    defaultTheme: resolveVuetifyTheme(themeConfig.app.theme),
    themes: {
      light: {
        colors: {
          'primary': cookieRef('lightThemePrimaryColor', staticPrimaryColor).value,
          'primary-darken-1': cookieRef('lightThemePrimaryDarkenColor', staticPrimaryDarkenColor).value,
        },
      },
      dark: {
        colors: {
          'primary': cookieRef('darkThemePrimaryColor', staticPrimaryColor).value,
          'primary-darken-1': cookieRef('darkThemePrimaryDarkenColor', staticPrimaryDarkenColor).value,
        },
      },
    },
  }

  const storedColorValues: Record<string, string> = {}
  if (storedThemeColors.primary_color) {
    storedColorValues.primary = storedThemeColors.primary_color
    storedColorValues['on-primary'] = contrastingTextColor(storedThemeColors.primary_color)
  }
  if (storedThemeColors.secondary_color) {
    storedColorValues.secondary = storedThemeColors.secondary_color
    storedColorValues['on-secondary'] = contrastingTextColor(storedThemeColors.secondary_color)
  }
  if (storedThemeColors.tertiary_color) {
    storedColorValues.tertiary = storedThemeColors.tertiary_color
    storedColorValues['on-tertiary'] = contrastingTextColor(storedThemeColors.tertiary_color)
  }

  const storedThemeValues = {
    themes: Object.fromEntries(Object.keys(themes).map(name => [name, { colors: storedColorValues }])),
  }

  const optionTheme = deepMerge(deepMerge({ themes }, cookieThemeValues), storedThemeValues)

  const vuetify = createVuetify({
    aliases: {
      IconBtn: VBtn,
    },
    components: {
      VDataTable: ResponsiveDataTable,
      VDataTableServer: ResponsiveDataTableServer,
      VVideo,
    },
    defaults,
    icons,
    theme: optionTheme,
    locale: {
      adapter: createVueI18nAdapter({ i18n: getI18n(), useI18n }),
    },
  })

  app.use(vuetify)
}
