import { readFileSync, writeFileSync, mkdirSync } from 'node:fs'
import { createRequire } from 'node:module'
import path from 'node:path'
import assert from 'node:assert/strict'

const root = process.cwd()
const require = createRequire(root + '/package.json')
const { parse, compileScript } = require('@vue/compiler-sfc')
const ts = require('typescript')
const validationCode = ts.transpileModule(readFileSync('resources/ts/utils/apiValidation.ts', 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText
const validationModule = { exports: {} }
const english = JSON.parse(readFileSync('resources/ts/plugins/i18n/locales/en.json', 'utf8'))
const translate = key => key.split('.').reduce((object, part) => object?.[part], english) ?? key
new Function('require', 'module', 'exports', validationCode)(() => ({ translate }), validationModule, validationModule.exports)
const validate = validationModule.exports.validateApiRequest
assert.deepEqual(validate({ method: 'post', url: '/incomes', data: { type: 'Consulting', amount: 10, quantity: 1, currency_id: 1, rate: 1, date: '2026-10-09' } }), {})
assert.equal(validate({ method: 'post', url: '/settings', data: {} }).website_address, undefined)
assert.deepEqual(validate({ method: 'patch', url: '/settings/1', data: { website_address: null } }), {})
assert.deepEqual(validate({ method: 'put', url: '/employees/2', data: { status: 'inactive' } }), {})
console.log('Frontend request validation passed for custom income types, optional website links, and employee status.')
const output = '/tmp/educational-finance-types-preview'
mkdirSync(output, { recursive: true })
const filename = 'resources/ts/components/setup/FinanceTypesPage.vue'
const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
assert.deepEqual(errors, [])
const compiled = compileScript(descriptor, { id: filename, inlineTemplate: true })
const code = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText
const financeLabels = ts.transpileModule(readFileSync('resources/ts/utils/financeTypes.ts', 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText

for (const locale of ['en', 'fa-AF', 'ps-AF']) for (const kind of ['income', 'expense']) {
  const messages = JSON.parse(readFileSync(`resources/ts/plugins/i18n/locales/${locale}.json`, 'utf8'))
  writeFileSync(`${output}/${kind}-${locale}.html`, `<!doctype html><html dir="${locale === 'en' ? 'ltr' : 'rtl'}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="file://${root}/node_modules/vuetify/dist/vuetify.min.css"><style>body{margin:0}.v-application__wrap{padding:16px}</style></head><body><div id="app"></div><script src="file://${root}/node_modules/vue/dist/vue.global.js"></script><script src="file://${root}/node_modules/vuetify/dist/vuetify.min.js"></script><script>
const {ref,computed,onMounted}=Vue;
const messages=${JSON.stringify(messages)}, kind=${JSON.stringify(kind)};
const t=(key,values={})=>{let text=key.split('.').reduce((obj,part)=>obj?.[part],messages); if(typeof text!=='string') throw new Error('Missing translation '+key);return text.replace(/\\{(\\w+)\\}/g,(_,name)=>String(values[name]??''))};
const useI18n=()=>({t});
let records=[{id:1,kind,value:kind==='income'?'Service':'Utilities',name:kind==='income'?'Service':'Utilities',is_system:true}];
window.requests=[];window.toasts=[];
const api={getFinanceTypes:async requestedKind=>{window.requests.push({method:'GET',kind:requestedKind});return records.map(x=>({...x}))},saveFinanceType:async(requestedKind,name,id)=>{window.requests.push({method:id?'PUT':'POST',kind:requestedKind,name,id});if(!id){const row={id:2,kind:requestedKind,value:name,name,is_system:false};records.push(row);return row}const row=records.find(x=>x.id===id);row.name=name;return row},deleteFinanceType:async(requestedKind,id)=>{window.requests.push({method:'DELETE',kind:requestedKind,id});records=records.filter(x=>x.id!==id)}};
const mocks={'@/api/financeTypes':api,'@/utils/localization':{translateBackendValue:value=>{const key=value.toLowerCase().replace(/[^\\p{L}\\p{N}\\s_-]/gu,'').replace(/[_\\s-]+(.)/g,(_,letter)=>letter.toUpperCase());return t('common.statuses.'+key)}},'@/utils/apiErrors':{apiErrorMessage:(error,fallback)=>fallback,apiFieldErrors:()=>({})},'@/utils/authorization':{canAccess:()=>true},'vue-toastification':{useToast:()=>({success:message=>window.toasts.push(message),error:message=>{throw new Error(message)}})},vue:Vue};
const factories={},cache={};factories['@/utils/financeTypes']=(require,module,exports)=>{${financeLabels}};factories['page']=(require,module,exports)=>{${code}};
function require(name){if(mocks[name])return mocks[name];if(cache[name])return cache[name].exports;const module={exports:{}};cache[name]=module;factories[name](require,module,module.exports);return module.exports}
const vuetify=Vuetify.createVuetify({locale:{locale:${JSON.stringify(locale === 'en' ? 'en' : 'fa')},rtl:{en:false,fa:true}},icons:{defaultSet:'class',sets:{class:{component:props=>Vue.h('i',{class:props.icon})}}}});
window.setTheme=value=>vuetify.theme.change(value);
const app=Vue.createApp({render:()=>Vue.h(Vuetify.VApp,null,{default:()=>Vue.h(require('page').default,{kind})})});app.use(vuetify);app.component('ResponsiveDataTable',Vuetify.VDataTable);app.component('IconBtn',{setup:(_,ctx)=>()=>Vue.h(Vuetify.VBtn,{...ctx.attrs,icon:true,variant:'text'},ctx.slots)});app.mount('#app');
</script></body></html>`)
}
console.log('Compiled actual finance type pages into ' + output)
if (process.argv.includes('--browser')) {
  const module = await import(process.env.PLAYWRIGHT_MODULE_PATH ?? 'playwright')
  const { chromium } = module.default ?? module
  const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE, headless: true, args: ['--no-sandbox', '--disable-crashpad-for-testing', '--allow-file-access-from-files'] })
  try {
    for (const locale of ['en', 'fa-AF', 'ps-AF']) for (const kind of ['income', 'expense']) {
      const messages = JSON.parse(readFileSync(`resources/ts/plugins/i18n/locales/${locale}.json`, 'utf8'))
      const common = messages.common.actions
      const singular = messages.pages.finance.types[kind + 'Type']
      const interpolate = text => text.replace('{item}', singular)
      const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } })
      const failures = []
      page.on('pageerror', error => failures.push(error.message))
      await page.goto(`file://${output}/${kind}-${locale}.html`)
      await page.locator('tbody tr').first().waitFor()
      await page.getByRole('button', { name: interpolate(common.addItem), exact: true }).click()
      await page.locator('.v-dialog input').fill('Consulting')
      await page.getByRole('button', { name: common.save, exact: true }).click()
      await page.getByRole('cell', { name: 'Consulting', exact: true }).waitFor()
      await page.getByRole('row').filter({ hasText: 'Consulting' }).getByRole('button', { name: interpolate(common.editItem), exact: true }).click()
      await page.locator('.v-dialog input').fill('Advisory')
      await page.getByRole('button', { name: common.save, exact: true }).click()
      await page.getByRole('cell', { name: 'Advisory', exact: true }).waitFor()
      await page.setViewportSize({ width: 390, height: 844 })
      await page.evaluate(() => window.setTheme('dark'))
      await page.getByRole('button', { name: interpolate(common.addItem), exact: true }).click()
      assert.ok(await page.locator('.v-dialog .v-card').evaluate(card => card.scrollWidth <= card.clientWidth + 2), 'Dialog fits mobile')
      await page.getByRole('button', { name: common.cancel, exact: true }).click()
      assert.ok(await page.locator('body').evaluate(body => body.scrollWidth <= innerWidth + 2), 'Page fits mobile')
      await page.setViewportSize({ width: 1440, height: 1000 })
      await page.getByRole('row').filter({ hasText: 'Advisory' }).getByRole('button', { name: interpolate(common.deleteItem), exact: true }).click()
      await page.getByRole('button', { name: common.delete, exact: true }).click()
      await page.getByRole('cell', { name: 'Advisory', exact: true }).waitFor({ state: 'hidden' })
      const requests = await page.evaluate(() => window.requests)
      assert.deepEqual(requests.filter(x => x.method !== 'GET').map(x => x.method), ['POST', 'PUT', 'DELETE'])
      assert.ok(requests.every(x => x.kind === kind))
      assert.deepEqual(failures, [])
      await page.close()
    }
    console.log('Both finance type pages passed CRUD, English/Dari/Pashto, LTR/RTL, desktop/mobile, and light/dark checks.')
  }
  finally { await browser.close() }
}
