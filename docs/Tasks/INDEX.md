# Task Set Index

- **Plan source/version:** User request on 2026-11-02 — "make the Stages section's seeded terminal stages immutable"; repository baseline `main` at 2026-11-02
- **Last updated:** `2026-11-04`
- **Status:** `Validated`
- **Validation command:** `python3 docs/Tasks/scripts/validate_tasks.py docs/Tasks`
- **Last validation result:** PASS on 2026-11-04 — 1 task, 0 errors, 0 warnings

## Tasks

| Task ID | Folder | Outcome | Status | Dependencies | Exclusive owner / file area | Validation status |
|---|---|---|---|---|---|---|
| Task-001 | `Task-001-lock-seeded-terminal-stages/` | Seeded terminal applicant stages cannot be edited or deleted; the stage editor shows and explains the lock | Review | None | None (delivered; forward ownership of the stage catalogue, stage editor, typed stage field and stage test moved to the service-stage-scope task on 2026-11-04) | PASS 2026-11-02 |

## Dependency layers
- **Layer 0:** Task-001 — no upstream task; it only reads existing seeded stages and the existing stage controller.
- **Layer 1:** none.

## Shared contracts and integration owners
| Contract/schema/interface | Exclusive owner task | Consumers | Freeze/compatibility rule |
|---|---|---|---|
| `applicant_stages.is_locked` column and its backfill | Task-001 | `ApplicantStageController`, `resources/ts/pages/applicant-stages.vue` | Additive boolean with default `false`; only the migration/seed writes it; the API never accepts it |
| `GET`/`PUT`/`DELETE /api/applicant-stages` response and error shape | Task-001 | Stage editor, pipeline and case-page stage readers | Existing fields and `200`/`204` paths unchanged; only locked rows gain the `409 business.applicant_stage_locked` answer |

## Cross-cutting coverage
| Concern | Owning task(s) | Acceptance/verification evidence |
|---|---|---|
| Permissions and data scope | Task-001 | Lock is independent of role; `manage_applicant_stages` still gates writes; refusal verified for a manager and a super admin |
| RTL, dark theme and responsiveness | Task-001 | Page checked in `en`, `fa-AF` RTL and dark theme at mobile and desktop widths |
| Migration portability and rollback | Task-001 | `migrate:fresh` plus a second `migrate` in the SQLite test environment; `down()` drops the column |
| Workflow integrity (commission, closure, task sync) | Task-001 (verification only) | Pipeline and `ContractTaskService::syncForStage` behaviour unchanged after the lock |

## Unresolved blockers
| Decision/dependency | Affected tasks | Owner | Next action |
|---|---|---|---|
| None | — | — | No blocking decision known; the immutability scope is recorded as an assumption in Task-001 |

## Validation history
Append date, command, result, issue/error count and follow-up owner after each validation run.
- `2026-11-02` — Task set created; validator not run yet.
- `2026-11-02` — `python3 docs/Tasks/scripts/validate_tasks.py docs/Tasks` → `PASS: 1 task(s); required files/sections, statuses, dependencies, cycles, exclusive ownership and INDEX.md are valid.` (exit 0, 0 errors, 0 warnings). Validator shipped at `docs/Tasks/scripts/validate_tasks.py` (Python 3.14.4). Structural pass only; behaviour still requires implementation and review.
- `2026-11-02` — Dependency/ownership review: single task, no dependencies or cycles, no cross-task file overlap. Business policy is not invented: the immutability scope and marker name are recorded as assumptions in `Task-001`, and the seeded terminal set is derived from the two existing seed migrations.
- `2026-11-04` — Implemented on `feat/remaining-task-closure`: additive `is_locked` migration + backfill, model predicate/mutation guard, controller 409 before validation, typed client field, editor lock chip and suppressed controls, three-locale copy, seven catalogue tests. `ApplicantStageCatalogTest` 7 tests / 59 assertions and `npm run typecheck` pass; status → `Review` pending the viewport/RTL browser pass and the MySQL migrate step.
- `2026-11-04` — Forward ownership of the stage catalogue, stage editor, typed stage field and stage catalogue test moved to the new service-stage-scope task in `issues/tasks` so canonical terminal sets and test scoping have one writer. Task-001 remains `Review`; no product code changed by the handoff.
