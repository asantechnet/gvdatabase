import assert from 'node:assert/strict'
import { execSync, spawn } from 'node:child_process'
import { createRequire } from 'node:module'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * WhatsApp message template regression: mounts the real
 * `pages/whatsapp-messages.vue` and the real `components/ContactActionMenu.vue`
 * in Chrome with real Vuetify and asserts that:
 *
 *  1. the page offers one subpage per audience and switching subpages renders
 *     only the templates of that audience,
 *  2. a row shows its title, labels, order, the tokens it uses and the message
 *     rendered the way WhatsApp renders it (markers applied, sample values),
 *  3. the editor is a plain textarea (no rich-text surface), its toolbar wraps
 *     the selection in WhatsApp markers, and its preview matches the message,
 *  4. the quick-contact menu lists only the templates of the page's audience and
 *     opening one fills the placeholders from that row and opens
 *     `https://wa.me/<phone>?text=<rendered message>`,
 *  5. the page has no horizontal overflow on a mobile viewport, in dark theme
 *     and in RTL.
 *
 * It runs for `en` (LTR) and `fa-AF` (RTL).
 */

const root = fileURLToPath(new URL('..', import.meta.url))
const require = createRequire(`${root}package.json`)
const { parse, compileScript, compileStyle } = require('@vue/compiler-sfc')
const ts = require('typescript')

const output = process.env.WHATSAPP_TEMPLATES_OUTPUT ?? join(tmpdir(), 'educational-whatsapp-templates')

mkdirSync(output, { recursive: true })

const read = file => readFileSync(join(root, 'resources/ts', file), 'utf8')

const themeModule = { exports: {} }

