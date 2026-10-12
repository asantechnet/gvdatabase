import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { access, copyFile, mkdir, readFile, writeFile } from 'node:fs/promises'
import { createServer } from 'node:http'
import { spawn } from 'node:child_process'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import ts from 'typescript'
import { parse, compileScript, compileStyle } from '@vue/compiler-sfc'

/**
 * Contract print regression: mounts the real `ContractDocumentPrinter` in
 * Chrome, prints through the real `printDocument`, and measures the print-media
 * result for three artwork states. The document must always end up with exactly
 * one cover page and one letterhead — the configured artwork when it loads, and
 * the built-in cover/letterhead chrome when it does not.
 */

const require = createRequire(import.meta.url)
const root = new URL('../', import.meta.url)
const read = file => readFile(new URL(file, root), 'utf8')
const output = join(tmpdir(), 'educational-contract-print')
await mkdir(output, { recursive: true })

const chromeBinary = process.env.CHROME_BIN ?? ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser'].find(candidate => {
  try {
    require('node:child_process').execSync(`command -v ${candidate}`, { stdio: 'ignore' })

    return true
  }
  catch {
    return false
  }
}) ?? 'google-chrome'

const compile = source => ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText

const messages = JSON.parse(await read('resources/ts/plugins/i18n/locales/en.json'))
const t = key => key.split('.').reduce((value, part) => value?.[part], messages) ?? key

// --- compile the real components -------------------------------------------------
let css = ''
const componentSources = {}

const compileComponent = async (file, scopeId) => {
  const { descriptor, errors } = parse(await read(file), { filename: file })
  assert.deepEqual(errors, [])
  componentSources[scopeId] = compile(compileScript(descriptor, { id: scopeId, inlineTemplate: true }).content)
  for (const style of descriptor.styles) {
    const result = compileStyle({ source: style.content, id: scopeId, scoped: style.scoped })
    assert.deepEqual(result.errors, [])
    assert.ok(!result.code.includes('@page'), `${file} must never declare @page`)
    css += `\n${result.code}`
  }
}

await compileComponent('resources/ts/pages/templates/contract_cover_template.vue', 'data-v-cover')
await compileComponent('resources/ts/pages/templates/contract_page_template.vue', 'data-v-page')
await compileComponent('resources/ts/components/contract/ContractPrintHeader.vue', 'data-v-header')
await compileComponent('resources/ts/components/contract/ContractDocumentPrinter.vue', 'data-v-printer')
css += `\n${require('sass').compileString(await read('resources/styles/styles.scss')).css}`

const printDocumentSource = compile(await read('resources/ts/utils/printDocument.ts'))
const printArtworkSource = compile(await read('resources/ts/utils/printArtwork.ts'))

