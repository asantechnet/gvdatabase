import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readFile } from 'node:fs/promises'
import ts from 'typescript'
import { compileScript, compileStyle, parse } from '@vue/compiler-sfc'
import { createSSRApp, ref } from 'vue'
import { renderToString } from '@vue/server-renderer'

const require = createRequire(import.meta.url)
const root = new URL('../', import.meta.url)
const read = file => readFile(new URL(file, root), 'utf8')
const compile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText
const load = (source, dependencies) => {
  const module = { exports: {} }
  new Function('require', 'module', 'exports', compile(source))(name => dependencies[name] ?? require(name), module, module.exports)
  return module.exports
}

const printSource = await read('resources/ts/utils/printAcademySlip.ts')
const documentSource = await read('resources/ts/utils/printDocument.ts')
const printing = load(printSource, { './printDocument': {} })
const papers = ['A4', 'A5', 'A4 2x A5', 'A5 2x']

for (const value of [-1, 0, 1, 1.5, 2, 4, 5, NaN]) {
  assert.equal(printing.academySlipCopies(value), Number.isFinite(value) ? Math.max(1, Math.min(4, Math.floor(value))) : 1)
}
for (const paper of papers) {
  const layout = printing.academySlipPrintLayout(paper)
  assert.ok(layout.width > 0 && layout.height > 0 && layout.cardHeight * layout.perPage === layout.height)
}
// Two-up modes place two landscape cards on one fixed page.
for (const paper of ['A4 2x A5', 'A5 2x']) {
  const layout = printing.academySlipPrintLayout(paper)
  assert.equal(layout.perPage, 2)
  assert.ok(layout.width > layout.cardHeight, `${paper} cards must be landscape`)
}

const file = 'resources/ts/pages/templates/academy_slip_template.vue'
const { descriptor, errors } = parse(await read(file), { filename: file })
assert.deepEqual(errors, [])
const scopeId = 'data-v-academy-slip-test'
const script = compileScript(descriptor, { id: scopeId, inlineTemplate: true, templateOptions: { ssr: true } })
let locale = 'en'
let messages = JSON.parse(await read('resources/ts/plugins/i18n/locales/en.json'))
const t = key => key.split('.').reduce((value, part) => value?.[part], messages) ?? key
const component = load(script.content, {
  'vue-i18n': { useI18n: () => ({ t, locale: ref(locale) }) },
  '@/utils/localization': { formatLocalizedDate: value => value.slice(0, 10) },
  '@/utils/documentBranding': load(await read('resources/ts/utils/documentBranding.ts'), {}),
  '@/utils/printAcademySlip': printing,
}).default
component.__scopeId = scopeId
const css = descriptor.styles.map(style => {
  const result = compileStyle({ source: style.content, id: scopeId, scoped: style.scoped })
  assert.deepEqual(result.errors, [])
  assert.ok(!result.code.includes('@page'))
  return result.code
}).join('\n')
assert.ok(css.includes('border-block-end: 1px dashed #444'), 'two-up cards must be separated by a dashed line')

const currency = { id: 1, code: 'USD', symbol: '$', decimalPlaces: 2, rate: 1 }
const slip = {
  reference: 'CON-00042', document: 'academy_slip', date: '2026-10-08',
  company: { name: 'Academy Firm', phone: '0700000000', address: 'Kabul' },
  applicant: { id: 12, name: 'Applicant Name', father_name: 'Father Name', contact: '0701234567', email: null, address: null },
  contract: { id: 1, contract_number: 'CON-00042' },
  academy: { student_id: 'STU-100', program: 'Web Development', time: 'Morning', duration: '6 months', class: 'Class A', teachers: ['Jane Doe'], started_on: '2026-10-08', ends_on: '2027-04-08' },
  service: { id: 1, name: 'Academy', service_label: 'Academy', fee: 120, discount: 20, payment_status: 'partially_paid', payment_status_label: 'Partially Paid', currency },
  totals: { total_fee: 100, paid_amount: 40, balance: 60, currency },
  letterhead: { source: 'none', url: null, raw: null, safe: { x: 5, y: 5, width: 90, height: 90 } },
  signature: { url: null, name: 'Academy Firm' },
}

