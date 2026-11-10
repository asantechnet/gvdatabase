import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { createRequire } from 'node:module'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Applications list regression: mounts the real `pages/applicants.vue` in Chrome
 * with real Vuetify and asserts that
 *
 *  1. the page header carries the concept breadcrumbs plus the shared in-page
 *     navigation that interconnects Applications / Search / Visa reports, with
 *     its own icon and colour on every destination,
 *  2. the workflow stages are the data-table tabs (open pipeline first, then the
 *     terminal/closing stages after a separator, marked with their own icons),
 *  3. picking a stage tab calls the list API with `stage=<key>` and never with
 *     the retired `board` parameter,
 *  4. "All applications" clears the stage filter again,
 *  5. the strip stays on one scrollable row inside the viewport on mobile.
 *
 * It runs for `en` (LTR) and `fa-AF` (RTL), in light and dark themes.
 */

const root = fileURLToPath(new URL('..', import.meta.url))
const require = createRequire(root + 'package.json')
const { parse, compileScript, compileStyle } = require('@vue/compiler-sfc')
const ts = require('typescript')

const output = process.env.APPLICATIONS_TABS_OUTPUT ?? join(tmpdir(), 'educational-applications-tabs')
mkdirSync(output, { recursive: true })

const read = file => readFileSync(join(root, 'resources/ts', file), 'utf8')

// The page renders the real Vuetify components, so the harness must use the app's
// own theme: the in-page navigation paints each destination with a theme colour
// (including `tertiary`, which Vuetify's built-in light theme does not define).
const themeModule = { exports: {} }
const semanticThemeColorsModule = { exports: {} }

