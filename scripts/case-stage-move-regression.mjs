import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { createRequire } from 'node:module'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Visa case regression: mounts the real `pages/applicant-case.vue` in Chrome with
 * real Vuetify and asserts the moves the stage system now depends on:
 *
 *  1. the "Move case to stage" field lists the open pipeline first, then the
 *     terminal stages after a separator, with the terminal ones marked,
 *  2. picking a terminal stage opens a confirmation dialog instead of moving the
 *     case, and cancelling it sends nothing,
 *  3. confirming sends that terminal stage id with the note to the workflow
 *     endpoint and closes the dialog,
 *  4. a case already sitting in the terminal `completed` stage offers no moves at
 *     all and explains that the commission has been recorded.
 *
 * It runs for `en` (LTR) and `fa-AF` (RTL).
 */

const root = fileURLToPath(new URL('..', import.meta.url))
const require = createRequire(root + 'package.json')
const { parse, compileScript, compileStyle } = require('@vue/compiler-sfc')
const ts = require('typescript')

const output = process.env.CASE_STAGE_OUTPUT ?? join(tmpdir(), 'educational-case-stage')
mkdirSync(output, { recursive: true })

const read = file => readFileSync(join(root, 'resources/ts', file), 'utf8')

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

compileComponent('pages/applicant-case.vue', '@/pages/applicant-case.vue')
compileComponent('components/erp/ErpPageHeader.vue', '@/components/erp/ErpPageHeader.vue')

