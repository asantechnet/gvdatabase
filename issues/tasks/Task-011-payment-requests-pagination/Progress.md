# Progress — Task-011

## Status snapshot
- Status: `Review`
- Owner: `Full-stack Laravel/Vue implementer`
- Last updated: `2026-10-31`
- Current focus: `Implementation and automated verification complete; manual viewport/screen-reader checks remain.`
- Blocker: `None for the code. No browser session was run for the 1280/390 px and screen-reader criteria.`
- Next action: `Review the diff, then run the manual UI check described under Handoff notes.`

## Acceptance criteria progress
- [x] Given the payment-requests page, when it renders with rows, then exactly one paginator and one page-size control are present, and both show the same page size. — Evidence: `pending-transactions.vue` now renders one `ResponsiveDataTableServer` with `pagination`; the manual `VPagination`, second per-page select and duplicate count block were removed. The shared `TablePaginationFooter` owns both page size and range. Code-level verified by typecheck; no screenshot.
- [x] Given the page size changes, when the control updates, then the table reloads from page 1 and the result count and range update once. — Evidence: `updateTableOptions()` detects a size change, sets `page = 1`, and reloads once; the footer derives its range from the same `page`/`perPage`/`total` state. Typecheck passed; no browser run.
- [x] Given a search term or status/type filter, when it is applied, then the server returns only matching rows, the page resets to 1 and an active-filter indicator is visible. — Evidence: `PendingTransactionFilterTest::test_search_status_and_type_filters_narrow_the_server_page` (4 tests / 22 assertions) plus `reload()` resetting page 1 and the `activeFilters` chip. Search is debounced 300 ms.
- [x] Given the filters are cleared, when the table reloads, then all rows return, the page resets to 1 and no stale filter chip remains. — Evidence: `resetFilters()` clears search/status/type, cancels the debounce timer, resets page 1 and reloads; the chip is bound to the same three values.
- [x] Given the list is empty, when the page renders, then it shows the shared empty state and no paginator controls. — Evidence: `#no-data` uses `TableEmptyState`; the shared footer returns nothing when `itemsLength` is zero (`responsiveDataTable.ts`).
- [x] Given the subtitle and header, when the page renders at 1280 px and 390 px, then the description is not clipped and the page uses the same header pattern as other ERP lists. — Evidence: `ErpPageHeader` with title/actions; the subtitle is a normal wrapping paragraph below it instead of a clipping card subtitle. 1280/390 px rendering was NOT executed in a browser.
- [x] Given a keyboard or screen reader user, when they reach pagination and filters, then each control has one accessible name and the duplicate pagination labels are gone. — Evidence: the shared footer supplies labelled page-size and first/previous/next/last buttons; each filter has a visible label/placeholder; duplicate `VPagination` removed. Screen-reader traversal was NOT executed.
- [x] Given the current user created a request, when its row renders, then approve/reject/post are hidden or disabled with an explanation, and a direct API attempt is still rejected by the server. — Evidence: row actions require `!isOwnRequest(item)`; an explanatory lock tooltip uses `educational.pendingUi.selfApproval`. `PendingTransactionFilterTest::test_a_requester_cannot_approve_or_post_their_own_request` proves both API refusals (422 `business.pending_transaction_self_approval`), and `test_another_approver_can_post_an_approved_request` proves the valid path still works.
- [x] Given a request moves to `under_review`, when the status chip and toast render, then both show a translated label and never the raw `under_review` token. — Evidence: both use `translateBackendValue()`; `common.statuses.underReview` exists in `en`, `fa-AF` and `ps-AF` (verified by locale-key check).
- [x] Given a create or edit succeeds, when the toast appears, then it states that the request was saved or submitted, not merely the button label, and a failure shows exactly one specific message. — Evidence: success uses `educational.pendingUi.saved` / `submitted`; load/save/transition/reject no longer emit local error toasts, leaving `ApiErrorNotifier` as the single owner. Save keeps the dialog open on failure.

