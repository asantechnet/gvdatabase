import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { mkdir, readFile, writeFile } from 'node:fs/promises'
import ts from 'typescript'
import { compileScript, compileStyle, parse } from '@vue/compiler-sfc'
import { createSSRApp, ref } from 'vue'
import { renderToString } from '@vue/server-renderer'

const require = createRequire(import.meta.url)
const root = new URL('../', import.meta.url)
const output = process.env.RECEIPT_TEST_OUTPUT ?? '/tmp/educational-receipt-print'
const read = file => readFile(new URL(file, root), 'utf8')
const compile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText
const load = (source, dependencies) => {
  const module = { exports: {} }
  new Function('require', 'module', 'exports', compile(source))(name => dependencies[name] ?? require(name), module, module.exports)
  return module.exports
}
const printSource = await read('resources/ts/utils/printReceipt.ts')
const documentSource = await read('resources/ts/utils/printDocument.ts')
const printing = load(printSource, { './printDocument': {} })
const papers = ['A3 landscape', 'A4', 'A4 landscape', 'A5', 'letter', 'A4 2x A5', 'A5 2x']
for (const value of [-1, 0, 1, 1.5, 2, 4, 5, NaN]) {
  assert.equal(printing.receiptCopies(value), Number.isFinite(value) ? Math.max(1, Math.min(4, Math.floor(value))) : 1)
}
for (const paper of papers) {
  const layout = printing.receiptPrintLayout(paper)
  assert.ok(layout.width > 0 && layout.height > 0 && layout.cardHeight * layout.perPage === layout.height)
}