for (const lang of ['en', 'fa-AF', 'ps-AF']) {
  locale = lang
  messages = JSON.parse(await read(`resources/ts/plugins/i18n/locales/${lang}.json`))
  for (const paper of papers) {
    const app = createSSRApp(component, { slip, paperSize: paper, copies: 3 })
    const rendered = await renderToString(app)
    assert.ok(rendered.includes('CON-00042') && rendered.includes('Applicant Name'))
    assert.ok(rendered.includes(`dir="${lang === 'en' ? 'ltr' : 'rtl'}"`))
    assert.equal((rendered.match(/class="academy-card"/g) ?? []).length, 3)
  }
  for (const key of ['paperA4TwoA5', 'paperA5Two', 'paperTooSmall', 'artworkFailed', 'printFailed'])
    assert.equal(typeof messages.documents.academySlip[key], 'string', `${lang}:${key}`)
}
require('postcss').parse(css).walkAtRules('media', media => {
  if (media.params === 'print') {
    media.walkRules(rule => {
      assert.ok(rule.selectors.every(selector => selector.startsWith('body.academy-slip-print-mode')))
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
    if (selector === ':scope > .academy-card') return this.children.filter(child => child.classList.contains('academy-card'))
    return this.children.flatMap(child => [...((selector === 'img' ? child.tagName === 'IMG' : child.classList.contains(selector.slice(1))) ? [child] : []), ...child.querySelectorAll(selector)])
  }
}
const makeRoot = copies => {
  const element = new Element('academy-sheet')
  for (let index = 0; index < copies; index++) element.appendChild(new Element('academy-card')).appendChild(new Element('academy-card__content'))
  return element
}
globalThis.document = Object.assign(new EventTarget(), { createElement: () => new Element(), documentElement: { dir: 'rtl' }, body: new Element(), head: new Element(), fonts: { ready: Promise.resolve() }, querySelectorAll: selector => document.body.querySelectorAll(selector) })
globalThis.window = Object.assign(new EventTarget(), { setTimeout, clearTimeout, print: () => {} })
globalThis.requestAnimationFrame = callback => setTimeout(callback, 0)
globalThis.getComputedStyle = () => ({ fontFamily: 'Arial, sans-serif' })
const documentPrinting = load(documentSource, { vue: { nextTick: () => Promise.resolve() } })
const academyPrinting = load(printSource, { vue: { nextTick: () => Promise.resolve() }, './printDocument': documentPrinting })
for (const paper of papers) {
  for (const copies of [1, 2, 3, 4]) {
    const original = makeRoot(copies)
    const host = academyPrinting.createAcademySlipPrintHost(original, paper)
    assert.equal(host.children.length, Math.ceil(copies / academyPrinting.academySlipPrintLayout(paper).perPage))
    assert.equal(host.querySelectorAll('.academy-card').length, copies)
    assert.equal(original.children.length, copies)
    assert.equal(host.dir, 'rtl')
  }
}
assert.throws(() => academyPrinting.createAcademySlipPrintHost(makeRoot(0), 'A5'), /printFailed/)
await academyPrinting.printAcademySlip(makeRoot(2), 'A4 2x A5')
assert.equal(document.head.children.length, 1)
assert.ok(document.head.children[0].textContent.includes('size: A4; margin: 0;'))
assert.equal(document.body.children.length, 1)
assert.equal(document.body.classList.contains('academy-slip-print-mode'), true)
assert.equal(document.body.children[0].children.length, 1)
assert.equal(document.body.children[0].children[0].children.length, 2)
window.dispatchEvent(new Event('afterprint'))
assert.equal(document.body.children.length, 0)
assert.equal(document.head.children.length, 0)
assert.equal(document.body.classList.contains('academy-slip-print-mode'), false)
const oversized = makeRoot(1)
oversized.children[0].children[0].scrollHeight = 200
await assert.rejects(() => academyPrinting.printAcademySlip(oversized, 'A5'), /paperTooSmall/)
assert.equal(document.body.children.length, 0)
window.print = () => { throw new Error('Print cancelled') }
await assert.rejects(() => academyPrinting.printAcademySlip(makeRoot(1), 'A5'), /Print cancelled/)
assert.equal(document.body.children.length, 0)
assert.equal(document.head.children.length, 0)
assert.equal(document.body.classList.contains('academy-slip-print-mode'), false)
const brokenArtwork = makeRoot(1)
const image = new Element()
image.tagName = 'IMG'
image.complete = true
image.naturalWidth = 0
brokenArtwork.children[0].appendChild(image)
await assert.rejects(() => academyPrinting.printAcademySlip(brokenArtwork, 'A5'), /artworkFailed/)
assert.equal(document.body.children.length, 0)
assert.ok(css.includes('.academy-slip-print-host .academy-card:not(:last-child)'))
console.log('Academy slip layout/copy rules, two-up grouping, RTL/LTR rendering, namespaced print CSS and lifecycle passed. Browser/PDF layout is not verified.')
