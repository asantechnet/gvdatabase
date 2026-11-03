# Task Task-026 — Localize the scope, membership and rank explanations

## Metadata
- Task ID: `Task-026`
- Title: `Localize the scope, membership and rank explanations`
- Initial status: `Ready`
- Type: `Frontend`
- Priority: `P1`
- Owner role: `Localization owner with Dari and Pashto review`
- Estimated size: `M`
- Source plan/version: `User request 2026-11-01: surface hidden HR logic and editable role seniority; repository inspection 2026-11-01`
- Requirement / behavior IDs: `I18N-ORG-001`, `I18N-ORG-002`

## Objective
Every new department-membership, Reports-to, role-seniority and refusal message exists in English, Dari and Pashto before the UI tasks are reviewed, with matching keys and no raw tokens.

## Context and problem
The UI tasks add dialogs and explanations that do not exist today: multi-department membership, primary fallback, manager removal, rank refusals, the Reports-to disclosure and the role seniority hint. If those strings are hard-coded in a page, Dari and Pashto users see English or a raw key, and a later copy change requires touching components.

The repository already has three frontend locale files under `resources/ts/plugins/i18n/locales` and three backend API catalogs under `lang`. Task-018 established the one-toast and business-code conventions. This task owns the copy additions for the new scope; it does not change UI code or API behaviour.

## Scope
### Included
- Add the new field labels and hints for Departments, Primary department, Reports to and Seniority level.
- Add the department members dialog copy: title, description, current members, add employees, search, empty states, primary badge, manager badge, manager-not-member note, multi-department note, primary-fallback note, manager-removal reason, inactive-employee reason, rank-refusal reason, save, cancel and success message.
- Add the Reports-to disclosure copy: title, who the supervisor is, what may be shared, what is not granted, separate department/task note, clearing effect, confirm, cancel, permission-required note and rank hint.
- Add the role seniority copy: column label, field label, range hint, rank explanation, protected-role reason, permission-required reason and validation message.
- Add the new business codes used by Task-022 to all three PHP catalogs under `api.errors.business`: `department_member_rank_invalid`, `department_manager_cannot_be_removed`, `employee_inactive`.
- Add the matching frontend code labels under the existing `backend.errors.business` namespace if `apiErrors.ts` maps the code; verify the namespace before adding.
- Preserve every existing key and value. Add only the new subtree entries and any missing code entries.
- Keep the three frontend JSON files structurally identical in key paths, not necessarily identical in word order.
- Review Dari and Pashto copy for natural phrasing, correct direction and short dialog lines; do not machine-translate technical permission names when a reviewed local label already exists.

### Excluded
- Vue components, pages and API contracts; Tasks 023, 024 and 025 own them.
- PHP controllers, validation, models, migrations and tests.
- Renaming existing locale keys or restructuring the JSON files.
- Translating unrelated screens or every backend error.
- Changing RTL CSS, typography or layout; only copy is in scope.

## Preconditions and assumptions
- The frontend locale files are `en.json`, `fa-AF.json` and `ps-AF.json`; the backend catalogs are `lang/en/api.php`, `lang/fa/api.php` and `lang/ps/api.php`. Verify the actual names before editing and record any mismatch.
- Existing page copy uses a flat `common.ui` camelCase convention and page-specific subtrees. Follow the surrounding convention instead of introducing a new nested scheme.
- The UI tasks will reference a frozen key list from this task. If a key changes after the UI lands, update both in the same handoff; do not leave a raw token.
- Assumption to validate: a native Dari/Pashto reviewer is available for the final read. If not, record the untranslated or machine-assisted entries explicitly rather than claiming a review.
- The frontend `apiErrors.ts` maps a business code to `backend.errors.<code>` or an equivalent prefix. Confirm the exact lookup before adding labels; do not add a second mapping.
- The existing `common.fields.supervisor` label already renders "Reports to"; reuse it if the wording matches, otherwise add one new label and remove the duplicate use, not the old key.

## Dependencies
- `None` — copy can be prepared before the UI tasks land. Keep the key list stable for the three UI consumers.

## Contracts and boundaries
- Key groups: `common.fields.*` for the four field labels; `common.ui.departmentMembers*`, `common.ui.departmentManager*`, `common.ui.primaryDepartment*` for membership; `common.ui.supervisorDisclosure*` and `common.ui.reportsTo*` for the reporting-line disclosure; `pages.settings.rolecards.rolecards.seniority*` for the role editor.
- Error catalogues: `api.errors.business.department_member_rank_invalid`, `api.errors.business.department_manager_cannot_be_removed` and `api.errors.business.employee_inactive` in all three PHP files. The frontend labels use the namespace actually read by `apiErrors.ts`.
- Compatibility: no existing key is removed, renamed or re-indented into another subtree; `git diff` may show additions and the minimal surrounding commas only.
- Quality: every new key has a value in all three locales, no value is an empty string, no value is the English sentence copied into both Dari and Pashto, and no value contains an untranslated placeholder name.
- Placeholders and plurals: reuse the existing `vue-i18n` placeholder syntax and plural rules; do not introduce a second interpolation style. Any count-bearing string must use the existing plural pattern.
- Integration seam: UI tasks read these keys and do not add local English defaults. The API returns codes; the frontend error mapper resolves them through these labels.
- Files this task must not modify: any `resources/ts/**/*.vue` or `*.ts` file, any `app/**` file, `routes/api.php`, `tests/**`.

