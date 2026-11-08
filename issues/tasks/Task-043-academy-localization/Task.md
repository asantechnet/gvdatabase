# Task Task-043 — Localize the Academy section in English, Dari and Pashto

## Metadata
- Task ID: `Task-043`
- Title: `Localize the Academy section in English, Dari and Pashto`
- Initial status: `Ready`
- Type: `Documentation`
- Priority: `P1`
- Owner role: `Localization owner with native Dari/Pashto review`
- Estimated size: `M`
- Source plan/version: `Academy revamp planning session 2026-11-07; client requirement that all UI supports RTL/LTR and Arabic/Persian-family locales, and the repository's three-locale convention. Repository inspection of en.json, fa-AF.json, ps-AF.json, lang/*/api.php and the Academy task key lists on 2026-11-07.`
- Requirement / behavior IDs: `ACADEMY-I18N-001`, `ACADEMY-I18N-002`

## Objective
Add every new Academy label, hint, empty state, print caption and business-code sentence to the three frontend locales and the three PHP API catalogs, with matching placeholders and RTL-safe copy. The Academy section is fully translated in English, Dari and Pashto; no raw key or English fallback appears in a non-English locale.

## Context and problem
The repository keeps three frontend locale JSON files and three PHP business-code catalogs. Task-026 owns the org-scope keys and is delivered; the Academy tasks introduce a new `academy.*` namespace, navigation labels, print captions and specific business codes. If each UI task edits the same JSON files independently, the files conflict and key parity drifts. This task is the single writer for the six catalogs, using the frozen key list produced by Tasks 039-042.

The task is copy/catalog work only. It does not change a component, controller, schema or print layout.

## Scope
### Included
- All keys under `academy.*` used by the overview, classes, students, catalogue, attendance, certificates and duration-months surfaces.
- The responsible-employee keys required by Task-046: `serviceContracts.responsibleEmployees`, `serviceContracts.responsibleEmployeesOptional`, `serviceContracts.responsibleEmployeesHint`, `serviceContracts.placeholders.responsibleEmployees`, `agencyStaff.responsibleEmployees`, `educational.responsibleEmployees` and `common.fields.responsibleEmployees`, plus `api.errors.business.applicant_stage_service_not_supported` in all three PHP catalogs.
- `common.navigation.academy` and its child labels; the Academy section title/description.
- Print captions/labels under `documents.academyAttendance.*`, `documents.academyCertificate.*` and the new `documents.academySlip.*` fields.
- PHP business codes: `business.academy_term_undefined`, `business.academy_class_ended`, `business.academy_class_full`, `business.academy_enrollment_invalid`, `business.academy_certificate_not_eligible`, `business.academy_certificate_revoked`, `business.academy_certificate_duplicate` and any additional code returned by Tasks 037-041.
- Field labels, hints, empty states, validation messages, status labels (`active`, `completed`, `withdrawn`, `cancelled`, `present`, `absent`, `late`, `excused`, `issued`, `revoked`), permission/read-only copy and confirmation text.
- Placeholder parity for counts, dates, money and certificate tokens.
- Key-parity and `npm run i18n:check` evidence; native review of the longest Dari/Pashto strings.

### Excluded
- Changing any component, controller, service, model, migration, route, nav file or test.
- Translating user-entered data, applicant names, class names, programme names, teacher names or certificate body tokens.
- Adding a new locale or changing the locale switcher.
- Reformatting the JSON files, reordering existing keys or removing existing keys.
- Changing the English certificate layout or adding a bilingual certificate design; the template copy is editable but the default remains English for visa use.
- Fixing unrelated missing keys outside the Academy namespace.

## Preconditions and assumptions
- Tasks 039-042 have frozen the key names and list them in their `Progress.md`/handoff notes.
- The three frontend locales are `en.json`, `fa-AF.json` and `ps-AF.json`; the PHP catalogs are `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php`.
- Existing `common.navigation` and `documents.*` groups remain stable; new keys are added beside related existing keys.
- RTL is driven by `fa-AF`/`ps-AF`; no separate RTL stylesheet is required for copy.
- The native reviewer is named by the localization owner; if no reviewer is available, the task records the limitation rather than claiming a native review.
- The frozen namespace is `academy.*`; UI tasks must not invent a second top-level namespace.

## Dependencies
- `Task-039` — overview/classes/students/catalogue key list.
- `Task-040` — attendance and teacher-timesheet key list.
- `Task-041` — certificate page/dialog/print key list and business codes.
- `Task-042` — slip term/student-id key list.

## Contracts and boundaries
- Frontend keys: each new key exists in all three JSON files with the same nested path and the same placeholder names; no key is English-only in `fa-AF`/`ps-AF`.
- PHP codes: each new code returns a specific sentence under `errors.business` in all three catalogs; no code falls back to `api.errors.business.default` or the server default.
- Placeholders: `{count}`, `{amount}`, `{currency}`, `{date}`, `{name}`, `{class}`, `{program}`, `{certificate_number}` and any date/term tokens keep identical names and order across locales. ICU/plural forms use the existing repository style; no new plural library.
- Terminology: use the existing academy terms (`program`, `class`, `time`, `duration`, `teacher`, `enrolment`/`enrollment`, `term`, `certificate`) consistently; do not introduce a second word for the same concept.
- Copy quality: short labels for table headers/nav; full sentences for hints/confirmations; no raw business code shown to the user; no English fallback in non-English strings.
- Compatibility: existing keys and the three delivered org-scope key sets are unchanged; no file reformat or key reorder.
- Files this task must not modify: anything outside the six owned catalogs. In particular, no Vue/TS/PHP class file, route, nav, test or migration.

