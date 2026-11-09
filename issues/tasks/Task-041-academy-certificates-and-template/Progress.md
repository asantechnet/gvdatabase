# Progress — Task-041

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
- [x] Given a completed enrollment, when a certificate is issued, then exactly one `academy_certificates` row is created with a unique number, the enrollment/applicant/class links, issue date/actor, and a snapshot of student, programme, class, start/end/completion dates, template copy and signatory; issuing again is refused or returns the existing certificate without a second number.
- [x] Given an active, withdrawn or cancelled enrollment, when issuance is attempted, then it is refused with a specific business code and no certificate row or number is created.
- [x] Given the certificate template, when the administrator edits title, body copy or signatory title, then only the template row changes and future certificates use it; already-issued certificates reprint from their snapshot and are unchanged.
- [ ] Given the certificate print, when it runs, then it is A4 landscape, uses `printDocument` with body class `printing-academy-certificate`, no `@page` in the SFC, shows logo/company/signature and every snapshot field, and prints cleanly in LTR and RTL.
- [x] Given a revoked certificate, when the list or reprint is requested, then the status is visible, the reason/actor/time are recorded, and the reprint is refused or visibly marked revoked; no valid-looking copy is produced.
- [x] Given bulk issuance for a class, when selected completed enrollments are submitted, then one certificate per enrollment is created, duplicates are skipped, and the response reports created/skipped without a partial number on any row.
- [x] Given a viewer without `issue_academy_certificates`, when they open the page, then they can read issued certificates but cannot issue, revoke or edit the template; a direct write returns 403, and a viewer without `view_academy_contracts` cannot read at all.
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark after Task-044 wires the route, when the list and print preview render, then the page is usable, icon actions are named, and the preview does not overflow.
- [x] Given the focused checks, when `php artisan test --filter=AcademyCertificateTest`, `node scripts/academy-certificate-print-regression.mjs`, `npm run typecheck` and `npm run i18n:check` run, then they pass.

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
- `database/migrations/2026_11_07_000006_create_academy_certificate_tables.php`
- `app/Models/AcademyCertificate.php`
- `app/Models/AcademyCertificateTemplate.php`
- `app/Services/AcademyCertificateService.php`
- `app/Http/Controllers/AcademyCertificateController.php`
- `app/Http/Requests/StoreAcademyCertificateRequest.php`
- `app/Http/Resources/AcademyCertificateResource.php`
- `routes/academy/certificates.php`
- `database/seeders/AcademyCertificateTemplateSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/ts/api/academyCertificates.ts`
- `resources/ts/pages/academy/certificates.vue`
- `resources/ts/components/academy/AcademyCertificateDialog.vue`
- `resources/ts/components/academy/AcademyCertificatePrinter.vue`
- `resources/ts/pages/templates/academy_certificate_template.vue`
- `tests/Feature/AcademyCertificateTest.php`
- `scripts/academy-certificate-print-regression.mjs`

## Decisions and deviations
- Implementation follows the Task.md contract; no scope deviation was introduced. Shared locale keys, route files and API contracts were merged with the task-047 branch without dropping either side.

## Blockers and open questions
- No implementation blocker. Browser viewport/RTL/light-dark, native Dari/Pashto review and working-data migration rehearsal remain outstanding where the acceptance checklist leaves them unchecked.

## Activity log (append-only)
- `2026-11-09` — Task-041 verified on the merged academy branch after the task-047 merge; task status updated to Review.
