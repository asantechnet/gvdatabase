# Progress — Task-029

## Status snapshot
- Status: `Review`
- Owner: `Full-stack Laravel/Vue implementer`
- Last updated: `2026-11-04`
- Current focus: `Implemented end to end; the browser viewport pass remains outstanding.`
- Blocker: `None.`
- Next action: `Run /applicant-stages at 390, 768 and 1280 px in LTR and RTL under light and dark, then nominate a Dari/Pashto reviewer for the new stage copy.`

## Acceptance criteria progress
- [x] Given the stage catalogue for scholarship, private, test offer and academy, when it is read with `all=1`, then scholarship, private and test offer contain exactly `completed`, `cancelled`, `rejected` and `withdrawn` as terminal rows and academy contains exactly `completed`, `cancelled` and `withdrawn`, each reports `is_terminal: true`, `is_active: true` and `is_locked: true`, and no other row for that service is terminal.
- [x] Given the test-offer catalogue, when it is read, then a locked `rejected` terminal stage exists with a display order after the existing outcomes, and the academy catalogue has no `rejected` terminal stage.
- [x] Given a create or update request that submits `is_terminal: true`, when the API processes it, then the request is rejected with `errors.is_terminal` and the terminal-managed business code, no terminal row is created or promoted, and the model refuses the same write outside the controller.
- [x] Given the stages editor, when it opens the add or edit dialog, then no terminal switch is offered, a newly created stage is non-terminal, and locked terminal rows show the terminal and lock chips without edit or delete controls.
- [x] Given an academy stage, when it is created or updated with country, university or test values, then those values are forced to null, the editor shows no country, university or test selector, and the catalogue labels the stage as having no dependency.
- [x] Given a test-offer stage, when it is created or updated, then country and university are forced to null, `test_id` may reference an active educational test, and the editor offers a Test select with an "All tests" option for a null value.
- [x] Given `GET /api/applicant-stages?service=test_offer&test_id=A`, when the catalogue loads for a picker, then it returns shared stages with `test_id = null` plus stages assigned to test A and excludes stages assigned only to test B.
- [x] Given `GET /api/applicant-stages?line_id=...` or `?applicant_id=...`, when it is requested by a viewer with `view_applicant_stages`, then it returns only the active stages that apply to that case's service, test, country and university, and it exposes no applicant financial or personal payload.
- [x] Given a test-offer case whose test is A, when a move targets a stage assigned only to test B, then the request is rejected with `errors.stage_id` and `business.applicant_stage_context_mismatch`, and no stage, history or task row changes; a shared stage or a test-A stage succeeds.
- [x] Given an academy case, when it moves to any active academy stage, then the move succeeds without a country, university or test requirement, and no scope guard rejects it.
- [x] Given a scholarship or private case, when stage scope is validated, then the existing country/university rule is preserved and a supplied `test_id` is forced to null; the stage editor still offers the country and university selectors.
- [x] Given a test referenced by an applicant stage, when an administrator deletes that test from educational data, then the request is refused with `409 business.master_data_in_use`; an unreferenced test remains deletable.
- [x] Given the frontend locales and backend catalogues, when `npm run i18n:check` and a key search run, then the new test-scope, no-dependency and terminal-managed strings exist in English, Dari and Pashto, and no removed terminal-switch key is still referenced.
- [x] Given a fresh database, when migrations run twice, then the first run adds the test scope and canonical terminal rows idempotently, the second run is a no-op, and the migration is portable to SQLite and MySQL.
- [ ] Given 390, 768 and 1280 px viewports in LTR and RTL under light and dark themes, when the stages editor renders for scholarship, test offer and academy, then the conditional scope fields, test column and locked terminal rows remain reachable and legible without horizontal page overflow.

## Work completed with evidence

### Migrations
- `2026_11_04_000001_add_test_scope_to_applicant_stages` adds nullable `applicant_stages.test_id` (FK to `tests`, `nullOnDelete`), the `(service, test_id)` index, and clears `country_id`/`university_id` for every test-offer and academy row. Down drops the index, FK and column; cleared scope values are not restored.
- `2026_11_04_000002_enforce_canonical_terminal_stages` restores/inserts `test_offer.rejected` at `max(sort_order) + 10`, marks the canonical rows terminal/locked/active, and demotes non-canonical terminal rows with the query builder. Demotions are counted and logged, never deleted. Academy keeps three outcomes.
- Both migrations use Blueprint/query-builder calls only; no raw SQL and no model events.

