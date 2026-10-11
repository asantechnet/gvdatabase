import { nextTick } from 'vue'
import { printDocument } from './printDocument'

export type ReceiptPaperSize = 'A3 landscape' | 'A4' | 'A4 landscape' | 'A5' | 'letter' | 'A4 2x A5' | 'A5 2x'

const papers: Record<ReceiptPaperSize, { size: string; width: number; height: number; perPage: number }> = {
  'A3 landscape': { size: 'A3 landscape', width: 420, height: 297, perPage: 1 },
  A4: { size: 'A4', width: 210, height: 297, perPage: 1 },
  'A4 landscape': { size: 'A4 landscape', width: 297, height: 210, perPage: 1 },
  A5: { size: 'A5', width: 148, height: 210, perPage: 1 },
  letter: { size: 'letter', width: 215.9, height: 279.4, perPage: 1 },
  'A4 2x A5': { size: 'A4', width: 210, height: 297, perPage: 2 },
  'A5 2x': { size: 'A5', width: 148, height: 210, perPage: 2 },
}

export function receiptPrintLayout(paper: ReceiptPaperSize) {
  const layout = papers[paper] ?? papers.A5
  // Leave a rounding allowance so a fixed paper does not spill onto a blank sheet.
  const height = layout.height - 0.4

  return { ...layout, height, cardHeight: height / layout.perPage }
}

export function receiptCopies(value: number): number {
  return Number.isFinite(value) ? Math.max(1, Math.min(4, Math.floor(value))) : 1
}

export function createReceiptPrintHost(root: HTMLElement, paper: ReceiptPaperSize): HTMLElement {
  const layout = receiptPrintLayout(paper)
  const cards = Array.from(root.querySelectorAll<HTMLElement>(':scope > .receipt-card'))
  if (!cards.length)
    throw new Error('documents.receipt.printFailed')
  const host = document.createElement('div')
  host.className = 'income-receipt-print-host'
  host.dir = root.dir
  host.style.fontFamily = getComputedStyle(root).fontFamily
  host.dataset.measuring = ''
  for (let index = 0; index < cards.length; index += layout.perPage) {
    const page = root.cloneNode(false) as HTMLElement
    page.classList.add('income-receipt-print-page')
    page.style.width = `${layout.width}mm`
    page.style.height = `${layout.height}mm`
    for (const card of cards.slice(index, index + layout.perPage)) {
      const copy = card.cloneNode(true) as HTMLElement
      copy.style.height = `${layout.cardHeight}mm`
      page.appendChild(copy)
    }
    host.appendChild(page)
  }

  return host
}

async function waitForArtwork(host: HTMLElement): Promise<void> {
  const loaded = Promise.all(Array.from(host.querySelectorAll('img')).map(image => {
    if (image.complete)
      return image.naturalWidth > 0 ? Promise.resolve() : Promise.reject(new Error('documents.receipt.artworkFailed'))

    return new Promise<void>((resolve, reject) => {
      image.addEventListener('load', () => resolve(), { once: true })
      image.addEventListener('error', () => reject(new Error('documents.receipt.artworkFailed')), { once: true })
    })
  }))
  let timer: ReturnType<typeof setTimeout> | undefined
  try {
    await Promise.race([
      Promise.all([loaded, document.fonts?.ready]),
      new Promise<never>((_, reject) => { timer = setTimeout(() => reject(new Error('documents.receipt.artworkFailed')), 10_000) }),
    ])
  }
  finally {
    clearTimeout(timer)
  }
}

export async function printReceipt(root: HTMLElement, paper: ReceiptPaperSize): Promise<void> {
  await nextTick()
  const host = createReceiptPrintHost(root, paper)
  try {
    await printDocument({
      bodyClass: 'income-receipt-print-mode',
      pageStyle: `size: ${receiptPrintLayout(paper).size}; margin: 0;`,
      beforePrint: async () => {
        document.body.appendChild(host)
        await waitForArtwork(host)
        for (const content of host.querySelectorAll<HTMLElement>('.receipt-card__content')) {
          if (content.scrollHeight > content.clientHeight + 1 || content.scrollWidth > content.clientWidth + 1)
            throw new Error('documents.receipt.paperTooSmall')
        }
        delete host.dataset.measuring
      },
      afterPrint: () => host.remove(),
    })
  }
  catch (error) {
    host.remove()
    throw error
  }
}
