import { createCardPrintHost, printCardSheet } from './printDocument'

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
  return createCardPrintHost({
    root,
    paper: papers[paper] ?? papers.A5,
    cardSelector: ':scope > .receipt-card',
    hostClass: 'income-receipt-print-host',
    pageClass: 'income-receipt-print-page',
    errorPrefix: 'documents.receipt',
  })
}

export async function printReceipt(root: HTMLElement, paper: ReceiptPaperSize): Promise<void> {
  await printCardSheet({
    root,
    paper: papers[paper] ?? papers.A5,
    cardSelector: ':scope > .receipt-card',
    contentSelector: '.receipt-card__content',
    bodyClass: 'income-receipt-print-mode',
    hostClass: 'income-receipt-print-host',
    pageClass: 'income-receipt-print-page',
    errorPrefix: 'documents.receipt',
  })
}