// Printing settings mirror the academy slip: the same paper options, two fixed
// copies (customer + office) and no copies selector.
const dialogSource = await read('resources/ts/components/income/ReceiptPrinter.vue')
const slipDialogSource = await read('resources/ts/components/academy/AcademySlipPrinter.vue')
const paperValues = source => [...source.matchAll(/\{\s*title:\s*[^,]+,\s*value:\s*'([^']+)'/g)].map(match => match[1])
assert.deepEqual(paperValues(dialogSource), ['A4 2x A5', 'A5 2x', 'A4', 'A5'])
assert.deepEqual(new Set(paperValues(dialogSource)), new Set(paperValues(slipDialogSource)))
assert.ok(!dialogSource.includes('VTextField'), 'receipt printing must not offer a copies selector')
assert.ok(/const copies = 2/.test(dialogSource), 'receipts print as two fixed copies')

const file = 'resources/ts/pages/templates/receipt_template.vue'
const { descriptor, errors } = parse(await read(file), { filename: file })
assert.deepEqual(errors, [])
const scopeId = 'data-v-receipt-test'
const script = compileScript(descriptor, { id: scopeId, inlineTemplate: true, templateOptions: { ssr: true } })
let locale = 'en'
let messages = JSON.parse(await read('resources/ts/plugins/i18n/locales/en.json'))
const t = key => key.split('.').reduce((value, part) => value?.[part], messages) ?? key
const component = load(script.content, {
  'vue-i18n': { useI18n: () => ({ t, locale: ref(locale) }) },
  '@/utils/localization': { formatLocalizedDate: value => value.slice(0, 10) },
  '@/utils/documentBranding': load(await read('resources/ts/utils/documentBranding.ts'), {}),
  '@/utils/amountInWords': load(await read('resources/ts/utils/amountInWords.ts'), {}),
  '@/utils/printReceipt': printing,
}).default
component.__scopeId = scopeId
const css = descriptor.styles.map(style => {
  const result = compileStyle({ source: style.content, id: scopeId, scoped: style.scoped })
  assert.deepEqual(result.errors, [])
  assert.ok(!result.code.includes('@page'))
  return result.code
}).join('\n')
const currency = { id: 1, code: 'USD', symbol: '$', decimalPlaces: 2, rate: 1 }
const service = { id: 1, name: 'Scholarship', service_label: 'Scholarship', converted_fee: 100, fee: 100, currency, payment_status_label: 'Partially Paid' }
const receipt = {
  serial_number: 'REC-000123', document: 'receipt', date: '2026-10-08',
  company: { name: 'Education Firm', phone: '0700000000', address: 'Kabul' },
  applicant: { id: 12, name: 'Applicant Name', father_name: 'Father Name', contact: '0701234567' },
  income: { id: 123, name: 'Service payment', type: 'Service', line_total: 40, currency },
  contract: { id: 1, contract_number: 'CON-00012' }, service, services: [service],
  totals: { total_fee: 100, paid_amount: 40, balance: 60, currency },
  letterhead: { url: null, safe: { x: 5, y: 5, width: 90, height: 90 } },
  signature: { url: null }, recorded_by: { id: 1, name: 'Finance Officer' },
}
const browserCode = `
  const nextTick = () => Promise.resolve();
  const documentModule = {exports: {}};
  (function(require,module,exports){${compile(documentSource)}})(() => ({nextTick}),documentModule,documentModule.exports);
  const receiptModule = {exports: {}};
  (function(require,module,exports){${compile(printSource)}})(name => name === 'vue' ? {nextTick} : documentModule.exports,receiptModule,receiptModule.exports);
  window.receiptPrinting = receiptModule.exports;
  window.printCalls = 0;
  window.print = () => { window.printCalls++; };
  window.testPrint = paper => receiptPrinting.printReceipt(document.querySelector('#preview .receipt-sheet'),paper);
`
await mkdir(output, { recursive: true })
async function html(paper = 'A5', copies = 1, data = receipt) {
  const app = createSSRApp(component, { receipt: data, paperSize: paper, copies })
  app.config.globalProperties.translateBackendValue = value => value
  const rendered = await renderToString(app)
  return `<!doctype html><html dir="${locale === 'en' ? 'ltr' : 'rtl'}"><head><meta charset="utf-8"><style>*{box-sizing:border-box}body{font-family:Arial,sans-serif}#preview{max-width:760px;margin:auto}@media print{@page{size:A4 landscape;margin:20mm}}</style><style>${css}</style></head><body><main id="preview">${rendered}</main><script>${browserCode}</script></body></html>`
}
await writeFile(`${output}/receipt.html`, await html())
console.log('Receipt layout/copy rules and Vue/style compilation passed.')
for (const lang of ['en', 'fa-AF', 'ps-AF']) {
  locale = lang
  messages = JSON.parse(await read(`resources/ts/plugins/i18n/locales/${lang}.json`))
  for (const paper of papers) {
    const markup = await html(paper, 3)
    assert.ok(markup.includes('REC-000123') && markup.includes('Applicant Name'))
    assert.ok(markup.includes(`dir="${lang === 'en' ? 'ltr' : 'rtl'}"`))
  }
  for (const key of ['paperTooSmall', 'artworkFailed', 'printFailed'])
    assert.equal(typeof messages.documents.receipt[key], 'string')
}
require('postcss').parse(css).walkAtRules('media', media => {
  if (media.params === 'print') {
    media.walkRules(rule => {
      assert.ok(rule.selectors.every(selector => selector.startsWith('body.income-receipt-print-mode')))
    })
  }
})

// Minimal DOM test doubles verify grouping and lifecycle, not browser layout.
class Element {
  constructor(className = '') {
    this.className = className
    this.children = []
    this.style = {}
    this.matches = selector => selector.split(',').some(part => this.classList.contains(part.trim().slice(1)))
    this.dataset = {}
    this.dir = 'rtl'
    this.tagName = 'DIV'
    this.clientHeight = this.scrollHeight = 100
    this.clientWidth = this.scrollWidth = 100
    this.classList = {
      add: name => { this.className += ` ${name}` },
      remove: name => { this.className = this.className.split(' ').filter(value => value !== name).join(' ') },
      contains: name => this.className.split(' ').includes(name),
    }
  }
  appendChild(child) { this.children.push(child); child.parent = this; return child }
  remove() { if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this) }
  cloneNode(deep) {
    const copy = new Element(this.className)
    for (const name of ['dir', 'tagName', 'complete', 'naturalWidth', 'clientHeight', 'scrollHeight', 'clientWidth', 'scrollWidth']) copy[name] = this[name]
    if (deep) for (const child of this.children) copy.appendChild(child.cloneNode(true))
    return copy
  }
  querySelectorAll(selector) {
    if (selector === ':scope > .receipt-card') return this.children.filter(child => child.classList.contains('receipt-card'))
    return this.children.flatMap(child => [...((selector === 'img' ? child.tagName === 'IMG' : child.classList.contains(selector.slice(1))) ? [child] : []), ...child.querySelectorAll(selector)])
  }
}
const makeRoot = copies => {
  const root = new Element('receipt-sheet')
  for (let index = 0; index < copies; index++) root.appendChild(new Element('receipt-card')).appendChild(new Element('receipt-card__content'))
  return root
}
globalThis.document = Object.assign(new EventTarget(), { createElement: () => new Element(), documentElement: { dir: 'rtl' }, body: new Element(), head: new Element(), fonts: { ready: Promise.resolve() }, querySelectorAll: selector => document.body.querySelectorAll(selector) })
globalThis.window = Object.assign(new EventTarget(), { setTimeout, clearTimeout, print: () => {} })
globalThis.requestAnimationFrame = callback => setTimeout(callback, 0)
globalThis.getComputedStyle = () => ({ fontFamily: 'Arial, sans-serif' })
const documentPrinting = load(documentSource, { vue: { nextTick: () => Promise.resolve() } })
const receiptPrinting = load(printSource, { vue: { nextTick: () => Promise.resolve() }, './printDocument': documentPrinting })
for (const paper of papers) {
  for (const copies of [1, 2, 3, 4]) {
    const original = makeRoot(copies)
    const host = receiptPrinting.createReceiptPrintHost(original, paper)
    assert.equal(host.children.length, Math.ceil(copies / printing.receiptPrintLayout(paper).perPage))
    assert.equal(host.querySelectorAll('.receipt-card').length, copies)
    assert.equal(original.children.length, copies)
    assert.equal(host.dir, 'rtl')
  }
}
assert.throws(() => receiptPrinting.createReceiptPrintHost(makeRoot(0), 'A5'), /printFailed/)
await receiptPrinting.printReceipt(makeRoot(1), 'A5')
assert.equal(document.head.children.length, 1)
assert.ok(document.head.children[0].textContent.includes('size: A5; margin: 0;'))
assert.equal(document.body.children.length, 1)
assert.equal(document.body.classList.contains('income-receipt-print-mode'), true)
window.dispatchEvent(new Event('afterprint'))
assert.equal(document.body.children.length, 0)
assert.equal(document.head.children.length, 0)
assert.equal(document.body.classList.contains('income-receipt-print-mode'), false)
const oversized = makeRoot(1)
oversized.children[0].children[0].scrollHeight = 200
await assert.rejects(() => receiptPrinting.printReceipt(oversized, 'A5'), /paperTooSmall/)
assert.equal(document.body.children.length, 0)
window.print = () => { throw new Error('Print cancelled') }
await assert.rejects(() => receiptPrinting.printReceipt(makeRoot(1), 'A5'), /Print cancelled/)
assert.equal(document.body.children.length, 0)
assert.equal(document.head.children.length, 0)
assert.equal(document.body.classList.contains('income-receipt-print-mode'), false)
const brokenArtwork = makeRoot(1)
const image = new Element()
image.tagName = 'IMG'
image.complete = true
image.naturalWidth = 0
brokenArtwork.children[0].appendChild(image)
await assert.rejects(() => receiptPrinting.printReceipt(brokenArtwork, 'A5'), /artworkFailed/)
assert.equal(document.body.children.length, 0)
assert.equal(document.head.children.length, 0)
window.print = () => {}
const table = new Element('list-print-report')
table.appendChild(new Element('report-content'))
await documentPrinting.printDocument({bodyClass:'printing-table-contracts',pageStyle:'size: A4 landscape; margin: 12mm;',element:table})
assert.equal(document.body.children.length,1)
assert.equal(document.body.children[0].id,'app-print-host')
assert.equal(document.body.children[0].dir,'rtl')
assert.equal(document.body.children[0].children[0].className,'list-print-report')
assert.equal(table.children.length,1)
assert.equal(document.body.classList.contains('printing-isolated-document'),true)
await receiptPrinting.printReceipt(makeRoot(1),'A5')
assert.equal(document.body.classList.contains('printing-isolated-document'),false)
assert.equal(document.body.classList.contains('printing-table-contracts'),false)
assert.equal(document.head.children.length,1)
assert.ok(document.head.children[0].textContent.includes('size: A5; margin: 0;'))
window.dispatchEvent(new Event('afterprint'))
assert.equal(document.body.children.length,0)
console.log('Receipt grouping (28 configurations), RTL/LTR rendering (21 configurations), namespaced CSS and lifecycle regressions passed. Browser/PDF layout is not verified.')
