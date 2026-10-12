import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readFile } from 'node:fs/promises'
import ts from 'typescript'
import { compileScript, compileStyle, parse } from '@vue/compiler-sfc'
import { createSSRApp, ref } from 'vue'
import { renderToString } from '@vue/server-renderer'

/**
 * Payment request regression: a payment request reuses the income receipt
 * template, so this guards the wiring (routes, controller, service, API
 * contract, RBAC mirror), the label switching between documents, and the two
 * print layouts — money in prints an income receipt, money out prints a
 * payment voucher.
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
assert.ok(routes.includes("foreach (['payment-requests', 'loans', 'pending-transactions'] as $paymentRequestPath)"), 'payment-requests must be the canonical route with loans/pending-transactions aliases')
assert.ok(routes.includes('"{$paymentRequestPath}/{pendingTransaction}/receipt"'), 'the receipt endpoint must be registered for every payment-request prefix')

const controller = await read('app/Http/Controllers/PendingTransactionController.php')
assert.ok(controller.includes('use App\\Services\\LoanReceiptService;'), 'the controller must import LoanReceiptService')
assert.ok(/public function receipt\(PendingTransaction \$pendingTransaction, LoanReceiptService \$receipts\)/.test(controller), 'the controller must expose receipt()')

const service = await read('app/Services/LoanReceiptService.php')
assert.ok(service.includes("'document' => 'loan'"), 'the loan payload must be tagged as a loan document')
assert.ok(service.includes("'letterhead' => DocumentLetterheads::resolve(\$setting, 'receipt')"), 'the loan payload must reuse receipt letterheads')
assert.ok(service.includes("sprintf('PR-%s-%06d'"), 'the payment-request payload must carry a stable PR serial')

const api = await read('resources/ts/api/pendingTransactions.ts')
assert.ok(api.includes('receipt: async (id: number)'), 'the payment-request API must expose receipt(id)')
assert.ok(api.includes('/payment-requests/${id}/receipt'), 'the API must call the payment-request receipt endpoint')
assert.ok(!api.includes("'/loans'"), 'the API contract must not call the legacy loans prefix')

const authorization = await read('resources/ts/utils/apiAuthorization.ts')
assert.ok(authorization.includes("'GET /payment-requests/:id/receipt': ['view_pending_transactions']"), 'the client RBAC mirror must allow the payment-request document')
assert.ok(authorization.includes("'GET /loans/:id/receipt': ['view_pending_transactions']"), 'the client RBAC mirror must keep the legacy loans alias')

const printer = await read('resources/ts/components/income/ReceiptPrinter.vue')
assert.ok(printer.includes('loader: (id: number) => Promise<ReceiptPayload>'), 'the shared printer must take a loader')
assert.ok(printer.includes('recordId: number | null'), 'the shared printer must take a record id')
assert.ok(printer.includes("document?: 'receipt' | 'loan'"), 'the shared printer must support both documents')

// --- i18n: both document label sets must exist in every locale -------------------
const loanKeys = ['title', 'requestedBy', 'requestSummary', 'reason', 'reference', 'details', 'item', 'amount', 'requestedAmount', 'approvedAmount', 'remaining', 'status', 'requestedAt', 'footerNote', 'print']
const voucherKeys = ['title', 'date', 'print', 'payTo', 'approval', 'voucherType', 'reason', 'approvedAt', 'requestedBy', 'approvedBy', 'rejectionReason', 'breakdown', 'totalPayout', 'paidStatus', 'baseAmount', 'note', 'preparedBy', 'approvedBySignature']
const locales = ['en', 'fa-AF', 'ps-AF']
const messages = {}
for (const lang of locales) {
  messages[lang] = JSON.parse(await read(`resources/ts/plugins/i18n/locales/${lang}.json`))
  assert.ok(messages[lang].documents.loan, `${lang}: documents.loan is missing`)
  for (const key of loanKeys)
    assert.equal(typeof messages[lang].documents.loan[key], 'string', `${lang}: documents.loan.${key} is missing`)
  assert.deepEqual(Object.keys(messages[lang].documents.loan).sort(), [...loanKeys].sort(), `${lang}: documents.loan keys differ`)
  assert.ok(messages[lang].documents.paymentVoucher, `${lang}: documents.paymentVoucher is missing`)
  for (const key of voucherKeys)
    assert.equal(typeof messages[lang].documents.paymentVoucher[key], 'string', `${lang}: documents.paymentVoucher.${key} is missing`)
  assert.deepEqual(Object.keys(messages[lang].documents.paymentVoucher).sort(), [...voucherKeys].sort(), `${lang}: documents.paymentVoucher keys differ`)
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
  serial_number: 'PR-2026-000123', document: 'loan', date: '2026-10-11',
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

// A money-in request keeps the income-slip layout.
const loanMarkup = await renderComponent('en', loanDoc)
for (const label of ['Loan Request', 'Requested By', 'Request Summary', 'Loan ID', 'Loan Details', 'Requested Amount', 'Approved Amount', 'Not Yet Approved', 'Requested On', 'PR-2026-000123'])
  assert.ok(loanMarkup.includes(label), `the payment-request receipt must render "${label}"`)
// The income-only sections must not leak into the request document.
for (const label of ['Received From', 'Contracted Services', 'Amount Received', 'Not linked to a contract'])
  assert.ok(!loanMarkup.includes(label), `the payment-request receipt must not show the income label "${label}"`)
assert.ok(!loanMarkup.includes('Payment Voucher'), 'a money-in request must not print the expense voucher')

// A money-out request (expense) prints the payment voucher instead.
const voucherDoc = {
  ...loanDoc,
  serial_number: 'PR-2026-000456',
  income: { ...loanDoc.income, type: 'expense', line_total: 400, base_amount: 400 },
  services: [{ kind: 'loan', id: 456, name: 'Office rent', converted_fee: 400, fee: 400, currency }],
  totals: { total_fee: 400, paid_amount: 0, balance: 400, currency },
  loan: { status: 'approved', transaction_type: 'expense', requested_at: '2026-10-11', approved_at: '2026-10-12', approver: { id: 2, name: 'Finance Manager' }, rejection_reason: null, payee_type: 'applicant', payee_name: 'Applicant Name', employee: null, reference: 'PR-2026-000456' },
}
const voucherMarkup = await renderComponent('en', voucherDoc)
for (const label of ['Payment Voucher', 'Pay To', 'Approval', 'Voucher Type', 'Payment Breakdown', 'Total Payout', 'Voucher Status', 'Prepared By', 'PR-2026-000456'])
  assert.ok(voucherMarkup.includes(label), `the payment voucher must render "${label}"`)
// The income-slip sections must not leak into the voucher.
for (const label of ['Loan Request', 'Request Summary', 'Requested Amount', 'Approved Amount', 'Received From', 'Amount Received', 'Contracted Services'])
  assert.ok(!voucherMarkup.includes(label), `the payment voucher must not show the receipt label "${label}"`)

// Localized voucher titles must resolve in RTL locales too.
for (const lang of ['fa-AF', 'ps-AF']) {
  const markup = await renderComponent(lang, voucherDoc)
  assert.ok(markup.includes(messages[lang].documents.paymentVoucher.title), `${lang}: the voucher title must be localized`)
  assert.ok(!markup.includes('documents.paymentVoucher.title'), `${lang}: the voucher title must not fall back to the key`)
  assert.ok(markup.includes('dir="rtl"'), `${lang}: the voucher must render RTL`)
}

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

console.log('Payment request wiring, RBAC mirror, i18n parity (3 locales) and receipt/voucher layout switching passed. Browser/PDF layout is not verified.')
