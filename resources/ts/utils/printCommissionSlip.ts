import { createCardPrintHost, printCardSheet } from './printDocument'

export type CommissionSlipPaperSize = 'A4' | 'A5' | 'A4 2x A5' | 'A5 2x'

const papers: Record<CommissionSlipPaperSize, { size: string; width: number; height: number; perPage: number }> = {
  A4: { size: 'A4', width: 210, height: 297, perPage: 1 },
  A5: { size: 'A5', width: 148, height: 210, perPage: 1 },
  'A4 2x A5': { size: 'A4', width: 210, height: 297, perPage: 2 },
  'A5 2x': { size: 'A5', width: 148, height: 210, perPage: 2 },
}

export function commissionSlipPrintLayout(paper: CommissionSlipPaperSize) {
  const layout = papers[paper] ?? papers.A5
  // Leave a rounding allowance so a fixed paper does not spill onto a blank sheet.
  const height = layout.height - 0.4

  return { ...layout, height, cardHeight: height / layout.perPage }
}

export function commissionSlipCopies(value: number): number {
  return Number.isFinite(value) ? Math.max(1, Math.min(4, Math.floor(value))) : 1
}

export function createCommissionSlipPrintHost(root: HTMLElement, paper: CommissionSlipPaperSize): HTMLElement {
  return createCardPrintHost({
    root,
    paper: papers[paper] ?? papers.A5,
    cardSelector: ':scope > .commission-card',
    hostClass: 'commission-slip-print-host',
    pageClass: 'commission-slip-print-page',
    errorPrefix: 'documents.commissionSlip',
  })
}

export async function printCommissionSlip(root: HTMLElement, paper: CommissionSlipPaperSize): Promise<void> {
  await printCardSheet({
    root,
    paper: papers[paper] ?? papers.A5,
    cardSelector: ':scope > .commission-card',
    contentSelector: '.commission-card__content',
    bodyClass: 'commission-slip-print-mode',
    hostClass: 'commission-slip-print-host',
    pageClass: 'commission-slip-print-page',
    errorPrefix: 'documents.commissionSlip',
  })
}
