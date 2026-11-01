# Task Task-011 — Give payment requests one pagination and a real filter bar

## Metadata
- Task ID: `Task-011`
- Title: `Give payment requests one pagination and a real filter bar`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P2`
- Owner role: `Full-stack Laravel/Vue implementer`
- Estimated size: `M`
- Source plan/version: `issues/review/issues/08-duplicate-pagination/issue.md`; `issues/review/REPORT.md` §4 P2-5; `fin-issues/issues/FIN-001-payment-request-self-approval-toasts.md`; `fin-issues/issues/FIN-004-send-to-review-raw-status.md`; `fin-issues/issues/FIN-005-payment-request-toast-submit.md`; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-08`, `FIN-001`, `FIN-004`, `FIN-005`

## Objective
The payment-requests table has one paginator, one page-size value and one count, plus server-side search and status/type filters.

## Context and problem
`pending-transactions.vue` passes the rows to `ResponsiveDataTable` without server pagination options, then renders a second `VPagination` plus an `items-per-page` select and a "records" count below it. The two controls disagree on page size (10 vs 25) and duplicate the accessible pagination labels. The subtitle is clipped and the page has no search or filters. Approve/reject controls remain visible to a user viewing their own request even though the server rejects self-approval, the review toast passes `under_review` through a missing translation and shows the raw token, and the submit toast reuses the button label ("Submit") instead of confirming what happened.

## Scope
### Included
- Remove the second paginator and keep one source of pagination state.
- Make the page size options consistent (10, 25, 50, 100), reload from page 1 when the size changes, and show the total count once.
- Add server-side search over reason and a status filter (submitted, under_review, approved, rejected, posted, draft) and a transaction-type filter.
- Reflect filters in the URL where the existing page pattern supports query state; otherwise keep local state and reset page to 1.
- Add a clear-all action and an active-filter indicator.
- Give the page a consistent header using the existing `ErpPageHeader` and stop the subtitle from clipping.
- Hide or disable approve/reject/post for a request created by the current user, with an explanation; keep the server-side self-approval guard authoritative.
- Translate `under_review` in the status chip and the review toast, and replace the submit toast with an explicit saved/submitted sentence.
- Ensure one failed request produces one specific message by removing this page's duplicate API error toast (Task-018 owns the global notifier).
- Keep the empty state and ensure no paginator is shown when the result set is empty.

### Excluded
- Changing approval, rejection, posting or self-approval rules.
- Adding payee/category/payment-method fields to the request form (a separate scope).
- Shared table pagination implementation (Task-013) — this task consumes it.

## Preconditions and assumptions
- `PendingTransactionController` already supports pagination through `PendingTransactionApi.list(page, perPage)`; search and filters are additive query parameters.
- The existing `ResponsiveDataTable` supports a server-pagination mode or can accept `items-length`, `page`, `items-per-page` and `update:options` like `ResponsiveDataTableServer`. Confirm before choosing the component.
- Assumption to validate: transactions have a `reason` and `transaction_type` searchable/filterable without a new index. If volume requires an index, record it as a follow-up migration rather than altering the schema here.

## Dependencies
- `Task-012` — money and date display use the shared formatter.
- `Task-013` — the single paginator and accessible table controls come from the shared table primitives.
- `Task-018` — one owner for API failure feedback; this task removes its duplicate error toasts.

## Contracts and boundaries
- Input/output: `GET /pending-transactions` accepts `search`, `status` and `transaction_type` in addition to page/per_page; response shape is unchanged.
- Compatibility guarantee: existing callers without filters receive the same page as before.
- Error behavior: an unknown status/type returns a validation error; an empty search returns the unfiltered first page.
- Permission/data scope: the existing approval/post permissions and employee/applicant scoping are unchanged.
- Integration seam: pagination state lives in one place in the page and is passed to the table; no second `VPagination`.
- Files this task must not modify: `resources/ts/pages/payrolls.vue`, `app/Http/Controllers/SalaryController.php`, `resources/ts/components/responsive-data-table/**`.

## Exclusive file ownership
- `resources/ts/pages/pending-transactions.vue` — single pagination, filters and header.
- `app/Http/Controllers/PendingTransactionController.php` — search/status/type filters.
- `resources/ts/api/pendingTransactions.ts` — filter parameters and list types.

## Acceptance criteria
- [ ] Given the payment-requests page, when it renders with rows, then exactly one paginator and one page-size control are present, and both show the same page size.
- [ ] Given the page size changes, when the control updates, then the table reloads from page 1 and the result count and range update once.
- [ ] Given a search term or status/type filter, when it is applied, then the server returns only matching rows, the page resets to 1 and an active-filter indicator is visible.
- [ ] Given the filters are cleared, when the table reloads, then all rows return, the page resets to 1 and no stale filter chip remains.
- [ ] Given the list is empty, when the page renders, then it shows the shared empty state and no paginator controls.
- [ ] Given the subtitle and header, when the page renders at 1280 px and 390 px, then the description is not clipped and the page uses the same header pattern as other ERP lists.
- [ ] Given a keyboard or screen reader user, when they reach pagination and filters, then each control has one accessible name and the duplicate pagination labels are gone.
- [ ] Given the current user created a request, when its row renders, then approve/reject/post are hidden or disabled with an explanation, and a direct API attempt is still rejected by the server.
- [ ] Given a request moves to `under_review`, when the status chip and toast render, then both show a translated label and never the raw `under_review` token.
- [ ] Given a create or edit succeeds, when the toast appears, then it states that the request was saved or submitted, not merely the button label, and a failure shows exactly one specific message.

## Verification plan
- Automated: feature tests for search/status/type filters and page reset; run the focused pending-transaction tests and `npm run typecheck`.
- Manual: create at least 30 requests across statuses; change page size, page through, filter by status/type/search, clear and confirm the empty state with a contradictory filter.
- Fixtures: 30 pending transactions across all statuses and both types; non-production only.
- Regression: confirm approve/reject/post actions, receipt printing, self-approval rejection and permissions still work.
- Evidence: screenshots of the single paginator, filter state and empty state; API responses for filtered pages.

## Implementation notes
Choose either the server table component or the client table plus explicit footer, never both. Bind `page` and `perPage` to the table's `v-model`/options and remove the manual `VPagination`. For filters, debounce search and reset page on every change. Keep the empty state outside the table footer so no zero-state paginator remains.

## Risks and rollback
- Risk: switching components can break row actions or mobile cards. Mitigation: compare action rendering and mobile behavior before removing the old footer.
- Risk: search over a large table is slow. Mitigation: cap results and add an index only if measurement shows a problem.
- Data migration: none. Rollback restores the previous page component; no data change.
- Stop condition: if the shared table cannot support server pagination without a breaking change, stop and coordinate with Task-013 instead of duplicating a footer.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
