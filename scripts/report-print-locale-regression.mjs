import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readFile } from 'node:fs/promises'
import postcss from 'postcss'
import ts from 'typescript'
import { compileScript, compileStyle, parse } from '@vue/compiler-sfc'
import { createSSRApp, ref } from 'vue'
import { renderToString } from '@vue/server-renderer'

/**
 * Report print regression.
 *
 * The Finance → Reports print template printed a raw ISO timestamp and the Visa
 * Reports page printed the live Vuetify table, so a dark theme produced a dark
 * document. This guards the localized date formatter, the CSV/exports, and the
 * dedicated theme-independent print document.
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

// --- server-side date formatting ------------------------------------------------
const formatter = await read('app/Support/ReportDateFormatter.php')
for (const snippet of [
  "private const CARBON_LOCALES = [",
  "'fa' => 'fa_AF'",
  "'ps' => 'ps_AF'",
  'translatedFormat(',
  "->setTimezone(\$timezone)",
  "' (GMT'",
  'public static function human(?string \$value, ?string \$timezone = null): string',
  'public static function day(?string \$value): string',
])
  assert.ok(formatter.includes(snippet), `ReportDateFormatter must contain: ${snippet}`)

const locale = await read('app/Support/AcceptedLocale.php')
assert.ok(locale.includes('public static function resolve(?string $tag): ?string'), 'AcceptedLocale must resolve request tags')
assert.ok(locale.includes("str_replace('_', '-', \$value)"), 'AcceptedLocale must tolerate -/_ and case differences')
assert.ok(locale.includes('public static function fromRequest(Request $request): void'), 'AcceptedLocale must resolve the request language')

const middleware = await read('app/Http/Middleware/SetLocaleFromRequest.php')
assert.ok(middleware.includes('AcceptedLocale::fromRequest($request)'), 'the request middleware must use the shared locale resolver')

// --- the blade print template no longer prints raw machine values ---------------
const blade = await read('resources/views/reports/print.blade.php')
assert.ok(blade.includes("@use('App\\Support\\ReportDateFormatter')"), 'the print template must import the date formatter')
assert.ok(blade.includes("ReportDateFormatter::human(\$report['meta']['generated_at'], \$report['meta']['timezone'])"), 'the generated-at footer must be localized')
assert.ok(blade.includes("ReportDateFormatter::day(\$report['filters']['start_date'])"), 'the reporting period must be localized')
assert.ok(blade.includes("__('reports.print.generated_at', ['date' => \$generatedAt])"), 'the footer must print the formatted date')
assert.equal(blade.match(/\$report\['meta'\]\['generated_at'\]/g).length, 1, 'the raw generated_at value must only be formatted once')
assert.ok(!blade.includes("\$report['filters']['start_date'] }} — {{"), 'the raw filter dates must not be printed')
assert.equal(blade.match(/\{\{ \$period \}\}/g).length, 2, 'both reporting-period lines must use the formatted period')
// Unlike the SPA, this template is a standalone document in its own tab, so it
// legitimately owns its @page rule.
assert.ok(blade.includes('@page { size: A4 landscape; margin: 12mm; }'), 'the standalone print template must declare its page size once')

// --- both CSV exports pair the machine value with a readable one ----------------
const reportController = await read('app/Http/Controllers/ReportController.php')
assert.ok(reportController.includes('AcceptedLocale::fromRequest($request)'), 'the report export must resolve the request locale')
assert.ok(reportController.includes("fputcsv(\$stream, ['generated_at_utc', \$report['meta']['generated_at']])"), 'the CSV must keep the machine timestamp')
assert.ok(reportController.includes("fputcsv(\$stream, ['generated_at', ReportDateFormatter::human("), 'the CSV must add the localized timestamp')

const educationalController = await read('app/Http/Controllers/EducationalReportController.php')
assert.ok(educationalController.includes('AcceptedLocale::fromRequest($request)'), 'the visa report export must resolve the request locale')
assert.ok(educationalController.includes("fputcsv(\$stream, ['generated_at', ReportDateFormatter::human(\$generatedAt)])"), 'the visa CSV must add the localized timestamp')

// --- the SPA tells the server which language the document should use -------------
for (const file of ['resources/ts/api/report.ts', 'resources/ts/api/educationalReports.ts']) {
  const source = await read(file)
  assert.ok(source.includes("import { currentLocale } from '@/utils/localization'"), `${file} must read the active locale`)
  assert.ok(source.includes('locale: currentLocale()'), `${file} must send the active locale`)
}

// --- the Visa Reports page prints a dedicated document, not the live table -------
const page = await read('resources/ts/pages/educational-reports.vue')
assert.ok(page.includes("import EducationalReportPrint from '@/components/educational/EducationalReportPrint.vue'"), 'the page must use the print document')
assert.ok(page.includes('element: (printRef.value?.$el as HTMLElement | undefined) ?? null'), 'printDocument must print the dedicated element')
assert.ok(page.includes("pageStyle: 'size: A4 landscape; margin: 12mm;'"), 'the page size must travel with the print job')
assert.ok(page.includes("printGeneratedAt.value = formatLocalizedDate(new Date(), 'long')"), 'the generated-at stamp must be localized')
assert.ok(page.includes(':generated-at="printGeneratedAt"'), 'the print document must receive the stamp')
assert.ok(!page.includes('@page'), 'the page must not declare @page')
assert.ok(!page.includes('print-color-adjust'), 'the page must not rely on printing the themed screen view')
assert.ok(page.includes('body.printing-education-reports .education-panel { display: none !important; }'), 'the native print fallback must hide the screen table')

// --- the print document itself --------------------------------------------------
const printFile = 'resources/ts/components/educational/EducationalReportPrint.vue'
const printSource = await read(printFile)
assert.ok(printSource.includes('.education-print-report { display: none; }'), 'the print document must be hidden on screen')
assert.ok(!printSource.includes('var(--v-theme'), 'the print document must not depend on the active theme')
assert.ok(!printSource.includes('@page'), 'the print document must not declare @page; printDocument owns it')
assert.ok(printSource.includes('color: #1f2233'), 'the print document must force readable ink')
assert.ok(printSource.includes('background: #fff'), 'the print document must force a white paper background')
assert.ok(printSource.includes("t('educational.printPeriod')"), 'the print document must label the reporting period')
assert.ok(printSource.includes("t('educational.printGeneratedAt')"), 'the print document must label the generated-at stamp')
assert.ok(printSource.includes("t('common.crmUi.printGenerated', { date: generatedAt })"), 'the print document must render the localized stamp in its footer')

const { descriptor, errors } = parse(printSource, { filename: printFile })
assert.deepEqual(errors, [])

for (const style of descriptor.styles) {
  const compiled = compileStyle({ source: style.content, id: 'data-v-education-print-test', scoped: style.scoped })
  assert.deepEqual(compiled.errors, [])
  assert.ok(!compiled.code.includes('@page'), `${printFile} must never declare @page`)
}

const css = descriptor.styles.map(style => compileStyle({ source: style.content, id: 'data-v-education-print-test', scoped: style.scoped }).code).join('\n')
postcss.parse(css).walkAtRules('media', media => {
  if (media.params !== 'print')
    return
  media.walkRules(rule => {
    for (const selector of rule.selectors)
      assert.ok(
        selector.includes('body.printing-education-reports') || selector.includes('body.printing-isolated-document'),
        `print selector must be namespaced by a print-mode body class: ${selector}`,
      )
  })
})

// --- render the print document and verify its content ---------------------------
const scopeId = 'data-v-education-print-test'
const script = compileScript(descriptor, { id: scopeId, inlineTemplate: true, templateOptions: { ssr: true } })
const messages = JSON.parse(await read('resources/ts/plugins/i18n/locales/en.json'))
const t = (key, values = {}) => {
  const value = key.split('.').reduce((current, part) => current?.[part], messages) ?? key

  return String(value).replace(/\{(\w+)\}/g, (_, name) => String(values[name] ?? `{${name}}`))
}
const component = load(script.content, {
  'vue-i18n': { useI18n: () => ({ locale: ref('en'), t }) },
  '@/composables/useBranding': { useBranding: () => ({ logoUrl: '/logo.svg', originalCompanyName: 'Educational Visa Services' }) },
}).default
component.__scopeId = scopeId

const app = createSSRApp(component, {
  title: 'Visa reports',
  subtitle: 'Operational detail with downloadable CSV.',
  columns: ['Applicant', 'Country', 'Status'],
  rows: [['Ali Ahmadi', 'Turkey', 'active'], ['Sara Noor', 'Germany', 'closed']],
  facts: [{ label: 'Country', value: 'Turkey' }],
  period: 'Jan 1, 2026 — Jan 31, 2026',
  generatedAt: 'October 11, 2026 at 3:24 PM',
  totalLabel: '2 records',
  direction: 'ltr',
})
const markup = await renderToString(app)

for (const label of ['Visa reports', 'Educational Visa Services', 'Applicant', 'Ali Ahmadi', 'Turkey', 'Reporting period', 'Jan 1, 2026 — Jan 31, 2026', 'Generated at', 'October 11, 2026 at 3:24 PM', 'Generated October 11, 2026 at 3:24 PM', '2 records'])
  assert.ok(markup.includes(label), `the printed document must render "${label}"`)
assert.ok(!markup.includes('var(--v-theme'), 'the printed document must not carry theme variables')
assert.ok(!markup.includes('[missing:'), 'the printed document must not fall back to raw keys')

const emptyApp = createSSRApp(component, { title: 'Visa reports', columns: ['Applicant'], rows: [] })
const emptyMarkup = await renderToString(emptyApp)
assert.ok(emptyMarkup.includes('No records available'), 'an empty report must print its own empty state')

console.log('Localized report dates (blade print + both CSV exports + client locale), theme-independent Visa Reports print document and its namespaced print CSS passed. Browser/PDF layout is not verified.')
