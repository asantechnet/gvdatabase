# Review — Task-011 payment requests pagination

- **Reviewed:** 2026-11-04
- **Verdict:** Needs changes — one acceptance criterion fails
- **Method:** Playwright CLI against `/payment-requests` after a fresh seeded database; `PendingTransactionTest` and `PendingTransactionFilterTest` passed.
- **Scope:** review only. No code was changed.

## Finding — Empty list still renders a zero-state paginator

**Severity:** Medium

**Expected:** The empty list shows the shared empty state and no paginator controls.

**Actual:** With zero payment requests the page shows `No payment requests.` and then still renders:

- `Items per page: 25`
- `0-0 of 0`
- disabled `First page`, `Previous page`, `Next page` and `Last page` controls

This is the exact zero-range/paginator state the acceptance criterion excludes.

**Evidence:** `/payment-requests` at 1280×720 after `migrate:fresh --seed`; snapshot shows one table with `No payment requests.`, followed by the page-size select, `0-0 of 0`, and the pagination navigation. Source passes `pagination` unconditionally to `ResponsiveDataTableServer` (`resources/ts/pages/pending-transactions.vue` around lines 400–410).

**Suggested fix:** Hide the footer when `total === 0` (and keep the `#no-data` empty state), or make the shared table suppress its footer on an empty result set. Re-check that the table still renders exactly one footer when rows exist.

## Notes

- The non-empty pagination path is not disproven by this finding; the failing case is the empty result.
- The shared-table empty-state criterion in Task-013 is also implicated; see that task’s review.
- No source was modified during this review.
