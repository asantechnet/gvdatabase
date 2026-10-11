/**
 * Generates `resources/ts/plugins/iconify/icons.css`.
 *
 * The stylesheet used to bundle every icon of every configured Iconify set
 * (~6,000 tabler glyphs), which made it by far the largest asset on the
 * critical path. It is now pruned to the icons this repository references:
 *
 *   1. Walk the source tree and collect every `prefix-name` icon token.
 *   2. Merge in `resources/ts/plugins/iconify/icon-allowlist.json` for names
 *      that cannot be discovered statically.
 *   3. Emit CSS only for icons that exist in the configured sets.
 *
 * Tokens that are referenced but unavailable in every configured set are
 * reported as warnings — an unlisted name renders as a blank glyph.
 *
 * This file is the class-based icon set: what `plugins/vuetify/icons.ts`
 * renders as `<i class="tabler-home">`. Icons loaded through the remote
 * `@iconify/vue` `<Icon>` component are fetched at runtime and never reach
 * this file.
 *
 * @see AGENTS.md "Icons" for the authoring rules.
 */
import fs from 'node:fs'
import { createRequire } from 'node:module'
import path from 'node:path'
import { getIconsCSS } from '@iconify/utils'

const require = createRequire(import.meta.url)

const root = process.cwd()

const target = path.join(root, 'resources/ts/plugins/iconify/icons.css')
const outputIndex = process.argv.indexOf('--output')
const output = outputIndex < 0 ? target : path.resolve(process.argv[outputIndex + 1])
const allowlistPath = path.join(root, 'resources/ts/plugins/iconify/icon-allowlist.json')

/** Icon sets bundled locally as CSS classes. */
const sets = [
  { prefix: 'tabler', filename: require.resolve('@iconify-json/tabler/icons.json') },
  { prefix: 'mdi', filename: require.resolve('@iconify-json/mdi/icons.json') },
  { prefix: 'fa', filename: require.resolve('@iconify-json/fa/icons.json') },
]

const prefixes = sets.map(set => set.prefix)

/** Where to look for icon references. */
const scanDirectories = [
  'resources/ts',
  'resources/views',
  'app',
  'lang',
  'routes',
  'database',
  'config',
]

const scanFiles = ['themeConfig.ts']

const scanExtensions = new Set([
  '.ts', '.tsx', '.js', '.mjs', '.cjs', '.vue', '.php', '.json', '.scss', '.css',
])

const ignoredSegments = [
  `${path.sep}node_modules${path.sep}`,
  `${path.sep}vendor${path.sep}`,
  `${path.sep}.git${path.sep}`,
  `${path.sep}storage${path.sep}`,
  `${path.sep}public${path.sep}build${path.sep}`,
]

// Never scan our own inputs/outputs, otherwise the previously generated file
// feeds back in and every icon that was ever bundled stays bundled forever.
const ignoredFiles = new Set([target, allowlistPath])

const iconToken = new RegExp(`\\b((?:${prefixes.join('|')})-[a-z0-9]+(?:-[a-z0-9]+)*)`, 'g')

/**
 * `include` forces an icon into the bundle (for dynamic names that are still
 * rendered as class-based icons).
 *
 * `ignore` skips an icon entirely and silences its warning. Use it for known
 * false positives — such as the `tabler-align` fragment of a template literal —
 * and for names rendered by another mechanism, like the `mdi-*` names mapped to
 * imported SVGs in `plugins/vuetify/icons.ts`.
 */
const readAllowlist = () => {
  try {
    const parsed = JSON.parse(fs.readFileSync(allowlistPath, 'utf8'))

    return {
      include: parsed.include ?? [],
      ignore: parsed.ignore ?? [],
    }
  }
  catch {
    return { include: [], ignore: [] }
  }
}

const collectSourceFiles = (directory, collected = []) => {
  if (!fs.existsSync(directory))
    return collected

  const stat = fs.statSync(directory)

  if (stat.isFile()) {
    if (!ignoredFiles.has(directory))
      collected.push(directory)

    return collected
  }

  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const fullPath = path.join(directory, entry.name)

    if (ignoredSegments.some(segment => fullPath.includes(segment)) || ignoredFiles.has(fullPath))
      continue

    if (entry.isDirectory())
      collectSourceFiles(fullPath, collected)
    else if (scanExtensions.has(path.extname(entry.name)))
      collected.push(fullPath)
  }

  return collected
}

const findIconTokens = (content, owners, file) => {
  for (const match of content.matchAll(iconToken)) {
    const token = match[1]
    const files = owners.get(token) ?? new Set()

    files.add(path.relative(root, file))
    owners.set(token, files)
  }
}