new Function('exports', 'module', ts.transpileModule(read('utils/semanticThemeColors.ts'), {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText)(semanticThemeColorsModule.exports, semanticThemeColorsModule)

new Function('exports', 'module', 'require', ts.transpileModule(read('plugins/vuetify/theme.ts'), {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText)(themeModule.exports, themeModule, name => {
  if (name === '@/utils/semanticThemeColors')
    return semanticThemeColorsModule.exports

  throw new Error(`unexpected theme dependency: ${name}`)
})

const appThemes = themeModule.exports.themes

assert.ok(appThemes?.light?.colors?.tertiary && appThemes?.dark?.colors?.tertiary, 'the app theme must define tertiary for both themes')

const chromeBinary = process.env.CHROME_BIN ?? ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser']
  .find(candidate => {
    try {
      require('node:child_process').execSync(`command -v ${candidate}`, { stdio: 'ignore' })

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
  const scopeId = 'data-v-' + file.replace(/[^a-z0-9]/gi, '')
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

compileComponent('pages/applicants.vue', '@/pages/applicants.vue')
compileComponent('components/erp/ErpPageHeader.vue', '@/components/erp/ErpPageHeader.vue')

const buildHarness = locale => {
  const messages = JSON.parse(read(`plugins/i18n/locales/${locale}.json`))
  const stubs = [
    '@/components/erp/ErpField.vue',
    '@/components/erp/ErpDateRange.vue',
    '@/components/erp/ErpSearchField.vue',
    '@/pages/applicant-pipeline.vue',
    '@/components/ApplicantServiceFields.vue',
    '@/components/crm/CrmRelationshipDetails.vue',
    '@/components/ContactActionMenu.vue',
    '@/components/responsive-data-table/DataTableFilterMenu.vue',
    '@/components/responsive-data-table/ResponsiveDataTableServer.vue',
    '@/components/PrintableListReport.vue',
    '@/components/TableEmptyState.vue',
    '@/components/TableLoadingSkeleton.vue',
  ]

  return `
const messages = ${JSON.stringify(messages)};
const t = key => key.split('.').reduce((node, part) => node?.[part], messages) ?? key;
const locale = ${JSON.stringify(locale)};
const useDebounceFn = fn => fn;
const stages = [
  { id: 1, key: 'new', name: 'New', is_terminal: false, sort_order: 10 },
  { id: 2, key: 'consultation', name: 'Consultation', is_terminal: false, sort_order: 20 },
  { id: 3, key: 'visa_processing', name: 'Visa Processing', is_terminal: false, sort_order: 110 },
  { id: 4, key: 'completed', name: 'Completed', is_terminal: true, sort_order: 140 },
  { id: 5, key: 'cancelled', name: 'Cancelled', is_terminal: true, sort_order: 900 },
];
window.requests = [];
window.toastErrors = [];
window.bootError = null;
const row = id => ({ id, name: 'Applicant ' + id, service: 'scholarship', status: 'active', contact: '070000000' + id, current_stage: 'new', attachments_count: 1, details: {} });
const applicantsApi = {
  list: async params => { window.requests.push(params); return { data: [row(1), row(2)], current_page: 1, last_page: 1, per_page: 25, total: 2 } },
  stages: async () => stages,
};
const factories = {}, cache = {};
const stub = name => ({ default: { name, setup: (_, { slots }) => () => (slots.default ? slots.default() : null) } });
const mocks = {
  'vue': Vue,
  'vuetify/components': Vuetify,
  'vue-i18n': { useI18n: () => ({ t, locale: Vue.ref(locale) }) },
  'vue-router': {
    useRoute: () => ({ name: 'applications', meta: {}, query: {}, params: {} }),
    useRouter: () => ({ push: () => {}, replace: () => {} }),
    onBeforeRouteLeave: () => {},
  },
  'vue-toastification': { useToast: () => ({ error: message => window.toastErrors.push(String(message)), success: () => {}, info: () => {} }) },
  '@/utils/authorization': { canAccess: () => true },
  '@/utils/apiErrors': { apiErrorMessage: () => 'Request failed' },
  '@/utils/printDocument': { printDocument: () => {} },
  '@/utils/localization': { translateApplicantStage: stage => stage?.name ?? '', translateBackendValue: value => String(value ?? '') },
  '@/api/applicants': { default: applicantsApi, applicantServicePayload: form => ({ ...form }) },
  '@/api/educationalMasterData': { default: { list: async () => [] } },
  '@/api/employee': { default: { getOptions: async () => [], getAssignmentOptions: async () => [] } },
  './workspace.scss': {},
${stubs.map(file => `  ${JSON.stringify(file)}: stub(${JSON.stringify(file.split('/').pop())}),`).join('\n')}
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
const page = require('@/pages/applicants.vue').default;
// Installing the Vuetify plugin registers every component globally, so the real
// VCard / VTabs / VTable markup inside the page resolves exactly as it does in
// the application shell.
const app = Vue.createApp({ render: () => Vue.h('div', { class: 'app-shell' }, [Vue.h(page)]) });
app.config.globalProperties.$t = t;
app.component('RouterLink', { props: ['to'], setup: () => () => Vue.h('a', { href: '#' }, '') });
app.use(vuetify);
try { app.mount('#app') } catch (error) { window.bootError = String(error?.stack ?? error) }
window.__pageMounted = true;
`
}

const pageHtml = locale => `<!doctype html>
<html lang="${locale === 'en' ? 'en' : 'fa'}" dir="${locale === 'en' ? 'ltr' : 'rtl'}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="file://${root}/node_modules/vuetify/dist/vuetify.css">
<link rel="stylesheet" href="file://${root}/resources/ts/plugins/iconify/icons.css">
<style>body { font-family: Arial, sans-serif; margin: 0; } ${scopedCss}</style>
</head>
<body>
<div id="app"></div>
<script src="file://${root}/node_modules/vue/dist/vue.global.js"></script>
<script src="file://${root}/node_modules/vuetify/dist/vuetify.js"></script>
<script>try { ${buildHarness(locale).replace(/<\/script/gi, '<\\/script')} } catch (error) { window.bootError = String(error?.stack ?? error) }</script>
</body>
</html>`

const pages = {}
for (const locale of ['en', 'fa-AF']) {
  const file = join(output, `applications-${locale}.html`)
  writeFileSync(file, pageHtml(locale))
  pages[locale] = 'file://' + file
}

// --- minimal CDP client (same approach as contracts/loan print checks) ---------
const chrome = spawn(chromeBinary, [
  '--headless=new', '--remote-debugging-port=9222', '--no-sandbox', '--disable-gpu',
  '--disable-dev-shm-usage', `--user-data-dir=${join(output, 'profile')}`,
  '--allow-file-access-from-files', 'about:blank',
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

const settle = async (attempts = 40) => {
  for (let attempt = 0; attempt < attempts; attempt++) {
    await new Promise(resolve => setTimeout(resolve, 50))
    if (await evaluate('Boolean(document.querySelector(".v-tabs .v-tab")) && window.requests.length > 0'))
      return
  }
  const diagnostic = await evaluate('JSON.stringify({ bootError: window.bootError ?? null, requests: window.requests?.length ?? null, errors: window.toastErrors })')

  throw new Error(`the applications page never rendered its tab strip: ${diagnostic}`)
}

const tabLabels = () => evaluate('Array.from(document.querySelectorAll(".v-tabs .v-tab")).map(element => element.textContent.trim())')
const clickTab = label => evaluate(`(() => {
  const tab = Array.from(document.querySelectorAll('.v-tabs .v-tab')).find(element => element.textContent.includes(${JSON.stringify(label)}));
  if (!tab) throw new Error('missing tab ' + ${JSON.stringify(label)});
  tab.click();
})()`)
const lastRequest = () => evaluate('window.requests.at(-1)')

try {
  for (const locale of ['en', 'fa-AF']) {
    const messages = JSON.parse(read(`plugins/i18n/locales/${locale}.json`))
    const allLabel = messages.educational.allApplications
    await send('Page.navigate', { url: pages[locale] }, sessionId)
    await settle()

    const bootError = await evaluate('window.bootError ?? null')
    assert.equal(bootError, null, `the applications page failed to mount (${locale}): ${bootError}`)

    // 1. the concept frame is present: breadcrumbs plus the shared in-page
    //    navigation that interconnects Applications / Search / Visa reports.
    //    (This replaces an earlier assertion that they were absent: the page was
    //    brought in line with the Visitors and Contracts pages on request.)
    assert.equal(await evaluate('Boolean(document.querySelector(".erp-page-header .v-breadcrumbs"))'), true, 'applications must render breadcrumbs')
    const subnav = JSON.parse(await evaluate(`JSON.stringify(Array.from(document.querySelectorAll('.erp-subnav .erp-subnav-btn')).map(button => {
      const computed = getComputedStyle(button);

      return {
        label: button.textContent.trim(),
        icon: Array.from(button.querySelectorAll('i')).flatMap(node => String(node.className).split(' ')).find(name => name.startsWith('tabler-')) ?? '',
        tone: computed.color + ' / ' + computed.backgroundColor,
        declaredColor: Array.from(button.classList).find(name => name.startsWith('bg-') || name.startsWith('text-')) ?? '',
        current: button.getAttribute('aria-current') ?? '',
        variant: button.className,
      };
    }))`))
    assert.equal(subnav.length, 3, `the applications in-page navigation must offer 3 destinations: ${JSON.stringify(subnav)}`)
    assert.deepEqual(subnav.map(entry => entry.label), [messages.educational.applications, messages.common.navigation.globalSearch, messages.common.navigation.visaReports], `unexpected in-page destinations: ${JSON.stringify(subnav)}`)
    assert.deepEqual(subnav.map(entry => entry.icon), ['tabler-clipboard-list', 'tabler-search', 'tabler-report-analytics'], `every destination needs its own icon: ${JSON.stringify(subnav)}`)
    assert.ok(subnav.every(entry => entry.declaredColor), `every destination needs its own colour: ${JSON.stringify(subnav)}`)
    assert.equal(new Set(subnav.map(entry => entry.declaredColor)).size, 3, `every destination needs its own colour: ${JSON.stringify(subnav)}`)
    assert.equal(new Set(subnav.map(entry => entry.tone)).size, 3, `every destination must actually paint its own colour: ${JSON.stringify(subnav)}`)
    assert.equal(subnav[0].current, 'page', `the current page must be marked as current: ${JSON.stringify(subnav)}`)
    assert.ok(subnav[0].variant.includes('v-btn--variant-flat'), `the current page must be filled: ${JSON.stringify(subnav)}`)
    assert.ok(subnav[1].variant.includes('v-btn--variant-tonal') && subnav[2].variant.includes('v-btn--variant-tonal'), `the sibling pages must stay tonal: ${JSON.stringify(subnav)}`)

    // 2. the stages are the data-table tabs, pipeline first and terminal last.
    const labels = await tabLabels()
    assert.equal(labels.length, 6, `expected 6 tabs, got ${JSON.stringify(labels)}`)
    assert.ok(labels[0].includes(allLabel), `expected the first tab to be "${allLabel}": ${JSON.stringify(labels)}`)
    const order = ['New', 'Consultation', 'Visa Processing', 'Completed', 'Cancelled']
    assert.deepEqual(order.map(name => labels.slice(1).findIndex(label => label.includes(name))), [0, 1, 2, 3, 4], `stages must keep their workflow order: ${JSON.stringify(labels)}`)
    assert.equal(await evaluate('document.querySelectorAll(".v-tabs .v-tab .tabler-circle-check").length'), 1, 'completed must be the only success-marked terminal tab')
    assert.equal(await evaluate('document.querySelectorAll(".v-tabs .v-tab .tabler-ban").length'), 1, 'cancelled must carry the terminal marker')

    // 3. a stage tab filters the list by that stage and never sends `board`.
    await clickTab('Visa Processing')
    await settle()
    const stageRequest = await lastRequest()
    assert.equal(stageRequest.stage, 'visa_processing', JSON.stringify(stageRequest))
    assert.ok(!('board' in stageRequest), 'the retired board parameter must not be sent')

    // 4. "All applications" clears the stage filter again.
    await clickTab(allLabel)
    await new Promise(resolve => setTimeout(resolve, 50))
    await settle()
    const allRequest = await lastRequest()
    assert.ok(!allRequest.stage, `All applications must clear the stage filter: ${JSON.stringify(allRequest)}`)

    await evaluate('window.testTheme("dark")')

    // 5. responsive: the strip scrolls instead of widening the page.
    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true }, sessionId)
    const mobile = await evaluate(`JSON.stringify({
      page: document.documentElement.scrollWidth,
      viewport: window.innerWidth,
      rows: new Set(Array.from(document.querySelectorAll('.v-tabs .v-tab')).map(tab => Math.round(tab.getBoundingClientRect().top))).size,
      contentWidth: document.querySelector('.v-tabs .v-slide-group__content').scrollWidth,
      containerWidth: document.querySelector('.v-tabs .v-slide-group__container').clientWidth,
      arrows: document.querySelectorAll('.v-tabs .v-slide-group__next').length,
    })`)
    const mobileMetrics = JSON.parse(mobile)
    assert.ok(mobileMetrics.page <= mobileMetrics.viewport + 2, `page overflows on mobile: ${mobile}`)
    assert.equal(mobileMetrics.rows, 1, `the tab strip must stay on one row on mobile: ${mobile}`)
    assert.ok(mobileMetrics.contentWidth > mobileMetrics.containerWidth, `the tab strip must scroll horizontally instead of wrapping: ${mobile}`)
    assert.equal(mobileMetrics.arrows, 1, `the tab strip must offer its scroll arrows: ${mobile}`)
    await send('Emulation.clearDeviceMetricsOverride', {}, sessionId)
  }
} finally {
  await send('Browser.close').catch(() => {})
  socket.close()
  chrome.kill()
}

assert.deepEqual(errors, [], `console errors: ${JSON.stringify(errors)}`)
console.log('Applications list passed: breadcrumbs plus the 3-destination in-page navigation (each with its own icon and colour, current page filled), stages as data-table tabs in workflow order, terminal stages marked, stage tabs filter the list by `stage` (no `board`), All applications clears the filter, and the stage strip stays responsive in LTR/RTL and light/dark.')
