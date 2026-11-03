# Progress — Task-001

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned`
- Last updated: `2026-11-02`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Read the two seed migrations and list the exact seeded terminal (service, key) pairs before writing the backfill migration.`

## Acceptance criteria progress
- [ ] Given the seeded terminal stages for scholarship, private, test_offer and academy, when the catalogue is read with `all=1`, then each of those rows reports `is_locked: true` and every user-created or non-terminal stage reports `is_locked: false`. — Evidence: `not yet available`
- [ ] Given a locked stage, when `PUT /api/applicant-stages/{id}` submits any field change, then the API responds `409` with the `business.applicant_stage_locked` message and no column of the row changed, including `is_terminal`, `is_active`, `allowed_next_keys` and `sort_order`. — Evidence: `not yet available`
- [ ] Given a locked stage, when `DELETE /api/applicant-stages/{id}` is called, then the API responds `409` with the `business.applicant_stage_locked` message and the row is not soft-deleted. — Evidence: `not yet available`
- [ ] Given a create or update body that contains `is_locked`, when the request is processed, then the submitted value is ignored, so no client can create or unlock a locked row. — Evidence: `not yet available`
- [ ] Given an unmarked stage, when it is updated or deleted through the API, then the existing behaviour and responses are unchanged. — Evidence: `not yet available`
- [ ] Given the Stages editor at `/applicant-stages`, when a locked stage is listed, then it shows a visible lock indicator and offers no edit or delete control, while an unmarked row keeps both controls. — Evidence: `not yet available`
- [ ] Given a locked stage, when a user with `manage_applicant_stages` reaches the API from the editor or directly, then a single dedicated locked-stage message is shown instead of the generic in-use message. — Evidence: `not yet available`
- [ ] Given the implementation, when `php artisan test --filter=ApplicantStageCatalogTest` and `npm run typecheck` run, then both pass with no new failures. — Evidence: `not yet available`

## Work completed with evidence
- None yet. Planning only: the seeded terminal identities and the stage-editor seam were located by reading `database/migrations/0001_06_01_000006_create_reference_tables.php`, `database/migrations/2026_10_13_000001_add_service_to_applicant_stages.php`, `app/Http/Controllers/ApplicantStageController.php`, `app/Models/ApplicantStage.php`, `resources/ts/pages/applicant-stages.vue`, `resources/ts/api/applicants.ts` and `tests/Feature/ApplicantStageCatalogTest.php`.

## Files changed and why
- None yet. The task record itself is `docs/Tasks/Task-001-lock-seeded-terminal-stages/` and is not a product change.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-02 | Task-set validator | local Python 3.14 | Pending | `docs/Tasks/INDEX.md` | Structure only; does not prove behaviour |

## Decisions and deviations
- Recorded in `Task.md` as an assumption: immutability covers the whole locked row (name, order, scope, next stages, active, terminal), and the marker is `applicant_stages.is_locked` set only by the migration/seed. If implementation finds a reason to narrow this, record the decision, reason and user approval here before proceeding.

## Blockers and open questions
- None. One question to settle while implementing, not blocking: whether the office ever needs to rename a seeded terminal stage for display; the task currently freezes the name too, and changing that later is a one-line guard adjustment.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-02 00:00 UTC` Created task record; implementation has not started.
