import assert from 'node:assert/strict'
import { readFile, writeFile, mkdir } from 'node:fs/promises'
import { createRequire } from 'node:module'
import ts from 'typescript'
import { parse, compileScript, compileStyle } from '@vue/compiler-sfc'
import { createSSRApp, ref } from 'vue'
import { renderToString } from '@vue/server-renderer'
const require = createRequire(import.meta.url)
const root = new URL('../', import.meta.url)
const read = file => readFile(new URL(file, root), 'utf8')
const output = '/tmp/educational-agency-documents'
await mkdir(output, { recursive: true })
const compile = source => ts.transpileModule(source, {compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022}}).outputText
const messages = JSON.parse(await read('resources/ts/plugins/i18n/locales/en.json'))
const t = key => key.split('.').reduce((value,part)=>value?.[part],messages) ?? key
const logo = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="40"><rect width="100" height="40" fill="#673ab7"/><text x="8" y="27" fill="white">AGENCY</text></svg>')
const dependencies = {
  'vue-i18n':{useI18n:()=>({t})},
  '@/composables/useBranding':{useBranding:()=>({logoUrl:ref(logo),originalCompanyName:ref('Example Visa Academy')})},
  '@/utils/localization':{formatLocalizedDate:()=> 'October 9, 2026'},
  '@/utils/printDocument':{},
}
let css = ''
async function component(file) {
  const {descriptor} = parse(await read('resources/ts/'+file), {filename:file})
  const id = 'data-v-'+file.replace(/\W/g,'')
  const script = compileScript(descriptor,{id,inlineTemplate:true,templateOptions:{ssr:true}})
  const module = {exports:{}}
  new Function('require','module','exports','useI18n',compile(script.content))(name=>dependencies[name]??require(name),module,module.exports,()=>({t}))
  const comp = module.exports.default
  comp.__scopeId=id
  for (const style of descriptor.styles) {
    const result=compileStyle({source:style.content,id,scoped:style.scoped})
    assert.deepEqual(result.errors,[])
    assert.ok(!result.code.includes('@page'))
    css+='\n'+result.code
  }
  return comp
}
dependencies['@/components/contract/ContractPrintHeader.vue']={default:await component('components/contract/ContractPrintHeader.vue')}
const list=await component('components/PrintableListReport.vue')
const contract=await component('pages/templates/contract_page_template.vue')
const cover=await component('pages/templates/contract_cover_template.vue')
const payslip=await component('pages/templates/payslip_template.vue')
const globalStyle=require('sass').compileString(await read('resources/styles/styles.scss')).css
// Include the real global rules: route styles must coexist without contaminating print jobs.
css+='\n'+globalStyle
async function render(comp,props) {
  const app=createSSRApp(comp,props);app.config.globalProperties.$t=t;app.config.globalProperties.formatLocalizedDate=value=>value;app.config.globalProperties.$i18n={locale:"en"}
  return renderToString(app)
}
const company={brandName:'Example Visa Academy',name:'Example Visa Academy',logo,address:'Kabul',addressLines:['Kabul'],phone:'0700000000',email:'office@example.test'}
const short=await render(contract,{documentTitle:'Employee Agreement',documentNumber:'HR-001',company,htmlContent:'<h2>Employment terms</h2><p>COMPLETE-SHORT-TERMS</p>',showSignature:true,contracteeName:'Employee Name'})
const long=await render(contract,{documentTitle:'Private Contract',documentNumber:'PRI-001',company,htmlContent:Array.from({length:100},(_,i)=>'<p>Clause '+i+' — Detailed visa application and service obligations. '+(i===99?'COMPLETE-FINAL-CLAUSE':'')+'</p>').join(''),showSignature:true,contracteeName:'Applicant Name'})
const coverHtml=await render(cover,{logo,contractTitle:'Private Contract',contractNumber:'PRI-001',contracteeName:'Applicant Name',companyName:'Example Visa Academy'})
const listHtml=await render(list,{title:'Cancelled Contracts',subtitle:'Filtered scope: cancelled',columns:['Number','Applicant','Amount'],rows:Array.from({length:100},(_,i)=>['CON-'+i,'Applicant '+i,i===99?'COMPLETE-FINAL-ROW':0])})
const slip=await render(payslip,{company,employee:{name:'Employee Name',designation:'Admission',department:'Admissions',dateOfJoining:'2025-03-02',payPeriod:'October 2026',payDate:'2026-10-09',accountNumber:'EMP-1',workedDays:26,totalDays:30,paidLeaveDays:2,absentDays:0,unpaidLeaveDays:2},earnings:[{label:'Base salary',amount:1000}],deductions:[{label:'Leave',amount:100}],amountInWords:'Nine hundred',documentNumber:'PAY-001'})
assert.ok(short.includes('Example Visa Academy') && short.includes('COMPLETE-SHORT-TERMS') && short.includes(logo.replaceAll('&', '&amp;')))
assert.ok(long.includes('COMPLETE-FINAL-CLAUSE'))
assert.ok(listHtml.includes('Cancelled Contracts') && listHtml.includes('COMPLETE-FINAL-ROW') && listHtml.includes('Example Visa Academy'))
assert.ok(slip.includes('Employee Name') && slip.includes('2025-03-02') && slip.includes('Admissions'))
const source=compile(await read('resources/ts/utils/printDocument.ts'))
const browserScript=`const module={exports:{}};(function(require,module,exports){${source}})(()=>({nextTick:()=>Promise.resolve()}),module,module.exports);window.print=()=>{};window.startPrint=(mode,paper)=>module.exports.printDocument({bodyClass:mode,pageStyle:'size: '+paper+'; margin: 0;',element:document.querySelector('#source')});`
for (const [name,markup] of Object.entries({list:listHtml,short,long,cover:coverHtml,payslip:slip})) {
  await writeFile(output+'/'+name+'.html',`<!doctype html><html dir="ltr"><head><meta charset="utf-8"><style>*{box-sizing:border-box}body{font-family:Arial} :root{--v-theme-primary:103,58,183}</style><style>${css}</style></head><body><nav>UNWANTED-NAVIGATION</nav><header>UNWANTED-APP-HEADER</header><div class="layout-wrapper" style="padding:40px;min-height:100vh"><main id="source">${markup}</main></div><script>${browserScript}</script></body></html>`)
}
if(process.argv.includes('--browser')) {
  const pkg=await import(process.env.PLAYWRIGHT_MODULE_PATH??'playwright')
  const {chromium}=pkg.default??pkg
  const browser=await chromium.launch({executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,headless:true,args:['--no-sandbox','--disable-crashpad-for-testing','--allow-file-access-from-files']})
  try {
    for (const name of ['list','short','long','cover','payslip']) {
      const page=await browser.newPage()
      await page.goto('file://'+output+'/'+name+'.html')
      await page.evaluate(({name})=>window.startPrint(name==='list'?'printing-table-contracts':name==='payslip'?'printing-payslip-document':'printing-contract-document',name==='payslip'?'A5':'A4'),{name})
      await page.emulateMedia({media:'print'})
      assert.equal(await page.locator('nav').isVisible(),false)
      assert.equal(await page.locator('body > header').isVisible(),false)
      assert.equal(await page.locator('#app-print-host').isVisible(),true)
      await page.pdf({path:output+'/'+name+'.pdf',preferCSSPageSize:true,printBackground:true})
      await page.evaluate(()=>window.dispatchEvent(new Event('afterprint')))
      assert.equal(await page.locator('#app-print-host').count(),0)
      assert.equal(await page.locator('style[data-app-print-page]').count(),0)
      assert.equal(await page.evaluate(()=>document.body.className),'')
      await page.close()
    }
  }finally{await browser.close()}
}
console.log('Actual branded list, contract, cover and payslip components compiled in '+output+(process.argv.includes('--browser')?'; browser PDF/lifecycle checks passed.':'; browser PDF/layout checks not run.'))
