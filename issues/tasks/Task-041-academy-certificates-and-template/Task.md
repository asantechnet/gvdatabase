# Task Task-041 — Issue academy certificates from a polished template

## Metadata
- Task ID: `Task-041`
- Title: `Issue academy certificates from a polished template`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Laravel and Vue document implementer`
- Estimated size: `L`
- Source plan/version: `Academy revamp planning session 2026-11-07; client decision: the academy needs a way to create certificates, and the certificate must be a nice, polished document template like the other documents. Repository inspection of the document print pipeline, ContractTemplate, HasDocumentNumber, the academy slip and Settings on 2026-11-07.`
- Requirement / behavior IDs: `ACADEMY-CERTIFICATE-001`, `ACADEMY-CERTIFICATE-002`, `ACADEMY-CERTIFICATE-003`, `ACADEMY-CERTIFICATE-004`

## Objective
Give the Academy section a certificate register where an administrator issues one polished A4 landscape certificate per completed enrollment, optionally in bulk for a class, with an immutable number and snapshot, a readable reprint, and an explicit revocation state. The certificate wording is editable as a small template; the layout is fixed and print-safe.

## Context and problem
Academy has an approved A5 slip but no certificate. Documents in the repository are either contract templates (HTML with placeholders and versions) or fixed Vue print templates (receipts, payslips, timesheets, academy slip). A certificate is not a contract: it has no negotiation, policies or versions, and a broken editable layout would produce an invalid award. The client wants a polished certificate template, not a generic HTML editor. This task therefore uses a fixed A4 landscape Vue layout plus a small editable copy/body row and a per-issuance snapshot, reusing the firm's logo, company identity, signature and print workflow.

The certificate is strategically useful to an educational visa consultancy: proof of English study/completion can support a scholarship, test-offer or visa file. It must be accurate and reprintable from the snapshot, never from changed live data.

## Scope
### Included
- `academy_certificates` table: unique number via `HasDocumentNumber`, enrollment/applicant/class/contract/line links, issued_at/issued_by, template id, snapshot JSON, status `issued`/`revoked`, revoked_at/by/reason, timestamps and soft deletes.
- `academy_certificate_templates` table: name, title, body copy with tokens, signatory title, is_active, timestamps; one default template seeded.
- Service that validates a completed enrollment, assigns the number, builds the snapshot and issues; one certificate per enrollment; bulk issue skips duplicates.
- Controller/resource/request and a route file under the Task-038 academy tree.
- Certificates page: list/filter/read, issue dialog from a completed enrollment, bulk issue for a class, reprint, revoke and template copy dialog.
- A4 landscape certificate template and printer dialog using `printDocument` with body class `printing-academy-certificate`, no `@page` in the SFC, RTL mirroring and signature/logo from Settings.
- Feature tests for eligibility, numbering, snapshot immutability, revocation, permissions and bulk behavior.
- A print regression script for the certificate.

### Excluded
- A WYSIWYG or versioned HTML certificate editor; only the title/body/signatory copy is editable, and the layout is fixed.
- Public certificate verification, QR codes, blockchain, a portal share link or email delivery.
- Attendance thresholds or exam grades as eligibility gates; eligibility is a completed enrollment, with a product decision required before adding more.
- Contract-template changes, slip changes or the academy contract print. Task-042 owns those.
- Student portal, LMS content, teacher signatures, seals or company-letterhead changes.
- Routes/nav and locale file edits. Task-044 and Task-043 own those.

## Preconditions and assumptions
- Task-036 created `academy_enrollments`; Task-037/038 expose completed enrollments with term dates.
- The enrollment status `completed` is the only default eligibility state; the certificate does not change the enrollment or contract status.
- `HasDocumentNumber` is available and already used by receipts/payslips; the certificate implements its column/prefix/format consistently.
- Settings already provide `company_signature`, `website_logo`, company name/address/phone/email and licence; no new seal image is required.
- A certificate is not a contract template and does not appear in Templates & Policies. Its small template row belongs to the Academy section.
- The print host is hidden on screen and shown only under `printing-academy-certificate`; the layout is A4 landscape.
- The default certificate language is English for visa use; UI labels are localized by Task-043, and the template can be edited to bilingual copy without a schema change.

## Dependencies
- `Task-036` — enrollment table and relations.
- `Task-038` — academy route tree, permissions and API conventions.
- `Task-040` — route file/table conventions and print/regression precedent; the attendance task must land before this task because both extend the academy route tree and print suite.

## Contracts and boundaries
- Create: `POST /academy/certificates` accepts `academy_enrollment_id` or a `class_id` plus `enrollment_ids` for bulk, and an optional `template_id`. It refuses an enrollment not `completed`, a missing/other applicant, a duplicate certificate, or an enrollment outside the caller's scope.
- Number: one immutable number per certificate, prefix `CERT`, year format `Y`, six-digit sequence, generated through `HasDocumentNumber`; a failed transaction must not leave a number attached to no certificate. If the trait assigns after create, wrap the create and number in a transaction and assert the number exists on the returned row.
- Snapshot: student name/father name/student id, programme, class, teachers, start/end/completion dates, certificate number, issue date, signatory name/title, template title/body and a template version marker. Reprints read only the snapshot plus the current company logo/signature; they never read the live enrollment for the award data.
- Template: body tokens are a fixed allowlist (`{student_name}`, `{father_name}`, `{student_id}`, `{program}`, `{class}`, `{teachers}`, `{start_date}`, `{end_date}`, `{completion_date}`, `{certificate_number}`, `{issue_date}`); an unknown token fails validation at template save, not at print. The body is plain text with line breaks, not HTML.
- Revocation: only an `issued` certificate can be revoked; a reason is required; the row keeps its number and snapshot; reprint is refused or clearly watermarked `REVOKED`. No deletion of issued certificates.
- Permissions: read with `view_academy_contracts`; issue/revoke/template writes with `issue_academy_certificates`; a viewer without the write grant sees read-only controls and receives 403 on direct writes.
- Print: `printDocument` with `bodyClass: 'printing-academy-certificate'`, `pageStyle: 'size: A4 landscape; margin: 0;'` (or a verified 6 mm safe margin), every global print selector namespaced, no `@page` in the SFC, and a hidden print host cloned from the visible preview.
- Compatibility: no contract, slip, enrollment status, payment or attendance write; no change to the contract template list or `service-contracts.vue`.
- Files this task must not modify: `resources/ts/plugins/1.router/routes.ts`, `resources/ts/navigation/**`, `resources/ts/plugins/i18n/locales/**`, `lang/**`, `resources/ts/pages/service-contracts.vue`, `resources/ts/pages/templates/academy_slip_template.vue`, `app/Services/AcademySlipService.php`, `database/seeders/AcademyContractTemplateSeeder.php`, `app/Services/Contracts/PlaceholderRegistry.php`.

## Exclusive file ownership
- `database/migrations/2026_11_07_000006_create_academy_certificate_tables.php` — certificate and certificate-template tables.
- `app/Models/AcademyCertificate.php` — number trait, casts, relations and status helpers.
- `app/Models/AcademyCertificateTemplate.php` — template copy fields and token allowlist helpers.
- `app/Services/AcademyCertificateService.php` — eligibility, numbering, snapshot, bulk issue and revocation.
- `app/Http/Controllers/AcademyCertificateController.php` — list/show/issue/bulk/revoke/template read/update.
- `app/Http/Requests/StoreAcademyCertificateRequest.php` — issue and bulk validation.
- `app/Http/Resources/AcademyCertificateResource.php` — certificate and template payloads.
- `routes/academy/certificates.php` — certificate endpoints inside the Task-038 route tree.
- `database/seeders/AcademyCertificateTemplateSeeder.php` — default polished template copy.
- `database/seeders/DatabaseSeeder.php` — register the certificate-template seeder.
- `resources/ts/api/academyCertificates.ts` — typed list/issue/bulk/revoke/template methods.
- `resources/ts/pages/academy/certificates.vue` — register, issue/bulk dialogs, preview and print actions.
- `resources/ts/components/academy/AcademyCertificateDialog.vue` — issue/revoke/template-copy dialog.
- `resources/ts/components/academy/AcademyCertificatePrinter.vue` — preview and print dialog.
- `resources/ts/pages/templates/academy_certificate_template.vue` — fixed A4 landscape certificate layout.
- `tests/Feature/AcademyCertificateTest.php` — eligibility, numbering, snapshot, revocation, bulk and permissions.
- `scripts/academy-certificate-print-regression.mjs` — certificate print layout regression.

## Acceptance criteria
- [ ] Given a completed enrollment, when a certificate is issued, then exactly one `academy_certificates` row is created with a unique number, the enrollment/applicant/class links, issue date/actor, and a snapshot of student, programme, class, start/end/completion dates, template copy and signatory; issuing again is refused or returns the existing certificate without a second number.
- [ ] Given an active, withdrawn or cancelled enrollment, when issuance is attempted, then it is refused with a specific business code and no certificate row or number is created.
- [ ] Given the certificate template, when the administrator edits title, body copy or signatory title, then only the template row changes and future certificates use it; already-issued certificates reprint from their snapshot and are unchanged.
- [ ] Given the certificate print, when it runs, then it is A4 landscape, uses `printDocument` with body class `printing-academy-certificate`, no `@page` in the SFC, shows logo/company/signature and every snapshot field, and prints cleanly in LTR and RTL.
- [ ] Given a revoked certificate, when the list or reprint is requested, then the status is visible, the reason/actor/time are recorded, and the reprint is refused or visibly marked revoked; no valid-looking copy is produced.
- [ ] Given bulk issuance for a class, when selected completed enrollments are submitted, then one certificate per enrollment is created, duplicates are skipped, and the response reports created/skipped without a partial number on any row.
- [ ] Given a viewer without `issue_academy_certificates`, when they open the page, then they can read issued certificates but cannot issue, revoke or edit the template; a direct write returns 403, and a viewer without `view_academy_contracts` cannot read at all.
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark after Task-044 wires the route, when the list and print preview render, then the page is usable, icon actions are named, and the preview does not overflow.
- [ ] Given the focused checks, when `php artisan test --filter=AcademyCertificateTest`, `node scripts/academy-certificate-print-regression.mjs`, `npm run typecheck` and `npm run i18n:check` run, then they pass.

## Verification plan
- Automated: run the certificate feature suite on an isolated database; assert one certificate per completed enrollment, duplicate refusal, invalid status refusal, number uniqueness, snapshot immutability after template/student changes, revocation and bulk skip counts.
- Number check: issue concurrently in a transaction test (or sequential retry) and assert the number column is unique and never null on a committed row.
- Template check: save a body with an unknown token and assert 422; save a valid body and assert old certificates keep their snapshot while a new one uses the new copy.
- Print check: `node scripts/academy-certificate-print-regression.mjs`; assert body class, A4 landscape, no `@page` in the SFC, signature/logo slots, long-name wrapping and RTL mirroring.
- Manual after Task-044: issue one certificate, edit the template, reprint the old one, revoke it and attempt a reprint; bulk issue a class with one already-certified student; check 390/768/1280 px in LTR/RTL and light/dark.
- Regression: academy slip print, contract generation and the existing document print regressions; confirm no contract/print style leak.
- Evidence: test output, certificate preview/print screenshots, duplicate/revoke responses and the print script output.

## Implementation notes
Use a fixed `academy_certificate_template.vue` with CSS variables for the firm's primary colour and a serif display face already available in the app; do not add a font dependency. Render a decorative border, the company logo/name, a large student name, the programme/class, the term dates, a short completion statement, the signature line and the certificate number. Keep the layout A4 landscape with a safe inner margin; long names wrap rather than shrink below legibility.

Store the template body as plain text with `\n` line breaks and resolve the token allowlist in the service before saving the snapshot. Do not store raw HTML; do not render user HTML with `v-html`. The template editor is a small dialog with title, body textarea and signatory title, not a TipTap editor.

For numbering, implement `HasDocumentNumber` on `AcademyCertificate` with `documentNumberColumn(): string { return 'certificate_number'; }` and prefix `CERT`; keep the default six-digit format unless the product owner asks otherwise. If `HasDocumentNumber` assigns on `created`, ensure the certificate is created inside the transaction and the returned model is refreshed before the response.

Bulk issue: validate every id before writing, skip existing certificates, and write the rest in one transaction. Do not create a number for a skipped row. Revocation keeps the row and number; the print preview must show a clear revoked state instead of pretending the award is valid.

## Risks and rollback
- Risk: a certificate is issued for the wrong student or course. Mitigation: show the student, programme, class and term in the confirmation; require an explicit issue action; snapshot the exact enrollment.
- Risk: the template editor breaks the layout. Mitigation: fixed Vue layout and plain-text copy only; unknown tokens rejected at save.
- Risk: a revoked certificate still prints as valid. Mitigation: the print path checks status and either refuses or adds a visible revoked watermark; test both.
- Risk: numbering gaps on a failed transaction. Mitigation: create and number inside one transaction; a rolled-back row leaves no committed number; missing numbers in the sequence are acceptable if the row is absent.
- Rollback: remove the route file, page, printer and controllers; drop the additive certificate tables only on a disposable database. Never delete issued certificates from working data without an explicit retention decision.
- Stop condition: if the product owner wants a seal, a bilingual fixed layout, exam grades or a public verification page, stop and create a separate decision task rather than inventing the policy.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Frozen locale key names and business codes are listed for Task-043.
- [ ] Route and nav wiring is explicitly deferred to Task-044.
- [ ] Remaining limitations and follow-up tasks are explicit.
