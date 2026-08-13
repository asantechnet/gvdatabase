import fs from 'node:fs'
import path from 'node:path'
import { createI18n } from 'vue-i18n'

const root = process.cwd()
const localeDirectory = path.join(root, 'resources/ts/plugins/i18n/locales')
const supported = ['en', 'fa-AF', 'ps-AF']
const files = fs.readdirSync(localeDirectory).filter(file => file.endsWith('.json')).sort()

if (JSON.stringify(files) !== JSON.stringify(supported.map(locale => `${locale}.json`).sort()))
  throw new Error(`Locale directory must contain only ${supported.join(', ')}.`)

const messages = Object.fromEntries(supported.map(locale => [
  locale,
  JSON.parse(fs.readFileSync(path.join(localeDirectory, `${locale}.json`), 'utf8')),
]))

const flatten = (object, prefix = '') => Object.entries(object).flatMap(([key, value]) => {
  const next = prefix ? `${prefix}.${key}` : key
  return value && typeof value === 'object' && !Array.isArray(value) ? flatten(value, next) : [[next, String(value)]]
})
const flattened = Object.fromEntries(supported.map(locale => [locale, Object.fromEntries(flatten(messages[locale]))]))
const englishKeys = Object.keys(flattened.en).sort()
const placeholders = message => [...message.matchAll(/\{([^}]+)\}/g)].map(match => match[1]).sort()

for (const locale of supported.slice(1)) {
  const keys = Object.keys(flattened[locale]).sort()
  if (JSON.stringify(keys) !== JSON.stringify(englishKeys)) throw new Error(`${locale} does not match the English locale schema.`)
  for (const key of englishKeys) {
    if (JSON.stringify(placeholders(flattened[locale][key])) !== JSON.stringify(placeholders(flattened.en[key])))
      throw new Error(`${locale}:${key} does not preserve interpolation placeholders.`)
  }
}

const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages })
for (const locale of supported) {
  i18n.global.locale.value = locale
  if (i18n.global.locale.value !== locale) throw new Error(`Runtime locale switching failed for ${locale}.`)
  if (!i18n.global.t('common.actions.save')) throw new Error(`Runtime lookup failed for ${locale}.`)
}

console.log(`Runtime switching and schema checks passed for ${supported.join(', ')} (${englishKeys.length} keys).`)
