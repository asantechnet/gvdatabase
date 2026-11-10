# Progress — Task-047

## Status snapshot
- Status: `Review`
- Owner: `Full-stack Laravel/Vue implementer`
- Last updated: `2026-11-09`
- Current focus: `Delivered and verified on the merged academy branch`
- Blocker: `No code blocker. Manual viewport/RTL and native-copy review remain unrun.`
- Next action: `Review the merged UI at 390/768/1280 px in LTR/RTL, then close the task.`

## Acceptance criteria progress
- [x] Given a database with existing countries and universities, when the notes migration runs twice, then both tables have a nullable `notes` column, existing rows and other columns are unchanged, and the second run makes no schema or data change.
- [x] Given a database with existing CRM interactions, when the applicant-scope migration runs twice, then `crm_interactions.applicant_id` is nullable, indexed and foreign-keyed, every existing row keeps a null applicant id and all existing interaction fields, and the second run makes no further change.
- [x] Given a university or country with notes and interactions, when an authorized caller reads the global relationship endpoint, then the response contains the profile fields, global notes and all interactions, while an unauthorized caller receives 403 and an unsupported type or missing record receives 404.
- [x] Given Educational Data on `countries` or `universities`, when a row's relationship or phone/email quick-contact action is used, then the correct popup or contact menu opens in editable mode with the matching master-data edit permission and in read-only mode without it, with no raw translation key and no unrelated catalogue target.
- [x] Given a visa case, when its contact/relationship controls render, then the only country target is `applicant.details.country_id` and the only university targets are rows in `applicant.universities`; no global university/country picker, search or unrelated catalogue contact is offered.
- [x] Given a visa case country or attached university with a phone/email, when the quick-contact action is used, then the visitor-follow-up `ContactActionMenu` behavior is preserved; with case-edit permission the interaction is written with the applicant id, and without it the call/email still opens but no interaction is written.
- [x] Given two visa cases that both track the same university, when one case reads its relationship records, then only interactions whose `applicant_id` matches that case are returned; the other case's interactions are absent, and global Educational Data interactions are not presented as case records.
- [x] Given a visa case, when `GET /api/applicants/{applicant}/relationships` is read, then it returns only the selected country, attached universities and case-linked interactions with target names, channels, titles, notes, contacted dates and creators; a caller without case view scope receives 403.
- [x] Given a case-linked interaction create, update or delete, when the target is not the selected country or an attached university, then the request is refused with 422 `business.relationship_not_linked_to_case` and nothing is written; when it is linked, the interaction stores the applicant id and cannot be moved to another applicant.
- [x] Given the visa case page, when case-linked records exist, then a dedicated relationship-records card displays them newest first with target and interaction context, an empty state appears when none exist, and no global country/university notes or other applicants' records are shown.
- [x] Given an existing applicant or visitor relationship popup, when it loads, opens, saves notes, adds, edits or deletes an interaction, then it uses the existing endpoints and payload unchanged and no university/country case field or entity label leaks into it.
- [x] Given a global country/university relationship, when notes or interactions are saved, then `edit_university`/`edit_country` is required; given a case-scoped interaction, the existing case-edit permission and manage scope are required, and global notes are neither displayed nor mutated from the case.
- [x] Given the parent contract of a service line, when `GET /api/applicant-contracts/{line}` is read, then the payload includes a `contract` object with the contract number, service, role, parent, status/display status, start/end/days/expiry, signatory, counterparty fields, template/version, country/language, cancellation, payment when permitted and timestamps; no sibling service line is present.
- [x] Given the service-case workspace, when it renders a line with a parent contract, then the contract section shows every structured contract field in a readable grid, the existing stage/history/tasks/finance sections remain, null values have explicit unavailable text, and the page does not show sibling-line data.
- [x] Given the applicant case edit form after Task-046, when a user with `assign_responsible_employees` edits a scholarship/private case, then it sends and renders the plural responsible employees, offers no singular admission field, and a case-free service still has no stage controls.
- [x] Given the relationship and contract changes, when an unsupported type or missing relationship/line is requested, then the server returns 404, a forbidden write returns 403/422 with no write, and the existing API notifier remains the only API error presenter.
- [ ] Given the focused suites and frontend checks, when `php artisan test --filter=UniversityCountryRelationshipTest`, `--filter=ApplicantRelationshipRecordsTest` and `--filter=ServiceCaseContractPayloadTest` run on an isolated database, `npm run typecheck` and `npm run i18n:check` run, then they exit 0 and the 390/768/1280 px LTR/RTL light/dark manual pass has no overflow, missing key or unreadable control.

## Work completed with evidence
- Country/university notes schema, applicant-scoped CRM interactions, global and case-scoped relationship APIs, quick-contact actions, the case relationship-records card and the nested service-case contract payload were delivered and merged into `feature/academy-revamp`.
- `UniversityCountryRelationshipTest`, `ApplicantRelationshipRecordsTest` and `ServiceCaseContractPayloadTest` pass in the focused 83-test run.
- Case targets stay limited to `applicant.details.country_id` and `applicant.universities`; global notes/interactions are not exposed as case records.