new Function('exports', 'module', ts.transpileModule(read('plugins/vuetify/theme.ts'), {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText)(themeModule.exports, themeModule)

const appThemes = themeModule.exports.themes

assert.ok(appThemes?.light?.colors?.success && appThemes?.dark?.colors?.success, 'the app theme must define success for both themes')

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

/** Compile a plain TypeScript module so the real implementation is under test. */
const compileModule = (file, alias) => {
  const source = ts.transpileModule(read(file), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText

  componentFactories.push(`factories[${JSON.stringify(alias)}] = (require, module, exports) => {${source}};`)
}

compileComponent('pages/whatsapp-messages.vue', '@/pages/whatsapp-messages.vue')
compileComponent('components/whatsapp/WhatsappMessageEditor.vue', '@/components/whatsapp/WhatsappMessageEditor.vue')
compileComponent('components/ContactActionMenu.vue', '@/components/ContactActionMenu.vue')
// Registered without the extension: that is the specifier the components import.
compileModule('utils/whatsappTemplates.ts', '@/utils/whatsappTemplates')
compileModule('composables/useWhatsappTemplates.ts', '@/composables/useWhatsappTemplates')

// The audience catalogue the harness feeds both surfaces. Visitor rows carry a
// formatted body and an inactive row; the other audiences carry one row each, so
// "only this page's audience appears" is a meaningful assertion.
const harnessData = `
const templates = [
  { id: 1, audience: 'visitor', title: 'Visitor follow-up', labels: ['follow-up', 'visitor'], message: 'Hello *{{name}}*, thanks for visiting about {{service}}.\\nYour counselor {{counselor}} will follow up.', active: true, sort_order: 1, created_at: null, updated_at: null },
  { id: 2, audience: 'visitor', title: 'Visit reminder', labels: ['reminder'], message: 'Reminder: your visit is on {{date}}.', active: true, sort_order: 2, created_at: null, updated_at: null },
  { id: 3, audience: 'visitor', title: 'Retired visitor note', labels: [], message: 'Retired {{name}}.', active: false, sort_order: 3, created_at: null, updated_at: null },
  { id: 4, audience: 'applicant', title: 'Application status', labels: ['status'], message: 'Dear {{name}}, your case is at _{{stage}}_.', active: true, sort_order: 1, created_at: null, updated_at: null },
  { id: 5, audience: 'employee', title: 'Employee welcome', labels: ['onboarding'], message: 'Welcome {{name}} as {{position}}.', active: true, sort_order: 1, created_at: null, updated_at: null },
];
const field = (token, label, source, sample) => ({ token, label, label_key: 'pages.whatsapp.placeholders.' + token, source, sample });
const groups = [
  { audience: 'visitor', label: 'Visitor', label_key: 'pages.whatsapp.audiences.visitor', fields: [field('name', 'Full name', 'name', 'Sample Name'), field('service', 'Service', 'service', 'Scholarship'), field('counselor', 'Counselor', 'counselor.name', 'Sample Counselor'), field('date', 'Visit date', 'date', '2026-10-14')] },
  { audience: 'applicant', label: 'Applicant', label_key: 'pages.whatsapp.audiences.applicant', fields: [field('name', 'Full name', 'name', 'Sample Name'), field('stage', 'Current stage', 'current_stage.name', 'Consultation')] },
  { audience: 'employee', label: 'Employee', label_key: 'pages.whatsapp.audiences.employee', fields: [field('name', 'Full name', 'name', 'Sample Name'), field('position', 'Position', 'position', 'Counselor')] },
];
const whatsappWrites = [];
// The mock is stateful, so a save or a delete really changes the rows the page
// reloads — that is what makes the round trip below meaningful.
const whatsappApi = {
  getWhatsappTemplates: async () => templates,
  getWhatsappPlaceholders: async () => groups,
  createWhatsappTemplate: async data => { const created = { ...data, id: 99, created_at: null, updated_at: null }; templates.push(created); whatsappWrites.push({ method: 'create', audience: data.audience, title: data.title, message: data.message }); return created },
  updateWhatsappTemplate: async (id, data) => { const row = templates.find(item => item.id === id); Object.assign(row, data); whatsappWrites.push({ method: 'update', id, title: data.title }); return row },
  deleteWhatsappTemplate: async id => { const index = templates.findIndex(row => row.id === id); if (index >= 0) templates.splice(index, 1); whatsappWrites.push({ method: 'delete', id }) },
};
const visitorRow = { id: 7, name: 'Row Visitor', contact: '+93 700 555 777', email: 'row@example.com', service: 'Test offer', counselor: { id: 3, name: 'Row Counselor' }, date: '2026-10-01' };
`

/** Shared harness bootstrap: mocks, module resolution and the Vuetify instance. */
const harnessPreamble = locale => `
const messages = ${JSON.stringify(JSON.parse(read(`plugins/i18n/locales/${locale}.json`)))};
const t = (key, values = {}) => {
  const message = key.split('.').reduce((node, part) => node?.[part], messages);
  if (typeof message !== 'string') return key;
  return message.replace(/\\{(\\w+)\\}/g, (_, name) => String(values[name] ?? ''));
};
const locale = ${JSON.stringify(locale)};
${harnessData}
const ref = value => Vue.ref(value);
// useI18n is an application auto-import (unplugin-auto-import) rather than an
// explicit import, so the harness declares it in the factories' own scope.
const useI18n = () => ({ t, locale: ref(locale) });
window.opened = [];
window.open = url => { window.opened.push(String(url)); };
window.whatsappWrites = whatsappWrites;
window.toastErrors = [];
window.toastSuccess = [];
window.bootError = null;
window.consoleErrors = [];
const nativeError = console.error.bind(console);
console.error = (...args) => { window.consoleErrors.push(args.map(String).join(' ')); nativeError(...args); };
const factories = {}, cache = {};
const mocks = {
  'vue': Vue,
  'vuetify/components': Vuetify,
  'vue-i18n': { useI18n: () => ({ t, locale: ref(locale) }) },
  'vue-router': {
    useRoute: () => ({ name: 'whatsapp-messages', meta: {}, query: { tab: 'visitor' }, params: {} }),
    useRouter: () => ({ push: () => {}, replace: () => {} }),
  },
  'vue-toastification': { useToast: () => ({ error: message => window.toastErrors.push(String(message)), success: message => window.toastSuccess.push(String(message)), info: () => {} }) },
  '@/api/whatsappTemplates': whatsappApi,
  '@/api/crmRelationships': { default: { createInteraction: async () => ({}) } },
  '@/utils/authorization': { canAccess: () => true },
  '@/utils/apiErrors': { apiErrorMessage: (_error, fallback) => fallback, apiFieldErrors: () => ({}) },
  '@/utils/localization': { translate: t },
  './workspace.scss': {},
};
${componentFactories.join('\n')}
// Compiled sources win over the mocks, so a real module is never shadowed.
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
function mount(component, selector) {
  const app = Vue.createApp({ render: () => Vue.h('div', { class: 'app-shell' }, [Vue.h(component)]) });
  app.config.globalProperties.$t = t;
  app.config.globalProperties.$i18n = { locale: locale };
  // Components the application auto-imports are registered by name here.
  app.component('AppDateTimePicker', { props: ['modelValue', 'label'], setup: () => () => Vue.h('div', { class: 'datepicker-stub' }) });
  app.use(vuetify);
  app.mount(selector);
}
`

const pageHarness = locale => `${harnessPreamble(locale)}
try { mount(require('@/pages/whatsapp-messages.vue').default, '#page') } catch (error) { window.bootError = String(error?.stack ?? error) }
window.__pageMounted = true;
`

const menuHarness = locale => `${harnessPreamble(locale)}
try {
  const menu = require('@/components/ContactActionMenu.vue').default;
  const wrapper = Vue.defineComponent({
    render: () => Vue.h(menu, {
      kind: 'phone',
      value: visitorRow.contact,
      contactableType: 'visitor',
      contactableId: visitorRow.id,
      audience: 'visitor',
      context: visitorRow,
      autoOpen: true,
    }),
  });
  mount(wrapper, '#menu');
} catch (error) { window.bootError = String(error?.stack ?? error) }
window.__menuMounted = true;
`

const pageHtml = (locale, harness) => `<!doctype html>
<html lang="${locale === 'en' ? 'en' : 'fa'}" dir="${locale === 'en' ? 'ltr' : 'rtl'}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="file://${root}node_modules/vuetify/dist/vuetify.css">
<link rel="stylesheet" href="file://${root}resources/ts/plugins/iconify/icons.css">
<style>body { font-family: Arial, sans-serif; margin: 0; } ${scopedCss}</style>
</head>
<body>
<div id="page"></div>
<div id="menu"></div>
<script src="file://${root}node_modules/vue/dist/vue.global.js"></script>
<script src="file://${root}node_modules/vuetify/dist/vuetify.js"></script>
<script>try { ${harness.replace(/<\/script/gi, '<\\/script')} } catch (error) { window.bootError = String(error?.stack ?? error) }</script>
</body>
</html>`

const documents = {}

for (const locale of ['en', 'fa-AF']) {
  const pageFile = join(output, `whatsapp-page-${locale}.html`)
  const menuFile = join(output, `whatsapp-menu-${locale}.html`)

  writeFileSync(pageFile, pageHtml(locale, pageHarness(locale)))
  writeFileSync(menuFile, pageHtml(locale, menuHarness(locale)))
  documents[`${locale}:page`] = `file://${pageFile}`
  documents[`${locale}:menu`] = `file://${menuFile}`
}

// --- minimal CDP client (same approach as the other page-mount regressions) ----
const chrome = spawn(chromeBinary, [
  '--headless=new',
  '--remote-debugging-port=9223',
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
      const response = await fetch(`http://127.0.0.1:9223${path}`)
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

const text = selector => evaluate(`document.querySelector(${JSON.stringify(selector)})?.textContent?.trim() ?? null`)
const texts = selector => evaluate(`JSON.stringify(Array.from(document.querySelectorAll(${JSON.stringify(selector)})).map(node => node.textContent.trim()))`)
const count = selector => evaluate(`document.querySelectorAll(${JSON.stringify(selector)}).length`)

/**
 * Vuetify keeps every rendered VWindowItem in the DOM and hides the inactive
 * ones, so an "only this subpage" assertion has to look at what is visible
 * rather than at what merely exists.
 */
const visibleRows = 'Array.from(document.querySelectorAll(".whatsapp-row")).filter(node => node.offsetParent !== null)'
const visibleTexts = selector => evaluate(`JSON.stringify(Array.from(document.querySelectorAll(${JSON.stringify(selector)})).filter(node => node.offsetParent !== null).map(node => node.textContent.trim()))`)
const rowChips = (index, selector) => evaluate(`JSON.stringify(Array.from(${visibleRows}[${index}].querySelectorAll(${JSON.stringify(selector)})).map(node => node.textContent.trim()))`)
const rowCellText = (index, selector) => evaluate(`${visibleRows}[${index}].querySelector(${JSON.stringify(selector)}).textContent.trim()`)
const rowCellHtml = (index, selector) => evaluate(`${visibleRows}[${index}].querySelector(${JSON.stringify(selector)}).innerHTML`)

/** Wait for a page that has finished its first template load. */
const settlePage = async () => {
  for (let attempt = 0; attempt < 60; attempt++) {
    await sleep(50)
    const ready = await evaluate('window.__pageMounted === true && document.querySelectorAll(".whatsapp-row").length > 0')
    if (ready)
      return
  }
  throw new Error(`the WhatsApp page never rendered its rows: ${await evaluate('JSON.stringify({ bootError: window.bootError ?? null, rows: document.querySelectorAll(".whatsapp-row").length, consoleErrors: window.consoleErrors })')}`)
}

const settleMenu = async () => {
  for (let attempt = 0; attempt < 60; attempt++) {
    await sleep(50)
    const ready = await evaluate('window.__menuMounted === true && document.querySelectorAll(".v-overlay-container .v-list-item").length > 0')
    if (ready)
      return
  }
  throw new Error(`the quick-contact menu never rendered: ${await evaluate('JSON.stringify({ bootError: window.bootError ?? null, overlay: document.querySelector(".v-overlay-container")?.innerHTML?.slice(0, 600) ?? null, consoleErrors: window.consoleErrors })')}`)
}

try {
  for (const locale of ['en', 'fa-AF']) {
    const messages = JSON.parse(read(`plugins/i18n/locales/${locale}.json`))
    const whatsapp = messages.pages.whatsapp
    const crmUi = messages.common.crmUi
    const where = locale

    // ---------------------------------------------------------------------
    // 1. the page: one subpage per audience, each showing only its own rows
    // ---------------------------------------------------------------------
    await send('Page.navigate', { url: documents[`${locale}:page`] }, sessionId)
    await settlePage()

    assert.equal(await evaluate('window.bootError ?? null'), null, `the WhatsApp page failed to mount (${where})`)

    assert.deepEqual(
      JSON.parse(await texts('.v-tab')),
      [whatsapp.audiences.visitor, whatsapp.audiences.applicant, whatsapp.audiences.employee],
      `the page must offer one subpage per audience (${where})`,
    )

    const titles = () => visibleTexts('.whatsapp-row__title')

    assert.deepEqual(
      JSON.parse(await titles()),
      ['Visitor follow-up', 'Visit reminder', 'Retired visitor note'],
      `the visitor subpage must show the visitor templates only (${where})`,
    )

    const row = {
      labels: JSON.parse(await rowChips(0, '.whatsapp-row__labels .v-chip')),
      bubbleHtml: await rowCellHtml(0, '.whatsapp-row__bubble'),
      bubbleText: await rowCellText(0, '.whatsapp-row__bubble'),
      meta: await rowCellText(0, '.whatsapp-row__meta'),
      status: await rowCellText(2, '.whatsapp-row__actions .v-chip'),
    }

    assert.deepEqual(row.labels, ['follow-up', 'visitor'], `a row must show its labels (${where})`)
    // The bubble renders the message with sample values and WhatsApp markers:
    // `*{{name}}*` becomes bold sample text, not literal asterisks.
    assert.ok(row.bubbleHtml.includes('<strong>Sample Name</strong>'), `the row preview must render the bold marker (${where}): ${row.bubbleHtml}`)
    assert.ok(row.bubbleText.includes('Sample Counselor'), `the row preview must fill the placeholders (${where}): ${row.bubbleText}`)
    assert.ok(!row.bubbleText.includes('{{'), `the row preview must not show raw tokens (${where}): ${row.bubbleText}`)
    assert.ok(row.meta.includes('{{name}}') && row.meta.includes('{{counselor}}'), `a row must list the tokens it uses (${where}): ${row.meta}`)
    assert.equal(
      await evaluate('document.querySelector(".whatsapp-row__order")?.getAttribute("aria-label")'),
      whatsapp.orderValue.replace('{order}', '1'),
      `a row must expose its order (${where})`,
    )
    assert.equal(row.status, messages.common.statuses.inactive, `an inactive template must be marked inactive (${where})`)

    // Switching subpages swaps the rows; no other audience may leak in.
    await evaluate('document.querySelectorAll(".v-tab")[1].click()')
    await sleep(400)
    assert.deepEqual(JSON.parse(await titles()), ['Application status'], `the applicant subpage must show the applicant templates only (${where})`)
    // `innerText` skips the hidden subpages, so this is the text actually on screen.
    const applicantScreen = await evaluate('document.querySelector(".whatsapp-page__workspace").innerText')

    assert.ok(!applicantScreen.includes('Visitor follow-up'), `a visitor template must not appear on the applicant subpage (${where}): ${applicantScreen.slice(0, 300)}`)

    await evaluate('document.querySelectorAll(".v-tab")[2].click()')
    await sleep(400)
    assert.deepEqual(JSON.parse(await titles()), ['Employee welcome'], `the employee subpage must show the employee templates only (${where})`)

    // ---------------------------------------------------------------------
    // 2. the editor: plain text, WhatsApp markers, live preview
    // ---------------------------------------------------------------------
    await evaluate('document.querySelectorAll(".v-tab")[0].click()')
    await sleep(400)
    await evaluate('document.querySelectorAll(".whatsapp-row")[0].querySelector("button").click()')
    await sleep(500)

    assert.equal(await evaluate('window.bootError ?? null'), null, `the editor dialog failed to open (${where})`)
    assert.equal(await count('.whatsapp-editor'), 1, `the editor must open for the row (${where})`)

    // No rich-text surface: the message is a plain textarea (Vuetify's
    // auto-grow textarea renders its own measuring control alongside it).
    assert.ok(await count('.whatsapp-editor textarea') >= 1, `the editor message must be a textarea (${where})`)
    assert.equal(await count('.whatsapp-editor [contenteditable]'), 0, `the editor must expose no rich-text surface (${where})`)
    assert.equal(await count('.whatsapp-editor input[type="hidden"]'), 0, `the editor must hold no hidden rich-text model (${where})`)
    assert.equal(await count('.whatsapp-editor__toolbar .v-btn'), 5, `the toolbar needs four markers plus the field picker (${where})`)

    const initialMessage = await evaluate('document.querySelector(".whatsapp-editor textarea").value')

    assert.ok(initialMessage.includes('*{{name}}*'), `the editor must load the stored message (${where}): ${initialMessage}`)

    // The preview renders markers and fills the placeholders with sample data.
    const previewHtml = await evaluate('document.querySelector(".whatsapp-editor__bubble").innerHTML')

    assert.ok(previewHtml.includes('<strong>Sample Name</strong>'), `the editor preview must render the bold marker (${where}): ${previewHtml}`)
    assert.ok(previewHtml.includes('Sample Counselor'), `the editor preview must show sample values (${where}): ${previewHtml}`)

    // Selecting text and pressing the bold button wraps it in the marker.
    await evaluate(`(() => {
      const textarea = document.querySelector('.whatsapp-editor textarea');
      textarea.focus();
      textarea.setSelectionRange(0, 5);
      document.querySelectorAll('.whatsapp-editor__toolbar .v-btn')[0].click();
    })()`)
    await sleep(250)

    const wrapped = await evaluate('document.querySelector(".whatsapp-editor textarea").value')

    assert.ok(wrapped.startsWith('*Hello*'), `the toolbar must wrap the selection in the WhatsApp marker (${where}): ${wrapped}`)

    // Character counter tracks the WhatsApp limit.
    assert.ok(
      (await text('.whatsapp-editor__toolbar .v-chip')).includes('4096'),
      `the editor must show the WhatsApp character limit (${where}): ${await text('.whatsapp-editor__toolbar .v-chip')}`,
    )

    // The preview toggles between the audience's sample values and the message
    // exactly as written.
    await evaluate('document.querySelectorAll(".whatsapp-editor__preview-toggle")[1].click()')
    await sleep(250)

    const tokenPreview = await evaluate('document.querySelector(".whatsapp-editor__bubble").innerHTML')

    assert.ok(tokenPreview.includes('{{name}}'), `the preview must be able to show the tokens as written (${where}): ${tokenPreview}`)
    assert.ok(!tokenPreview.includes('Sample Name'), `the token preview must not fill the samples (${where}): ${tokenPreview}`)

    await evaluate('document.querySelectorAll(".whatsapp-editor__preview-toggle")[0].click()')
    await sleep(250)

    assert.ok(
      (await evaluate('document.querySelector(".whatsapp-editor__bubble").innerHTML')).includes('Sample Name'),
      `the preview must go back to the sample values (${where})`,
    )

    // Pasting rich content keeps only the plain-text flavour, which is the whole
    // of WhatsApp's vocabulary.
    await evaluate(`(() => {
      const textarea = document.querySelector('.whatsapp-editor textarea');
      const clipboard = new DataTransfer();
      clipboard.setData('text/html', '<b>Rich</b> paste');
      clipboard.setData('text/plain', 'Plain paste');
      textarea.focus();
      textarea.setSelectionRange(0, textarea.value.length);
      textarea.dispatchEvent(new ClipboardEvent('paste', { clipboardData: clipboard, bubbles: true, cancelable: true }));
    })()`)
    await sleep(250)

    assert.equal(
      await evaluate('document.querySelector(".whatsapp-editor textarea").value'),
      'Plain paste',
      `a rich paste must land as plain text (${where}): ${await evaluate('document.querySelector(".whatsapp-editor textarea").value')}`,
    )
    assert.ok(
      !(await evaluate('document.querySelector(".whatsapp-editor").innerHTML')).includes('<b>'),
      `no pasted markup may reach the editor (${where})`,
    )

    // ---------------------------------------------------------------------
    // 2b. editing and deleting through the page itself
    // ---------------------------------------------------------------------
    // Save the open dialog with a new title: the row must follow the API.
    await evaluate(`(() => {
      const dialog = document.querySelector('.v-overlay-container .v-dialog');
      const input = dialog.querySelector('input');
      input.value = 'Visitor follow-up (edited)';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      dialog.querySelector('.v-card-actions .v-btn:last-child').click();
    })()`)
    await sleep(400)

    assert.equal(
      await text('.whatsapp-editor'),
      null,
      `saving must close the editor (${where}): ${await text('.whatsapp-editor')}`,
    )

    const [update] = JSON.parse(await evaluate('JSON.stringify(window.whatsappWrites)'))

    assert.deepEqual(update, { method: 'update', id: 1, title: 'Visitor follow-up (edited)' }, `editing a row must save it through the API (${where})`)
    assert.ok(
      JSON.parse(await titles()).includes('Visitor follow-up (edited)'),
      `the edited row must show its new title (${where}): ${await titles()}`,
    )
    assert.ok(
      (await evaluate('JSON.stringify(window.toastSuccess)')).includes(whatsapp.updated),
      `a save must report its outcome (${where}): ${await evaluate('JSON.stringify(window.toastSuccess)')}`,
    )

    // Delete that row through its confirm dialog.
    await evaluate(`(() => {
      const row = Array.from(document.querySelectorAll('.whatsapp-row')).find(node => node.textContent.includes('Visitor follow-up (edited)'));
      const remove = Array.from(row.querySelectorAll('button')).find(button => button.querySelector('i.tabler-trash'));
      remove.click();
    })()`)
    await sleep(300)

    assert.ok(
      (await text('.v-overlay-container .v-dialog .v-card-title')) === messages.common.actions.delete,
      `deleting a row must ask for confirmation first (${where}): ${await text('.v-overlay-container .v-dialog .v-card-title')}`,
    )

    await evaluate(`document.querySelector('.v-overlay-container .v-dialog .v-card-actions .v-btn:last-child').click()`)
    await sleep(400)

    const writes = JSON.parse(await evaluate('JSON.stringify(window.whatsappWrites)'))

    assert.deepEqual(writes.at(-1), { method: 'delete', id: 1 }, `deleting a row must reach the API (${where})`)
    assert.ok(
      (await evaluate('JSON.stringify(window.toastSuccess)')).includes(whatsapp.deleted),
      `a delete must report its outcome (${where}): ${await evaluate('JSON.stringify(window.toastSuccess)')}`,
    )
    assert.deepEqual(
      JSON.parse(await titles()),
      ['Visit reminder', 'Retired visitor note'],
      `the deleted row must leave the list (${where}): ${await titles()}`,
    )
    assert.deepEqual(
      JSON.parse(await evaluate('JSON.stringify(window.toastErrors)')),
      [],
      `the edit/delete round trip must not report failures (${where})`,
    )

    // Add a template from the subpage's own add button.
    await evaluate(`Array.from(document.querySelectorAll('button')).find(button => button.textContent.trim() === ${JSON.stringify(whatsapp.addTemplate)}).click()`)
    await sleep(400)

    await evaluate(`(() => {
      const dialog = document.querySelector('.v-overlay-container .v-dialog');
      const title = dialog.querySelector('input');
      title.value = 'Fresh visitor note';
      title.dispatchEvent(new Event('input', { bubbles: true }));
      const message = dialog.querySelector('.whatsapp-editor textarea');
      message.value = 'Hello {{name}}, welcome back.';
      message.dispatchEvent(new Event('input', { bubbles: true }));
      dialog.querySelector('.v-card-actions .v-btn:last-child').click();
    })()`)
    await sleep(400)

    const [create] = JSON.parse(await evaluate('JSON.stringify(window.whatsappWrites)')).filter(write => write.method === 'create')

    assert.equal(create?.audience, 'visitor', `a new template belongs to the open subpage's audience (${where}): ${JSON.stringify(create)}`)
    assert.equal(create?.title, 'Fresh visitor note', `a new template must be saved with its title (${where}): ${JSON.stringify(create)}`)
    assert.equal(create?.message, 'Hello {{name}}, welcome back.', `a new template must be saved with its message (${where}): ${JSON.stringify(create)}`)
    assert.ok(
      JSON.parse(await titles()).includes('Fresh visitor note'),
      `the new row must appear on the subpage (${where}): ${await titles()}`,
    )
    assert.ok(
      (await evaluate('JSON.stringify(window.toastSuccess)')).includes(whatsapp.created),
      `a create must report its outcome (${where}): ${await evaluate('JSON.stringify(window.toastSuccess)')}`,
    )

    // ---------------------------------------------------------------------
    // 3. theming and mobile: no horizontal overflow
    // ---------------------------------------------------------------------
    await evaluate('window.testTheme("dark")')
    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true }, sessionId)
    await sleep(200)

    const mobile = JSON.parse(await evaluate(`JSON.stringify((() => {
      const box = document.querySelector('.v-tabs').getBoundingClientRect();

      return {
        page: document.documentElement.scrollWidth,
        viewport: window.innerWidth,
        tabsLeft: Math.round(box.left),
        tabsRight: Math.round(box.right),
      };
    })())`))

    // The subpage strip itself never exceeds the viewport; VTabs scrolls its own
    // items (show-arrows) when the three labels do not fit.
    assert.ok(mobile.page <= mobile.viewport + 2, `the page overflows on mobile (${where}): ${JSON.stringify(mobile)}`)
    assert.ok(mobile.tabsLeft >= -1 && mobile.tabsRight <= mobile.viewport + 1, `the subpage strip must stay inside the viewport on mobile (${where}): ${JSON.stringify(mobile)}`)

    await send('Emulation.clearDeviceMetricsOverride', {}, sessionId)
    await evaluate('window.testTheme("light")')

    // ---------------------------------------------------------------------
    // 4. the quick-contact menu: this page's audience only, row-filled link
    // ---------------------------------------------------------------------
    await send('Page.navigate', { url: documents[`${locale}:menu`] }, sessionId)
    await settleMenu()

    assert.equal(await evaluate('window.bootError ?? null'), null, `the quick-contact menu failed to mount (${where})`)

    const menuTitles = JSON.parse(await texts('.v-overlay-container .v-list-item-title'))
    const expectedTemplateTitles = ['Visitor follow-up', 'Visit reminder']

    assert.ok(
      expectedTemplateTitles.every(title => menuTitles.includes(title)),
      `the menu must offer this audience's active templates (${where}): ${JSON.stringify(menuTitles)}`,
    )
    assert.ok(
      !menuTitles.includes('Retired visitor note'),
      `the menu must not offer an inactive template (${where}): ${JSON.stringify(menuTitles)}`,
    )
    assert.ok(
      !menuTitles.some(title => ['Application status', 'Employee welcome'].includes(title)),
      `the menu must not offer another audience's templates (${where}): ${JSON.stringify(menuTitles)}`,
    )
    assert.equal(
      await text('.v-overlay-container .v-list-subheader'),
      whatsapp.quickContactHeading,
      `the menu must label the template section (${where}): ${await text('.v-overlay-container .v-list-subheader')}`,
    )

    // Choosing a template fills the placeholders from the row and opens wa.me.
    // A single click on the entry: clicking its children too would bubble and
    // open one window per element.
    const clicked = await evaluate(`(() => {
      const item = Array.from(document.querySelectorAll('.v-overlay-container .v-list-item')).find(node => node.textContent.includes('Visitor follow-up'));
      if (!item) return false;
      item.click();
      return true;
    })()`)

    assert.ok(clicked, `the template entry must be clickable (${where})`)
    await sleep(300)

    const opened = await evaluate('window.opened')

    assert.equal(opened.length, 1, `choosing a template must open WhatsApp exactly once (${where}): ${JSON.stringify(opened)}`)

    const url = String(opened[0])

    assert.ok(url.startsWith('https://wa.me/93700555777?text='), `the link must target the row phone (${where}): ${url}`)

    const decoded = decodeURIComponent(url.split('?text=')[1])

    assert.ok(decoded.includes('*Row Visitor*'), `the message must fill the row's name (${where}): ${decoded}`)
    assert.ok(decoded.includes('Test offer'), `the message must fill the row's service (${where}): ${decoded}`)
    assert.ok(decoded.includes('Row Counselor'), `the message must fill a nested field (${where}): ${decoded}`)
    assert.ok(!decoded.includes('{{') && !decoded.includes('Sample'), `the sent message must contain no tokens or sample data (${where}): ${decoded}`)

    // The CRM dialog opens after the link, as the other contact actions do, with
    // the conversation already named after the template.
    await sleep(300)

    const dialog = JSON.parse(await evaluate(`JSON.stringify({
      title: document.querySelector('.v-overlay-container .v-dialog .v-card-title')?.textContent?.trim() ?? '',
      fields: Array.from(document.querySelectorAll('.v-overlay-container .v-dialog input, .v-overlay-container .v-dialog textarea')).map(field => field.value),
    })`))

    assert.equal(dialog.title, crmUi.logConversation, `the template must still record a conversation (${where}): ${JSON.stringify(dialog)}`)
    assert.ok(
      dialog.fields.some(value => value.includes('Visitor follow-up')),
      `the conversation title must be pre-filled from the template (${where}): ${JSON.stringify(dialog)}`,
    )
  }
}
finally {
  await send('Browser.close').catch(() => {})
  socket.close()
  chrome.kill()
}

assert.deepEqual(errors, [], `console errors: ${JSON.stringify(errors)}`)

console.log('whatsapp templates regression: ok (subpages per audience, row preview, plain-text editor with markers, create/edit/delete round trip, quick-contact audience filter and wa.me prefill)')
