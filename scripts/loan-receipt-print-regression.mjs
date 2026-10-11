import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readFile } from 'node:fs/promises'
import ts from 'typescript'
import { compileScript, compileStyle, parse } from '@vue/compiler-sfc'
import { createSSRApp, ref } from 'vue'
import { renderToString } from '@vue/server-renderer'

/**
 * Loan receipt regression: the loan print reuses the income receipt template,
 * so this guards the wiring (route, controller, service, API contract, RBAC
 * mirror) and the label switching between the two documents.
 */

const require = createRequire(import.meta.url)
const root = new URL('../', import.meta.url)
const read = file => readFile(new URL(file, root), 'utf8')
const compile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText
const load = (source, dependencies) => {
  const module = { exports: {} }
  new Function('require', 'module', 'exports', compile(source))(name => dependencies[name] ?? require(name), module, module.exports)

  return module.exports
}

// --- static wiring: the loan endpoint must exist end to end ----------------------
const routes = await read('routes/api.php')
assert.ok(routes.includes("Route::get('loans/{pendingTransaction}/receipt'"), 'the loans receipt route must be registered')
assert.ok(routes.includes("Route::get('pending-transactions/{pendingTransaction}/receipt'"), 'the legacy pending-transactions receipt alias must be registered')

const controller = await read('app/Http/Controllers/PendingTransactionController.php')
assert.ok(controller.includes('use App\\Services\\LoanReceiptService;'), 'the controller must import LoanReceiptService')
assert.ok(/public function receipt\(PendingTransaction \$pendingTransaction, LoanReceiptService \$receipts\)/.test(controller), 'the controller must expose receipt()')

const service = await read('app/Services/LoanReceiptService.php')
assert.ok(service.includes("'document' => 'loan'"), 'the loan payload must be tagged as a loan document')
assert.ok(service.includes("'letterhead' => DocumentLetterheads::resolve(\$setting, 'receipt')"), 'the loan payload must reuse receipt letterheads')
assert.ok(service.includes("sprintf('LN-%s-%06d'"), 'the loan payload must carry a stable serial')

const api = await read('resources/ts/api/pendingTransactions.ts')
assert.ok(api.includes('receipt: async (id: number)'), 'the pending-transaction API must expose receipt(id)')
assert.ok(api.includes('/loans/${id}/receipt'), 'the API must call the loan receipt endpoint')

const authorization = await read('resources/ts/utils/apiAuthorization.ts')
assert.ok(authorization.includes("'GET /loans/:id/receipt': ['view_pending_transactions']"), 'the client RBAC mirror must allow the loan receipt')

const printer = await read('resources/ts/components/income/ReceiptPrinter.vue')
assert.ok(printer.includes('loader: (id: number) => Promise<ReceiptPayload>'), 'the shared printer must take a loader')
assert.ok(printer.includes('recordId: number | null'), 'the shared printer must take a record id')
assert.ok(printer.includes("document?: 'receipt' | 'loan'"), 'the shared printer must support both documents')

// --- i18n: the loan label set must exist and stay in every locale ----------------
const loanKeys = ['title', 'requestedBy', 'requestSummary', 'reason', 'reference', 'details', 'item', 'amount', 'requestedAmount', 'approvedAmount', 'remaining', 'status', 'requestedAt', 'footerNote', 'print']
const locales = ['en', 'fa-AF', 'ps-AF']
const messages = {}
for (const lang of locales) {
  messages[lang] = JSON.parse(await read(`resources/ts/plugins/i18n/locales/${lang}.json`))
  assert.ok(messages[lang].documents.loan, `${lang}: documents.loan is missing`)
  for (const key of loanKeys)
    assert.equal(typeof messages[lang].documents.loan[key], 'string', `${lang}: documents.loan.${key} is missing`)
  assert.deepEqual(Object.keys(messages[lang].documents.loan).sort(), [...loanKeys].sort(), `${lang}: documents.loan keys differ`)
}

// --- render the real receipt template for a loan and an income -------------------
const file = 'resources/ts/pages/templates/receipt_template.vue'
const { descriptor, errors } = parse(await read(file), { filename: file })
assert.deepEqual(errors, [])
const scopeId = 'data-v-loan-receipt-test'
const script = compileScript(descriptor, { id: scopeId, inlineTemplate: true, templateOptions: { ssr: true } })

const printSource = await read('resources/ts/utils/printReceipt.ts')
const printing = load(printSource, { './printDocument': {} })

