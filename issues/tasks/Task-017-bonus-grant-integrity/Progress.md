# Progress — Task-017

## Status snapshot
- Status: `Review`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-10-31`
- Current focus: `Explicit grant amount, zero/negative refusal, acknowledgment and payroll availability are implemented; focused tests and typecheck pass.`
- Blocker: `None`
- Next action: `Reviewer confirms the one-amount rule and the legacy zero-row handling, then runs the positive/zero grant walkthrough.`

## Acceptance criteria progress
- [x] Given a nomination with a zero selected amount, when the user opens Grant, then the confirmation shows zero and refuses to confirm with a specific message instead of writing a zero grant. — Evidence: the dialog renders `grantAmount` (0 for a zero nomination), shows `common...grantAmountRequired` in a `VAlert`, and disables Confirm via `grantAmountInvalid`; `confirmGrantBonus` also guards `amount <= 0` before calling the API. `BonusGrantTest::test_zero_stored_amount_cannot_be_granted` proves the server refuses the same case with `errors.grantedAmount.0 = business.bonus_amount_required` and leaves the row ungranted.
- [x] Given a nomination with a positive amount, when the user confirms, then the dialog displays that amount, the payload includes it explicitly, and the persisted `granted_amount` equals it. — Evidence: the page sends `{ isGranted: true, grantedAmount: amount }`; `WorthyEmployeeController::update` resolves that explicit amount, sets both `selected_amount` and `granted_amount`, and the response is assigned back to the row. `test_explicit_amount_is_the_amount_shown_and_persisted` asserts `1250.5` in the response, `granted_amount` and `selected_amount`.
- [x] Given a zero or negative grant submitted directly to the API, when it is validated, then it is rejected with a field-level error and no row is modified. — Evidence: zero is refused with `business.bonus_amount_required` on `grantedAmount`; negative is refused by `validation.min`. `test_explicit_zero_grant_is_rejected_without_changing_the_nomination` and `test_negative_grant_is_rejected_by_validation` assert both, including that the nomination remains ungranted.
- [x] Given a successful grant, when the response returns, then a toast names the employee, month and amount and the row updates without a page reload. — Evidence: the page replaces the row from `response.data.data` and toasts `grantedToast` with `{ employee, month, amount }` in the active locale. No browser capture.
- [x] Given a failed grant, when the request fails, then one specific error toast appears and the dialog stays open so the user can correct the input. — Evidence: the page does not raise its own toast (Task-018's notifier owns exactly one); it stores `apiFieldErrors(error).grantedAmount || apiErrorMessage(error)` in `grantError`, renders it beside the amount, and leaves `isConfirmGrantVisible` true. No browser capture.
- [x] Given a granted bonus and a generated payslip for the same employee and month, when payroll runs, then the payslip includes the granted amount and not zero. — Evidence: `test_granted_amount_is_available_to_the_payroll_query` grants 1000 and calls `GET /salaries/granted-bonuses?employee_id=…&salaryMonth=2026-10`, backed by `SalaryController::availableGrantedBonusesQuery()`; it returns the row with `grantedAmount = 1000`. Limitation: the test proves the payroll attachment query, not a full salary generation/payslip render (that requires attendance fixtures).
- [x] Given an already-granted bonus, when a second grant or edit is submitted, then the immutable-record error is returned and the stored amount is unchanged. — Evidence: `test_a_granted_bonus_cannot_be_granted_again` gets `business.granted_bonus_immutable` and asserts both `granted_amount` and `selected_amount` remain 1000.

## Work completed with evidence
- `WorthyEmployeeController::update` now accepts `grantedAmount`, resolves one grant amount (explicit `grantedAmount` → `selectedAmount` → `amount` → stored nomination), and rejects a resolved value `<= 0` with a field-level `business.bonus_amount_required` error inside the locked transaction. A successful grant writes the same value to `selected_amount` and `granted_amount`, keeps the base-amount snapshot calculation, and preserves the existing immutability/salary checks.
- `resources/ts/api/bonuslist.ts` now types `grantedAmount` on the update payload.
- `resources/ts/pages/bonuslist.vue` now:
  - sends the displayed amount explicitly on grant;
  - shows a zero-blocking alert and disables Confirm for a zero/invalid amount;
  - guards double submission with `granting`;
  - keeps the dialog open and shows the specific field message on failure;
  - shows a localized success toast naming the employee, month and amount;
  - updates the row from the response without reloading.
- `lang/{en,fa,ps}/api.php`: added `business.bonus_amount_required` in all three languages. This also supplies Task-018's zero-bonus case.
- `locales/{en,fa-AF,ps-AF}.json`: added `grantAmountRequired` and `grantedToast` under the existing bonus-list subtree.
- `tests/Feature/BonusGrantTest.php`: six regression tests covering zero stored, explicit zero, negative, positive persistence, repeat grant and payroll availability.

## Files changed and why
- `app/Http/Controllers/WorthyEmployeeController.php` — explicit one-amount resolution and zero refusal.
- `resources/ts/api/bonuslist.ts` — `grantedAmount` payload type.
- `resources/ts/pages/bonuslist.vue` — dialog amount/error/disabled state, success toast, explicit payload.
- `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php` — zero-grant business copy.
- `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json` — two bonus-list keys per locale.
- `tests/Feature/BonusGrantTest.php` — new regression coverage.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php -l` controller, test and three catalogs | Local PHP 8.2 | PASS | terminal output | 5 files |
| 2026-10-31 | `npm run typecheck` | Local | FAIL then PASS | terminal output | First run: `grantedAmount` missing from `UpdateEmployeeData` (TS2353). Added the field; rerun exit 0. |
| 2026-10-31 | `php artisan test --filter=BonusGrantTest` | Local | PASS — 6 tests, 27 assertions | terminal output | Final run |
| 2026-10-31 | Locale JSON key assertions | Local Node 24 | PASS | terminal output | `grantAmountRequired` and `grantedToast` in all three locales |
| 2026-10-31 | `php artisan tinker` → `ApiMessage::forCode('business.bonus_amount_required')` for `en`, `fa`, `ps` | Local Laravel | PASS | terminal output | See final check |
| 2026-10-31 | `git diff --check` | Local | PASS | terminal output | Clean |

