# FIN-007 — Payment requests page renders two pagination controls

- **Severity:** Low
- **Area:** Finance → Payment requests
- **Status:** Open

## Steps to reproduce

1. Open `/payment-requests` (empty state is enough).

## Actual

The page shows two independent pagination blocks:

1. Under the table: `Items per page: 10`, `0-0 of 0`, First/Previous/Next/Last.
2. Below that: `Previous` / `Next`, a second `Per page` selector defaulting to `25`, and
   `0 records`.

Two "per page" selectors with different defaults (10 and 25) on the same table is confusing, and
only one of them can actually drive it.

## Suggested fix

Remove the duplicate footer pagination and keep the table's own control.
