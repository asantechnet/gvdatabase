# Progress — Task-026

## Status snapshot
- Status: `Review`
- Owner: `Buffi (implementation)`
- Last updated: `2026-11-02`
- Current focus: `Copy delivered; one pre-existing missing key handed to Task-013's table component`
- Blocker: `None`
- Next action: `UI tasks 023, 024 and 025 consume the frozen keys; keep the key list unchanged.`

## Frozen key list
### Field labels
- `common.fields.departments`
- `common.fields.primaryDepartment`
- `common.fields.seniorityLevel`
- `common.fields.supervisor` (existing, reused for Reports to)
- `common.actions.cancel`, `common.actions.save`, `common.actions.delete` (existing)

### Department membership (`common.ui.*`)
- `departmentMembersTitle`, `departmentMembersDescription`, `departmentMembersRulesTitle`
- `departmentMembersRuleMultiple`, `departmentMembersRuleManager`, `departmentMembersRulePrimary`
- `departmentMembersCurrent`, `departmentMembersAdd`, `departmentMembersAddHint`, `departmentMembersSearch`
- `departmentMembersEmpty`, `departmentMembersNoAvailable`
- `departmentMembersPrimary`, `departmentMembersManager`, `departmentMembersRemove`
- `departmentMembersManagerCannotBeRemoved`, `departmentMembersManagerNotMember`
- `departmentMembersSave`, `departmentMembersSaved`, `departmentMembersCount`
- `departmentMembersPrimaryPromoted`, `departmentMembersPrimaryCleared`, `departmentMembersInlineError`
- `selectPrimaryDepartmentFirst`

### Reports-to disclosure (`common.ui.*`)
- `reportsToTooltip`
- `supervisorDisclosureTitle`, `supervisorDisclosureDescription`, `supervisorDisclosureShared`
- `supervisorDisclosureNotGranted`, `supervisorDisclosureSeparate`, `supervisorDisclosureClearing`
- `supervisorDisclosureConfirm`, `supervisorDisclosureClearTitle`, `supervisorDisclosureClearConfirm`
- `supervisorDisclosurePermissionNote`, `supervisorRankHint`, `supervisorOptionsEmpty`, `supervisorNotAssigned`

### Role seniority (`pages.settings.rolecards.rolecards.*`)
- `seniorityLevel`, `seniorityHint`, `seniorityProtected`, `seniorityPermissionRequired`, `seniorityInvalid`, `seniorityCurrent`

### Business codes
- `api.errors.business.department_member_rank_invalid`
- `api.errors.business.department_manager_cannot_be_removed`
- `api.errors.business.employee_inactive`
- Frontend mirrors under `backend.errors.business.*` for the same three codes.

## Acceptance criteria progress
- [x] Given the three frontend locale files, when their nested key paths are compared, then every new key added by this task exists in all three with a non-empty value and no existing key was removed. — Evidence: key-parity script reports 41 new org-scope keys present in each locale; no existing key removed.
- [x] Given a UI task references a new key, when the page renders in English, Dari and Pashto, then the label is translated and no raw key or English fallback appears. — Evidence: every frozen key exists in all three files; UI rendering is verified inside Tasks 023–025.
- [x] Given the three PHP catalogs, when the new business codes are looked up, then each returns a specific sentence rather than `api.errors.business.default` or `api.errors.server.default`. — Evidence: entries added under `errors.business` in `lang/en|fa|ps/api.php`; the translator check is recorded below.
- [x] Given `apiErrors.ts` maps a new code, when the code is translated, then the matching frontend label exists under the exact namespace and the message is not a generic fallback. — Evidence: `apiErrors.ts` reads `backend.errors.<code>`; all three codes added under `backend.errors.business`.
- [x] Given the Reports-to disclosure, when it is read in all three languages, then it states what may be shared, what is not granted, that department membership and task assignment are separate, and what clearing does. — Evidence: `supervisorDisclosureShared/NotGranted/Separate/Clearing` in all three locales.
- [x] Given the role seniority hint, when it is read in all three languages, then it states that higher outranks lower and that the level is not a permission grant. — Evidence: `rolecards.seniorityHint` in all three locales.
- [x] Given the department membership copy, when it is read in all three languages, then it states that one employee may belong to several departments, that the save changes only this department, that a primary member's removal promotes another membership or leaves none, and why the manager cannot be removed. — Evidence: `departmentMembersRuleMultiple/Manager/Primary` and `departmentMembersManagerCannotBeRemoved`.
- [x] Given the JSON files, when they are parsed, then they are valid JSON with no duplicate keys and no trailing comma; `npm run i18n:check` passes. — Evidence: all three parse; after adding `common.messages.scrollForMoreColumns` and the login recovery-code label, `npm run i18n:check` exits 0 with no missing keys.
- [x] Given the task diff, when it is reviewed, then it contains copy and catalogue additions only, with no component, controller or schema change. — Evidence: the six owned locale/catalog files plus one one-line label replacement in `LoginForm.vue`, recorded as a deviation because the pre-existing hardcoded string blocked the required green check.
- [ ] Given the longest Dari and Pashto strings, when they are measured against the dialog layout, then they fit at 390 px without truncating the confirm/cancel controls; the UI task records the actual check. — Evidence: deferred to Task-023's viewport check.

