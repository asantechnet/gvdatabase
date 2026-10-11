import fs from 'node:fs'
import path from 'node:path'
import { parse } from '@vue/compiler-sfc'
import { sourceFiles, translationKeys, dynamicTranslationKeys } from './source-keys.mjs'

const root = process.cwd()

const files = sourceFiles(root)

const locales = Object.fromEntries(['en', 'fa-AF', 'ps-AF'].map(locale => {
  const messages = JSON.parse(fs.readFileSync(path.join(root, `resources/ts/plugins/i18n/locales/${locale}.json`), 'utf8'))
  if (messages.legacyPages) {
    messages.pages = { ...messages.pages, ...messages.legacyPages }
    delete messages.legacyPages
  }

  return [locale, messages]
}))

const flatten = (object, prefix = '') => Object.entries(object).flatMap(([key, value]) => {
  const next = prefix ? `${prefix}.${key}` : key

  return value && typeof value === 'object' && !Array.isArray(value) ? flatten(value, next) : [[next, value]]
})

const localeKeys = Object.fromEntries(Object.entries(locales).map(([locale, messages]) => [locale, new Set(flatten(messages).map(([key]) => key))]))
const used = dynamicTranslationKeys(root)
const raw = []
const dateIssues = []
const physicalCss = []
const translatedSymbols = []

const lineOf = (contents, index) => contents.slice(0, index).split(/\r?\n/).length
const properNames = new Set(['WhatsApp', 'Telegram', 'Vazirmatn', 'Estedad', 'Sahel', 'Samim', 'Shabnam', 'Arial', 'Tahoma', 'Times New Roman', 'Courier New', 'SSL', 'smtp.example.com'])
const englishGuideFiles = new Set(['usePageGuide.ts', 'useAccountSettingsGuide.ts'])
const record = (collection, file, contents, match, text) => {
  // Font/provider names and example hostnames are intentionally literal. These
  // two guides select English copy only in English and localized copy otherwise.
  if (collection === raw && (properNames.has(text.trim()) || englishGuideFiles.has(path.basename(file)))) return
  collection.push(`${path.relative(root, file)}:${lineOf(contents, match.index)} ${text.trim().replace(/\s+/g, ' ').slice(0, 160)}`)
}

for (const file of files) {
  const contents = fs.readFileSync(file, 'utf8')
  for (const key of translationKeys(contents)) used.add(key)
  for (const match of contents.matchAll(/\$t\(['"][^'"]*\.n(?:8984K|copy|arrow|bullet)['"]\)/gi)) record(translatedSymbols, file, contents, match, match[0])
  if (file.endsWith('.vue')) {
    const template = contents.match(/<template\b[^>]*>([\s\S]*)<\/template>/)?.[1] ?? ''
    for (const match of template.matchAll(/\s(?:label|placeholder|title|hint|aria-label|text|empty-text|no-data-text)="([^"<>A-Za-z]*[A-Za-z][^"<>]*)"/g)) {
      if (!/^[^\s@]+@[^\s@][^\s.@]*\.[^\s@]+$/.test(match[1]) && !/^(?:https?:\/\/|www\.)/i.test(match[1]) && !/^[A-Z0-9._/-]+$/.test(match[1]))
        record(raw, file, contents, match, match[1])
    }

    const { descriptor } = parse(contents)
    const inspectText = node => {
      if (node.type === 2 && /[A-Z]{2}/i.test(node.content))
        record(raw, file, contents, { index: (descriptor.template?.loc.start.offset ?? 0) + node.loc.start.offset }, node.content)
      for (const child of node.children ?? []) inspectText(child)
    }
    if (descriptor.template?.ast) inspectText(descriptor.template.ast)
  }
  for (const match of contents.matchAll(/\b(?:title|label|placeholder|hint|message|subtitle|heading)\s*:\s*['"]([^"'A-Za-z]*[A-Za-z][^"']*)['"]/g)) {
    if (!/^(?:common|pages|components|documents|backend|notifications|educational|erp|agencyStaff|serviceContracts)\./.test(match[1])
      && !/^\s+in\b/.test(contents.slice(match.index + match[0].length)))
      record(raw, file, contents, match, match[1])
  }

  for (const match of contents.matchAll(/(?:toast\.(?:success|error|info|warning)|alert|confirm)\(\s*['"]([^"'A-Za-z]*[A-Za-z][^"']*)['"]/g)) record(raw, file, contents, match, match[1])
  for (const match of contents.matchAll(/\.value\s*=\s*['"]((?:Failed|Unable|Please|Select|Enter|No |Invalid|Unknown)[^'"]*)['"]/g)) record(raw, file, contents, match, match[1])
  for (const match of contents.matchAll(/\.toLocale(?:Date|Time)?String\s*\(/g)) record(dateIssues, file, contents, match, match[0])
  if (file.endsWith('.vue'))
    for (const match of contents.matchAll(/\b(?:margin|padding|border)-(?:left|right)(?:-[\w-]+)?\s*:/g)) record(physicalCss, file, contents, match, match[0])
}

const hasPath = (object, key) => key.split('.').reduce((cursor, part) => cursor?.[part], object) !== undefined
const missing = Object.fromEntries(Object.entries(locales).map(([locale, messages]) => [locale, [...used].filter(key => !hasPath(messages, key) && !(locale === 'ps-AF' && hasPath(locales['fa-AF'], key)))]))
const unused = [...localeKeys.en].filter(key => !used.has(key) && !key.startsWith('$vuetify.'))

console.log(`i18n audit: ${files.length} source files, ${used.size} used keys, ${localeKeys.en.size} English keys.`)
for (const [locale, keys] of Object.entries(missing)) {
  if (keys.length)
    console.log(`\nMissing in ${locale} (${keys.length}):\n${keys.join('\n')}`)
}
if (raw.length)
  console.log(`\nLikely untranslated UI strings (${raw.length}):\n${raw.join('\n')}`)
if (dateIssues.length)
  console.log(`\nDirect locale date formatting (${dateIssues.length}):\n${dateIssues.join('\n')}`)
if (physicalCss.length)
  console.log(`\nPhysical-direction CSS (${physicalCss.length}):\n${physicalCss.join('\n')}`)
if (translatedSymbols.length)
  console.log(`\nSymbols incorrectly routed through i18n (${translatedSymbols.length}):\n${translatedSymbols.join('\n')}`)
if (unused.length)
  console.log(`\nUnused English keys (${unused.length}). Run with I18N_SHOW_UNUSED=1 to list them.`)
if (unused.length && process.env.I18N_SHOW_UNUSED === '1')
  console.log(unused.join('\n'))
if (Object.values(missing).some(keys => keys.length) || raw.length || dateIssues.length || physicalCss.length || translatedSymbols.length)
  process.exitCode = 1
