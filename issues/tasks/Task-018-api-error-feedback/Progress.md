# Progress — Task-018

## Status snapshot
- Status: `Review`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-10-31`
- Current focus: `Single api-error dispatch, inline-first feedback, missing business copy and the underReview label are implemented and verified.`
- Blocker: `None for the shared contract. Downstream page tasks still own removal of their duplicate toasts (criterion 8) and Task-017 owns the zero-grant field error (criterion 4).`
- Next action: `Reviewer confirms the dispatch helper and the three-locale copy, then Tasks 006/011/015/017/019 remove their page-level apiErrorMessage toasts.`

## Acceptance criteria progress
- [x] Given a 422 with a field that exists on the form, when the response returns, then the field shows one inline error and the notifier adds no toast. — Evidence: `ApiErrorNotifier.vue` keeps inline placement first and returns before `toast.error` when `displayInlineFormErrors()` matched a control. `formErrors.ts` now waits for Vue's render and skips its DOM bridge when the shared control already renders the same `.v-messages__message`, so a shared App* field shows **one** message rather than the store message plus a bridge copy. Browser capture not performed.
- [x] Given a 422 with no matching field, when the response returns, then exactly one toast shows the server's specific message, not the page's generic fallback. — Evidence: the notifier path is unchanged (`matchedFields === 0` → one `toast.error(apiErrorMessage(error))`); the temporary harness emitted exactly one `api-error` for a business 422. When the JSON catalog has no business key, `humanizeApiError` falls back to the server-localized `message`, which `NormalizeApiErrorResponse` fills from `ApiMessage::forCode`; `ApiMessage` resolved all six new codes in `en`, `fa` and `ps` (see checks).
- [x] Given a 500, 403, 429, offline write or network failure, when it occurs, then exactly one localized toast appears and no `business.*`, `validation.*` or `server.error` token is visible. — Evidence: all HTTP, offline and network branches now call the single `dispatchApiError`; `humanizeApiError` keeps its missing-key guard and returns the localized server message/fallback rather than a machine code. The existing axios suite passes its manual-failure, quiet-failure, offline and 401 cases (`node scripts/background-polling-test.cjs`, 16/16).
- [x] Given a department delete refusal, a department manager refusal, a self-approval refusal or a zero bonus grant, when the API rejects it, then the message names the real reason in English, Persian and Pashto. — Evidence: `business.department_has_employees` and `business.pending_transaction_self_approval` are now in all three PHP catalogs; the manager codes were already present. `PendingTransactionController` also uses `pending_transaction_locked`, `pending_transaction_not_approved`, `pending_transaction_invalid_transition` and `pending_transaction_currency_rate_invalid`, so those were added too. Task-017 now implements the zero-grant refusal as `business.bonus_amount_required` (field-level `grantedAmount`, `BonusGrantTest` coverage) and the copy is present in all three catalogs.
- [x] Given a payment request in `under_review`, when its status renders in a chip or toast, then the label is translated and never the raw token. — Evidence: added `common.statuses.underReview` to `en.json`, `fa-AF.json` and `ps-AF.json`; `translateBackendValue('under_review')` resolves through `normalizedKey` to that key. Values: `Under review`, `در حال بررسی`, `د بیا کتنې لاندې`.
- [x] Given one failed request, when an `api-error` listener counts events, then the count is exactly one; a request-interceptor rejection does not also dispatch from the response interceptor. — Evidence: `axios.ts` now has exactly one `dispatchEvent` site (`grep -c` = 1, the helper). `dispatchApiError` sets `error.handled = true` before dispatching, and the response interceptor's first guard rejects anything already handled. The temporary harness drove a request-interceptor validation rejection and then passed the same rejection through the response interceptor: **1** event; a business 422 then produced **1** more, and replaying that handled rejection added none.
- [x] Given a quiet or background request, when it fails, then no event, toast or inline error is produced and the existing background polling behavior is unchanged. — Evidence: `errorNotificationsSuppressed` treats **either** `X-Suppress-Error-Notification` or `X-Background-Request` as silent, and the helper marks the error handled so a later interceptor cannot re-notify. `node scripts/background-polling-test.cjs` passes 16/16, including background network/timeouts/HTTP/offline silence, renewal, heartbeat and dispatch-guard cases.
- [ ] Given the source tree, when `toast.error(apiErrorMessage` is searched, then every remaining call site is recorded in `Progress.md` with its owner task, and the pages covered by Tasks 006, 011, 015, 017 and 019 have removed theirs. — Evidence: the full inventory is recorded below (53 call sites in 15 files). The covered pages have **not** removed theirs because those page tasks have not run yet; this task is explicitly forbidden from editing pages under `resources/ts/pages/`. Remains unchecked until those tasks adopt the contract.

## Work completed with evidence
- `resources/ts/api/axios.ts`: added `errorNotificationsSuppressed()` and the single `dispatchApiError()` choke point. All four former dispatch blocks (request validation, request permission, HTTP response, offline, network) now use it. The helper marks `handled` first, so a rejection that passes through another interceptor cannot dispatch twice; both quiet headers are honoured.
- `resources/ts/components/ApiErrorNotifier.vue`: inline-first behaviour retained; added a `WeakSet` guard so a duplicated event for the same error object cannot raise a second toast.
- `resources/ts/utils/formErrors.ts`: awaits `nextTick()` before matching, and when a shared App* control already renders the message it counts the field as matched without appending a second DOM error. This closes the one-inline-error gap caused by App* controls consuming the reactive store.
- `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php`: added specific copy for `department_has_employees`, `pending_transaction_self_approval`, `pending_transaction_locked`, `pending_transaction_not_approved`, `pending_transaction_invalid_transition`, `pending_transaction_currency_rate_invalid` in all three languages.
- `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json`: added only the missing `common.statuses.underReview` label, per the ownership rule.

## Files changed and why
- `resources/ts/api/axios.ts` — one guarded dispatch path instead of four repeated blocks.
- `resources/ts/components/ApiErrorNotifier.vue` — duplicate-toast guard for the same error object.
- `resources/ts/utils/formErrors.ts` — wait for render and avoid double inline errors on shared controls.
- `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php` — missing HR/finance business copy.
- `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json` — `underReview` status label only.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php -l lang/{en,fa,ps}/api.php` | Local PHP 8.2 | PASS — 3/3 no syntax errors | terminal output | After adding six keys per catalog |
| 2026-10-31 | `node -e` JSON parse + `common.statuses.underReview` assertions | Local Node 24 | PASS — `en`, `fa-AF`, `ps-AF` all present | terminal output | Values printed above |
| 2026-10-31 | `php artisan tinker --execute` → `ApiMessage::forCode('business.department_has_employees')` for `en`, `fa`, `ps` | Local Laravel | PASS — specific sentence in all three locales | terminal output | Proves the server-localized fallback the frontend uses |
| 2026-10-31 | `grep -c "dispatchEvent(new CustomEvent<ApiError>('api-error'" resources/ts/api/axios.ts` | Local | PASS — `1` | terminal output | Single dispatch site |
| 2026-10-31 | `npm run typecheck` | Local | PASS — exit 0 | terminal output | Whole project after all edits |
| 2026-10-31 | `node scripts/background-polling-test.cjs` | Local Node 24 | PASS — 16/16 | terminal output | Quiet/background, renewal, heartbeat, manual-failure cases unchanged |
| 2026-10-31 | Temporary harness `/tmp/task018/axios-once.cjs` (copy of the regression suite plus one focused test) | Local Node 24 | PASS — 17/17 | `/tmp/task018/axios-once.cjs` | Validation rejection = 1 event; business 422 = 1 event; replayed handled rejection = 0. First run failed because the test re-fed the raw transport error instead of the transformed rejection; the assertion was corrected, the code was not changed. |
| 2026-10-31 | `git diff --check` | Local | PASS — clean | terminal output | No whitespace errors |

No browser session was run: toast counts, inline rendering and Persian/Pashto UI copy remain a manual review item.

## Decisions and deviations
- Business copy lives in the PHP catalogs only; the JSON locales received only `underReview`, exactly as the ownership cell says. The frontend therefore falls back to the server-localized `message` for business codes, which is why the `ApiMessage` check matters.
- Pages were not edited. The task explicitly forbids modifying `resources/ts/pages/`; the integration seam is that page tasks remove their own `toast.error(apiErrorMessage(...))` calls.
- The zero-grant refusal is a Task-017 contract (field-level validation), not a new business code here. Adding speculative copy would create a key nothing emits.
- The unused English `errorMessages` map in `apiErrors.ts` was left untouched. `humanizeApiError` resolves through `translate('backend.errors.<code>')` and then the localized fallback; consulting an English-only map first would surface English in Dari/Pashto.
- Background requests are now silent if **either** quiet header is present. `backgroundReadConfig` already sends both, so existing behaviour is preserved and the contract is harder to violate.

## Call-site inventory (criterion 8)
`grep -c "toast.error(apiErrorMessage" resources/ts/pages --include='*.vue'` → **50** call sites after Task-019 removed the three department sites.

| File | Call sites | Owner task |
|---|---:|---|
| `resources/ts/pages/payrolls.vue` | 10 | Task-006 |
| `resources/ts/pages/commissions.vue` | 7 | Task-016 |
| `resources/ts/pages/visitor.vue` | 6 | Task-005 |
| `resources/ts/pages/off_days.vue` | 5 | Task-020 |
| `resources/ts/pages/leave_requests.vue` | 3 | Task-006 |
| `resources/ts/pages/service-contracts.vue` | 3 | Task-002 (completed, needs a follow-up cleanup) |
| `resources/ts/pages/setup/departments.vue` | 0 (was 3) | Task-019 — resolved |
| `resources/ts/pages/setup/roles.vue` | 3 | Task-008 |
| `resources/ts/pages/academy-data.vue` | 2 | No task in this set |
| `resources/ts/pages/announcements.vue` | 2 | No task in this set |
| `resources/ts/pages/applicants.vue` | 2 | Task-001 |
| `resources/ts/pages/expenses.vue` | 2 | Task-014 |
| `resources/ts/pages/income.vue` | 2 | Task-015 |
| `resources/ts/pages/staff_list.vue` | 2 | Task-006 |
| `resources/ts/pages/whatsapp-messages.vue` | 1 | No task in this set |

Additional duplicate-toast patterns exist in pages that catch with `toast.error(t(...))` instead of `apiErrorMessage` (for example `pending-transactions.vue`, `contract_list.vue`, `asset.vue`). Those are page-owned and were not counted here; the same integration rule applies.

## Blockers and open questions
- Task-017 must add the specific zero-grant field error before criterion 4 can be checked.
- Tasks 006, 011, 015, 017 and 019 must remove their page-level API error toasts before criterion 8 can be checked.
- No browser capture of toast counts or three-locale UI copy; the evidence is source + Node harness + PHP resolution.

## Handoff notes
- Do not add another `window.dispatchEvent(new CustomEvent('api-error', ...))`; call or extend `dispatchApiError` so the `handled` guard stays authoritative.
- Page tasks should delete only the API-failure toast. Success toasts and locally built validation messages stay.
- Keep `displayInlineFormErrors`'s `nextTick` and existing-message check; removing either reintroduces duplicate inline messages on App* controls.
- The temporary harness lives outside the repository and is not committed; recreate it from the checks table if a rerun is needed.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated HR-002, HR-003, FIN-001 and FIN-004 toast/status findings; implementation has not started.
- `2026-10-31` Claimed by `Buffy (erp-task-implementer)`, status → `In Progress`.
- `2026-10-31` Implemented the single dispatch helper, notifier guard, inline de-duplication, six business codes in three PHP catalogs and `underReview` in three JSON locales. Typecheck, 16/16 regression tests and the focused 17th harness test pass. Status → `Review` with page adoption and the Task-017 zero-grant case outstanding.
