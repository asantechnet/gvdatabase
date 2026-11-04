# Review — Task-013 shared table foundations

- **Reviewed:** 2026-11-04
- **Verdict:** Needs changes — shared empty-state criterion fails in practice
- **Method:** Playwright CLI on `/payment-requests` with zero rows; source inspection of `responsiveDataTable.ts` and `TablePaginationFooter.vue`.
- **Scope:** review only. No code was changed.

## Finding — Shared table still exposes pagination when there are no rows

**Severity:** Medium

**Expected:** An empty result set renders one shared empty state and no paginator or zero-range control.

**Actual:** The empty `/payment-requests` table renders the shared `No payment requests.` state, but also renders the page-size control, `0-0 of 0`, and first/previous/next/last controls. This is the shared table’s empty-state contract, not only a page-copy issue.

**Evidence:** `/payment-requests` at 1280×720 after a fresh seed. The shared implementation has a guard at `resources/ts/components/responsive-data-table/responsiveDataTable.ts:583–586` that returns `null` when `footer.itemsLength` is falsy, but the rendered page still shows the footer. Investigate whether the default Vuetify footer is being rendered outside that slot or the guard is not receiving the expected `itemsLength`.

**Suggested fix:** Make the shared server table suppress its footer deterministically when `itemsLength`/`total` is zero, including the Vuetify default-footer path. Add a component-level or page-level regression check for an empty table with `pagination` enabled.

## Notes

- This is the same observed surface as the Task-011 finding; fixing the shared primitive is preferable to patching each page.
- Non-empty tables were not exhaustively re-tested in this pass.
- No source was modified during this review.