### Backend
- `ApplicantStage` gains `test_id` and the `test()` relation; `is_terminal` is removed from `$fillable` and from the JSON write path. `creating` forces new rows non-terminal; `updating` refuses a dirty terminal flag with `errors.is_terminal` and `business.applicant_stage_terminal_managed`. The `is_locked` 409 guard is unchanged.
- `ApplicantStageController` adds `test_id`, `line_id` and `applicant_id` reads; returns active case-scoped stages through `ApplicantStageContext`; normalizes scope per service (test offer: test only; academy: no scope; scholarship/private: destination scope only); rejects any request that submits `is_terminal`, including `false`.
- `ApplicantStageContext` is the single applicability rule: academy always applies, test offer matches a null or equal `test_id`, admission services match the country/university detail row, unknown services never apply. It exposes both `appliesTo()` and `stagesFor()`/`queryFor()`.
- `Applicant` and `ApplicantContract` updating guards call the context service when `current_stage_id` is dirty, so a cross-service/test/destination write is refused even outside the move controllers. Initial creation is deliberately not guarded.
- `EducationalMasterDataController` refuses to delete a test referenced by any `applicant_stages.test_id`, including soft-deleted stages, with `409 business.master_data_in_use`.
- `business.applicant_stage_terminal_managed` and `business.applicant_stage_context_mismatch` exist in the English, Dari and Pashto API catalogues.

### Frontend
- New `resources/ts/api/applicantStages.ts` extends the shared stage type with `test_id`/`test` and exposes `list`, `catalogue`, `create`, `update`, `remove` without changing `resources/ts/api/applicants.ts`.
- `/applicant-stages` shows country/university only for scholarship/private, a Test column only for test offer, and no dependency column for academy. The dialog offers country/university for admission services, a Test select with an "All tests" null option for test offer, and no dependency control for academy. The terminal switch and its hint are gone; a system-owned terminal note replaces them. Locked rows keep the lock chip and have no edit/delete controls.
- New copy exists in `en`, `fa-AF` and `ps-AF`; the dead `terminalStageHint` key is removed.

## Files changed and why
- `database/migrations/2026_11_04_000001_add_test_scope_to_applicant_stages.php` — test scope and unused-scope cleanup.
- `database/migrations/2026_11_04_000002_enforce_canonical_terminal_stages.php` — canonical terminal set and demotion.
- `app/Models/ApplicantStage.php` — test relation, system-owned terminal status, new-row defaults.
- `app/Models/Applicant.php`, `app/Models/ApplicantContract.php` — context write guards.
- `app/Services/ApplicantStageContext.php` — single applicability rule.
- `app/Http/Controllers/ApplicantStageController.php` — scoped reads, normalization, terminal rejection.
- `app/Http/Controllers/EducationalMasterDataController.php` — test deletion guard.
- `resources/ts/api/applicantStages.ts` — typed stage catalogue client.
- `resources/ts/pages/applicant-stages.vue` — conditional editor, dependency column, terminal note.
- `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json` — new stage copy, removed terminal hint.
- `lang/{en,fa,ps}/api.php` — terminal-managed and context-mismatch codes.
- `tests/Feature/ApplicantStageCatalogTest.php` — canonical set, ordering, terminal rejection.
- `tests/Feature/ApplicantStageScopeTest.php` — normalization, test picker, test deletion.
- `tests/Feature/ApplicantStageContextTest.php` — case reads, cross-test/country moves, model guard.
- `issues/tasks/INDEX.md` — task status and validation history.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-04 | `php artisan test --stop-on-failure tests/Feature/ApplicantStageCatalogTest.php tests/Feature/ApplicantStageScopeTest.php tests/Feature/ApplicantStageContextTest.php tests/Feature/ApplicantContractStageTest.php` | In-memory SQLite | PASS — 27 tests, 202 assertions | terminal output | New and existing stage suites. |
| 2026-11-04 | `php artisan test --stop-on-failure` on the workflow, pipeline, service-details, visa-workspace, service-case, audit and commission suites | In-memory SQLite | PASS — 58 tests, 835 assertions | terminal output | Regression for the new model guards and scope rules. |
| 2026-11-04 | `npm run typecheck` | Local Node | PASS — exit 0 | terminal output | Includes the new stage client and page. |
| 2026-11-04 | `npm run i18n:check` | Local Node | PASS — exit 0, 314 source files / 3275 used keys / 4999 English keys | terminal output | No missing keys. |
| 2026-11-04 | `php artisan migrate --force` twice with `DB_CONNECTION=sqlite DB_DATABASE=/tmp/task029.sqlite` | Temporary SQLite file | PASS — first 0, second 0; second run reports `Nothing to migrate` | terminal output | Both new migrations applied once and were a no-op on re-run. MySQL was not available to execute; only portable Blueprint/query-builder calls are used. |
| 2026-11-04 | `php -l` on all changed PHP and catalogue files | Local PHP | PASS — 11 files, no syntax errors | terminal output | |
| 2026-11-04 | `vendor/bin/pint --test` on the changed files | Local PHP | PASS for 11 of 14 files; 3 pre-existing findings | terminal output | `EducationalMasterDataController` `ordered_imports`, `lang/fa/api.php` and `lang/ps/api.php` `array_indentation` fail identically on their `HEAD` copies; no new style issue. |
| 2026-11-04 | `git diff --check` | Git | PASS — exit 0 | terminal output | |
| 2026-11-04 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | Local Python | PASS — 29 tasks, 0 errors | terminal output | Task status updated to `Review`. |

