# Task Task-045 — Accept the Academy revamp end to end

## Metadata
- Task ID: `Task-045`
- Title: `Accept the Academy revamp end to end`
- Initial status: `Ready`
- Type: `Test`
- Priority: `P1`
- Owner role: `QA and integration reviewer`
- Estimated size: `M`
- Source plan/version: `Academy revamp planning session 2026-11-07; client decisions and the Task-036 through Task-044 contracts. This acceptance task has no product-file ownership and reports findings instead of fixing them.`
- Requirement / behavior IDs: `ACADEMY-ACCEPT-001`, `ACADEMY-ACCEPT-002`, `ACADEMY-ACCEPT-003`

## Objective
Prove the Academy revamp works as one feature: data migration, contract term and capacity, workspace pages, attendance, certificates, documents, localization and navigation. Record pass/fail evidence for every cross-cutting check and reopen the owning task for any defect instead of patching outside ownership.

## Context and problem
The Academy work spans ten tasks and three layers: additive data, contract/domain rules, APIs, pages, print, localization and routing. Unit/feature suites prove their own slices, but the client-facing outcome also depends on the slice boundaries: a contract with a real term, a returning student with several courses, a class roster with seats, a monthly attendance sheet that is not HR, a certificate that reprints from its snapshot, an A5 slip that still prints, and an Academy menu that appears only for the right users.

This task exists so the set is not declared done because the files exist. It owns no product file and does not invent fixes; it runs the checks, records limitations and returns defects to the owning task.

## Scope
### Included
- Run the focused backend suites for Tasks 036-041 and the existing academy/contract/slip regressions.
- Run frontend typecheck, i18n check, icon build, production build and the three print regression scripts.
- Run the migration/backfill on a disposable copy of working data and reconcile counts.
- Exercise the permission matrix for read/manage classes/manage attendance/issue certificates.
- Review the six pages at 390/768/1280 px, LTR/RTL, light/dark with keyboard-only traversal.
- Verify the Contracts → Academy creation path, the existing slip, the new term display, multiple courses and capacity.
- Verify the certificate reprint after a template/student change and the revoked state.
- Record every failure as a blocker/reopened task with the exact command/output; record what was not run.
- Update `issues/tasks/INDEX.md` validation history and the final task statuses after the review.

### Excluded
- Editing product code, schemas, routes, pages, locale files or tests to make a check pass. A failure is returned to the owning task.
- Re-testing unrelated ERP modules beyond the listed regressions.
- Destructive actions on working data: no drop/rollback, no certificate revocation of a real certificate, no bulk attendance on a live class, no payment/income mutation.
- Declaring a visual acceptance from source reading alone when the route is unavailable; the route must be wired by Task-044 first.
- New features, refactors or polish beyond the acceptance criteria.

## Preconditions and assumptions
- Tasks 036-044 are implemented and their `Progress.md` files are at `Review` (or `Done` with evidence).
- A disposable database copy or a seeded test database is available; the reviewer may read working data but must not write to it.
- Demo accounts exist for super admin, a manager with all academy grants, a viewer with read-only academy access and a viewer with no academy access.
- The print scripts are run against the built app or the established regression harness; no printer hardware is required.
- The native Dari/Pashto review may be a recorded limitation if no reviewer is available; the task must say so rather than claim it.
- The acceptance report is evidence, not a new implementation task.

## Dependencies
- `Task-039` — workspace pages and clients.
- `Task-040` — attendance page/API/print.
- `Task-041` — certificate page/API/print.
- `Task-042` — contract/slip term documents.
- `Task-043` — three-locale copy and business codes.
- `Task-044` — routes, nav and redirect; browser checks require this task.
- `Task-046` — case-free test-offer/academy contracts, retired stages and plural responsible employees.

## Contracts and boundaries
- No product-file ownership. The only files this task may write are its own `Progress.md` and the `INDEX.md` validation history/status cells.
- A failed check does not get "fixed" here; the reviewer reopens the owning task with the exact failing command, expected/actual, fixture and screenshot if applicable.
- Data safety: all write checks use seeded/disposable records; no production certificate revocation, no working-data migration rollback, no live payment changes.
- Acceptance evidence: command, date, environment, result, count/assertion, path to screenshot/output. A check not run is recorded as "not run" with the reason.
- Status: the set is acceptable only when all cross-cutting criteria pass or have an explicit, owner-assigned limitation. A known failure cannot be hidden behind a green typecheck.
- Compatibility: the reviewer confirms the existing academy slip/contract and unrelated registers still work; a regression in a shared print/global selector is a blocker.

## Exclusive file ownership
- None (acceptance and verification task; no product files are edited).

