# Task Task-018 — Make API failures produce one specific message, not two

## Metadata
- Task ID: `Task-018`
- Title: `Make API failures produce one specific message, not two`
- Initial status: `Ready`
- Type: `Frontend`
- Priority: `P1`
- Owner role: `Frontend platform implementer`
- Estimated size: `M`
- Source plan/version: `fin-issues/issues/FIN-001-payment-request-self-approval-toasts.md`; `hr-issues/issues/HR-002-department-delete-generic-duplicate-toast.md`; `hr-issues/issues/HR-003-duplicate-toast-department-manager.md`; `fin-issues/issues/FIN-004-send-to-review-raw-status.md`; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `FIN-001-TOAST`, `FIN-004`, `HR-002`, `HR-003`

## Objective
A rejected API request produces exactly one user-facing message, always in the user's language, and pages never add a second generic toast on top of the global one.

## Context and problem
`axios.ts` dispatches an `api-error` event for every non-quiet failure. `ApiErrorNotifier.vue` always shows `apiErrorMessage(error)`, except when it can place a 422 field error inline. Pages also catch the same rejection and call `toast.error(...)` or `toast.error(apiErrorMessage(...))`. Department save/delete, income changes, bonus grants and payment requests therefore show two toasts for one failed request, and the page's generic copy often wins over the server's specific reason.

`apiErrorMessage` maps codes through `backend.errors.<code>`; the catalog has `department_manager_not_supervisory` and `department_manager_rank_invalid`, but no entry for `department_has_employees` or `pending_transaction_self_approval`, so those refusals fall back to generic copy. `under_review` is translated through `common.statuses.underReview`, which is absent from `en.json`, so the status renders as the raw token in toasts and chips.

## Scope
### Included
- Establish the global notifier as the single owner of API failure feedback: pages show success messages and local validation only, never a second API error toast.
- Keep inline field errors as the preferred 422 presentation; when a matching field is found, show the inline message and no toast.
- When no field can be matched, show exactly one toast built from the server's specific code/message, never the page's generic text.
- Add the missing business-code translations for `department_has_employees`, `pending_transaction_self_approval` and any nearby codes exercised by the HR/finance tasks, in English, Persian and Pashto.
- Add the missing `common.statuses.underReview` translation so `under_review` never renders as a raw token.
- Ensure exactly one `api-error` event is dispatched per request (the request interceptor may already reject before the response interceptor runs).
- Preserve quiet/background behavior: no event, no toast and no inline errors for `X-Suppress-Error-Notification` and `X-Background-Request`.
- Inventory every `toast.error(apiErrorMessage` call site and record which page task removes it.

### Excluded
- Page-specific success copy, dialogs and form layouts (owned by Tasks 006, 011, 015, 017, 019).
- Translating every backend error that is not exercised by these issues.
- Changing HTTP status handling, token refresh, offline blocking or background polling.
- Removing validation toasts for locally-built client rules.

## Preconditions and assumptions
- The intended precedence is: inline field error first, otherwise one toast. A user should never receive both for the same request.
- Pages that handle an API error for a specific workflow (for example a failed payroll generation) may show their own message only after calling the request with `quiet` or after the global event is suppressed; this task defines the contract, page tasks adopt it.
- `lang/en/api.php`, `lang/fa/api.php` and `lang/ps/api.php` are the backend error catalogs; `resources/ts/plugins/i18n/locales/*.json` hold the frontend status labels. Both may need a key for a complete fix.
- Assumption to validate: no page currently depends on the global toast *and* its own toast to show different information. If one does, record the exception rather than silently removing copy.

## Dependencies
- `None` — this is the upstream contract for the page-level tasks. Tasks 006, 011, 015, 017 and 019 depend on it.

