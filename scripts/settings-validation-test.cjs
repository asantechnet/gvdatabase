const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const ts = require('typescript')
const vue = require('vue')
const { parse: parseTemplate } = require('@vue/compiler-dom')
const { parse } = require('@vue/compiler-sfc')

const root = process.cwd()
let locale = 'en'
const catalogs = Object.fromEntries(['en', 'fa-AF', 'ps-AF'].map(name => [name,
  JSON.parse(fs.readFileSync(path.join(root, 'resources/ts/plugins/i18n/locales', `${name}.json`), 'utf8')),
]))
const lookup = (catalog, key) => key.split('.').reduce((value, part) => value?.[part], catalog)
const translate = (key, values = {}) => {
  const message = lookup(catalogs[locale], key) ?? lookup(catalogs.en, key)
  return typeof message === 'string'
    ? message.replace(/\{(\w+)\}/g, (match, parameter) => String(values[parameter] ?? match))
    : `[missing:${key}]`
}
const notifications = []
const cache = new Map()

// Execute the actual TypeScript helpers with a deterministic locale and toast sink.
function load(file) {
  file = path.resolve(root, file)
  if (cache.has(file)) return cache.get(file).exports
  const module = { exports: {} }
  cache.set(file, module)
  const code = ts.transpileModule(fs.readFileSync(file, 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText
  const dependency = name => {
    if (name === 'vue') return vue
    if (name === 'vue-toastification') return { useToast: () => ({ error: message => notifications.push(message) }) }
    if (name === '@/utils/localization') return {
      translate,
      translateOrFallback: (key, fallback) => lookup(catalogs[locale], key) || lookup(catalogs.en, key) ? translate(key) : fallback,
    }
    if (name.startsWith('@/')) return load(`resources/ts/${name.slice(2)}.ts`)
    if (name.startsWith('.')) return load(path.join(path.dirname(file), `${name}.ts`))
    return require(name)
  }
  // The production auto-import plugin supplies translate for legacy utilities.
  new Function('require', 'module', 'exports', 'translate', code)(dependency, module, module.exports, translate)
  return module.exports
}

async function main() {
  const validation = load('resources/ts/api/settingValidation.ts')
  const { apiErrorMessage } = load('resources/ts/utils/apiErrors.ts')
  const { useSettingsValidation } = load('resources/ts/composables/useSettingsValidation.ts')
  const { validateApiRequest } = load('resources/ts/utils/apiValidation.ts')
  assert.deepEqual(validateApiRequest({ method: 'patch', url: '/settings/1', data: { mail_port: 587 } }), {})
  assert.ok(validateApiRequest({ method: 'patch', url: '/settings/1', data: { website_title: '' } }).website_title)
  for (const language of Object.keys(catalogs)) {
    locale = language
    for (const key of Object.values(validation.settingFieldLabels)) assert.equal(typeof lookup(catalogs[language], key), 'string', key)
    const errors = validation.settingsFieldErrors({ errors: { website_title: [translate('backend.validation.required')] } })
    assert.ok(errors.website_title.includes(validation.settingFieldLabel('website_title')))
    assert.notEqual(errors.website_title, translate('backend.validation.required'))
  }
  locale = 'en'
  const serverError = {
    code: 'validation_failed', errors: { mail_port: ['validation.between'] },
    messages: { mail_port: ['SMTP port must be between 1 and 65535.'] },
  }
  assert.match(validation.settingsFieldErrors(serverError).mail_port, /65535/)
  assert.match(apiErrorMessage(serverError), /65535/)
  assert.match(validation.settingsFieldErrors({ errors: { receipt_letterhead_safe_width: ['validation.numeric'] } }).receipt_letterhead_safe_width, /Receipt.*Width/i)
  assert.match(validation.settingsErrorMessage({ errors: { phone: ['Required.'], website_title: ['Required.'], timezone: ['Required.'], physical_address: ['Required.'] } }, 'Failed'), /1 more fields/)

  const feedback = useSettingsValidation()
  let revealed
  let scrolled = false
  let focused = false
  global.document = { querySelectorAll: () => [{
    dataset: { settingsField: 'mail_port' }, getClientRects: () => [1],
    scrollIntoView: () => { scrolled = true }, matches: () => false,
    querySelector: () => ({ focus: () => { focused = true } }),
  }] }
  await feedback.capture(serverError, 'Failed', field => { revealed = field })
  assert.equal(revealed, 'mail_port')
  assert.ok(scrolled && focused)
  assert.equal(feedback.inputProps('mail_port').name, 'mail_port')
  assert.match(feedback.inputProps('mail_port')['error-messages'][0], /65535/)
  await feedback.capture({ errors: { company_email: ['validation.email'] } }, 'Failed')
  feedback.inputProps('company_email')['onUpdate:modelValue']()
  assert.deepEqual(feedback.inputProps('company_email')['error-messages'], [])
  assert.match(feedback.inputProps('mail_port')['error-messages'][0], /65535/)
  feedback.clear()
  assert.deepEqual(feedback.errors.value, {})
  await feedback.capture({ errors: { 'attachments.0': ['validation.max'] }, messages: { 'attachments.0': ['The file must not exceed 10 MB.'] } }, 'Failed')
  assert.match(feedback.inputProps('attachments')['error-messages'][0], /10 MB/)
  feedback.inputProps('attachments')['onUpdate:modelValue']()
  assert.deepEqual(feedback.inputProps('attachments')['error-messages'], [])
  delete global.document
  assert.equal(notifications.length, 3)

  assert.equal(validation.settingsUploadError({ type: 'image/gif', size: 1 }, 'website_logo'), null)
  assert.match(validation.settingsUploadError({ type: 'image/gif', size: 1 }, 'company_signature'), /JPG, PNG, WEBP/)
  assert.match(validation.settingsUploadError({ type: 'image/png', size: 3 * 1024 * 1024 }, 'website_logo'), /2 MB/)
  assert.match(validation.settingsUploadError({ type: 'image/png', size: 9 * 1024 * 1024 }, 'letterhead'), /8 MB/)

  // All editable Vuetify controls must receive explicit field-error props.
  const controls = new Set(['VTextField', 'VTextarea', 'VSelect', 'VSwitch', 'VCheckbox', 'VFileInput', 'AppSelect', 'AppTextField', 'AppTextarea'])
  for (const file of ['resources/ts/pages/setup/settings.vue', 'resources/ts/components/setup/OfficeSettingsPanel.vue', 'resources/ts/pages/support.vue']) {
    const { descriptor } = parse(fs.readFileSync(path.join(root, file), 'utf8'))
    let count = 0
    function visit(node) {
      if (node.type === 1 && controls.has(node.tag)) {
        assert.ok(node.props.some(prop => prop.type === 7 && prop.name === 'bind' && !prop.arg), `${file}: ${node.tag} has no field-error binding`)
        count++
      }
      for (const child of node.children ?? []) visit(child)
    }
    visit(parseTemplate(descriptor.template.content))
    assert.ok(count >= (file.endsWith('support.vue') ? 5 : 10), `${file}: no controls audited`)
  }
  console.log('Settings validation passed: localized field descriptions, limits, error bindings, focus, independent correction, uploads and editor coverage.')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
