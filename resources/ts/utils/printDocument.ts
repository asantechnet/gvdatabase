import { nextTick } from 'vue'

interface PrintDocumentOptions {
  bodyClass: string
  pageStyle: string
  element?: HTMLElement | null
  beforePrint?: () => void | Promise<void>
  afterPrint?: () => void
}

let activePrintCleanup: (() => void) | undefined

const waitForLayout = () => new Promise<void>(resolve => {
  requestAnimationFrame(() => requestAnimationFrame(() => resolve()))
})

export const printDocument = async ({
  bodyClass,
  pageStyle,
  element,
  beforePrint,
  afterPrint,
}: PrintDocumentOptions) => {
  activePrintCleanup?.()
  await beforePrint?.()
  document.dispatchEvent(new Event('app:before-print'))

  // List reports paginate inside the browser's print area. The browser print
  // dialog's margin setting ("None"/"Default") overrides @page margins, so a
  // report that relies on @page has no margin once the user picks "None". Move
  // that margin onto the cloned print host instead: the host is the only
  // content we print, and element padding is never overridden by the dialog.
  const isTablePrint = bodyClass.startsWith('printing-table-')
  const tableMargin = isTablePrint ? /(?:^|;)\s*margin\s*:\s*([^;]+)/i.exec(pageStyle)?.[1]?.trim() : undefined
  const documentPageStyle = tableMargin ? pageStyle.replace(/margin\s*:[^;]*;?/i, 'margin: 0;') : pageStyle

  const pageStyleElement = document.createElement('style')

  pageStyleElement.dataset.appPrintPage = bodyClass
  // A top-level @page rule is honoured by every engine; nesting it inside
  // `@media print` is not reliable, so keep it at the root of this sheet.
  pageStyleElement.textContent = `@page { ${documentPageStyle} }`
  document.head.appendChild(pageStyleElement)
  document.body.classList.add(bodyClass)
  let printHost: HTMLElement | undefined
  // A body-level copy removes all layout ancestors from pagination and prevents
  // another lazy-loaded route's report or application chrome from printing.
  const source = element ?? (bodyClass.startsWith('printing-table-')
    ? document.querySelector<HTMLElement>('.list-print-report') : null)
  if (source) {
    await nextTick()
    printHost = document.createElement('div')
    printHost.id = 'app-print-host'
    printHost.dir = document.documentElement.dir || 'ltr'
    const copy = source.cloneNode(true) as HTMLElement
    if (bodyClass === 'printing-contract-document') {
      const pageSelector = '.contract-cover, .custom-cover-page, .contract-page-container, .empty-print-page'
      if (copy.matches(pageSelector)) copy.classList.add('print-page')
      copy.querySelectorAll(pageSelector).forEach(page => page.classList.add('print-page'))
    }
    printHost.appendChild(copy)
    document.body.appendChild(printHost)
    document.body.classList.add('printing-isolated-document')
    pageStyleElement.textContent += `
      @media screen { #app-print-host { position: fixed; inset: 0; visibility: hidden; pointer-events: none; } }
      @media print {
        body.printing-isolated-document { margin: 0 !important; padding: 0 !important; background: white !important; }
        body.printing-isolated-document > :not(#app-print-host) { display: none !important; }
        body.printing-isolated-document #app-print-host { display: block !important; position: static !important; color: #222; background: white; }
        body.printing-isolated-document #app-print-host > * { display: block !important; margin: 0 !important; }
        body.printing-isolated-document #app-print-host > :not(.print-page):not(.payslip-container) { position: static !important; }
        ${tableMargin ? `body.printing-isolated-document #app-print-host { padding: ${tableMargin} !important; box-sizing: border-box; }` : ''}
      }`
  }

  let fallbackTimer: number | undefined
  let cleaned = false

  const cleanup = () => {
    if (cleaned)
      return

    cleaned = true
    window.removeEventListener('afterprint', cleanup)
    window.clearTimeout(fallbackTimer)
    document.body.classList.remove(bodyClass)
    pageStyleElement.remove()
    printHost?.remove()
    document.body.classList.remove('printing-isolated-document')
    afterPrint?.()

    if (activePrintCleanup === cleanup)
      activePrintCleanup = undefined
  }

  activePrintCleanup = cleanup
  window.addEventListener('afterprint', cleanup, { once: true })

  try {
    await nextTick()
    await document.fonts.ready
    const images = Array.from((printHost ?? document).querySelectorAll('img'))
    await Promise.all(images.map(img => img.complete ? Promise.resolve() : new Promise<void>(resolve => {
      const timer = window.setTimeout(resolve, 8000)
      const done = () => { window.clearTimeout(timer); resolve() }
      img.addEventListener('load', done, { once: true })
      img.addEventListener('error', done, { once: true })
    })))
    await waitForLayout()
    window.print()
  }
  catch (error) {
    cleanup()
    throw error
  }

  // `afterprint` is authoritative; this only protects browsers that omit it.
  if (!cleaned)
    fallbackTimer = window.setTimeout(cleanup, 60_000)
}
