import assert from 'node:assert/strict'
import { execSync, spawn } from 'node:child_process'
import { createRequire } from 'node:module'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Visitor / Follow-up list regression: mounts the real `pages/visitor.vue` in
 * Chrome with real Vuetify and asserts that one page serves two lists:
 *
 *  1. the CRM in-page navigation offers Visitors, Follow-up list and Applicants;
 *     the Follow-up destination deep-links with `?list=follow-up` and exactly one
 *     destination is marked current for the active mode,
 *  2. each list asks the API for its own side of the follow-up flag
 *     (`follow_up=0` for Visitors, `follow_up=1` for the Follow-up list),
 *  3. the Visitors list shows plain phone/e-mail with no quick contact, no
 *     relationship details, no create-contract action and no add button, but does
 *     offer the move-to-follow-up action,
 *  4. the Follow-up list restores quick contact, relationship details and
 *     create-contract, offers the reverse action, and has no add button,
 *  5. the page and printed report titles follow the active list,
 *  6. the move actions call the API and drop the row from the list on screen.
 *
 * It runs for `en` (LTR) and `fa-AF` (RTL), in light and dark themes.
 */

const root = fileURLToPath(new URL('..', import.meta.url))
const require = createRequire(`${root}package.json`)
const { parse, compileScript, compileStyle } = require('@vue/compiler-sfc')
const ts = require('typescript')

const output = process.env.VISITOR_FOLLOW_UP_OUTPUT ?? join(tmpdir(), 'educational-visitor-follow-up')

mkdirSync(output, { recursive: true })

const read = file => readFileSync(join(root, 'resources/ts', file), 'utf8')

// The page renders the real Vuetify components, so the harness must use the app's
// own theme: the in-page navigation paints each destination with a theme colour
// (including `tertiary`, which Vuetify's built-in light theme does not define).
const themeModule = { exports: {} }

