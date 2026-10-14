import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { createRequire } from 'node:module'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Support centre regression: mounts the real `pages/support.vue` in Chrome with
 * real Vuetify and asserts that
 *
 *  1. the developer contact channels render as working `tel:`, `wa.me`, `t.me`
 *     and `mailto:` links with the platform team's details (never the office
 *     profile), plus the address row,
 *  2. the channels come from i18n and read left-to-right even in RTL locales,
 *  3. the section is visible both standalone and as an embedded Setup → Settings
 *     sub-page, while the standalone page title only renders when it is not embedded,
 *  4. the tiles stay inside the viewport on mobile, light and dark.
 *
 * It also checks the wiring of the rest of the batch's non-browser changes:
 * Support is gone from the profile menu and lives in a Setup → Settings sub-page,
 * payment requests no longer offer salary, and a user can be created with a
 * manual password that sends no email.
 */

const root = fileURLToPath(new URL('..', import.meta.url))
const require = createRequire(root + 'package.json')
const { parse, compileScript, compileStyle } = require('@vue/compiler-sfc')
const ts = require('typescript')

const output = process.env.SUPPORT_CONTACT_OUTPUT ?? join(tmpdir(), 'educational-support-contact')
mkdirSync(output, { recursive: true })

const read = file => readFileSync(join(root, 'resources/ts', file), 'utf8')
const readApp = file => readFileSync(join(root, file), 'utf8')

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

// --- 1. static wiring ---------------------------------------------------------

const constants = read('utils/constants.ts')
for (const value of ['+935453435234', 'Kabul, Afghanistan', 'blue@gm.com', 'tel:+935453435234', 'https://wa.me/935453435234', 'https://t.me/+935453435234', 'mailto:blue@gm.com'])
  assert.ok(constants.includes(value), `constants.ts must hold the platform contact value ${value}`)