/** tabler ships 2px strokes; this project renders them at 1.5px. */
const applyTablerStrokeWidth = content => {
  if (content.prefix !== 'tabler')
    return

  for (const name in content.icons) {
    const icon = content.icons[name]

    if (typeof icon.body === 'string')
      icon.body = icon.body.replace(/stroke-width="2"/g, 'stroke-width="1.5"')
  }
}

/**
 * Resolves an icon name to real icon data, following Iconify aliases. Aliases
 * carry no glyph of their own, so they must be resolved to their parent —
 * otherwise the icon silently renders blank. Transforms on the alias are merged
 * onto the parent so `getIconsCSS` can bake them into the SVG.
 */
const resolveIcon = (content, bare, seen = new Set()) => {
  if (seen.has(bare))
    return null

  seen.add(bare)

  const icon = content.icons?.[bare]

  if (icon)
    return { ...icon }

  const alias = content.aliases?.[bare]

  if (!alias?.parent)
    return null

  const base = resolveIcon(content, alias.parent, seen)

  if (!base)
    return null

  if (alias.rotate)
    base.rotate = (base.rotate ?? 0) + alias.rotate

  if (alias.hFlip)
    base.hFlip = !base.hFlip

  if (alias.vFlip)
    base.vFlip = !base.vFlip

  return base
}

const { include, ignore } = readAllowlist()

// ---- 1. Discover every icon the repository references ----
const files = [
  ...scanFiles.map(file => path.join(root, file)),
  ...scanDirectories.flatMap(directory => collectSourceFiles(path.join(root, directory))),
]

const owners = new Map()

for (const file of files) {
  try {
    findIconTokens(fs.readFileSync(file, 'utf8'), owners, file)
  }
  catch {
    // Unreadable or binary file — skip it.
  }
}

for (const name of include) {
  const ownerFiles = owners.get(name) ?? new Set()

  ownerFiles.add('icon-allowlist.json')
  owners.set(name, ownerFiles)
}

// Names handled by another mechanism (or known false positives) never reach the CSS.
const ignored = new Set(ignore)

for (const name of ignored)
  if (!include.includes(name))
    owners.delete(name)

console.log(`Scanned ${files.length} files, found ${owners.size} icon references.`)

// ---- 2. Filter each configured set down to the referenced icons ----
const blocks = []
const unavailable = []

for (const { prefix, filename } of sets) {
  const content = JSON.parse(fs.readFileSync(filename, 'utf8'))

  applyTablerStrokeWidth(content)

  const referenced = [...owners.keys()].filter(name => name.startsWith(`${prefix}-`))

  if (!referenced.length) {
    console.log(`  ${prefix}: no referenced icons, skipping.`)

    continue
  }

  const icons = {}

  for (const name of referenced) {
    const bare = name.slice(prefix.length + 1)
    const icon = resolveIcon(content, bare)

    if (icon)
      icons[bare] = icon
    else
      unavailable.push(name)
  }

  if (!Object.keys(icons).length) {
    console.log(`  ${prefix}: no matching icons, skipping.`)

    continue
  }

  const resolved = {
    prefix,
    width: content.width,
    height: content.height,
    icons,
  }

  blocks.push(getIconsCSS(resolved, Object.keys(icons), {
    iconSelector: '.{prefix}-{name}',
    mode: 'mask',
  }))

  console.log(`  ${prefix}: bundled ${Object.keys(icons).length} of ${Object.keys(content.icons).length + Object.keys(content.aliases ?? {}).length} icons/aliases.`)
}

// ---- 3. Write the stylesheet ----
const stylesheet = `${blocks.join('\n').trimEnd()}\n`
if (!fs.existsSync(output) || fs.readFileSync(output, 'utf8') !== stylesheet)
  fs.writeFileSync(output, stylesheet, 'utf8')

console.log(`Generated ${path.relative(root, output)}.`)

const unresolved = unavailable.filter(name => !ignored.has(name))

if (unresolved.length) {
  console.warn(`\n⚠ ${unresolved.length} icon(s) are referenced but unavailable in every configured set.`)
  console.warn('  They render as blank glyphs. Fix the name, add it to')
  console.warn(`  ${path.relative(root, allowlistPath)} ("include"), or use the remote`)
  console.warn('  `@iconify/vue` <Icon> component instead.\n')

  for (const name of unresolved)
    console.warn(`  - ${name}  <- ${[...(owners.get(name) ?? [])].slice(0, 3).join(', ')}`)
}
