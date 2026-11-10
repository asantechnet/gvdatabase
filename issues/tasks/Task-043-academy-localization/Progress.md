# Progress — Task-043

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
- [x] Given all Academy surfaces, when their keys are compared, then every key referenced by Tasks 039-042 exists under `academy.*` or `common.navigation.academy` in en, fa-AF and ps-AF, with non-empty values and matching placeholders.
- [x] Given the three PHP catalogs, when the new business codes are translated, then `business.academy_term_undefined`, `business.academy_class_ended`, `business.academy_class_full`, `business.academy_enrollment_invalid`, `business.academy_certificate_not_eligible`, `business.academy_certificate_revoked` and `business.academy_certificate_duplicate` return specific sentences, never a generic default.
- [ ] Given the UI in Dari and Pashto, when the Academy nav, KPI cards, class/student tables, attendance grid and certificate dialogs render, then labels are translated, no raw key or English fallback appears, and RTL alignment is correct.
- [ ] Given the longest labels, when measured at 390 px, then navigation, table headers, dialogs and print controls do not truncate or overlap; the reviewer records the exact strings checked.
- [x] Given the JSON files, when parsed, then they are valid JSON with no duplicate keys and no trailing comma; `npm run i18n:check` and the key-parity check pass.
- [x] Given the task diff, then it contains only the six catalogue files and no component, controller, schema, route or test change; existing org-scope keys are unchanged.
- [x] Given the responsible-employee surfaces in Task-046, when the six catalogs are checked, then the seven frontend keys exist in all three locales with matching placeholders and `api.errors.business.applicant_stage_service_not_supported` returns a specific sentence in all three PHP catalogs.

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
- `resources/ts/plugins/i18n/locales/en.json`
- `resources/ts/plugins/i18n/locales/fa-AF.json`
- `resources/ts/plugins/i18n/locales/ps-AF.json`
- `lang/en/api.php`
- `lang/fa/api.php`
- `lang/ps/api.php`

## Decisions and deviations
- Implementation follows the Task.md contract; no scope deviation was introduced. Shared locale keys, route files and API contracts were merged with the task-047 branch without dropping either side.

## Blockers and open questions
- No implementation blocker. Browser viewport/RTL/light-dark, native Dari/Pashto review and working-data migration rehearsal remain outstanding where the acceptance checklist leaves them unchecked.

## Activity log (append-only)
- `2026-11-09` — Task-043 verified on the merged academy branch after the task-047 merge; task status updated to Review.
