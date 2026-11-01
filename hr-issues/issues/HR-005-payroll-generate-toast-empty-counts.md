# HR-005 — Generate payslips success toast has empty counts

- Severity: Medium
- Area: Payrolls (`/payrolls`) → Generate payslips
- Environment: 2026-10-31, super_admin, month 2026-10

## Steps to reproduce

1. Open HR → Payrolls for month `2026-10`.
2. Click **Generate payslips** → confirm **Generate payslips**.
3. Observe the toast.

## Expected

A specific result, e.g. `7 payslip(s) created; 0 skipped.` plus, when applicable, a list or count of employees that could not be generated and why.

## Actual

- Toast text was literally ` payslip(s) created;  skipped.` — both numbers are blank.
- Seven payslips were in fact created (Zahra, Fatima, Ahmad, Bilal, Sana, Omid, Test Counselor for 2026-10).

## Root cause

`resources/ts/pages/payrolls.vue` calls
`toast.success($t('pages.payrolls.generatedCount', { created: result.created, skipped: result.skipped }))`,
but the API wrapper returns the payload normalized under `data` (double `data` envelope). `result.created` / `result.skipped` are therefore `undefined` and the translation placeholders render empty.

## Impact

The user cannot tell whether the operation partially failed, and the "skipped" count — the actionable part — is lost.

## Suggested direction

Normalize the generate response in `resources/ts/api/salary.ts` and read the typed fields; include per-employee errors from the API's `errors` map when non-empty.

## Evidence

- Live toast: `"20 new audit change(s) await review. × ||  payslip(s) created;  skipped. ×"`.
- Created rows verified in `salaries` (`2026-10`).