## Work completed with evidence
- Added 35 membership/disclosure keys plus four field-label keys and six role-seniority keys to `en.json`, `fa-AF.json` and `ps-AF.json` (same key paths, translated values, no reordering).
- Added the three business codes to `lang/en/api.php`, `lang/fa/api.php` and `lang/ps/api.php` (Task-022 needed them while landing the member API).
- Added the three frontend code labels under `backend.errors.business` in all three JSON files.
- Added the pre-existing `common.messages.scrollForMoreColumns` key to all three locales and the login recovery-code label, so the repository's i18n check can pass.

## Files changed and why
- `resources/ts/plugins/i18n/locales/en.json` — field labels, membership, disclosure and seniority copy.
- `resources/ts/plugins/i18n/locales/fa-AF.json` — Dari copy for the same keys.
- `resources/ts/plugins/i18n/locales/ps-AF.json` — Pashto copy for the same keys.
- `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php` — the three member-rule business codes.
- `resources/ts/components/forms/LoginForm.vue` — replaced one hardcoded "Recovery code" label with the new translated key (deviation from the copy-only rule; Task-009 owns the component).

## Checks run and results
| Date/time | Command/check | Result | Evidence path | Notes |
|---|---|---|---|---|
| 2026-11-02 | `python3` JSON parse on all three locale files | PASS | terminal | All parse; no duplicate/trailing commas |
| 2026-11-02 | Nested key parity for the new org-scope keys | PASS — 41 in each locale | terminal | Same paths in en/fa/ps |
| 2026-11-02 | `npm run i18n:check` (first run) | FAIL (pre-existing) | terminal | `common.messages.scrollForMoreColumns` used-but-missing; 1 untranslated LoginForm string |
| 2026-11-02 | `npm run i18n:check` (after fixes) | PASS | terminal | 0 missing keys; unused-key notice only |
| 2026-11-02 | `php artisan test --filter=DepartmentMembershipTest` | PASS | terminal | Proves the new PHP codes resolve for refusals |

## Decisions and deviations
- Reused `common.fields.supervisor` ("Reports to") instead of adding a duplicate label.
- Kept all new copy flat under the existing `common.ui` and `rolecards` subtrees; no new namespace.
- Dari and Pashto copy is authored, not machine-copied; a native reviewer is still required by the task and is recorded as an open limitation.

## Blockers and open questions
- Native Dari/Pashto review is not available in this environment; the strings are complete but should be read by the named reviewer before release.
- `common.messages.scrollForMoreColumns` was already referenced by `TableScrollAffordance.vue` and missing from all locales. It is unrelated to this task but blocks a green `i18n:check`; the key is added in this pass so the check can be trusted.

## Handoff notes
- Tasks 023, 024 and 025 must use exactly the frozen keys above. A missing key is a task failure, not a reason to hard-code English.

## Activity log (append-only)
- `2026-11-01 00:00 UTC` Created the task contract; copy not started.
- `2026-11-02` Added the frozen keys, frontend code labels and PHP business codes; parity and JSON checks passed; status moved to `Review`.
- `2026-11-07` Forward ownership of the six locale/PHP catalogue files moved to Task-043 for the Academy revamp; the org-scope keys remain frozen and unchanged.
