import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'
import ts from 'typescript'

/**
 * Concept navigation regression.
 *
 * The global navigation must show one link per concept (Contracts, CRM,
 * Applications) instead of a dropdown group, and every page of a concept must
 * offer the same in-page navigation, where each destination carries its own icon
 * and its own colour. This script loads the real navigation modules and the real
 * header component and guards:
 *
 *  1. the nav barrel renders exactly one global link per concept, its default
 *     page is a real route, and the other concepts keep their grouped menus,
 *  2. no concept page is reachable both globally and as a child of a group,
 *  3. every in-page destination declares its own icon and its own colour, and
 *     the Applications strip holds exactly Applications / Search / Visa reports,
 *  4. all four Applications pages render the shared header with that section,
 *  5. every referenced icon is in the bundled icon set and every label key
 *     exists in the three locales (en, fa-AF, ps-AF).
 */

const root = fileURLToPath(new URL('..', import.meta.url))
const read = file => readFileSync(join(root, file), 'utf8')

const navDir = 'resources/ts/navigation/horizontal/'
const barrel = read(navDir + 'index.ts')
const barrelModules = [...barrel.matchAll(/from '\.\/([\w-]+)'/g)].map(match => match[1])

/** Import a navigation module for real: it has no runtime imports of its own. */
const loadNavModule = async module => {
  const source = readFileSync(join(root, `${navDir}${module}.ts`), 'utf8')
  const compiled = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext },
  }).outputText
  const imported = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`)

  assert.ok(Array.isArray(imported.default), `${module}.ts must default-export a navigation array`)

  return imported.default
}

// --- 1. one global link per concept -------------------------------------------

assert.ok(barrelModules.length >= 8, `the nav barrel must import the nav modules: ${barrelModules.join(', ')}`)

const navItems = Object.fromEntries(await Promise.all(
  barrelModules.map(async module => [module, await loadNavModule(module)]),
))

const conceptGlobals = { contracts: 'scholarship-contracts', applications: 'applications', crm: 'visitor' }

for (const [concept, destination] of Object.entries(conceptGlobals)) {
  assert.ok(barrelModules.includes(concept), `the nav barrel must include ${concept}`)
  assert.equal(navItems[concept].length, 1, `${concept} must contribute exactly one global link`)

  const [item] = navItems[concept]

  assert.ok(!('children' in item), `${concept} must be a single link, not a dropdown group`)
  assert.equal(item.to, destination, `${concept} must link to its default page (${destination})`)
}

assert.equal(navItems.contracts[0].title, 'common.navigation.contract', 'the Contracts link must keep its concept label')
assert.equal(navItems.crm[0].title, 'common.navigation.crm', 'the CRM link must keep its concept label')
assert.equal(navItems.applications[0].title, 'common.navigation.applications', 'the Applications link must keep its concept label')

// The other concepts keep their grouped navigation (guards against over-removal).
for (const grouped of ['finance', 'hr', 'settings', 'setup']) {
  assert.ok(barrelModules.includes(grouped), `the nav barrel must still include ${grouped}`)
  assert.ok(navItems[grouped].every(item => item.children?.length), `${grouped} must keep its grouped navigation`)
}

const routeNames = new Set([...read('resources/ts/plugins/1.router/routes.ts').matchAll(/name: '([\w-]+)'/g)].map(match => match[1]))
for (const destination of Object.values(conceptGlobals))
  assert.ok(routeNames.has(destination), `the concept default page (${destination}) must be a real route`)

// --- 2. no concept page is reachable twice ------------------------------------

// Only the barrel's modules are rendered, so a dead snapshot such as `visitor.ts`
// must not sneak a second Visitors link into the navigation.
const rendered = barrelModules.flatMap(module => navItems[module])
const collect = node => (Array.isArray(node)
  ? node.flatMap(collect)
  : [{ to: node.to ?? null }, ...(node.children ? collect(node.children) : [])])
const links = rendered.flatMap(collect)

// Each concept contributes exactly one global link (its default page); every
// other page of the concept is reached through the in-page navigation only.
const globalLinkCounts = {
  'applications': 1,
  'visitor': 1,
  'scholarship-contracts': 1,
  'search': 0,
  'visa-reports': 0,
  'all-customers': 0,
}

for (const [destination, expected] of Object.entries(globalLinkCounts)) {
  const occurrences = links.filter(link => link.to === destination).length

  assert.equal(occurrences, expected, `the global navigation must offer exactly ${expected} link(s) to ${destination}, found ${occurrences}`)
}

// "For each concept only one link appears" holds across the whole menu, not just
// the three concepts above: no page may be listed twice.
const destinationCounts = links.reduce((counts, link) => link.to
  ? { ...counts, [link.to]: (counts[link.to] ?? 0) + 1 }
  : counts, {})
const duplicated = Object.entries(destinationCounts).filter(([, count]) => count > 1)

assert.deepEqual(duplicated, [], `no page may appear twice in the global navigation: ${JSON.stringify(duplicated)}`)

// --- 3. every in-page destination has its own icon and colour -----------------

const headerFile = 'resources/ts/components/erp/ErpPageHeader.vue'
const header = read(headerFile)
const sectionsBlock = header.slice(header.indexOf('const sections'), header.indexOf('const links = computed'))

for (const section of ['contracts', 'crm', 'applications', 'dashboard'])
  assert.ok(sectionsBlock.includes(`${section}: [`), `ErpPageHeader must define the ${section} section`)

const destinationLines = sectionsBlock.split('\n').filter(line => line.includes('name:'))

assert.ok(destinationLines.length >= 11, `expected at least 11 in-page destinations, found ${destinationLines.length}`)

const headerIcons = []
const headerLabels = []
const headerNames = []
for (const line of destinationLines) {
  const icon = line.match(/icon: '(tabler-[\w-]+)'/)
  const color = line.match(/color: '(primary|secondary|tertiary|success|info|warning|error)'/)
  const label = line.match(/label: '([\w.]+)'/)
  const name = line.match(/name: '([\w-]+)'/)

  assert.ok(icon, `every in-page destination needs its own icon: ${line.trim()}`)
  assert.ok(color, `every in-page destination needs its own colour: ${line.trim()}`)
  assert.ok(label, `every in-page destination needs a locale label: ${line.trim()}`)
  headerIcons.push(icon[1])
  headerLabels.push(label[1])
  headerNames.push(name?.[1])
}

// Every in-page destination must be a real route, otherwise the strip renders a
// button that cannot navigate anywhere.
for (const name of headerNames)
  assert.ok(routeNames.has(name), `the in-page destination ${name} must be a real route`)

// The Applications concept is exactly its three pages, in order.
const applicationsBlock = sectionsBlock.slice(sectionsBlock.indexOf('applications: ['), sectionsBlock.indexOf('dashboard: ['))
assert.deepEqual(
  [...applicationsBlock.matchAll(/name: '([\w-]+)'/g)].map(match => match[1]),
  ['applications', 'search', 'visa-reports'],
  'the Applications in-page navigation must interconnect its three pages',
)

// A strip may not reuse one colour for two destinations, otherwise the "own
// colour" requirement silently regresses.
for (const section of ['contracts', 'crm', 'applications']) {
  const block = sectionsBlock.slice(sectionsBlock.indexOf(`${section}: [`))
  const lines = block.slice(0, block.indexOf('\n  ]')).split('\n').filter(line => line.includes('name:'))
  const colors = lines.map(line => line.match(/color: '(\w+)'/)[1])

  assert.equal(new Set(colors).size, colors.length, `the ${section} strip must not repeat a colour: ${colors.join(', ')}`)
}

// --- 4. every Applications page renders the shared concept header -------------

for (const page of ['applicants', 'search', 'educational-reports', 'applicant-pipeline']) {
  const source = read(`resources/ts/pages/${page}.vue`)

  assert.ok(source.includes('ErpPageHeader'), `${page}.vue must render ErpPageHeader`)
  assert.ok(source.includes('section="applications"'), `${page}.vue must use the applications section`)
}

// Breadcrumbs are part of the design these pages now share.
assert.ok(!read('resources/ts/pages/applicants.vue').includes(':breadcrumbs="false"'), 'the applications page must show breadcrumbs')

// --- 5. bundled icons and locale labels ---------------------------------------

const iconsCss = read('resources/ts/plugins/iconify/icons.css')
const navIcons = rendered.flatMap(item => (item.icon?.icon ? [item.icon.icon] : []))

for (const icon of [...headerIcons, ...navIcons])
  assert.ok(iconsCss.includes(`.${icon}`), `the bundled icon set must include ${icon}: run npm run build:icons`)

const locales = Object.fromEntries(['en', 'fa-AF', 'ps-AF'].map(locale => [
  locale,
  JSON.parse(read(`resources/ts/plugins/i18n/locales/${locale}.json`)),
]))
const lookup = (messages, key) => key.split('.').reduce((node, part) => node?.[part], messages)

for (const locale of Object.keys(locales)) {
  assert.ok(lookup(locales[locale], 'erp.applications'), `${locale} must translate the Applications breadcrumb (erp.applications)`)
  for (const key of headerLabels)
    assert.ok(lookup(locales[locale], key), `${locale} is missing the locale key ${key}`)
}

console.log(`concept-navigation-regression: ok (${barrelModules.length} nav modules, ${Object.keys(conceptGlobals).length} single-link concepts, ${destinationLines.length} in-page destinations each with their own icon and colour, applications strip = applications/search/visa-reports, all icons bundled and labelled in 3 locales)`)
