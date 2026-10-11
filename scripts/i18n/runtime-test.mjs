import fs from 'node:fs'
import path from 'node:path'
import { createI18n } from 'vue-i18n'
import { computed } from 'vue'
import ts from 'typescript'
import { dynamicTranslationKeys, sourceFiles, translationKeys } from './source-keys.mjs'

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
for (const catalog of Object.values(messages)) {
  if (catalog.legacyPages) {
    catalog.pages = { ...catalog.pages, ...catalog.legacyPages }
    delete catalog.legacyPages
  }
}

const flatten = (object, prefix = '') => Object.entries(object).flatMap(([key, value]) => {
  const next = prefix ? `${prefix}.${key}` : key

  return value && typeof value === 'object' && !Array.isArray(value) ? flatten(value, next) : [[next, String(value)]]
})

const flattened = Object.fromEntries(supported.map(locale => [locale, Object.fromEntries(flatten(messages[locale]))]))
const englishKeys = Object.keys(flattened.en).sort()
const placeholders = message => [...new Set([...message.matchAll(/\{([A-Z_]\w*|\d+)\}/gi)].map(match => match[1]))].sort()
const used = dynamicTranslationKeys(root)
for (const file of sourceFiles(root))
  for (const key of translationKeys(fs.readFileSync(file, 'utf8'))) used.add(key)
for (const key of used) {
  // Some literals configure a namespace (e.g. backend.values), not a message.
  const source = key.split('.').reduce((value, part) => value?.[part], messages.en)
  if (source && typeof source === 'object') continue
  if (!(key in flattened.en)) throw new Error(`Used translation ${key} has no English source message.`)
  if (!(key in flattened['fa-AF'])) throw new Error(`Used translation ${key} has no Dari message.`)
}

// Namespaced helpers must be expanded, not mistaken for global keys.
const helperKeys = translationKeys('const tr = (key: string) => t(`pages.example.${key}`); tr("common.label");')
if (!helperKeys.has('pages.example.common.label') || helperKeys.has('common.label'))
  throw new Error('Namespaced translation helper auditing failed.')

for (const locale of supported.slice(1)) {
  const keys = Object.keys(flattened[locale]).sort()

  // Pashto may intentionally fall back to the complete Dari catalog. Check
  // placeholder safety for translations it owns; resolved coverage is checked
  // below through vue-i18n's actual fallback chain.
  for (const key of keys) {
    const englishMessage = flattened.en[key]
    if (englishMessage === undefined)
      throw new Error(`${locale}:${key} has no English source message.`)
    if (JSON.stringify(placeholders(flattened[locale][key])) !== JSON.stringify(placeholders(englishMessage)))
      throw new Error(`${locale}:${key} does not preserve interpolation placeholders.`)
  }
}

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: { 'en': [], 'fa-AF': ['en'], 'ps-AF': ['fa-AF', 'en'] },
  missingWarn: false,
  fallbackWarn: false,
  messages,
})

// Exercise the real frontend utilities with the test i18n instance. The plugin
// itself depends on Vite's import.meta.glob and cannot run directly in Node.
const moduleUrl = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
globalThis.__translationTestI18n = i18n
const pluginUrl = moduleUrl(`export const getI18n = () => globalThis.__translationTestI18n;
  export const supportedLocales = ['en', 'fa-AF', 'ps-AF'];`)
const utilityUrl = (file, replacements) => {
  let source = fs.readFileSync(path.join(root, file), 'utf8')
  for (const [from, to] of Object.entries(replacements)) source = source.replaceAll(from, to)

  return moduleUrl(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText)
}
const localizationUrl = utilityUrl('resources/ts/utils/localization.ts', { '@/plugins/i18n': pluginUrl })
const { translateApplicantStage, translateBackendValue, translateContractPolicyType, translateOrFallback } = await import(localizationUrl)
const { localizePlaceholderGroups, serializePlaceholders, hydratePlaceholders } = await import(utilityUrl(
  'resources/ts/utils/contractPlaceholders.ts', { '@/utils/localization': localizationUrl },
))
const groups = [{ key: 'service', label: 'Service', fields: [{ key: 'service.fee', label: 'Fee' }] }]
const localizedGroups = computed(() => localizePlaceholderGroups(groups))

for (const locale of supported) {
  i18n.global.locale.value = locale
  if (i18n.global.locale.value !== locale)
    throw new Error(`Runtime locale switching failed for ${locale}.`)
  for (const key of englishKeys) {
    const parameters = placeholders(flattened.en[key])
    const values = parameters.length && parameters.every(value => /^\d+$/.test(value))
      ? Array.from({ length: Math.max(...parameters.map(Number)) + 1 }, () => 'Example')
      : Object.fromEntries(parameters.map(value => [value, 'Example']))
    const translated = i18n.global.t(key, values)
    if (translated === key || (translated === '' && flattened.en[key] !== ''))
      throw new Error(`${locale}:${key} cannot be resolved through the fallback chain.`)
  }
  if (translateOrFallback('common.nonexistentExample', 'Custom label') !== 'Custom label'
    || translateBackendValue('custom-status') !== 'custom-status'
    || translateContractPolicyType('custom-type', 'Custom agreement') !== 'Custom agreement')
    throw new Error(`${locale}: unknown backend values must retain their readable fallback.`)
  if (translateBackendValue('closed') !== i18n.global.t('common.statuses.closed'))
    throw new Error(`${locale}: applicant status did not translate.`)
  if (translateApplicantStage({ key: 'consultation', name: 'Consultation' }) !== i18n.global.t('educational.stageNames.consultation')
    || translateApplicantStage({ key: 'consultation', name: 'Custom stage name' }) !== 'Custom stage name')
    throw new Error(`${locale}: stage localization must preserve customized names.`)
  const group = localizedGroups.value[0]
  if (group.label !== i18n.global.t('pages.settings.policies.policies.contractTemplate.placeholders.service.label')
    || group.fields[0].label !== i18n.global.t('pages.settings.policies.policies.contractTemplate.placeholders.service.fields.fee'))
    throw new Error(`${locale}: placeholder labels did not react to language switching.`)
  const html = '<p>{{ service.fee }} {{ custom.unknown }}</p>'
  if (serializePlaceholders(hydratePlaceholders(html, localizedGroups.value, new Set(['service.fee']))) !== html)
    throw new Error(`${locale}: localization changed serialized document placeholders.`)
}
delete globalThis.__translationTestI18n

console.log(`Runtime switching and schema checks passed for ${supported.join(', ')} (${englishKeys.length} keys).`)
