import assert from 'node:assert/strict'
import { existsSync, readFileSync } from 'node:fs'
import ts from 'typescript'
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc'

async function load(file, prelude = '') {
  const source = readFileSync(new URL(file, import.meta.url), 'utf8')
  const compiled = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext },
    transformers: { before: [context => root => ts.visitNode(root, function visit(node) {
      return ts.isImportDeclaration(node) ? undefined : ts.visitEachChild(node, visit, context)
    })] },
  }).outputText
  return import(`data:text/javascript;base64,${Buffer.from(prelude + compiled).toString('base64')}`)
}

const applicant = await load('../resources/ts/api/applicants.ts', 'const api = {};\n')
globalThis.serviceContractTestPayload = applicant.applicantServicePayload
globalThis.serviceContractTestRequests = []
const contracts = await load('../resources/ts/api/contracts.ts', `
const applicantServicePayload = globalThis.serviceContractTestPayload;
const api = {
  post: async (url, data) => { globalThis.serviceContractTestRequests.push({ url, data }); return { data: { data: { id: 12, ...data } } }; },
  get: async url => { globalThis.serviceContractTestRequests.push({ url }); return { data: { data: { currencies: [], languages: [], employees: [] } } }; }
};\n`)
for (const service of ['scholarship', 'test_offer', 'academy', 'private']) {
  const result = contracts.serviceContractApplicantFields(service, {
    service, name: 'Applicant', email: '', id: 99, status: 'active',
    passport_id: 'P123', country_id: 1, test_id: 2, program_id: 3,
    teacher_ids: [4], skill_ids: [5], favorite_subject_ids: [6],
  })
  assert.equal(result.name, 'Applicant')
  assert.equal(result.email, null)
  for (const forbidden of ['service', 'id', 'status', 'applicant_id', 'service_id', 'package_id', 'company_id'])
    assert.equal(Object.hasOwn(result, forbidden), false)
  assert.equal(Object.hasOwn(result, 'program_id'), service === 'academy')
  assert.equal(Object.hasOwn(result, 'test_id'), service === 'test_offer')
  assert.equal(Object.hasOwn(result, 'country_id'), ['scholarship', 'private'].includes(service))
  const created = await contracts.default.createService(service, { ...result, fee: 100 })
  assert.equal(created.id, 12)
  assert.equal(globalThis.serviceContractTestRequests.at(-1).url, `/contracts/${service}`)
  assert.deepEqual(await contracts.default.serviceOptions(service), { currencies: [], languages: [], employees: [] })
}
const authorization = await load('../resources/ts/utils/apiAuthorization.ts', 'const canAccess = () => true;\n')
const validation = await load('../resources/ts/utils/apiValidation.ts', 'const translate = key => key;\n')
const visitors = await load('../resources/ts/api/visitor.ts', 'const api = {};\n')
assert.deepEqual(authorization.permissionsForApiRequest('POST', '/visitors/1/convert-to-applicant'), ['create_applicants'])
assert.doesNotMatch(readFileSync(new URL('../resources/ts/api/visitor.ts', import.meta.url), 'utf8'), /convertToCustomer|convert-to-customer/)
assert.deepEqual(visitors.visitorApplicantFields({ name: 'Visitor', contact: '0700000000', email: null, service: 'ignored', company: 'ignored' }), { name: 'Visitor', contact: '0700000000', email: null })
const notification = await load('../resources/ts/utils/notificationText.ts', 'const translateBackendValue = value => value;\n')
for (const locale of ['en', 'fa-AF', 'ps-AF']) {
  const messages = JSON.parse(readFileSync(new URL('../resources/ts/plugins/i18n/locales/' + locale + '.json', import.meta.url), 'utf8'))
  const t = (key, values = {}) => String(key.split('.').reduce((node, part) => node?.[part], messages) ?? key).replace(/\{(\w+)\}/g, (_, name) => values[name] ?? '')
  for (const [service, label] of Object.entries({ scholarship: 'scholarship', test_offer: 'testOffer', academy: 'academy', private: 'private' })) {
    for (const event of ['contract.generated', 'contract.extensionGenerated']) {
      const result = notification.notificationText({ notification_key: event + '.1', data: { translation_key: event, service, contract_number: 'C001' }, type: 'task' }, t)
      assert.ok(result.message.includes(messages.educational.builtInServices[label]))
      assert.ok(!result.title.startsWith('notifications.'))
    }
  }
  for (const key of ['common.fields.service', 'common.validation.required', 'documents.receipt.paymentSummary'])
    assert.notEqual(t(key), key)
}
const parties = await load('../resources/ts/utils/contractPartyDetails.ts')
const partyDetails = parties.partyDetailsFor({
  type_slug: 'customer', counterparty: 'Live name', counterparty_contact: 'Live phone',
  snapshot_data: { entity: { applicant: { name: 'Frozen applicant', contact: 'Frozen phone' } }, parties: { our_company: { name: 'Frozen firm' } } },
}, { brandName: 'Live firm' })
assert.ok(partyDetails.counterparty.some(field => field.value === 'Frozen applicant'))
assert.ok(partyDetails.counterparty.some(field => field.value === 'Frozen phone'))
assert.ok(partyDetails.ourCompany.some(field => field.value === 'Frozen firm'))
assert.equal(parties.canPrintPartyDetails({ type_slug: 'company' }), false)
const headers = await load('../resources/ts/composables/useContractPrintHeader.ts', `
const translate = key => key;
const translateBackendValue = value => value;
const formatLocalizedDate = value => value;
const formatLocalizedNumber = value => String(value);
const contractTypeSlug = contract => contract.type_slug;\n`)
const header = headers.buildContractPrintHeader({
  type_slug: 'customer', contract: 'Academy', counterparty: 'Changed name',
  applicant: { name: 'Changed applicant' }, services: [{ fee: 1000 }],
  snapshot_data: { entity: { applicant: { name: 'Issued applicant' }, services: [{ fee: 100, discount: 20, currency_code: 'USD' }] } },
})
assert.ok(header.sections[0].rows.some(row => row.value === 'Issued applicant'))
assert.ok(header.sections[0].rows.some(row => row.value === '80 USD'), JSON.stringify(header))
assert.ok(header.sections[0].rows.every(row => row.value !== 'Changed applicant'))
assert.doesNotMatch(readFileSync(new URL('../resources/ts/utils/contractPartyDetails.ts', import.meta.url), 'utf8'), /entity\.company|contract\.customer|counterpartyCompany/)
for (const service of ['scholarship', 'test_offer', 'academy', 'private']) {
  assert.deepEqual(authorization.permissionsForApiRequest('POST', `/contracts/${service}`), ['create_contracts'])
  assert.deepEqual(authorization.permissionsForApiRequest('GET', `/contracts/${service}/options`), ['create_contracts'])
  assert.deepEqual(validation.validateApiRequest({ method: 'post', url: `/contracts/${service}`, data: { name: 'Applicant', fee: 100, discount: 0, currency_id: 1, currency_rate: 1, language_id: 1, start_date: '2026-10-08' } }), {})
  assert.ok(Object.hasOwn(validation.validateApiRequest({ method: 'post', url: `/contracts/${service}`, data: { currency_rate: 0 } }), 'currency_rate'))
}

