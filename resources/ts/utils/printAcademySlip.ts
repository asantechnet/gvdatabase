import { createCardPrintHost, printCardSheet } from './printDocument'

export type AcademySlipPaperSize = 'A4' | 'A5' | 'A4 2x A5' | 'A5 2x'

const papers: Record<AcademySlipPaperSize, { size: string; width: number; height: number; perPage: number }> = {
  A4: { size: 'A4', width: 210, height: 297, perPage: 1 },
  A5: { size: 'A5', width: 148, height: 210, perPage: 1 },
  'A4 2x A5': { size: 'A4', width: 210, height: 297, perPage: 2 },
  'A5 2x': { size: 'A5', width: 148, height: 210, perPage: 2 },
}

export function academySlipPrintLayout(paper: AcademySlipPaperSize) {
  const layout = papers[paper] ?? papers.A5
  // Leave a rounding allowance so a fixed paper does not spill onto a blank sheet.
  const height = layout.height - 0.4

  return { ...layout, height, cardHeight: height / layout.perPage }
}

export function academySlipCopies(value: number): number {
  return Number.isFinite(value) ? Math.max(1, Math.min(4, Math.floor(value))) : 1
}

export function createAcademySlipPrintHost(root: HTMLElement, paper: AcademySlipPaperSize): HTMLElement {
  return createCardPrintHost({
    root,
    paper: papers[paper] ?? papers.A5,
    cardSelector: ':scope > .academy-card',
    hostClass: 'academy-slip-print-host',
    pageClass: 'academy-slip-print-page',
    errorPrefix: 'documents.academySlip',
  })
}

export async function printAcademySlip(root: HTMLElement, paper: AcademySlipPaperSize): Promise<void> {
  await printCardSheet({
    root,
    paper: papers[paper] ?? papers.A5,
    cardSelector: ':scope > .academy-card',
    contentSelector: '.academy-card__content',
    bodyClass: 'academy-slip-print-mode',
    hostClass: 'academy-slip-print-host',
    pageClass: 'academy-slip-print-page',
    errorPrefix: 'documents.academySlip',
  })
}