new Function('exports', 'module', ts.transpileModule(read('plugins/vuetify/theme.ts'), {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText)(themeModule.exports, themeModule)

const appThemes = themeModule.exports.themes

assert.ok(appThemes?.light?.colors?.tertiary && appThemes?.dark?.colors?.tertiary, 'the app theme must define tertiary for both themes')

const chromeBinary = process.env.CHROME_BIN ?? ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser']
  .find(candidate => {
    try {
      execSync(`command -v ${candidate}`, { stdio: 'ignore' })

      return true
    }
    catch {
      return false
    }
  }) ?? 'google-chrome'

let scopedCss = ''
const componentFactories = []

/** Compile a real SFC (script + inlined template + scoped styles) into a factory. */
const compileComponent = (file, alias) => {
  const { descriptor, errors } = parse(read(file), { filename: file })

  assert.deepEqual(errors, [], `${file} must parse`)

  const scopeId = `data-v-${file.replace(/[^a-z0-9]/gi, '')}`

  const source = ts.transpileModule(
    compileScript(descriptor, { id: scopeId, inlineTemplate: true }).content,
    { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } },
  ).outputText

  for (const style of descriptor.styles) {
    const result = compileStyle({ source: style.content, id: scopeId, scoped: style.scoped })

    assert.deepEqual(result.errors, [])
    scopedCss += `\n${result.code}`
  }
  componentFactories.push(`factories[${JSON.stringify(alias)}] = (require, module, exports) => {${source}};`)
}

compileComponent('pages/visitor.vue', '@/pages/visitor.vue')
compileComponent('components/erp/ErpPageHeader.vue', '@/components/erp/ErpPageHeader.vue')

const buildHarness = (locale, mode) => {
  const messages = JSON.parse(read(`plugins/i18n/locales/${locale}.json`))
  const query = mode === 'follow-up' ? '{ list: \'follow-up\' }' : '{}'

  return `
const messages = ${JSON.stringify(messages)};
const t = key => key.split('.').reduce((node, part) => node?.[part], messages) ?? key;
const locale = ${JSON.stringify(locale)};
const query = ${query};
window.visitorRequests = [];
window.followUpRequests = [];
window.toastErrors = [];
window.toastSuccess = [];
window.bootError = null;
window.consoleErrors = [];
const nativeError = console.error.bind(console);
console.error = (...args) => { window.consoleErrors.push(args.map(String).join(' ')); nativeError(...args); };
const visitorRow = id => ({
  id,
  name: 'Visitor ' + id,
  contact: '+93 700 000 10' + id,
  email: 'visitor' + id + '@example.com',
  service: 'Scholarship',
  counselor_employee_id: null,
  counselor: null,
  notes: null,
  follow_up: ${mode === 'follow-up'},
  date: '2026-10-0' + id,
});
const visitorApi = {
  getAll: async params => { window.visitorRequests.push(params); return [visitorRow(1), visitorRow(2)]; },
  setFollowUp: async (id, followUp) => { window.followUpRequests.push({ id, followUp }); return { ...visitorRow(id), follow_up: followUp }; },
  create: async () => visitorRow(3),
  update: async () => visitorRow(1),
  delete: async () => {},
};
const factories = {}, cache = {};
const marker = className => ({ setup: () => () => Vue.h('span', { class: className }) });
const stub = (name, className) => ({ default: { name, ...marker(className) } });
const printableReport = { default: {
  name: 'PrintableListReport',
  props: ['title', 'subtitle', 'columns', 'rows'],
  setup: props => () => Vue.h('div', {
    class: 'print-report-stub',
    'data-title': props.title ?? '',
    'data-subtitle': props.subtitle ?? '',
  }),
} };
// The router reuses this page when only the query changes (the strip deep-links
// the same route), so the route query is reactive and 'switchList' performs
// exactly that change without remounting the component.
const routeState = Vue.reactive({ name: 'visitor', meta: {}, params: {}, query: { ...query } });
const mocks = {
  'vue': Vue,
  'vuetify/components': Vuetify,
  'vue-i18n': { useI18n: () => ({ t, locale: Vue.ref(locale) }) },
  'vue-router': {
    useRoute: () => routeState,
    useRouter: () => ({ push: () => {}, replace: () => {} }),
  },
  'vue-toastification': { useToast: () => ({ error: message => window.toastErrors.push(String(message)), success: message => window.toastSuccess.push(String(message)), info: () => {} }) },
  '@casl/vue': { useAbility: () => ({ can: () => true }) },
  '@core/composable/useCookie': { useCookie: () => Vue.ref({ roles: [], employee: null }) },
  '@/utils/authorization': { canAccess: () => true },
  '@/utils/apiErrors': { apiErrorMessage: (_error, fallback) => fallback },
  '@/utils/localization': { translate: t },
  '@/utils/printDocument': { printDocument: async () => { window.printed = true } },
  '@/api/visitor': { default: visitorApi },
  '@/api/employee': { default: { getAssignmentOptions: async () => [] } },
  '@/components/PrintableListReport.vue': printableReport,
  '@/components/ContactActionMenu.vue': stub('ContactActionMenu', 'quick-contact-stub'),
  '@/components/crm/CrmRelationshipDetails.vue': stub('CrmRelationshipDetails', 'relationship-details-stub'),
  '@/components/erp/ErpDateRange.vue': stub('ErpDateRange', 'date-range-stub'),
  '@/components/erp/ErpField.vue': stub('ErpField', 'field-stub'),
  './workspace.scss': {},
};
${componentFactories.join('\n')}
// Compiled SFCs win over the stub/API mocks so a real component is never shadowed.
function require(name){
  if (factories[name]) { if (cache[name]) return cache[name].exports; const module = { exports: {} }; cache[name] = module; factories[name](require, module, module.exports); return module.exports; }
  if (mocks[name]) return mocks[name];
  throw new Error('missing module ' + name);
}
const vuetify = Vuetify.createVuetify({
  locale: { rtl: { en: false, fa: true }, locale: ${JSON.stringify(locale === 'en' ? 'en' : 'fa')} },
  theme: { defaultTheme: 'light', themes: ${JSON.stringify(appThemes)} },
  icons: { defaultSet: 'class', sets: { class: { component: props => Vue.h('i', { class: props.icon }) } } },
});
window.testTheme = theme => vuetify.theme.change(theme);
// Mirrors the strip's own destinations: only the Follow-up entry declares a
// marker, so visiting the Visitors destination clears the query again.
window.switchList = list => { routeState.query = list === 'follow-up' ? { list: 'follow-up' } : {}; };
const page = require('@/pages/visitor.vue').default;
const app = Vue.createApp({ render: () => Vue.h('div', { class: 'app-shell' }, [Vue.h(page)]) });
app.config.globalProperties.$t = t;
app.component('RouterLink', { props: ['to'], setup: () => () => Vue.h('a', { href: '#' }, '') });
// The responsive table is registered application-wide. The harness swaps in a
// minimal table that renders the very same item.<column> slots, so the cells
// under test are the page's own slot content.
// Components the application auto-imports (unplugin-vue-components) are not
// imported by the page itself, so the harness registers them by name.
app.component('CrmRelationshipDetails', marker('relationship-details-stub'));
app.component('ResponsiveDataTable', {
  props: { headers: { type: Array, default: () => [] }, items: { type: Array, default: () => [] } },
  setup: (props, { slots }) => () => Vue.h('table', { class: 'responsive-table-stub' }, [
    Vue.h('tbody', {}, props.items.map(item => Vue.h('tr', { 'data-id': item.id }, props.headers.map(header => Vue.h(
      'td',
      { class: 'cell-' + header.key },
      slots['item.' + header.key] ? [slots['item.' + header.key]({ item })] : [String(item[header.key] ?? '')],
    ))))),
  ]),
});
app.component('DataTableFilterMenu', { setup: (_, { slots }) => () => Vue.h('div', { class: 'filter-menu-stub' }, slots.default ? slots.default() : []) });
app.use(vuetify);
try { app.mount('#app') } catch (error) { window.bootError = String(error?.stack ?? error) }
window.__pageMounted = true;
`
}

const pageHtml = (locale, mode) => `<!doctype html>
<html lang="${locale === 'en' ? 'en' : 'fa'}" dir="${locale === 'en' ? 'ltr' : 'rtl'}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="file://${root}node_modules/vuetify/dist/vuetify.css">
<link rel="stylesheet" href="file://${root}resources/ts/plugins/iconify/icons.css">
<style>body { font-family: Arial, sans-serif; margin: 0; } ${scopedCss}</style>
</head>
<body>
<div id="app"></div>
<script src="file://${root}node_modules/vue/dist/vue.global.js"></script>
<script src="file://${root}node_modules/vuetify/dist/vuetify.js"></script>
<script>try { ${buildHarness(locale, mode).replace(/<\/script/gi, '<\\/script')} } catch (error) { window.bootError = String(error?.stack ?? error) }</script>
</body>
</html>`

const pages = {}
for (const locale of ['en', 'fa-AF']) {
  for (const mode of ['visitors', 'follow-up']) {
    const file = join(output, `visitor-${mode}-${locale}.html`)

    writeFileSync(file, pageHtml(locale, mode))
    pages[`${locale}:${mode}`] = `file://${file}`
  }
}

// --- minimal CDP client (same approach as the other page-mount regressions) ----
const chrome = spawn(chromeBinary, [
  '--headless=new',
  '--remote-debugging-port=9222',
  '--no-sandbox',
  '--disable-gpu',
  '--disable-dev-shm-usage',
  `--user-data-dir=${join(output, 'profile')}`,
  '--allow-file-access-from-files',
  'about:blank',
], { stdio: ['ignore', 'ignore', 'pipe'] })

let chromeStderr = ''
chrome.stderr.on('data', chunk => { chromeStderr += chunk })

const endpoint = async path => {
  for (let attempt = 0; attempt < 100; attempt++) {
    try {
      const response = await fetch(`http://127.0.0.1:9222${path}`)
      if (response.ok)
        return response.json()
    }
    catch { /* Chrome is still starting */ }
    await new Promise(resolve => setTimeout(resolve, 100))
  }
  throw new Error(`Chrome DevTools never became available. Set CHROME_BIN if ${chromeBinary} is wrong.\n${chromeStderr}`)
}

const version = await endpoint('/json/version')
const socket = new WebSocket(version.webSocketDebuggerUrl)

await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject })

let messageId = 0
const pending = new Map()

socket.onmessage = event => {
  const message = JSON.parse(event.data)
  const entry = pending.get(message.id)
  if (!entry)
    return
  pending.delete(message.id)
  if (message.error)
    entry.reject(new Error(JSON.stringify(message.error)))
  else
    entry.resolve(message.result)
}

const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
  const id = ++messageId

  pending.set(id, { resolve, reject })
  socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }))
})

const { targetId } = await send('Target.createTarget', { url: 'about:blank' })
const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true })

const evaluate = async expression => {
  const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }, sessionId)
  if (result.exceptionDetails)
    throw new Error(`browser exception: ${result.exceptionDetails.exception?.description ?? result.exceptionDetails.text}`)

  return result.result.value
}

const errors = []

await send('Log.enable', {}, sessionId)
await send('Runtime.enable', {}, sessionId)
socket.addEventListener('message', event => {
  const message = JSON.parse(event.data)
  if (message.method === 'Log.entryAdded' && message.params?.entry?.level === 'error')
    errors.push(message.params.entry.text)
  if (message.method === 'Runtime.exceptionThrown')
    errors.push(message.params?.exceptionDetails?.exception?.description ?? 'uncaught exception')
})

const sleep = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds))

const settle = async (attempts = 40) => {
  for (let attempt = 0; attempt < attempts; attempt++) {
    await sleep(50)
    if (await evaluate('(window.visitorRequests?.length ?? 0) > 0 && document.querySelectorAll("table tbody tr").length > 0'))
      return
  }
  const diagnostic = await evaluate('JSON.stringify({ bootError: window.bootError ?? null, requests: window.visitorRequests ?? null, tables: document.querySelectorAll("table").length, consoleErrors: window.consoleErrors })')

  throw new Error(`the visitor page never rendered its list: ${diagnostic}`)
}