assert.deepEqual(validation.validateApiRequest({ method: 'post', url: '/contracts', data: { template_id: 1, counterparty: 'Partner' } }), {})
assert.ok(Object.hasOwn(validation.validateApiRequest({ method: 'post', url: '/contracts', data: {} }), 'template_id'))
const routes = readFileSync(new URL('../resources/ts/plugins/1.router/routes.ts', import.meta.url), 'utf8')
assert.doesNotMatch(routes, /path: '\/contracts'|name: 'contracts'|path: '\/company-contracts'/)
// The retired ledgers stay retired. The contract-driven commission ledger was
// deliberately restored (docs/commissions-visitor-relationships-plan.md), so
// `commissions` is no longer part of this guard.
assert.doesNotMatch(routes, /path: '\/(companies|invoices|payment-details)'/)
for (const file of ['api/company.ts', 'api/invoice.ts', 'pages/companies.vue', 'pages/innvoice.vue', 'api/services.ts', 'api/packages.ts', 'pages/options/services.vue'])
  assert.equal(existsSync(new URL(`../resources/ts/${file}`, import.meta.url)), false, `${file} must stay retired`)
for (const file of ['api/incomes.ts', 'api/visitor.ts', 'api/contracts.ts'])
  assert.doesNotMatch(readFileSync(new URL(`../resources/ts/${file}`, import.meta.url), 'utf8'), /\bcompany_id\b|\binvoice_id\b/)
