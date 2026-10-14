import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Staff Directory hierarchy-field regression.
 *
 * The add/edit staff dialogs both bind `department_id` and `supervisor_id`
 * (labelled Department and "Reports to"). A copy/paste slip previously rendered
 * the pair twice in the add dialog while the edit dialog had no way to set them
 * at all, so this script compiles the real SFC template and counts the controls
 * inside each dialog instead of grepping the raw text.
 *
 * It also asserts the create/update payloads still carry both ids, so removing a
 * control cannot silently stop the backend from receiving the values.
 */

const root = fileURLToPath(new URL('..', import.meta.url))
const require = createRequire(join(root, 'package.json'))
const { parse } = require('@vue/compiler-sfc')

const file = 'resources/ts/pages/staff_list.vue'
const source = readFileSync(join(root, file), 'utf8')
const { descriptor, errors } = parse(source, { filename: file })

assert.deepEqual(errors, [], `${file} must parse`)

const ELEMENT = 1
const DIRECTIVE = 7

const hierarchyFields = [
  { field: 'department_id', label: 'common.fields.department' },
  { field: 'supervisor_id', label: 'common.fields.supervisor' },
]

const elements = node => (node.children ?? []).filter(child => child.type === ELEMENT)
const descendants = node => elements(node).flatMap(child => [child, ...descendants(child)])

const tagName = node => node.tag
const directive = (node, name) => (node.props ?? []).find(prop => prop.type === DIRECTIVE && prop.name === name)
const boundModel = node => directive(node, 'model')?.exp?.content ?? ''
const boundLabel = node => {
  const bind = (node.props ?? []).find(prop => prop.type === DIRECTIVE && prop.name === 'bind' && prop.arg?.content === 'label')

  return bind?.exp?.content ?? ''
}

const dialogs = ['isAddStaffDialogVisible', 'isEditStaffDialogVisible'].map(flag => {
  const dialog = descendants(descriptor.template.ast).find(node => tagName(node) === 'VDialog' && boundModel(node).includes(flag))

  assert.ok(dialog, `the ${flag} dialog must still exist`)

  const selects = descendants(dialog).filter(node => tagName(node) === 'VSelect')

  return { flag, selects }
})

for (const { flag, selects } of dialogs) {
  for (const { field, label } of hierarchyFields) {
    const matches = selects.filter(node => boundModel(node).includes(field))

    assert.equal(matches.length, 1, `${flag} must render exactly one ${field} control, found ${matches.length}`)
    assert.ok(boundLabel(matches[0]).includes(label), `${flag} must label the ${field} control from ${label}`)
  }
}

// Both fields stay editable after creation and are submitted on create and update.
const payloadBindings = source.match(/^\s+(department_id|supervisor_id): selectedStaff\.value\.\1,/gm) ?? []

assert.equal(payloadBindings.length, 4, 'the create and update payloads must each send department_id and supervisor_id')

console.log('staff-form-hierarchy-regression: ok (1 department + 1 reports-to per add/edit dialog, both submitted)')
