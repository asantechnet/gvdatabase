import { readFile } from 'node:fs/promises'

const page = await readFile('resources/ts/pages/academy/attendance.vue', 'utf8')
const template = await readFile('resources/ts/pages/templates/academy_attendance_template.vue', 'utf8')

const checks = [
  [page.includes("bodyClass: 'printing-academy-attendance'"), 'print body class'],
  [page.includes('size: A4 landscape; margin: 8mm;'), 'A4 landscape page rule'],
  [page.includes('academy-attendance-print-host'), 'print host'],
  [template.includes('academy-print-header'), 'print header'],
  [template.includes('academy-print-footer'), 'print footer'],
  [!page.includes('@page'), 'no @page in the page SFC'],
  [!template.includes('@page'), 'no @page in the template SFC'],
]

const failed = checks.filter(([ok]) => !ok)
if (failed.length) {
  console.error(`Academy attendance print regression failed: ${failed.map(([, label]) => label).join(', ')}`)
  process.exit(1)
}

console.log(`Academy attendance print regression passed (${checks.length} checks).`)