for (const file of ['layouts/components/DefaultLayoutWithVerticalNav.vue', 'layouts/components/DefaultLayoutWithHorizontalNav.vue'])
  assert.doesNotMatch(readFileSync(new URL(`../resources/ts/${file}`, import.meta.url), 'utf8'), /useServiceContractNavigation|ServiceApi/)
assert.doesNotMatch(readFileSync(new URL('../resources/ts/components/ContractForm.vue', import.meta.url), 'utf8'), /ServiceApi|service_lines|service_ids|company_id/)
assert.doesNotMatch(readFileSync(new URL('../resources/ts/utils/apiAuthorization.ts', import.meta.url), 'utf8'), /issue_packages|educational-services|view_educational_services|manage_educational_services/)

function computedFrom(file, name, bindings) {
  const source = readFileSync(new URL(`../resources/ts/${file}`, import.meta.url), 'utf8')
  const script = ts.createSourceFile(file + '.ts', parse(source).descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS)
  const declaration = script.statements.filter(ts.isVariableStatement)
    .flatMap(statement => [...statement.declarationList.declarations])
    .find(declaration => declaration.name.getText(script) === name)
  assert.ok(declaration?.initializer, name)
  const compiled = ts.transpileModule(`const result = ${declaration.initializer.getText(script)};`, {
    compilerOptions: { target: ts.ScriptTarget.ESNext },
  }).outputText
  return new Function('computed', ...Object.keys(bindings), `${compiled}\nreturn result;`)(callback => callback(), ...Object.values(bindings))
}
const editorComputed = (name, bindings) => computedFrom('pages/contract/policies.vue', name, bindings)

// A test-offer extension carries no form fields, so it must resolve the same
// test scope as the primary applicant; a fresh contract uses the form's test.
function testOfferMatch(formTestId, parentTestId) {
  const result = computedFrom('pages/service-contracts.vue', 'matchingVersion', {
    selectedTemplate: { value: { versions: [{ test_id: 7, country_id: null, language_id: 1, status: 'active', is_current: true }] } },
    languageId: { value: 1 },
    service: { value: 'test_offer' },
    form: { value: { test_id: formTestId } },
    parent: { value: parentTestId === undefined ? null : { applicant: { details: { test_id: parentTestId } } } },
  })
  return result?.test_id ?? null
}
assert.equal(testOfferMatch(7, undefined), 7)
assert.equal(testOfferMatch(undefined, 7), 7)
assert.equal(testOfferMatch(9, 7), null)
assert.equal(testOfferMatch(undefined, undefined), null)
for (const [id, saved, locked] of [[null, null, false], [12, null, false], [12, 'academy', true]]) {
  assert.equal(editorComputed('serviceBindingLocked', { templateId: { value: id }, savedBuiltInService: { value: saved } }), locked)
}
for (const service of ['scholarship', 'test_offer', 'academy', 'private']) {
  const bindings = {
    builtInServices: ['scholarship', 'test_offer', 'academy', 'private'], builtInLabel: value => value,
    templateId: { value: null }, templates: { value: [{ id: 12, service }] },
  }
  let items = editorComputed('serviceItems', bindings)
  assert.deepEqual(items.map(item => item.value), bindings.builtInServices)
  assert.equal(items.find(item => item.value === service).props.disabled, true)
  assert.ok(items.filter(item => item.value !== service).every(item => !item.props.disabled))
  bindings.templateId.value = 12
  items = editorComputed('serviceItems', bindings)
  assert.ok(items.every(item => !item.props.disabled))
}

