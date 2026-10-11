import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

/**
 * Contract workflow regression.
 *
 * Guards three related changes:
 *  - employment and other agreements extend into a linked draft (like academy),
 *  - cancelling keeps the record: it stays in its own list with a cancelled
 *    label, is no longer printable, and is still fully viewable from both its
 *    own page and the cancelled contracts page,
 *  - the shared details popup no longer renders its footer disclaimer.
 */

const root = new URL('../', import.meta.url)
const read = file => readFile(new URL(file, root), 'utf8')

const readme = {
  list: 'resources/ts/pages/contract_list.vue',
  form: 'resources/ts/components/ContractForm.vue',
  service: 'resources/ts/pages/service-contracts.vue',
  cancelled: 'resources/ts/pages/cancelled_contracts.vue',
  details: 'resources/ts/components/FullDetailsButton.vue',
  api: 'resources/ts/api/contracts.ts',
  controller: 'app/Http/Controllers/ContractController.php',
}

const [list, form, service, cancelled, details, api, controller] = await Promise.all([
  read(readme.list), read(readme.form), read(readme.service),
  read(readme.cancelled), read(readme.details), read(readme.api), read(readme.controller),
])

// --- extension on the employment + other contract list ---------------------------
for (const snippet of [
  "const canExtend = (contract: Contract) => ability.can('read', 'create_contracts')",
  "contract.contract_role !== 'extension'",
  "['active', 'completed'].includes(contract.status)",
  'v-if="canExtend(item)"',
  '@click="extendContract(item)"',
  'const extensionParent = ref<Contract | null>(null)',
  'parent_contract_id: parent?.id ?? null',
  "...(parent ? { status: 'draft' as const } : {})",
  ':parent-contract="extensionParent"',
  ':extension-mode="Boolean(extensionParent)"',
])
  assert.ok(list.includes(snippet), `${readme.list} must contain: ${snippet}`)

// A cancelled contract keeps a cancelled label, loses its print action and can
// still be opened in the details popup.
assert.ok(list.includes("ability.can('read', 'cancel_contracts') && !item.is_cancelled"), `${readme.list} must gate the cancel action with cancel_contracts`)
assert.ok(list.includes('v-if="!item.is_cancelled"'), `${readme.list} must hide the print action for cancelled contracts`)
assert.ok(list.includes('cancelledContractPrintBlocked'), `${readme.list} must refuse the cancelled print deep link`)
assert.ok(list.includes("v-if=\"item.is_cancelled\""), `${readme.list} must keep the cancelled chip on the row`)

// --- the form issues the extension as an editable draft --------------------------
for (const snippet of [
  'interface ContractFormSource',
  'parentContract?: ContractFormSource | null',
  'extensionMode?: boolean',
  'const formSource = props.contract ?? props.parentContract ?? null',
  "props.extensionMode ? 'draft' :",
  'const initialStartDate = ()',
  'parent_contract_id: props.extensionMode ? (props.parentContract?.id ?? null) : null',
  ':disabled="extensionMode"',
  "tr('extendingNotice'",
  "tr('extensionStatusHint')",
])
  assert.ok(form.includes(snippet), `${readme.form} must contain: ${snippet}`)

assert.ok(api.includes('parent_contract_id?: number | null'), `${readme.api} must type the extension parent`)

// --- backend: link the parent, force a draft, keep the origin in the snapshot ----
for (const snippet of [
  "'parent_contract_id' => ['nullable', 'integer', $exists('contracts')]",
  'private function extensionParent(Request $request, ContractTemplate $template, ?int $parentId): ?Contract',
  '$parent->contract_role === Contract::ROLE_PRIMARY',
  "'contract_role' => $parent ? Contract::ROLE_EXTENSION : Contract::ROLE_PRIMARY",
  "'parent_contract_id' => $parent?->id",
  "'status' => $parent ? 'draft' : ($data['status'] ?? 'active')",
  "'extension_of' => $parent->contract_number",
  "'business.contract_extension_invalid'",
])
  assert.ok(controller.includes(snippet), `${readme.controller} must contain: ${snippet}`)

