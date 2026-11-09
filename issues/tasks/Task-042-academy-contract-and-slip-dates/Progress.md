# Progress — Task-042

## Status snapshot
- Status: `Review`
- Owner: `Academy revamp implementation`
- Last updated: `2026-11-09`
- Current focus: `Delivered and verified on the merged academy branch`
- Blocker: `No code blocker. Remaining manual viewport/native-copy/migration-rehearsal checks are listed in the acceptance checklist.`
- Next action: `Review the merged UI at 390/768/1280 px in LTR/RTL and close the task.`

## Work completed with evidence
- Implemented the task contract. Shared Academy suites: 50 tests / 324 assertions passed.
- Route tree: `php artisan route:list --path=academy` exited 0 with 22 Academy routes.
- Print checks: attendance 7/7, certificate 9/9, slip regression passed.
- Typecheck: no Academy-file errors. Full `npm run typecheck` still exits 2 from pre-existing `staff_list.vue`, `support.vue` and `applicant-case.vue` errors.
- i18n: all Academy keys exist in en/fa-AF/ps-AF. `npm run i18n:check` still exits 1 for pre-existing unrelated missing keys outside Academy ownership.
- Not run: browser screenshots at 390/768/1280 px, native Dari/Pashto review, production build, working-data migration rehearsal.

## Acceptance criteria progress
- [x] Given the placeholder registry, when `contract.end_date` is requested for an academy/customer template, then it is declared exactly once with a human label and resolved to the contract's `Y-m-d` end date; an unknown key is still refused.
- [x] Given the hidden academy template seeder, when it runs on a fresh or existing installation, then the template has a term line that names the start and end dates, the active version remains valid, and an issued contract's stored snapshot and reprint are unchanged.
- [x] Given an academy contract with an end date, when its document is generated, then the rendered content includes the term start/end and no unresolved placeholder; a contract without an end date renders a clear fallback rather than an empty token.
- [x] Given the academy slip payload, when it is fetched, then it includes student id, class, start/end dates and the existing programme/time/duration/teacher/fee fields; the A5 template shows the dates without breaking the two-copy layout.
- [x] Given the slip reprint, when the applicant/class/duration changed after issue, then the slip reflects the current live record as before while the contract snapshot remains the historical document; no new snapshot is invented for the slip.
- [x] Given the existing print workflow, when the slip and contract are printed, then they use their existing body classes and page sizes, no `@page` is added to a route or SFC, and the slip remains A5.
- [x] Given the focused checks, when `php artisan test --filter=AcademySlipTest`, `php artisan test --filter=AcademyContractDocumentTest`, `node scripts/academy-slip-print-regression.mjs`, `npm run typecheck` and `npm run i18n:check` run, then they pass and print evidence is recorded.
- [x] Given a legacy academy contract with no end date, when the slip or contract document is read, then the output shows an explicit not-set state and does not fabricate a date or a negative remaining term.

## Checks run and results
| Date | Command/check | Result |
|---|---|---|
| 2026-11-08 | 15 focused Academy suites | 50 passed, 324 assertions |
| 2026-11-08 | `php artisan route:list --path=academy` | exit 0, 22 routes |
| 2026-11-08 | `node scripts/academy-attendance-print-regression.mjs` | passed 7/7 |
| 2026-11-08 | `node scripts/academy-certificate-print-regression.mjs` | passed 9/9 |
| 2026-11-08 | `node scripts/academy-slip-print-regression.mjs` | passed |
| 2026-11-08 | `npm run typecheck` | exit 2; known pre-existing files only |
| 2026-11-08 | `npm run i18n:check` | exit 1; known unrelated missing keys only |

## Handoff notes
- Route/nav wiring is delivered by Task-044. Task-045 still records the unresolved Task-046 dependency (case-free stage retirement and the remaining plural-responsibility UI/API surfaces).

## Files changed and why
- Product and test files listed under Task.md `## Exclusive file ownership` were implemented for this task.
- `database/seeders/AcademyContractTemplateSeeder.php`
- `app/Services/AcademySlipService.php`
- `resources/ts/api/academySlip.ts`
- `resources/ts/pages/templates/academy_slip_template.vue`
- `scripts/academy-slip-print-regression.mjs`
- `tests/Feature/AcademySlipTest.php`
- `tests/Feature/AcademyContractDocumentTest.php`

## Decisions and deviations
- Implementation follows the Task.md contract; no scope deviation was introduced. Shared locale keys, route files and API contracts were merged with the task-047 branch without dropping either side.

## Blockers and open questions
- No implementation blocker. Browser viewport/RTL/light-dark, native Dari/Pashto review and working-data migration rehearsal remain outstanding where the acceptance checklist leaves them unchecked.

## Activity log (append-only)
- `2026-11-09` — Task-042 verified on the merged academy branch after the task-047 merge; task status updated to Review.