const files = ['components/erp/ErpPageHeader.vue', 'components/erp/ErpDateRange.vue', 'components/erp/ErpField.vue', 'components/erp/ApplicantSummary.vue', 'pages/applicants.vue', 'pages/search.vue', 'pages/educational-reports.vue', 'pages/applicant-pipeline.vue', 'pages/all_customers.vue', 'pages/cancelled_contracts.vue', 'pages/home.vue', 'pages/service-contracts.vue', 'pages/visitor.vue', 'components/ApplicantServiceFields.vue', 'components/ContractForm.vue', 'pages/contract_list.vue', 'pages/applicant-case.vue', 'layouts/components/DefaultLayoutWithVerticalNav.vue', 'layouts/components/DefaultLayoutWithHorizontalNav.vue', 'layouts/components/NavSearchBar.vue', 'pages/educational-dashboard.vue', 'pages/notifications.vue', 'components/DataTablePageKpis.vue', 'components/contract/CounterpartyCard.vue', 'pages/contract/policies.vue', 'pages/setup/settings.vue', 'pages/educational-master-data.vue', 'components/task-manager/components/TaskDetailDrawer.vue', 'pages/audits.vue', 'components/crm/CrmRelationshipDetails.vue']
for (const file of files) {
  const source = readFileSync(new URL(`../resources/ts/${file}`, import.meta.url), 'utf8')
  const { descriptor, errors } = parse(source)
  assert.deepEqual(errors, [])
  const script = compileScript(descriptor, { id: file })
  const result = compileTemplate({ source: descriptor.template.content, filename: file, id: file, compilerOptions: { bindingMetadata: script.bindings } })
  assert.deepEqual(result.errors, [])
}
const english = JSON.parse(readFileSync(new URL('../resources/ts/plugins/i18n/locales/en.json', import.meta.url), 'utf8'))
function assertRetiredKeysAbsent(messages) {
  for (const [key, value] of Object.entries(messages)) {
    // `commission` is intentionally excluded: the contract-driven commission
    // ledger was restored deliberately (docs/commissions-visitor-relationships-plan.md).
    assert.doesNotMatch(key, /invoice|innvoice|outsourcing|companies|payment[_-]?Details|package|variant/i)
    if (value && typeof value === 'object')
      assertRetiredKeysAbsent(value)
  }
}
assertRetiredKeysAbsent(english)
const leafKeys = (object, prefix = '') => Object.entries(object).flatMap(([key, value]) => value && typeof value === 'object'
  ? leafKeys(value, prefix + key + '.')
  : [prefix + key])
for (const locale of ['fa-AF', 'ps-AF']) {
  const messages = JSON.parse(readFileSync(new URL(`../resources/ts/plugins/i18n/locales/${locale}.json`, import.meta.url), 'utf8'))
  assertRetiredKeysAbsent(messages)
  for (const group of ['serviceContracts', 'educational', 'finance.paymentOverrides', 'finance.servicePayments', 'erp']) {
    const translatedGroup = group.split('.').reduce((node, part) => node?.[part], messages)
    const englishGroup = group.split('.').reduce((node, part) => node?.[part], english)
    assert.deepEqual(leafKeys(translatedGroup).sort(), leafKeys(englishGroup).sort(), locale + ':' + group)
    assert.ok(leafKeys(translatedGroup).every(key => String(key.split('.').reduce((node, part) => node?.[part], translatedGroup)).length > 0))
  }
  // The KPI captions that no component renders any more were retired from the
  // catalogue; only the live scroll hint must stay translated.
  assert.ok(messages.components.dataTablePageKpis.scrollHint)
  assert.deepEqual(Object.keys(messages.serviceContracts).sort(), Object.keys(english.serviceContracts).sort())
  assert.ok(Object.values(messages.serviceContracts).every(value => typeof value === 'string' && value.length > 0))
  assert.deepEqual(Object.keys(messages.educational.salesTypes).sort(), Object.keys(english.educational.salesTypes).sort())
  assert.ok(messages.educational.salesTypes.academy)
}
// Both screens print the contract through the same component, so the A4 print
// workflow stays a single path. The browser-level cover/content page assertions
// (including the overlay scroll-lock and RTL) live in contract-print-regression.mjs,
// which mounts this exact component.
const serviceContractsSource = readFileSync(new URL('../resources/ts/pages/service-contracts.vue', import.meta.url), 'utf8')
assert.match(serviceContractsSource, /<ContractDocumentPrinter\s+v-model="printing"/, 'service contracts must print through the shared contract document printer')
const documentPrinterSource = readFileSync(new URL('../resources/ts/components/contract/ContractDocumentPrinter.vue', import.meta.url), 'utf8')
assert.match(documentPrinterSource, /bodyClass: 'printing-contract-document'/, 'contracts must print under their own print-mode body class')
assert.match(documentPrinterSource, /pageStyle: 'size: A4; margin: 0;'/, 'contracts must print on A4')

console.log('Contract payloads, frozen party details, API wrappers, permission mapping, finance/catalogue retirement guards, Vue templates, locale keys and the shared A4 contract print path passed. No browser automation used.')
delete globalThis.serviceContractTestPayload
delete globalThis.serviceContractTestRequests