## Files changed and why
- `database/migrations/2026_11_07_000010_add_notes_to_countries_and_universities.php`, `2026_11_07_000011_add_applicant_scope_to_crm_interactions.php` — nullable notes and applicant-scoped interaction link.
- `app/Models/Country.php`, `University.php`, `CrmInteraction.php`, `app/Http/Controllers/CrmInteractionController.php`, `app/Http/Resources/CrmRelationshipResource.php`, `CrmInteractionResource.php`, `routes/crm.php` — relationship read/write and case-link validation.
- `resources/ts/api/crmRelationships.ts`, `resources/ts/components/crm/CrmRelationshipDetails.vue`, `CaseRelationshipRecords.vue`, `resources/ts/components/ContactActionMenu.vue`, `resources/ts/pages/applicant-case.vue` — case-scoped popups, quick contact and records card.
- `app/Http/Resources/ApplicantContractResource.php`, `resources/ts/pages/service-case.vue`, `resources/ts/api/serviceCases.ts` — nested parent-contract payload and structured contract grid.
- `tests/Feature/UniversityCountryRelationshipTest.php`, `ApplicantRelationshipRecordsTest.php`, `ServiceCaseContractPayloadTest.php` — focused coverage.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-09 | Focused Academy/Task-046/Task-047 suites | isolated testing DB | PASS | terminal | 83 tests / 953 assertions across the merged suites. |
| 2026-11-09 | `npm run typecheck` | working tree | PASS — exit 0 | terminal | |
| 2026-11-09 | `npm run i18n:check` | working tree | FAIL — exit 1 | terminal | Pre-existing unrelated keys only; no Task-047 key reported. |
| 2026-11-09 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | repository task set | PASS — 45 tasks, 0 errors | terminal | Required files/sections, statuses, dependencies, cycles, exclusive ownership and INDEX.md valid. |

## Decisions and deviations
- One task covers the two related case surfaces because both expose the truth of a single applicant case: the relationship popup and case record card for the selected country/attached universities, and the contract behind a service line.
- "Visa case" means the applicant-level `/applicants/:id` workspace. Its selected country is `applicant.details.country_id`; its attached universities are `applicant.universities`. The contract remains on `/service-cases/:line`.
- `crm_interactions.applicant_id` is the case link. Global Educational Data interactions have a null applicant id; legacy interactions stay null and are not guessed into a case.
- Case-scoped interaction writes use the existing case-edit/manage permission, not master-data edit. Global notes and global interactions still require `edit_university`/`edit_country`.
- Global country/university notes are not case records and are hidden from the visa case. A case note is an interaction with channel `note` and the applicant id.
- The visa case exposes no country/university picker or global catalogue contact; only the selected country and attached universities are contactable.
- `educational-master-data.vue` remains Task-035-owned for the audit rail; this task is a dependent post-owner additive edit, not a parallel writer.
- `applicant-case.vue`, `ApplicantContractResource`, `service-case.vue` and `serviceCases.ts` are forward-owned from Task-046 and Task-028; no parallel writer remains.
- The nested contract uses the existing `ContractResource` without editing it. Raw contract `content` and `snapshot_data` remain in the payload for document rendering, but the case UI renders structured fields only.
- `routes/api.php` remains Task-038-owned. Task-038 gets a guarded `routes/crm.php` include; this task owns the route file.
- Locale files remain Task-043-owned. This task reuses existing keys and treats a missing key as a handoff, not a reason to edit the shared catalogues.

## Blockers and open questions
- No implementation blocker. Product/CRM review should confirm that case-scoped interaction writes may use the case-edit permission without master-data edit permission; the task assumes yes and keeps global notes master-data-only.
- Product review should confirm that "all contract data" means the structured `ContractResource` fields on the case page, with raw content/snapshot reserved for document rendering. If raw content must be displayed, that is a separate presentation/security decision.
- If relationship records must be scoped to an `applicant_contract` line rather than the applicant-level visa case, stop and record that schema decision; this task deliberately uses `applicant_id`.

## Handoff notes
- Delivered on the merged academy branch; the guarded `routes/crm.php` include, Educational Data audit rail and Task-046 plural API are in place.
- Preserve Task-028's line-isolation contract: one line, no sibling payload; the nested contract must not eager-load `applicantContracts`.
- Keep the case page's plural responsible-employee control; do not restore the singular admission field.
- Manual viewport/RTL and native Dari/Pashto review remains unrun.
- Add row-level phone/email quick contact to every country/university row in Educational Data and only to the selected country/attached universities on the visa case; pass the contactable type/id plus case id only when the relevant edit permission is present.
- Preserve Task-035's audit rail and existing Educational Data table behavior while adding the relationship/quick-contact controls.
- Keep the visa case records card on the aggregate endpoint; never merge in global history or another applicant's records.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the university/country relationship and contract-aware case request; no product code changed.
- `2026-11-07` Revised the task for the visa-case clarification: only the selected country/attached universities are contactable, and relationship records require an applicant/case link and a case-only display.
- `2026-11-07` Reconciled the Educational Data page wording with Task-035 ownership, mirrored all 17 acceptance criteria here, updated the INDEX ownership/contracts/decisions, and re-ran validation (45 tasks, 0 errors) plus `git diff --check` (exit 0).
- `2026-11-09` Merged Task-047 into `feature/academy-revamp`, verified the relationship and contract payload suites in the focused 83-test run, and moved the task to Review.