No live browser walkthrough was performed, so the dialog's zero state, inline error and toast count are evidenced by source and API tests rather than a screenshot.

## Decisions and deviations
- **One resolved amount:** explicit `grantedAmount` wins, then `selectedAmount`, then `amount`, then the stored nomination. The grant writes that same value to both `selected_amount` and `granted_amount` so the dialog, payload, database and resource cannot disagree.
- **Field key is `grantedAmount`** for the refusal; the task allows `selectedAmount` or `grantedAmount`.
- **The page does not toast API failures.** Task-018's notifier raises the single error toast; the dialog adds a local field message so the user can correct the amount. The page's "one specific error toast" criterion is therefore satisfied by the global owner, with no duplicate.
- **Legacy zero granted row:** the demo row is already `is_granted = 1` with zero and is protected by the immutability rule. It cannot be corrected through the grant endpoint by design. It needs a reviewed one-off data correction (or editing while still ungranted); no automatic backfill was added.
- **No full payslip test:** the payroll query that attaches granted bonuses is covered; generating a salary requires attendance fixtures and would duplicate Task-006's surface.
- The page's existing `console.error` calls in unrelated fetch/dismiss paths were left in place; the API layer already logs failures, and removing them is outside this task.

## Blockers and open questions
- A product/finance decision is needed for the existing granted-zero demo row: correct the data directly or add an explicit audited correction flow. The normal grant path now refuses new zero grants.
- The grant confirmation's success toast and zero-state alert were not visually verified in a browser.

## Handoff notes
- Do not reintroduce a grant that copies `selected_amount` without checking it; `grantedAmount` is the single resolved value.
- If a correction flow is approved for legacy zero rows, it must be audited and must not weaken `business.granted_bonus_immutable` for ordinary edits/grants.
- `business.bonus_amount_required` is now part of the three PHP catalogs; Task-018's zero-bonus case can be checked.
- Payroll continues to attach only `is_granted = true`, `granted_amount > 0` rows through `availableGrantedBonusesQuery()`.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated HR-001; implementation has not started.
- `2026-10-31` Claimed by `Buffy (erp-task-implementer)`, status → `In Progress`.
- `2026-10-31` Implemented the explicit amount, zero/negative refusal, dialog states, success toast, three-locale copy and six tests. Added the missing `grantedAmount` API type after the first typecheck. Final focused run: 6 passed, 27 assertions. Status → `Review`.
