# Task-029 — Make service stage scopes and terminal stages system-owned

## Metadata
- Task ID: `Task-029`
- Title: `Make service stage scopes and terminal stages system-owned`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack Laravel/Vue implementer with applicant-stage catalogue review`
- Estimated size: `L`
- Source plan/version: User request on 2026-11-04 for the stages page: four immutable terminal stages, no user-created terminal stages, no country/university scope for test offers or academy, test-offer stages scoped to educational tests, and no rejected terminal for academy. Extends the delivered `is_locked` stage foundation in `docs/Tasks/Task-001-lock-seeded-terminal-stages/`.
- Requirement IDs: `STAGE-TERM-01` canonical terminal set; `STAGE-TERM-02` terminal creation blocked; `STAGE-TERM-03` test-offer rejected; `STAGE-SCOPE-01` academy has no dependency; `STAGE-SCOPE-02` test-offer test dependency; `STAGE-SCOPE-03` country/university cleanup; `STAGE-RUNTIME-01` context guard; `STAGE-DATA-01` test deletion guard; `STAGE-UI-01` conditional stage editor.

## Objective
Make the applicant-stage catalogue enforce the business model instead of exposing scope and terminal status as free-form fields. After this task, each service has a fixed set of immutable terminal stages, ordinary users cannot create or promote terminal stages, test-offer stages can be limited to an educational test, academy stages have no country, university or test dependency, and every case move is checked against the stage scope that belongs to that case.

## Context and problem
- `applicant_stages` stores `service`, optional `country_id` and `university_id`, `is_terminal`, `allowed_next_keys`, `is_active` and the `is_locked` marker introduced by the delivered stage-lock foundation.
- Scholarship and private have `completed`, `cancelled`, `rejected` and `withdrawn` terminal rows. Test offer and academy have only `completed`, `cancelled` and `withdrawn`.
- `is_terminal` is still accepted from the create and update forms and the API. A manager can create a new terminal stage or promote an existing one, which then sits outside the locked terminal set.
- The stages page always offers country and university selects. Test-offer and academy stages do not use either value, but the API accepts and stores them.
- Test-offer stages have no relation to the educational tests catalogue (`tests`), so one test-offer workflow is shared by every test and there is no way to scope a stage to one test.
- The only runtime context check is country/university for unlined scholarship and private moves. The current line move checks the stage's service only; test, country and university are unchecked, and its `allowed_next_stages` payload is the unfiltered live list for that service.
- The delivered terminal-reason guard (`ApplicantStageTransitionGuard`) already handles the written reason for a terminal move and contains no document requirement. This task keeps that rule as-is and does not reintroduce a document check.
- Educational tests can be deleted while an applicant stage would still reference them once the new relation is added.

## Scope

### Included
- Two additive migrations:
  - `test_id` on `applicant_stages`, nullable, constrained to `tests`, indexed for service/test lookup, with `country_id` and `university_id` cleared for test-offer and academy rows.
  - Canonical terminal enforcement: add the missing `test_offer.rejected`, lock all canonical terminal rows, and demote any non-canonical terminal row without deleting it or rewriting history.
- Canonical terminal sets:
  - Scholarship: `completed`, `cancelled`, `rejected`, `withdrawn`.
  - Private: `completed`, `cancelled`, `rejected`, `withdrawn`.
  - Test offer: `completed`, `cancelled`, `rejected`, `withdrawn`.
  - Academy: `completed`, `cancelled`, `withdrawn`; no rejected terminal.
- Remove terminal creation and promotion from the stages editor and the API. `is_terminal` becomes system-owned, just like `is_locked`.
- Add a test selector to the stages editor and a Test column for test-offer rows. A null test means the stage is shared by every test; a populated test limits the stage to that test.
- Remove country and university controls for test-offer and academy stages, force both columns to null for those services, and keep the existing controls unchanged for scholarship and private.
- Add a single `ApplicantStageContext` service that decides whether a stage applies to an applicant or a contract line. Use it to filter stage-options reads and to guard stage writes at the model boundary.
- Accept optional `line_id` and `applicant_id` filters on `GET /api/applicant-stages` so a case can ask for only its own valid stages. The case workspace consumes this contract; it does not need to duplicate the rules.
- Refuse deletion of an educational test that is referenced by an active or soft-deleted applicant stage.
- Update the stage editor, stage API types, backend messages and all three locale catalogues.
- Tests for the canonical terminal set, terminal immutability, service scope, test scope, line/applicant context filtering, model write guards and test deletion.

### Excluded
- No per-test duplicate stage keys or per-test variants of the same key. The `(service, key)` identity remains unique; a single stage can be shared by all tests or limited to one test.
- No deletion or renaming of historical custom terminal stages. They are demoted to non-terminal and reported, so existing history and foreign keys stay intact.
- No changes to `app/Http/Controllers/ApplicantController.php`, `app/Http/Controllers/ApplicantContractStageController.php`, `resources/ts/pages/applicant-case.vue`, `resources/ts/pages/service-case.vue`, `resources/ts/pages/applicant-pipeline.vue` or `resources/ts/api/applicants.ts`. Those files are owned by the service-case isolation task; this task exposes the stage-options contract they consume and enforces writes through models they already use.
- No new routes, permissions, navigation entries, test-result fields, test scheduling, contract generation changes, commission changes, task-sync changes or case-closing changes.
- No removal of country/university scope from scholarship or private stages.
- No redesign of the existing `is_locked` marker or its 409 behavior.
- No automatic reseating of a current stage when an applicant's test changes. The guard validates the target of a move; the current stage is not rewritten silently.

## Preconditions and assumptions
- A stage's terminal status is a system invariant, not catalogue data. Only migrations and system seeds write `is_terminal`; every user-created stage is non-terminal. The API rejects attempts to set it, and the model refuses the write even if a future controller forgets the check.
- The four canonical outcomes are the only terminal stages. A custom terminal row is not a valid outcome; it is demoted to an ordinary stage rather than deleted.
- `test_id = null` is the backward-compatible shared baseline for existing test-offer stages. A test-specific stage is available only to that test. New test-offer stages may be shared or test-specific; the editor shows both choices. If the product owner requires every test-offer stage to belong to exactly one test, stop before implementation and raise a follow-up, because existing shared stages would need a per-test duplication decision.
- Test context for a contract line comes from the line's service and the applicant's detail for that service: `testDetail` for test offer, `scholarshipDetail` for scholarship and `privateDetail` for private. This is deliberately not the applicant's current service, because one applicant can have several service lines.
- Academy has no country, university or test dependency. An academy stage applies to every academy case.
- The canonical terminal rows remain active and locked. Locking is already role-independent and is checked before validation.
- Demo data is not a fixture. Verification must use isolated test data and a copy of the working database, never mutate the owner's working data.
- The service-case isolation task will pass its line id or applicant id to `GET /applicant-stages`; this task defines and tests that filter. No file owned by that task is edited here.

## Dependencies
- None. The delivered stage-lock foundation is already in the codebase and this task extends it. The service-case task consumes the stage-options contract defined here rather than blocking this task.

## Contracts and boundaries

### Stage catalogue API
- `GET /api/applicant-stages` keeps `service`, `country_id`, `university_id`, `all` and the existing `view_applicant_stages` permission.
- New optional `test_id`: for `service=test_offer`, returns rows where `test_id` is null or equals the supplied test. Without a test filter, picker reads return shared rows plus test-specific rows for the requested test only when a test is supplied; editor reads use `all=1` and receive the full service catalogue.
- New optional `line_id` and `applicant_id`: when present, the controller resolves the case's service and detail, then returns only active stages that `ApplicantStageContext` says apply. A `line_id` must belong to an existing `applicant_contracts` row; an `applicant_id` must identify an existing applicant. These filters expose stage rows only, never applicant finance, contact or document data.
- `POST`, `PUT` and `DELETE /api/applicant-stages` keep their current permissions and responses. `test_id` is accepted only for test offer and must reference a live `tests` row. `country_id` and `university_id` are accepted only for scholarship and private.
- Scope values outside the service are normalized to null, not copied across services. `is_terminal` is never accepted from the request body; an attempt to set it returns `422` with `errors.is_terminal` and `business.applicant_stage_terminal_managed`.

### Stage context service
- `App\Services\ApplicantStageContext` is the only place that decides stage applicability.
- `appliesTo(Applicant $applicant, ApplicantStage $stage, ?string $service = null): bool` checks the service and then evaluates the service-specific rule below.
  - academy: always true;
  - test offer: `test_id` is null or equals the applicant test detail's `test_id`;
  - scholarship and private: the existing country/university match against the corresponding detail;
  - unknown service: false.
- `stagesFor(Applicant $applicant, string $service)` returns the active query with the same rules. It must not silently fall back to another service's detail.
- Model-level updating guards in `Applicant` and `ApplicantContract` call the service when an existing record's `current_stage_id` is dirty. A mismatch throws `ValidationException` with `stage_id` and `business.applicant_stage_context_mismatch`, so the move transaction rolls back with no stage, history or task change. Initial creation is not blocked by the guard because a service's detail row may be written after the applicant or line row exists.
- The service-case line read's `allowed_next_stages` array remains owned by the service-case task. It must not be the authoritative movable-option list for a test-offer case unless it is filtered by the same context; the workspace uses `GET /api/applicant-stages?line_id=...` for the scoped options. This task guarantees that read and does not edit the line controller.

### Canonical terminal rows
- `test_offer.rejected` is inserted if missing, with a name, sort order after the existing outcomes, `is_terminal = true`, `is_locked = true`, `is_active = true` and no outgoing transitions. No appeal or retake edge is invented.
- The migration is idempotent and portable to SQLite and MySQL. A soft-deleted canonical row is restored rather than duplicated, because the `(service, key)` identity reserves the key.
- Non-canonical terminal rows are demoted to `is_terminal = false` and `is_locked = false` and counted in the migration output. They are not deleted, renamed or detached from history.
- `is_terminal` is removed from `$fillable`; the model forces new rows to non-terminal and refuses a dirty terminal flag. Controller validation reports the dedicated business message before any other write.

### Frontend
- `resources/ts/pages/applicant-stages.vue` keeps the service selector and the add/edit/delete controls for ordinary stages.
- The editor shows country and university only for scholarship/private, a Test select only for test offer, and no scope control for academy.
- The Test select offers an explicit "All tests" choice for null plus active tests from `MasterDataApi.list('tests')`.
- The table shows Country/University for scholarship/private, Test for test offer, and no dependency column for academy. The terminal chip remains read-only and the edit/delete controls stay hidden for locked rows.
- The terminal switch and its hint are removed. A short system-owned terminal note replaces them.
- `resources/ts/api/applicantStages.ts` extends the shared stage type with `test_id` and the scoped read parameters without changing `resources/ts/api/applicants.ts`.
- Locale files are shared with the existing catalogue task. Add only the new keys; do not restructure the catalogues.

### Files this task must not modify
- `app/Http/Controllers/ApplicantController.php`, `app/Http/Controllers/ApplicantContractStageController.php` — owned by the service-case isolation task; model guards enforce the rule without touching them.
- `resources/ts/pages/applicant-case.vue`, `resources/ts/pages/service-case.vue`, `resources/ts/pages/applicant-pipeline.vue`, `resources/ts/api/applicants.ts` — owned by the service-case isolation task.
- `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json` — shared catalogues; add keys without claiming ownership.
- `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php` — shared catalogues; add keys without claiming ownership.
- `resources/ts/plugins/iconify/icons.css` — generated; never hand-edit.
- Existing migrations — add new dated migrations instead.

## Exclusive file ownership
- `app/Http/Controllers/EducationalMasterDataController.php`
- `database/migrations/2026_11_04_000001_add_test_scope_to_applicant_stages.php`
- `database/migrations/2026_11_04_000002_enforce_canonical_terminal_stages.php`

## Implementation notes
1. Record the 29-task baseline and transfer forward ownership of the stage catalogue, stage editor and stage catalogue test from the delivered stage-lock task in `docs/Tasks` to this task.
2. Add the test-scope migration. Add nullable `test_id` with a foreign key to `tests`, index `(service, test_id)`, and clear `country_id`/`university_id` for test-offer and academy rows. Keep the down path limited to dropping the new column and index; the cleared scope values are not restored.
3. Add the canonical-terminal migration. Restore or insert `test_offer.rejected`, update the canonical rows to terminal, locked and active, and demote non-canonical terminal rows. Use the query builder so the model guards cannot block the migration. Log the number of demoted rows.
4. Update `ApplicantStage`: add the test relation and `test_id`; remove `is_terminal` from `$fillable`; force new rows non-terminal; refuse a dirty terminal flag; keep `is_locked` un-fillable and preserve the existing 409 guard.
5. Update `ApplicantStageController`:
   - validation for `test_id`, with service normalization and the terminal-managed rejection;
   - index filters for `test_id`, `line_id` and `applicant_id`;
   - no cross-service scope leakage;
   - canonical 409 behavior for locked rows remains before validation.
6. Add `ApplicantStageContext` with the service rules and detail resolution. Add updating guards to existing `Applicant` and `ApplicantContract` records that use it when `current_stage_id` changes; do not block initial creation, because service details may be written after the row. Do not add duplicate checks to the controllers.
7. Add the test-in-use guard to `EducationalMasterDataController` before deleting a `tests` record.
8. Update the stages page: conditional scope fields, the Test column and select, no terminal switch, no dependency display for academy, and a system-owned terminal note.
9. Add `resources/ts/api/applicantStages.ts` with the extended stage type and scoped read parameters. Keep the existing `ApplicantApi` surface intact.
10. Add the locale keys and backend business message in English, Dari and Pashto. Remove the dead terminal-switch hint.
11. Add and update the tests, then run the stage catalogue, scope and context suites plus the existing applicant workflow/pipeline/contract-stage suites. Run `npm run typecheck`, `npm run i18n:check`, and a fresh SQLite migration twice.
12. Update `Progress.md` with actual commands, counts and limitations, then hand off the stage-options contract to the service-case task.

Forward ownership of the contract-generation, applicant, stage, commission and contract-register paths needed by Task-046 moved on 2026-11-07; this task's delivered behaviour remains historical evidence.

## Acceptance criteria
- [ ] Given the stage catalogue for scholarship, private, test offer and academy, when it is read with `all=1`, then scholarship, private and test offer contain exactly `completed`, `cancelled`, `rejected` and `withdrawn` as terminal rows and academy contains exactly `completed`, `cancelled` and `withdrawn`, each reports `is_terminal: true`, `is_active: true` and `is_locked: true`, and no other row for that service is terminal.
- [ ] Given the test-offer catalogue, when it is read, then a locked `rejected` terminal stage exists with a display order after the existing outcomes, and the academy catalogue has no `rejected` terminal stage.
- [ ] Given a create or update request that submits `is_terminal: true`, when the API processes it, then the request is rejected with `errors.is_terminal` and the terminal-managed business code, no terminal row is created or promoted, and the model refuses the same write outside the controller.
- [ ] Given the stages editor, when it opens the add or edit dialog, then no terminal switch is offered, a newly created stage is non-terminal, and locked terminal rows show the terminal and lock chips without edit or delete controls.
- [ ] Given an academy stage, when it is created or updated with country, university or test values, then those values are forced to null, the editor shows no country, university or test selector, and the catalogue labels the stage as having no dependency.
- [ ] Given a test-offer stage, when it is created or updated, then country and university are forced to null, `test_id` may reference an active educational test, and the editor offers a Test select with an "All tests" option for a null value.
- [ ] Given `GET /api/applicant-stages?service=test_offer&test_id=A`, when the catalogue loads for a picker, then it returns shared stages with `test_id = null` plus stages assigned to test A and excludes stages assigned only to test B.
- [ ] Given `GET /api/applicant-stages?line_id=...` or `?applicant_id=...`, when it is requested by a viewer with `view_applicant_stages`, then it returns only the active stages that apply to that case's service, test, country and university, and it exposes no applicant financial or personal payload.
- [ ] Given a test-offer case whose test is A, when a move targets a stage assigned only to test B, then the request is rejected with `errors.stage_id` and `business.applicant_stage_context_mismatch`, and no stage, history or task row changes; a shared stage or a test-A stage succeeds.
- [ ] Given an academy case, when it moves to any active academy stage, then the move succeeds without a country, university or test requirement, and no scope guard rejects it.
- [ ] Given a scholarship or private case, when stage scope is validated, then the existing country/university rule is preserved and a supplied `test_id` is forced to null; the stage editor still offers the country and university selectors.
- [ ] Given a test referenced by an applicant stage, when an administrator deletes that test from educational data, then the request is refused with `409 business.master_data_in_use`; an unreferenced test remains deletable.
- [ ] Given the frontend locales and backend catalogues, when `npm run i18n:check` and a key search run, then the new test-scope, no-dependency and terminal-managed strings exist in English, Dari and Pashto, and no removed terminal-switch key is still referenced.
- [ ] Given a fresh database, when migrations run twice, then the first run adds the test scope and canonical terminal rows idempotently, the second run is a no-op, and the migration is portable to SQLite and MySQL.
- [ ] Given 390, 768 and 1280 px viewports in LTR and RTL under light and dark themes, when the stages editor renders for scholarship, test offer and academy, then the conditional scope fields, test column and locked terminal rows remain reachable and legible without horizontal page overflow.

## Verification plan
- `php artisan test --filter=ApplicantStageCatalogTest`
- `php artisan test --filter=ApplicantStageScopeTest`
- `php artisan test --filter=ApplicantStageContextTest`
- `php artisan test --filter=ApplicantWorkflowTest` as a regression check for the existing move endpoints
- `php artisan test --filter=ApplicantPipelineTest`
- `php artisan test --filter=ApplicantContractStageTest`
- `npm run typecheck`
- `npm run i18n:check`
- `php artisan migrate:fresh --seed` on a temporary SQLite database, then `php artisan migrate` again to prove idempotence
- Browser checks at 390, 768 and 1280 px in LTR and RTL, light and dark, for scholarship, test offer and academy. Record the browser result honestly; source inspection alone is not a browser pass.

## Evidence requirements
- Test output with test and assertion counts for each suite.
- Migration output for both database engines and the exact count of demoted terminal rows on the working-data copy.
- The API read for each service showing the terminal rows, scope columns and test relation.
- One rejected terminal-creation request and one rejected cross-test move showing the field-level error and unchanged rows.
- One successful shared-stage move and one successful test-specific-stage move, with the resulting history row.
- `npm run typecheck` and `npm run i18n:check` exit codes.
- Screenshots or a written browser result at the required viewports. Do not claim a screenshot that was not taken.

## Risks and rollback
- **Shared baseline versus exact test ownership.** The nullable `test_id` keeps existing test-offer stages usable by every test. If the owner wants every stage to belong to exactly one test, the migration must duplicate existing rows and the unique stage key model must change. This is an explicit stop condition, not an implementation surprise.
- **Custom terminal demotion.** Demoting user-created terminal rows changes outcomes for any workflow that already used them. The migration must count and report them, and the owner must approve the demotion on a copy of the working database. No history is rewritten.
- **Model updating guards.** Seeders or tests that update an existing applicant or line to a stage that does not apply can now fail. Scan fixtures and set initial or changed stages only through valid context; do not bypass the guard in application code. Initial creation is not blocked, and migrations must use the query builder.
- **Irreversible scope cleanup.** Country and university values on test-offer and academy stages are cleared. The down migration drops `test_id` but cannot restore those values; take a database backup before applying to working data.
- **Test deletion guard.** The guard blocks deleting a test that any stage references, including soft-deleted stages. If the product wants soft-deleted references to stop blocking deletion, that is a separate decision; do not silently ignore them here.
- **Locked canonical rows.** Reopening a deactivated canonical terminal row by setting `is_active = true` may change dashboards that counted only active stages. Verify the working-data impact before applying.
- **Rollback.** Revert application code, drop `test_id` and its index, remove the new terminal row, restore the previous active/locked flags from the backup, and remove the new locale keys. There is no reverse for demoted custom terminals without the backup.

## Handoff checklist
- [ ] The service-case isolation task requests stage options with `line_id` (or `applicant_id` for an unlined applicant) so the new context filter is used; the page does not duplicate the applicability rules.
- [ ] The stage editor's Test column is the only place an administrator chooses a test scope; a null value means shared by all tests, not a missing configuration error.
- [ ] Canonical terminal keys remain non-configurable; a future service or outcome needs a migration and a product decision, not a new editor switch.
- [ ] `is_terminal` and `is_locked` remain system-owned and absent from `$fillable` and writable request validation.