## Exclusive file ownership
- `resources/ts/plugins/i18n/locales/en.json` — English labels, hints and disclosure copy.
- `resources/ts/plugins/i18n/locales/fa-AF.json` — Dari labels, hints and disclosure copy.
- `resources/ts/plugins/i18n/locales/ps-AF.json` — Pashto labels, hints and disclosure copy.
- `lang/en/api.php` — English business codes.
- `lang/fa/api.php` — Dari business codes.
- `lang/ps/api.php` — Pashto business codes.

## Acceptance criteria
- [ ] Given the three frontend locale files, when their nested key paths are compared, then every new key added by this task exists in all three with a non-empty value and no existing key was removed.
- [ ] Given a UI task references a new key, when the page renders in English, Dari and Pashto, then the label is translated and no raw key or English fallback appears.
- [ ] Given the three PHP catalogs, when the new business codes are looked up, then each returns a specific sentence rather than `api.errors.business.default` or `api.errors.server.default`.
- [ ] Given `apiErrors.ts` maps a new code, when the code is translated, then the matching frontend label exists under the exact namespace and the message is not a generic fallback.
- [ ] Given the Reports-to disclosure, when it is read in all three languages, then it states what may be shared, what is not granted, that department membership and task assignment are separate, and what clearing does.
- [ ] Given the role seniority hint, when it is read in all three languages, then it states that higher outranks lower and that the level is not a permission grant.
- [ ] Given the department membership copy, when it is read in all three languages, then it states that one employee may belong to several departments, that the save changes only this department, that a primary member's removal promotes another membership or leaves none, and why the manager cannot be removed.
- [ ] Given the JSON files, when they are parsed, then they are valid JSON with no duplicate keys and no trailing comma; `npm run i18n:check` passes.
- [ ] Given the task diff, when it is reviewed, then it contains copy and catalogue additions only, with no component, controller or schema change.
- [ ] Given the longest Dari and Pashto strings, when they are measured against the dialog layout, then they fit at 390 px without truncating the confirm/cancel controls; the UI task records the actual check.

## Verification plan
- Automated: parse all three JSON files with `python3 -m json.tool` or the project's checker; run `npm run i18n:check`; run a key-parity script that compares nested paths and reports missing or extra new keys.
- Catalogue check: `php artisan tinker` or a focused test that calls the translator for each new business code in all three locales; confirm no fallback string is returned.
- Source check: search the UI tasks' key references against the locale files; every referenced key must exist. Search for the three business codes and confirm the frontend mapper namespace matches.
- Manual: read every new Dari and Pashto string with the reviewer; check direction, punctuation, placeholders and length at 390 px in the dialog mock or the implemented dialog.
- Regression: `git diff --stat` shows only the six owned files; spot-check existing keys such as `common.fields.supervisor` and `common.statuses.underReview` are unchanged.
- Evidence: key-parity output, JSON parse result, i18n check result, translator review note, and the longest-string viewport note.

## Implementation notes
Add keys next to their related existing groups, preserving indentation and file ordering. Use the current `common.ui` flat camelCase names for shared dialog copy and the existing `pages.settings.rolecards.rolecards` subtree for the role page; do not create a new top-level namespace. Keep each dialog line short enough for a 390 px width; put detailed scope bullets in the disclosure body, not in a single dense paragraph. For PHP, add the three codes under the existing `errors.business` array in each file with the same key order. For frontend code labels, confirm whether `apiErrors.ts` reads `backend.errors.business.<code>` before adding; if it reads the server message instead, do not duplicate the sentence in JSON and record that in the progress note.

## Risks and rollback
- Risk: key drift between the locale files and the UI tasks. Mitigation: freeze the key list in this task's progress notes, run the parity check, and require the UI task to fail on a missing key rather than hard-code English.
- Risk: machine translation reads unnatural or reverses a technical term. Mitigation: named reviewer, short sentences, reuse reviewed permission labels, and record any unreviewed string.
- Risk: a JSON edit reorders the whole file and creates a noisy diff. Mitigation: insert only near related keys and do not run a formatter that rewrites the file.
- Data migration: none. Rollback is a copy-only revert; no runtime data depends on the keys.
- Stop condition: if a required concept has no agreed translation, leave the UI task blocked and record the exact term and reviewer rather than shipping English in the Dari or Pashto file.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] The reviewed key list and any unreviewed strings are recorded for the UI tasks.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
