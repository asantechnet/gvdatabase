# Progress — Task-005

## Status snapshot
- Status: `Review`
- Owner: `Implementer (agent)`
- Last updated: `2026-10-31`
- Current focus: `Implementation complete; manual viewport and assistive-technology checks outstanding`
- Blocker: `None`
- Next action: `Run the 1280x720 Add Visitor dialog and screen-reader pass, then move to integration.`

## Acceptance criteria progress
- [x] Given the Add Visitor dialog, when the service field is focused, then it offers exactly the four built-in services and does not accept arbitrary text. — Evidence: `resources/ts/components/crm/VisitorProfileFields.vue` renders `ErpField kind="select"` from the shared `BUILT_IN_SERVICES` constant (single source with cases/reports); `validateForm()` in `visitor.vue` rejects any value outside that list.
- [x] Given a direct API request with an unsupported service, when it is submitted, then it is rejected with a field-level `service` error and no visitor row is written. — Evidence: `VisitorServiceTest::test_unsupported_service_values_are_rejected_with_a_field_error` covers `Scholarship`, `consulting`, `test offer` and `''` and asserts the visitor count is unchanged.
- [x] Given historical visitor rows with label-case service values, when the migration runs, then they become canonical keys and the resource returns both key and translated label; unknown values remain visible for review. — Evidence: `VisitorServiceTest::test_normalization_maps_known_labels_and_keeps_unknown_values`; `VisitorResource` adds `service_key`, `service_label`, `service_label_key`; unmapped values are stored unchanged and logged as a warning by the migration.
- [x] Given zero flagged visitors, when the Follow-up list opens, then it shows an empty state, a follow-up-specific title and an active navigation state, and the Visitors list shows the unflagged visitors. — Evidence: `ErpPageHeader` declares the `?list=follow-up` tab (active-state compares the declared query keys); the page title switches to `common.navigation.followUpList`; `TableEmptyState` now uses the follow-up-specific `followUpEmpty` copy; `VisitorFollowUpTest` asserts the two lists are split and that a converted visitor is in neither.
- [x] Given a visitor is starred, when the lists reload, then it leaves the Visitors list and appears in Follow-up; unstarring reverses that exactly. — Evidence: `VisitorFollowUpTest::test_a_visitor_can_be_moved_into_and_back_out_of_the_follow_up_list`.
- [x] Given the qualification fields, when the form renders, then percentage is labelled as a 0-100 score and age shows its allowed min/max, and both are enforced server-side. — Evidence: `gradeScoreLabel`/`gradeScoreHint`/`ageHint` in all three locales, `min`/`max` on the inputs, and `age` `min:1|max:120` / `grade_score` `min:0|max:100` in `VisitorController`.
- [x] Given a visitor row at 1280 px, when a keyboard or screen reader user reaches the actions, then every icon button has an accessible name. — Evidence: by inspection, star, create-contract, unstar, consultant review, edit, delete and print buttons each carry `aria-label`; previously only tooltips (not a reliable accessible name) were present. No live screen-reader run was performed.
- [ ] Given a 720 px tall Add Visitor dialog, when the form is scrolled to the notes field, then the sticky footer does not cover the field or its error text. — Evidence: structurally supported (both dialogs use `scrollable`, so only `VCardText` scrolls and `VCardActions` stays outside the scroll area), but no viewport check was run. Unchecked.

## Work completed with evidence
- Visitor service is now a select of the four built-in services in the shared profile field set, replacing free text.
- Server-side create and update validate `service` with `Rule::in(BuiltInService::ALL)`; unsupported values return 422 `errors.service`.
- `VisitorResource` returns `service` (stored value) plus derived `service_key`, `service_label` and `service_label_key`, so canonical rows render translated labels and legacy rows still show what was stored.
- New migration `2026_11_01_000003_normalize_visitor_services.php` records every original value in `legacy_service`, rewrites only known labels case-insensitively (`Scholarship` → `scholarship`, `Test Offer`/`Test Offer`/`testoffer` → `test_offer`, `Private` → `private`, `Academy` → `academy`), leaves unknown text untouched and logs the distinct unknown values.
- List, print report and edit dialog use the canonical label; editing a legacy row requires choosing one of the four services (unknown text is not a selectable option and would be rejected on save).
- Follow-up queue: distinct title, follow-up-specific empty state, reversible star/unstar, and named row actions.
- Corrected `VisitorFollowUpTest`'s ordering assertion to the endpoint's documented newest-first order (visit date desc, id desc) — this was the pre-existing ordering failure recorded in `docs/facts/testing.md`, caused by the test expecting insertion order, not by the controller.

