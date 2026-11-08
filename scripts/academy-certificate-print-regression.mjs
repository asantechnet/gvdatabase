import { readFile } from 'node:fs/promises'

const printer = await readFile('resources/ts/components/academy/AcademyCertificatePrinter.vue', 'utf8')
const template = await readFile('resources/ts/pages/templates/academy_certificate_template.vue', 'utf8')
const checks = [
  [printer.includes("bodyClass: 'printing-academy-certificate'"), 'print body class'],
  [printer.includes('size: A4 landscape; margin: 6mm;'), 'A4 landscape rule'],
  [printer.includes('printDocument'), 'printDocument workflow'],
  [template.includes('signatureUrl'), 'signature slot'],
  [template.includes('logoUrl'), 'logo slot'],
  [template.includes('revoked-watermark'), 'revoked watermark'],
  [template.includes('overflow-wrap: anywhere'), 'long-name wrapping'],
  [!printer.includes('@page'), 'no @page in printer SFC'],
  [!template.includes('@page'), 'no @page in template SFC'],
]

const failed = checks.filter(([ok]) => !ok)
if (failed.length) {
  console.error(`Academy certificate print regression failed: ${failed.map(([, label]) => label).join(', ')}`)
  process.exit(1)
}
console.log(`Academy certificate print regression passed (${checks.length} checks).`)
