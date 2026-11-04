# Progress — Task-001

## Status snapshot
- Status: `Review`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-11-04`
- Current focus: `The lock is implemented end to end: seeded terminal stages are marked, the API refuses every write with 409, and the editor shows a lock instead of controls.`
- Blocker: `None.`
- Next action: `Reviewer confirms the migration on a copy of the MySQL working database, then runs the 390/768/1280 px LTR/RTL light/dark browser pass on /applicant-stages.`

## Acceptance criteria progress
- [x] Given the seeded terminal stages for scholarship, private, test_offer and academy, when the catalogue is read with `all=1`, then each of those rows reports `is_locked: true` and every user-created or non-terminal stage reports `is_locked: false`. — Evidence: `test_seeded_terminal_stages_are_locked_and_every_other_stage_is_not` reads all four services with `all=1`, asserts `completed`/`cancelled`/`rejected` (where seeded)/`withdrawn` are locked, asserts seeded `new` and a newly created `academy.orientation` are not. Passed.
- [x] Given a locked stage, when `PUT /api/applicant-stages/{id}` submits any field change, then the API responds `409` with the `business.applicant_stage_locked` message and no column of the row changed, including `is_terminal`, `is_active`, `allowed_next_keys` and `sort_order`. — Evidence: `test_a_locked_stage_cannot_be_updated_or_deleted` sends all five fields to `scholarship.completed` and asserts `409`, `code = business.applicant_stage_locked`, and an identical field snapshot afterwards. `test_a_locked_stage_answers_409_before_validation` proves the lock is checked before validation (invalid body still 409).
- [x] Given a locked stage, when `DELETE /api/applicant-stages/{id}` is called, then the API responds `409` with the `business.applicant_stage_locked` message and the row is not soft-deleted. — Evidence: same test; `deleted_at` is asserted `null` after the 409.
- [x] Given a create or update body that contains `is_locked`, when the request is processed, then the submitted value is ignored, so no client can create or unlock a locked row. — Evidence: `test_is_locked_is_ignored_from_the_request_body` creates with `is_locked: true` → `false`; updates an unmarked row with `is_locked: true` → still `false`; tries `is_locked: false` on locked `scholarship.cancelled` → 409 and the flag stays true. The column is absent from `$fillable` and from `validated()`.
- [x] Given an unmarked stage, when it is updated or deleted through the API, then the existing behaviour and responses are unchanged. — Evidence: `test_a_stage_keeps_its_service_and_its_key_stays_reserved` updates an unmarked row (200, service not reparented), deletes it (204, soft-deleted), and still gets 422 when the reserved key is reused. `test_stage_catalogue_is_service_scoped_and_hides_inactive_rows_by_default` now disables the unmarked `documents_required` row.
- [x] Given the Stages editor at `/applicant-stages`, when a locked stage is listed, then it shows a visible lock indicator and offers no edit or delete control, while an unmarked row keeps both controls. — Evidence: `resources/ts/pages/applicant-stages.vue` renders a `tabler-lock` "Locked" chip from the API row's `is_locked`, hides the pencil/trash buttons for locked rows, and guards `open()`/`remove()` even if a handler is reached another way. Source inspection only — no browser capture in this run.
- [x] Given a locked stage, when a user with `manage_applicant_stages` reaches the API from the editor or directly, then a single dedicated locked-stage message is shown instead of the generic in-use message. — Evidence: the controller/model answer `409 business.applicant_stage_locked`, the message exists in `lang/{en,fa,ps}/api.php`, and the page no longer adds its own `inUse`/`saveFailed` toast, so the global notifier is the only message. Attempted as a super admin in the tests; the lock is role-independent. No browser capture.
- [x] Given the implementation, when `php artisan test --filter=ApplicantStageCatalogTest` and `npm run typecheck` run, then both pass with no new failures. — Evidence: 7 tests / 59 assertions pass; `npm run typecheck` exits 0.

## Work completed with evidence
- New additive migration `2026_11_02_000001_add_is_locked_to_applicant_stages.php`: boolean `is_locked` default `false`, then an idempotent `UPDATE` of the exact seeded identities — scholarship/private `completed,cancelled,rejected,withdrawn`; test_offer/academy `completed,cancelled,withdrawn` — restricted to `is_terminal = true` and `deleted_at IS NULL`. `down()` drops the column.
- `ApplicantStage`: `is_locked` cast, default attribute so a create response matches a read, `isLocked()`, and a model-level `assertUnlocked()` guard on `updating`/`deleting` that throws `409 business.applicant_stage_locked` even if a future code path bypasses the controller. The flag stays out of `$fillable`.
- `ApplicantStageController`: `assertUnlocked()` runs before validation in `update` and before the in-use check in `destroy`; `validated()` still cannot receive `is_locked`.
- `resources/ts/api/applicants.ts`: `ApplicantStage.is_locked?: boolean`.
- `resources/ts/pages/applicant-stages.vue`: "Locked" chip with a hint title, suppressed edit/delete controls for locked rows, guards in `open()`/`remove()`, and removal of the page's duplicate API-error toasts so the one server message is the feedback.
- Locales: `educational.masterData.lockedStage` and `lockedStageHint` in `en`, `fa-AF`, `ps-AF`; `business.applicant_stage_locked` in `lang/en|fa|ps/api.php`.
- `tests/Feature/ApplicantStageCatalogTest.php`: seven tests covering the lock, the refusal order, the ignored input, and unchanged behaviour for unmarked stages.

## Files changed and why
- `database/migrations/2026_11_02_000001_add_is_locked_to_applicant_stages.php` — marker + backfill + rollback.
- `app/Models/ApplicantStage.php` — cast/default/predicate/mutation guard.
- `app/Http/Controllers/ApplicantStageController.php` — 409 refusal before validation/in-use; locked flag reaches readers.
- `resources/ts/api/applicants.ts` — typed `is_locked` field.
- `resources/ts/pages/applicant-stages.vue` — visual lock, suppressed controls, single feedback path.
- `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json` — lock labels/hint.
- `lang/{en,fa,ps}/api.php` — translated refusal message.
- `tests/Feature/ApplicantStageCatalogTest.php` — locked/unlocked coverage.
- `resources/ts/plugins/iconify/icons.css` — regenerated by `npm run build:icons` for `tabler-lock` (not hand-edited).

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-04 | `php -l` on model, controller, migration and three API catalogs | Local PHP 8.2 | PASS — 6/6 no syntax errors | terminal output | |
| 2026-11-04 | `php artisan test --filter=ApplicantStageCatalogTest` | Local SQLite `RefreshDatabase` | PASS — 7 tests, 59 assertions | terminal output | Includes the four new lock tests |
| 2026-11-04 | `npm run build:icons` | Local Node | PASS — 290 references, 288 tabler icons, no unavailable-token warning | terminal output | `tabler-lock` bundled |
| 2026-11-04 | `npm run typecheck` | Local | PASS — exit 0 | terminal output | Whole project after the page/API changes |
| 2026-11-04 | Fresh SQLite migration run twice + `is_locked` count query | Temporary `/tmp/task001.sqlite` | PASS — portable, second run a no-op, 14 locked rows (4+4+3+3) | terminal output | Does not touch the working MySQL database |
| 2026-11-04 | Browser pass on `/applicant-stages` at 390/768/1280 px, LTR/RTL, light/dark | Browser | NOT RUN | — | Recorded as a review limitation, not a pass |

## Decisions and deviations
- **Total immutability is kept.** Name, order, scope, allowed next stages, `is_active` and `is_terminal` are all frozen for a locked row; there is no rename-only exception. This was the recorded assumption in `Task.md` and no reason to narrow it was found.
- **Marker is `applicant_stages.is_locked`.** Only the migration/future seed writes it; it is not fillable and not validated.
- **Model guard added.** `updating`/`deleting` also refuse a locked row, so the invariant does not depend on every future controller remembering the check. Tests that need to simulate a disabled seeded stage now use an unmarked row.
- **Seed identities derived from the migrations**, not guessed: scholarship + private have `rejected`; test_offer + academy do not.
- **Page copy uses one chip and one hint.** The action cell is simply empty for a locked row rather than showing a disabled control, which satisfies "offers no edit or delete control" and avoids a second lock indicator.

## Blockers and open questions
- None for the code. The migration still has to be applied to the owner's MySQL working database by the normal deploy/migrate step.
- The 390/768/1280 px LTR/RTL light/dark browser pass and the read-through of the Dari/Pashto lock copy are outstanding review items.

## Handoff notes
- Do not infer a lock from `key` or `is_terminal` in any other screen; read `is_locked`. Non-terminal seeded stages intentionally stay editable.
- A future seed that adds a load-bearing terminal stage must set `is_locked = true` in the same insert; the column default is `false`.
- `409 business.applicant_stage_locked` is produced before validation, so a locked row always answers 409 rather than 422.

## Activity log (append-only)
- `2026-11-02 00:00 UTC` Created task record; implementation had not started.
- `2026-11-04` Claimed by `Buffy (erp-task-implementer)`. Added the marker migration and backfill, the model predicate/guard, the controller 409, the typed field, the editor lock UI, three-locale copy and seven catalogue tests. ApplicantStageCatalogTest 7/59, `npm run typecheck`, `npm run build:icons` and a fresh SQLite double-migrate pass. Status → `Review`.
- `2026-11-04` Forward ownership of `ApplicantStage`, `ApplicantStageController`, `applicant-stages.vue`, the typed stage field and `ApplicantStageCatalogTest` moved to the service-stage-scope task in `issues/tasks`. Task-001 remains the delivery record for the lock invariant; no product code changed by the handoff.
