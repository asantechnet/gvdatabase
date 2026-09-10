import { nextTick } from 'vue'

interface PrintDocumentOptions {
  bodyClass: string
  pageStyle: string
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
  beforePrint,
  afterPrint,
}: PrintDocumentOptions) => {
  activePrintCleanup?.()
  await beforePrint?.()
  document.dispatchEvent(new Event('app:before-print'))

  const pageStyleElement = document.createElement('style')

  pageStyleElement.dataset.appPrintPage = bodyClass
  pageStyleElement.textContent = `@media print { @page { ${pageStyle} } }`
  document.head.appendChild(pageStyleElement)
  document.body.classList.add(bodyClass)

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
    afterPrint?.()

    if (activePrintCleanup === cleanup)
      activePrintCleanup = undefined
  }

  activePrintCleanup = cleanup
  window.addEventListener('afterprint', cleanup, { once: true })

  await nextTick()
  await waitForLayout()

  window.print()

  // `afterprint` is authoritative; this only protects browsers that omit it.
  if (!cleaned)
    fallbackTimer = window.setTimeout(cleanup, 60_000)
}