const buildHarness = () => {
  const loader = (scopeId, source) => `(function () {
    const module = { exports: {} };
    const require = name => {
      if (name === 'vue') return Vue;
      const dependency = window.__modules[name];
      return typeof dependency === 'function' ? dependency() : dependency;
    };
    (function (require, module, exports, useI18n, translate, formatLocalizedDate) {
${source}
    })(require, module, module.exports, __useI18n, __translate, __formatLocalizedDate);
    if (module.exports.default) module.exports.default.__scopeId = ${JSON.stringify(scopeId)};
    return module.exports.default;
  })()`

  const modules = Object.entries(componentSources)
    .map(([id, source]) => `__components[${JSON.stringify(id)}] = ${loader(id, source)};`)
    .join('\n')

  return `
const __messages = ${JSON.stringify(messages)};
const __translate = key => key.split('.').reduce((value, part) => value?.[part], __messages) ?? key;
const __locale = Vue.ref('en');
const __useI18n = () => ({ t: __translate, locale: __locale });
const __formatLocalizedDate = value => String(value ?? '');
window.__state = {
  contract: {
    id: 1, contract_number: 'CON-1', contract: 'Scholarship', counterparty: 'Applicant One',
    status: 'active', start_date: '2026-01-01', cover_text: '', content: '<p>Body</p>',
    snapshot_data: null, template_id: null,
  },
  template: { id: 1, name: 'Scholarship', type: 'customer', cover_text: '', content: '' },
  setting: {
    original_company_name: 'Example Firm', website_logo_url: null, company_signature_url: null,
    contract_letterhead_url: null, contract_cover_design_url: null,
    physical_address: 'Kabul', phone: '0700', company_email: 'a@b.c', website_address: 'example.test',
  },
  toasts: [],
};
const __state = window.__state;
const __components = {};
const __module = (source, require) => {
  const module = { exports: {} };
  new Function('require', 'module', 'exports', source)(require, module, module.exports);
  return module.exports;
};
const __printDocumentModule = __module(${JSON.stringify(printDocumentSource)}, name => (name === 'vue' ? Vue : undefined));
const __printArtworkModule = __module(${JSON.stringify(printArtworkSource)}, name => (name === 'vue' ? Vue : undefined));
window.__modules = {
  'vue-i18n': { useI18n: () => ({ t: __translate, locale: __locale }) },
  'vue-toastification': { useToast: () => ({ info: message => __state.toasts.push(message), warning: () => {}, error: () => {}, success: () => {} }) },
  '@/utils/printDocument': __printDocumentModule,
  '@/utils/printArtwork': __printArtworkModule,
  '@/api/contracts': { default: { getById: async () => __state.contract } },
  '@/api/contractTemplate': { default: { getById: async () => __state.template } },
  '@/api/setting': { default: { getCompanyProfile: async () => ({ success: true, data: __state.setting }) } },
  '@/utils/contractPlaceholders': { legacyContentToHtml: value => value ?? '' },
  '@/utils/contractPartyDetails': { canPrintPartyDetails: () => false, partyDetailsFor: () => ({ ourCompany: [], counterparty: [] }) },
  '@/composables/useContractPrintHeader': { buildContractPrintHeader: () => null, canPrintContractHeader: () => false },
  '@/pages/templates/contract_cover_template.vue': { get default() { return __components['data-v-cover'] } },
  '@/pages/templates/contract_page_template.vue': { get default() { return __components['data-v-page'] } },
  '@/components/contract/ContractPrintHeader.vue': { get default() { return __components['data-v-header'] } },
};
${modules}
const __slotStub = name => ({ name, setup: (_, { slots }) => () => slots.default?.() ?? null });
window.__open = Vue.ref(true);
const __app = Vue.createApp({ render: () => Vue.h(__components['data-v-printer'], { modelValue: window.__open.value, contract: __state.contract }) });
for (const name of ['VDialog', 'VCard', 'VCardTitle', 'VCardText', 'VCardActions', 'VBtn']) __app.component(name, __slotStub(name));
__app.component('VCheckbox', { props: ['modelValue', 'label'], setup: () => () => null });
__app.component('VIcon', { setup: () => () => null });
__app.config.globalProperties.$t = __translate;
__app.config.globalProperties.$i18n = { locale: 'en' };
__app.mount('#app');

window.__hiddenBody = () => {
  const candidates = Array.from(document.querySelectorAll('#app div'))
    .filter(node => node.querySelector('.contract-cover, .custom-cover-page'))

  return candidates.at(-1) ?? null;
};
window.__print = () => Vue.nextTick().then(() => __printDocumentModule.printDocument({
  bodyClass: 'printing-contract-document',
  pageStyle: 'size: A4; margin: 0;',
  element: window.__hiddenBody(),
}));
window.__reopen = async patch => {
  __state.setting = { ...__state.setting, ...patch };
  __state.toasts = [];
  window.__open.value = false;
  await Vue.nextTick();
  window.__open.value = true;
  await Vue.nextTick();
  await new Promise(resolve => setTimeout(resolve, 50));

  return Boolean(window.__hiddenBody());
};
`
}

