import type { App } from 'vue'
import { createI18n } from 'vue-i18n'
import { cookieRef } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

export const supportedLocales = ['en', 'fa-AF', 'ps-AF'] as const
export type SupportedLocale = typeof supportedLocales[number]

const messages = Object.fromEntries(
  Object.entries(
    import.meta.glob<{ default: any }>('./locales/*.json', { eager: true }))
    .map(([key, value]) => [key.slice(10, -5), value.default])
    .filter(([locale]) => supportedLocales.includes(locale as SupportedLocale)),
)

let _i18n: any = null

export const getI18n = () => {
  if (_i18n === null) {
    const storedLocale = cookieRef<string>('language', themeConfig.app.i18n.defaultLocale).value
    _i18n = createI18n({
      legacy: false,
      locale: supportedLocales.includes(storedLocale as SupportedLocale) ? storedLocale : 'en',
      fallbackLocale: 'en',
      messages,
      escapeParameter: true,
      missingWarn: import.meta.env.DEV,
      fallbackWarn: import.meta.env.DEV,
      missing: (_locale, key) => import.meta.env.DEV ? `[missing:${key}]` : '',
      datetimeFormats: {
        en: {
          short: { year: 'numeric', month: 'short', day: 'numeric' },
          long: { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' },
          time: { hour: 'numeric', minute: '2-digit' },
        },
        'fa-AF': {
          short: { year: 'numeric', month: 'short', day: 'numeric' },
          long: { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' },
          time: { hour: 'numeric', minute: '2-digit' },
        },
        'ps-AF': {
          short: { year: 'numeric', month: 'short', day: 'numeric' },
          long: { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' },
          time: { hour: 'numeric', minute: '2-digit' },
        },
      },
      numberFormats: Object.fromEntries(supportedLocales.map(locale => [locale, {
        decimal: { style: 'decimal', maximumFractionDigits: 2 },
        percent: { style: 'percent', maximumFractionDigits: 1 },
      }])),
    })
  }

  return _i18n
}

export default function (app: App) {
  app.use(getI18n())
}
