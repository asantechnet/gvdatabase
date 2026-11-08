# Progress — Task-003

## Status snapshot
- Status: `Review`
- Owner: `Full-stack implementer`
- Last updated: `2026-10-31`
- Current focus: `Implementation and task-local verification complete; viewport/visual checks remain for review.`
- Blocker: `None`
- Next action: `Review the diff, then check the register at 390 and 1280 px in LTR/RTL: expiry badges, extension chip, scheduled status, payment column and the expiring filter chip.`

## Acceptance criteria progress
- [x] Given an active employee contract ending within 30 days, when the register opens, then the row shows its end date, days remaining and an expiring badge, and the row is visually distinguishable from a non-expiring active contract. — Evidence: `ContractRegisterTest::test_register_reports_role_expiry_and_scheduled_extension_state` (pass) asserts `days_remaining = 10` and `expiry_state = expiring`; the page renders the end date with the days label and a warning tint via `:row-props`. Tint not visually checked.
- [x] Given the register URL contains the expiring filter, when the page loads, then only active contracts with an end date inside the 30-day window are listed, and an active-filter chip with a clear action is visible. — Evidence: `ContractRegisterTest::test_expiring_filter_lists_only_live_active_contracts_inside_the_window` (pass): includes 10-day and already-expired active contracts, excludes far-future, completed and cancelled records. `expiringOnly` initialised from `?expiring=1` with a closable chip and `clearExpiring`.
- [x] Given an extension contract with a future start date, when it appears in the list, then it shows its extension role and parent contract number and is labelled scheduled or not yet effective rather than simply Active. — Evidence: same test asserts `contract_role = extension`, `parent_contract_number`, `is_scheduled = true` and `display_status = scheduled`; the register renders an "Extension · of {number}" chip and a Scheduled status chip.
- [x] Given an applicant service contract with a partially paid line, when the register renders for a viewer allowed to see financials, then the payment column shows partially paid and the outstanding amount with its currency. — Evidence: `ContractRegisterTest::test_register_shows_service_line_payment_and_a_defined_not_applicable_state` asserts `payment.status = partially_paid`, `outstanding = 60`, currency `AFN`; the column shows the status chip plus "Outstanding: 60 ؋".
- [x] Given an employee or other contract with no service line, when the register renders, then the payment column shows a defined not-applicable state rather than a misleading dash or zero. — Evidence: same test deletes the lines and asserts `payment.applicable = false` with `status = null`; the column renders the translated "Not applicable".
- [ ] Given a 1280 px viewport, when the register first renders, then status and row actions are reachable without horizontal hunting, using the shared table affordances from Task-013. — Evidence: not verified; the register uses the shared `ResponsiveDataTableServer` with its sticky-action defaults, but no viewport check was run.
- [x] Given an expiring contract row, when the user opens its actions, then a renewal action is available or explicitly explained as unavailable, and it preserves the primary/extension relationship. — Evidence: the existing `canExtend`/`extendContract` path is unchanged and still links the new draft to its primary parent; extension rows are excluded from extending. Not clicked through in a browser.

## Work completed with evidence
- `GET /api/contracts` accepts `expiring` and filters to non-cancelled active contracts with an end date on or before today + 30 days.
- `ContractResource` adds `parent_contract_number`, `days_remaining`, `expiry_state`, `is_scheduled`, `display_status` and a permission-gated `payment` summary computed from the already-loaded service lines (no payment service call).
- Register page shows end date + days label, expiring/expired badges and row tint, extension role/parent chip, scheduled status, payment status/outstanding, and the visible expiring filter chip with clear action.

## Files changed and why
- `app/Http/Controllers/ContractController.php` — `expiring` validation/filter and `parentContract` eager load.
- `app/Http/Resources/ContractResource.php` — additive role/expiry/schedule/payment fields; presentation only.
- `resources/ts/api/contracts.ts` — typed fields and `expiring` parameter serialization.
- `resources/ts/composables/useContracts.ts` — unchanged after review: parameter serialization lives in `api/contracts.ts::listPage`.
- `resources/ts/pages/contract_list.vue` — end-date/payment columns, role chip, scheduled status, expiry badges/row tint, expiring chip/clear, report row alignment.
- `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json` — register labels in three locales.
- `tests/Feature/ContractRegisterTest.php` — new suite (4 tests).

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php -l` changed PHP files | Local | Pass | — | No syntax errors |
| 2026-10-31 | JSON parse of en/fa-AF/ps-AF locales | Local | Pass | — | Valid |
| 2026-10-31 | `DB_DATABASE=testing_task3 php artisan test tests/Feature/ContractRegisterTest.php` | Isolated MySQL | Pass (4 tests, 36 assertions) | — | Database dropped afterwards |
| 2026-10-31 | `npm run typecheck` | Local | Pass (exit 0) | — | After adding `n` from `useI18n()` |
| 2026-10-31 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | Local | Pass (20 tasks, 0 errors) | — | Structural only |
| 2026-10-31 | `git diff --check` | Local | Pass | — | No whitespace errors |

## Decisions and deviations
- Expiring definition (frozen for the dashboard deep link): `is_cancelled = false`, `status = active`, `end_date` not null and `end_date <= today + 30 days`; already-expired active contracts are included because the dashboard attention query has the same upper bound.
- Payment visibility uses the existing `view_applicant_financials` permission; without it the key is omitted and the page hides the column.
- `payment.applicable = false` (not zero, not a dash) is the defined state for employee/other contracts and for contracts with no service lines.
- `display_status` is derived (`scheduled` for an extension whose start date is in the future); the stored status is never rewritten.

## Blockers and open questions
- None.

## Handoff notes
- Ready for review. Remaining manual checks: 390/1280 px LTR/RTL, expiry tint contrast in dark mode, and clicking the extension/renew action.
- `git status` contains unrelated modifications from other tasks; only the files listed above belong to Task-003.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issues 04, 06 and 17; implementation has not started.
- `2026-10-31` Implemented the expiring filter, additive role/expiry/schedule/payment resource fields, register columns/chips and three-locale copy; added `ContractRegisterTest`. Typecheck, task tests and the validator pass. Status moved to `Review`.
- `2026-11-07` Forward ownership of the paths needed for Task-046 moved to Task-046; the delivered behaviour remains historical evidence.