const page = `<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<title>Contract print harness</title>
<style>* { box-sizing: border-box; } body { font-family: Arial, sans-serif; margin: 0; }
.layout-wrapper { padding: 40px; min-height: 100vh; background: #eef; }</style>
<style>${css}</style>
<!--
  Faithful copy of Vuetify's VOverlay scroll-lock rules (node_modules/vuetify/lib/components/VOverlay/VOverlay.css).
  Every print path opens a real VDialog, so the app runs these rules while the print job is prepared. Without them
  the harness cannot reproduce the pinned <html>/clipped <body> state that truncates the print to one page.
-->
<style>
.v-overlay-scroll-blocked { padding-inline-end: var(--v-scrollbar-offset); }
.v-overlay-scroll-blocked:not(html) { overflow-y: hidden !important; }
html.v-overlay-scroll-blocked { position: fixed; top: var(--v-body-scroll-y); left: var(--v-body-scroll-x); width: 100%; height: 100%; }
</style>
</head>
<body>
<nav>UNWANTED-NAVIGATION</nav>
<header>UNWANTED-APP-HEADER</header>
<div class="layout-wrapper"><main id="app"></main></div>
<script src="/vue.global.js"><\/script>
<script>try { (0, eval)(${JSON.stringify(buildHarness()).replace(/<\/script/gi, '<\\/script')}) } catch (error) { window.__bootError = String((error && error.stack) || error) }<\/script>
</body>
</html>`

await writeFile(join(output, 'index.html'), page)
try {
  await access(new URL('../node_modules/vue/dist/vue.global.js', import.meta.url))
}
catch {
  throw new Error('node_modules/vue/dist/vue.global.js is missing — install dependencies before running this check')
}
await copyFile(new URL('../node_modules/vue/dist/vue.global.js', import.meta.url), join(output, 'vue.global.js'))

// --- minimal CDP client ----------------------------------------------------------
const chrome = spawn(chromeBinary, [
  '--headless=new', '--remote-debugging-port=9222', '--no-sandbox', '--disable-gpu',
  '--disable-dev-shm-usage', `--user-data-dir=${join(output, 'profile')}`,
  '--allow-file-access-from-files', 'about:blank',
], { stdio: ['ignore', 'ignore', 'pipe'] })
let chromeStderr = ''
chrome.stderr.on('data', chunk => { chromeStderr += chunk })

const server = createServer(async (request, response) => {
  const name = request.url === '/' ? 'index.html' : request.url.slice(1).split('?')[0]
  try {
    const body = await readFile(join(output, name))
    response.writeHead(200, { 'Content-Type': name.endsWith('.js') ? 'text/javascript' : 'text/html' })
    response.end(body)
  }
  catch {
    response.writeHead(404)
    response.end('not found')
  }
})
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
const port = server.address().port

