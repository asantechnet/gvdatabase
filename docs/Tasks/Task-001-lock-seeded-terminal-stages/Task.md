# Task Task-001 — Seeded terminal stages are immutable

## Metadata
- Task ID: `Task-001`
- Title: `Make seeded terminal applicant stages immutable in the Stages editor`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack implementer agent`
- Estimated size: `M`
- Source plan/version: User request on 2026-11-02 — "make the Stages section's seeded terminal stages immutable"; repository baseline `main` at 2026-11-02
- Requirement / behavior IDs: `BEH-STAGE-IMMUTABLE-1` (seed protection), `BEH-STAGE-IMMUTABLE-2` (API refusal), `PERM-STAGE-MANAGE` (existing `manage_applicant_stages`)

## Objective
The terminal stages that ship with the product stop being editable or removable from Options → Contracts → Contract Stages, so the workflow outcomes that commission, case closure and reporting depend on cannot be renamed, deactivated, re-keyed or deleted; every other stage stays fully editable.

## Context and problem
`applicant_stages` is seeded twice: `database/migrations/0001_06_01_000006_create_reference_tables.php` creates the scholarship pipeline with the terminal rows `completed`, `cancelled`, `rejected`, `withdrawn`, and `database/migrations/2026_10_13_000001_add_service_to_applicant_stages.php` copies it to `private` and seeds `test_offer` (`completed`, `cancelled`, `withdrawn`) and `academy` (`completed`, `cancelled`, `withdrawn`).

Those keys are load-bearing. `ApplicantStage::COMPLETED_KEY` releases commission, `ApplicantStage::EXIT_KEYS` marks a case closed without commission, and `is_terminal` drives case closure, task completion (`ContractTaskService::syncForStage`) and pipeline columns. Today `PUT /api/applicant-stages/{id}` accepts `name`, `sort_order`, `is_active`, `is_terminal`, `allowed_next_keys` and scope on any row, and `DELETE` soft-deletes any row that is not currently in use — so a manager with `manage_applicant_stages` can silently break every future case by deactivating `completed` or deleting `cancelled`.

The stage editor is `resources/ts/pages/applicant-stages.vue`, the only stage editor, backed by `App\Http\Controllers\ApplicantStageController` and the typed `ApplicantStage` contract in `resources/ts/api/applicants.ts`. There is no stage API resource, so the controller's raw model output is the contract.

## Scope
### Included
- A persisted marker that distinguishes platform-seeded terminal stages from user-created stages, backfilled for the rows the two seed migrations created.
- API refusal of any update or delete of a marked row, with the locked state exposed to readers.
- Request bodies that try to set the marker are ignored, so no client can create or unlock a locked stage.
- Stage editor reflects the lock: visible indication, no edit/delete controls, and a dedicated message instead of the generic in-use toast.
- Translation keys for the new strings in `en`, `fa-AF` and `ps-AF`, laid out for RTL.
- Feature coverage for the locked and non-locked paths.

### Excluded
- Changing `is_terminal` semantics, `COMPLETED_KEY`, `EXIT_KEYS`, commission, case closure, task sync or pipeline counting.
- Locking non-terminal seeded stages; `new`, `consultation`, `documents_required` and the rest stay editable.
- A permission or role change; `manage_applicant_stages` keeps its current meaning for non-locked stages.
- Admin override, unlock screen, audit workflow or super-admin bypass.
- New services' stage lists, stage history backfill, or any edit to already-run migrations.
- Restyling the page or touching other master-data catalogues.

## Preconditions and assumptions
- Assumption, to validate during implementation: immutability is total for a locked row — name, sort order, scope, allowed next stages, `is_active` and `is_terminal` are all frozen. No rename-only exception. Recorded as a decision in `Progress.md` if the implementer changes it.
- Assumption: the marker is a boolean column `applicant_stages.is_locked` (default `false`), set only by the backfill migration and future seed writes, never from the API. The predicate is the flag itself, not `is_system AND is_terminal`, so a later seed only has to set the flag.
- The seeded terminal set is fixed at implementation time: scholarship and private `completed`, `cancelled`, `rejected`, `withdrawn`; test_offer and academy `completed`, `cancelled`, `withdrawn`. Derive it from the two seed migrations, not from a hand-typed guess.
- Tests run on the repository's in-memory SQLite `RefreshDatabase` setup, so the new migration must be portable (no MySQL-only SQL).
- `manage_applicant_stages` remains the only permission needed to attempt an edit; the API refuses the locked row for everyone, including super admins.

## Dependencies
- `None` — this task has no upstream task in this set.
- External blockers (if any): none identified; the user request is the approved source.

## Contracts and boundaries
- Inputs/outputs: `GET /api/applicant-stages` (including `all=1`) keeps its envelope `{ success, data }` and adds `is_locked` on each row; `PUT /api/applicant-stages/{id}` returns `409` with the translated `business.applicant_stage_locked` message for a locked row and keeps returning `200` with the fresh row otherwise; `DELETE` returns `409` for a locked row and `204` otherwise.
- Compatibility guarantees: existing response fields, key/service uniqueness rules, `all=1` semantics, ordering by `sort_order`, soft-delete reservation of keys, and the behaviour for unmarked rows are unchanged. No route path, permission name or navigation entry changes.
- Error and failure behavior: a locked-row write is refused before validation, so a locked row always answers `409` rather than `422`; the message is actionable and translated, never a stack trace or a silent no-op.
- Permission/data-scope expectations: reads stay behind `view_applicant_stages`; writes stay behind `manage_applicant_stages`; the lock is not a permission grant and cannot be bypassed by role.
- Integration seams: the model is the single place that answers "is this stage locked" for the controller and the client; the page never re-derives the lock from `key` or `is_terminal`.
- Compatibility with existing rows: the backfill only touches non-deleted seeded terminal rows and must be idempotent (a second `migrate` on the same data is a no-op).
- Files/modules this task must not modify: `app/Http/Controllers/ApplicantController.php`, `app/Http/Controllers/ApplicantContractStageController.php`, `app/Models/ApplicantContract.php`, `app/Support/CommissionService.php`, `app/Support/ContractTaskService.php`, `routes/api.php`, the two existing seed migrations, and `resources/ts/pages/applicant-pipeline.vue`.

## Exclusive file ownership
- None — this task is delivered and in review. Forward ownership of the stage catalogue, stage editor, typed stage field and stage catalogue test moved to the service-stage-scope task in `issues/tasks` on `2026-11-04`. The lock predicate and its 409 refusal remain this task's delivery record, but no file is held open for parallel writes.

## Acceptance criteria
- [ ] Given the seeded terminal stages for scholarship, private, test_offer and academy, when the catalogue is read with `all=1`, then each of those rows reports `is_locked: true` and every user-created or non-terminal stage reports `is_locked: false`.
- [ ] Given a locked stage, when `PUT /api/applicant-stages/{id}` submits any field change, then the API responds `409` with the `business.applicant_stage_locked` message and no column of the row changed, including `is_terminal`, `is_active`, `allowed_next_keys` and `sort_order`.
- [ ] Given a locked stage, when `DELETE /api/applicant-stages/{id}` is called, then the API responds `409` with the `business.applicant_stage_locked` message and the row is not soft-deleted.
- [ ] Given a create or update body that contains `is_locked`, when the request is processed, then the submitted value is ignored, so no client can create or unlock a locked row.
- [ ] Given an unmarked stage, when it is updated or deleted through the API, then the existing behaviour and responses are unchanged.
- [ ] Given the Stages editor at `/applicant-stages`, when a locked stage is listed, then it shows a visible lock indicator and offers no edit or delete control, while an unmarked row keeps both controls.
- [ ] Given a locked stage, when a user with `manage_applicant_stages` reaches the API from the editor or directly, then a single dedicated locked-stage message is shown instead of the generic in-use message.
- [ ] Given the implementation, when `php artisan test --filter=ApplicantStageCatalogTest` and `npm run typecheck` run, then both pass with no new failures.

## Verification plan
- Automated: `php artisan test --filter=ApplicantStageCatalogTest` — covers the backfill visibility, the `409` update, the `409` delete, the ignored `is_locked` input, and the unchanged behaviour of an unmarked stage.
- Automated: `npm run typecheck` — covers the added `is_locked` field and the page changes; never run bare `vue-tsc` or `tsc`.
- Automated: `php artisan migrate:fresh --seed` (or the project's existing migrate test path) to prove the backfill works from an empty database, followed by a second `php artisan migrate` to prove idempotence; note the real working database is MySQL, so check the migration uses portable schema calls only.
- Automated: `npm run build:icons` if a new `tabler-*` icon token is introduced, then confirm the output has no unavailable-token warnings.
- Manual/UI: open `/applicant-stages` per service, confirm each seeded terminal row is marked and cannot be opened for editing or removed, confirm an unmarked row still edits and deletes, and check the page in light theme, dark theme, and RTL (`fa-AF`) at mobile and desktop widths.
- Manual/API: attempt `PUT` and `DELETE` on a locked row with `manage_applicant_stages` and with a super admin, confirming both are refused.
- Data fixtures: use the seeded rows for the positive case and a newly created stage (for example `test_offer` / `waiting_list`) for the unmarked case; no fixture may depend on the local MySQL database.
- Regression: confirm the pipeline still places cards in `completed`/`cancelled` and that `ContractTaskService::syncForStage` still closes tasks, since those read the same rows.
- Required evidence: command output, the tested IDs, and the exact rejection message recorded in `Progress.md`.

## Implementation notes
- Add the marker in a new migration dated after `2026_10_27_000002`; do not edit the migrations that already ran. The backfill must match on `(service, key)` for the seeded terminal identities, restrict itself to `is_terminal = true` and `deleted_at IS NULL`, and be safe to run twice.
- Keep the marker out of `$fillable` and out of the request validation, so `store` and `update` cannot receive it from the body; casting it to boolean keeps the JSON contract stable.
- Express "locked" once on the model (a small `isLocked()`-style helper plus a guard that refuses mutation) and let the controller translate that into `409 business.applicant_stage_locked`. Check the lock before validation and before the existing in-use check, so the answer is deterministic.
- The controller returns raw models, so the field reaches the client without a resource change; still assert it explicitly in the test rather than relying on mass-assignment behaviour.
- The page should read the flag from the API row, not infer it from `is_terminal` or the key. Suppress `open()` and `remove()` for locked rows and keep the existing terminal chip.
- Add the strings under `educational.masterData` next to `terminalStage`/`terminalStageHint`, and reuse the existing `business.*` key style for the API message. Keep the wording RTL-safe (no concatenated left/right layout assumptions).
- Preserve the page's existing guard: view-only users still see the list without write controls.

## Risks and rollback
- Risk: the backfill marks a user-created terminal stage by key collision, which would freeze a stage the office deliberately made. Mitigation: match `(service, key)` against the seeded identities only and review the affected-row count printed by the migration on a copy of real data.
- Risk: a future seed adds a terminal stage but forgets the flag, leaving a load-bearing row editable. Mitigation: note the requirement in the seed-selection helper's docblock and keep the flag default explicit.
- Risk: over-blocking, for example a stage that becomes terminal after creation but should stay editable. Mitigation: only the flag locks, and toggling `is_terminal` on an unmarked row stays allowed.
- Data migration/compatibility: additive nullable-free boolean column with a default, so old clients ignore it and no backfill downtime is required; the column and its backfill will need to be applied to the MySQL working database by the owner.
- Rollback: `down()` drops the column, which restores the previous editable behaviour and loses no stage data; no workflow row is rewritten by this change.
- Stop conditions: stop and ask before changing commission, closure rules, or the seeded stage definitions themselves; stop if the migration cannot be applied without locking the stage table beyond a trivial window.

## Handoff checklist
- [ ] Contract and file ownership respected; no file outside the ownership list changed.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] The decision on total immutability and the marker name is recorded, with its impact.
- [ ] Remaining limitations, the MySQL migration step and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
