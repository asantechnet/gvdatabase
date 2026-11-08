# Progress — Task-041

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (Laravel and Vue document implementer)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Add the certificate tables/service/page/print and run the certificate suite and print regression.`

## Acceptance criteria progress
- [ ] Given a completed enrollment, when a certificate is issued, then exactly one `academy_certificates` row is created with a unique number, the enrollment/applicant/class links, issue date/actor, and a snapshot of student, programme, class, start/end/completion dates, template copy and signatory; issuing again is refused or returns the existing certificate without a second number. — Evidence: `not yet available`
- [ ] Given an active, withdrawn or cancelled enrollment, when issuance is attempted, then it is refused with a specific business code and no certificate row or number is created. — Evidence: `not yet available`
- [ ] Given the certificate template, when the administrator edits title, body copy or signatory title, then only the template row changes and future certificates use it; already-issued certificates reprint from their snapshot and are unchanged. — Evidence: `not yet available`
- [ ] Given the certificate print, when it runs, then it is A4 landscape, uses `printDocument` with body class `printing-academy-certificate`, no `@page` in the SFC, shows logo/company/signature and every snapshot field, and prints cleanly in LTR and RTL. — Evidence: `not yet available`
- [ ] Given a revoked certificate, when the list or reprint is requested, then the status is visible, the reason/actor/time are recorded, and the reprint is refused or visibly marked revoked; no valid-looking copy is produced. — Evidence: `not yet available`
- [ ] Given bulk issuance for a class, when selected completed enrollments are submitted, then one certificate per enrollment is created, duplicates are skipped, and the response reports created/skipped without a partial number on any row. — Evidence: `not yet available`
- [ ] Given a viewer without `issue_academy_certificates`, when they open the page, then they can read issued certificates but cannot issue, revoke or edit the template; a direct write returns 403, and a viewer without `view_academy_contracts` cannot read at all. — Evidence: `not yet available`
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark after Task-044 wires the route, when the list and print preview render, then the page is usable, icon actions are named, and the preview does not overflow. — Evidence: `not yet available`
- [ ] Given the focused checks, when `php artisan test --filter=AcademyCertificateTest`, `node scripts/academy-certificate-print-regression.mjs`, `npm run typecheck` and `npm run i18n:check` run, then they pass. — Evidence: `not yet available`

## Work completed with evidence
- No implementation work has started. The task contract was created from the 2026-11-07 Academy revamp planning session after repository inspection named the real paths and constraints.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Not run yet | — | — | — | Task not started. |

## Decisions and deviations
- The certificate is a fixed A4 landscape layout plus editable plain-text copy and a snapshot; no WYSIWYG editor or seal.

## Blockers and open questions
- None.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the Academy revamp plan and repository inspection.