const endpoint = async path => {
  for (let attempt = 0; attempt < 100; attempt++) {
    try {
      const response = await fetch(`http://127.0.0.1:9222${path}`)
      if (response.ok)
        return response.json()
    }
    catch { /* chrome is still starting */ }
    await new Promise(resolve => setTimeout(resolve, 100))
  }
  throw new Error(`Chrome DevTools never became available on port 9222. Set CHROME_BIN if ${chromeBinary} is not the right binary.\n${chromeStderr}`)
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

await send('Page.enable', {}, sessionId)
await send('Page.navigate', { url: `http://127.0.0.1:${port}/` }, sessionId)
for (let attempt = 0; attempt < 100 && !(await evaluate('Boolean(window.__hiddenBody?.())')); attempt++)
  await new Promise(resolve => setTimeout(resolve, 100))
const bootError = await evaluate('window.__bootError ?? null')
assert.equal(bootError, null, `the printer failed to mount: ${bootError}`)
assert.equal(await evaluate('Boolean(window.__hiddenBody?.())'), true, 'the printer never rendered its print body')

await send('Emulation.setEmulatedMedia', { media: 'print' }, sessionId)

// The print dialog is a Vuetify overlay, which pins <html> to the viewport for
// as long as it is open. Reproduce that state (the previous harness never did)
// and measure the print output through Chrome's own PDF pipeline.
const pdfPageCount = async () => {
  const { data } = await send('Page.printToPDF', { printBackground: true, preferCSSPageSize: true }, sessionId)
  const raw = Buffer.from(data, 'base64').toString('latin1')

  return (raw.match(/\/Type\s*\/Page[^s]/g) ?? []).length
}

const measure = async label => {
  const result = await evaluate(`(async () => {
    document.documentElement.classList.add('v-overlay-scroll-blocked');
    document.body.classList.add('v-overlay-scroll-blocked');
    document.documentElement.style.setProperty('--v-scrollbar-offset', '15px');
    document.documentElement.style.setProperty('--v-body-scroll-x', '0px');
    document.documentElement.style.setProperty('--v-body-scroll-y', '0px');
    await window.__print();
    const host = document.querySelector('#app-print-host');
    if (!host) return { error: 'no print host' };
    const cover = host.querySelector('.contract-cover');
    const customCover = host.querySelector('.custom-cover-page');
    const customCoverImage = host.querySelector('.custom-cover-page img');
    const page = host.querySelector('.contract-page-container');
    const letterhead = host.querySelector('.letterhead-background');
    const surface = element => element ? { width: Math.round(element.getBoundingClientRect().width), height: Math.round(element.getBoundingClientRect().height) } : null;
    return {
      pages: host.querySelectorAll('.print-page').length,
      builtInCover: Boolean(cover),
      customCover: Boolean(customCover),
      customCoverUsable: customCoverImage ? customCoverImage.naturalWidth > 0 : null,
      contentPage: Boolean(page),
      builtInHeader: Boolean(host.querySelector('.contract-header')),
      builtInFooter: Boolean(host.querySelector('.contract-footer-wrapper')),
      letterhead: Boolean(letterhead),
      letterheadUsable: letterhead ? letterhead.naturalWidth > 0 : null,
      letterheadSurface: surface(letterhead),
      coverSurface: surface(cover ?? customCover),
      pageBreak: cover ? getComputedStyle(cover).breakAfter : null,
      applicationChromeHidden: getComputedStyle(document.querySelector('nav')).display === 'none',
      dialogText: document.querySelector('#app > *') ? document.querySelector('#app').textContent.replace(/\\s+/g, ' ').slice(0, 200) : null,
      htmlPosition: getComputedStyle(document.documentElement).position,
      htmlHeight: getComputedStyle(document.documentElement).height,
      htmlPaddingInlineEnd: getComputedStyle(document.documentElement).paddingInlineEnd,
      viewportHeight: window.innerHeight,
      direction: document.documentElement.dir,
      toasts: window.__state.toasts,
    };
  })()`)
  // Chrome's print pipeline lays out exactly what window.print() would.
  result.pdfPages = await pdfPageCount()
  await evaluate('document.documentElement.classList.remove("v-overlay-scroll-blocked"); document.body.classList.remove("v-overlay-scroll-blocked"); document.documentElement.style.removeProperty("--v-scrollbar-offset"); document.documentElement.style.removeProperty("--v-body-scroll-x"); document.documentElement.style.removeProperty("--v-body-scroll-y")')
  await evaluate('window.dispatchEvent(new Event("afterprint"))')
  console.log(`--- ${label} ---`)
  console.log(JSON.stringify(result))

  return result
}

const artwork = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100"><rect width="200" height="100" fill="#26308e"/></svg>')
const missing = name => `http://127.0.0.1:${port}/missing-${name}.png`

await evaluate('window.__reopen({ contract_letterhead_url: null, contract_cover_design_url: null })')
const withoutArtwork = await measure('no artwork configured')

await evaluate(`window.__reopen(${JSON.stringify({ contract_letterhead_url: artwork, contract_cover_design_url: artwork })})`)
const withArtwork = await measure('usable artwork configured')

await evaluate(`window.__reopen(${JSON.stringify({ contract_letterhead_url: missing('letterhead'), contract_cover_design_url: missing('cover') })})`)
const withMissingArtwork = await measure('configured artwork whose file is gone')

// The overlay lock's scrollbar compensation is a logical `padding-inline-end`, so
// in RTL it lands on the left edge and shifts the whole sheet when it survives
// into print. Print the same document right-to-left and check that too.
await evaluate('document.documentElement.dir = "rtl"')
await evaluate('window.__reopen({ contract_letterhead_url: null, contract_cover_design_url: null })')
const rightToLeft = await measure('right-to-left with the overlay scroll-lock')
await evaluate('document.documentElement.dir = "ltr"')

// --- assertions ------------------------------------------------------------------
for (const [label, result] of [['no artwork', withoutArtwork], ['usable artwork', withArtwork], ['missing artwork', withMissingArtwork], ['right-to-left', rightToLeft]]) {
  assert.equal(result.error, undefined, `${label}: ${result.error}`)
  assert.equal(result.pages, 2, `${label}: the document must print a cover page and one content page`)
  assert.ok(result.contentPage, `${label}: the content page is missing`)
  assert.ok(result.applicationChromeHidden, `${label}: the application shell must not print`)
  // A Vuetify overlay must never shrink the page set to one screen's worth.
  assert.equal(result.htmlPosition, 'static', `${label}: the overlay scroll-lock must be released for print`)
  assert.notEqual(result.htmlHeight, `${result.viewportHeight}px`, `${label}: <html> must not stay pinned to the viewport height`)
  assert.ok(result.pdfPages >= 2, `${label}: the printed PDF must contain the cover and the content page (got ${result.pdfPages})`)
  // The lock's scrollbar compensation must never widen or shift the printed sheet.
  assert.equal(result.htmlPaddingInlineEnd, '0px', `${label}: the overlay scrollbar offset must not leak into print`)
}

assert.equal(rightToLeft.direction, 'rtl', 'right-to-left: the document must print in RTL')
assert.ok(rightToLeft.builtInCover && rightToLeft.builtInFooter, 'right-to-left: the built-in cover and letterhead must print')
assert.equal(rightToLeft.coverSurface.height, 1119, 'right-to-left: the built-in cover must fill one A4 page')

// The cover is either the configured artwork or the built-in cover page, never neither.
assert.ok(withoutArtwork.builtInCover && !withoutArtwork.customCover, 'no artwork: the built-in cover page must print')
assert.ok(withoutArtwork.builtInHeader && withoutArtwork.builtInFooter && !withoutArtwork.letterhead, 'no artwork: the built-in letterhead must print')
assert.equal(withoutArtwork.coverSurface.height, 1119, 'no artwork: the built-in cover must fill one A4 page')
assert.equal(withoutArtwork.toasts.length, 0, 'no artwork: nothing is unavailable, so no notice is shown')

assert.ok(withArtwork.customCover && withArtwork.customCoverUsable, 'usable artwork: the configured cover must print')
assert.ok(withArtwork.letterhead && withArtwork.letterheadUsable, 'usable artwork: the configured letterhead must print')
assert.ok(!withArtwork.builtInHeader && !withArtwork.builtInFooter, 'usable artwork: the letterhead replaces the built-in chrome')
assert.equal(withArtwork.letterheadSurface.width, 794, 'usable artwork: the letterhead must cover the full page width')
assert.equal(withArtwork.toasts.length, 0, 'usable artwork: nothing is unavailable, so no notice is shown')

// A configured path whose file is gone must degrade to the built-in design
// instead of printing a blank cover and losing the letterhead entirely.
assert.ok(withMissingArtwork.builtInCover && !withMissingArtwork.customCover, 'missing artwork: the built-in cover page must be used')
assert.ok(withMissingArtwork.builtInHeader && withMissingArtwork.builtInFooter, 'missing artwork: the built-in letterhead must be used')
assert.ok(!withMissingArtwork.letterhead, 'missing artwork: an unusable letterhead image must not print')
assert.equal(withMissingArtwork.coverSurface.height, 1119, 'missing artwork: the built-in cover must fill one A4 page')
assert.equal(withMissingArtwork.toasts.length, 1, 'missing artwork: the user must be told the configured artwork was skipped')

// The print dialog must never show a stale, pre-load contract name.
assert.ok(withMissingArtwork.dialogText.includes('Scholarship'), 'the print dialog must show the loaded contract name')
assert.ok(!withMissingArtwork.dialogText.includes('Untitled contract'), 'the print dialog must not show the pre-load placeholder')

socket.close()
chrome.kill()
server.close()

console.log('Contract print cover/letterhead fallback, A4 page set, print isolation, overlay scroll-lock release and print-dialog state passed in Chrome.')
