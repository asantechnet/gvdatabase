# Progress — Task-045

## Status snapshot
- Status: `Review`
- Owner: `QA and integration reviewer`
- Last updated: `2026-11-09`
- Current focus: `Academy 036-047 delivered; remaining acceptance evidence`
- Blocker: `No code blocker. Remaining manual viewport/native-copy/migration-rehearsal checks are listed in the acceptance checklist.`
- Next action: `Run the remaining browser, native-copy and migration-rehearsal acceptance checks, then close or reopen the owning tasks.`

## Evidence recorded
- Focused Academy/Task-046/Task-047 suites: 83 tests / 953 assertions passed on an isolated database.
- Academy API route tree: 22 routes registered.
- Print regressions: attendance 7/7, certificate 9/9, slip passed.
- Academy locale keys exist in all three frontend locales; Academy business codes exist in all three PHP catalogs.
- `npm run typecheck` exits 0; `npm run build` exits 0; `npm run build:icons` exits 0 with no generated-file drift.
- `npm run i18n:check` still exits 1 from pre-existing unrelated raw strings and fa/ps settings/support gaps; no Academy/Task-046/Task-047 key is reported.
- Not run: browser viewport/RTL/light-dark pass, native Dari/Pashto review, disposable working-data migration rehearsal.

## Acceptance criteria progress
- [x] Given the full Academy feature, when the focused backend suites run, then they pass on an isolated database and the report lists the suite names, test counts and assertion counts.
- [ ] Given the frontend, when `npm run typecheck`, `npm run i18n:check`, `npm run build:icons` and `npm run build` run, then they exit 0 and no generated-file drift is committed. — Evidence: typecheck, build and icons pass; `npm run i18n:check` still exits 1 on pre-existing unrelated keys.
- [x] Given the print workflows, when the slip, attendance sheet and certificate regressions run, then they pass and the A5/A4 page rules are correct; no global print selector leaks into another route.
- [ ] Given a disposable copy of working data, when the migrations and backfill run, then enrollment counts reconcile with existing academy details, unresolved links are reported, and no existing row is lost.
- [ ] Given the permission matrix (super admin, manager, finance/HR read-only, academy staff with and without manage), when each Academy page/API is exercised, then read/write access matches the declared permissions and refusals are 403/422 without data changes. — Evidence: API permission tests pass; page-level matrix review not run.
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark, when overview, classes, students, attendance, certificates and catalogue are reviewed, then layout, focus, labels and empty/error states pass; findings are recorded with screenshots or exact source evidence.
- [x] Given the full review, then no acceptance criterion from Tasks 036-044 is silently skipped; any failure is recorded as a blocker/reopened task, and the final report states what was not run.
- [x] Given test-offer and academy contracts and multiple responsible employees, when the full pass runs, then those contracts create no stage or pipeline case, the stage catalogue no longer offers those services, the responsible-employee labels/permission work in all three locales, and scholarship/private commission results are unchanged.

## Work completed with evidence
- Implementation delivered on the merged academy branch; focused suites, print regressions and frontend checks are recorded below.

## Files changed and why
- Product and test files listed under Task.md `## Exclusive file ownership` were implemented for this task.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-09 | Focused Academy/Task-046/Task-047 suites | isolated testing DB | PASS | terminal | 83 tests / 953 assertions. |
| 2026-11-09 | `php artisan route:list --path=academy` | working tree | PASS | terminal | 22 Academy routes registered. |
| 2026-11-09 | `npm run build:icons` | working tree | PASS — exit 0 | terminal | No generated-file drift. |
| 2026-11-09 | `npm run i18n:check` | working tree | FAIL — exit 1 | terminal | Pre-existing unrelated raw strings and fa/ps settings/support keys only; no Academy key reported. |
| 2026-11-09 | `npm run typecheck` | working tree | PASS — exit 0 | terminal | |
| 2026-11-09 | `npm run build` | working tree | PASS — exit 0 | terminal | |

## Decisions and deviations
- Implementation follows the Task.md contract; no scope deviation was introduced. Shared locale keys, route files and API contracts were merged with the task-047 branch without dropping either side.

## Blockers and open questions
- No implementation blocker. Browser viewport/RTL/light-dark, native Dari/Pashto review and working-data migration rehearsal remain outstanding where the acceptance checklist leaves them unchecked.

## Handoff notes
- Preserve the merged academy enrollment, case-free stage, plural responsibility, relationship and contract-payload behavior when reviewing future changes.

## Activity log (append-only)
- `2026-11-09` — Task-045 verified on the merged academy branch after the task-047 merge; task status updated to Review.