const renderComponent = async (locale, data) => {
  let active = locale
  const t = key => key.split('.').reduce((value, part) => value?.[part], messages[active]) ?? key
  const component = load(script.content, {
    'vue-i18n': { useI18n: () => ({ t, locale: ref(active) }) },
    '@/utils/localization': { formatLocalizedDate: value => String(value ?? '').slice(0, 10) },
    '@/utils/printReceipt': printing,
  }).default
  component.__scopeId = scopeId
  const app = createSSRApp(component, { receipt: data, paperSize: 'A5', copies: 1 })
  app.config.globalProperties.translateBackendValue = value => value
  const rendered = await renderToString(app)
  active = locale

  return rendered
}

for (const style of descriptor.styles) {
  const result = compileStyle({ source: style.content, id: scopeId, scoped: style.scoped })
  assert.deepEqual(result.errors, [])
  assert.ok(!result.code.includes('@page'), `${file} must never declare @page`)
}
const css = descriptor.styles.map(style => compileStyle({ source: style.content, id: scopeId, scoped: style.scoped }).code).join('\n')
require('postcss').parse(css).walkAtRules('media', media => {
  if (media.params === 'print') {
    media.walkRules(rule => {
      assert.ok(rule.selectors.every(selector => selector.startsWith('body.income-receipt-print-mode')), 'print styles must be namespaced by the print-mode body class')
    })
  }
})

const currency = { id: 1, code: 'USD', symbol: '$', decimalPlaces: 2, rate: 1 }
const loanDoc = {
  serial_number: 'LN-2026-000123', document: 'loan', date: '2026-10-11',
  company: { name: 'Education Firm', phone: '0700000000', address: 'Kabul' },
  applicant: { id: 12, name: 'Applicant Name', father_name: 'Father Name', contact: '0701234567' },
  income: { id: 123, name: 'Tuition deposit', type: 'income', line_total: 250, currency },
  contract: null, service: null,
  services: [{ kind: 'loan', id: 123, name: 'Tuition deposit', converted_fee: 250, fee: 250, currency }],
  totals: { total_fee: 250, paid_amount: 0, balance: 250, currency },
  letterhead: { url: null, safe: { x: 5, y: 5, width: 90, height: 90 } },
  signature: { url: null }, recorded_by: { id: 1, name: 'Finance Officer' },
  loan: { status: 'submitted', transaction_type: 'income', requested_at: '2026-10-11', approved_at: null, approver: null, rejection_reason: null },
}
const incomeDoc = {
  ...loanDoc, serial_number: 'RCPT-000123', document: 'receipt', loan: null,
  contract: { id: 1, contract_number: 'CON-00012' },
  service: { id: 1, name: 'Scholarship', service_label: 'Scholarship', converted_fee: 250, fee: 250, currency, payment_status_label: 'Partially Paid' },
  totals: { total_fee: 250, paid_amount: 250, balance: 0, currency },
}

const loanMarkup = await renderComponent('en', loanDoc)
for (const label of ['Loan Request', 'Requested By', 'Request Summary', 'Loan ID', 'Loan Details', 'Requested Amount', 'Approved Amount', 'Not Yet Approved', 'Requested On', 'LN-2026-000123'])
  assert.ok(loanMarkup.includes(label), `the loan receipt must render "${label}"`)
// The income-only sections must not leak into the loan document.
for (const label of ['Received From', 'Contracted Services', 'Amount Received', 'Not linked to a contract'])
  assert.ok(!loanMarkup.includes(label), `the loan receipt must not show the income label "${label}"`)

const incomeMarkup = await renderComponent('en', incomeDoc)
for (const label of ['Payment Receipt', 'Received From', 'Payment Summary', 'Contracted Services', 'Amount Received', 'RCPT-000123'])
  assert.ok(incomeMarkup.includes(label), `the income receipt must still render "${label}"`)
assert.ok(!incomeMarkup.includes('Loan Request'), 'the income receipt must not show the loan title')

// Localized loan titles must resolve (not fall back to the raw key).
for (const lang of ['fa-AF', 'ps-AF']) {
  const markup = await renderComponent(lang, loanDoc)
  assert.ok(markup.includes(messages[lang].documents.loan.title), `${lang}: the loan title must be localized`)
  assert.ok(!markup.includes('documents.loan.title'), `${lang}: the loan title must not fall back to the key`)
  assert.ok(markup.includes('dir="rtl"'), `${lang}: the loan receipt must render RTL`)
}

console.log('Loan receipt wiring, RBAC mirror, i18n parity (3 locales) and loan/income label switching passed. Browser/PDF layout is not verified.')
