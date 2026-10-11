import { readFileSync, writeFileSync, mkdirSync } from 'node:fs'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import assert from 'node:assert/strict'
const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))
const require = createRequire(root + '/package.json')
const { parse, compileScript } = require('@vue/compiler-sfc')
const ts = require('typescript')
const sass = require('sass')
const output = process.env.EDUCATIONAL_UI_OUTPUT ?? '/tmp/educational-erp-preview'
mkdirSync(output, { recursive: true })
const files = ['components/erp/ErpField.vue', 'components/erp/ErpDateRange.vue', 'components/erp/ErpPageHeader.vue', 'components/erp/ApplicantSummary.vue', 'components/ApplicantServiceFields.vue', 'components/responsive-data-table/DataTableFilterMenu.vue', 'pages/service-contracts.vue']
const factories = files.map(file => {
  const { descriptor } = parse(readFileSync(root + '/resources/ts/' + file, 'utf8'), { filename: file })
  const compiled = compileScript(descriptor, { id: file, inlineTemplate: true })
  const code = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText
  return `factories[${JSON.stringify('@/' + file)}] = (require, module, exports) => {${code}};`
}).join('\n')
const style = sass.compile(root + '/resources/ts/components/erp/workspace.scss').css
for (const locale of ['en', 'fa-AF']) for (const service of ['scholarship', 'test_offer', 'academy', 'private']) {
  const messages = JSON.parse(readFileSync(root + '/resources/ts/plugins/i18n/locales/' + locale + '.json', 'utf8'))
  const html = `<!doctype html><html dir="${locale === 'en' ? 'ltr' : 'rtl'}"><head><meta charset="utf-8"><link rel="stylesheet" href="file://${root}/node_modules/vuetify/dist/vuetify.css"><link rel="stylesheet" href="file://${root}/resources/ts/plugins/iconify/icons.css"><style>body{font-family:Arial,sans-serif}.erp-workspace{padding:24px}${style}</style></head><body><div id="app"></div><script src="file://${root}/node_modules/vue/dist/vue.global.js"></script><script src="file://${root}/node_modules/vuetify/dist/vuetify.js"></script><script>
const messages = ${JSON.stringify(messages)};
const service = ${JSON.stringify(service)};
const t = key => key.split('.').reduce((node,part)=>node?.[part], messages) ?? key;
const factories = {}, cache = {};
const data = { id: 1, name: 'Example applicant', service, contact: '0701234567', details: { program: {name:'English'}, academy_class: {name:'Morning'}, education_level: {name:'Bachelor'}, test: {name:'IELTS'} } };
window.requests = [];
const rows = [1,2,3].map(id => ({id, service, contract_number:'CON-000'+id, counterparty:'Example applicant '+id, counterparty_contact:'0701234567', applicant:data, status:'active', services:[], start_date:'2026-10-08'}));
const contracts = {getAll: async params=>{window.requests.push({params});return rows}, serviceOptions: async()=>({currencies:[{id:1,code:'USD',rate:1}], languages:[{id:1,name:'English'}],employees:[],admission_employees:[],template:{id:1,name:service+' Agreement',versions:[{id:1,name:'Standard 2026',version_number:1,country_id:['private','scholarship'].includes(service)?1:null,language_id:1,status:'active',is_current:true}]}}), createService:async(service,data)=>{window.requests.push({service,data});return {...rows[0], id:4}}};
const mocks = {
'vue':Vue, 'vuetify/components':Vuetify,
'vue-i18n':{useI18n:()=>({t, n: x=>String(x)})},
'@casl/vue':{useAbility:()=>({can:()=>true})},
'vue-router':{useRoute:()=>({meta:{builtInService:service},query:{},name:(service==='test_offer'?'test-offer':service)+'-contracts'})},
'vue-toastification':{useToast:()=>({error:console.error,success:()=>{}})},
'@/components/PrintableListReport.vue':{default:{render:()=>null}}, '@/utils/printDocument':{printDocument:async()=>{}},
'@/utils/authorization':{canAccess:()=>true}, '@/utils/apiErrors':{apiErrorMessage:()=> 'Error'},
'@/api/contracts':{default:contracts,serviceContractApplicantFields:(_service,form)=>({...form})},
'@/api/visitor':{default:{getAll:async()=>[]},visitorApplicantFields:x=>x},
'@/api/educationalMasterData':{default:{list:async catalog=>[{id:1,name:catalog==='countries'?'Canada':catalog==='tests'?'IELTS':catalog==='programs'?'English':'Example '+catalog,is_active:true}]}},
'@/api/contractType':{default:{getAll:async()=>[]}},
'@/components/contract/ContractDocumentPrinter.vue':{default:{render:()=>null}},
'./workspace.scss':{}
};
${factories}
function require(name){if(mocks[name]) return mocks[name]; if(cache[name])return cache[name].exports; const module={exports:{}}; cache[name]=module; if(!factories[name])throw new Error(name); factories[name](require,module,module.exports);return module.exports;}
const vuetify = Vuetify.createVuetify({locale:{rtl:{en:false,fa:true},locale:${JSON.stringify(locale==='en'?'en':'fa')}},theme:{defaultTheme:'light'},icons:{defaultSet:'class',sets:{class:{component:props=>Vue.h('i',{class:props.icon})}}}});
window.testTheme = theme=> {vuetify.theme.change(theme)};
const component = require('@/pages/service-contracts.vue').default;
const app=Vue.createApp({render:()=>Vue.h(Vuetify.VApp,null,{default:()=>Vue.h(component)})});app.use(vuetify);app.config.globalProperties.$t=t;
app.component('DataTableFilterMenu', require('@/components/responsive-data-table/DataTableFilterMenu.vue').default);
app.component('RouterLink',{props:['to'],setup:props=>()=>Vue.h('a',{href:'#'},'')});app.mount('#app');
</script></body></html>`
  writeFileSync(output + '/' + service + '-' + locale + '.html', html)
}
console.log('Compiled actual service contract forms and shared components into ' + output)

