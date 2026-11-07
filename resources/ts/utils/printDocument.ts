import { nextTick } from 'vue'

interface PrintDocumentOptions {
  bodyClass: string
  pageStyle: string
  element?: HTMLElement | null
  /**
   * Optional selector for logical pages inside `element`. The isolated host
   * adds `print-page` to these only, so page-break rules can never affect
   * unrelated documents visited earlier in the SPA.
   */
  printPageSelector?: string
  /**
   * Padding applied to the isolated print host. This keeps document spacing
   * intact when the browser print dialog overrides the @page margin.
   */
  hostPadding?: string
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
  printPageSelector,
  hostPadding,
  beforePrint,
  afterPrint,
}: PrintDocumentOptions) => {
  activePrintCleanup?.()
  await beforePrint?.()
  document.dispatchEvent(new Event('app:before-print'))

  // Reports paginate inside the browser's print area. The browser print
  // dialog's margin setting ("None"/"Default") overrides @page margins, so a
  // report that relies on @page has no margin once the user picks "None". Move
  // that margin onto the cloned print host instead: the host is the only
  // content we print, and element padding is never overridden by the dialog.
  const isTablePrint = bodyClass.startsWith('printing-table-')
  const contentMargin = hostPadding ?? (isTablePrint ? /(?:^|;)\s*margin\s*:\s*([^;]+)/i.exec(pageStyle)?.[1]?.trim() : undefined)
  const documentPageStyle = contentMargin ? pageStyle.replace(/margin\s*:[^;]*;?/i, 'margin: 0;') : pageStyle

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
    if (printPageSelector) {
      if (copy.matches(printPageSelector)) copy.classList.add('print-page')
      copy.querySelectorAll(printPageSelector).forEach(page => page.classList.add('print-page'))
    }
    printHost.appendChild(copy)
    document.body.appendChild(printHost)
    document.body.classList.add('printing-isolated-document')
    pageStyleElement.textContent += `
      @media screen { #app-print-host { position: fixed; inset: 0; visibility: hidden; pointer-events: none; } }
      @media print {
        body.printing-isolated-document { margin: 0 !important; padding: 0 !important; block-size: auto !important; overflow: visible !important; background: white !important; }
        body.printing-isolated-document > :not(#app-print-host) { display: none !important; }
        body.printing-isolated-document #app-print-host { display: block !important; position: static !important; color: #222; background: white; --v-scrollbar-offset: 0px; }
        body.printing-isolated-document #app-print-host > * { display: block !important; margin: 0 !important; }
        body.printing-isolated-document #app-print-host > :not(.print-page):not(.payslip-container) { position: static !important; }
        ${contentMargin ? `body.printing-isolated-document #app-print-host { padding: ${contentMargin} !important; box-sizing: border-box; }` : ''}
      }
      /*
       * Vuetify's overlay scroll strategy pins <html> to the viewport
       * (position: fixed; width/height: 100%) and clips <body> (overflow
       * hidden) for as long as a dialog is open. Every print path opens a
       * dialog, so without overriding that lock the print engine can only lay
       * out a single viewport-height page and the document is truncated to its
       * first screen. These selectors are global by necessity (html cannot be
       * namespaced by a body class) but live only in this temporary sheet,
       * which is removed once the print job finishes.
       */
      @media print {
        html.v-overlay-scroll-blocked {
          position: static !important;
          inset: auto !important;
          inline-size: auto !important;
          block-size: auto !important;
          overflow: visible !important;
        }
        .v-overlay-scroll-blocked:not(html) { overflow-y: visible !important; }
        .v-overlay-scroll-blocked { padding-inline-end: 0 !important; }
        html:has(body.printing-isolated-document) { overflow: visible !important; }
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
