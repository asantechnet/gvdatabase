import fs from 'node:fs'
import path from 'node:path'
import { en as vuetifyEnglish } from 'vuetify/locale'

export const sourceRoots = ['pages', 'components', 'layouts', 'navigation', 'api', 'utils', 'composables']
  .map(directory => `resources/ts/${directory}`)

const walk = directory => fs.existsSync(directory)
  ? fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => entry.isDirectory()
    ? walk(path.join(directory, entry.name))
    : [path.join(directory, entry.name)])
  : []

export const sourceFiles = root => sourceRoots.flatMap(directory => walk(path.join(root, directory)))
  .filter(file => /\.(vue|ts)$/.test(file))

export const translationKeys = contents => {
  const used = new Set()
  const helpers = new Map([...contents.matchAll(/const\s+(\w+)\s*=\s*\(key[^\n]*\)\s*=>\s*(?:t|translate)\(`([^`]*?)\$\{key\}`/g)]
    .map(match => [match[1], match[2]]))
  const names = [...new Set(['\\$t', 't', 'translate', ...helpers.keys()])].join('|')
  const translatedArguments = new Set()

  for (const match of contents.matchAll(new RegExp(`(?<![\\w$])(${names})\\(\\s*(['"])([^'"]+)\\2`, 'g'))) {
    used.add(`${helpers.get(match[1]) ?? ''}${match[3]}`)
    translatedArguments.add(match.index + match[0].indexOf(match[2]))
  }
  // Keys in table definitions, navigation and field maps are translated later.
  for (const match of contents.matchAll(/['"]((?:common|pages|components|documents|backend|notifications|educational|erp|agencyStaff|serviceContracts)\.[\w.-]+)['"]/g)) {
    if (!match[1].endsWith('.') && !translatedArguments.has(match.index)) used.add(match[1])
  }

  return used
}

export const dynamicTranslationKeys = root => {
  const used = new Set()
  const flatten = (object, prefix) => {
    for (const [key, value] of Object.entries(object)) {
      const next = `${prefix}.${key}`
      if (value && typeof value === 'object') flatten(value, next)
      else used.add(next)
    }
  }
  // Vuetify resolves these internally rather than through application t() calls.
  flatten(vuetifyEnglish, '$vuetify')
  const stageMigration = fs.readFileSync(path.join(root, 'database/migrations/0001_06_01_000006_create_reference_tables.php'), 'utf8')
  const stagePath = stageMigration.match(/\$happyPath = \[([\s\S]*?)\];/)?.[1] ?? ''
  for (const match of stagePath.matchAll(/'([^']+)' =>/g)) used.add(`educational.stageNames.${match[1]}`)
  for (const key of ['completed', 'cancelled', 'rejected', 'withdrawn']) used.add(`educational.stageNames.${key}`)
  const registry = fs.readFileSync(path.join(root, 'app/Services/Contracts/PlaceholderRegistry.php'), 'utf8')
    .split('public static function groupsForSlug')[0]
  const base = 'pages.settings.policies.policies.contractTemplate.placeholders'
  for (const match of registry.matchAll(/'key' => '(\w+)',\s*'label' => '[^']+',([\s\S]*?)(?=\n            \],)/g)) {
    used.add(`${base}.${match[1]}.label`)
    for (const field of match[2].matchAll(/\['key' => '([^']+)', 'label' => '[^']+'\]/g))
      used.add(`${base}.${match[1]}.fields.${field[1].split('.').slice(1).join('.')}`)
  }

  return used
}