if (process.argv.includes('--browser')) {
  const browserModule = await import(process.env.PLAYWRIGHT_MODULE_PATH ?? 'playwright')
  const { chromium } = browserModule.default ?? browserModule
  const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE, headless: true, args: ['--no-sandbox','--disable-crashpad-for-testing', '--allow-file-access-from-files'] })
  try {
    for (const locale of ['en', 'fa-AF']) for (const service of ['scholarship', 'test_offer', 'academy', 'private']) {
      const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } })
      const failures = []
      page.on('pageerror', error => failures.push(error.message))
      await page.goto('file://' + output + '/' + service + '-' + locale + '.html')
      await page.locator('.v-data-table tbody tr').first().waitFor()
      const zebra = await page.locator('.v-data-table tbody tr td:first-child').evaluateAll(cells => cells.slice(0,2).map(cell => getComputedStyle(cell).backgroundColor))
      assert.notEqual(zebra[0], zebra[1], 'Zebra rows must have different backgrounds')
      await page.locator('.erp-header-actions button').first().click()
      await page.locator('.erp-form .erp-field').first().waitFor()
      await page.locator('.erp-form .v-progress-linear').waitFor({state:'hidden'})
      const widths = await page.locator('.erp-form .v-row').first().locator('.erp-field').evaluateAll(fields => fields.map(field => Math.round(field.getBoundingClientRect().width)))
      assert.ok(Math.max(...widths)-Math.min(...widths) < 3, JSON.stringify(widths))
      assert.equal(await page.locator('.erp-form-actions button').last().isDisabled(), true)
      const labels = await page.locator('.erp-form .erp-field-label').allTextContents()
      const messages = JSON.parse(readFileSync(root + '/resources/ts/plugins/i18n/locales/' + locale + '.json','utf8'))
      const expected = service==='private'?['applicationFee','visaFee','requestedFields','percentage']:service==='test_offer'?['backupPhone','bookingFee','serviceFee','remoteFee']:service==='academy'?['class','serviceFee']:['scholarship','applicationFee']
      for (const key of expected) assert.ok(labels.some(label=>label.includes(messages.erp[key])),key)
      await page.screenshot({path:output+'/'+service+'-'+locale+'-desktop.png'})
      await page.setViewportSize({width:390,height:844})
      assert.ok(await page.locator('.erp-form .v-card').evaluate(card => card.scrollWidth <= card.clientWidth + 2),'Dialog must fit mobile width')
      await page.screenshot({path:output+'/'+service+'-'+locale+'-mobile.png'})
      await page.setViewportSize({width:1440,height:1000})
      await page.evaluate(()=>window.testTheme('dark'))
      await page.screenshot({path:output+'/'+service+'-'+locale+'-dark.png'})
      assert.deepEqual(failures,[])
      await page.close()
    }
    console.log('Four real Vue forms passed desktop/mobile, LTR/RTL, dark/light, required field and zebra checks.')
  } finally { await browser.close() }
}
