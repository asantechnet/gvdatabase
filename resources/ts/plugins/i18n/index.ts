import type { App } from 'vue'
import { createI18n } from 'vue-i18n'
import { cookieRef } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

export const supportedLocales = ['en', 'fa-AF', 'ps-AF'] as const
export type SupportedLocale = typeof supportedLocales[number]

/**
 * `en` is both the default locale and the universal fallback for every other
 * locale, so it has to be available synchronously. Keeping it in the main bundle
 * and splitting the rest into their own chunks removes ~576 KB of message data
 * from the initial payload — every visitor used to download and compile all
 * three locales before the first paint.
 */
const eagerLocaleModules = import.meta.glob<{ default: any }>('./locales/en.json', {
  eager: true,
})

const lazyLocaleLoaders = import.meta.glob<{ default: any }>(
  ['./locales/*.json', '!./locales/en.json'],
)

const defaultLocaleModule = eagerLocaleModules['./locales/en.json']

// Dari is a deliberate fallback for Pashto while newer Pashto copy is being
// completed. It prevents raw English source text and missing-key markers from
// reaching end users.
const fallbackLocale: Record<SupportedLocale, SupportedLocale[]> = {
  'en': [],
  'fa-AF': ['en'],
  'ps-AF': ['fa-AF', 'en'],
}

const isSupportedLocale = (value: unknown): value is SupportedLocale =>
  typeof value === 'string' && (supportedLocales as readonly string[]).includes(value)

export const getInitialLocale = (): SupportedLocale => {
  const storedLocale = cookieRef<string>('language', themeConfig.app.i18n.defaultLocale).value

  return isSupportedLocale(storedLocale) ? storedLocale : 'en'
}

/**
 * Keep compatibility with locale files that historically contained a second
 * top-level pages object. JSON parsing otherwise overwrites the main pages
 * namespace and makes settings/contracts appear missing.
 */
const normalizeMessages = (localeMessages: any) => {
  if (localeMessages.legacyPages) {
    localeMessages.pages = { ...localeMessages.pages, ...localeMessages.legacyPages }
    delete localeMessages.legacyPages
  }

  return localeMessages
}

const loadedLocales = new Set<SupportedLocale>(['en'])

let _i18n: any = null

export const getI18n = () => {
  if (_i18n === null) {
    _i18n = createI18n({
      legacy: false,
      locale: getInitialLocale(),
      fallbackLocale,
      messages: {
        en: normalizeMessages(defaultLocaleModule.default),
      },
      escapeParameter: true,
      missingWarn: import.meta.env.DEV,
      fallbackWarn: import.meta.env.DEV,
      missing: (_locale, key) => import.meta.env.DEV ? `[missing:${key}]` : '',
      datetimeFormats: {
        'en': {
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

/**
 * Loads a locale together with its fallback chain into the active i18n instance.
 * Already-loaded locales are skipped, so this is safe to call on every startup
 * and on every language switch.
 *
 * Accepts a raw string (locale codes reach here from cookies and `langConfig`)
 * and returns `false` for an unknown locale or a chunk that could not be
 * fetched, so callers can keep the previous locale instead of rendering an
 * untranslated view.
 */
export const loadLocale = async (locale: string): Promise<boolean> => {
  if (!isSupportedLocale(locale))
    return false

  const chain = [locale, ...fallbackLocale[locale]]
  const i18n = getI18n()

  const pending = chain.filter(target => !loadedLocales.has(target))

  await Promise.all(pending.map(async target => {
    const loader = lazyLocaleLoaders[`./locales/${target}.json`]

    if (!loader)
      return

    const localeModule = await loader()

    i18n.global.setLocaleMessage(target, normalizeMessages(localeModule.default))
    loadedLocales.add(target)
  }))

  return chain.every(target => loadedLocales.has(target))
}

export default function (app: App) {
  app.use(getI18n())
}