## Decisions and deviations
- **`test_id = null` is the shared baseline.** Existing test-offer stages remain usable by every test; a populated value limits the stage to one test. No per-test duplicate keys or per-test variants were introduced.
- **Academy keeps three terminal outcomes.** The request explicitly excludes `rejected` for academy; `completed`, `cancelled` and `withdrawn` remain.
- **Terminal request rejection also covers `is_terminal: false`.** The contract says clients never write `is_terminal`; rejecting its presence avoids silently accepting a stale editor payload. The acceptance criterion for `true` is covered.
- **Model guards run only on updates.** Initial creation is not blocked because a service detail row may be written after the applicant or line row exists. Migrations use the query builder.
- **Demotion, not deletion.** Non-canonical terminal rows keep their history and foreign keys. The migration logs the demoted count instead of rewriting history.
- **Test deletion includes soft-deleted stages.** The guard matches any `applicant_stages.test_id`, active or deleted, so a stage scope cannot be orphaned.
- **`applicant_stages` table name is singular.** The `line_id` validation uses the real `applicant_contract` table.

## Blockers and open questions
- The 390/768/1280 px LTR/RTL light/dark browser pass has not been run; this is the only unchecked acceptance criterion.
- Native Dari and Pashto review of the new stage copy is still outstanding.
- Two product decisions remain as recorded in `Task.md`: the shared null-test baseline versus exact single-test ownership, and approval for demoting existing custom terminal rows on a copy of working data. Implementation proceeds with the shared baseline and non-destructive demotion.

## Handoff notes
- A picker or workspace should call `GET /api/applicant-stages?line_id=...` (or `?applicant_id=...`) instead of reimplementing service/test/country rules.
- `is_terminal` and `is_locked` remain system-owned and absent from `$fillable` and writable request validation.
- Canonical terminal keys are non-configurable; a future service or outcome needs a new migration and product approval, not an editor switch.
- The test-offer `test_id = null` label is "All tests"; it is a valid shared scope, not an incomplete row.

## Activity log (append-only)
- `2026-11-04` Task record created; forward ownership transferred from `docs/Tasks/Task-001`; validators passed (29 issues/tasks, 1 docs/Tasks, 0 errors).
- `2026-11-04` Implemented the two migrations, stage-context service, model guards, scoped catalogue reads, editor and three-locale copy. New suites passed 27 tests / 202 assertions; regression passed 58 tests / 835 assertions. Typecheck, i18n, migration idempotence, PHP lint and diff check passed. Status → `Review`; viewport and native-copy review remain open.
- `2026-11-07` Forward ownership of the paths needed for Task-046 moved to Task-046; the delivered behaviour remains historical evidence.
