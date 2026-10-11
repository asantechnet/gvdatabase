import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import ts from 'typescript'

const source = readFileSync(new URL('../resources/ts/utils/incomePaymentBalance.ts', import.meta.url), 'utf8')
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext } }).outputText
const { paymentEntryLimit, changedIncomeFields, servicePaymentIssue, submitServicePayments } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`)

const line = { id: 1, payable_total: 100, amount_paid: 60, remaining_total: 40, currency_rate: 1 }
const original = { id: 5, applicant_id: 3, applicant_contract_id: 1, currency_id: 1, amount: 60, quantity: 1, rate: 1, description: 'Before', type: 'Service', date: '2026-10-07' }

assert.equal(paymentEntryLimit(line, 1), 40)
assert.equal(paymentEntryLimit(line, 1, original), 100)
assert.equal(paymentEntryLimit({ ...line, amount_paid: 100, remaining_total: 0 }, 1, { ...original, amount: 100 }), 100)
assert.equal(paymentEntryLimit({ ...line, id: 2 }, 1, original), 40)
assert.equal(paymentEntryLimit(line, 2, original), 200)
assert.equal(paymentEntryLimit({ ...line, currency_rate: 2 }, 4, { ...original, rate: 2 }), 200)
assert.deepEqual(changedIncomeFields(original, { ...original, amount: '60.000000', rate: '1.00000000', description: 'After' }), { description: 'After' })
assert.deepEqual(changedIncomeFields(original, { ...original, applicant_contract_id: null }), { applicant_contract_id: null })
assert.deepEqual(changedIncomeFields(original, { ...original, amount: 20, rate: 2 }), { amount: 20, rate: 2 })
assert.deepEqual(changedIncomeFields(original, original), {})
assert.equal(paymentEntryLimit({ ...line, amount_paid: 10, remaining_total: 90, amount_paid_overridden: true }, 1, original), 100)
assert.equal(paymentEntryLimit({ ...line, amount_paid: 150, remaining_total: 0, amount_paid_overridden: true }, 1, original), 10)
console.log('Income payment regressions: 12 assertions passed.')

const entry = () => ({ line: { ...line, currency: { id: 1 }, is_cancelled: false }, selected: true, amount: 20, rate: 1, state: 'pending' })
assert.equal(servicePaymentIssue(entry()), null)
assert.equal(servicePaymentIssue({ ...entry(), amount: 0 }), 'amount')
assert.equal(servicePaymentIssue({ ...entry(), amount: '' }), 'amount')
assert.equal(servicePaymentIssue({ ...entry(), rate: 0 }), 'rate')
assert.equal(servicePaymentIssue({ ...entry(), rate: 0.000000001 }), 'rate')
assert.equal(servicePaymentIssue({ ...entry(), amount: 41 }), 'balance')
assert.equal(servicePaymentIssue({ ...entry(), amount: 80, rate: 2 }), null)
assert.equal(servicePaymentIssue({ ...entry(), line: { ...entry().line, remaining_total: 0 } }), 'unavailable')
assert.equal(servicePaymentIssue({ ...entry(), line: { ...entry().line, is_cancelled: true } }), 'unavailable')
assert.equal(servicePaymentIssue({ ...entry(), line: { ...entry().line, currency: null } }), 'unavailable')
const entries = [entry(), { ...entry(), line: { ...entry().line, id: 2 } }, { ...entry(), selected: false }]
const recorded = []
let requests = []
await submitServicePayments(entries, async item => {
  requests.push(item.line.id)
  if (item.line.id === 2)
    throw { status: 422 }
  return { ...original, id: item.line.id }
}, receipt => recorded.push(receipt.id))
assert.deepEqual(requests, [1, 2])
assert.deepEqual(entries.map(item => item.state), ['saved', 'failed', 'pending'])
assert.deepEqual(recorded, [1])
requests = []
await submitServicePayments(entries, async item => {
  requests.push(item.line.id)
  return { ...original, id: item.line.id }
}, receipt => recorded.push(receipt.id))
assert.deepEqual(requests, [2])
assert.deepEqual(recorded, [1, 2])
const uncertain = [entry()]
await submitServicePayments(uncertain, async () => { throw { code: 'server.error' } }, () => assert.fail('No confirmed receipt'))
assert.equal(uncertain[0].state, 'uncertain')
await submitServicePayments(uncertain, async () => assert.fail('An unconfirmed payment must not be retried'), () => {})
assert.equal(uncertain[0].state, 'uncertain')
const rejected = [entry()]
await submitServicePayments(rejected, async () => { throw { code: 'validation_failed' } }, () => {})
assert.equal(rejected[0].state, 'failed')
console.log('Service payment batch regressions: 18 assertions passed.')

async function loadWithStubs(file, prelude) {
  const source = readFileSync(new URL(file, import.meta.url), 'utf8')
  const result = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext },
    transformers: { before: [context => root => ts.visitNode(root, function visit(node) {
      return ts.isImportDeclaration(node) ? undefined : ts.visitEachChild(node, visit, context)
    })] },
  }).outputText

  return import(`data:text/javascript;base64,${Buffer.from(prelude + result).toString('base64')}`)
}

const authorization = await loadWithStubs('../resources/ts/utils/apiAuthorization.ts', 'const canAccess = permission => permission === "view_incomes";\n')
const overrideUrl = '/incomes/applicant-contracts/12/payment-override'
assert.deepEqual(authorization.permissionsForApiRequest('PUT', overrideUrl), ['override_contract_payments'])
assert.equal(authorization.isApiRequestAllowed('PUT', overrideUrl), false)
assert.equal(authorization.isApiRequestAllowed('GET', '/incomes/applicants'), true)
const validation = await loadWithStubs('../resources/ts/utils/apiValidation.ts', 'const translate = key => key;\n')
const validateOverride = data => validation.validateApiRequest({ method: 'put', url: overrideUrl, data })
assert.deepEqual(validateOverride({ amount_paid: null, payment_status: null }), {})
assert.deepEqual(validateOverride({ amount_paid: 0, payment_status: 'partially_paid' }), {})
assert.deepEqual(Object.keys(validateOverride({})).sort(), ['amount_paid', 'payment_status'])
assert.deepEqual(Object.keys(validateOverride({ amount_paid: -1, payment_status: null })), ['amount_paid'])
assert.deepEqual(Object.keys(validateOverride({ amount_paid: null, payment_status: 'pending' })), ['payment_status'])
for (const grants of [[], ['override_contract_payments'], ['view_incomes'], ['view_incomes', 'override_contract_payments']]) {
  const scoped = await loadWithStubs('../resources/ts/utils/apiAuthorization.ts', 'const canAccess = permission => ' + JSON.stringify(grants) + '.includes(permission);\n')
  assert.equal(scoped.isApiRequestAllowed('PUT', overrideUrl), grants.length === 2)
}
console.log('Payment API authorization/validation regressions: 12 assertions passed.')