// --- service contracts: view + cancel, nothing else once cancelled --------------
for (const snippet of [
  '<FullDetailsButton :item="item"',
  "const canCancel = (row: Contract) => ability.can('read', 'cancel_contracts')",
  "['draft', 'active'].includes(row.status)",
  'v-if="canCancel(item)"',
  '@click="requestCancel(item)"',
  'await ContractApi.cancel(target.id)',
  'serviceContracts.cancelConfirmation',
])
  assert.ok(service.includes(snippet), `${readme.service} must contain: ${snippet}`)

// Print, slip and extend must all sit behind the "not cancelled" guard.
const notCancelledGuard = service.indexOf('<template v-if="!item.is_cancelled">')
for (const action of ['printable = item; printing = true', 'openSlip(item)', 'create(item)'])
  assert.ok(service.indexOf(action) > notCancelledGuard, `the ${action} action must be hidden for cancelled contracts`)

// --- cancelled contracts page: counterparty wording + retained dates ------------
for (const snippet of [
  "pages.contracts.allContracts.allContracts.counterparty",
  "pages.contracts.allContracts.allContracts.filterByCounterparty",
  "key: 'cancelled_at'",
  'reportRows',
  'cancelled_at ? formatLocalizedDate(item.cancelled_at',
])
  assert.ok(cancelled.includes(snippet), `${readme.cancelled} must contain: ${snippet}`)
assert.ok(!cancelled.includes('common.messages.client'), `${readme.cancelled} must not label the column "Client"`)
assert.ok(!cancelled.includes('filterByClient'), `${readme.cancelled} must not use the legacy client filter`)

// --- popup footer disclaimer removed --------------------------------------------
assert.ok(!details.includes('detail-viewer__footer-note'), `${readme.details} must not render the footer note`)
assert.ok(!details.includes('business-friendly'), `${readme.details} must not reference the removed disclaimer`)
assert.ok(!details.includes('fullDetails.footer'), `${readme.details} must not resolve the removed footer key`)

// --- locale parity for every key this work adds or removes ----------------------
const locales = ['en', 'fa-AF', 'ps-AF']
const messages = {}
for (const lang of locales)
  messages[lang] = JSON.parse(await read(`resources/ts/plugins/i18n/locales/${lang}.json`))

const dig = (object, path) => path.split('.').reduce((value, part) => value?.[part], object)
const required = [
  'educational.printPeriod',
  'educational.printGeneratedAt',
  'common.fields.cancelledAt',
  'serviceContracts.cancelContract',
  'serviceContracts.cancelConfirmation',
  'serviceContracts.cancelledDone',
  'pages.contracts.allContracts.allContracts.extendContract',
  'pages.contracts.allContracts.allContracts.extendedDraftCreated',
  'pages.contracts.allContracts.allContracts.cancelledContractPrintBlocked',
  'pages.contracts.contractform.contractform.extendingNotice',
  'pages.contracts.contractform.contractform.extensionStatusHint',
  // Still referenced by staff_salary.vue, so this must stay in every locale.
  'common.messages.noRecordsFound',
]
for (const lang of locales) {
  for (const key of required)
    assert.equal(typeof dig(messages[lang], key), 'string', `${lang}: ${key} is missing`)
  assert.equal(dig(messages[lang], 'components.fullDetails.footer'), undefined, `${lang}: the removed popup footer key is still present`)
  assert.equal(dig(messages[lang], 'pages.contracts.cancelledContracts.cancelledContracts.filterByClient'), undefined, `${lang}: the legacy client filter label is still present`)
}

const translatable = ['educational.printPeriod', 'common.fields.cancelledAt', 'serviceContracts.cancelContract', 'pages.contracts.allContracts.allContracts.extendContract']
for (const key of translatable) {
  const values = locales.map(lang => dig(messages[lang], key))
  assert.equal(new Set(values).size, locales.length, `${key} must be translated per locale (got ${values.join(' | ')})`)
}

console.log('Contract extension wiring (employment + other), cancelled print/view matrix, counterparty rename and popup footer removal passed; i18n parity verified across 3 locales.')