const countSelector = selector => evaluate(`document.querySelectorAll(${JSON.stringify(selector)}).length`)
const cellText = (row, cell) => evaluate(`document.querySelectorAll('table tbody tr')[${row}].querySelector('.cell-${cell}').textContent.trim()`)
const countInRow = (row, selector) => evaluate(`document.querySelectorAll('table tbody tr')[${row}].querySelectorAll(${JSON.stringify(selector)}).length`)
const countCellIconButtons = icon => evaluate(`Array.from(document.querySelectorAll('.cell-actions button')).filter(button => button.querySelector('i.${icon}')).length`)

const clickCellIconButton = icon => evaluate(`(() => {
  const button = Array.from(document.querySelectorAll('.cell-actions button')).find(element => element.querySelector('i.${icon}'));
  if (!button) throw new Error('missing action with icon ' + ${JSON.stringify(icon)});
  button.click();
})()`)

const rowCount = () => evaluate('document.querySelectorAll("table tbody tr").length')

const subnav = () => evaluate(`JSON.stringify(Array.from(document.querySelectorAll('.erp-subnav .erp-subnav-btn')).map(button => ({
  label: button.textContent.trim(),
  icon: Array.from(button.querySelectorAll('i')).flatMap(node => String(node.className).split(' ')).find(name => name.startsWith('tabler-')) ?? '',
  declaredColor: Array.from(button.classList).find(name => name.startsWith('bg-') || name.startsWith('text-')) ?? '',
  current: button.getAttribute('aria-current') ?? '',
  variant: button.className,
})))`)

