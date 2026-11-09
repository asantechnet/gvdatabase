# Slip printing

All slip-like documents share one in-app preview popup and one print pipeline.

## Unified dialog

- `resources/ts/components/documents/SlipPrintDialog.vue` is the only preview/print popup for slips. It owns the title/reference header, optional paper-size selector, Close and Print actions, loading state and print error toast.
- Academy slip, income receipt and commission slip keep their own data loaders in `AcademySlipPrinter.vue`, `ReceiptPrinter.vue` and `CommissionSlipPrinter.vue`, but render the shared dialog.
- Payslip and salary advance receipt render the same dialog directly from `/payrolls`. Opening a slip preview never calls `window.print()`; the browser print engine opens only after the Print button is pressed.

## Print pipeline

- Academy slip, receipt and commission slip build a body-level print host through `printCardSheet` in `resources/ts/utils/printDocument.ts`. Each template keeps its card markup and namespaced print CSS; the utility owns page grouping (one or two cards per sheet), artwork/font waiting, overflow detection, fixed `@page size`/`margin: 0`, and cleanup.
- Payslip and advance receipt print through `printDocument` with fixed A5 sizing and `margin: 0`; their own padding supplies the safe area.
- Every feature-specific `@media print` rule stays prefixed with its body class (`academy-slip-print-mode`, `income-receipt-print-mode`, `commission-slip-print-mode`, `printing-payslip-document`, `printing-advance-receipt`). `@page` is injected only by `printDocument` for the active job.

## Browser print settings

JavaScript cannot disable the browser's own print dialog controls. The strongest guarantee available is applied: fixed `@page` size and zero margins in the injected rule, consistent host dimensions, and print-only CSS that does not depend on the browser's margin or scale setting. An operator can still override paper, scale or margins inside the browser dialog; that limitation is inherent to `window.print()`.

## Verification

- `node scripts/academy-slip-print-regression.mjs`
- `node scripts/receipt-print-regression.mjs`
- `npm run typecheck`

Browser/PDF layout is not covered by those scripts.