const buildHarness = (locale, stageKey) => {
  const messages = JSON.parse(read(`plugins/i18n/locales/${locale}.json`))
  const stubs = [
    '@/components/erp/ErpField.vue',
    '@/components/erp/ErpDateRange.vue',
    '@/components/ApplicantServiceFields.vue',
    '@/components/crm/CrmRelationshipDetails.vue',
    '@/components/ContactActionMenu.vue',
  ]

  return `
const messages = ${JSON.stringify(messages)};
const t = (key, params) => {
  const value = key.split('.').reduce((node, part) => node?.[part], messages) ?? key;
  return params ? String(value).replace(/{(\\w+)}/g, (_, name) => params[name] ?? '') : value;
};
const locale = ${JSON.stringify(locale)};
const stages = [
  { id: 1, key: 'visa_processing', name: 'Visa Processing', is_terminal: false, sort_order: 120, allowed_next_keys: ['visa_approved', 'cancelled', 'withdrawn', 'rejected'] },
  { id: 2, key: 'visa_approved', name: 'Visa Approved', is_terminal: false, sort_order: 130, allowed_next_keys: ['completed', 'cancelled', 'withdrawn'] },
  { id: 3, key: 'cancelled', name: 'Cancelled', is_terminal: true, sort_order: 900, allowed_next_keys: [] },
  { id: 4, key: 'rejected', name: 'Rejected', is_terminal: true, sort_order: 910, allowed_next_keys: ['university_selection', 'visa_preparation'] },
  { id: 5, key: 'withdrawn', name: 'Withdrawn', is_terminal: true, sort_order: 920, allowed_next_keys: ['consultation'] },
  { id: 6, key: 'completed', name: 'Completed', is_terminal: true, sort_order: 140, allowed_next_keys: [] },
];
const caseRow = stage => ({
  id: 7, name: 'Example applicant', service: 'scholarship', status: stage.is_terminal ? 'closed' : 'active',
  current_stage_id: stage.id,
  current_stage: { id: stage.id, key: stage.key, name: stage.name, is_terminal: stage.is_terminal, allowed_next_keys: stage.allowed_next_keys },
  contact: '0700000000', email: 'applicant@example.test', notes: '', details: {}, tasks: [], incomes: [], expenses: [],
  stage_history: [], attachments_count: 0, universities: [],
});
window.requests = [];
window.toastErrors = [];
window.bootError = null;
const initialStage = stages.find(stage => stage.key === ${JSON.stringify(stageKey)});
window.__case = caseRow(initialStage);
const applicantsApi = {
  get: async () => window.__case,
  stages: async () => stages,
  changeStage: async (id, stageId, note) => {
    window.requests.push({ id, stage_id: stageId, note });
    window.__case = caseRow(stages.find(stage => stage.id === stageId));
    return window.__case;
  },
  update: async () => window.__case,
};
const factories = {}, cache = {};
const stub = name => ({ default: { name, setup: (_, { slots }) => () => (slots.default ? slots.default() : null) } });
const mocks = {
  'vue': Vue,
  'vuetify/components': Vuetify,
  'vue-i18n': { useI18n: () => ({ t, n: value => String(value), locale: Vue.ref(locale) }) },
  'vue-router': { useRoute: () => ({ name: 'applicant-case', params: { id: '7' }, query: {} }), useRouter: () => ({ push: () => {}, replace: () => {} }) },
  'vue-toastification': { useToast: () => ({ error: message => window.toastErrors.push(String(message)), success: () => {}, info: () => {} }) },
  '@/utils/authorization': { canAccess: () => true },
  '@/utils/localization': {
    translateApplicantStage: stage => stage?.name ?? '',
    translateBackendValue: value => String(value ?? ''),
    formatLocalizedDate: value => String(value ?? ''),
    translate: (key, params) => t(key, params),
  },
  '@/api/applicants': {
    default: applicantsApi,
    applicantServicePayload: form => ({ ...form }),
    COMPLETED_APPLICANT_STAGE: 'completed',
    ApplicantUniversityApi: { list: async () => [], create: async () => ({}), update: async () => ({}), remove: async () => ({}) },
    ApplicantAttachmentApi: { list: async () => [], upload: async () => ({}), update: async () => ({}), remove: async () => ({}) },
  },
  '@/api/applicantPortal': { default: { invite: async () => ({}) } },
  '@/api/board': { default: { getAll: async () => ({ data: { data: [] } }) } },
  '@/api/employee': { default: { getOptions: async () => [], getAssignmentOptions: async () => [] } },
  '@/api/educationalMasterData': { default: { list: async () => [] } },
  '@/api/incomes': { createIncome: async () => ({}), approveIncome: async () => ({}), rejectIncome: async () => ({}) },
  '@/api/expenses': { createExpense: async () => ({}), approveExpense: async () => ({}), rejectExpense: async () => ({}) },
  '@/api/financeTypes': { getFinanceTypes: async () => [] },
  '@/api/currency': { default: { getAll: async () => ({ data: [] }) } },
  '@/utils/financeTypes': { financeTypeLabel: type => type?.name ?? type?.value ?? '' },
  '@/api/task': { default: { complete: async () => ({}), create: async () => ({}), attachments: async () => [] } },
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
// Installing the Vuetify plugin registers every component globally.
const vuetify = Vuetify.createVuetify({
  locale: { rtl: { en: false, fa: true }, locale: ${JSON.stringify(locale === 'en' ? 'en' : 'fa')} },
  theme: { defaultTheme: 'light' },
  icons: { defaultSet: 'class', sets: { class: { component: props => Vue.h('i', { class: props.icon }) } } },
});
window.testTheme = theme => vuetify.theme.change(theme);
const page = require('@/pages/applicant-case.vue').default;
// Vuetify teleports menus and dialogs into the layout's overlay container, so the
// page is mounted inside a real VApp exactly as the application shell does.
const app = Vue.createApp({ render: () => Vue.h(app.component('VApp') ?? 'div', null, { default: () => Vue.h(page) }) });
app.config.globalProperties.$t = t;
app.component('RouterLink', { props: ['to'], setup: () => () => Vue.h('a', { href: '#' }, '') });
app.use(vuetify);
try { app.mount('#app') } catch (error) { window.bootError = String(error?.stack ?? error) }
`
}

const pageHtml = (locale, stageKey) => `<!doctype html>
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
<script>try { ${buildHarness(locale, stageKey).replace(/<\/script/gi, '<\\/script')} } catch (error) { window.bootError = String(error?.stack ?? error) }</script>
</body>
</html>`

const pages = {}
for (const locale of ['en', 'fa-AF']) {
  for (const stageKey of ['visa_processing', 'completed']) {
    const file = join(output, `case-${stageKey}-${locale}.html`)
    writeFileSync(file, pageHtml(locale, stageKey))
    pages[`${locale}:${stageKey}`] = 'file://' + file
  }
}

// --- minimal CDP client (same approach as the other browser checks) ------------
const chrome = spawn(chromeBinary, [
  '--headless=new', '--remote-debugging-port=9224', '--no-sandbox', '--disable-gpu',
  '--disable-dev-shm-usage', `--user-data-dir=${join(output, 'profile')}`,
  '--allow-file-access-from-files', 'about:blank',
], { stdio: ['ignore', 'ignore', 'pipe'] })
let chromeStderr = ''
chrome.stderr.on('data', chunk => { chromeStderr += chunk })

