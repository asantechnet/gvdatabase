# FIN-010 — Commission reconcile confirmation has broken pluralization

- **Severity:** Low
- **Area:** Finance → Commissions → Reconcile completed cases
- **Status:** Open

## Steps to reproduce

1. Open `/commissions` with no completed cases.
2. Press **Reconcile completed cases**.

## Actual

> `Reconciled 0 completed case(s) and voided 0 outdated accrual(s).`

The `(s)` convention is not localized and reads as developer copy.

## Expected

> Reconciled 0 completed cases and voided 0 outdated accruals.

(with proper plural forms in each supported locale)

## Related product question

The page subtitle states commissions are earned **"when a case reaches the completed stage"**.
That is the behaviour the accrual service implements, but it contradicts the stated business rule
that commission should be earned when a **contract** completes. This is recorded in the HR review
as `HR-006`; it is repeated here because the finance surface is where the wording lives.