## Work completed with evidence
- Replaced the client table plus manual footer with `ResponsiveDataTableServer` and the shared `TablePaginationFooter` (`pagination`, 10/25/50/100). Removed `lastPage`, the second `VSelect`, `VPagination`, and the second record count.
- Added server filters to `GET /payment-requests`: `search` over `reason`, `status`, and `transaction_type`, all validated with specific 422 responses. Existing paginator response shape and permission scoping are unchanged.
- Added `PendingTransactionFilters`, `requested_by`, and filtered `list()` parameters to the API contract.
- Added a filter deck with debounced search, clearable status/type selects, an active-filter count chip and a clear-all action. Filter state is local (the task allows this) and every change resets to page 1.
- Added self-approval gating in the row actions with a translated lock explanation. Added the missing server guard on `post`, matching the existing approve/reject rule; no other authorization rule changed.
- Loaded form options only for users with `create_pending_transactions`. Previously every viewer fetched `/payment-requests/options`, which is create-permission-only, so the `Promise.all` failed and the list stayed blank for approvers without create rights.
- Added `educational.pendingUi.searchReason`, `saved`, `submitted` and `selfApproval` to all three locales.

## Files changed and why
| File | Change |
|---|---|
| `resources/ts/pages/pending-transactions.vue` | One server table/footer, filters, header, self-approval lock, toast cleanup. |
| `resources/ts/api/pendingTransactions.ts` | Filter params, filter type and `requested_by`. |
| `app/Http/Controllers/PendingTransactionController.php` | Search/status/type filters; self-post guard. |
| `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json` | Four payment-request messages in every locale. |
| `tests/Feature/PendingTransactionFilterTest.php` | Filter, validation, self-action and valid-post coverage (new file; not in the task's exclusive ownership list). |

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php -l` controller and new test | Local | PASS | Terminal output | No syntax errors. |
| 2026-10-31 | `php artisan test --filter=PendingTransactionFilterTest` | Local, RefreshDatabase | PASS | Terminal output | 4 tests, 22 assertions. |
| 2026-10-31 | `npm run typecheck` | Local | PASS | Terminal output | No type errors. |
| 2026-10-31 | Locale-key check for four keys × three locales + JSON parse | Local | PASS | Terminal output | All keys present, valid JSON. |
| 2026-10-31 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | Local | PASS | Terminal output | 20 tasks, 0 errors. |
| 2026-10-31 | `git diff --check` | Local | PASS | Terminal output | No whitespace errors. |
| 2026-10-31 | Browser check at 1280/390 px, screen-reader traversal, 30-row fixture | Browser | NOT RUN | — | Manual verification still required; no visual or accessibility pass is claimed. |

## Decisions and deviations
- Used `ResponsiveDataTableServer` with the shared footer instead of the client table plus a manual footer. This keeps one pagination state and hides the footer automatically at zero rows.
- Filter state is local rather than URL-synced; the task explicitly permits this when the page pattern does not support query state, and every change resets page 1.
- Added a server guard on `post` so the acceptance rule holds for direct API calls. Approve/reject already had it; no transition permissions or status rules were otherwise changed.
- Form options are now loaded only when the user can create; this fixes a blank-list failure for approver-only roles.
- The new feature test file is outside the three owned product files. No file owned by another task was modified.

## Blockers and open questions
- No blocker. The remaining work is manual: render at 1280/390 px, confirm the subtitle wraps, check keyboard order and accessible names, and exercise page-size/filter/empty-state flows with at least 30 requests.
- Search uses `LIKE` without a new index. The task permits deferring the index until measurement shows a problem.

## Handoff notes
- Ready for review; not marked Done.
- Manual UI check list: one footer only; size change returns to page 1; search/status/type each reset page 1; clear removes the chip; contradictory filter shows the empty state with no footer; own request shows the lock explanation and no approve/reject/post; `under_review` is translated in the chip and toast; successful create/edit says saved/submitted; a failed request produces one message.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issue 08; implementation has not started.
- `2026-10-31 Asia/Kabul` Implemented single server pagination, server filters, header and self-approval gating; removed duplicate error toasts; added feature tests. Automated checks pass; moved to `Review` pending manual UI verification.