## Acceptance criteria
- [ ] Given the full Academy feature, when the focused backend suites run, then they pass on an isolated database and the report lists the suite names, test counts and assertion counts.
- [ ] Given the frontend, when `npm run typecheck`, `npm run i18n:check`, `npm run build:icons` and `npm run build` run, then they exit 0 and no generated-file drift is committed.
- [ ] Given the print workflows, when the slip, attendance sheet and certificate regressions run, then they pass and the A5/A4 page rules are correct; no global print selector leaks into another route.
- [ ] Given a disposable copy of working data, when the migrations and backfill run, then enrollment counts reconcile with existing academy details, unresolved links are reported, and no existing row is lost.
- [ ] Given the permission matrix (super admin, manager, finance/HR read-only, academy staff with and without manage), when each Academy page/API is exercised, then read/write access matches the declared permissions and refusals are 403/422 without data changes.
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark, when overview, classes, students, attendance, certificates and catalogue are reviewed, then layout, focus, labels and empty/error states pass; findings are recorded with screenshots or exact source evidence.
- [ ] Given the full review, then no acceptance criterion from Tasks 036-044 is silently skipped; any failure is recorded as a blocker/reopened task, and the final report states what was not run.

- [ ] Given test-offer and academy contracts and multiple responsible employees, when the full pass runs, then those contracts create no stage or pipeline case, the stage catalogue no longer offers those services, the responsible-employee labels/permission work in all three locales, and scholarship/private commission results are unchanged.

## Verification plan
- Backend: `php artisan test --filter=AcademyEnrollmentFoundationTest`, `--filter=AcademyEnrollmentBackfillTest`, `--filter=AcademyContractTermTest`, `--filter=AcademyEnrollmentCapacityTest`, `--filter=AcademyMultiCourseTest`, `--filter=AcademyClassApiTest`, `--filter=AcademyEnrollmentApiTest`, `--filter=AcademyOverviewApiTest`, `--filter=AcademyPermissionTest`, `--filter=AcademyAttendanceTest`, `--filter=AcademyCertificateTest`, `--filter=AcademySlipTest`, `--filter=AcademyContractDocumentTest`; record each result.
- Frontend: `npm run typecheck`, `npm run i18n:check`, `npm run build:icons`, `npm run build`; capture exit codes and the icon warning output.
- Print: `node scripts/academy-slip-print-regression.mjs`, `node scripts/academy-attendance-print-regression.mjs`, `node scripts/academy-certificate-print-regression.mjs`; inspect the generated page rules and body classes.
- Data: on a disposable copy, run `php artisan migrate` and the backfill; compare `academy_applicant_details`, `academy_enrollments`, `contracts`, `applicant_contract`, `incomes` counts; record unresolved details and duplicate student ids.
- Browser: sign in as each demo role; open the six Academy pages and the Contracts → Academy page; create a class, enroll a second course for an existing student, mark attendance, issue/reprint/revoke a disposable certificate, print the slip; check 390/768/1280 px, LTR/RTL, light/dark and keyboard focus.
- Regression: existing contract generation/print, academy slip, timesheet/HR attendance, payroll and audit-trail suites; confirm no shared global selector or route-order regression.
- Report: commands, environment, results, limitations, reopened task ids and the final status recommendation.

## Implementation notes
Run backend suites with an isolated database per suite group if the shared database causes DDL collisions; record the exact command and any environment workaround. Do not run `php artisan migrate:fresh` against the working database. Use a copy or a seeded test database and state which.

For browser checks, use the demo accounts and disposable records; if a check mutates data, clean it up or leave it in a clearly named test class/certificate. Do not revoke a certificate that was not created for this review.

Treat the i18n check as a gate, not a formality: a missing Academy key is a blocker. Treat a missing icon glyph as a real rendering bug, not a build warning to ignore. Treat a print page-size drift or an unscoped `@media print` selector as a blocker because the AGENTS print rules are global.

## Risks and rollback
- Risk: the acceptance run mutates working data. Mitigation: disposable copy/fixtures only; no working-data rollback.
- Risk: a green suite hides a route/nav failure. Mitigation: browser checks are mandatory after Task-044; source/typecheck alone is not acceptance.
- Risk: the native-language review is unavailable. Mitigation: record it as a limitation and do not claim a pass; the English/Dari/Pashto parity check still runs.
- Risk: a shared print selector regresses another document. Mitigation: run the existing receipt/payslip/contract print regressions, not only the new scripts.
- Rollback: none; this task changes no product state. Reopen tasks for fixes.
- Stop condition: if the disposable data cannot be prepared without touching working data, stop and report the environment blocker rather than proceeding destructively.

## Handoff checklist
- [ ] No product files changed by this task.
- [ ] Every acceptance criterion has a recorded result or an explicit not-run reason.
- [ ] Failures name the owning task and the exact reproduction.
- [ ] Data/print/permission evidence is attached or referenced.
- [ ] Limitations (including native-language review) are explicit.
- [ ] The final status recommendation is honest about what was and was not verified.