try {
  for (const locale of ['en', 'fa-AF']) {
    const messages = JSON.parse(read(`plugins/i18n/locales/${locale}.json`))
    const visitorLabels = messages.common.navigation.visitor
    const followUpLabels = messages.common.navigation.followUpList
    const visitorKeys = messages.pages.contracts.visitor.visitor

    for (const mode of ['visitors', 'follow-up']) {
      const followUp = mode === 'follow-up'
      const where = `${locale}/${mode}`

      await send('Page.navigate', { url: pages[`${locale}:${mode}`] }, sessionId)
      await settle()

      const bootError = await evaluate('window.bootError ?? null')

      assert.equal(bootError, null, `the visitor page failed to mount (${where}): ${bootError}`)

      // 1. the shared CRM strip offers both lists plus the applicants page, and
      //    the active list is the only destination marked current.
      const strip = JSON.parse(await subnav())

      assert.deepEqual(strip.map(entry => entry.label), [visitorLabels, followUpLabels, messages.common.navigation.applicants], `unexpected CRM destinations (${where}): ${JSON.stringify(strip)}`)
      assert.deepEqual(strip.map(entry => entry.icon), ['tabler-user-search', 'tabler-list-check', 'tabler-users'], `every destination needs its own icon (${where}): ${JSON.stringify(strip)}`)
      assert.equal(new Set(strip.map(entry => entry.declaredColor)).size, 3, `every destination needs its own colour (${where}): ${JSON.stringify(strip)}`)

      const currentIndex = followUp ? 1 : 0

      assert.deepEqual(strip.map(entry => entry.current), currentIndex === 0 ? ['page', '', ''] : ['', 'page', ''], `exactly the ${mode} list must be marked current (${where}): ${JSON.stringify(strip)}`)
      assert.ok(strip[currentIndex].variant.includes('v-btn--variant-flat'), `the active list must be filled (${where}): ${JSON.stringify(strip[currentIndex])}`)
      assert.ok(strip.slice(0, currentIndex).concat(strip.slice(currentIndex + 1)).every(entry => entry.variant.includes('v-btn--variant-tonal')), `the other destinations must stay tonal (${where}): ${JSON.stringify(strip)}`)

      // 2. the page title and the printed report follow the active list.
      assert.equal(await evaluate('document.querySelector(".erp-page-header h1").textContent.trim()'), followUp ? followUpLabels : visitorLabels, `the page title must follow the active list (${where})`)

      const report = JSON.parse(await evaluate('JSON.stringify({ title: document.querySelector(".print-report-stub").dataset.title, subtitle: document.querySelector(".print-report-stub").dataset.subtitle })'))
      const printKeys = followUp ? messages.common.crmUi.printReports.followUp : messages.common.crmUi.printReports.visitors

      assert.equal(report.title, printKeys.title, `the printed report title must follow the active list (${where})`)
      assert.equal(report.subtitle, printKeys.subtitle, `the printed report subtitle must follow the active list (${where})`)

      // 3. the list asks for its own side of the follow-up flag.
      const request = await evaluate('window.visitorRequests.at(-1)')

      assert.equal(request.follow_up, followUp ? 1 : 0, `the ${mode} list must request its own side of the flag (${where}): ${JSON.stringify(request)}`)

      // 4. quick contact is only offered on the Follow-up list; the Visitors list
      //    shows the raw contact details instead.
      assert.equal(await countSelector('.quick-contact-stub'), followUp ? 4 : 0, `quick contact belongs to the follow-up list only (${where})`)
      if (followUp) {
        assert.equal(await countInRow(0, '.cell-contact .quick-contact-stub'), 1, `the phone must be a quick-contact control on the follow-up list (${where})`)
        assert.equal(await countInRow(0, '.cell-email .quick-contact-stub'), 1, `the e-mail must be a quick-contact control on the follow-up list (${where})`)
      }
      else {
        assert.equal(await cellText(0, 'contact'), '+93 700 000 101', `the Visitors list must show the phone as plain text (${where})`)
        assert.equal(await cellText(0, 'email'), 'visitor1@example.com', `the Visitors list must show the e-mail as plain text (${where})`)
      }

      // 5. the action set is the one the active list owns.
      assert.equal(await countSelector('.relationship-details-stub'), followUp ? 2 : 0, `relationship details belong to the follow-up list (${where}): ${await evaluate('document.querySelector(".cell-actions")?.innerHTML?.slice(0, 1500)')}`)
      assert.equal(await countCellIconButtons('tabler-file-plus'), followUp ? 2 : 0, `create contract belongs to the follow-up list (${where})`)
      assert.equal(await countCellIconButtons('tabler-star'), followUp ? 0 : 2, `the move-to-follow-up action belongs to the visitors list (${where})`)
      assert.equal(await countCellIconButtons('tabler-star-off'), followUp ? 2 : 0, `the reverse action belongs to the follow-up list (${where})`)
      assert.equal(await countCellIconButtons('tabler-edit'), 2, `edit stays in both lists (${where})`)
      assert.equal(await countCellIconButtons('tabler-trash'), 2, `delete stays in both lists (${where})`)

      // 6. the add button exists on the Visitors list only. The check is scoped to
      //    real buttons: the harness embeds the message catalogue in the page, so
      //    a raw text search would also match the catalogue itself.

      const addButtons = await evaluate(`Array.from(document.querySelectorAll('button')).filter(button => button.textContent.trim() === ${JSON.stringify(visitorKeys.addVisitor)}).length`)

      assert.equal(addButtons, followUp ? 0 : 1, `the add button belongs to the visitors list only (${where})`)

      // 7. the move action calls the API and drops the row from the list on screen.
      await clickCellIconButton(followUp ? 'tabler-star-off' : 'tabler-star')
      await sleep(150)

      const [move] = await evaluate('window.followUpRequests')

      assert.deepEqual(move, { id: 1, followUp: !followUp }, `the move action must send the new flag (${where})`)
      assert.equal(await rowCount(), 1, `the moved visitor must leave the list on screen (${where})`)

      const [toast] = await evaluate('window.toastSuccess')

      assert.equal(toast, followUp ? visitorKeys.removedFromFollowUp : visitorKeys.markedForFollowUp, `the move action must report its outcome (${where})`)
      assert.deepEqual(await evaluate('window.toastErrors'), [], `the move action must not report failures (${where})`)

      // 8. switching lists in place: both destinations share one route, so the
      //    page is reused and must re-read the flag instead of keeping the list
      //    that happens to be on screen.
      const switched = followUp ? 'visitors' : 'follow-up'
      const switchedFollowUp = !followUp

      await evaluate(`window.switchList(${JSON.stringify(switched)})`)
      await sleep(200)

      assert.equal(await evaluate('document.querySelector(".erp-page-header h1").textContent.trim()'), switchedFollowUp ? followUpLabels : visitorLabels, `the title must follow an in-place switch to the ${switched} list (${where})`)
      assert.equal((await evaluate('window.visitorRequests.at(-1)')).follow_up, switchedFollowUp ? 1 : 0, `an in-place switch must re-request the ${switched} list (${where})`)
      assert.equal(JSON.parse(await subnav())[switchedFollowUp ? 1 : 0].current, 'page', `the ${switched} destination must become current after an in-place switch (${where})`)

      // 9. theming and narrow screens: the strip wraps by design, but it must
      //    never push the page or a destination outside the viewport.
      await evaluate('window.testTheme("dark")')
      await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true }, sessionId)

      const mobile = JSON.parse(await evaluate(`JSON.stringify({
        page: document.documentElement.scrollWidth,
        viewport: window.innerWidth,
        outside: Array.from(document.querySelectorAll('.erp-subnav-btn')).filter(button => {
          const box = button.getBoundingClientRect();

          return box.left < -1 || box.right > window.innerWidth + 1;
        }).length,
      })`))

      assert.ok(mobile.page <= mobile.viewport + 2, `the page overflows on mobile (${where}): ${JSON.stringify(mobile)}`)
      assert.equal(mobile.outside, 0, `every in-page destination must stay inside the viewport on mobile (${where}): ${JSON.stringify(mobile)}`)
      await send('Emulation.clearDeviceMetricsOverride', {}, sessionId)
      await evaluate('window.testTheme("light")')
    }
  }
}
finally {
  await send('Browser.close').catch(() => {})
  socket.close()
  chrome.kill()
}

assert.deepEqual(errors, [], `console errors: ${JSON.stringify(errors)}`)

console.log('visitor follow-up regression: ok (visitors/follow-up lists, page + print titles, contacts, actions, flag filter)')