// Hardcoded on purpose: it must not read the office profile or any API.
assert.ok(!/\bfrom\s+['"]|\brequire\(/.test(constants), 'the platform contact must stay hardcoded in constants.ts')
assert.ok(constants.includes('not editable company'), 'constants.ts must state that the contact is not editable company data')

// The bundled icon set must contain the channel glyphs, otherwise they render blank.
const iconsCss = readFileSync(join(root, 'resources/ts/plugins/iconify/icons.css'), 'utf8')
for (const icon of ['tabler-brand-whatsapp', 'tabler-brand-telegram', 'tabler-headset', 'tabler-external-link'])
  assert.ok(iconsCss.includes(`.${icon}`), `the bundled icon set must include ${icon}: run npm run build:icons`)

// Support lives as an embedded sub-page of Setup → Settings, not in the profile menu or the account page.
assert.ok(!read('layouts/components/UserProfile.vue').includes("name: 'support'"), 'the profile menu must no longer link to Support')
const setupSettings = read('pages/setup/settings.vue')
assert.ok(setupSettings.includes("value: 'support' as const"), 'Setup → Settings must own the Support sub-page')
assert.ok(setupSettings.includes('<SupportPage'), 'the Support sub-page must render the support page component')
assert.ok(/activeSettingsPage === 'support'/.test(setupSettings), 'the Support sub-page must render inside the settings content area')
const accountSettings = read('pages/account-settings.vue')
assert.ok(!accountSettings.includes("value: 'support'"), 'Support must no longer be a tab of the account settings page')

// Payment requests are income/expense only; payroll owns salary.
const pendingPage = read('pages/pending-transactions.vue')
assert.ok(pendingPage.includes(":items=\"['income', 'expense']"), 'payment requests must offer income and expense only')
assert.ok(!pendingPage.includes("'salary'"), 'the payment-request form must not mention salary any more')
const pendingController = readApp('app/Http/Controllers/PendingTransactionController.php')
assert.ok(pendingController.includes("'transaction_type' => [\$presence, 'in:income,expense']"), 'the API must reject salary as a payment-request type')

// Accounts can be created with a manual password and no email.
assert.ok(read('api/user.ts').includes('password_confirmation'), 'the user API contract must carry the optional manual credentials')
const userForm = read('components/forms/UserForm.vue')
assert.ok(userForm.includes('passwordMode'), 'the user form must render the manual password fields')
const userController = readApp('app/Http/Controllers/UserController.php')
assert.ok(userController.includes("if (! \$password) {\n            \$user->sendEmailVerificationNotification();\n        }"), 'a manual password must skip the invitation email')
assert.ok(userController.includes("\$data['must_set_password'] = false;"), 'a manual password must clear the password-setup pending flag')

// --- 2. browser harness -------------------------------------------------------

let scopedCss = ''
const componentFactories = []

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

/** Compile a plain TS module (no imports) so the page gets the real values. */
const compileModule = (file, alias) => {
  const source = ts.transpileModule(read(file), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText
  componentFactories.push(`factories[${JSON.stringify(alias)}] = (require, module, exports) => {${source}};`)
}

compileComponent('pages/support.vue', '@/pages/support.vue')
compileModule('utils/constants.ts', '@/utils/constants')

// `support.vue` relies on the project's auto-imports, which the SFC compiler in
// this harness does not apply. Expose the Vue-provided helpers as globals so the
// real component code runs unchanged (helper utilities are stubbed below).
const autoImportedVueNames = [...readApp('auto-imports.d.ts').matchAll(/^\s+const ([A-Za-z0-9_]+): typeof import\('vue'\)/gm)].map(match => match[1])

const buildHarness = (locale, embedded) => {
  const messages = JSON.parse(read(`plugins/i18n/locales/${locale}.json`))

  return `
for (const name of ${JSON.stringify(autoImportedVueNames)}) { if (!(name in window) && typeof Vue[name] !== 'undefined') window[name] = Vue[name]; }
const messages = ${JSON.stringify(messages)};
const t = (key, params) => {
  const value = key.split('.').reduce((node, part) => node?.[part], messages) ?? key;
  if (typeof value !== 'string' || !params) return value;
  return Object.entries(params).reduce((text, [name, replacement]) => text.replaceAll('{' + name + '}', replacement), value);
};
const locale = ${JSON.stringify(locale)};
window.requests = [];
window.bootError = null;
window.requiredValidator = () => true;
const factories = {}, cache = {};
const supportApi = {
  getSupportTickets: async () => [],
  createSupportTicket: async () => { throw new Error('not used'); },
};
const mocks = {
  'vue': Vue,
  'vuetify/components': Vuetify,
  'vue-i18n': { useI18n: () => ({ t, locale: Vue.ref(locale) }) },
  'vue-toastification': { useToast: () => ({ error: () => {}, success: () => {}, info: () => {} }) },
  '@/utils/localization': { currentLocale: () => locale },
  '@/utils/apiErrors': { apiErrorMessage: () => 'Request failed' },
  '@/api/supportTicket': { default: supportApi, ...supportApi },
};
${componentFactories.join('\n')}
function require(name){
  if (factories[name]) { if (cache[name]) return cache[name].exports; const module = { exports: {} }; cache[name] = module; factories[name](require, module, module.exports); return module.exports; }
  if (mocks[name]) return mocks[name];
  throw new Error('missing module ' + name);
}
const vuetify = Vuetify.createVuetify({
  locale: { rtl: { en: false, fa: true }, locale: ${JSON.stringify(locale === 'en' ? 'en' : 'fa')} },
  theme: { defaultTheme: 'light' },
  icons: { defaultSet: 'class', sets: { class: { component: props => Vue.h('i', { class: props.icon }) } } },
});
window.testTheme = theme => vuetify.theme.change(theme);
const page = require('@/pages/support.vue').default;
const app = Vue.createApp({ render: () => Vue.h('div', { class: 'app-shell' }, [Vue.h(page, { embedded: ${embedded ? 'true' : 'false'} })]) });
app.config.globalProperties.$t = t;
app.use(vuetify);
try { app.mount('#app') } catch (error) { window.bootError = String(error?.stack ?? error) }
window.__pageMounted = true;
`
}

const pageHtml = (locale, embedded) => `<!doctype html>
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
<script>try { ${buildHarness(locale, embedded).replace(/<\/script/gi, '<\\/script')} } catch (error) { window.bootError = String(error?.stack ?? error) }</script>
</body>
</html>`

const variants = [
  { locale: 'en', embedded: false },
  { locale: 'fa-AF', embedded: false },
  { locale: 'en', embedded: true },
]
const pages = {}
for (const variant of variants) {
  const file = join(output, `support-${variant.locale}-${variant.embedded ? 'embedded' : 'standalone'}.html`)
  writeFileSync(file, pageHtml(variant.locale, variant.embedded))
  pages[`${variant.locale}:${variant.embedded}`] = 'file://' + file
}

// --- minimal CDP client (same approach as the other UI regressions) -----------
const chrome = spawn(chromeBinary, [
  '--headless=new', '--remote-debugging-port=9223', '--no-sandbox', '--disable-gpu',
  '--disable-dev-shm-usage', `--user-data-dir=${join(output, 'profile')}`,
  '--allow-file-access-from-files', 'about:blank',
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
await send('Page.enable', {}, sessionId)
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
    if (await evaluate('Boolean(document.querySelector(".support-contact-tile"))'))
      return
  }
  const diagnostic = await evaluate('JSON.stringify({ bootError: window.bootError ?? null, tiles: document.querySelectorAll(".support-contact-tile").length })')

  throw new Error(`the support contact section never rendered: ${diagnostic}`)
}

const expectedHrefs = ['tel:+935453435234', 'https://wa.me/935453435234', 'https://t.me/+935453435234', 'mailto:blue@gm.com']

try {
  for (const variant of variants) {
    const key = `${variant.locale}:${variant.embedded}`
    const messages = JSON.parse(read(`plugins/i18n/locales/${variant.locale}.json`))
    await send('Page.navigate', { url: pages[key] }, sessionId)
    await settle()

    const bootError = await evaluate('window.bootError ?? null')
    assert.equal(bootError, null, `the support page failed to mount (${key}): ${bootError}`)

    // 1. every channel is a real link to the platform team's details.
    const hrefs = await evaluate('Array.from(document.querySelectorAll(".support-contact-tile")).map(tile => tile.getAttribute("href"))')
    assert.deepEqual(hrefs, expectedHrefs, `${key}: the contact channels must link to the developer details in order`)
    const labels = await evaluate('Array.from(document.querySelectorAll(".support-contact-tile .support-contact-tile__label")).map(node => node.textContent.trim())')
    assert.deepEqual(labels, [
      messages.pages.support.contact.phone,
      messages.pages.support.contact.whatsapp,
      messages.pages.support.contact.telegram,
      messages.pages.support.contact.email,
    ], `${key}: the channel labels must come from i18n`)
    const values = await evaluate('Array.from(document.querySelectorAll(".support-contact-tile .support-contact-tile__value")).map(node => ({ text: node.textContent.trim(), dir: node.getAttribute("dir") }))')
    assert.ok(values.every(value => value.dir === 'ltr'), `${key}: the phone/email values must stay readable left-to-right: ${JSON.stringify(values)}`)
    assert.ok(values[0].text.includes('+935453435234') && values[3].text.includes('blue@gm.com'), `${key}: the channel values are wrong: ${JSON.stringify(values)}`)

    // 2. only the chat links open a new tab, and they are marked safe.
    const external = await evaluate('Array.from(document.querySelectorAll(".support-contact-tile")).filter(tile => tile.getAttribute("target") === "_blank").map(tile => tile.getAttribute("rel"))')
    assert.deepEqual(external, ['noopener noreferrer', 'noopener noreferrer'], `${key}: the WhatsApp and Telegram links must be the only new-tab links`)

    // 3. the address row and the developer notice are always present.
    assert.equal(await evaluate('document.querySelectorAll(".support-contact-address").length'), 1, `${key}: the address row must render`)
    assert.ok((await evaluate('document.querySelector(".support-contact-address .support-contact-tile__value").textContent')).includes('Kabul, Afghanistan'), `${key}: the address value is wrong`)
    assert.ok((await evaluate('document.querySelector(".v-alert").textContent')).includes(messages.pages.support.contact.developerNotice), `${key}: the section must say who owns these details`)

    // 4. embedded in Settings there is no standalone page title.
    const hasTitle = await evaluate('Boolean(document.querySelector("[data-page-title]"))')
    assert.equal(hasTitle, !variant.embedded, `${key}: the standalone page title must only render outside Settings`)

    await evaluate('window.testTheme("dark")')

    // 5. responsive: the tiles wrap inside the viewport instead of overflowing.
    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true }, sessionId)
    const mobile = JSON.parse(await evaluate(`JSON.stringify({
      page: document.documentElement.scrollWidth,
      viewport: window.innerWidth,
      columns: new Set(Array.from(document.querySelectorAll('.support-contact-tile')).map(tile => Math.round(tile.getBoundingClientRect().left))).size,
      border: getComputedStyle(document.querySelector('.support-contact-tile')).borderTopColor,
    })`))
    assert.ok(mobile.page <= mobile.viewport + 2, `${key}: the contact grid overflows on mobile: ${JSON.stringify(mobile)}`)
    assert.equal(mobile.columns, 1, `${key}: the tiles must stack on mobile: ${JSON.stringify(mobile)}`)
    assert.ok(!/rgba\(0, 0, 0, 0\)/.test(mobile.border), `${key}: the tile border must stay visible in dark mode: ${JSON.stringify(mobile)}`)
    await send('Emulation.clearDeviceMetricsOverride', {}, sessionId)
  }
} finally {
  await send('Browser.close').catch(() => {})
  socket.close()
  chrome.kill()
}

assert.deepEqual(errors, [], `console errors: ${JSON.stringify(errors)}`)
console.log('Support centre passed: the developer phone/WhatsApp/Telegram/email and address render as working links (i18n labels, LTR values, safe new-tab targets) standalone and embedded, the standalone title only shows outside Settings, the grid stays responsive, and the Support sub-page/payment-request/manual-password wiring matches the batch.')