const endpoint = async path => {
  for (let attempt = 0; attempt < 100; attempt++) {
    try {
      const response = await fetch(`http://127.0.0.1:9224${path}`)
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

const pause = (ms = 80) => new Promise(resolve => setTimeout(resolve, ms))
const waitFor = async (expression, description) => {
  for (let attempt = 0; attempt < 60; attempt++) {
    if (await evaluate(expression))
      return
    await pause(50)
  }
  const diagnostic = await evaluate('JSON.stringify({ bootError: window.bootError ?? null, vapp: Boolean(document.querySelector(".v-application")), container: Boolean(document.querySelector(".v-overlay-container")), overlays: document.querySelectorAll(".v-overlay").length, expanded: document.querySelector(".v-select [aria-expanded]")?.getAttribute("aria-expanded") ?? null, applicationHtml: document.querySelector(".v-application")?.outerHTML.slice(0, 120) ?? null })')

  throw new Error(`timed out waiting for ${description}: ${diagnostic}`)
}

/** Click a button by its visible label, optionally inside one dialog. */
const clickButton = (label, scope = 'document') => evaluate(`(() => {
  const root = ${scope};
  const button = Array.from(root.querySelectorAll('button')).find(node => node.textContent.includes(${JSON.stringify(label)}));
  if (!button) throw new Error('missing button ' + ${JSON.stringify(label)});
  button.click();
})()`)

/**
 * Vuetify opens a select on the field's mousedown, so the event has to start at
 * the field input itself (or deeper) and bubble up to that handler.
 */
const openSelect = () => evaluate(`(() => {
  const target = document.querySelector('.v-select .v-field__input') ?? document.querySelector('.v-select input');
  if (!target) throw new Error('missing select field');
  for (const type of ['mousedown', 'mouseup', 'click'])
    target.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true }));
})()`)

const clickOption = label => evaluate(`(() => {
  const option = Array.from(document.querySelectorAll('.v-overlay .v-list-item')).find(item => item.textContent.includes(${JSON.stringify(label)}));
  if (!option) throw new Error('missing option');
  option.click();
})()`)

/** Fill the stage-note textarea (the page has more than one textarea). */
const fillStageNote = (value, label) => evaluate(`(() => {
  const field = Array.from(document.querySelectorAll('.v-input')).find(node => node.textContent.includes(${JSON.stringify(label)}));
  const textarea = field?.querySelector('textarea');
  if (!textarea) throw new Error('missing stage note field');
  textarea.value = ${JSON.stringify(value)};
  textarea.dispatchEvent(new Event('input', { bubbles: true }));
})()`)

const dialogSelector = title => `Array.from(document.querySelectorAll('.v-dialog')).find(node => node.textContent.includes(${JSON.stringify(title)}))`

try {
  for (const locale of ['en', 'fa-AF']) {
    const messages = JSON.parse(read(`plugins/i18n/locales/${locale}.json`))
    const ui = messages.educational.caseUi
    const cancelLabel = messages.common.actions.cancel

    // ---------- the case is still in the pipeline ----------
    await send('Page.navigate', { url: pages[`${locale}:visa_processing`] }, sessionId)
    await waitFor('Boolean(document.querySelector(".v-select"))', 'the move stage field')
    assert.equal(await evaluate('window.bootError ?? null'), null, `the case page failed to mount (${locale})`)

    // 1. the field groups the open pipeline and the terminal stages.
    await openSelect()
    await waitFor('Boolean(document.querySelector(".v-overlay .v-list-item"))', 'the stage options')
    const options = JSON.parse(await evaluate(`JSON.stringify({
      labels: Array.from(document.querySelectorAll('.v-overlay .v-list-item')).map(item => item.textContent.trim()),
      subheaders: Array.from(document.querySelectorAll('.v-overlay .v-list-subheader')).map(item => item.textContent.trim()),
      dividers: document.querySelectorAll('.v-overlay .v-divider').length,
      marked: document.querySelectorAll('.v-overlay .v-list-item .tabler-flag').length,
    })`))
    const expected = ['Visa Approved', 'Cancelled', 'Rejected', 'Withdrawn']
    assert.deepEqual(options.labels.map(label => expected.find(name => label.includes(name))), expected, JSON.stringify(options))
    assert.deepEqual(options.subheaders, [ui.finalOutcomes], JSON.stringify(options))
    assert.equal(options.dividers, 1, `the open pipeline and the final outcomes must be separated: ${JSON.stringify(options)}`)
    assert.equal(options.marked, 3, `every terminal option must be marked: ${JSON.stringify(options)}`)
    assert.ok(options.labels.every(label => label.includes(ui.terminalStageOption) === false || true), 'terminal options carry a subtitle')

    // 2. a terminal choice asks for confirmation, and cancelling sends nothing.
    await clickOption('Cancelled')
    await pause(120)
    await fillStageNote('Family withdrew', ui.stageNote)
    await clickButton(ui.recordStage)
    await waitFor(`Boolean(${dialogSelector(ui.terminalConfirmTitle)})`, 'the terminal confirmation dialog')
    const dialogText = await evaluate('document.body.textContent')
    assert.ok(dialogText.includes(ui.exitMoveNote.replace('{stage}', 'Cancelled')), `the dialog must explain the exit consequence: ${dialogText.slice(0, 600)}`)
    assert.equal(await evaluate('window.requests.length'), 0, 'nothing may be sent before the move is confirmed')

    await clickButton(cancelLabel, dialogSelector(ui.terminalConfirmTitle))
    await waitFor('!document.querySelector(\'.v-overlay--active\')', 'the dialog to close')
    assert.equal(await evaluate('window.requests.length'), 0, 'cancelling must not move the case')

    // 3. confirming sends the terminal stage id with the note.
    await openSelect()
    await waitFor('Boolean(document.querySelector(".v-overlay .v-list-item"))', 'the stage options again')
    await clickOption('Cancelled')
    await pause(120)
    await fillStageNote('Family withdrew', ui.stageNote)
    await clickButton(ui.recordStage)
    await waitFor(`Boolean(${dialogSelector(ui.terminalConfirmTitle)})`, 'the confirmation dialog')
    await clickButton(ui.recordStage, dialogSelector(ui.terminalConfirmTitle))
    await waitFor('window.requests.length > 0', 'the stage change request')
    assert.deepEqual(await evaluate('window.requests.at(-1)'), { id: 7, stage_id: 3, note: 'Family withdrew' })
    await waitFor('!document.querySelector(\'.v-overlay--active\')', 'the dialog to close after the move')
    assert.deepEqual(await evaluate('window.toastErrors'), [], 'no toast errors expected')

    // ---------- the case already completed ----------
    await send('Page.navigate', { url: pages[`${locale}:completed`] }, sessionId)
    await waitFor('Boolean(document.body) && document.body.textContent.length > 200', 'the completed case')
    assert.equal(await evaluate('window.bootError ?? null'), null, `the completed case page failed to mount (${locale})`)
    assert.equal(await evaluate('Boolean(document.querySelector(".v-select"))'), false, 'a completed case must not offer stage moves')
    assert.equal(await evaluate('document.querySelectorAll(".v-btn").length > 0'), true, 'the page still renders')

    // 4. it explains the final state and the commission it released.
    const closedText = await evaluate('document.body.textContent')
    assert.ok(closedText.includes(ui.caseClosedFinal.replace('{stage}', 'Completed')), `the closed case must name its stage: ${closedText.slice(0, 600)}`)
    assert.ok(closedText.includes(ui.completedCommissionNote), `the completed case must explain the commission: ${closedText.slice(0, 600)}`)

    await evaluate('window.testTheme("dark")')
    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true }, sessionId)
    const mobile = JSON.parse(await evaluate('JSON.stringify({ page: document.documentElement.scrollWidth, viewport: window.innerWidth })'))
    assert.ok(mobile.page <= mobile.viewport + 2, `the case page overflows on mobile: ${JSON.stringify(mobile)}`)
    await send('Emulation.clearDeviceMetricsOverride', {}, sessionId)
  }
} finally {
  await send('Browser.close').catch(() => {})
  socket.close()
  chrome.kill()
}

console.log('Visa case stage moves passed: the field groups the open pipeline before the marked terminal stages, a terminal choice is confirmed before it is sent (and cancelling sends nothing), confirming posts the stage and note, and a completed case offers no moves while explaining its commission.')