## Files changed and why
- `app/Http/Controllers/VisitorController.php` — built-in service validation on create/update (plus the existing follow-up filter).
- `app/Http/Resources/VisitorResource.php` — canonical key, label and translation key alongside the stored value.
- `database/migrations/2026_11_01_000003_normalize_visitor_services.php` — additive `legacy_service` column, label normalization, reversible restore.
- `resources/ts/api/visitor.ts` — typed canonical service fields and `BuiltInService` payload/form types.
- `resources/ts/components/crm/VisitorProfileFields.vue` — service select from the shared constant, score/age labels, hints and ranges.
- `resources/ts/pages/visitor.vue` — service label rendering, field-level service check, follow-up empty state, accessible names on every icon action.
- `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json` — `gradeScoreLabel`, `gradeScoreHint`, `ageHint`, `followUpEmpty`.
- `tests/Feature/VisitorServiceTest.php` — new coverage for rejection, canonical payload and normalization.
- `tests/Feature/VisitorFollowUpTest.php` — ordering assertion aligned to the endpoint contract.
- `tests/Feature/AgencyWorkflowCoherenceTest.php`, `tests/Feature/VisitorProfileAndServiceContractFieldSetsTest.php` — visitor fixtures now send canonical service keys.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php artisan test` on `VisitorServiceTest`, `VisitorFollowUpTest`, `VisitorProfileAndServiceContractFieldSetsTest`, `AgencyWorkflowCoherenceTest`, `FinanceRetirementBoundaryTest` | Root checkout, MySQL `testing` | 29 passed / 1 failed (275 assertions, 46.6 s) | tool output | Only failure was `VisitorFollowUpTest` ordering (pre-existing per `docs/facts/testing.md`); fixed the assertion, not the controller. |
| 2026-10-31 | `php artisan test tests/Feature/VisitorFollowUpTest.php tests/Feature/VisitorServiceTest.php` | Root checkout, MySQL `testing` | Pass — 7 tests / 67 assertions (43.3 s) | tool output | Re-run after the ordering fix. |
| 2026-10-31 | `php -l` on changed PHP and migration files | PHP 8.2 | Pass | — | |
| 2026-10-31 | `npm run typecheck` | Node | Pass (exit 0) | — | Two type errors found and fixed: submit payload cast after validation, and legacy service mapped to `''` in the edit form. |
| 2026-10-31 | JSON parse of `en`, `fa-AF`, `ps-AF` locales | Node | Pass | — | |
| 2026-10-31 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | Python 3 | Pass — 20 tasks, 0 errors | `issues/tasks/INDEX.md` | Structural validation only. |

## Decisions and deviations
- Unknown historical services are preserved verbatim rather than coerced to a built-in; they remain visible in the list/print report and are logged once per distinct value by the migration.
- `legacy_service` is written for every row (including canonical ones) so `down()` restores exact original casing; the column is additive and nullable.
- Editing a visitor whose service is legacy text starts the select empty and requires a canonical choice; the server would reject the legacy text on save anyway, so this makes the requirement explicit instead of silently changing the service.
- The submit payload casts the validated service to `BuiltInService`; `validateForm()` runs first and guarantees the value is one of the four.
- `VisitorFollowUpTest` fixture now expects `['Plain two', 'Plain one']`, matching the controller's documented `date desc, id desc` ordering.
- Migration rollback (`down()`) was not exercised in a test: dropping a column inside `RefreshDatabase`'s transaction causes an implicit commit, and the repository's migration round-trip tests require `DatabaseTruncation`. Restore logic is a single `service = legacy_service` update followed by the column drop.

## Blockers and open questions
- None blocking. Manual checks outstanding: 1280x720 dialog (notes/footer overlap), 390x844 layout, RTL, dark mode, live screen-reader pass.

## Handoff notes
- Service vocabulary is frozen to `BuiltInService::ALL`; any new service must be added to the backend constant, `BUILT_IN_SERVICES` in `resources/ts/api/applicants.ts` and the three locale files together.
- Task-013 owns shared table primitives; this task only added per-row `aria-label`s and did not modify `resources/ts/components/responsive-data-table/**`.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issues 05 and 15; implementation has not started.
- `2026-10-31` Implemented the controlled service vocabulary, normalization migration, resource labels, follow-up empty state and accessible row actions; tests, lint, typecheck and the task validator pass. Status moved to `Review` pending manual viewport and assistive-technology checks.