## Exclusive file ownership
- `resources/ts/plugins/i18n/locales/en.json` — English Academy keys and business labels.
- `resources/ts/plugins/i18n/locales/fa-AF.json` — Dari Academy keys and business labels.
- `resources/ts/plugins/i18n/locales/ps-AF.json` — Pashto Academy keys and business labels.
- `lang/en/api.php` — English Academy business codes.
- `lang/fa/api.php` — Dari Academy business codes.
- `lang/ps/api.php` — Pashto Academy business codes.

Forward ownership of these six files moves from Task-026 on 2026-11-07; Task-026's org-scope keys remain frozen and must not be changed.

## Acceptance criteria
- [ ] Given all Academy surfaces, when their keys are compared, then every key referenced by Tasks 039-042 exists under `academy.*` or `common.navigation.academy` in en, fa-AF and ps-AF, with non-empty values and matching placeholders.
- [ ] Given the three PHP catalogs, when the new business codes are translated, then `business.academy_term_undefined`, `business.academy_class_ended`, `business.academy_class_full`, `business.academy_enrollment_invalid`, `business.academy_certificate_not_eligible`, `business.academy_certificate_revoked` and `business.academy_certificate_duplicate` return specific sentences, never a generic default.
- [ ] Given the UI in Dari and Pashto, when the Academy nav, KPI cards, class/student tables, attendance grid and certificate dialogs render, then labels are translated, no raw key or English fallback appears, and RTL alignment is correct.
- [ ] Given the longest labels, when measured at 390 px, then navigation, table headers, dialogs and print controls do not truncate or overlap; the reviewer records the exact strings checked.
- [ ] Given the JSON files, when parsed, then they are valid JSON with no duplicate keys and no trailing comma; `npm run i18n:check` and the key-parity check pass.
- [ ] Given the task diff, then it contains only the six catalogue files and no component, controller, schema, route or test change; existing org-scope keys are unchanged.

- [ ] Given the responsible-employee surfaces in Task-046, when the six catalogs are checked, then the seven frontend keys exist in all three locales with matching placeholders and `api.errors.business.applicant_stage_service_not_supported` returns a specific sentence in all three PHP catalogs.

## Verification plan
- Automated: `npm run i18n:check`; a key-parity script that walks the three JSON trees, compares the Academy subtree and reports missing/extra keys and placeholder mismatches; `python3 -m json.tool` for each file.
- PHP check: a focused test or `php artisan tinker` loop that translates every new business code in en/fa/ps and asserts no default fallback.
- Source check: search the Task 039-042 code for every `$t('academy...')`/`translate('academy...')` reference and assert it exists in all three locales; search for raw English strings in the new pages.
- Manual: read the Dari/Pashto copy with the native reviewer; check nav/dropdown, 390 px dialogs, long certificate/attendance labels and RTL punctuation/numbers.
- Regression: compare the existing org-scope keys and a sample of unrelated keys against `HEAD`; assert unchanged.
- Evidence: parity output, i18n-check output, PHP translator output, reviewer note and the longest-string viewport note.

## Implementation notes
Add keys next to their related groups and preserve indentation/order. Keep the `academy` namespace flat and predictable (`academy.overview.*`, `academy.classes.*`, `academy.students.*`, `academy.catalog.*`, `academy.attendance.*`, `academy.certificates.*`, `academy.common.*`). Use the existing `common.actions`/`common.statuses` when a label already exists; do not duplicate a generic action label.

For PHP, add the codes under the existing `errors.business` array in each file in the same order. Use the repository's existing sentence style and avoid technical words such as `422` or `null` in user copy. If a UI task used a key that is not frozen, resolve it with that task rather than silently renaming; record the decision.

Do not reformat the JSON files with a tool that changes indentation globally. Add the smallest diff possible. If the native reviewer cannot cover a string, mark it `review pending` in the handoff instead of claiming a pass.

## Risks and rollback
- Risk: a UI task invents a key after this task is written. Mitigation: the dependency list and the source check catch it; reopen the UI task or add the key with the same namespace.
- Risk: a long Dari/Pashto string breaks a table. Mitigation: short header labels and a 390 px review; the UI task keeps the layout flexible.
- Risk: JSON merge conflicts with another task. Mitigation: this task is the only writer of the six files; Task-026's keys are frozen and no other task edits them.
- Rollback: revert the six files to their pre-task versions; no code/schema change depends on the copy at boot.
- Stop condition: if a product term is ambiguous (for example whether `term` means the class term or the contract term), stop and ask the product owner before translating it inconsistently.

## Handoff checklist
- [ ] Contract and file ownership respected, including the Task-026 handoff.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Every new business code is listed with its English sentence.
- [ ] Native review coverage and any pending strings are explicit.
- [ ] Remaining limitations and follow-up tasks are explicit.
