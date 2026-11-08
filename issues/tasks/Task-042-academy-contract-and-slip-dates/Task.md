# Task Task-042 — Put academy term dates on the contract document and slip

## Metadata
- Task ID: `Task-042`
- Title: `Put academy term dates on the contract document and slip`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Laravel and Vue document-print implementer`
- Estimated size: `M`
- Source plan/version: `Academy revamp planning session 2026-11-07; client decision: academy contracts need an end date and the current academy slip/contract are approved and useful. Repository inspection of PlaceholderRegistry, PlaceholderResolver, AcademyContractTemplateSeeder, AcademySlipService and the slip template on 2026-11-07.`
- Requirement / behavior IDs: `ACADEMY-DOCUMENT-001`, `ACADEMY-DOCUMENT-002`, `ACADEMY-DOCUMENT-003`

## Objective
Make the academy term visible on the documents the client already trusts: the generated academy contract states the start and end dates, and the A5 academy slip shows the student id, class and term dates alongside the existing programme, time, duration, teacher and payment details. The issued contract snapshot remains the historical truth.

## Context and problem
`ContractGenerationService` now writes `contracts.end_date`, but the hidden academy contract template is a static paragraph with no placeholders. `PlaceholderRegistry` exposes `contract.start_date` but not `contract.end_date`, so even a template with the token could not resolve it. The academy slip API returns `reference`, `date`, programme, time, duration, class, teachers and money, but no student id or term dates.

The academy template is hidden from Templates & Policies and seeded with one active version per language. The slip is an A5 two-copy document with letterhead support and a client-approved layout. This task changes the content/data, not the paper sizes or branding.

## Scope
### Included
- Add `contract.end_date` to `PlaceholderRegistry` with a human label and the customer/academy context; resolve it in `PlaceholderResolver` from the contract, with an empty/not-set fallback rather than an unresolved token.
- Update the hidden academy template seeder to add a term line that uses the start/end placeholders and names the programme/class where available; keep the template hidden and the existing version active.
- Add `student_id`, `started_on` and `ends_on` to the academy slip payload and show them in the A5 template without breaking the two-copy layout.
- Handle a legacy contract with no end date explicitly: the document shows `Not set` or omits the line cleanly, never a blank token or a fabricated date.
- Update the slip API type and the print regression script.
- Add a contract-document test for the new placeholder/term line and extend the slip test.
- Keep all existing slip/signature/letterhead fields and the A5 print workflow unchanged.

### Excluded
- Changing the contract template system, the template version editor, Templates & Policies visibility or the document design system.
- Changing the academy slip paper sizes, two-copy behavior, letterhead resolution, signature placement or company branding.
- Changing `service-contracts.vue` or adding an end-date table column. The Academy Students page from Task-039 is the operational register; the contract detail already exposes the column.
- Changing contract creation, capacity or enrollment logic. Task-037 owns those.
- Certificates or attendance prints. Task-041 and Task-040 own those.
- Locale file edits. Task-043 owns the copy.

## Preconditions and assumptions
- Task-037 sets `contracts.end_date` and creates the enrollment for new academy contracts.
- `PlaceholderRegistry::groups()` is the single declaration point and `PlaceholderResolver` refuses keys not in that registry.
- The hidden academy template seeder is idempotent and is registered in `DatabaseSeeder`; changing its content must not rewrite an issued contract's stored snapshot.
- The slip reads the live applicant/class/line data by design; only the contract document is snapshot-based.
- The A5 slip template uses `printAcademySlip`/`printDocument` and has no `@page` in the SFC.
- Legacy academy contracts may have a null end date; the UI/document must say so honestly.
- The client's native-language copy for the term line is supplied by Task-043; this task uses the frozen keys/labels.

## Dependencies
- `Task-037` — `contracts.end_date` and enrollment term fields.
- `Task-039` — the Academy workspace and student term display this task's documents mirror; no file overlap.

## Contracts and boundaries
- Placeholder: `contract.end_date` is declared once, labelled `End Date`, and available in the same contexts as `contract.start_date` (`customer`/all contract types via the `contract` group). It resolves to `Y-m-d`; a null end date resolves to an empty string that the template can guard, not an unresolved-token error.
- Template: the hidden academy template body adds a short term paragraph, for example “This enrolment runs from {contract.start_date} to {contract.end_date}.” It must resolve with no unresolved placeholders on a new academy contract. An issued contract's `content`/`snapshot_data` is not rewritten by the seeder.
- Slip payload: add `student_id`, `started_on`, `ends_on` under the academy block and a `term` block if the template prefers; keep `program`, `time`, `duration`, `class`, `teachers` and all money fields. `started_on`/`ends_on` are `Y-m-d` strings or null.
- Slip document: the student id and term dates join the existing A5 layout in a way that does not shrink the payment table below legibility; the two-copy mode remains A4 2×A5 and A5 2×.
- Legacy null end date: the slip/contract print shows a localized `Not set`/`—` value; no date is guessed from the class or duration at print time.
- Print: contract and slip keep their existing page sizes/body classes; no `@page` is added to a route/SFC; no global print selector outside the feature namespace.
- Files this task must not modify: `resources/ts/pages/service-contracts.vue`, `app/Http/Resources/ContractResource.php`, `app/Services/Contracts/ContractGenerationService.php`, `database/migrations/**`, `resources/ts/plugins/1.router/routes.ts`, `resources/ts/navigation/**`, `resources/ts/plugins/i18n/locales/**`, `lang/**`.

## Exclusive file ownership
- `database/seeders/AcademyContractTemplateSeeder.php` — add the term paragraph to the hidden academy version.
- `app/Services/AcademySlipService.php` — student id and term fields in the slip payload.
- `resources/ts/api/academySlip.ts` — typed student id and term fields.
- `resources/ts/pages/templates/academy_slip_template.vue` — show the student id and term without breaking the A5/two-copy layout.
- `scripts/academy-slip-print-regression.mjs` — updated payload/layout assertions.
- `tests/Feature/AcademySlipTest.php` — payload and legacy-null coverage.
- `tests/Feature/AcademyContractDocumentTest.php` — placeholder/term-line generation coverage.

Forward ownership of the contract-generation, applicant, stage, commission and contract-register paths needed by Task-046 moved on 2026-11-07; this task's delivered behaviour remains historical evidence.

## Acceptance criteria
- [ ] Given the placeholder registry, when `contract.end_date` is requested for an academy/customer template, then it is declared exactly once with a human label and resolved to the contract's `Y-m-d` end date; an unknown key is still refused.
- [ ] Given the hidden academy template seeder, when it runs on a fresh or existing installation, then the template has a term line that names the start and end dates, the active version remains valid, and an issued contract's stored snapshot and reprint are unchanged.
- [ ] Given an academy contract with an end date, when its document is generated, then the rendered content includes the term start/end and no unresolved placeholder; a contract without an end date renders a clear fallback rather than an empty token.
- [ ] Given the academy slip payload, when it is fetched, then it includes student id, class, start/end dates and the existing programme/time/duration/teacher/fee fields; the A5 template shows the dates without breaking the two-copy layout.
- [ ] Given the slip reprint, when the applicant/class/duration changed after issue, then the slip reflects the current live record as before while the contract snapshot remains the historical document; no new snapshot is invented for the slip.
- [ ] Given the existing print workflow, when the slip and contract are printed, then they use their existing body classes and page sizes, no `@page` is added to a route or SFC, and the slip remains A5.
- [ ] Given the focused checks, when `php artisan test --filter=AcademySlipTest`, `php artisan test --filter=AcademyContractDocumentTest`, `node scripts/academy-slip-print-regression.mjs`, `npm run typecheck` and `npm run i18n:check` run, then they pass and print evidence is recorded.
- [ ] Given a legacy academy contract with no end date, when the slip or contract document is read, then the output shows an explicit not-set state and does not fabricate a date or a negative remaining term.

## Verification plan
- Automated: run the two feature suites on an isolated database; assert the placeholder is declared/resolved, an unknown key still fails, a new contract's rendered content contains the term line, and a legacy null end date renders the fallback.
- Template check: run `php artisan db:seed --class=AcademyContractTemplateSeeder` twice; assert one active current version per language, no duplicate template, and an issued contract's snapshot content is unchanged.
- Slip check: fetch the payload for a contract with a term and for a legacy null-term contract; assert student id/dates and no undefined key; run the print regression script.
- Print check: print the A5 slip in A4 2×A5 and A5 2×, plus the single A5 mode; assert no page-size drift, no clipped payment table and no `@page` in the SFC.
- Manual: generate a new academy contract, open its document and slip, and compare the term with the Academy Students page; repeat with a legacy contract.
- Regression: contract generation, contract print, receipt/payslip print and the academy certificate regression once Task-041 exists.
- Evidence: rendered contract excerpt, slip payload, screenshots/print output for both paper modes and the seeder idempotence output.

## Implementation notes
Add `contract.end_date` next to `contract.start_date` in `PlaceholderRegistry`, and add the resolver case beside the existing contract date resolution. Use the same `Y-m-d` formatting and a null-safe fallback. Do not add a new placeholder group or a second registry.

In the seeder, keep the template hidden (`is_active = false`) and the existing version number/status. Change only the body content and re-run the same `firstOrNew` path; if the version is already issued, do not create a second current version unless the existing pattern requires it. Prove the snapshot is not rewritten by generating a contract before and after the seed and comparing `content`/`snapshot_data`.

For the slip, extend the payload in `AcademySlipService::academyPayload` with `student_id`, `started_on`, `ends_on` and keep the existing keys. In the template, add a compact term row under the enrolment block; use the existing `formatDate` helper and a `—`/not-set label. Do not restructure the A5 card or change the two-copy CSS.

## Risks and rollback
- Risk: the seeder overwrites an active hidden template and changes a reprint. Mitigation: verify the contract snapshot is read first; the snapshot must not be regenerated for an existing contract.
- Risk: a long term line breaks the A5 two-copy layout. Mitigation: keep the line compact and test both two-copy modes at 100% print scale.
- Risk: a null end date renders as an empty token. Mitigation: null-safe resolution and a visible fallback; test the legacy row.
- Risk: a new placeholder is added but an old template cached in a snapshot still contains it. Mitigation: snapshots store rendered content, not unresolved tokens; do not re-render issued documents.
- Rollback: revert the registry/resolver and the seeder content; the slip additions are additive and can be removed independently. Existing issued documents remain unchanged.
- Stop condition: if the hidden template's current version is shared with already-issued contracts in a way that a content change would rewrite them, stop and design a new version instead.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Frozen locale key names/labels are listed for Task-043.
- [ ] The issued-snapshot check is recorded explicitly.
- [ ] Remaining limitations and follow-up tasks are explicit.