## Contracts and boundaries
- Input/output: no route or payload changes. `ApiError` keeps `code`, `message`, `errors`, `messages`, `status`, `handled`.
- Compatibility guarantee: `humanizeApiError`, `apiFieldErrors` and `apiErrorMessage` keep their signatures; quiet and background behavior is unchanged.
- Error behavior: one failed request yields at most one visible message. A 422 with a matched field is inline-only; a 422 without a field is one toast; 401/403/429/500/network each yield one toast.
- Permission/data scope: unchanged.
- Integration seam: page tasks remove their own `toast.error(apiErrorMessage(...))` calls; they do not mute the global notifier or clear unrelated toasts.
- Files this task must not modify: any page under `resources/ts/pages/`, `resources/ts/components/FullDetailsButton.vue`, `app/Http/Controllers/**`.

## Exclusive file ownership
- `resources/ts/api/axios.ts` — single event dispatch and quiet handling.
- `resources/ts/components/ApiErrorNotifier.vue` — inline-first, one-toast fallback.
- `resources/ts/utils/apiErrors.ts` — business-code map and message selection.
- `resources/ts/utils/formErrors.ts` — field matching and clearing contract.
- `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php` — missing business copy.
- `resources/ts/plugins/i18n/locales/en.json`, `fa.json`, `ps.json` — missing `common.statuses.underReview` label only.

## Acceptance criteria
- [ ] Given a 422 with a field that exists on the form, when the response returns, then the field shows one inline error and the notifier adds no toast.
- [ ] Given a 422 with no matching field, when the response returns, then exactly one toast shows the server's specific message, not the page's generic fallback.
- [ ] Given a 500, 403, 429, offline write or network failure, when it occurs, then exactly one localized toast appears and no `business.*`, `validation.*` or `server.error` token is visible.
- [ ] Given a department delete refusal, a department manager refusal, a self-approval refusal or a zero bonus grant, when the API rejects it, then the message names the real reason in English, Persian and Pashto.
- [ ] Given a payment request in `under_review`, when its status renders in a chip or toast, then the label is translated and never the raw token.
- [ ] Given one failed request, when an `api-error` listener counts events, then the count is exactly one; a request-interceptor rejection does not also dispatch from the response interceptor.
- [ ] Given a quiet or background request, when it fails, then no event, toast or inline error is produced and the existing background polling behavior is unchanged.
- [ ] Given the source tree, when `toast.error(apiErrorMessage` is searched, then every remaining call site is recorded in `Progress.md` with its owner task, and the pages covered by Tasks 006, 011, 015, 017 and 019 have removed theirs.

## Verification plan
- Automated: `npm run typecheck`; search for `toast.error(apiErrorMessage`, `toast.error(apiErrorMessage(` and `api-error` dispatches; run any existing frontend error-handling test if present.
- Manual: trigger a 422 with a field (department manager), a 422 without a field (department delete), a 403, an offline write and a 500; count toasts; inspect Persian/Pashto copy; verify `under_review` in the payment-request table and in the review toast.
- Fixtures: one populated department, one ineligible manager, one submitted payment request owned by the current user, one offline session. Non-production only.
- Regression: confirm background heartbeat requests stay silent, login/refresh errors still redirect, and successful mutations still show their page success toasts.
- Evidence: toast counts, screenshots in three locales, listener-count result, inventory of remaining call sites.

## Implementation notes
Keep the event dispatch in one interceptor path: if the request interceptor rejects a client-side validation error, mark it handled so the response interceptor does not dispatch again. In the notifier, attempt inline placement first and return early when at least one field matched; otherwise show one toast from `apiErrorMessage`. Add business copy under the existing `backend.errors.business` catalog in all three PHP locale files, and add only the missing status label to the JSON locales. Do not edit page files in this task; the page tasks remove their duplicate calls.

## Risks and rollback
- Risk: removing a page toast also removes information the global message lacks. Mitigation: inventory first, keep the page's specific text in the server message or an inline error before removing the toast.
- Risk: suppressing the global toast for a quiet mutation hides a real failure. Mitigation: quiet is only for background reads and explicitly handled flows; verify each caller.
- Data migration: none. Rollback is code-only.
- Stop condition: if a page cannot express its failure through the shared contract without losing required copy, record the exception and leave that page's toast in place rather than double-notifying.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
